<?php
/**
 * =============================================================================
 * secure.inc.php — Guardia de autenticación para páginas protegidas del cliente
 * =============================================================================
 *
 * Propósito:
 *   Archivo de inclusión que se añade al inicio de TODAS las páginas del
 *   portal de cliente que requieren sesión iniciada (tickets.php, etc.).
 *   Si el usuario no está autenticado, lo redirige al formulario de login.
 *
 * Funcionamiento:
 *   1. Impide la ejecución directa del archivo (protección anti-acceso).
 *   2. Carga client.inc.php para inicializar $thisclient y el entorno.
 *   3. Define clientLoginPage() si no existe (permite que código AJAX
 *      sobreescriba esta función para gestionar logins sin redirección).
 *   4. Verifica que $thisclient sea un objeto válido con ID y sesión activa.
 *      Si no, redirige a la página de login guardando la URL de destino.
 *   5. Refresca la sesión del cliente para prorrogar su expiración.
 *
 * Uso típico:
 *   <?php require('secure.inc.php'); ?>
 *   Al inicio de tickets.php, history.php, profile.php, etc.
 *
 * Variables disponibles tras la inclusión:
 *   $thisclient  — Objeto del cliente autenticado (clase TicketUser/EndUser)
 *   $ost         — Instancia global de osTicket
 *   $cfg         — Configuración global del sistema
 *   $nav         — Objeto de navegación del portal cliente
 *
 * @package    osTicket\Client
 * @copyright  2006-2013 osTicket
 * @license    GNU General Public License
 * =============================================================================
 */

/*********************************************************************
    secure.inc.php

    File included on every client's "secure" pages

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if(!strcasecmp(basename($_SERVER['SCRIPT_NAME']),basename(__FILE__))) die('Kwaheri!');
if(!file_exists('client.inc.php')) die('Fatal Error.');
require_once('client.inc.php');

//Client Login page: Ajax interface can pre-declare the function to trap logins.
if(!function_exists('clientLoginPage')) {
    function clientLoginPage($msg ='') {
        global $ost, $cfg, $nav;
        $_SESSION['_client']['auth']['dest'] =
            '/' . ltrim($_SERVER['REQUEST_URI'], '/');
        require('./login.php');
        exit;
    }
}

//User must be logged in!
if(!$thisclient || !$thisclient->getId() || !$thisclient->isValid()){
    clientLoginPage();
    exit;
}
$thisclient->refreshSession();
?>