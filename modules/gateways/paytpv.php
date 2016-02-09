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
 * @version    1.0
 *
**/

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
function paytpv_MetaData()
{

    return array(
        'DisplayName' => 'PAYTPV Payment Gateway Module',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => false,
        'TokenisedStorage' => true,
    );

}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function paytpv_config()
{
    $config_array = array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'PAYTPV',
        ),
        // a text field type allows for single line text input
        'clientcode' => array(
            'FriendlyName' => 'Código de Cliente',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Introduzca su Código de Cliente de PAYTPV',
        ),
        // a password field type allows for masked text input
        'term' => array(
            'FriendlyName' => 'Número de Terminal',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Introduzca su Número de Terminal de PAYTPV',
        ),

        // a password field type allows for masked text input
        'pass' => array(
            'FriendlyName' => 'Contraseña',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Introduzca su Contraseña de PAYTPV',
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


        // the yesno field type displays a single checkbox option
        'testmode' => array(
            'FriendlyName' => 'Modo Test',
            'Type' => 'yesno',
            'Description' => 'Seleccionar para habilitar el modo Test',
        ),

    );
    
    return $config_array;
}


/**
 * Capture payment.
 *
 * Called when a payment is to be processed and captured.
 *
 * The card cvv number will only be present for the initial card holder present
 * transactions. Automated recurring capture attempts will not provide it.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return array Transaction response status
 */

function paytpv_storeremote($params){

    $testmode = ($params['testmode']) ? 1:0;

    if ($testmode==1){
        return array(
            "status" => "success",
            "gatewayid" => "1,TOKEN_PAYTPV",
            "rawdata" => $res,
        );
    }


    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];
    $terminales = $params['terminales'];
    $tdfirst = $params['tdfirst'];

    // Credit Card Parameters
    $cardType = $params['cardtype'];
    $cardNumber = $params['cardnum'];
    $cardExpiry = $params['cardexp'];
    $cardStart = $params['cardstart'];
    $cardIssueNumber = $params['cardissuenum'];
    $cardCvv = $params['cardcvv'];
    $tdmin = $params['tdmin'];

    $client = new SoapClient( 'https://secure.paytpv.com/gateway/xml_bankstore.php?wsdl');
            
    $DS_MERCHANT_MERCHANTCODE = $clientcode;
    $DS_MERCHANT_TERMINAL = $term;
    $DS_MERCHANT_PAN = $cardNumber;
    $DS_MERCHANT_EXPIRYDATE = $cardExpiry;
    $DS_MERCHANT_CVV2 = $cardCvv;
    $DS_MERCHANT_ORDER = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);
    $DS_MERCHANT_MERCHANTSIGNATURE = sha1($DS_MERCHANT_MERCHANTCODE . $DS_MERCHANT_PAN . $DS_MERCHANT_CVV2 . $DS_MERCHANT_TERMINAL . $pass);

    $DS_ORIGINAL_IP = $_SERVER['REMOTE_ADDR'];

    $p = array(

        'DS_MERCHANT_MERCHANTCODE' => $DS_MERCHANT_MERCHANTCODE,
        'DS_MERCHANT_TERMINAL' => $DS_MERCHANT_TERMINAL,
        'DS_MERCHANT_PAN' => $DS_MERCHANT_PAN,
        'DS_MERCHANT_EXPIRYDATE' => $DS_MERCHANT_EXPIRYDATE,
        'DS_MERCHANT_CVV2' => $DS_MERCHANT_CVV2,
        'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
        'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP
    );

    $res = $client->__soapCall( 'add_user', $p);

    if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']){
        $DS_IDUSER = $res['DS_IDUSER'];
        $DS_TOKEN_USER = $res['DS_TOKEN_USER'];
        return array(
            "status" => "success",
            "gatewayid" => $DS_IDUSER.",".$DS_TOKEN_USER,
            "rawdata" => $res,
        );
    }


    return array(
        "status" => "failed",
        "rawdata" => $res,
    );

}

