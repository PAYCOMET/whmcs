<?php
/**
 * Modulo de pago PAYCOMET
 *
 * Este módulo de pago permite realizar pagos con tarjeta de credito mediante la pasarela PAYCOMET
 * PAYCOMET - Pasarela de pagos PCI-DSS Nivel 1 Multiplataforma
 *
 * @package    paycomet.php
 * @author     PAYCOMET <info@paycomet.com>
 * @copyright  2020 PAYCOMET
 * @version    2.3
 *
**/

use WHMCS\Database\Capsule;
use WHMCS\Utility\Environment\CurrentUser;

include_once 'paycomet/lib/ApiRest.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see http://docs.whmcs.com/Gateway_Module_Meta_Data_Parameters
 *
 * @return array
 */
function paycomet_MetaData()
{

    return array(
        'DisplayName' => 'PAYCOMET Payment Gateway Module',
        'APIVersion' => '1.1', // Use API Version 1.1
    );

}


/**
 * Define gateway configuration options.
 *
 * @return array
 */
function paycomet_config()
{

    $config_array = array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'PAYCOMET',
        ),
        // a text field type allows for single line text input
        'apikey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '40',
            'Default' => '',
            'Description' => 'Introduzca la API Key generada en el panel de PAYCOMET',
        ),
        // a text field type allows for single line text input
        'clientcode' => array(
            'FriendlyName' => 'Código de Cliente',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Introduzca su Código de Cliente de PAYCOMET',
        ),
        // a password field type allows for masked text input
        'term' => array(
            'FriendlyName' => 'Número de Terminal',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Introduzca su Número de Terminal de PAYCOMET',
        ),

        // a password field type allows for masked text input
        'pass' => array(
            'FriendlyName' => 'Contraseña',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Introduzca su Contraseña de PAYCOMET',
        ),


        // the dropdown field type renders a select menu of options
        'terminales' => array(
            'FriendlyName' => 'Terminales',
            'Type' => 'dropdown',
            'Options' => "Seguro,No-Seguro,Ambos",
            'Description' => '',
        ),

        // the dropdown field type renders a select menu of options
        'tdfirst' => array(
            'FriendlyName' => 'Usar 3D Secure',
            'Type' => 'dropdown',
            'Options' => "Si,No",
            'Description' => 'Opción sólo válida cuando se ha seleccionado en Terminales la opción "Ambos". Primera compra por 3D Secure',
        ),

        // the dropdown field type renders a select menu of options
        'tdmin' => array(
            'FriendlyName' => 'Usar 3D Secure en pagos superiores a',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Opción sólo válida cuando se ha seleccionado en Terminales la opción "Ambos"',
        ),



    );

    return $config_array;
}


/**
 * No local credit card input.
 *
 * This is a required function declaration. Denotes that the module should
 * not allow local card data input.
 */
function paycomet_nolocalcc() {}


/**
 * Capture payment.
 *
 * Called when a payment is requested to be processed and captured.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 *
 * @return array
 */
function paycomet_capture($params){
    global $remote_ip;

    if (!filter_var($remote_ip,FILTER_VALIDATE_IP)) {
        $remote_ip = CurrentUser::getIP();

        if (!filter_var($remote_ip,FILTER_VALIDATE_IP)) {
            $remote_ip = gethostbyname(gethostname());

            if (!filter_var($remote_ip,FILTER_VALIDATE_IP)) {
                $remote_ip = "127.0.0.1";
            }
        }
    }

    // Gateway Configuration Parameters
    $apikey = trim($params['apikey']);
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    $gatewayId = $params['gatewayid'];

    // A token is required for a remote input gateway capture attempt
    if (!$gatewayId) {
        return [
            'status' => 'declined',
            'decline_message' => 'No Remote Token',
        ];
    }

    $DS_MERCHANT_MERCHANTCODE = $clientcode;
    $DS_MERCHANT_TERMINAL = $term;
    $DS_MERCHANT_ORDER = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);
    $DS_MERCHANT_CURRENCY = $currencyCode;
    $DS_MERCHANT_AMOUNT = number_format($amount * 100, 0, '.', '');

    $DS_ORIGINAL_IP = $remote_ip;

    $TransactionId = 0;

    $datos = explode(",",$gatewayId);
    $DS_IDUSER = $datos[0];
    $DS_TOKEN_USER = $datos[1];

    // REST
    if ($apikey != "") {

        $merchantData = paycomet_getMerchantData($params);

        // Por defecto MERCHANT_TRX_TYPE = M
        $trxType = "M";

        // Comprobar si es recurring
        if (paycomet_haveRecurringProduct($params)) {
            $trxType = "R";

            $dateAux = new \DateTime("now");
            $dateAux->modify('+10 year'); 
            $recurringExpiry = $dateAux->format('Ymd'); // Fecha actual + 10 años.

            $merchantData["recurringExpiry"] = $recurringExpiry; 
            $merchantData["recurringFrequency"] = "1";
        };

        $secure = 0;
        $methodId = 1;
        $userInteraction = 0;
        $notifyDirectPayment = 2; // Sin notificacion http

        $res = array();
        try {
            $apiRest = new ApiRest($apikey);
            $apiResponse = $apiRest->executePurchase(
                $DS_MERCHANT_TERMINAL,
                $DS_MERCHANT_ORDER,
                $DS_MERCHANT_AMOUNT,
                $DS_MERCHANT_CURRENCY,
                $methodId,
                $DS_ORIGINAL_IP,
                $secure,
                $DS_IDUSER,
                $DS_TOKEN_USER,
                '',
                '',
                '',
                '',
                '',
                $userInteraction,
                [],
                $trxType,
                'MIT',
                $notifyDirectPayment,
                $merchantData
            );

            $res["DS_RESPONSE"] = ($apiResponse->errorCode > 0)? 0 : 1;
            $res["DS_ERROR_ID"] = $apiResponse->errorCode;
            $res["DS_MERCHANT_AUTHCODE"] = $apiResponse->authCode;
            $res["DS_MERCHANT_AMOUNT"] = $apiResponse->amount;

        } catch (Exception $e) {
            $res["DS_ERROR_ID"] = $apiResponse->errorCode;
        }
    } else {
        $client = new SoapClient( 'https://api.paycomet.com/gateway/xml-bankstore?wsdl');

        $DS_MERCHANT_MERCHANTSIGNATURE = hash('sha512', $DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_AMOUNT . $DS_MERCHANT_ORDER . $pass);

        $p = array(

            'DS_MERCHANT_MERCHANTCODE' => $DS_MERCHANT_MERCHANTCODE,
            'DS_MERCHANT_TERMINAL' => $DS_MERCHANT_TERMINAL,
            'DS_IDUSER' => $DS_IDUSER,
            'DS_TOKEN_USER' => $DS_TOKEN_USER,
            'DS_MERCHANT_AMOUNT' => $DS_MERCHANT_AMOUNT,
            'DS_MERCHANT_ORDER' => $DS_MERCHANT_ORDER,
            'DS_MERCHANT_CURRENCY' => $DS_MERCHANT_CURRENCY,
            'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
            'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP

        );

        $res = $client->__soapCall( 'execute_purchase', $p);
    }

    if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']) {
        $TransactionId = $res['DS_MERCHANT_AUTHCODE'];

        $returnData = [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'success',
            // Data to be recorded in the gateway log - can be a string or array
            'rawdata' => $res,
            // Unique Transaction ID for the capture transaction
            'transid' => $TransactionId,
            // Optional fee amount for the fee value refunded
            'fee' => 0,
        ];
    } else {
        $returnData = [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'declined',
            // When not successful, a specific decline reason can be logged in the Transaction History
            'declinereason' => $res['DS_ERROR_ID'],
            // Data to be recorded in the gateway log - can be a string or array
            'rawdata' => $res,
        ];
    }
    return $returnData;
}


