<?php
/**
 * =============================================================================
 * open.php — Formulario de apertura de nuevo ticket desde el portal de cliente
 * =============================================================================
 *
 * Propósito:
 *   Gestiona la creación de nuevos tickets de soporte desde el portal web.
 *   Incluye validación CAPTCHA para usuarios anónimos, procesamiento del
 *   formulario dinámico de osTicket (form_id configurable por topic) y
 *   filtrado de opciones ocultas mediante una API interna.
 *
 * Funcionamiento:
 *   POST — Creación de ticket:
 *     1. Fuerza deptId y emailId a 0 (solo se acepta topicId del usuario).
 *     2. Si el usuario no está logueado y el CAPTCHA está activo, lo valida.
 *     3. Procesa el formulario dinámico (TicketForm) y los adjuntos.
 *     4. Elimina el borrador de sesión antes de crear el ticket.
 *     5. Llama a Ticket::create() con los datos procesados.
 *     6. Si tiene éxito: muestra la página de agradecimiento del topic o la
 *        página de thank-you global configurada en el panel admin.
 *
 *   GET — Renderizado del formulario:
 *     Verifica si se requiere login para abrir tickets (isClientLoginRequired).
 *     Si el registro está desactivado, redirige a view.php.
 *
 * Personalización Grupo ATU:
 *   Este archivo incluye dos extensiones sobre el osTicket original:
 *   1. CSS para mostrar errores en rojo y labels en negro.
 *   2. Script JS que llama a options.php?api_ocultos_v5=1 para ocultar
 *      dinámicamente las opciones marcadas como ocultas en los desplegables
 *      del formulario (para incidencias y valoraciones). Usa MutationObserver
 *      para gestionar campos renderizados dinámicamente.
 *
 * Parámetros POST relevantes:
 *   topicId   — ID del help topic seleccionado
 *   message   — Cuerpo del mensaje inicial del ticket
 *   captcha   — Respuesta del CAPTCHA (si está activado)
 *   draft_id  — ID del borrador guardado en sesión (si existe)
 *
 * Constante definida:
 *   SOURCE = 'Web'  — Indica que el ticket se originó desde la interfaz web
 *
 * Dependencias:
 *   - client.inc.php, TicketForm, Ticket::create(), Draft
 *   - CLIENTINC_DIR/open.inc.php, header.inc.php, footer.inc.php
 *   - API: /osticket/options.php (filtro de opciones ocultas)
 *
 * @package    osTicket\Client\GrupoATU
 * @copyright  2006-2013 osTicket + modificaciones Grupo ATU
 * @license    GNU General Public License
 * =============================================================================
 */

