PAYCOMET for WHMCS
===================

Módulo de Pago PAYCOMET con Tokenización para WHMCS. 

Soporta pagos recurrentes (suscripciones) sin tener que introducir nuevamente los datos de tarjeta.

Soporta Devoluciones Totales y Parciales desde WHMCS.

NOTA: El comercio debe tener un Certificado SSL para el envío de datos de tarjeta a PAYCOMET.


## Descripcion General

Esta pasarela permite el sistema de pagos de [WHMCS](http://www.whmcs.com) con tokenización usando [PAYCOMET](https://www.paycomet.com) para pagos recurrentes. [PAYCOMET](https://www.paycomet.com) sólo almacena un token de usuario en la base de datos de WHMCS. No se almacenan datos de tarjeta.

PAYCOMET es PCI-DSS Level 1, eliminando los costes concernientes al cumplimiento del PCI.

La documentación la puede encontrar en el siguiente enlace [MÓDULO WHMCS PAYCOMET](https://docs.paycomet.com/es/modulos-de-pago/whmcs)

## Instructiones de Uso

1. Descarga la ultima release de (https://github.com/PAYCOMET/whmcs/releases/latest) y descomprimela en el directorio raiz de WHMCS

Al final, la estructura de directorios debe tener un aspecto como el diagrama de abajo, con el fichero paycomet.php en el directorio `/modules/gateways/`, el fichero paycomet.php en `/modules/gateways/callback` y el fichero ApiRest.php en el directorio `/modules/gateways/paycomet/lib`

```
whmcs
  |-- modules
  	  |-- gateways
          |-- paycomet.php
		  |-- paycomet
		  	 |-- lib
			   |-- ApiRest.php
  	  	  |-- callbacks
  	  	     |-- paycomet.php
          
 ```

Después necesita activar esta Pasarela de Pago en WHMCS a través de Ajustes > Formas de Pago > Pasarelas de Pago. Este Módulo aparece como *PAYCOMET*.

Necesita disponer de una cuenta de PAYCOMET (https://www.paycomet.com) para configurar la API Key, el Código de Cliente, Número de Terminal y Contraseña en las opciones del Módulo. Puede solicitar una cuenta de pruebas para testear nuestra solución.

## Configuracion del producto en PAYCOMET

Vaya al área de cliente en https://www.paycomet.com/ → Mis Productos → Configurar producto y seleccione su producto Whmcs.

URL OK: {RUTA_WHMCS}/modules/gateways/callback/paycomet.php
URL KO: {RUTA_WHMCS}/modules/gateways/callback/paycomet.php

En _tipo de notificación_ seleccione _Notificación por URL_ or _Notificación por URL y por email_, y por ultimo en 

_URL Notificación_ : {RUTA_WHMCS}/modules/gateways/callback/paycomet.php



