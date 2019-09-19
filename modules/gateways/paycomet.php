<?php
/**
 * Modulo de pago PAYCOMET
 *
 * Este módulo de pago permite realizar pagos con tarjeta de credito mediante la pasarela PAYCOMET
 * PAYCOMET - Pasarela de pagos PCI-DSS Nivel 1 Multiplataforma
 *
 * @package    paycomet.php
 * @author     PAYCOMET <info@paycomet.com>
 * @copyright  2016 PAYCOMET
 * @version    2.0
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
function paycomet_MetaData()
{

    return array(
        'DisplayName' => 'PAYCOMET Payment Gateway Module',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );

}


/* La captura de datos de tarjeta por Iframe */
function paycomet_nolocalcc()
{
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
 * Update token
 *
 * Called when a customer access to Manage Credit Cards 
 */
function paycomet_remoteupdate($params)
{
    global $_LANG;
    if( !$params["gatewayid"] ) 
    {
        return "<p align=\"center\">Debe dar de alta una tarjeta o realizar un pago para poder editar la tarjeta</p>";
    }

    $userid = $_SESSION['uid'];  

    global $cc_encryption_hash;
    $cchash = md5($cc_encryption_hash.$userid);
   
    $result = select_query("tblclients", "cardtype,cardlastfour, AES_DECRYPT(`tblclients`.`expdate`,'". $cchash. "') as expdate", array("id" => $userid));
    $data = mysql_fetch_array($result);
    $cardType = $data['cardtype'];
    $cardLastFour = $data['cardlastfour'];  
    $expdate = $data['expdate'];

    $thereturn = '<div class="credit-card">
                    <div class="card-icon pull-right">
                        <b class="fa-2x fas fa-credit-card">&nbsp;</b>
                    </div>
                    <div class="card-type">' . $cardType .'</div>
                    <div class="card-number">XXXX XXXX XXXX ' . $cardLastFour . '</div>
                    <div class="card-expiry">' . $_LANG['creditcardcardexpires'] . ': ' . substr($expdate,0,2) . "/" . substr($expdate,2,2) .'</div>
                    
                    <div class="end"></div>
                 </div>';


    $thereturn .= '<h3>'.$_LANG['creditcardenternewcard'].'</h3>';

    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];
    

    // System Parameters    
    $systemUrl = $params['systemurl'];

    $paycomet_order_ref = $userid;

    $OPERATION = "107";

    $signature = hash('sha512', $clientcode . $term . $OPERATION . $paycomet_order_ref . md5($pass));
    $fields = array
    (
        'MERCHANT_MERCHANTCODE' => $clientcode,
        'MERCHANT_TERMINAL' => $term,
        'OPERATION' => $OPERATION,
        'LANGUAGE' => "es",
        'MERCHANT_MERCHANTSIGNATURE' => $signature,
        'MERCHANT_ORDER' => $paycomet_order_ref,        
        'URLOK' => $systemUrl . 'clientarea.php?action=creditcard',
        'URLKO' => $systemUrl . 'clientarea.php?action=creditcard'
    );

    $url = "https://api.paycomet.com/gateway/ifr-bankstore";
    
    $query = http_build_query($fields);


    $url .= '?' . $query;

    $thereturn .= "<iframe src=\"".$url . "\" height=\"650\" width=\"450\" frameborder=\"0\"></iframe><br/>";
    
    return  $thereturn;
}

/**
 * Update token
 *
 * Called when a customer access to Manage Credit Cards when paycomet_nolocalcc is not defined
 */