function paytpv_capture($params){

    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];
    $terminales = $params['terminales'];
    $tdfirst = $params['tdfirst'];

    // Credit Card Parameters
    $cardType = $params['cardtype'];
    $cardNumber = $params['cardnum'];
    $cardExpiry = $params['cardexp'];
    $cardStart = $params['cardstart'];
    $cardIssueNumber = $params['cardissuenum'];
    $cardCvv = $params['cccvv'];
    $tdmin = $params['tdmin'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    $gatewayId = $params['gatewayid'];   

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // perform API call to initiate refund and interpret result
    try{
        $client = new SoapClient( 'https://secure.paytpv.com/gateway/xml_bankstore.php?wsdl');
            
        $DS_MERCHANT_MERCHANTCODE = $clientcode;
        $DS_MERCHANT_TERMINAL = $term;
        $DS_MERCHANT_PAN = $cardNumber;
        $DS_MERCHANT_EXPIRYDATE = $cardExpiry;
        $DS_MERCHANT_CVV2 = $cardCvv;
        $DS_MERCHANT_ORDER = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);
        $DS_MERCHANT_AUTHCODE = $transactionIdToRefund;
        $DS_MERCHANT_CURRENCY = $currencyCode;
        $DS_MERCHANT_AMOUNT = number_format($amount * 100, 0, '.', '');
        
        $DS_ORIGINAL_IP = $_SERVER['REMOTE_ADDR'];
        
        $success = 'error';
        $responseData = '';
        $refundTransactionId = 0;
        $error = 1;

        if ($gatewayId!=""){
            $datos = explode(",",$gatewayId);
            $DS_IDUSER = $datos[0];
            $DS_TOKEN_USER = $datos[1];
            $error = 0;
        }

        if ($error==0){
            $DS_MERCHANT_MERCHANTSIGNATURE = sha1($DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_AMOUNT . $DS_MERCHANT_ORDER . $pass);

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

            $testmode = ($params['testmode']) ? 1:0;


            if ($testmode==1){
                if ($DS_TOKEN_USER=="TOKEN_PAYTPV"){
                    $res['DS_ERROR_ID'] = 0;
                    $res['DS_MERCHANT_AUTHCODE'] = "PAYTPV_TEST_" . date("YmdHis");
                }else{
                    $res['DS_ERROR_ID'] = 1;
                }
            }else{
                $res = $client->__soapCall( 'execute_purchase', $p);
            }

            if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']){
                $success = 'success';
                $responseData = $res;

                $refundTransactionId = $res['DS_MERCHANT_AUTHCODE'];

                $arrGatewayId = array($DS_IDUSER,$DS_TOKEN_USER);

                $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

                // Save token, remove cardnumer
                update_query( "tblclients", array( "gatewayid" => implode( ",", $arrGatewayId ), "cardnum" => ""), array("id" => $params['clientdetails']['userid']));

            }
        }
        
    }catch (exception $e){}
        

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $success,
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
    );
}


function paytpv_adminstatusmsg($vars) {
    $gatewayProfileID = get_query_val(
        'tblclients',
        'gatewayid',
        array('id' => $vars['userid'])
    );

    return array(
        'type' => 'info',
        'title' => 'PAYTPV Profile',
        'msg' => ($gatewayProfileID) ? 'This client has a PAYTPV Profile storing their card details for automated recurring billing with ID ' . $gatewayProfileID : 'This client does not yet have a gateway profile setup'
    );
}


function paytpv_isSecureTransaction($terminales,$tdfirst,$tdmin,$importe=0,$card=0){
        
    // Si solo tiene Terminal Seguro
    if ($terminales=="Seguro")
        return true;

    if ($terminales=="No-Seguro")
        return false;

    // Si esta definido que el pago es 3d secure y no estamos usando una tarjeta tokenizada
    if ($terminales=="Ambos"){

        if ($tdfirst=="Si" && $card==0)
            return true;

        // Si se supera el importe maximo para compra segura
        if (($tdmin>0 && $tdmin < $importe))
            return true;

    }
    
    return false;
}


$result = select_query("tblpaymentgateways", "value", array("gateway" => "paytpv","setting" => "terminales"));
$data = mysql_fetch_array($result);
$terminales = $data[0];

$result = select_query("tblpaymentgateways", "value", array("gateway" => "paytpv","setting" => "tdfirst"));
$data = mysql_fetch_array($result);
$tdfirst = $data[0];

$result = select_query("tblpaymentgateways", "value", array("gateway" => "paytpv","setting" => "tdmin"));
$data = mysql_fetch_array($result);
$tdmin = $data[0];

$importe = $_SESSION['orderdetails']['TotalDue'];

if (isset($_POST["ccinfo"])){
    $card = ($_POST["ccinfo"]=="useexisting")?1:0;
    setcookie("paytpv_card", $card);
}else{
    $card = $_COOKIE["paytpv_card"];
}

$isSecureTransaction = paytpv_isSecureTransaction($terminales,$tdfirst,$tdmin,$importe,$card);

$secure = ($isSecureTransaction)?1:0;

