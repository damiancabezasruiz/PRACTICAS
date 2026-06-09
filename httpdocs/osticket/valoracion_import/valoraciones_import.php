<?php
/******************************************************************
 * valoracion_import/valoraciones_upload.php
 ******************************************************************/
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar configuración de osTicket para usar las constantes de BD
require_once '../include/ost-config.php';

$db = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
$db->set_charset("utf8mb4");

if ($db->connect_error) {
    die("Error de conexión: " . $db->connect_error);
}

if (isset($_POST['importar_ahora']) && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv']['tmp_name'];
    $handle = fopen($file, "r");

    // Omitir la primera línea (cabecera)
    fgetcsv($handle, 1000, ";");

    $importados = 0;
    
    // IDs críticos según el archivo valoraciones.php
    $form_id = 16;  // El ID del formulario que filtra tu archivo
    $topic_id = 13; // El ID del tema de ayuda para valoraciones

    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        // Estructura: 0:Fecha, 1:Expediente, 2:Empresa, 3:Satisfaccion, 4:Ticket
        $fecha     = $db->real_escape_string($data[0]);
        $exp       = $db->real_escape_string($data[1]);
        $emp       = $db->real_escape_string($data[2]);
        $sat       = $db->real_escape_string($data[3]);
        $t_num     = $db->real_escape_string($data[4]);

        // 1. Crear el Ticket
        $db->query("INSERT INTO ost_ticket (number, topic_id, status_id, created, updated) 
                    VALUES ('$t_num', $topic_id, 3, '$fecha', NOW())");
        $ticket_id = $db->insert_id;

        // 2. Crear la entrada del formulario (necesaria para que aparezca en la lista)
        $db->query("INSERT INTO ost_form_entry (form_id, object_id, object_type, created, updated) 
                    VALUES ($form_id, $ticket_id, 'T', NOW(), NOW())");
        $entry_id = $db->insert_id;

        // 3. Insertar los valores específicos para que el botón "VER VALORACIÓN" los encuentre[cite: 1]
        // Debemos buscar el field_id de cada nombre interno (expediente, empresa, satisfaccion)
        $campos = [
            'expediente' => $exp,
            'empresa' => $emp,
            'satisfaccion' => $sat
        ];

        foreach ($campos as $nombre_interno => $valor) {
            $res = $db->query("SELECT id FROM ost_form_field WHERE name='$nombre_interno' AND form_id=$form_id");
            if ($f = $res->fetch_assoc()) {
                $field_id = $f['id'];
                $db->query("INSERT INTO ost_form_entry_values (entry_id, field_id, value) 
                            VALUES ($entry_id, $field_id, '$valor')");
            }
        }
        $importados++;
    }

    fclose($handle);
    header("Location: ../valoraciones.php?importados=$importados");
    exit;
}