/**
 * Remote input.
 *
 * Called when a pay method is requested to be created or a payment is
 * being attempted.
 *
 * New pay methods can be created or added without a payment being due.
 * In these scenarios, the amount parameter will be empty and the workflow
 * should be to create a token without performing a charge.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 *
 * @return array
 */
function paycomet_remoteinput($params)
{
    global $_LANG;

    // Gateway Configuration Parameters
    $apikey = trim($params['apikey']);
    $clientcode = trim($params['clientcode']);
    $term = trim($params['term']);
    $pass = trim($params['pass']);
    $terminales = $params['terminales'];
    $tdfirst = $params['tdfirst'];
    $tdmin = trim($params['tdmin']);

    $language = paycomet_getLang($_LANG['locale']);

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    $userid = $_SESSION['uid'];

    $action = '';
    if ($amount > 0) {
        $action = 'payment';
    } else {
        $action = 'add_user';
    }

    $url = "https://api.paycomet.com/gateway/ifr-bankstore";

    if ($action == 'payment') {
        // PAYCOMET
        $importe = number_format($amount * 100, 0, '.', '');

        $paycomet_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

        $OPERATION = "1";

        $isSecureTransaction = paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$amount,0);
        $secure = ($isSecureTransaction)?1:0;

        // REST
        if ($apikey != "") {
            $merchantData = paycomet_getMerchantData($params);

            $formFields = array();

            $userInteraction = 1;

            try {
                $apiRest = new ApiRest($apikey);
                $apiResponse = $apiRest->form(
                    $OPERATION,
                    $language,
                    $term,
                    '',
                    [
                        'terminal' => $term,
                        'methods' => [1],
                        'order' => $paycomet_order_ref,
                        'amount' => $importe,
                        'currency' => $currencyCode,
                        'userInteraction' => $userInteraction,
                        'secure' => $secure,
                        'merchantData' => $merchantData,
                        'urlOk' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId,
                        'urlKo' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId
                    ]
                );

                if ($apiResponse->errorCode==0) {
                    $url = $apiResponse->challengeUrl;
                } else {
                    return "Error: " . $apiResponse->errorCode;
                }
            } catch (Exception $e) {
                $url = $apiResponse->challengeUrl;
            }
        // GET
        } else {
            $signature = hash('sha512', $clientcode . $term . $OPERATION . $paycomet_order_ref . $importe . $currencyCode . md5($pass));
            $formFields = array
            (
                'MERCHANT_MERCHANTCODE' => $clientcode,
                'MERCHANT_TERMINAL' => $term,
                'OPERATION' => $OPERATION,
                'LANGUAGE' => $language,
                'MERCHANT_MERCHANTSIGNATURE' => $signature,
                'MERCHANT_ORDER' => $paycomet_order_ref,
                'MERCHANT_AMOUNT' => $importe,
                'MERCHANT_CURRENCY' => $currencyCode,
                '3DSECURE' => $secure,
                'URLOK' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId,
                'URLKO' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId
            );
        }
    } else if ($action == 'add_user') {
        // System Parameters
        $systemUrl = $params['systemurl'];

        $paycomet_order_ref = $userid;
        $OPERATION = 107;

        // REST
        if ($apikey != "") {
            try {
                $apiRest = new ApiRest($apikey);
                $apiResponse = $apiRest->form(
                    $OPERATION,
                    $language,
                    $term,
                    '',
                    [
                        'terminal' => (int) $term,
                        'order' => (string) $paycomet_order_ref,
                        'urlOk' => (string) $systemUrl . 'clientarea.php?action=creditcard',
                        'urlKo' => (string) $systemUrl . 'clientarea.php?action=creditcard'
                    ]
                );

                $formFields = array();

                if ($apiResponse->errorCode==0) {
                    $url = $apiResponse->challengeUrl;
                } else {
                    return "Error: " . $apiResponse->errorCode;
                }
            } catch (Exception $e) {
                $url = $apiResponse->challengeUrl;
            }
        // GET
        } else {
            $signature = hash('sha512', $clientcode . $term . $OPERATION . $paycomet_order_ref . md5($pass));
            $formFields = array
            (
                'MERCHANT_MERCHANTCODE' => $clientcode,
                'MERCHANT_TERMINAL' => $term,
                'OPERATION' => $OPERATION,
                'LANGUAGE' => $language,
                'MERCHANT_MERCHANTSIGNATURE' => $signature,
                'MERCHANT_ORDER' => $paycomet_order_ref,
                'URLOK' => $systemUrl . 'clientarea.php?action=creditcard',
                'URLKO' => $systemUrl . 'clientarea.php?action=creditcard'
            );
        }
    }

    $htmlOutput = '<form method="get" action="' . $url . '">';
    foreach ($formFields as $k => $v) {
       $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . PHP_EOL;
    }
    $htmlOutput .= '<noscript>';
    $htmlOutput .= '    <input type="submit" value="Click here to continue &raquo;" />';
    $htmlOutput .= '</noscript>';
    $htmlOutput .= '</form>';
    global $code;
    $code = $htmlOutput;
    return $htmlOutput;
}



