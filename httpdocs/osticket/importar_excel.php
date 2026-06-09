<?php
/**
 * =============================================================================
 * importar_excel.php — Importación masiva de incidencias desde hoja de cálculo
 * =============================================================================
 *
 * Propósito:
 *   Endpoint AJAX que recibe un archivo CSV / XLSX / ODS subido por el usuario
 *   y vuelca su contenido como tickets en la base de datos de osTicket,
 *   rellenando los campos personalizados de ost_ticket__cdata.
 *
 * Funcionamiento general:
 *   1. Recibe el archivo mediante POST multipart (campo: archivo_excel).
 *   2. Detecta el formato por extensión y lo parsea con el lector apropiado:
 *        - CSV  → leerCSV()  (detección automática de encoding y delimitador)
 *        - XLSX → leerXLSX() (parse XML del ZIP sin librería externa)
 *        - ODS  → leerODS()  (parse XML del ZIP sin librería externa)
 *   3. Mapea las cabeceras del archivo a los campos de la BD usando un sistema
 *      de puntuación con patrones exactos / parciales / exclusiones, para
 *      resolver columnas con nombres ambiguos (detalles, razón, dificultad).
 *   4. Construye un catálogo histórico con los datos ya importados para
 *      reclasificar automáticamente los valores de las columnas problemáticas.
 *   5. Por cada fila:
 *        a. Extrae y normaliza los valores mapeados.
 *        b. Reclasifica detalles/razón/dificultad/datos contra el catálogo.
 *        c. Resuelve el texto de estado al ID de ost_ticket_status.
 *        d. Inserta en ost_ticket, ost_form_entry y ost_ticket__cdata.
 *        e. Crea el hilo (ost_thread) requerido por osTicket.
 *   6. Devuelve un JSON con contadores: importados, errores, vacías.
 *
 * Respuesta JSON:
 *   {
 *     "ok": true|false,
 *     "msg": "Importados: N, Errores: M, Vacías: K",
 *     "importados": N,
 *     "errores": M,
 *     "vacias": K,
 *     "ext": "csv|xlsx|ods",
 *     "columnas": { ...mapeo campo→índice... },
 *     "errores_ejemplo": [ "Fila X: mensaje", ... ]
 *   }
 *
 * Logs de depuración:
 *   - debug_import.log   → traza detallada del proceso (mapeo, reclasificación)
 *   - debug_import_php.log → errores PHP fatales
 *
 * Dependencias:
 *   - PHP 8.0+
 *   - Extensión mysqli, ZipArchive
 *   - include/ost-config.php (credenciales de BD)
 *
 * Límites configurados:
 *   - memory_limit: 512M    (archivos grandes pueden requerir mucha RAM)
 *   - max_execution_time: 600s (10 min para ficheros con miles de filas)
 *
 * @package    GrupoATU\Incidencias
 * @author     Equipo de desarrollo Grupo ATU
 * @version    2.0
 * =============================================================================
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_import_php.log');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'  => false,
            'msg' => 'Error fatal PHP: ' . $e['message'] . ' en línea ' . $e['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

if (!defined('INCLUDE_DIR')) define('INCLUDE_DIR', __DIR__ . '/include/');
require_once INCLUDE_DIR . 'ost-config.php';

file_put_contents(__DIR__ . '/debug_import.log', "=== IMPORT " . date('Y-m-d H:i:s') . " ===
");

function debugLog($msg) {
    file_put_contents(__DIR__ . '/debug_import.log', $msg . "
", FILE_APPEND);
}
function responder($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizar_cabecera($texto) {
    $texto = trim((string)$texto);
    $texto = mb_strtoupper($texto, 'UTF-8');
    $texto = str_replace(
        ['Á','É','Í','Ó','Ú','Ü','Ñ','"','"','"','\''],
        ['A','E','I','O','U','U','N','','','',''],
        $texto
    );
    $texto = preg_replace('/[
	]+/', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

function parseFecha($v) {
    if ($v === null || $v === '') return null;
    $v = trim((string)$v);
    if (is_numeric($v)) {
        $n = floatval($v);
        if ($n > 1 && $n < 100000) return date('Y-m-d H:i:s', ($n - 25569) * 86400);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $v, $m)) {
        $y = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
        return sprintf('%04d-%02d-%02d 00:00:00', $y, $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10) . ' 00:00:00';
    $ts = strtotime($v);
    if ($ts !== false) return date('Y-m-d H:i:s', $ts);
    return null;
}

function normTxt($t) {
    $t = mb_strtolower(trim((string)$t), 'UTF-8');
    $t = str_replace(
        ['á','é','í','ó','ú','ü','ñ'],
        ['a','e','i','o','u','u','n'],
        $t
    );
    return $t;
}

// ======================================================
// NORMALIZACIÓN BASE
// ======================================================
function norm_base($t) {
    $t = mb_strtolower(trim((string)$t), 'UTF-8');
    $t = str_replace(
        ['á','é','í','ó','ú','ü','ñ'],
        ['a','e','i','o','u','u','n'],
        $t
    );
    $t = preg_replace('/[^a-z0-9\s]/', '', $t);
    $t = preg_replace('/\s+/', ' ', $t);
    return trim($t);
}

function similitud_simple($a, $b) {
    similar_text($a, $b, $percent);
    return $percent;
}

// ======================================================
// CONSTRUIR CATÁLOGO HISTÓRICO
// ======================================================
function construir_catalogo(mysqli $db): array {

    $catalogo = [];

    $sql = "
        SELECT incidencia, detalles, razon, dificultad, datos
        FROM ost_ticket__cdata
        WHERE incidencia IS NOT NULL
        AND incidencia != ''
        LIMIT 5000
    ";

    $res = $db->query($sql);

    while ($row = $res->fetch_assoc()) {

        $inc = norm_base($row['incidencia']);
        if ($inc === '') continue;

        if (!isset($catalogo[$inc])) {
            $catalogo[$inc] = [
                'detalles' => [],
                'razon' => [],
                'dificultad' => [],
                'datos' => []
            ];
        }

        foreach (['detalles','razon','dificultad','datos'] as $campo) {
            $val = norm_base($row[$campo] ?? '');
            if ($val !== '') {
                if (!isset($catalogo[$inc][$campo][$val])) {
                    $catalogo[$inc][$campo][$val] = 0;
                }
                $catalogo[$inc][$campo][$val]++;
            }
        }
    }

    return $catalogo;
}

// ======================================================
// RECLASIFICACIÓN POR CATÁLOGO
// ======================================================
function reclasificar_por_catalogo(
    string $incidencia,
    array $valores,
    array $catalogo
): array {

    $inc = norm_base($incidencia);

    if (!isset($catalogo[$inc])) {
        return $valores;
    }

    $resultado = [
        'detalles' => '',
        'razon' => '',
        'dificultad' => '',
        'datos' => ''
    ];

    foreach ($valores as $origen => $valorOriginal) {

        $valor = norm_base($valorOriginal);
        if ($valor === '') continue;

        $mejorCampo = null;
        $mejorScore = 0;

        foreach (['detalles','razon','dificultad','datos'] as $campo) {

            foreach ($catalogo[$inc][$campo] as $valorHistorico => $freq) {

                $score = similitud_simple($valor, $valorHistorico);
                $score = $score + ($freq * 0.1);

                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejorCampo = $campo;
                }
            }
        }

        $umbral = 60;

if ($mejorCampo === 'razon') {
    $umbral = 45; // RAZÓN necesita más tolerancia
}

if ($mejorCampo && $mejorScore > $umbral) {
            if ($resultado[$mejorCampo] === '') {
                $resultado[$mejorCampo] = $valorOriginal;
            } else {
                $resultado[$mejorCampo] .= ' | ' . $valorOriginal;
            }
        } else {
            if ($resultado[$origen] === '') {
                $resultado[$origen] = $valorOriginal;
            } else {
                $resultado[$origen] .= ' | ' . $valorOriginal;
            }
        }
    }

    return $resultado;
}

function leerCSV($archivo) {
    debugLog("Leyendo CSV...");
    $content = file_get_contents($archivo);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    debugLog("Encoding CSV: " . ($encoding ?: 'no detectado'));
    if ($encoding && $encoding !== 'UTF-8') $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    $primeraLinea = strtok($content, "
");
    // Forzamos delimitador coma (tu CSV usa coma)
	$delim = ',';
    debugLog("Delimitador CSV: '$delim'");
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    $filas = [];
    while (($fila = fgetcsv($handle, 0, $delim, '"', '\')) !== false) {
        $tieneContenido = false;
        foreach ($fila as $v) { if (trim((string)$v) !== '') { $tieneContenido = true; break; } }
        if ($tieneContenido) $filas[] = $fila;
    }
    fclose($handle);
    debugLog("CSV filas: " . count($filas));
    return $filas;
}

function leerXLSX($archivo) {
    debugLog("Leyendo XLSX...");
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== true) return [];
    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $ssXml, $siMatches);
        foreach ($siMatches[1] as $si) {
            preg_match_all('/<t[^>]*>([^<]*)<\/t>/s', $si, $tMatches);
            $ss[] = html_entity_decode(implode('', $tMatches[1]), ENT_QUOTES, 'UTF-8');
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) return [];
    preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches);
    $filas = [];
    foreach ($rowMatches[1] as $rowXml) {
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
    debugLog("XLSX filas: " . count($filas));
    return $filas;
}

function leerODS($archivo) {
    debugLog("Leyendo ODS...");
    $zip = new ZipArchive();
    if ($zip->open($archivo) !== true) return [];
    $content = $zip->getFromName('content.xml');
    $zip->close();
    if (!$content) return [];
    $content = preg_replace('/<office:annotation[^>]*>.*?<\/office:annotation>/si', '', $content);
    preg_match_all('/<table:table-row[^>]*>(.*?)<\/table:table-row>/si', $content, $rowMatches);
    $filas = [];
    foreach ($rowMatches[1] as $rowHtml) {
        preg_match_all('/<table:table-cell([^>]*)(?:\s*\/>|>(.*?)<\/table:table-cell>)/si', $rowHtml, $cellMatches, PREG_SET_ORDER);
        $fila = []; $celdas = 0;
        foreach ($cellMatches as $cell) {
            if ($celdas > 60) break;
            $attrs = $cell[1] ?? ''; $inner = $cell[2] ?? '';
            $repeat = 1;
            if (preg_match('/number-columns-repeated="(\d+)"/i', $attrs, $rm)) $repeat = min(10, (int)$rm[1]);
            $val = '';
            if (preg_match('/office:date-value="([^"]+)"/i', $attrs, $dm)) $val = $dm[1];
            elseif (preg_match('/office:value="([^"]+)"/i', $attrs, $vm)) $val = $vm[1];
            elseif (preg_match_all('/<text:p[^>]*>(.*?)<\/text:p>/si', $inner, $tm)) {
                $textos = [];
                foreach ($tm[1] as $t) { $t = strip_tags($t); if (trim($t) !== '') $textos[] = trim($t); }
                $val = implode("
", $textos);
            }
            $val = html_entity_decode(trim($val), ENT_QUOTES, 'UTF-8');
            for ($r = 0; $r < $repeat; $r++) { $fila[] = $val; $celdas++; }
        }
        $tieneContenido = false;
        foreach ($fila as $v) { if (trim((string)$v) !== '') { $tieneContenido = true; break; } }
        if ($tieneContenido) $filas[] = $fila;
    }
    debugLog("ODS filas: " . count($filas));
    return $filas;
}

// ── Validación subida ────────────────────────────────────────────────────────
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    responder(['ok' => false, 'msg' => 'Error subiendo archivo']);
}
$archivo = $_FILES['archivo_excel']['tmp_name'];
$nombre  = $_FILES['archivo_excel']['name'] ?? '';
$ext     = strtolower(trim(pathinfo($nombre, PATHINFO_EXTENSION)));
debugLog("Archivo: $nombre, Extensión: '$ext'");

if ($ext === 'csv')       $filas = leerCSV($archivo);
elseif ($ext === 'xlsx')  $filas = leerXLSX($archivo);
elseif ($ext === 'ods')   $filas = leerODS($archivo);
else responder(['ok' => false, 'msg' => 'Formato no soportado: ' . $ext]);

if (count($filas) < 2) responder(['ok' => false, 'msg' => 'Archivo vacío o sin datos']);
debugLog("Total filas: " . count($filas));

// ── Cabeceras ────────────────────────────────────────────────────────────────
$cabeceras = array_shift($filas);
debugLog("Cabeceras RAW: " . implode(' | ', array_slice($cabeceras, 0, 30)));



/* ============================================================
 MAPEO INTELIGENTE DE CABECERAS CON SISTEMA DE CASCADA
 Similar a cómo osTicket valida campos en formularios
============================================================ */

