<?php
/**
 * Modulo de pago PAYCOMET
 *
 * Este módulo de pago permite realizar pagos con tarjeta de credito mediante la pasarela PAYCOMET
 * PAYCOMET - Pasarela de pagos PCI-DSS Nivel 1 Multiplataforma
 *
 * @package    paycomet.php
 * @author     PAYCOMET <info@paycomet.com>
 * @version    2.9
 * @copyright  PAYCOMET
 *
**/

use WHMCS\Database\Capsule;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
include_once '../paycomet/lib/ApiRest.php';

App::load_function('gateway');
App::load_function('invoice');

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
$invoiceId = $_POST["Order"];   // Order -> Puede ser:
                                // 1. order para el pago.
                                // 2. iduser para el add_user
                                // 3. iduser/paymentMethod para edit_user

$transactionId = $_POST["AuthCode"];
$paymentAmount = number_format($_POST["Amount"] / 100, 2, ".", "");
$Amount = $_POST["Amount"]; // Campo requerido para los pagos por token
$hash = $_POST["ExtendedSignature"];
$TpvID = $_POST['TpvID'];
$TransactionType = $_POST['TransactionType'];
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
$apikey = trim($gatewayParams['apikey']);
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
    logTransaction($gatewayParams['name'], $_POST, $transactionStatus);
    die($transactionStatus);
    $success = false;
} else {
    $success = true;
}

switch ($TransactionType) {
    case 1: // execute_purchase
        // Si es pago con Tarjeta (methodId = 1) y no llega no llega el IdUser es un pago execute_purchase ya procesado. No hacemos nada
        if ((isset($_POST['MethodId']) && $_POST['MethodId']==1) && !isset($_POST["IdUser"])) {
            return;
        }

        if ($success) {
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

            // Si es pago sin tarjeta, y llega el token, lo almacenamos para futuras compras
            if ($_POST['MethodId']==1 && isset($_POST["IdUser"])) {

                $userid = Capsule::table('tblinvoices')->where('id',$invoiceId)->value('userid');

                if ($userid > 0) {
                    $resultSaveToken = saveToken($_POST, $userid, $invoiceId, $TransactionType);
                }

            }

            /**
             * Log Transaction.
             *
             * Add an entry to the Gateway Log for debugging purposes.
             *
             * The debug data can be a string or an array.
             *
             * @param string $gatewayName Display label
             * @param string|array $debugData Data to log
             * @param string $transactionStatus Status
             */
            logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

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
                0,
                $gatewayModuleName
            );

            $paymentSuccess = true;

            
            print "PAYCOMET Payment Processed: " . $invoiceId . "," . $transactionId . "," . $paymentAmount . ",0,"  . $gatewayModuleName;

        }

    break;

    case 107: // add_user
        $userid = $invoiceId;
        $resultSaveToken = saveToken($_POST, $userid, 0, $TransactionType);

    break;
}


function saveToken($arrData, $userid, $invoiceId = 0, $TransactionType=1) {
    global $remote_ip, $apikey, $clientcode, $TpvID, $pass, $gatewayModuleName;
    $arrGatewayId = array($arrData["IdUser"],$arrData["TokenUser"]);

    // Obtenemos información de la tarjeta
    try {
        if ($remote_ip=="") {
            $remote_ip = "127.0.0.1";
        }

        $DS_ORIGINAL_IP = $remote_ip;
        $cardnum = $cardExpiryDate = $cardBrand = "";

        // REST
        if ($apikey != "") {
            $apiRest = new ApiRest($apikey);
            try {
                $apiResponse = $apiRest->infoUser(
                    $arrData["IdUser"],
                    $arrData["TokenUser"],
                    $TpvID
                );
                if ($apiResponse->errorCode==0) {
                    $arrExpDate = explode("/",$apiResponse->expiryDate);
                    $cardExpiryDate = $arrExpDate[1] . substr($arrExpDate[0],2,2);
                    $cardnum = substr($apiResponse->pan,-4);
                    $cardBrand = $apiResponse->cardBrand;
                }
            } catch (exception $e){}
        } else {
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

            if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']) {
                $arrExpDate = explode("/",$res['DS_EXPIRYDATE']);
                $cardExpiryDate = $arrExpDate[1] . substr($arrExpDate[0],2,2);
                $cardnum = substr($res['DS_MERCHANT_PAN'],-4);
                $cardBrand = $res['DS_CARD_BRAND'];
            }

        }

        if ($TransactionType==1) {
            // Create a pay method for the newly created remote token.
            invoiceSaveRemoteCard($invoiceId, $cardnum, $cardBrand, $cardExpiryDate, implode( ",", $arrGatewayId ));

        } else if ($TransactionType==107) {
            $arrDatos = explode("/",$userid);
            $action = "";
            try {
                // add_user, no llega el metodo de pago
                if (sizeof($arrDatos)==1) {
                    $action = "Create";
                    // Function available in WHMCS 7.9 and later
                    createCardPayMethod(
                        $userid,
                        $gatewayModuleName,
                        $cardnum,
                        $cardExpiryDate,
                        $cardBrand,
                        null, //start date
                        null, //issue number
                        implode( ",", $arrGatewayId )
                    );
                } else if (sizeof($arrDatos)==2) {
                    // Function available in WHMCS 7.9 and later
                    $action = "Update";
                    $customerId = $arrDatos[0];
                    $payMethodId = $arrDatos[1];

                    updateCardPayMethod(
                        $customerId,
                        $payMethodId,
                        $cardExpiryDate,
                        null, // card start date
                        null, // card issue number
                        implode( ",", $arrGatewayId )
                    );

                }

                // Log to gateway log as successful.
                logTransaction($gatewayModuleName, $_POST, $action .' Success');
            } catch (Exception $e) {
                // Log to gateway log as unsuccessful.
                logTransaction($gatewayModuleName, $_POST, $action .' Failed');

            }
        }

        /*
        // Save token, remove cardnumer
        full_query("UPDATE tblclients set expdate = AES_ENCRYPT('". $expDate ."','". $cchash. "') WHERE id = ". $userid);
        update_query( "tblclients", array( "gatewayid" => implode( ",", $arrGatewayId ), "cardlastfour" => $lastFour, "cardtype" => $cardBrand), array("id" => $userid));
        */
        return true;
    } catch (exception $e) { return false;}
}
