<?php
/**
 * Modulo de pago PAYCOMET
 *
 * Este módulo de pago permite realizar pagos con tarjeta de credito mediante la pasarela PAYCOMET
 * PAYCOMET - Pasarela de pagos PCI-DSS Nivel 1 Multiplataforma
 *
 * @package    paycomet.php
 * @author     PAYCOMET <info@paycomet.com>
 * @copyright  2019 PAYCOMET
 * @version    2.0
 *
**/

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);


// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$success = ($_POST["Response"]=="OK")?1:0;
$response = $_POST["Response"];
$invoiceId = $_POST["Order"];
$transactionId = $_POST["AuthCode"];
$paymentAmount = $_POST["AmountEur"];
$paymentFee = $_POST["x_fee"];
$hash = $_POST["ExtendedSignature"];
$TpvID = $_POST['TpvID'];
$TransactionType = $_POST['TransactionType'];
$Amount = $_POST['Amount'];
$Currency = $_POST['Currency'];
$BankDateTime = $_POST['BankDateTime'];
$NotificationHash = $_POST["NotificationHash"];

$transactionStatus = $success ? 'Success' : 'Failure';

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
$secretKey = $gatewayParams['pass'];
$clientcode = $gatewayParams['clientcode'];
$pass = $gatewayParams['pass'];

$systemUrl = $gatewayParams['systemurl'];

switch ($TransactionType) {
    case 1: // execute_purchase
        $local_sign = hash('sha512',$clientcode.$TpvID.$TransactionType.$invoiceId.$Amount.$Currency.md5($pass).$BankDateTime.$response);
        break;
    case 107: // add_user
        $local_sign = hash('sha512',$clientcode.$TpvID.$TransactionType.$invoiceId.$BankDateTime.md5($pass));
        break;
}

if ($NotificationHash != $local_sign) {
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
}else{
    $success = true;
}


switch ($TransactionType) {
    case 1: // execute_purchase
        /**
         * Validate Callback Invoice ID.
         *
         * Checks invoice ID is a valid invoice number. Note it will count an
         * invoice in any status as valid.
         *
         * Performs a die upon encountering an invalid Invoice ID.
         *
         * Returns a normalised invoice ID.
         */
        $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

        /**
         * Check Callback Transaction ID.
         *
         * Performs a check for any existing transactions with the same given
         * transaction number.
         *
         * Performs a die upon encountering a duplicate.
         */
        checkCbTransID($transactionId);

        /**
         * Log Transaction.
         *
         * Add an entry to the Gateway Log for debugging purposes.
         *
         * The debug data can be a string or an array. In the case of an
         * array it will be
         *
         * @param string $gatewayName        Display label
         * @param string|array $debugData    Data to log
         * @param string $transactionStatus  Status
         */
                
        logTransaction($gatewayParams['paymentmethod'], $_POST, $transactionStatus);

        if ($success) {
 
            /**
             * Add Invoice Payment.
             *
             * Applies a payment transaction entry to the given invoice ID.
             *
             * @param int $invoiceId         Invoice ID
             * @param string $transactionId  Transaction ID
             * @param float $paymentAmount   Amount paid (defaults to full balance)
             * @param float $paymentFee      Payment fee (optional)
             * @param string $gatewayModule  Gateway module name
             */
            addInvoicePayment(
                $invoiceId,
                $transactionId,
                $paymentAmount,
                $paymentFee,
                $gatewayModuleName
            );
        
            // Si es pago sin tarjeta tokenizada, alamacenamos el token para futuras compras
            if (isset($_POST["IdUser"])){

                $result = select_query("tblinvoices", "userid,total", array("id" => $invoiceId));
                $data = mysql_fetch_array($result);
                $userid = $data['userid'];

                $resultSaveToken = saveToken($_POST, $userid);
                
            }            
        
            print "PAYCOMET Payment Processed";
        
        }

    break;

    case 107: // add_user
        $userid = $invoiceId;
        $resultSaveToken = saveToken($_POST, $userid);

    break;
}


function saveToken($arrData, $userid) {
    global $remote_ip, $clientcode, $TpvID, $pass;
    $arrGatewayId = array($arrData["IdUser"],$arrData["TokenUser"]);
    
    global $cc_encryption_hash;
    $cchash = md5($cc_encryption_hash.$userid);

    // Obtenemos información de la tarjeta
    try {
    
        if ($remote_ip=="")
            $remote_ip = gethostbyname(gethostname());

        $DS_ORIGINAL_IP = $remote_ip;

        $DS_MERCHANT_MERCHANTSIGNATURE = hash('sha512', $clientcode . $arrData["IdUser"] . $arrData["TokenUser"] . $TpvID . $pass);

        $p = array(

            'DS_MERCHANT_MERCHANTCODE' => $clientcode,
            'DS_MERCHANT_TERMINAL' => $TpvID,
            'DS_IDUSER' => $arrData["IdUser"],
            'DS_TOKEN_USER' => $arrData["TokenUser"],           
            'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
            'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP
        );

        
        $client = new SoapClient('https://api.paycomet.com/gateway/xml-bankstore?wsdl');
        $res = $client->__soapCall( 'info_user', $p);

        $lastFour = $expDate = $cardBrand = "";
        if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']){
            $arrExpDate = explode("/",$res['DS_EXPIRYDATE']);
            $expDate = $arrExpDate[1] . substr($arrExpDate[0],2,2);
            $lastFour = substr($res['DS_MERCHANT_PAN'],-4);
            $cardBrand = $res['DS_CARD_BRAND'];
        }      

        // Save token, remove cardnumer
        full_query("UPDATE tblclients set expdate = AES_ENCRYPT('". $expDate ."','". $cchash. "') WHERE id = ". $userid);
        update_query( "tblclients", array( "gatewayid" => implode( ",", $arrGatewayId ), "cardlastfour" => $lastFour, "cardtype" => $cardBrand), array("id" => $userid));
        return true;
    } catch (exception $e) { return false;}
}







