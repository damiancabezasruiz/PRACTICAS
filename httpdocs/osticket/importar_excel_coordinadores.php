<?php
/**
 * =============================================================================
 * importar_excel_coordinadores.php — Importación masiva de coordinadores
 * =============================================================================
 *
 * Endpoint AJAX que recibe un CSV / XLSX / ODS y vuelca su contenido
 * como tickets en osTicket con form_id=22 (Coordinadores).
 *
 * Uso desde coordinadores.php:
 *   fetch('importar_excel_coordinadores.php', { method:'POST', body: formData })
 *
 * Columnas esperadas en el archivo (cabeceras en español, sin tildes):
 *   FECHA, EXPEDIENTE, PLAN, SECTOR, ACCION, GRUPO, NOMBRE DEL CURSO,
 *   Nº ALUMNOS MATRICULADOS, Nº ALUMNOS QUE FINALIZAN, TUTOR,
 *   PERSONAS DE APOYO, EQUIPO DE DINAMIZACION,
 *   DOMINIO DE LOS CONTENIDOS, RESOLUCION DE DUDAS,
 *   CALIDAD DEL SEGUIMIENTO, CLARIDAD DE LOS SEGUIMIENTOS,
 *   CORRECCION Y REVISION (TUTOR), OBSERVACIONES (TUTOR),
 *   ATENCION Y ACOMPANAMIENTO, RESOLUCION DE INCIDENCIAS,
 *   RAPIDEZ Y EFICACIA, SEGUIMIENTO DEL ALUMNADO,
 *   REALIZACION DE ACCIONES, CORRECCION Y REVISION (APOYO),
 *   OBSERVACIONES (APOYO),
 *   REALIZA LLAMADAS, ALUMNADO INACTIVO, LLEVA CABO ACCIONES,
 *   REGISTRA CORRECTAMENTE, DETECTA DE FORMA TEMPRANA,
 *   OBSERVACIONES (DINAMIZACION),
 *   PARTICIPACION GENERAL, SATISFACCION PERCIBIDA,
 *   PRINCIPALES DIFICULTADES, MOTIVOS,
 *   PERFIL ALUMNADO,
 *   SE HAN DETECTADO INCIDENCIAS, DESCRIBIR, FUERON CORRECTAS,
 *   OBSERVACIONES ADICIONALES,
 *   VALORACION GLOBAL, CONCLUSION GENERAL,
 *   ASPECTOS DESTACABLES, ASPECTOS PRIORITARIOS,
 *   NECESIDADES DETECTADAS, EXISTIO COORDINACION,
 *   ESTADO
 *
 * @version 1.0
 * =============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_import_coord_php.log');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: application/json; charset=utf-8');

// Capturar errores fatales
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Error fatal: ' . $e['message'] . ' línea ' . $e['line']], JSON_UNESCAPED_UNICODE);
    }
});

if (!defined('INCLUDE_DIR')) define('INCLUDE_DIR', __DIR__ . '/include/');
require_once INCLUDE_DIR . 'ost-config.php';

$logFile = __DIR__ . '/debug_import_coord.log';
file_put_contents($logFile, "=== IMPORT COORD " . date('Y-m-d H:i:s') . " ===\n");

function debugLog(string $msg): void {
    global $logFile;
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

function responder(array $arr): void {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Normalizar cabecera (mayúsculas, sin tildes, sin espacios dobles) ─────────
function normCab(string $t): string {
    $t = mb_strtoupper(trim($t), 'UTF-8');
    $t = str_replace(
        ['Á','É','Í','Ó','Ú','Ü','Ñ','"','"','\'',"\r","\n","\t"],
        ['A','E','I','O','U','U','N','','','', ' ',' ',' '],
        $t
    );
    return trim(preg_replace('/\s+/', ' ', $t));
}

// ── Parsear fecha ─────────────────────────────────────────────────────────────
function parseFecha(?string $v): ?string {
    if ($v === null || trim($v) === '') return null;
    $v = trim($v);
    if (is_numeric($v)) {
        $n = floatval($v);
        if ($n > 1 && $n < 200000) return date('Y-m-d H:i:s', (int)(($n - 25569) * 86400));
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $v, $m)) {
        $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
        return sprintf('%04d-%02d-%02d 00:00:00', $y, $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10) . ' 00:00:00';
    $ts = strtotime($v);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
}

// ── Lectores de archivo ───────────────────────────────────────────────────────
function leerCSV(string $archivo): array {
    $content = file_get_contents($archivo);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $enc = mb_detect_encoding($content, ['UTF-8','ISO-8859-1','Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') $content = mb_convert_encoding($content, 'UTF-8', $enc);
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    $filas = [];
    // Detectar delimitador
    $primera = fgets($handle);
    rewind($handle);
    $delim = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
    while (($fila = fgetcsv($handle, 0, $delim, '"')) !== false) {
        if (array_filter($fila, fn($v) => trim((string)$v) !== '')) $filas[] = $fila;
    }
    fclose($handle);
    return $filas;
}

function leerXLSX(string $archivo): array {
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== true) return [];
    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $ssXml, $siM);
        foreach ($siM[1] as $si) {
            preg_match_all('/<t[^>]*>([^<]*)<\/t>/s', $si, $tM);
            $ss[] = html_entity_decode(implode('', $tM[1]), ENT_QUOTES, 'UTF-8');
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) return [];
    preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowM);
    $filas = [];
    foreach ($rowM[1] as $rowXml) {
        preg_match_all('/<c\s+r="([A-Z]+)\d+"([^>]*)>(?:.*?<v>([^<]*)<\/v>)?/s', $rowXml, $cells, PREG_SET_ORDER);
        $fila = [];
        foreach ($cells as $cell) {
            $col = $cell[1]; $attrs = $cell[2]; $val = $cell[3] ?? '';
            if (strpos($attrs, 't="s"') !== false && isset($ss[(int)$val])) $val = $ss[(int)$val];
            $idx = 0;
            for ($j = 0; $j < strlen($col); $j++) $idx = $idx * 26 + (ord($col[$j]) - 64);
            $fila[$idx - 1] = $val;
        }
        if (!empty($fila)) {
            ksort($fila);
            $max = max(array_keys($fila));
            for ($j = 0; $j <= $max; $j++) { if (!isset($fila[$j])) $fila[$j] = ''; }
            $filas[] = array_values($fila);
        }
    }
    return $filas;
}

function leerODS(string $archivo): array {
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== true) return [];
    $content = $zip->getFromName('content.xml');
    $zip->close();
    if (!$content) return [];
    $content = preg_replace('/<office:annotation[^>]*>.*?<\/office:annotation>/si', '', $content);
    preg_match_all('/<table:table-row[^>]*>(.*?)<\/table:table-row>/si', $content, $rowM);
    $filas = [];
    foreach ($rowM[1] as $rowHtml) {
        preg_match_all('/<table:table-cell([^>]*)(?:\s*\/>|>(.*?)<\/table:table-cell>)/si', $rowHtml, $cellM, PREG_SET_ORDER);
        $fila = []; $celdas = 0;
        foreach ($cellM as $cell) {
            if ($celdas > 80) break;
            $attrs = $cell[1] ?? ''; $inner = $cell[2] ?? '';
            $repeat = 1;
            if (preg_match('/number-columns-repeated="(\d+)"/i', $attrs, $rm)) $repeat = min(10, (int)$rm[1]);
            $val = '';
            if (preg_match('/office:date-value="([^"]+)"/i', $attrs, $dm)) $val = $dm[1];
            elseif (preg_match('/office:value="([^"]+)"/i', $attrs, $vm)) $val = $vm[1];
            elseif (preg_match_all('/<text:p[^>]*>(.*?)<\/text:p>/si', $inner, $tm)) {
                $textos = [];
                foreach ($tm[1] as $t) { $t = strip_tags($t); if (trim($t) !== '') $textos[] = trim($t); }
                $val = implode("\n", $textos);
            }
            $val = html_entity_decode(trim($val), ENT_QUOTES, 'UTF-8');
            for ($r = 0; $r < $repeat; $r++) { $fila[] = $val; $celdas++; }
        }
        if (array_filter($fila, fn($v) => trim((string)$v) !== '')) $filas[] = $fila;
    }
    return $filas;
}

// ── Validar subida ────────────────────────────────────────────────────────────
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    responder(['ok' => false, 'msg' => 'Error subiendo archivo: ' . ($_FILES['archivo_excel']['error'] ?? 'sin archivo')]);
}

$archivo = $_FILES['archivo_excel']['tmp_name'];
$nombre  = $_FILES['archivo_excel']['name'] ?? '';
$ext     = strtolower(trim(pathinfo($nombre, PATHINFO_EXTENSION)));
debugLog("Archivo: $nombre | Ext: $ext");

$filas = match($ext) {
    'csv'  => leerCSV($archivo),
    'xlsx' => leerXLSX($archivo),
    'ods'  => leerODS($archivo),
    default => null,
};

if ($filas === null) responder(['ok' => false, 'msg' => "Formato no soportado: $ext. Usa CSV, XLSX u ODS."]);
if (count($filas) < 2) responder(['ok' => false, 'msg' => 'Archivo vacío o sin datos (menos de 2 filas)']);

// ── Cabeceras ─────────────────────────────────────────────────────────────────
$cabeceras    = array_shift($filas);
$cabNorm      = array_map('normCab', $cabeceras);

debugLog("Cabeceras (" . count($cabNorm) . "): " . implode(' | ', array_slice($cabNorm, 0, 20)));

// ── Mapa cabecera normalizada → campo BD (name en ost_form_field) ─────────────
//
// Clave   = fragmento que debe aparecer en la cabecera normalizada
// Valor   = name del campo en ost_form_field (form_id=22)
//
// El algoritmo busca coincidencia EXACTA primero, luego CONTIENE.
//
$MAPA_CAMPOS = [
    // Campos simples
    'FECHA'                          => 'fecha',          // columna especial → ost_ticket.created
    'EXPEDIENTE'                     => 'expedientes',
    'PLAN'                           => 'planes',
    'SECTOR'                         => 'sectores',
    'ACCION'                         => 'acciones',
    'GRUPO'                          => 'grupos',
    'NOMBRE DEL CURSO'               => 'nombrescursos',
    'ALUMNOS MATRICULADOS'           => 'ndealumnos',
    'ALUMNOS QUE FINALIZAN'          => 'nfinalizados',
    'TUTOR'                          => 'tutores',
    'PERSONAS DE APOYO'              => 'personasdeapoyo',
    'EQUIPO DE DINAMIZACION'         => 'equiposdedinamizacion',
    // Desempeño tutor/a
    'DOMINIO DE LOS CONTENIDOS'      => 'dominiosdeloscontenidos',
    'RESOLUCION DE DUDAS'            => 'resoluciondedudas',
    'CALIDAD DEL SEGUIMIENTO'        => 'calidadesdelseguimiento',
    'CLARIDAD DE LOS SEGUIMIENTOS'   => 'claridaddelosseguimientos',
    'CORRECCION Y REVISION'          => 'correccionyrevision',
    'OBSERVACIONES'                  => 'OBSERVACIONES',       // primera OBSERVACIONES = tutor
    // Desempeño persona de apoyo
    'ATENCION Y ACOMPANAMIENTO'      => 'atencionyacompañamiento',
    'RESOLUCION DE INCIDENCIAS'      => 'resoluciondeincidencias',
    'RAPIDEZ Y EFICACIA'             => 'rapidezyeficacia',
    'SEGUIMIENTO DEL ALUMNADO'       => 'seguimientodelalumnado',
    'REALIZACION DE ACCIONES'        => 'realizaciondeacciones',
    'CORRECCION Y REVISION APOYO'    => 'correccion',
    'OBSERVACIONES APOYO'            => 'observaciones2',
    // Equipo dinamización
    'REALIZA LLAMADAS'               => 'realizallamadas',
    'ALUMNADO INACTIVO'              => 'alumnadoinactivo',
    'LLEVA CABO ACCIONES'            => 'llevaacaboacciones',
    'REGISTRA CORRECTAMENTE'         => 'registracorrectamente',
    'DETECTA DE FORMA TEMPRANA'      => 'detectadeformatemprana',
    'OBSERVACIONES DINAMIZACION'     => 'observaciones3',
    // Análisis alumnado
    'PARTICIPACION GENERAL'          => 'participaciongeneral',
    'SATISFACCION PERCIBIDA'         => 'satisfacciónpercibida',
    'PRINCIPALES DIFICULTADES'       => 'principalesdificultades',
    'MOTIVOS'                        => 'observaciones4',
    'PERFIL ALUMNADO'                => 'perfilalumnado',
    // Incidencias
    'SE HAN DETECTADO INCIDENCIAS'   => 'sehandetectadoincidencias',
    'DESCRIBIR'                      => 'describir',
    'FUERON CORRECTAS'               => 'fueroncorrectas',
    'OBSERVACIONES ADICIONALES'      => 'observaciones5',
    // Valoración global
    'VALORACION GLOBAL'              => 'valoracionglobal',
    'CONCLUSION GENERAL'             => 'conclusionesgenerales',
    'ASPECTOS DESTACABLES'           => 'apectosdestacables',
    'ASPECTOS PRIORITARIOS'          => 'aspectosprioritarios',
    'NECESIDADES DETECTADAS'         => 'necesidadesdetectadasenlat',
    'EXISTIO COORDINACION'           => 'existiocordinacion',
    // Estado
    'ESTADO'                         => '__estado__',
];

// Construir mapeo índice de columna → campo BD
$mapeo = [];   // campo_bd => índice_columna
$usados = [];  // índices ya asignados

// Primero: coincidencias EXACTAS
foreach ($cabNorm as $idx => $cab) {
    foreach ($MAPA_CAMPOS as $patron => $campo) {
        if ($cab === $patron && !isset($mapeo[$campo]) && !in_array($idx, $usados)) {
            $mapeo[$campo] = $idx;
            $usados[] = $idx;
            debugLog("EXACTO: '$patron' → col $idx ($campo)");
        }
    }
}

// Segundo: coincidencias PARCIALES (contiene)
foreach ($cabNorm as $idx => $cab) {
    if (in_array($idx, $usados)) continue;
    foreach ($MAPA_CAMPOS as $patron => $campo) {
        if (isset($mapeo[$campo])) continue;
        if (strpos($cab, $patron) !== false) {
            $mapeo[$campo] = $idx;
            $usados[] = $idx;
            debugLog("PARCIAL: '$patron' en '$cab' → col $idx ($campo)");
            break;
        }
    }
}

debugLog("Mapeo final: " . json_encode($mapeo));

// ── Conexión BD ───────────────────────────────────────────────────────────────
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
    $db->set_charset('utf8mb4');
} catch (Exception $e) {
    responder(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
}

// ── Cargar estados desde BD ───────────────────────────────────────────────────
$statusMap = [];
$resStatus = $db->query("SELECT id, name FROM ost_ticket_status ORDER BY id ASC");
if ($resStatus) {
    while ($row = $resStatus->fetch_assoc()) {
        $n = mb_strtolower(trim($row['name']), 'UTF-8');
        $n = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $n);
        $statusMap[$n] = (int)$row['id'];
    }
}
$sinAsignarId = 1;
foreach ($statusMap as $n => $sid) {
    if (strpos($n, 'open') !== false || strpos($n, 'abierta') !== false) { $sinAsignarId = $sid; break; }
}

function resolverStatus(string $texto, array $statusMap, int $default): int {
    if (trim($texto) === '') return $default;
    $lower = mb_strtolower(trim($texto), 'UTF-8');
    $lower = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $lower);
    if (isset($statusMap[$lower])) return $statusMap[$lower];
    if (preg_match('/(cerrad|finaliz|anulad)/i', $texto)) {
        return $statusMap['cerrada'] ?? $statusMap['closed'] ?? $default;
    }
    if (preg_match('/en\s*curso/i', $texto)) return $statusMap['en curso'] ?? $default;
    if (preg_match('/iniciad/i', $texto))    return $statusMap['iniciada'] ?? $default;
    if (preg_match('/enviad/i', $texto))     return $statusMap['enviada']  ?? $default;
    foreach ($statusMap as $n => $sid) {
        if (strpos($lower, $n) !== false) return $sid;
    }
    return $default;
}

// ── Asegurar columnas en cdata ────────────────────────────────────────────────
$existentes = [];
$r = $db->query("SHOW COLUMNS FROM ost_ticket__cdata");
while ($row = $r->fetch_assoc()) $existentes[] = strtolower($row['Field']);

$necesarias = [
    'expedientes' => 'VARCHAR(255)',
    'planes'      => 'VARCHAR(255)',
    'sectores'    => 'VARCHAR(255)',
    'acciones'    => 'VARCHAR(255)',
    'grupos'      => 'VARCHAR(255)',
    'nombrescursos' => 'VARCHAR(255)',
    'ndealumnos'  => 'VARCHAR(64)',
    'nfinalizados'=> 'VARCHAR(64)',
    'tutores'     => 'VARCHAR(255)',
    'personasdeapoyo'          => 'VARCHAR(255)',
    'equiposdedinamizacion'    => 'VARCHAR(255)',
    'dominiosdeloscontenidos'  => 'VARCHAR(10)',
    'resoluciondedudas'        => 'VARCHAR(10)',
    'calidadesdelseguimiento'  => 'VARCHAR(10)',
    'claridaddelosseguimientos'=> 'VARCHAR(10)',
    'correccionyrevision'      => 'VARCHAR(10)',
    'OBSERVACIONES'            => 'TEXT',
    'atencionyacompañamiento'  => 'VARCHAR(10)',
    'resoluciondeincidencias'  => 'VARCHAR(10)',
    'rapidezyeficacia'         => 'VARCHAR(10)',
    'seguimientodelalumnado'   => 'VARCHAR(10)',
    'realizaciondeacciones'    => 'VARCHAR(10)',
    'correccion'               => 'VARCHAR(10)',
    'observaciones2'           => 'TEXT',
    'realizallamadas'          => 'VARCHAR(10)',
    'alumnadoinactivo'         => 'VARCHAR(10)',
    'llevaacaboacciones'       => 'VARCHAR(10)',
    'registracorrectamente'    => 'VARCHAR(10)',
    'detectadeformatemprana'   => 'VARCHAR(10)',
    'observaciones3'           => 'TEXT',
    'participaciongeneral'     => 'VARCHAR(10)',
    'satisfacciónpercibida'    => 'VARCHAR(10)',
    'principalesdificultades'  => 'VARCHAR(255)',
    'observaciones4'           => 'TEXT',
    'perfilalumnado'           => 'TEXT',
    'sehandetectadoincidencias'=> 'VARCHAR(10)',
    'describir'                => 'TEXT',
    'fueroncorrectas'          => 'VARCHAR(10)',
    'observaciones5'           => 'TEXT',
    'valoracionglobal'         => 'VARCHAR(10)',
    'conclusionesgenerales'    => 'TEXT',
    'apectosdestacables'       => 'TEXT',
    'aspectosprioritarios'     => 'TEXT',
    'necesidadesdetectadasenlat' => 'TEXT',
    'existiocordinacion'       => 'TEXT',
];

foreach ($necesarias as $col => $tipo) {
    if (!in_array(strtolower($col), $existentes)) {
        try { $db->query("ALTER TABLE ost_ticket__cdata ADD COLUMN `$col` $tipo"); }
        catch (Exception $e) { debugLog("No se pudo añadir columna $col: " . $e->getMessage()); }
    }
}

// ── Obtener field_id de cada campo en form_id=22 ──────────────────────────────
$fieldIds = [];
$resFields = $db->query("SELECT id, name FROM ost_form_field WHERE form_id = 22");
if ($resFields) {
    while ($row = $resFields->fetch_assoc()) {
        $fieldIds[$row['name']] = (int)$row['id'];
    }
}
debugLog("field_ids form 22: " . json_encode($fieldIds));

// ── Bucle de importación ──────────────────────────────────────────────────────
$importados = $errores = $vacias = 0;
$erroresEjemplo = [];

foreach ($filas as $idx => $fila) {
    // Rellenar columnas faltantes
    while (count($fila) < 80) $fila[] = '';

    $g = fn(string $key): string => isset($mapeo[$key])
        ? trim((string)($fila[$mapeo[$key]] ?? ''))
        : '';

    // Saltar filas vacías
    $expediente = $g('expedientes');
    $plan       = $g('planes');
    if ($expediente === '' && $plan === '' && $g('tutores') === '') {
        $vacias++;
        continue;
    }
    // Saltar cabeceras repetidas
    if (normCab($expediente) === 'EXPEDIENTE') { $vacias++; continue; }

    try {
        $fecha    = parseFecha($g('fecha')) ?? date('Y-m-d H:i:s');
        $estadoTxt= $g('__estado__');
        $statusId = resolverStatus($estadoTxt, $statusMap, $sinAsignarId);
        $numero   = 'CORD' . date('ymd') . sprintf('%06d', $importados + 1);

        // 1) INSERT ost_ticket
        $stmt = $db->prepare(
            "INSERT INTO ost_ticket (number, user_id, status_id, dept_id, topic_id, created, updated)
             VALUES (?, 1, ?, 1, 1, ?, NOW())"
        );
        $stmt->bind_param('sis', $numero, $statusId, $fecha);
        $stmt->execute();
        $ticketId = $db->insert_id;
        $stmt->close();

        if ($ticketId <= 0) throw new Exception("insert_id=0 en fila $idx");

        // 2) INSERT ost_form_entry (form_id=22)
        $stmt = $db->prepare(
            "INSERT INTO ost_form_entry (form_id, object_id, object_type, created, updated)
             VALUES (22, ?, 'T', NOW(), NOW())"
        );
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $entryId = $db->insert_id;
        $stmt->close();

        // 3) INSERT ost_form_entry_values para cada campo mapeado
        if ($entryId > 0) {
            $stmtVal = $db->prepare(
                "INSERT INTO ost_form_entry_values (entry_id, field_id, value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value=VALUES(value)"
            );
            foreach ($mapeo as $campo => $colIdx) {
                if ($campo === 'fecha' || $campo === '__estado__') continue;
                $valor = trim((string)($fila[$colIdx] ?? ''));
                if ($valor === '') continue;
                // Buscar field_id por name
                $fieldId = $fieldIds[$campo] ?? null;
                if (!$fieldId) continue;
                $stmtVal->bind_param('iis', $entryId, $fieldId, $valor);
                $stmtVal->execute();
            }
            $stmtVal->close();
        }

        // 4) INSERT ost_ticket__cdata
        // Construir SET dinámico solo con campos mapeados que existen en cdata
        $cdataSets  = [];
        $cdataTypes = 'i';
        $cdataVals  = [$ticketId];

        foreach ($mapeo as $campo => $colIdx) {
            if ($campo === 'fecha' || $campo === '__estado__') continue;
            $valor = trim((string)($fila[$colIdx] ?? ''));
            // Usar nombre de columna cdata (coincide con $campo salvo excepciones)
            $col = $campo;
            if (in_array(strtolower($col), $existentes) || in_array($col, array_keys($necesarias))) {
                $cdataSets[]   = "`$col` = ?";
                $cdataTypes   .= 's';
                $cdataVals[]   = $valor;
            }
        }

        if (!empty($cdataSets)) {
            $sqlCdata = "INSERT INTO ost_ticket__cdata (ticket_id, " .
                implode(', ', array_map(fn($s) => explode(' =', $s)[0], $cdataSets)) .
                ") VALUES (?" . str_repeat(', ?', count($cdataSets)) . ")
                 ON DUPLICATE KEY UPDATE " . implode(', ', $cdataSets);

            // Duplicar vals para ON DUPLICATE KEY UPDATE
            $allVals  = array_merge($cdataVals, array_slice($cdataVals, 1));
            $allTypes = $cdataTypes . substr($cdataTypes, 1);

            $stmtC = $db->prepare($sqlCdata);
            if ($stmtC) {
                $refs = [];
                foreach ($allVals as $k => $v) $refs[$k] = &$allVals[$k];
                array_unshift($refs, $allTypes);
                call_user_func_array([$stmtC, 'bind_param'], $refs);
                $stmtC->execute();
                $stmtC->close();
            }
        } else {
            // Asegurar fila vacía en cdata
            $db->query("INSERT IGNORE INTO ost_ticket__cdata (ticket_id) VALUES ($ticketId)");
        }

        // 5) INSERT ost_thread (requerido por osTicket)
        $stmt = $db->prepare("INSERT INTO ost_thread (object_id, object_type, created) VALUES (?, 'T', NOW())");
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $stmt->close();

        $importados++;

    } catch (Exception $e) {
        $errores++;
        $msg = "Fila " . ($idx + 2) . ": " . $e->getMessage();
        debugLog("ERROR: $msg");
        if (count($erroresEjemplo) < 5) $erroresEjemplo[] = $msg;
    }
}

$db->close();
debugLog("=== FIN: OK=$importados, ERR=$errores, VACIAS=$vacias ===");

$msg = "Importados: $importados";
if ($errores > 0) $msg .= ", Errores: $errores";
if ($vacias  > 0) $msg .= ", Vacías: $vacias";

responder([
    'ok'              => $importados > 0,
    'msg'             => $msg,
    'importados'      => $importados,
    'errores'         => $errores,
    'vacias'          => $vacias,
    'ext'             => $ext,
    'columnas'        => $mapeo,
    'errores_ejemplo' => $erroresEjemplo,
]);