// Primero: normalizar TODAS las cabeceras para análisis
$cabecerasNormalizadas = [];
foreach ($cabeceras as $i => $cab) {
    $cabecerasNormalizadas[$i] = normalizar_cabecera($cab);
}

// Segundo: definir patrones específicos para campos problemáticos
$patronesEspecificos = [
    'detalles' => [
        'exactos' => [
            'DETALLES',
            'DETALLE',
            'TIPO DE DATOS INCORRECTOS'
        ],
        'contiene' => [
            'TIPO DE DATOS',
            'DATOS INCORRECTOS'
        ],
        'excluye' => ['RAZON', 'DIFICULTAD', 'FALTA DE RECURSO', 'MOTIVO', 'NO QUIERE']
    ],
    'razon' => [
        'exactos' => [
            'RAZON',
            'MOTIVO NO QUIERE',
            'MOTIVO POR EL QUE NO QUIERE'
        ],
        'contiene' => [
            'RAZON',
            'NO QUIERE',
            'POR QUE NO'
        ],
        'excluye' => ['DETALLES', 'DIFICULTAD', 'DATOS INCORRECTOS', 'TIPO DE DATOS', 'MOTIVOS']
    ],
    'dificultad' => [
        'exactos' => [
            'DIFICULTAD',
            'FALTA DE RECURSO',
            'FALTA DE RECURSOS'
        ],
        'contiene' => [
            'DIFICULTAD',
            'FALTA',
            'RECURSO'
        ],
        'excluye' => ['DETALLES', 'RAZON', 'DATOS INCORRECTOS', 'TIPO DE DATOS', 'MOTIVO', 'NO QUIERE']
    ],
    'datos' => [
        'exactos' => [
            'DATOS'
        ],
        'contiene' => [
            'DATO'
        ],
        'excluye' => ['TIPO DE DATOS INCORRECTOS', 'DETALLES', 'RAZON', 'DIFICULTAD']
    ]
];