/**
 * Remote update.
 *
 * Called when a pay method is requested to be updated.
 *
 * The expected return of this function is direct HTML output. It provides
 * more flexibility than the remote input function by not restricting the
 * return to a form that is posted into an iframe. We still recommend using
 * an iframe where possible and this sample demonstrates use of an iframe,
 * but the update can sometimes be handled by way of a modal, popup or
 * other such facility.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 *
 * @return array
 */
function paycomet_remoteupdate($params)
{
    return "Esta tarjeta no se puede editar. Si lo desea puede dar de alta una nueva y asignarla por defecto como metodo de pago.";
    global $_LANG;
    if( !$params["gatewayid"] )
    {
        return "<p align=\"center\">Debe dar de alta una tarjeta o realizar un pago para poder editar la tarjeta</p>";
    }

    $userid = $_SESSION['uid'];
    $payMethodId = $params['paymethodid'];

    // Gateway Configuration Parameters
    $apikey = trim($params['apikey']);
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];

    $language = paycomet_getLang($_LANG['locale']);

    // System Parameters
    $systemUrl = $params['systemurl'];

    $paycomet_order_ref = $userid . "/" . $payMethodId; // Si estamos editando pasamos el metodo para obtenerlo en la notificación

    $OPERATION = 107;
    // REST
    if ($apikey != "") {
        try {
            $apiRest = new ApiRest($apikey);
            $apiResponse = $apiRest->form(
                $OPERATION,
                $language,
                $term,
                '',
                [
                    'terminal' => (int) $term,
                    'order' => (string) $paycomet_order_ref,
                    'urlOk' => (string) $systemUrl . 'clientarea.php?action=creditcard',
                    'urlKo' => (string) $systemUrl . 'clientarea.php?action=creditcard'
                ]
            );

            if ($apiResponse->errorCode==0) {
                $url = $apiResponse->challengeUrl;
            }
        } catch (Exception $e) {
            $url = $apiResponse->challengeUrl;
        }
    } else {
        $signature = hash('sha512', $clientcode . $term . $OPERATION . $paycomet_order_ref . md5($pass));
        $fields = array
        (
            'MERCHANT_MERCHANTCODE' => $clientcode,
            'MERCHANT_TERMINAL' => $term,
            'OPERATION' => $OPERATION,
            'LANGUAGE' => $language,
            'MERCHANT_MERCHANTSIGNATURE' => $signature,
            'MERCHANT_ORDER' => $paycomet_order_ref,
            'URLOK' => $systemUrl . 'clientarea.php?action=creditcard',
            'URLKO' => $systemUrl . 'clientarea.php?action=creditcard'
        );

        $url = "https://api.paycomet.com/gateway/ifr-bankstore";

        $query = http_build_query($fields);

        $url .= '?' . $query;
    }
    $thereturn .= "<iframe src=\"".$url . "\" height=\"650\" width=\"450\" frameborder=\"0\"></iframe><br/>";
    return  $thereturn;
}


function paycomet_getLang($locale){
    $arrDatos = explode("_",$locale);
    $lang = $arrDatos[0];
    if ($lang!="") {
        return $lang;
    } else {
        return "es";
    }
}

/**
 * Admin status message.
 *
 * Called when an invoice is viewed in the admin area.
 *
 * @param array $params Payment Gateway Module Parameters.
 *
 * @return array
 */
