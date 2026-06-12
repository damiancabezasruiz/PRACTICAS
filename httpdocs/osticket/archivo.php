<?php
/**
 * =============================================================================
 * archivo.php — Proxy de descarga / visualización de adjuntos de osTicket
 * =============================================================================
 *
 * Propósito:
 *   Sirve archivos adjuntos almacenados en osTicket al navegador del usuario.
 *   Actúa como proxy inteligente que intenta primero usar la API de osTicket
 *   (AttachmentFile) y, si no está disponible, cae en un modo de acceso directo
 *   a la base de datos.
 *
 * Funcionamiento:
 *   Modo 1 — API de osTicket disponible (AttachmentFile):
 *     1. Carga el framework de osTicket (main.inc.php, class.file.php).
 *     2. Usa AttachmentFile::lookup($id) para obtener la URL de descarga.
 *     3. Corrige la URL al dominio actual si cambió desde la instalación.
 *     4. Redirige al navegador con un 302.
 *
 *   Modo 2 — Fallback directo a BD (cuando la clase no existe):
 *     1. Conecta directamente a MySQL con las credenciales de ost-config.php.
 *     2. Recupera el archivo concatenando todos los chunks de ost_file_chunk.
 *     3. Intenta descomprimir gzip (osTicket comprime algunos adjuntos).
 *     4. Sirve el binario con los headers correctos.
 *
 * Parámetros GET:
 *   id    (int, requerido) — ID del archivo en ost_file.id
 *   modo  (string, 'ver'|'descargar') — 'descargar' fuerza attachment,
 *         'ver' intenta mostrar inline (default: 'ver')
 *
 * Ejemplos de uso:
 *   archivo.php?id=42               → muestra inline si es posible
 *   archivo.php?id=42&modo=descargar → fuerza descarga
 *
 * Dependencias:
 *   - PHP 8.0+ (usa mixed, str_starts_with, match)
 *   - osTicket instalado en ROOT_DIR
 *   - Extensión mysqli, extensión zlib (para gzuncompress)
 *
 * Notas técnicas:
 *   - Los chunks en ost_file_chunk pueden estar codificados en base64 y/o
 *     comprimidos con gzip dependiendo de la versión de osTickket.
 *   - El fallback BD concatena chunks con JOIN en lugar de ORDER BY chunk_id
 *     (columna se llama 'chunk' en versiones antiguas, 'chunk_id' en nuevas).
 *
 * @package    GrupoATU\Incidencias
 * @author     Equipo de desarrollo Grupo ATU
 * @version    1.1
 * =============================================================================
 */

// Solo registrar errores fatales en log; no mostrarlos para no corromper
// la salida binaria del archivo descargado
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_error.log');

// =============================================================================
// 1. BOOTSTRAP DE OSTICKET
// Carga la configuración, el framework principal y las clases de archivo.
// Si alguna clase falla, el bloque de fallback se encargará de servir el archivo.
// =============================================================================
define('ROOT_DIR',    '/var/www/vhosts/incidencias.grupoatu.com/httpdocs/osticket/');
define('INCLUDE_DIR', ROOT_DIR . 'include/');

require_once INCLUDE_DIR . 'ost-config.php';
require_once ROOT_DIR    . 'main.inc.php';
require_once INCLUDE_DIR . 'class.file.php';

// =============================================================================
// 2. VALIDAR PARÁMETROS DE ENTRADA
// =============================================================================
$id   = intval($_GET['id']   ?? 0);
$modo = $_GET['modo'] ?? 'ver'; // 'ver' = inline, 'descargar' = attachment

if ($id <= 0) {
    http_response_code(404);
    exit('Archivo no válido');
}

// =============================================================================
// 3. MODO FALLBACK — ACCESO DIRECTO A BD
// Se activa cuando AttachmentFile no está disponible (carga incompleta de osTicket
// o versión incompatible). Reproduce manualmente lo que haría la API de osTicket.
// =============================================================================
if (!class_exists('AttachmentFile')) {

    try {
        // Conexión directa usando constantes cargadas por ost-config.php
        $conexion = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        $conexion->set_charset('utf8mb4');

        // Recuperar metadatos + todos los chunks del archivo en una sola consulta.
        // Los chunks se ordenan por 'chunk ASC' (nombre de columna en versiones antiguas).
        $stmt = $conexion->prepare(
            "SELECT f.id, f.name, f.type, f.size, fs.filedata
             FROM ost_file f
             LEFT JOIN ost_file_chunk fs ON fs.file_id = f.id
             WHERE f.id = ?
             ORDER BY fs.chunk ASC"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            http_response_code(404);
            exit('Archivo no encontrado');
        }

        // Concatenar todos los chunks para reconstruir el archivo completo
        $fileData = '';
        $fileName = '';
        $fileType = '';

        while ($row = $result->fetch_assoc()) {
            $fileName  = $row['name'];
            $fileType  = $row['type'];
            $fileData .= $row['filedata'];
        }

        $stmt->close();
        $conexion->close();

        // -----------------------------------------------------------------
        // Decodificar y descomprimir
        // osTicket puede almacenar los chunks en base64, gzip, o ambos.
        // Se intenta base64 primero; si falla se usa el valor crudo.
        // Después se intenta descomprimir gzip; si falla se usa el resultado anterior.
        // -----------------------------------------------------------------
        $decoded = base64_decode($fileData, true);
        if ($decoded === false) $decoded = $fileData; // No era base64 válido

        $uncompressed = @gzuncompress($decoded); // '@' suprime warnings si no es gzip
        if ($uncompressed !== false) $decoded = $uncompressed;

        // -----------------------------------------------------------------
        // Enviar el archivo al navegador
        // -----------------------------------------------------------------
        $disposition = ($modo === 'descargar') ? 'attachment' : 'inline';

        header('Content-Type: ' . ($fileType ?: 'application/octet-stream'));
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
        header('Content-Length: ' . strlen($decoded));
        header('Cache-Control: private, max-age=3600');

        echo $decoded;
        exit;

    } catch (Exception $e) {
        error_log('archivo.php error BD: ' . $e->getMessage());
        http_response_code(500);
        exit('Error al cargar el archivo');
    }
}

// =============================================================================
// 4. MODO API OSTICKET — AttachmentFile disponible
// Usa la capa ORM de osTicket para obtener la URL canónica del archivo y
// redirigir al navegador. Más limpio y compatible con el sistema de permisos
// nativo de osTicket.
// =============================================================================
$file = AttachmentFile::lookup($id);
if (!$file) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

// Opciones para getDownloadUrl: inline o attachment según el parámetro ?modo
$options = [];
$options['disposition'] = ($modo === 'descargar') ? 'attachment' : 'inline';

$url = $file->getDownloadUrl($options);

// Corregir la URL al dominio actual por si la instalación fue migrada o
// el dominio original difiere del actual (frecuente tras migraciones de hosting)
$url = preg_replace(
    '#https?://[^/]+/osticket/#',
    'https://incidencias.grupoatu.com/osticket/',
    $url
);

// Limpiar buffer de salida antes de redirigir (evita headers ya enviados)
if (ob_get_length()) ob_end_clean();
header('Location: ' . $url);
exit;