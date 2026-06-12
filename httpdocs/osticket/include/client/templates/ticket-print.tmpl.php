<html>
<head>
<style type="text/css">

@page {
    header: html_def;
    footer: html_def;
    margin: 15mm;
    margin-top: 35mm;
    margin-bottom: 22mm;
}

body {
    font-family: sans-serif;
    font-size: 10pt;
    color: #333;
}

table {
    border-collapse: collapse;
}

/* ============================= */
/* TABLA CLARA Y DIFERENCIADA */
/* ============================= */

.meta-data,
.custom-data {
    width: 100%;
    margin-top: 12px;
    border: 1px solid #cfcfcf;
}

.meta-data th,
.custom-data th {
    background-color: #0b3d91;
    color: #ffffff;
    text-align: left;
    padding: 8px 10px;
    font-weight: 600;
    width: 25%;
    border: 1px solid #cfcfcf;
}

.meta-data td,
.custom-data td {
    padding: 8px 10px;
    border: 1px solid #cfcfcf;
    word-break: break-word;
}

.meta-data tr:nth-child(even) td,
.custom-data tr:nth-child(even) td {
    background-color: #f7f9fc;
}

.headline {
    background-color: #0b3d91;
    color: #ffffff;
    padding: 8px 10px;
    font-weight: bold;
    border: 1px solid #cfcfcf;
}

/* ============================= */
/* HISTORIAL */
/* ============================= */

.thread-entry {
    margin-top: 14px;
    border: 1px solid #cfcfcf;
    padding: 12px;
    page-break-inside: avoid;
}

.thread-header {
    font-size: 9pt;
    color: #666;
    margin-bottom: 6px;
}

.thread-body {
    font-size: 10pt;
}

.hr {
    border-top: 1px solid #ccc;
    margin: 5px 0;
}

</style>
</head>
<body>

<!-- HEADER -->
<htmlpageheader name="def" style="display:none">
<table width="100%" style="border-bottom:2px solid #0b3d91; padding-bottom:8px;">
<tr>
    <td>
        <strong><?php echo (string) $ost->company; ?></strong><br>
        <?php echo Format::daydatetime(Misc::gmtime()); ?>
    </td>
    <td style="text-align:right;">
        <img src="<?php echo ROOT_PATH; ?>custom/img/logo-atu.png" style="height:55px;">
    </td>
</tr>
</table>
</htmlpageheader>

<!-- FOOTER -->
<htmlpagefooter name="def" style="display:none">
<div class="hr"></div>
<table width="100%">
<tr>
    <td>Ticket #<?php echo $ticket->getNumber(); ?></td>
    <td style="text-align:right;">Página {PAGENO}</td>
</tr>
</table>
</htmlpagefooter>

<h1 style="color:#0b3d91; margin-top:5px;">
    INFORME DE INCIDENCIA
</h1>

<h3>Ticket #<?php echo $ticket->getNumber(); ?></h3>

<!-- DATOS PRINCIPALES -->
<table class="meta-data">
<tbody>

<tr>
    <th>Estado</th>
    <td><?php echo $ticket->getStatus(); ?></td>
    <th>Nombre</th>
    <td><?php echo $ticket->getOwner()->getName(); ?></td>
</tr>

<tr>
    <th>Prioridad</th>
    <td><?php echo $ticket->getPriority(); ?></td>
    <th>Email</th>
    <td><?php echo $ticket->getEmail(); ?></td>
</tr>

<tr>
    <th>Departamento</th>
    <td><?php echo $ticket->getDept(); ?></td>
    <th>Fecha de creación</th>
    <td><?php echo Format::datetime($ticket->getCreateDate()); ?></td>
</tr>

</tbody>
</table>

<!-- CAMPOS DINÁMICOS -->
<?php
foreach (DynamicFormEntry::forTicket($ticket->getId()) as $form) {

    $answers = $form->getAnswers()->exclude(Q::any(array(
        'field__flags__hasbit' => DynamicFormField::FLAG_EXT_STORED,
        Q::not(array('field__flags__hasbit' => DynamicFormField::FLAG_CLIENT_VIEW)),
        'field__name__in' => array('subject', 'priority'),
    )));

    if (count($answers) == 0)
        continue;
?>
<table class="custom-data">
<tr>
    <td colspan="2" class="headline">
        <?php echo $form->getTitle(); ?>
    </td>
</tr>

<?php foreach($answers as $a) {

    if (!($v = $a->display())) continue;

    $field = $a->getField();
    $type = $field->get('type');

    echo '<tr>';
    echo '<th>'.$field->get('label').'</th>';
    echo '<td>';

    /* ===== CAMPO FILE (FORMULARIO) ===== */
    if ($type == 'file' && $a->getFiles()) {

        foreach ($a->getFiles() as $file) {

            $filename = Format::htmlchars($file->name);
            $filesize = Format::file_size($file->size);
            $downloadUrl = $file->getDownloadUrl();

            $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);

            if ($isImage) {

                $data = base64_encode($file->getData());
                $mime = $file->getMimeType();

                echo '<div style="margin-bottom:18px;">';
                echo '<img src="data:'.$mime.';base64,'.$data.'" style="max-width:420px; max-height:300px; display:block; margin-bottom:8px;">';
                echo '<a href="'.$downloadUrl.'" style="color:#0b3d91;">Descargar '.$filename.'</a>';
                echo ' <span style="color:#777;">('.$filesize.')</span>';
                echo '</div>';

            } else {

                echo '<div style="margin-bottom:8px;">';
                echo '<a href="'.$downloadUrl.'" style="color:#0b3d91;">Descargar '.$filename.'</a>';
                echo ' <span style="color:#777;">('.$filesize.')</span>';
                echo '</div>';

            }

        }

    } else {

        echo $v;

    }

    echo '</td>';
    echo '</tr>';

} ?>

</table>
<?php } ?>

<?php
$types = array('M', 'R');
if ($thread = $ticket->getThreadEntries($types)) {

    $thread = ThreadEntry::sortEntries($thread, $ticket);

    foreach ($thread as $entry) { ?>
<div class="thread-entry">
    <div class="thread-header">
        <?php echo Format::datetime($entry->created); ?> —
        <?php echo Format::htmlchars($entry->getName()); ?>
    </div>

    <div class="thread-body">
        <?php echo $entry->getBody()->display('pdf'); ?>
    </div>

<?php
if ($entry->has_attachments && ($files = $entry->attachments)) { ?>
    <div style="margin-top:12px;">
        <strong>Adjuntos:</strong><br><br>

<?php foreach ($files as $A) {

    $file = $A->file;
    $filename = Format::htmlchars($file->name);
    $filesize = Format::file_size($file->size);
    $downloadUrl = $file->getDownloadUrl();

    $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);

    if ($isImage) {

        $data = base64_encode($file->getData());
        $mime = $file->getMimeType();

        echo '<div style="margin-bottom:18px;">';
        echo '<img src="data:'.$mime.';base64,'.$data.'" style="max-width:420px; max-height:300px; display:block; margin-bottom:8px;">';
        echo '<a href="'.$downloadUrl.'" style="color:#0b3d91;">Descargar '.$filename.'</a>';
        echo ' <span style="color:#777;">('.$filesize.')</span>';
        echo '</div>';

    } else {

        echo '<div style="margin-bottom:8px;">';
        echo '<a href="'.$downloadUrl.'" style="color:#0b3d91;">Descargar '.$filename.'</a>';
        echo ' <span style="color:#777;">('.$filesize.')</span>';
        echo '</div>';

    }

} ?>
    </div>
<?php } ?>

</div>
<?php }
} ?>

</body>
</html>