function paycomet_adminstatusmsg($params)
{
    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];

    // Invoice Parameters
    $remoteGatewayToken = $params['gatewayid'];
    $invoiceId = $params['id']; // The Invoice ID
    $userId = $params['userid']; // The Owners User ID
    $date = $params['date']; // The Invoice Create Date
    $dueDate = $params['duedate']; // The Invoice Due Date
    $status = $params['status']; // The Invoice Status

    return [
        'type' => 'info',
        'title' => 'Token Gateway Profile',
        'msg' => ($remoteGatewayToken) ? 'This client has a PAYCOMET Profile storing their card details for automated recurring billing with ID ' . $remoteGatewayToken : 'This client does not yet have a gateway profile setup',
    ];

}


function paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$importe=0,$card=0){

    $tdmin = str_replace(",",".",$tdmin); // Por si se han definido los decimales con ","
    $tdmin = number_format($tdmin * 100, 0, '.', '');
    $importe = number_format($importe * 100, 0, '.', '');

    // Si solo tiene Terminal Seguro
    if ($terminales=="Seguro") {
        return true;
    }

    if ($terminales=="No-Seguro") {
        return false;
    }

    // Si esta definido que el pago es 3d secure y no estamos usando una tarjeta tokenizada
    if ($terminales=="Ambos") {

        if ($tdfirst=="Si" && $card==0) {
            return true;
        }

        // Si se supera el importe maximo para compra segura
        if (($tdmin>0 && $tdmin < $importe)) {
            return true;
        }

    }
    return false;
}


// Default values
$terminales = "Seguro";
$tdfirst    = "Si";
$tdmin      = 0;

try {
    $terminales = Capsule::table('tblpaymentgateways')->where('gateway','paycomet')->where('setting','terminales')->value('value');
    $tdfirst = Capsule::table('tblpaymentgateways')->where('gateway','paycomet')->where('setting','tdfirst')->value('value');
    $tdmin = Capsule::table('tblpaymentgateways')->where('gateway','paycomet')->where('setting','tdmin')->value('value');    
} catch (exception $e) {}


// Verificacion de Pago Seguro
$amount = $_SESSION['orderdetails']['TotalDue'];

if (isset($_POST["ccinfo"])) {
    $card = ($_POST["ccinfo"]>0)?1:0;
    setcookie("paycomet_card", $card);
} else {
    $card = $_COOKIE["paycomet_card"];
}

$isSecureTransaction = paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$amount,$card);

$secure = ($isSecureTransaction)?1:0;