/**
 * Función para verificar si una cabecera coincide con un patrón
 */
function coincidePatron($cabNorm, $patron) {
    // 1. Verificar exclusiones primero
    if (!empty($patron['excluye'])) {
        foreach ($patron['excluye'] as $excluir) {
            if (strpos($cabNorm, $excluir) !== false) {
                return false;
            }
        }
    }
    
    // 2. Verificar coincidencias exactas
    if (!empty($patron['exactos'])) {
        foreach ($patron['exactos'] as $exacto) {
            if ($cabNorm === $exacto) {
                return 100;
            }
        }
    }
    
    // 3. Verificar coincidencias parciales
    if (!empty($patron['contiene'])) {
        foreach ($patron['contiene'] as $palabra) {
            if (strpos($cabNorm, $palabra) !== false) {
                return 50;
            }
        }
    }
    
    return 0;
}

// Tercero: mapear campos simples (sin conflictos)
$mapeo = [];
$puntuaciones = [];

foreach ($cabecerasNormalizadas as $i => $norm) {
    if (($norm === 'FECHA' || $norm === 'DATE') && !isset($mapeo['fecha']))
        $mapeo['fecha'] = $i;
    elseif ($norm === 'EXPEDIENTE' && !isset($mapeo['expediente']))
        $mapeo['expediente'] = $i;
    elseif ($norm === 'PLAN' && !isset($mapeo['plan']))
        $mapeo['plan'] = $i;
    elseif ($norm === 'SECTOR LABORA' && !isset($mapeo['sector_labora']))
        $mapeo['sector_labora'] = $i;
    elseif ($norm === 'SECTOR CYL' && !isset($mapeo['sector_cyl']))
        $mapeo['sector_cyl'] = $i;
    elseif ($norm === 'SECTOR ASTURIAS' && !isset($mapeo['sector_asturias']))
        $mapeo['sector_asturias'] = $i;
    elseif ($norm === 'SECTOR ESTATAL' && !isset($mapeo['sector_estatal']))
        $mapeo['sector_estatal'] = $i;
    elseif (strpos($norm, 'TUTOR/A') !== false)
        $mapeo['tutor'] = $i;
    elseif (strpos($norm, 'TUTOR') !== false && !isset($mapeo['tutor']))
        $mapeo['tutor'] = $i;
    elseif ($norm === 'FORMACION INTERNA' && !isset($mapeo['tutor']))
        $mapeo['tutor'] = $i;
    elseif (!isset($mapeo['nombre']) && strpos($norm, 'NOMBRE') !== false && strpos($norm, 'ALUMN') !== false)
        $mapeo['nombre'] = $i;
    elseif (!isset($mapeo['apellido']) && strpos($norm, 'APELLIDO') !== false)
        $mapeo['apellido'] = $i;
    elseif ($norm === 'EMPRESA' && !isset($mapeo['empresa']))
        $mapeo['empresa'] = $i;
    elseif ($norm === 'ACCION' && !isset($mapeo['accion']))
        $mapeo['accion'] = $i;
    elseif ($norm === 'GRUPO' && !isset($mapeo['grupo']))
        $mapeo['grupo'] = $i;
    elseif ($norm === 'CURSO' && !isset($mapeo['curso']))
        $mapeo['curso'] = $i;
    elseif ($norm === 'INCIDENCIA' && !isset($mapeo['incidencia']))
        $mapeo['incidencia'] = $i;
	// Detectar columna específica de RAZÓN tipo "Motivo no quiere hacer el curso"
elseif (
    !isset($mapeo['razon']) &&
    strpos($norm, 'MOTIVO NO QUIERE') !== false
) {
    $mapeo['razon'] = $i;
}
    elseif (!isset($mapeo['motivo']) && ($norm === 'MOTIVOS' || $norm === 'MOTIVO'))
        $mapeo['motivo'] = $i;
    elseif (!isset($mapeo['fotos']) && ($norm === 'FOTOS' || $norm === 'ARCHIVO' || $norm === 'FOTO'))
        $mapeo['fotos'] = $i;
    elseif (!isset($mapeo['medidas']) && strpos($norm, 'MEDIDAS') !== false)
        $mapeo['medidas'] = $i;
    elseif (!isset($mapeo['solucion']) && $norm === 'SOLUCION' && strpos($norm, 'FECHA') === false)
        $mapeo['solucion'] = $i;
    elseif (!isset($mapeo['fecha_sol']) && strpos($norm, 'FECHA') !== false && strpos($norm, 'SOLUCION') !== false)
        $mapeo['fecha_sol'] = $i;
    elseif ($norm === 'ESTADO' && !isset($mapeo['estado']))
        $mapeo['estado'] = $i;
    elseif (!isset($mapeo['estado_aux']) && $norm === 'ANULADO INICIADO FINALIZADO')
        $mapeo['estado_aux'] = $i;
}