//print "(Secure: ". $secure .")-->Terminales " . $terminales . "---Tdfirst: " . $tdfirst . "---Tdmin: " . $tdmin . "--Importe: " . $importe . "--Card: " . $card;

if ($isSecureTransaction){


    function paytpv_3dsecure($params) {

        // Gateway Configuration Parameters
        $clientcode = $params['clientcode'];
        $term = $params['term'];
        $pass = $params['pass'];
        $terminales = $params['terminales'];
        $tdfirst = $params['tdfirst'];
        $tdmin = $params['tdmin'];

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

        // PAYTPV
        $importe = number_format($amount * 100, 0, '.', '');

        $paytpv_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

        $OPERATION = "109";

        $signature = md5($clientcode . $DS_IDUSER . $DS_TOKEN_USER . $term . $OPERATION . $paytpv_order_ref . $importe . $currencyCode . md5($pass));
        $fields = array
        (
            'MERCHANT_MERCHANTCODE' => $clientcode,
            'MERCHANT_TERMINAL' => $term,
            'OPERATION' => $OPERATION,
            'LANGUAGE' => "es",
            'MERCHANT_MERCHANTSIGNATURE' => $signature,
            'MERCHANT_ORDER' => $paytpv_order_ref,
            'MERCHANT_AMOUNT' => $importe,
            'MERCHANT_CURRENCY' => $currencyCode,
            'IDUSER' => $DS_IDUSER,
            'TOKEN_USER' => $DS_TOKEN_USER,          
            '3DSECURE' => 1,
            'URLOK' => $systemUrl . '/viewinvoice.php?id=' . $invoiceId,
            'URLKO' => $systemUrl . '/viewinvoice.php?id=' . $invoiceId
        );

        $testmode = ($params['testmode']) ? 1:0;
        if ($testmode==1){
            $fields['URLNOT'] = $systemUrl . '/modules/gateways/callback/paytpv.php';
            $url = $systemUrl . "/modules/gateways/templates/paytpv/payment_3ds_test.php";
        }else{
            $url = "https://secure.paytpv.com/gateway/bnkgateway.php";
        }

        $query = http_build_query($fields);
        $url = $url . "?".$query;

        $code = '<form method="post" action="'.$url.'">

        <p align="center"><input type="submit" value="Continue >>" /></p>
        </noscript>
        </form>';

        
        return $code;
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
function paytpv_refund($params)
{
    
    // Gateway Configuration Parameters
    $testmode = ($params['testmode']) ? 1:0;
    $secretKey = $params['pass'];
    $clientcode = $params['clientcode'];
    $pass = $params['pass'];
    $term = $params['term'];

    $gatewayids = explode( ",", $params['gatewayid'] );


    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    $invoiceId = $params['invoiceid'];

    $paytpv_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    $client = new SoapClient( 'https://secure.paytpv.com/gateway/xml_bankstore.php?wsdl');
        
    $DS_MERCHANT_MERCHANTCODE = $clientcode;
    $DS_MERCHANT_TERMINAL = $term;
    $DS_IDUSER = $gatewayids[0];
    $DS_TOKEN_USER = $gatewayids[1];
    $DS_MERCHANT_ORDER = $paytpv_order_ref;
    $DS_MERCHANT_AUTHCODE = $transactionIdToRefund;
    $DS_MERCHANT_CURRENCY = $currencyCode;
    $DS_MERCHANT_AMOUNT = $importe = number_format($refundAmount * 100, 0, '.', '');
    $DS_MERCHANT_MERCHANTSIGNATURE = sha1($DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_AUTHCODE . $DS_MERCHANT_ORDER . $pass);

    $DS_ORIGINAL_IP = $_SERVER['REMOTE_ADDR'];

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
    
    if ($testmode==1){
        if ($DS_TOKEN_USER=="TOKEN_PAYTPV"){
            $res['DS_ERROR_ID'] = 0;
            $res['DS_MERCHANT_AUTHCODE'] = "PAYTPV_TEST_" . date("YmdHis");
        }else{
            $res['DS_ERROR_ID'] = 1;
        }
    }else{
        $res = $client->__soapCall( 'execute_refund', $p);
    }
    
    $success = 'error';

    if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']) {
        $success = 'success';
        $responseData = $res;
        $refundTransactionId = $res['DS_MERCHANT_AUTHCODE'];
    }
        
    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $success,
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
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
function paytpv_cancelSubscription($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testmode = $params['testmode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

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
        'rawdata' => $responseData,
    );
}
