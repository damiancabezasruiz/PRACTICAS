<?php
/**
 * =============================================================================
 * view.php — Punto de entrada para autologin por token y vista de estado
 * =============================================================================
 *
 * Propósito:
 *   Gestiona el acceso al portal de cliente mediante tokens de autenticación
 *   enviados por correo (enlaces "ver tu ticket"). Intenta autenticar al usuario
 *   automáticamente y redirigirle al ticket correspondiente.
 *
 * Flujo de control:
 *   Caso 1 — El cliente ya tiene sesión activa Y el token coincide con su ID:
 *     Rota la clave de autenticación (para que el mismo enlace funcione con
 *     tickets distintos) y redirige directamente a tickets.php.
 *
 *   Caso 2 — Hay token (?auth=) o ID de ticket (?t=) en la URL:
 *     Intenta autologin mediante UserAuthenticationBackend::processSignOn().
 *     El usuario puede ser el propietario del ticket o un colaborador.
 *     Si tiene éxito, redirige a tickets.php con el ID del ticket.
 *
 *   Caso 3 — Sin token / autologin fallido:
 *     Muestra la página accesslink.inc.php (formulario de acceso con número
 *     de ticket y email) para que el usuario solicite un nuevo enlace.
 *
 * Parámetros GET:
 *   auth  (string) — Token de autenticación incluido en el email de osTicket
 *   t     (int)    — ID numérico del ticket (formato alternativo)
 *   id    (int)    — ID externo del ticket (para redirección directa)
 *
 * @package    osTicket\Client
 * @copyright  2006-2010 osTicket
 * @license    GNU General Public License
 * =============================================================================
 */

/*********************************************************************
    view.php

    Ticket View.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2010 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
    $Id: $
**********************************************************************/
require_once('client.inc.php');

$errors = array();
// Check if the client is already signed in. Don't corrupt their session!
if ($_GET['auth']
        && $thisclient
        && ($u = TicketUser::lookupByToken($_GET['auth']))
        && ($u->getUserId() == $thisclient->getId())
) {
    // Switch auth keys ? (Otherwise the user can never use links for two
    // different tickets)
    if (($bk = $thisclient->getAuthBackend()) instanceof AuthTokenAuthentication) {
        $bk->setAuthKey($u, $bk);
    }
    Http::redirect('tickets.php?id='.$u->getTicketId());
}
// Try autologin the user
// Authenticated user can be of type ticket owner or collaborator
elseif (isset($_GET['auth']) || isset($_GET['t'])) {
    // TODO: Consider receiving an AccessDenied object
    $user =  UserAuthenticationBackend::processSignOn($errors, false);
}

if (@$user && is_object($user) && $user->getTicketId())
    Http::redirect('tickets.php?id='.$user->getTicketId());
elseif ($thisclient && isset($_GET['id']) && is_numeric($_GET['t']))
    Http::redirect('tickets.php?id='.$_GET['id']);

$nav = new UserNav();
$nav->setActiveNav('status');

$inc = 'accesslink.inc.php';
require CLIENTINC_DIR.'header.inc.php';
require CLIENTINC_DIR.$inc;
require CLIENTINC_DIR.'footer.inc.php';
?>