// Cuarto: mapear campos conflictivos con sistema de puntuación
foreach (['detalles', 'razon', 'dificultad', 'datos'] as $campoProblematico) {
    $mejorColumna = -1;
    $mejorPuntuacion = 0;
    
    foreach ($cabecerasNormalizadas as $i => $cabNorm) {
        $puntos = coincidePatron($cabNorm, $patronesEspecificos[$campoProblematico]);
        
        if ($puntos > $mejorPuntuacion) {
            $mejorPuntuacion = $puntos;
            $mejorColumna = $i;
        }
    }
    
    if ($mejorColumna >= 0 && $mejorPuntuacion > 0) {
        $mapeo[$campoProblematico] = $mejorColumna;
        $puntuaciones[$campoProblematico] = $mejorPuntuacion;
        debugLog("Campo '$campoProblematico' → Col $mejorColumna ('{$cabeceras[$mejorColumna]}') puntos=$mejorPuntuacion");
    }
}

// Quinto: resolver conflictos
$columnasUsadas = [];
foreach ($mapeo as $campo => $columna) {
    if (!isset($columnasUsadas[$columna])) {
        $columnasUsadas[$columna] = [$campo];
    } else {
        $columnasUsadas[$columna][] = $campo;
    }
}

foreach ($columnasUsadas as $col => $campos) {
    if (count($campos) > 1) {
        debugLog("⚠ CONFLICTO en col $col: " . implode(', ', $campos));
        
        usort($campos, function($a, $b) use ($puntuaciones) {
            return ($puntuaciones[$b] ?? 0) - ($puntuaciones[$a] ?? 0);
        });
        
        $ganador = $campos[0];
        debugLog("  ✓ Ganador: $ganador");
        
        foreach (array_slice($campos, 1) as $perdedor) {
            unset($mapeo[$perdedor]);
            debugLog("  ✗ Eliminado: $perdedor");
        }
    }
}