// Si el pago es seguro se define la funcion paycomet_3dsecure
if ($isSecureTransaction) {
    function paycomet_3dsecure($params) {
        global $_LANG;

        $language = paycomet_getLang($_LANG['locale']);

        // Gateway Configuration Parameters
        $apikey = trim($params['apikey']);
        $clientcode = $params['clientcode'];
        $term = $params['term'];
        $pass = $params['pass'];


        $gatewayId = $params['gatewayid'];

        // System Parameters
        $companyName = $params['companyname'];
        $systemUrl = $params['systemurl'];
        $langPayNow = $params['langpaynow'];

        $datos = explode(",",$gatewayId);
        $DS_IDUSER = $datos[0];
        $DS_TOKEN_USER = $datos[1];

        // Invoice Parameters
        $invoiceId = $params['invoiceid'];
        $description = $params["description"];
        $amount = $params['amount'];
        $currencyCode = $params['currency'];

        // PAYCOMET
        $importe = number_format($amount * 100, 0, '.', '');

        $paycomet_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

        $url = "";
        // REST
        if ($apikey != "") {
            try {
                $OPERATION = 1;

                $merchantData = paycomet_getMerchantData($params);
                $secure = 1;
                $userInteraction = 1;

                $apiRest = new ApiRest($apikey);
                $apiResponse = $apiRest->form(
                    $OPERATION,
                    $language,
                    $term,
                    '',
                    [
                        'terminal' => $term,
                        'methods' => [1],
                        'order' => $paycomet_order_ref,
                        'amount' => $importe,
                        'currency' => $currencyCode,
                        'userInteraction' => $userInteraction,
                        'secure' => $secure,
                        'idUser' => $DS_IDUSER,
                        'tokenUser' => $DS_TOKEN_USER,
                        'merchantData' => $merchantData,
                        'urlOk' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId,
                        'urlKo' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId
                    ]
                );
                $postfields = array();
                if ($apiResponse->errorCode==0) {
                    $url = $apiResponse->challengeUrl;
                }
            } catch (Exception $e) {
                $url = "";
            }
        } else {
            $OPERATION = "109";

            $signature = hash('sha512', $clientcode . $DS_IDUSER . $DS_TOKEN_USER . $term . $OPERATION . $paycomet_order_ref . $importe . $currencyCode . md5($pass));
            $postfields = array
            (
                'MERCHANT_MERCHANTCODE' => $clientcode,
                'MERCHANT_TERMINAL' => $term,
                'OPERATION' => 109,
                'LANGUAGE' => $language,
                'MERCHANT_MERCHANTSIGNATURE' => $signature,
                'MERCHANT_ORDER' => $paycomet_order_ref,
                'MERCHANT_AMOUNT' => $importe,
                'MERCHANT_CURRENCY' => $currencyCode,
                'IDUSER' => $DS_IDUSER,
                'TOKEN_USER' => $DS_TOKEN_USER,
                '3DSECURE' => 1,
                'URLOK' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId,
                'URLKO' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId
            );

            $url = "https://api.paycomet.com/gateway/ifr-bankstore";

        }

        $htmlOutput = '<form method="get" action="'.$url.'">';
        foreach ($postfields as $k => $v) {
            $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
        }

        $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
        $htmlOutput .= '</form>';

        return $htmlOutput;
    }
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */
function paycomet_refund($params)
{
    global $remote_ip;

    if (!filter_var($remote_ip,FILTER_VALIDATE_IP)) {
        $remote_ip = CurrentUser::getIP();

        if (!filter_var($remote_ip,FILTER_VALIDATE_IP)) {
            $remote_ip = gethostbyname(gethostname());

            if (!filter_var($remote_ip,FILTER_VALIDATE_IP)) {
                $remote_ip = "127.0.0.1";
            }
        }
    }

    // Gateway Configuration Parameters
    $apikey = trim($params['apikey']);    
    $clientcode = $params['clientcode'];
    $pass = $params['pass'];
    $term = $params['term'];

    $gatewayids = explode( ",", $params['gatewayid'] );

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    $invoiceId = $params['invoiceid'];

    $paycomet_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

    // perform API call to initiate refund and interpret result

    $DS_MERCHANT_MERCHANTCODE = $clientcode;
    $DS_MERCHANT_TERMINAL = $term;
    $DS_IDUSER = $gatewayids[0];
    $DS_TOKEN_USER = $gatewayids[1];
    $DS_MERCHANT_ORDER = $paycomet_order_ref;
    $DS_MERCHANT_AUTHCODE = $transactionIdToRefund;
    $DS_MERCHANT_CURRENCY = $currencyCode;
    $DS_MERCHANT_AMOUNT = $importe = number_format($refundAmount * 100, 0, '.', '');
    $DS_MERCHANT_MERCHANTSIGNATURE = hash('sha512', $DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_AUTHCODE . $DS_MERCHANT_ORDER . $pass);

    $DS_ORIGINAL_IP = $remote_ip;

    // REST
    if ($apikey!="") {
        try {
            $notifyDirectPayment = 2; // Sin notificacion http

            $apiRest = new ApiRest($apikey);
            $apiResponse = $apiRest->executeRefund(
                $DS_MERCHANT_ORDER,
                $DS_MERCHANT_TERMINAL,
                $DS_MERCHANT_AMOUNT,
                $DS_MERCHANT_CURRENCY,
                $DS_MERCHANT_AUTHCODE,
                $DS_ORIGINAL_IP,
                $notifyDirectPayment
            );

            $response = array();
            $response["DS_RESPONSE"] = ($apiResponse->errorCode > 0)? 0 : 1;
            $response["DS_ERROR_ID"] = $apiResponse->errorCode;

            $success = 'error';

            if ($response["DS_RESPONSE"]==1) {
                $success = 'success';
                $refundTransactionId = $apiResponse->authCode;
                $response["DS_MERCHANT_AUTHCODE"] = $apiResponse->authCode;
                $responseData = $response;
            }
        } catch (Exception $e) {
            $success = 'error';
        }
    } else {
        $client = new SoapClient( 'https://api.paycomet.com/gateway/xml-bankstore?wsdl');

        $p = array(

            'DS_MERCHANT_MERCHANTCODE' => $DS_MERCHANT_MERCHANTCODE,
            'DS_MERCHANT_TERMINAL' => $DS_MERCHANT_TERMINAL,
            'DS_IDUSER' => $DS_IDUSER,
            'DS_TOKEN_USER' => $DS_TOKEN_USER,
            'DS_MERCHANT_AUTHCODE' => $DS_MERCHANT_AUTHCODE,
            'DS_MERCHANT_ORDER' => $DS_MERCHANT_ORDER,
            'DS_MERCHANT_CURRENCY' => $DS_MERCHANT_CURRENCY,
            'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
            'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP,
            'DS_MERCHANT_AMOUNT' => $DS_MERCHANT_AMOUNT
        );

        $res = $client->__soapCall( 'execute_refund', $p);

        $success = 'error';

        if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']) {
            $success = 'success';
            $responseData = $res;
            $refundTransactionId = $res['DS_MERCHANT_AUTHCODE'];
        }
    }

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $success,
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
    );
}

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */
function paycomet_cancelSubscription($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];


    // perform API call to cancel subscription and interpret result
    return array(
        // 'success' if successful, any other value for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $params,
    );
}


