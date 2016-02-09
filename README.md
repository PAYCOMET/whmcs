PAYTPV for WHMCS 5+
===================

Módulo de Pago PAYTPV con Tokenización para WHMCS. 

Soporta pagos recurrentes (suscripciones) sin tener que introducir nuevamente los datos de tarjeta.

Soporta Devoluciones Totales y Parciales desde WHMCS.

Combina Terminales Seguros y No Seguros. Si el cliente dispone de ambos, se puede configurar para que la primera compra vaya con validación 3D Secure por motivos de seguridad y los siguientes pagos por sin 3D Secure. También se puede configurar para que los pagos superiores a un importe siempre vayan por 3D Secure aumentando la seguridad.

NOTA: El comercio debe tener un Certificado SSL para el envío de datos de tarjeta a PAYTPV.


## Descripcion General

Esta pasarela permite el sistema de pagos de [WHMCS](http://www.whmcs.com) con tokenización usando [PAYTPV's](https://www.paytpv.com) para pagos recurrentes. [PAYTPV](https://www.paytpv.com) sólo almacena un token de usuario en la base de datos de WHMCS. No se almacenan datos de tarjeta.

PAYTPV es PCI-DSS Level 1, eliminando los costes concernientes al cumplimiento del PCI.

## Instructiones de Uso

1. Descarga la ultima release de (https://github.com/PayTpv/paytpv-for-whmcs/releases/latest) y descomprimela en el directorio raiz de WHMCS

Al final, la estructura de directorios debe tener un aspecto como el diagrama de abajo, con el fichero paytpv.php en el directorio `/modules/gateways/`, el fichero paytpv.php en `/modules/gateways/callback` y el directorio `/modules/gateways/templates/paytpv

```
whmcs
  |-- modules
  	  |-- gateways
          |-- paytpv.php
  	  	  |-- callbacks
  	  	     |-- paytpv.php
          |-- templates
      	     |-- paytpv
                |-- css
                    |-- 2100.css
                    |-- ...
                    |-- ...
                |-- img
                    |-- 2100candau256.png
                    |-- VerifiedByVisa.jpg
        	      |-- payment_3ds_test.php
 ```

Después necesita activar esta Pasarela de Pago en WHMCS a través de Ajustes > Formas de Pago > Pasarelas de Pago. Este Módulo aparece como *PAYTPV*.

Necesita disponer de una cuenta de PAYTPV (https://www.paytpv.com) para configurar el Código de Cliente, Número de Terminal y Contraseña en las opciones del Módulo. Puede solicitar una cuenta de pruebas para testear nuestra solución.

## Configuracion del producto en PAYTPV

Vaya al área de cliente en https://paytpv.com/ → Mis Productos → Configurar producto y seleccione su producto Whmcs.

URL OK: {RUTA_WHMCS}/modules/gateways/callback/paytpv.php
URL KO: {RUTA_WHMCS}/modules/gateways/callback/paytpv.php

En _tipo de notificación_ seleccione _Notificación por URL_ or _Notificación por URL y por email_, y por ultimo en 

_URL Notificación_ : {RUTA_WHMCS}/modules/gateways/callback/paytpv.php