// Sexto: Fallbacks
if (!isset($mapeo['detalles'])) {
    foreach ($cabecerasNormalizadas as $i => $cabNorm) {
        if (strpos($cabNorm, 'TIPO') !== false 
            && !in_array($i, $mapeo)
            && strpos($cabNorm, 'RAZON') === false
            && strpos($cabNorm, 'DIFICULTAD') === false
        ) {
            $mapeo['detalles'] = $i;
            debugLog("Fallback DETALLES → col $i");
            break;
        }
    }
}

if (!isset($mapeo['datos'])) {
    foreach ($cabecerasNormalizadas as $i => $cabNorm) {
        if ($cabNorm === 'DATOS' && !in_array($i, $mapeo)) {
            $mapeo['datos'] = $i;
            debugLog("Fallback DATOS → col $i");
            break;
        }
    }
}

if (!isset($mapeo['fecha_sol'])) {
    foreach ($cabeceras as $i => $cab) {
        $norm = normalizar_cabecera($cab);
        if (strpos($norm, 'FECHA') !== false 
            && $i !== ($mapeo['fecha'] ?? -1)
            && !in_array($i, $mapeo)
        ) {
            $mapeo['fecha_sol'] = $i;
            debugLog("Fallback FECHA_SOL → col $i");
            break;
        }
    }
}

// Fallback fecha_sol
if (!isset($mapeo['fecha_sol'])) {
    foreach ($cabeceras as $i => $cab) {
        $norm = normalizar_cabecera($cab);
        if (strpos($norm, 'FECHA') !== false && $i !== ($mapeo['fecha'] ?? -1)) {
            $mapeo['fecha_sol'] = $i;
            break;
        }
    }
}