/*********************************************************************
    open.php

    New tickets handle.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('client.inc.php');
define('SOURCE','Web'); //Ticket source.
$ticket = null;
$errors=array();
if ($_POST) {
    $vars = $_POST;
    $vars['deptId']=$vars['emailId']=0; //Just Making sure we don't accept crap...only topicId is expected.
    if ($thisclient) {
        $vars['uid']=$thisclient->getId();
    } elseif($cfg->isCaptchaEnabled()) {
        if(!$_POST['captcha'])
            $errors['captcha']=__('Enter text shown on the image');
        elseif(strcmp($_SESSION['captcha'], md5(strtoupper($_POST['captcha']))))
            $errors['captcha']=sprintf('%s - %s', __('Invalid'), __('Please try again!'));
    }

    $tform = TicketForm::objects()->one()->getForm($vars);
    $messageField = $tform->getField('message');
    $attachments = $messageField->getWidget()->getAttachments();
    if (!$errors) {
        $vars['message'] = $messageField->getClean();
        if ($messageField->isAttachmentsEnabled())
            $vars['files'] = $attachments->getFiles();
    }

    // Drop the draft.. If there are validation errors, the content
    // submitted will be displayed back to the user
    Draft::deleteForNamespace('ticket.client.'.substr(session_id(), -12));
    //Ticket::create...checks for errors..
    if(($ticket=Ticket::create($vars, $errors, SOURCE))){
        $msg=__('Support ticket request created');
        // Drop session-backed form data
        unset($_SESSION[':form-data']);
        //Logged in...simply view the newly created ticket.
        if ($thisclient && $thisclient->isValid()) {
            // Regenerate session id
            $thisclient->regenerateSession();
            
        } else
            $ost->getCSRF()->rotate();
    }else{
        $errors['err'] = $errors['err'] ?: sprintf('%s %s',
            __('Unable to create a ticket.'),
            __('Correct any errors below and try again.'));
        // Log detallado de errores para depuración
        error_log('[open.php] Ticket creation failed. topicId=' . ($_POST['topicId'] ?? 'N/A') . ' errors=' . json_encode($errors));
    }
}

//page
$nav->setActiveNav('new');
if ($cfg->isClientLoginRequired()) {
    if ($cfg->getClientRegistrationMode() == 'disabled') {
        Http::redirect('view.php');
    }
    elseif (!$thisclient) {
        require_once 'secure.inc.php';
    }
    elseif ($thisclient->isGuest()) {
        require_once 'login.php';
        exit();
    }
}

require(CLIENTINC_DIR.'header.inc.php');

// ── COLOR ERRORES EN ROJO ────────────────────────────────────────
?>
<style>
div.error,
td div.error,
font.error,
.error {
    color: #dc2626 !important;
    font-weight: bold !important;
}

/* ── LABELS DE CAMPOS EN NEGRO ── */
label,
.form-group label,
.field-label,
.control-label,
th label,
td label,
.form-field label,
b.required,
label.required {
    color: #000000 !important;
}
</style>
<?php
// ── FIN ESTILOS ERRORES ──────────────────────────────────────────

// ── FILTRO OPCIONES OCULTAS ──────────────────────────────────────
?>
<script>
(function() {
  var base = '/osticket/options.php?api_ocultos_v5=1&secret=atu2026';

  Promise.all([
    fetch(base + '&tipo=incidencias').then(function(r) { return r.json(); }),
    fetch(base + '&tipo=valoraciones').then(function(r) { return r.json(); })
  ]).then(function(results) {
    var ocultos = {};
    results.forEach(function(data) {
      if (!data.ok || !data.items) return;
      data.items.forEach(function(item) {
        var preg = item.pregunta_nombre.trim().toLowerCase();
        if (!ocultos[preg]) ocultos[preg] = [];
        var op = item.opcion_nombre.trim().toLowerCase();
        if (ocultos[preg].indexOf(op) === -1) ocultos[preg].push(op);
      });
    });

    if (Object.keys(ocultos).length === 0) return;

    function filtrarSelects() {
      document.querySelectorAll('select').forEach(function(sel) {
        var id = sel.id || sel.name || '';
        var labelEl = document.querySelector('label[for="' + id + '"]')
                   || document.querySelector('label[for="_' + id + '"]');
        var label = labelEl ? labelEl.textContent.trim().toLowerCase() : '';

        Object.keys(ocultos).forEach(function(pregunta) {
          if (label.indexOf(pregunta) === -1) return;
          Array.from(sel.options).forEach(function(opt) {
            if (ocultos[pregunta].indexOf(opt.text.trim().toLowerCase()) !== -1) {
              opt.remove();
            }
          });
        });
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', filtrarSelects);
    } else {
      filtrarSelects();
    }
    if (window.MutationObserver) {
      new MutationObserver(filtrarSelects).observe(document.body, { childList: true, subtree: true });
    }
  }).catch(function(e) { console.warn('Filtro ocultos error:', e); });
})();
</script>
<?php
// ── FIN FILTRO ───────────────────────────────────────────────────

if ($ticket
    && (
        (($topic = $ticket->getTopic()) && ($page = $topic->getPage()))
        || ($page = $cfg->getThankYouPage())
    )
) {
    // Thank the user and promise speedy resolution!
    echo Format::viewableImages(
        $ticket->replaceVars(
            $page->getLocalBody()
        ),
        ['type' => 'P']
    );
}
else {
    require(CLIENTINC_DIR.'open.inc.php');
}
require(CLIENTINC_DIR.'footer.inc.php');
?>