function paycomet_storeremote($params){

    global $remote_ip;
    if ($remote_ip=="")
        $remote_ip = gethostbyname(gethostname());

    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];
    
    // Credit Card Parameters    
    $cardNumber = $params['cardnum'];
    $cardExpiry = $params['cardexp'];    
    $cardCvv = $params['cardcvv'];
    
    $client = new SoapClient( 'https://api.paycomet.com/gateway/xml-bankstore?wsdl');

    $DS_MERCHANT_MERCHANTCODE = $clientcode;
    $DS_MERCHANT_TERMINAL = $term;
    $DS_MERCHANT_PAN = $cardNumber;
    $DS_MERCHANT_EXPIRYDATE = $cardExpiry;
    $DS_MERCHANT_CVV2 = $cardCvv;

    $action = $params["action"];

    $DS_ORIGINAL_IP = $remote_ip;

    // Delete card
    if ($action=="delete"){
        $gatewayId = $params['gatewayid'];
        $datos = explode(",",$gatewayId);
        $DS_IDUSER = $datos[0];
        $DS_TOKEN_USER = $datos[1];

        $DS_MERCHANT_MERCHANTSIGNATURE = sha1($DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $pass);

        $p = array(

            'DS_MERCHANT_MERCHANTCODE' => $DS_MERCHANT_MERCHANTCODE,
            'DS_MERCHANT_TERMINAL' => $DS_MERCHANT_TERMINAL,
            'DS_IDUSER' => $DS_IDUSER,
            'DS_TOKEN_USER' => $DS_TOKEN_USER,
            'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
            'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP
        );

        $res = $client->__soapCall( 'remove_user', $p);

        if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']){

            return array(
                "status" => "success",
                "gatewayid" => "",
                "rawdata" => $res,
            );
        }
        
    // Update/New Card
    }else{
        $DS_MERCHANT_MERCHANTSIGNATURE = hash('sha512', $DS_MERCHANT_MERCHANTCODE . $DS_MERCHANT_PAN . $DS_MERCHANT_CVV2 . $DS_MERCHANT_TERMINAL . $pass);

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

            //$params['cardnum'] = "";

            return array(
                "status" => "success",
                "gatewayid" => $DS_IDUSER.",".$DS_TOKEN_USER,
                "rawdata" => $res,
            );
        }
    }

    return array(
        "status" => "failed",
        "rawdata" => $res,
    );

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

/* Pagos por Iframe en nueva página */
function paycomet_remoteinput($params)
{

    global $_LANG;

    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];
    $terminales = $params['terminales'];
    $tdfirst = $params['tdfirst'];
    $tdmin = $params['tdmin'];
    

    $locale = paycomet_getLang($_LANG['locale']);

    $gatewayId = $params['gatewayid'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // PAYCOMET
    $importe = number_format($amount * 100, 0, '.', '');

    $paycomet_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

    $OPERATION = "1";

    $isSecureTransaction = paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$amount,0);
    $secure = ($isSecureTransaction)?1:0;

    $signature = hash('sha512', $clientcode . $term . $OPERATION . $paycomet_order_ref . $importe . $currencyCode . md5($pass));
    $fields = array
    (
        'MERCHANT_MERCHANTCODE' => $clientcode,
        'MERCHANT_TERMINAL' => $term,
        'OPERATION' => $OPERATION,
        'LANGUAGE' => $locale,
        'MERCHANT_MERCHANTSIGNATURE' => $signature,
        'MERCHANT_ORDER' => $paycomet_order_ref,
        'MERCHANT_AMOUNT' => $importe,
        'MERCHANT_CURRENCY' => $currencyCode,     
        '3DSECURE' => $secure,
        'URLOK' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId,
        'URLKO' => $systemUrl . 'viewinvoice.php?id=' . $invoiceId
    );

    $url = "https://api.paycomet.com/gateway/ifr-bankstore";
   
    $htmlOutput = "";
    $htmlOutput .= '<form method="get" action="' . $url . '">';
    if( $params["gatewayid"] ) 
    {
        $userid = $_SESSION['uid'];  
   
        $result = select_query("tblclients", "cardtype,cardlastfour", array("id" => $userid));
        $data = mysql_fetch_array($result);
        $cardType = $data['cardtype'];
        $cardLastFour = $data['cardlastfour'];

        $datos = explode(",",$gatewayId);
        $DS_IDUSER = $datos[0];
        $DS_TOKEN_USER = $datos[1];

        $isSecureTransaction = paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$amount,$DS_IDUSER);
        $secure = ($isSecureTransaction)?1:0;

        $fields2 = $fields;
        $OPERATION = "109";
        $signature = hash('sha512', $clientcode . $DS_IDUSER . $DS_TOKEN_USER . $term . $OPERATION . $paycomet_order_ref . $importe . $currencyCode . md5($pass));
        $fields2["IDUSER"] = $DS_IDUSER;
        $fields2["TOKEN_USER"] = $DS_TOKEN_USER;
        $fields2["OPERATION"] = $OPERATION;
        $fields2["MERCHANT_MERCHANTSIGNATURE"] = $signature;

        $fields2["3DSECURE"] = $secure;

        
        $url_token = $url . "?" . $query = http_build_query($fields2);
        
        //$htmlOutput .= "<a style='float:left' href='".$url_token."'>Pagar con Tarjeta XXXX XXXX XXXX " . $cardLastFour . " (" . $cardType .")</a><br><hr/><br><span style='float:left'>NUEVA TARJETA:</span><br/><br/>";
        $htmlOutput .= "<p class='text-left'>" . $_LANG['creditcarduseexisting']."</p><p class='text-left'><input type='button' class='btn btn-success btn-sm'  value='Pagar con Tarjeta XXXX XXXX XXXX " . $cardLastFour . " (" . $cardType .")' onclick=\"location.href='" . $url_token. "'\"><hr/></p><span style='float:left'>" . $_LANG['creditcardenternewcard']."</span><br/><br/>";
    }
    
   
    $query = http_build_query($fields);

    foreach ($fields as $k => $v) {
        $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
    }
    $htmlOutput .= '</form>';

    
    return $htmlOutput;
}