debugLog("=== MAPEO DETALLADO ===");
foreach ($mapeo as $campo => $col) {
    debugLog("  $campo => Col $col: " . ($cabeceras[$col] ?? '???'));
}
debugLog("Mapeo FINAL: " . json_encode($mapeo));

// ── Conexión BD ──────────────────────────────────────────────────────────────
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
    $db->set_charset('utf8mb4');
	$CATALOGO = construir_catalogo($db);
} catch (Exception $e) {
    responder(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
}

/* ============================================================
   CARGAR ESTADOS DESDE BD
   Tu BD actual tiene:
   1=open, 2=resolved, 3=closed, 4=archived, 5=deleted,
   6=iniciada, 7=en curso, 8=abierta, 9=enviada

   El importador viejo usaba: 6=iniciada, 7=en_curso, 8=cerrada, 9=enviada
   Pero en tu BD actual 8=abierta NO cerrada.

   Necesitamos mapear el texto del CSV → ID correcto de la BD.
============================================================ */
$statusMap = [];
$resStatus = $db->query("SELECT id, name, state FROM ost_ticket_status ORDER BY id ASC");
if ($resStatus) {
    while ($row = $resStatus->fetch_assoc()) {
        $nameNorm = mb_strtolower(trim($row['name']), 'UTF-8');
        $nameNorm = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $nameNorm);
        $statusMap[$nameNorm] = (int)$row['id'];
    }
    $resStatus->free();
}
debugLog("Status map BD: " . json_encode($statusMap));

// ID para "sin asignar" = cualquier OPEN que no sea los estados nombrados
$sinAsignarId = $statusMap['open'] ?? $statusMap['abierta'] ?? $statusMap['abierto'] ?? 1;
// Intentar encontrar uno específico "sin asignar"
foreach ($statusMap as $n => $sid) {
    if (strpos($n, 'sin asignar') !== false) { $sinAsignarId = $sid; break; }
}
debugLog("Sin asignar ID: $sinAsignarId");

/**
 * Resolver texto de estado → status_id
 * El texto viene de unir las columnas "Anulado/Iniciado/Finalizado" + "ESTADO"
 */
function resolverStatusId(string $textoCompleto, array $statusMap, int $sinAsignarId): int
{
    $texto = trim($textoCompleto);
    if ($texto === '') return $sinAsignarId;

    $lower = mb_strtolower($texto, 'UTF-8');
    $lower = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $lower);

    // 1. Coincidencia exacta
    if (isset($statusMap[$lower])) return $statusMap[$lower];

    // 2. Buscar por palabras clave en orden de prioridad
    // CERRADA/FINALIZADA/ANULADA → buscar "closed" o "resolved" en la BD
    if (preg_match('/(cerrad|finaliz|anulad)/i', $texto)) {
        // Preferir closed > resolved > cualquier estado con esos nombres
        if (isset($statusMap['cerrada']))    return $statusMap['cerrada'];
        if (isset($statusMap['cerrado']))    return $statusMap['cerrado'];
        if (isset($statusMap['closed']))     return $statusMap['closed'];
        if (isset($statusMap['finalizada'])) return $statusMap['finalizada'];
        if (isset($statusMap['resolved']))   return $statusMap['resolved'];
        // Si no hay ninguno específico, buscar por state='closed' en BD
        return $sinAsignarId; // fallback
    }

    // EN CURSO / INICIADO
    if (preg_match('/(en\s*curso)/i', $texto)) {
        if (isset($statusMap['en curso'])) return $statusMap['en curso'];
        return $sinAsignarId;
    }
    if (preg_match('/iniciad/i', $texto)) {
        if (isset($statusMap['iniciada'])) return $statusMap['iniciada'];
        if (isset($statusMap['iniciado'])) return $statusMap['iniciado'];
        return $sinAsignarId;
    }

    // ENVIADA
    if (preg_match('/enviad/i', $texto)) {
        if (isset($statusMap['enviada'])) return $statusMap['enviada'];
        if (isset($statusMap['enviado'])) return $statusMap['enviado'];
        return $sinAsignarId;
    }

    // 3. Coincidencia parcial genérica
    foreach ($statusMap as $nameN => $sid) {
        if ($nameN !== '' && (strpos($lower, $nameN) !== false || strpos($nameN, $lower) !== false)) {
            return $sid;
        }
    }

    debugLog("  Estado no reconocido: '$texto' → sin asignar (ID $sinAsignarId)");
    return $sinAsignarId;
}

