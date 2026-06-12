<?php
/**
 * =============================================================================
 * logo.php — Servicio del logotipo personalizado del portal de cliente
 * =============================================================================
 *
 * Propósito:
 *   Endpoint que sirve el logotipo del portal de cliente configurado en
 *   Admin Panel → Settings → Pages → Client Logo.
 *   Permite personalizar el logo sin modificar archivos de la instalación.
 *
 * Funcionamiento:
 *   1. Carga client.inc.php con NOOP_SESSION=true (evita iniciar sesión PHP,
 *      lo que mejora el rendimiento para recursos estáticos).
 *   2. Intenta obtener el logo personalizado desde la configuración de osTicket
 *      ($ost->getConfig()->getClientLogo()).
 *   3. Si existe un logo personalizado: lo sirve directamente con display().
 *   4. Si no existe: redirige al logo por defecto (atuu.png en ASSETS_PATH).
 *   5. En ambos casos establece cabeceras de caché de 24 horas (86400 s).
 *
 * Cabeceras HTTP generadas:
 *   Cache-Control: private, max-age=86400
 *   Pragma: private
 *   Location: [URL del logo por defecto, si aplica]
 *
 * Dependencias:
 *   - client.inc.php (bootstrap del portal cliente)
 *   - Constante ASSETS_PATH (definida por osTicket)
 *
 * @package    osTicket\Client
 * @copyright  2006-2013 osTicket
 * @license    GNU General Public License
 * =============================================================================
 */

/*********************************************************************
    logo.php

    Simple logo to facilitate serving a customized client-side logo from
    osTicet. The logo is configurable in Admin Panel -> Settings -> Pages

    Peter Rotich <peter@osticket.com>
    Jared Hancock <jared@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
// Use Noop Session Handler
define('NOOP_SESSION', true);
require('client.inc.php');
$ttl = 86400; // max-age
if (($logo = $ost->getConfig()->getClientLogo())) {
    $logo->display(false, $ttl);
}

header("Cache-Control: private, max-age=$ttl");
header('Pragma: private');
header('Location: '.ASSETS_PATH.'images/atuu.png');
?>