function paycomet_capture($params){
    global $remote_ip;
    if ($remote_ip=="")
        $remote_ip = gethostbyname(gethostname());

    // Gateway Configuration Parameters
    $clientcode = $params['clientcode'];
    $term = $params['term'];
    $pass = $params['pass'];
    
    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    $gatewayId = $params['gatewayid'];   

    $currencyCode = $params['currency'];

    
    try{
        $client = new SoapClient( 'https://api.paycomet.com/gateway/xml-bankstore?wsdl');
            
        $DS_MERCHANT_MERCHANTCODE = $clientcode;
        $DS_MERCHANT_TERMINAL = $term;        
        $DS_MERCHANT_ORDER = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);        
        $DS_MERCHANT_CURRENCY = $currencyCode;
        $DS_MERCHANT_AMOUNT = number_format($amount * 100, 0, '.', '');

        $DS_ORIGINAL_IP = $remote_ip;
        
        $success = 'error';
        $responseData = '';
        $TransactionId = 0;
        $error = 1;

        if ($gatewayId!=""){
            $datos = explode(",",$gatewayId);
            $DS_IDUSER = $datos[0];
            $DS_TOKEN_USER = $datos[1];
            $error = 0;
        }

        if ($error==0){
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
            
            if ('' == $res['DS_ERROR_ID'] || 0 == $res['DS_ERROR_ID']){
                $success = 'success';
                $responseData = $res;

                $TransactionId = $res['DS_MERCHANT_AUTHCODE'];

                $arrGatewayId = array($DS_IDUSER,$DS_TOKEN_USER);

                // Detect module name from filename.
                $gatewayModuleName = basename(__FILE__, '.php');

                // Fetch gateway configuration parameters.
                $gatewayParams = getGatewayVariables($gatewayModuleName);

                $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

                // Save token, remove cardnumer
                update_query( "tblclients", array( "gatewayid" => implode( ",", $arrGatewayId ), "cardnum" => ""), array("id" => $params['clientdetails']['userid']));

            }else{
                $responseData = $res;

            }
        }
        
    }catch (exception $e){}
        

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $success,
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the capture transaction
        'transid' => $TransactionId,        
    );
}