// ── Asegurar columnas ────────────────────────────────────────────────────────
$existentes = [];
$r = $db->query("SHOW COLUMNS FROM ost_ticket__cdata");
while ($row = $r->fetch_assoc()) $existentes[] = strtolower($row['Field']);
$necesarias = [
    'subject'=>'VARCHAR(255)', 'plan'=>'VARCHAR(255)',
    'sector_labora'=>'VARCHAR(255)', 'sector_cyl'=>'VARCHAR(255)',
    'sector_asturias'=>'VARCHAR(255)', 'sector_estatal'=>'VARCHAR(255)',
    'tutor'=>'VARCHAR(255)', 'nombreAlu'=>'VARCHAR(255)', 'apellidosAlu'=>'VARCHAR(255)',
    'empresa'=>'VARCHAR(255)', 'accion'=>'VARCHAR(255)', 'grupo'=>'VARCHAR(64)',
    'curso'=>'VARCHAR(255)', 'incidencia'=>'TEXT', 'detalles'=>'MEDIUMTEXT',
    'razon'=>'MEDIUMTEXT', 'dificultad'=>'MEDIUMTEXT', 'datos'=>'MEDIUMTEXT',
    'motivo'=>'TEXT', 'fotos'=>'TEXT', 'medidas'=>'TEXT',
    'solucion_manual'=>'TEXT', 'fecha_solucion_manual'=>'DATETIME NULL',
    'estado_texto'=>'VARCHAR(64)',
];
foreach ($necesarias as $col => $tipo) {
    if (!in_array(strtolower($col), $existentes)) {
        try { $db->query("ALTER TABLE ost_ticket__cdata ADD COLUMN `$col` $tipo"); }
        catch (Exception $e) {}
    }
}

/* ============================================================
   BUCLE DE IMPORTACIÓN
============================================================ */
$importados = $errores = $vacias = 0;
$erroresEjemplo = [];

foreach ($filas as $idx => $fila) {
    while (count($fila) < 60) $fila[] = '';

    // Función helper para extraer valor
    $g = function($key) use ($fila, $mapeo) {
        return isset($mapeo[$key]) ? trim((string)($fila[$mapeo[$key]] ?? '')) : '';
    };

    $fecha          = $g('fecha');
    $expediente     = $g('expediente');
    $plan           = $g('plan');
    $sector_labora  = $g('sector_labora');
    $sector_cyl     = $g('sector_cyl');
    $sector_asturias= $g('sector_asturias');
    $sector_estatal = $g('sector_estatal');
    $tutor          = $g('tutor');
    $nombreAlu      = $g('nombre');
    $apellidosAlu   = $g('apellido');
    $empresa        = $g('empresa');
    $accion         = $g('accion');
    $grupo          = $g('grupo');
    $curso          = $g('curso');
    $incidencia     = $g('incidencia');
    $detalles       = $g('detalles');
    $razon          = $g('razon');
    $dificultad     = $g('dificultad');
    $datos          = $g('datos');
	$valoresIniciales = [
    'detalles' => $detalles,
    'razon' => $razon,
    'dificultad' => $dificultad,
    'datos' => $datos
];

$reclasificados = reclasificar_por_catalogo(
    $incidencia,
    $valoresIniciales,
    $CATALOGO
);

$detalles = $reclasificados['detalles'];
$razon = $reclasificados['razon'];
$dificultad = $reclasificados['dificultad'];
$datos = $reclasificados['datos'];
	
	// ==========================================================
// CASO ESPECIAL: "Datos personales / contacto erróneos"
// ==========================================================
$incNorm = mb_strtolower(trim($incidencia), 'UTF-8');
$incNorm = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $incNorm);

if (strpos($incNorm, 'datos personales') !== false || strpos($incNorm, 'contacto erroneo') !== false) {
    $detNorm = mb_strtolower(trim($detalles), 'UTF-8');
    $detNorm = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $detNorm);
    
    $valoresMover = ['correo', 'electronico', 'telefono', 'email', 'datos personales'];
    
    foreach ($valoresMover as $v) {
        if (strpos($detNorm, $v) !== false) {
            if (empty($datos)) {
                debugLog("Fila $idx: Moviendo DETALLES → DATOS (por incidencia)");
                $datos = $detalles;
                $detalles = '';
            }
            break;
        }
    }
}
    $motivo         = $g('motivo');
    $fotos          = $g('fotos');
    $medidas        = $g('medidas');
    $solucion       = $g('solucion');
    $fechaSol       = $g('fecha_sol');
    // DOS campos de estado, como el viejo
    $estado         = $g('estado');
    $estadoAux      = $g('estado_aux');

    if ($idx < 5) {
        debugLog("Fila $idx => exp=$expediente | estado='$estado' | estadoAux='$estadoAux'");
    }

    // Saltar filas vacías
    if (empty($expediente) && empty($nombreAlu) && empty($apellidosAlu) && empty($empresa)) {
        $vacias++;
        continue;
    }
    // Saltar cabeceras repetidas
    if (normalizar_cabecera($expediente) === 'EXPEDIENTE') {
        $vacias++;
        continue;
    }