function paycomet_getEMV3DS($params)
{

    $s_cid = ($params["clientdetails"]["userid"])?$params["clientdetails"]["userid"]:"";
    if ($s_cid == "" ) {
        $s_cid = 0;
    }

    $Merchant_EMV3DS = array();

    $Merchant_EMV3DS["customer"]["id"] = (int)$s_cid;
    $Merchant_EMV3DS["customer"]["name"] = ($params["clientdetails"]["firstname"])?$params["clientdetails"]["firstname"]:"";
    $Merchant_EMV3DS["customer"]["surname"] = ($params["clientdetails"]["lastname"])?$params["clientdetails"]["lastname"]:"";
    $Merchant_EMV3DS["customer"]["email"] = ($params["clientdetails"]["email"])?$params["clientdetails"]["email"]:"";


    $phone = "";
    if (!empty($params["clientdetails"]["phonenumber"])) {
        $phone = $params["clientdetails"]["phonenumber"];
    }

    if ($phone) {
        $phone_prefix = paycomet_isoCodePhonePrefix($params["clientdetails"]["countrycode"]);

        if ($phone_prefix!="") {
            $arrDatosWorkPhone["cc"] = (int)$phone_prefix;
            $arrDatosWorkPhone["subscriber"] = preg_replace("/[^0-9]/", '', $phone);
            $Merchant_EMV3DS["customer"]["homePhone"] = $arrDatosWorkPhone;
        }
    }

    $Merchant_EMV3DS["customer"]["firstBuy"] = (paycomet_numPurchaseCustomer($s_cid,1,10,"year")>0)?"no":"si";

    $Merchant_EMV3DS["billing"]["billAddrCity"] = ($params["clientdetails"]["city"])?$params["clientdetails"]["city"]:"";
    $Merchant_EMV3DS["billing"]["billAddrCountry"] = ($params["clientdetails"]["country"])?$params["clientdetails"]["country"]:"";
    if ($Merchant_EMV3DS["billing"]["billAddrCountry"] != "") {
        $billAddrCountry = paycomet_isoCodeToNumber($Merchant_EMV3DS["billing"]["billAddrCountry"]);
        if ($billAddrCountry != "") {
            $Merchant_EMV3DS["billing"]["billAddrCountry"] = (int)$billAddrCountry;
        }
    }
    $Merchant_EMV3DS["billing"]["billAddrLine1"] = ($params["clientdetails"]["address1"])?$params["clientdetails"]["address1"]:"";
    $Merchant_EMV3DS["billing"]["billAddrLine2"] = ($params["clientdetails"]["address2"])?$params["clientdetails"]["address2"]:"";

    $Merchant_EMV3DS["billing"]["billAddrPostCode"] = ($params["clientdetails"]["postcode"])?$params["clientdetails"]["postcode"]:"";

    // acctInfo
    $Merchant_EMV3DS["acctInfo"] = paycomet_acctInfo($params);

    // threeDSRequestorAuthenticationInfo
    $Merchant_EMV3DS["threeDSRequestorAuthenticationInfo"] = paycomet_threeDSRequestorAuthenticationInfo($params);

    $Merchant_EMV3DS["challengeWindowSize"] = 05;

    return $Merchant_EMV3DS;
}

function paycomet_isoCodeToNumber($code)
{
    $arrCode = array("AF" => "004", "AX" => "248", "AL" => "008", "DE" => "276", "AD" => "020", "AO" => "024", "AI" => "660", "AQ" => "010", "AG" => "028", "SA" => "682", "DZ" => "012", "AR" => "032", "AM" => "051", "AW" => "533", "AU" => "036", "AT" => "040", "AZ" => "031", "BS" => "044", "BD" => "050", "BB" => "052", "BH" => "048", "BE" => "056", "BZ" => "084", "BJ" => "204", "BM" => "060", "BY" => "112", "BO" => "068", "BQ" => "535", "BA" => "070", "BW" => "072", "BR" => "076", "BN" => "096", "BG" => "100", "BF" => "854", "BI" => "108", "BT" => "064", "CV" => "132", "KH" => "116", "CM" => "120", "CA" => "124", "QA" => "634", "TD" => "148", "CL" => "52", "CN" => "156", "CY" => "196", "CO" => "170", "KM" => "174", "KP" => "408", "KR" => "410", "CI" => "384", "CR" => "188", "HR" => "191", "CU" => "192", "CW" => "531", "DK" => "208", "DM" => "212", "EC" => "218", "EG" => "818", "SV" => "222", "AE" => "784", "ER" => "232", "SK" => "703", "SI" => "705", "ES" => "724", "US" => "840", "EE" => "233", "ET" => "231", "PH" => "608", "FI" => "246", "FJ" => "242", "FR" => "250", "GA" => "266", "GM" => "270", "GE" => "268", "GH" => "288", "GI" => "292", "GD" => "308", "GR" => "300", "GL" => "304", "GP" => "312", "GU" => "316", "GT" => "320", "GF" => "254", "GG" => "831", "GN" => "324", "GW" => "624", "GQ" => "226", "GY" => "328", "HT" => "332", "HN" => "340", "HK" => "344", "HU" => "348", "IN" => "356", "ID" => "360", "IQ" => "368", "IR" => "364", "IE" => "372", "BV" => "074", "IM" => "833", "CX" => "162", "IS" => "352", "KY" => "136", "CC" => "166", "CK" => "184", "FO" => "234", "GS" => "239", "HM" => "334", "FK" => "238", "MP" => "580", "MH" => "584", "PN" => "612", "SB" => "090", "TC" => "796", "UM" => "581", "VG" => "092", "VI" => "850", "IL" => "376", "IT" => "380", "JM" => "388", "JP" => "392", "JE" => "832", "JO" => "400", "KZ" => "398", "KE" => "404", "KG" => "417", "KI" => "296", "KW" => "414", "LA" => "418", "LS" => "426", "LV" => "428", "LB" => "422", "LR" => "430", "LY" => "434", "LI" => "438", "LT" => "440", "LU" => "442", "MO" => "446", "MK" => "807", "MG" => "450", "MY" => "458", "MW" => "454", "MV" => "462", "ML" => "466", "MT" => "470", "MA" => "504", "MQ" => "474", "MU" => "480", "MR" => "478", "YT" => "175", "MX" => "484", "FM" => "583", "MD" => "498", "MC" => "492", "MN" => "496", "ME" => "499", "MS" => "500", "MZ" => "508", "MM" => "104", "NA" => "516", "NR" => "520", "NP" => "524", "NI" => "558", "NE" => "562", "NG" => "566", "NU" => "570", "NF" => "574", "NO" => "578", "NC" => "540", "NZ" => "554", "OM" => "512", "NL" => "528", "PK" => "586", "PW" => "585", "PS" => "275", "PA" => "591", "PG" => "598", "PY" => "600", "PE" => "604", "PF" => "258", "PL" => "616", "PT" => "620", "PR" => "630", "GB" => "826", "EH" => "732", "CF" => "140", "CZ" => "203", "CG" => "178", "CD" => "180", "DO" => "214", "RE" => "638", "RW" => "646", "RO" => "642", "RU" => "643", "WS" => "882", "AS" => "016", "BL" => "652", "KN" => "659", "SM" => "674", "MF" => "663", "PM" => "666", "VC" => "670", "SH" => "654", "LC" => "662", "ST" => "678", "SN" => "686", "RS" => "688", "SC" => "690", "SL" => "694", "SG" => "702", "SX" => "534", "SY" => "760", "SO" => "706", "LK" => "144", "SZ" => "748", "ZA" => "710", "SD" => "729", "SS" => "728", "SE" => "752", "CH" => "756", "SR" => "740", "SJ" => "744", "TH" => "764", "TW" => "158", "TZ" => "834", "TJ" => "762", "IO" => "086", "TF" => "260", "TL" => "626", "TG" => "768", "TK" => "772", "TO" => "776", "TT" => "780", "TN" => "788", "TM" => "795", "TR" => "792", "TV" => "798", "UA" => "804", "UG" => "800", "UY" => "858", "UZ" => "860", "VU" => "548", "VA" => "336", "VE" => "862", "VN" => "704", "WF" => "876", "YE" => "887", "DJ" => "262", "ZM" => "894", "ZW" => "716");
    if (isset($arrCode[$code])) {
        return $arrCode[$code];
    }
    return "";
}

