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
?>

<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
	<title>SAS Servidor Autenticación PAYTPV</title>
	<meta http-equiv="Expires" content="-1" />
	<meta http-equiv="Expires" content="Monday, 01-Jan-90 00:00:00 GMT" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Cache-Control" content="no-cache" />
	<link media="screen" href="css/2100.css" type="text/css" rel="StyleSheet" />
	<script type="text/javascript" src="http://code.jquery.com/jquery-2.1.4.min.js"></script>

	<script type="text/javascript">
	function checkform() {
		if ($("#demopin").val() == "1234") {
			// Throw notification Test
			$.post( '<?php print $_GET["URLNOT"];?>', $( "#formulario" ).serialize(), function( data ) {
				parent.location=data.urlok;
			}, "json");
		} else {
			document.getElementById("showerror").innerHTML = "El PIN introducido es erróneo";
		}
		return false;
	}
	</script>
</head>
<body>
	<table width="370" cellspacing="0" cellpadding="0">
		<tbody>
			<tr>
				<td align="center" colspan="2">
					<h3>Autenticación Comercio Electrónico Seguro</h3>
				</td>
			</tr>
			<tr>
				<td width="185px" align="center">
					<img border="0" alt="VISA" src="img/VerifiedByVisa.jpg"></td>
				<td width="185px" align="center"></td>
			</tr>
			<tr>
				<td colspan="2">
					<hr></td>
			</tr>
		</tbody>
	</table>
	<div id="capaPantalla1" style="position: absolute; visibility: visible; left: 5px;">
		<div style="position:relative;margin:0px auto;display:block;top: -10px;">
			<h4 class="uno">Compruebe los datos de su operación</h4>
		</div>
		<form onsubmit="javascript:checkform();" id="formulario" name="formulario" method="formulario">
		<table width="374" cellspacing="0" cellpadding="0" border="0" style="BORDER-COLLAPSE: collapse">
			<tbody>
				<tr>
					<td style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px"> <font class="titulotabla">Importe:</font>
					</td>
					<td style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px"> <font class="detalletabla"><?php print $_GET["MERCHANT_AMOUNT"]/100;?></font>
					</td>
				</tr>
				<tr>
					<td style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px">
						<font class="titulotabla">Fecha:</font>
					</td>
					<td style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px">
						<font class="detalletabla"><?php print date("d-m-Y");?></font>
					</td>
				</tr>
				<tr>
					<td style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px">
						<font class="titulotabla">Hora:</font>
					</td>
					<td style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px">
						<font class="detalletabla"><?php print date("H:i");?></font>
					</td>
				</tr>
				
				<tr>
					<td width="362" height="1" colspan="2">
						<div style="margin:0px auto;padding:0px auto;">
							<hr></div>
						<div style="margin:0px auto;display:block;top: -10px;">
							<h4 class="dos">
								Introduzca el PIN de 4 dígitos de su tarjeta de crédito/débito:
							</h4>
						</div>
					</td>
				</tr>
				<tr>
					<td width="363" align="center" class="letra" colspan="2">
						<table align="center">
							<tbody>
								<tr>
									<td width="40%" align="right">
										<font style="font-family: Arial;font-size: 11px;font-weight:bold;color:#858585;">PIN:</font>
										&nbsp;
									</td>
									<td width="60%" align="left">
										<input type="text" size="6" maxlength="6" id="demopin" name="pin" class="formulario">
										&nbsp;
										<img border="0" align="middle" alt="CaixaProtect" src="img/2100candau256.png"></td>
								</tr>
								<tr>
									<td width="100%" align="center" colspan="2">
										<div id="showerror" class="error_text">
										</div>
										<br></td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>

				<tr>
					<td width="362" height="7" style="BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px" colspan="2"></td>
				</tr>
				<tr>
					<td width="362" align="right" height="30" style="valign:center;BORDER-TOP: 0px ; BORDER-LEFT-WIDTH: 0px; BORDER-BOTTOM: 0px ; BORDER-RIGHT-WIDTH: 0px" colspan="2">
						<div style="align: right;">
							<a class="boton aceptar" href="#" onclick="checkform();">
								<span>Confirmar compra</span>
							</a>
							<a class="boton cancelar" href="#" onclick="window.location.href='<?php print $_GET["URLKO"];?>'">
								<span>Cancelar</span>
							</a>
						</div>
					</td>
				</tr>
				<input type="hidden" name="TransactionType" value="<?php print $_GET["OPERATION"];?>">
				<input type="hidden" name="Order" value="<?php print $_GET["MERCHANT_ORDER"];?>">
				<input type="hidden" name="Amount" value="<?php print $_GET["MERCHANT_AMOUNT"];?>">
				<input type="hidden" name="Response" value="OK">
				<input type="hidden" name="ExtendedSignature" value="<?php print $_GET["MERCHANT_MERCHANTSIGNATURE"];?>">
				<input type="hidden" name="IdUser" value="<?php print $_GET["IDUSER"];?>">
				<input type="hidden" name="TokenUser" value="<?php print $_GET["TOKEN_USER"];?>">
				<input type="hidden" name="Currency" value="<?php print $_GET["CURRENCY"];?>">
				<input type="hidden" name="merchan_pan" value="<?php print $_GET["MERCHAN_PAN"];?>">
				<input type="hidden" name="AuthCode" value="Test_mode">
			</tbody>
		</table>
		</form>
	</div>
</body>