// Logging para diagnóstico (primeras 5 filas)
if ($importados < 5) {
    debugLog("═════════════════════════════════════");
    debugLog("Fila #$idx:");
    debugLog("  Expediente: $expediente");
    debugLog("  Incidencia: $incidencia");
    debugLog("  DETALLES: " . substr($detalles, 0, 60));
    debugLog("  RAZÓN: " . substr($razon, 0, 60));
    debugLog("  DIFICULTAD: " . substr($dificultad, 0, 60));
    debugLog("  DATOS: " . substr($datos, 0, 60));
}
    try {
        $fechaParsed    = parseFecha($fecha);
        $fechaSolParsed = parseFecha($fechaSol);

        // ── UNIR ESTADO: exactamente como el viejo ────────────────────
        // El viejo: $estadoTexto = trim($estado.' '.$estadoAux);
        // Pero el valor REAL suele estar en UNA de las dos columnas.
        // Tomamos el que NO esté vacío. Si ambos tienen valor, los unimos.
        $estadoTexto = trim(trim($estadoAux) . ' ' . trim($estado));
        $estadoTexto = trim($estadoTexto);

        if ($idx < 10) {
            debugLog("  estadoTexto combinado = '$estadoTexto'");
        }

        // Resolver status_id
        $statusId = resolverStatusId($estadoTexto, $statusMap, $sinAsignarId);

        // Texto a guardar en estado_texto (el valor más relevante)
        $estadoGuardar = $estadoTexto;

        if ($idx < 10) {
            debugLog("  → statusId=$statusId, guardar='$estadoGuardar'");
        }

        $numero = 'IMP' . date('ymd') . sprintf('%06d', $importados);

        // INSERT ost_ticket
        $stmt1 = $db->prepare(
            "INSERT INTO ost_ticket (number, user_id, status_id, dept_id, topic_id, created, updated)
             VALUES (?, 1, ?, 1, 1, ?, NOW())"
        );
        $stmt1->bind_param('sis', $numero, $statusId, $fechaParsed);
       $stmt1->execute();
$ticketId = $db->insert_id;

$stmtForm = $db->prepare("
    INSERT INTO ost_form_entry
    (form_id, object_id, object_type, created, updated)
    VALUES (18, ?, 'T', NOW(), NOW())
");

$stmtForm->bind_param("i", $ticketId);
$stmtForm->execute();
$stmtForm->close();

$stmt1->close();

        // INSERT ost_ticket__cdata
        $sql = "INSERT INTO ost_ticket__cdata (
            ticket_id, subject, plan, sector_labora, sector_cyl, sector_asturias, sector_estatal,
            tutor, nombreAlu, apellidosAlu, empresa, accion, grupo, curso,
            incidencia, detalles, razon, dificultad, datos, motivo,
            fotos, medidas, solucion_manual, fecha_solucion_manual, estado_texto
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = $db->prepare($sql);
        $stmt2->bind_param(
            'issssssssssssssssssssssss',
            $ticketId, $expediente, $plan, $sector_labora, $sector_cyl, $sector_asturias, $sector_estatal,
            $tutor, $nombreAlu, $apellidosAlu, $empresa, $accion, $grupo, $curso,
            $incidencia, $detalles, $razon, $dificultad, $datos, $motivo,
            $fotos, $medidas, $solucion, $fechaSolParsed, $estadoGuardar
        );
        $stmt2->execute();
        $stmt2->close();

        // INSERT ost_thread
        $stmt3 = $db->prepare("INSERT INTO ost_thread (object_id, object_type, created) VALUES (?, 'T', NOW())");
        $stmt3->bind_param('i', $ticketId);
        $stmt3->execute();
        $stmt3->close();

        $importados++;

    } catch (Exception $e) {
        $errores++;
        if (count($erroresEjemplo) < 5) {
            $erroresEjemplo[] = "Fila " . ($idx + 2) . ": " . $e->getMessage();
        }
    }
}

$db->close();
debugLog("=== FIN: OK=$importados, ERR=$errores, VACIAS=$vacias ===");

$msg = "Importados: $importados";
if ($errores > 0) $msg .= ", Errores: $errores";
if ($vacias  > 0) $msg .= ", Vacías: $vacias";

responder([
    'ok'             => $importados > 0,
    'msg'            => $msg,
    'importados'     => $importados,
    'errores'        => $errores,
    'vacias'         => $vacias,
    'ext'            => $ext,
    'columnas'       => $mapeo,
    'errores_ejemplo'=> $erroresEjemplo,
]);