function paycomet_isoCodePhonePrefix($code)
{
    try {
        $arrCode = array("AC" => "247", "AD" => "376", "AE" => "971", "AF" => "93","AG" => "268", "AI" => "264", "AL" => "355", "AM" => "374", "AN" => "599", "AO" => "244", "AR" => "54", "AS" => "684", "AT" => "43", "AU" => "61", "AW" => "297", "AX" => "358", "AZ" => "374", "AZ" => "994", "BA" => "387", "BB" => "246", "BD" => "880", "BE" => "32", "BF" => "226", "BG" => "359", "BH" => "973", "BI" => "257", "BJ" => "229", "BM" => "441", "BN" => "673", "BO" => "591", "BR" => "55", "BS" => "242", "BT" => "975", "BW" => "267", "BY" => "375", "BZ" => "501", "CA" => "1", "CC" => "61", "CD" => "243", "CF" => "236", "CG" => "242", "CH" => "41", "CI" => "225", "CK" => "682", "CL" => "56", "CM" => "237", "CN" => "86", "CO" => "57", "CR" => "506", "CS" => "381", "CU" => "53", "CV" => "238", "CX" => "61", "CY" => "392", "CY" => "357", "CZ" => "420", "DE" => "49", "DJ" => "253", "DK" => "45", "DM" => "767", "DO" => "809", "DZ" => "213", "EC" => "593", "EE" => "372", "EG" => "20", "EH" => "212", "ER" => "291", "ES" => "34", "ET" => "251", "FI" => "358", "FJ" => "679", "FK" => "500", "FM" => "691", "FO" => "298", "FR" => "33", "GA" => "241", "GB" => "44", "GD" => "473", "GE" => "995", "GF" => "594", "GG" => "44", "GH" => "233", "GI" => "350", "GL" => "299", "GM" => "220", "GN" => "224", "GP" => "590", "GQ" => "240", "GR" => "30", "GT" => "502", "GU" => "671", "GW" => "245", "GY" => "592", "HK" => "852", "HN" => "504", "HR" => "385", "HT" => "509", "HU" => "36", "ID" => "62", "IE" => "353", "IL" => "972", "IM" => "44", "IN" => "91", "IO" => "246", "IQ" => "964", "IR" => "98", "IS" => "354", "IT" => "39", "JE" => "44", "JM" => "876", "JO" => "962", "JP" => "81", "KE" => "254", "KG" => "996", "KH" => "855", "KI" => "686", "KM" => "269", "KN" => "869", "KP" => "850", "KR" => "82", "KW" => "965", "KY" => "345", "KZ" => "7", "LA" => "856", "LB" => "961", "LC" => "758", "LI" => "423", "LK" => "94", "LR" => "231", "LS" => "266", "LT" => "370", "LU" => "352", "LV" => "371", "LY" => "218", "MA" => "212", "MC" => "377", "MD"  > "533", "MD" => "373", "ME" => "382", "MG" => "261", "MH" => "692", "MK" => "389", "ML" => "223", "MM" => "95", "MN" => "976", "MO" => "853", "MP" => "670", "MQ" => "596", "MR" => "222", "MS" => "664", "MT" => "356", "MU" => "230", "MV" => "960", "MW" => "265", "MX" => "52", "MY" => "60", "MZ" => "258", "NA" => "264", "NC" => "687", "NE" => "227", "NF" => "672", "NG" => "234", "NI" => "505", "NL" => "31", "NO" => "47", "NP" => "977", "NR" => "674", "NU" => "683", "NZ" => "64", "OM" => "968", "PA" => "507", "PE" => "51", "PF" => "689", "PG" => "675", "PH" => "63", "PK" => "92", "PL" => "48", "PM" => "508", "PR" => "787", "PS" => "970", "PT" => "351", "PW" => "680", "PY" => "595", "QA" => "974", "RE" => "262", "RO" => "40", "RS" => "381", "RU" => "7", "RW" => "250", "SA" => "966", "SB" => "677", "SC" => "248", "SD" => "249", "SE" => "46", "SG" => "65", "SH" => "290", "SI" => "386", "SJ" => "47", "SK" => "421", "SL" => "232", "SM" => "378", "SN" => "221", "SO" => "252", "SO" => "252", "SR"  > "597", "ST" => "239", "SV" => "503", "SY" => "963", "SZ" => "268", "TA" => "290", "TC" => "649", "TD" => "235", "TG" => "228", "TH" => "66", "TJ" => "992", "TK" =>  "690", "TL" => "670", "TM" => "993", "TN" => "216", "TO" => "676", "TR" => "90", "TT" => "868", "TV" => "688", "TW" => "886", "TZ" => "255", "UA" => "380", "UG" =>  "256", "US" => "1", "UY" => "598", "UZ" => "998", "VA" => "379", "VC" => "784", "VE" => "58", "VG" => "284", "VI" => "340", "VN" => "84", "VU" => "678", "WF" => "681", "WS" => "685", "YE" => "967", "YT" => "262", "ZA" => "27","ZM" => "260", "ZW" => "263");
        if (isset($arrCode[$code]))
            return $arrCode[$code];
    } catch (exception $e) {}
    return "";
}

