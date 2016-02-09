<?php
/**
 * Modulo de pago PAYTPV
 *
 * Este módulo de pago permite realizar pagos con tarjeta de credito mediante la pasarela PAYTPV
 * PAYTPV - Pasarela de pagos PCI-DSS Nivel 1 Multiplataforma
 *
 * @package    paytpv.php
 * @author     PAYTPV <info@paytpv.com>
 * @copyright  2016 PAYTPV
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

$testmode = ($gatewayParams['testmode']) ? 1:0;

$systemUrl = $gatewayParams['systemurl'];

if ($testmode==1){
    if ($_POST["IdUser"]==1 && $_POST["TokenUser"]=="TOKEN_PAYTPV"){
        $transactionId .= date("Ymdhmi"); // Para no repetir la transaction
        $success = true;
    }
}else{

    $local_sign = md5($clientcode.$TpvID.$TransactionType.$invoiceId.$Amount.$Currency.md5($pass).$BankDateTime.$response);

    if ($hash != $local_sign) {
        $transactionStatus = 'Hash Verification Failure';
        $success = false;
    }else{
        $success = true;
    }
}


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
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);


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

        $arrGatewayId = array($_POST["IdUser"],$_POST["TokenUser"]);

        $result = select_query("tblinvoices", "userid,total", array("id" => $invoiceId));
        $data = mysql_fetch_array($result);
        $userid = $data['userid'];

        // Save token, remove cardnumer
        update_query( "tblclients", array( "gatewayid" => implode( ",", $arrGatewayId ), "cardnum" => ""), array("id" => $userid));
    }

    if ($testmode==1){

        $URLOK = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
        $res["urlok"] = $URLOK;

        print json_encode($res);
        exit;
    }

    print "PAYTPV Payment Processed";

}
