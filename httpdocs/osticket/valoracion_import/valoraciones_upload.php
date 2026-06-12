<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'localhost';      
$db_user = 'user_bd_atu';    
$db_pass = 'q5DzXzsN!wf0*qj1';   
$db_name = 'bd_ostickets_atu'; 

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
$db->set_charset("utf8mb4");

if (isset($_POST['importar_ahora']) && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv']['tmp_name'];
    $handle = fopen($file, "r");
    
    fgetcsv($handle, 10000, ";"); // saltar cabecera

    $form_id = 16;  
    $topic_id = 13; 
    $dept_id = 1;

    $campos_db = [];
    $res = $db->query("SELECT id, name FROM ost_form_field WHERE form_id = $form_id ORDER BY sort ASC");
    while($row = $res->fetch_assoc()) { $campos_db[] = $row; }

    $count = 0;
    while (($data = fgetcsv($handle, 10000, ";")) !== FALSE) {
        if(empty($data[0])) continue;

        // El CSV mezcla formato español (DD/MM/YYYY) y americano (M/D/YYYY)
        // Detectamos cuál usar según los valores del primer y segundo número
        $fecha_raw = trim($data[0]);
        $fecha_obj = null;

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}:\d{2}:\d{2})$#', $fecha_raw, $fm)) {
            $p1 = (int)$fm[1]; // primer número (¿día o mes?)
            $p2 = (int)$fm[2]; // segundo número (¿mes o día?)

            if ($p1 > 12) {
                // Primer número > 12 → solo puede ser DD/MM/YYYY (español)
                $fecha_obj = DateTime::createFromFormat('d/m/Y G:i:s', $fecha_raw)
                          ?: DateTime::createFromFormat('d/m/Y H:i:s', $fecha_raw);
            } elseif ($p2 > 12) {
                // Segundo número > 12 → solo puede ser MM/DD/YYYY (americano)
                $fecha_obj = DateTime::createFromFormat('n/j/Y G:i:s', $fecha_raw)
                          ?: DateTime::createFromFormat('n/j/Y H:i:s', $fecha_raw);
            } else {
                // Ambiguo (ambos ≤ 12): si p2 >= p1 es más probable español (día pequeño/mes mayor)
                // pero usamos español como primario por ser el origen mayoritario
                $fecha_obj = DateTime::createFromFormat('d/m/Y G:i:s', $fecha_raw)
                          ?: DateTime::createFromFormat('d/m/Y H:i:s', $fecha_raw);
                // Validar que el resultado es coherente (fecha no futura en más de 1 año)
                if ($fecha_obj && $fecha_obj->format('Y') > (int)date('Y') + 1) {
                    // Probamos americano como alternativa
                    $alt = DateTime::createFromFormat('n/j/Y G:i:s', $fecha_raw)
                        ?: DateTime::createFromFormat('n/j/Y H:i:s', $fecha_raw);
                    if ($alt && $alt->format('Y') <= (int)date('Y') + 1) {
                        $fecha_obj = $alt;
                    }
                }
            }
        }

        // Si sigue sin parsear, saltar fila (texto suelto de celda multilínea)
        if (!$fecha_obj) continue;

        $fecha = $db->real_escape_string($fecha_obj->format('Y-m-d H:i:s'));
        $t_num = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); 
        
        $db->query("INSERT INTO ost_ticket (number, topic_id, dept_id, status_id, created, updated, source) VALUES ('$t_num', $topic_id, $dept_id, 3, '$fecha', NOW(), 'Other')");
        
        $ticket_id = $db->insert_id;
        $db->query("INSERT INTO ost_form_entry (form_id, object_id, object_type, created, updated) VALUES ($form_id, $ticket_id, 'T', NOW(), NOW())");
        $entry_id = $db->insert_id;

        foreach ($campos_db as $index => $campo) {
            $f_id = $campo['id'];
            $f_name = $campo['name'];
            
            $valor_bruto = '';
            if ($f_name == 'expediente') { $valor_bruto = $data[1] ?? ''; }
            elseif ($f_name == 'empresa') { $valor_bruto = $data[6] ?? ''; }
            elseif ($f_name == 'satisfaccion') { $valor_bruto = $data[35] ?? ''; }
            else {
                $col_excel = $index + 1; 
                $valor_bruto = $data[$col_excel] ?? '';
            }

            if ($valor_bruto !== '') {
                if (!mb_check_encoding($valor_bruto, 'UTF-8')) {
                    $valor_limpio = mb_convert_encoding($valor_bruto, 'UTF-8', 'ISO-8859-1');
                } else {
                    $valor_limpio = $valor_bruto;
                }
                $val_esc = $db->real_escape_string((string)$valor_limpio);
                $db->query("INSERT INTO ost_form_entry_values (entry_id, field_id, value) VALUES ($entry_id, $f_id, '$val_esc')");
            }
        }
        $count++;
    }
    fclose($handle);
    header("Location: ../valoraciones.php?importados=$count");
    exit;
}
?>