function paycomet_acctInfo($params)
{

    $customer = $params["clientdetails"]["model"];
    $acctInfoData = array();
    $date_now = new \DateTime("now");

    $date_customer = new \DateTime($customer->datecreated);

    $diff = $date_now->diff($date_customer);
    $dias = $diff->days;

    if ($dias==0) {
        $acctInfoData["chAccAgeInd"] = "02";
    } else if ($dias < 30) {
        $acctInfoData["chAccAgeInd"] = "03";
    } else if ($dias < 60) {
        $acctInfoData["chAccAgeInd"] = "04";
    } else {
        $acctInfoData["chAccAgeInd"] = "05";
    }

    $accChange = new \DateTime($customer->updated_at);
    $acctInfoData["chAccChange"] = $accChange->format('Ymd');

    $date_customer_upd = new \DateTime($customer->updated_at);
    $diff = $date_now->diff($date_customer_upd);
    $dias_upd = $diff->days;

    if ($dias_upd==0) {
        $acctInfoData["chAccChangeInd"] = "01";
    } else if ($dias_upd < 30) {
        $acctInfoData["chAccChangeInd"] = "02";
    } else if ($dias_upd < 60) {
        $acctInfoData["chAccChangeInd"] = "03";
    } else {
        $acctInfoData["chAccChangeInd"] = "04";
    }

    $chAccDate = new \DateTime($customer->datecreated);
    $acctInfoData["chAccDate"] = $chAccDate->format('Ymd');

    $acctInfoData["nbPurchaseAccount"] = paycomet_numPurchaseCustomer($customer->id,1,6,"month");

    $acctInfoData["txnActivityDay"] = paycomet_numPurchaseCustomer($customer->id,0,1,"day");
    $acctInfoData["txnActivityYear"] = paycomet_numPurchaseCustomer($customer->id,0,1,"year");

    $acctInfoData["suspiciousAccActivity"] = "01";

    return $acctInfoData;
}


function paycomet_numPurchaseCustomer($id_customer,$valid=1,$interval=1,$intervalType="day")
{
    try {
        $from = new \DateTime("now");
        $from->modify('-' . $interval . ' ' . $intervalType);
        $from = $from->format('Y-m-d h:m:s');

        if ($valid==1) {
            $orderCollection = Capsule::table('tblorders')->select('id')->where('userid',$id_customer)->where('date',">=",$from)->where('status',"Active")->get();
        } else {
            $orderCollection = Capsule::table('tblorders')->select('id')->where('userid',$id_customer)->where('date',">=",$from)->get();
        }
        return sizeof($orderCollection);
    } catch (exception $e) {
        return 0;
    }
}


function paycomet_threeDSRequestorAuthenticationInfo($params)
{

    $threeDSRequestorAuthenticationInfo = array();
    $threeDSRequestorAuthenticationInfo["threeDSReqAuthMethod"] = "02";

    $customer = $params["clientdetails"]["model"];

    $lastLogin = new \DateTime($customer->lastlogin);
    $threeDSRequestorAuthenticationInfo["threeDSReqAuthTimestamp"] = $lastLogin->format('YmdHm');


    return $threeDSRequestorAuthenticationInfo;
}

function paycomet_haveRecurringProduct($params) {

    $cart = $params["cart"];
    foreach ($cart->items as $key=>$item) {
        if (isset($item->recurring)) {
            return true;
        }
    }
    return false;
}


function paycomet_getShoppingCart($params)
{

    $cart = $params["cart"];

    foreach ($cart->items as $key=>$item) {
        $shoppingCartData[$key]["sku"] = $item->id;
        $shoppingCartData[$key]["quantity"] = number_format($item->qty, 0, '.', '');

        $objAmount = $item->amount;
        $shoppingCartData[$key]["unitPrice"] = number_format($objAmount->toNumeric()*100, 0, '.', '');
        $shoppingCartData[$key]["name"] = mb_substr($item->name,0,254);
    }

    return array("shoppingCart"=>array_values($shoppingCartData));
}

function paycomet_getMerchantData($params)
{
    $MERCHANT_EMV3DS = paycomet_getEMV3DS($params);
    $SHOPPING_CART = paycomet_getShoppingCart($params);

    $datos = array_merge($MERCHANT_EMV3DS,$SHOPPING_CART);
    return $datos;
}