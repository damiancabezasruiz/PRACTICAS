<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');

$info = array();
if($thisclient && $thisclient->isValid()) {
    $info = array(
        'name'  => $thisclient->getName(),
        'email' => $thisclient->getEmail(),
        'phone' => $thisclient->getPhoneNumber()
    );
}

$info = ($_POST && $errors) ? Format::htmlchars($_POST) : $info;
$info['topicId'] = $info['topicId'] ?? null;

$form = null;

if (!$info['topicId']) {
    if (array_key_exists('topicId', $_GET)
        && preg_match('/^\d+$/', $_GET['topicId'])
        && Topic::lookup($_GET['topicId'])) {
        $info['topicId'] = intval($_GET['topicId']);
    } else {
        $info['topicId'] = $cfg->getDefaultTopicId();
    }
}

$forms = array();
if ($info['topicId'] && ($topic = Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F->getForm();
    }
}
?>

<h1>Abrir Incidencia o Valoración</h1>
<p><?php echo __('Please fill in the form below to open a new ticket.');?></p>



<form id="ticketForm" method="post" action="open.php" enctype="multipart/form-data">
    <?php csrf_token(); ?>
    <input type="hidden" name="a" value="open">

<table width="800" cellpadding="1" cellspacing="0" border="0">
    <tbody>
        <?php
        if (!$thisclient) {
			?>

<?php
            $uform = UserForm::getUserForm()->getForm($_POST);
            if ($_POST) $uform->isValid();
            $uform->render(array('staff' => false, 'mode' => 'create'));
        } else { ?>
            <tr><td colspan="2"><hr /></td></tr>
<tr>
    <td colspan="2" style="padding:0;">
        <div class="email-client-wrapper">

            <div class="email-block">
                <strong><?php echo __('Email'); ?>:</strong><br>
                <?php echo $thisclient->getEmail(); ?>
            </div>

            <div class="client-block">
                <strong><?php echo __('Client'); ?>:</strong><br>
                <?php echo Format::htmlchars($thisclient->getName()); ?>
            </div>

        </div>
    </td>
</tr>
        <?php } ?>
    </tbody>

        <tbody>
            <tr>
                <td colspan="2">
                    <hr />
                    
                </td>
            </tr>
		

            <tr>
                <td colspan="2" style="grid-column: 1 / 2;">
                    <!-- Label correcto para el select -->
                    <label for="topicId" class="hidden"><?php echo __('Help Topic'); ?></label>

                    <select id="topicId" name="topicId" onchange="javascript:
                        var data = $(':input[name]', '#dynamic-form').serialize();
                        $.ajax(
                            'ajax.php/form/help-topic/' + this.value,
                            {
                                data: data,
                                dataType: 'json',
                                success: function(json) {
                                    $('#dynamic-form').empty().append(json.html);
                                    $(document.head).append(json.media);

                                    // Intentar corregir labels tras cargar el formulario dinámico
                                    if (window.__ost_fixLabels)
                                        window.__ost_fixLabels(document.getElementById('dynamic-form'));
                                }
                            }
                        );">'
                        <option value="" selected="selected">&mdash; <?php echo __('Select a Help Topic');?> &mdash;</option>
                        <?php
                        if ($topics = Topic::getPublicHelpTopics()) {
                            foreach ($topics as $id => $name) {
                                echo sprintf(
                                    '<option value="%d" %s>%s</option>',
                                    $id,
                                    ($info['topicId'] == $id) ? 'selected="selected"' : '',
                                    $name
                                );
                            }
                        } ?>
                    </select>

                    <font class="error">*&nbsp;<?php echo $errors['topicId']; ?></font>
                </td>
            </tr>
        </tbody>

        <tbody id="dynamic-form">
            <?php
            $options = array('mode' => 'create');
            foreach ($forms as $form) {
                include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
            } ?>
        </tbody>

        <tbody>
            <?php
            if ($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) {
                if ($_POST && $errors && !$errors['captcha'])
                    $errors['captcha'] = __('Please re-enter the text again');
            ?>
                <tr class="captchaRow">
                    <td class="required">
                        <label for="captcha"><?php echo __('CAPTCHA Text');?>:</label>
                    </td>
                    <td>
                        <span class="captcha">
                            <img src="captcha.php" border="0" align="left">
                        </span>
                        &nbsp;&nbsp;
                        <input id="captcha" type="text" name="captcha" size="6" autocomplete="off">
                        <em><?php echo __('Enter the text shown on the image.');?></em>
                        <font class="error">*&nbsp;<?php echo $errors['captcha']; ?></font>
                    </td>
                </tr>
            <?php } ?>

            <tr><td colspan="2">&nbsp;</td></tr>
        </tbody>
    </table>

    <hr/>

    <p class="buttons" style="text-align:center;">
        <input type="submit" value="<?php echo __('Create Ticket');?>">
        <input type="reset" name="reset" value="<?php echo __('Reset');?>">
        <input type="button" name="cancel" value="<?php echo __('Cancel'); ?>" onclick="javascript:
            $('.richtext').each(function() {
                var redactor = $(this).data('redactor');
                if (redactor && redactor.opts.draftDelete)
                    redactor.plugin.draft.deleteDraft();
            });
            window.location.href='index.php';">
    </p>
</form>

<script>
(function () {
    function fixLabels(root) {
        root = root || document;

        var labels = root.querySelectorAll('label[for]');
        for (var i = 0; i < labels.length; i++) {
            var label = labels[i];
            var f = label.getAttribute('for');
            if (!f) continue;

            // 1) Si existe un elemento con ese id, OK
            if (document.getElementById(f)) continue;

            // 2) A veces el id real viene con '_' delante
            var el2 = document.getElementById('_' + f);
            if (el2) {
                label.setAttribute('for', el2.id);
                continue;
            }

            // 3) Si el "for" está apuntando al name, intenta enlazar por name
            try {
                var elByName = root.querySelector('[name="' + f.replace(/"/g, '\\"') + '"]');
                if (elByName) {
                    if (elByName.id) {
                        label.setAttribute('for', elByName.id);
                    } else {
                        elByName.id = f;
                    }
                }
            } catch (e) {}
        }
    }

    window.__ost_fixLabels = fixLabels;

    document.addEventListener('DOMContentLoaded', function () {
        fixLabels(document);
    });
})();
</script>