function paycomet_adminstatusmsg($vars) {
   
    $gatewayProfileID = get_query_val(
        'tblclients',
        'gatewayid',
        array('id' => $vars['userid'])
    );

    return array(
        'type' => 'info',
        'title' => 'PAYCOMET Profile',
        'msg' => ($gatewayProfileID) ? 'This client has a PAYCOMET Profile storing their card details for automated recurring billing with ID ' . $gatewayProfileID : 'This client does not yet have a gateway profile setup'
    );
}


function paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$importe=0,$card=0){

    $tdmin = str_replace(",",".",$tdmin); // Por si se han definido los decimales con ","
    $tdmin = number_format($tdmin * 100, 0, '.', '');
    $importe = number_format($importe * 100, 0, '.', '');
   
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


$result = select_query("tblpaymentgateways", "value", array("gateway" => "paycomet","setting" => "terminales"));
$data = mysql_fetch_array($result);
$terminales = $data[0];

$result = select_query("tblpaymentgateways", "value", array("gateway" => "paycomet","setting" => "tdfirst"));
$data = mysql_fetch_array($result);
$tdfirst = $data[0];

$result = select_query("tblpaymentgateways", "value", array("gateway" => "paycomet","setting" => "tdmin"));
$data = mysql_fetch_array($result);
$tdmin = $data[0];

$amount = $_SESSION['orderdetails']['TotalDue'];

if (isset($_POST["ccinfo"])){
    $card = ($_POST["ccinfo"]=="useexisting")?1:0;
    setcookie("paycomet_card", $card);
}else{
    $card = $_COOKIE["paycomet_card"];
}

$isSecureTransaction = paycomet_isSecureTransaction($terminales,$tdfirst,$tdmin,$amount,$card);

$secure = ($isSecureTransaction)?1:0;

//print "(Secure: ". $secure .")-->Terminales " . $terminales . "---Tdfirst: " . $tdfirst . "---Tdmin: " . $tdmin . "--Importe: " . $imamountporte . "--Card: " . $card;

if ($isSecureTransaction){
    

    function paycomet_3dsecure($params) {
        

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

        // PAYCOMET
        $importe = number_format($amount * 100, 0, '.', '');

        $paycomet_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

        $OPERATION = "109";

        $signature = hash('sha512', $clientcode . $DS_IDUSER . $DS_TOKEN_USER . $term . $OPERATION . $paycomet_order_ref . $importe . $currencyCode . md5($pass));
        $fields = array
        (
            'MERCHANT_MERCHANTCODE' => $clientcode,
            'MERCHANT_TERMINAL' => $term,
            'OPERATION' => $OPERATION,
            'LANGUAGE' => "es",
            'MERCHANT_MERCHANTSIGNATURE' => $signature,
            'MERCHANT_ORDER' => $paycomet_order_ref,
            'MERCHANT_AMOUNT' => $importe,
            'MERCHANT_CURRENCY' => $currencyCode,
            'IDUSER' => $DS_IDUSER,
            'TOKEN_USER' => $DS_TOKEN_USER,          
            '3DSECURE' => 1,
            'URLOK' => $systemUrl . '/viewinvoice.php?id=' . $invoiceId,
            'URLKO' => $systemUrl . '/viewinvoice.php?id=' . $invoiceId
        );

        $url = "https://api.paycomet.com/gateway/ifr-bankstore";
        
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
function paycomet_refund($params)
{
    global $remote_ip;
    if ($remote_ip=="")
        $remote_ip = gethostbyname(gethostname());
    
    // Gateway Configuration Parameters
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

    $paycomet_order_ref = str_pad($invoiceId, 8, "0", STR_PAD_LEFT);

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    $client = new SoapClient( 'https://api.paycomet.com/gateway/xml-bankstore?wsdl');
        
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
