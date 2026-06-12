<?php
/**
 * =============================================================================
 * ver_archivo.php — Servidor de archivos adjuntos desde la BD de osTicket
 * =============================================================================
 *
 * Propósito:
 *   Recupera y sirve al navegador archivos almacenados en la base de datos de
 *   osTicket (tablas ost_file y ost_file_chunk), sin necesidad de cargar el
 *   framework completo de osTicket ni depender del sistema de archivos local.
 *
 * Funcionamiento:
 *   1. Lee las credenciales de BD directamente desde ost-config.php mediante
 *      expresiones regulares (evita cargar todo osTicket).
 *   2. Recibe el parámetro GET ?id=<file_id>.
 *   3. Consulta los metadatos del archivo en ost_file (nombre, MIME, tamaño).
 *   4. Recupera el contenido binario trozo a trozo desde ost_file_chunk,
 *      ordenando por chunk_id para reconstruir el archivo completo.
 *   5. Decide si servirlo inline (imágenes, PDF) o como descarga adjunta.
 *   6. Envía los headers HTTP apropiados y escribe el contenido binario.
 *
 * Parámetros GET:
 *   id  (int, requerido) — Identificador del archivo en ost_file.id
 *
 * Ejemplos de uso:
 *   ver_archivo.php?id=42          → sirve el archivo inline si es imagen/PDF
 *   ver_archivo.php?id=42          → descarga como attachment si es otro tipo
 *
 * Tipos MIME servidos inline:
 *   image/jpeg, image/png, image/gif, image/webp, image/svg+xml, application/pdf
 *   El resto se sirven como attachment (descarga forzada).
 *
 * Dependencias:
 *   - PHP 7.4+
 *   - Extensión mysqli
 *   - include/ost-config.php en el mismo directorio padre
 *
 * Seguridad:
 *   ⚠ No valida permisos de usuario. Cualquiera con acceso a la URL puede
 *   descargar cualquier archivo por ID. Debe protegerse con autenticación
 *   a nivel de servidor o aplicación antes de publicarlo.
 *
 * @package    GrupoATU\Incidencias
 * @author     Equipo de desarrollo Grupo ATU
 * @version    1.0
 * =============================================================================
 */

// Desactivar reporte de errores en pantalla: un error visible corrompería
// la salida binaria del archivo y generaría un fichero descargado inválido
error_reporting(0);

// =============================================================================
// 1. LECTURA DE CREDENCIALES DESDE ost-config.php
// Se hace con regex sobre el texto del archivo para no ejecutar require_once,
// lo que evita cargar el bootstrap completo de osTicket y sus dependencias.
// =============================================================================
$config_file = __DIR__ . '/include/ost-config.php';
if (!file_exists($config_file)) die('Config no encontrado');

$c = file_get_contents($config_file);

// Extraer cada constante de BD con una expresión regular
// Las constantes en ost-config.php tienen la forma: define('DBHOST', 'valor')
$h = $d = $u = $p = '';
if (preg_match("/DBHOST['\"]?\s*,\s*['\"]([^'\"]+)['\"]/",  $c, $m)) $h = $m[1]; // Host
if (preg_match("/DBNAME['\"]?\s*,\s*['\"]([^'\"]+)['\"]/",  $c, $m)) $d = $m[1]; // Nombre de BD
if (preg_match("/DBUSER['\"]?\s*,\s*['\"]([^'\"]+)['\"]/",  $c, $m)) $u = $m[1]; // Usuario
if (preg_match("/DBPASS['\"]?\s*,\s*['\"]([^'\"]*?)['\"]/", $c, $m)) $p = $m[1]; // Contraseña

// =============================================================================
// 2. CONEXIÓN A MYSQL
// =============================================================================
$db = new mysqli($h, $u, $p, $d);
if ($db->connect_error) die('Error BD');

// utf8 (no utf8mb4) para mantener compatibilidad con el charset de la BD de osTicket
$db->set_charset('utf8');

// =============================================================================
// 3. VALIDAR PARÁMETRO DE ENTRADA
// Se acepta únicamente el ID numérico del archivo. Cualquier otro valor
// termina la ejecución inmediatamente.
// =============================================================================
$file_id = intval($_GET['id'] ?? 0);
if (!$file_id) die('ID no válido');

// =============================================================================
// 4. OBTENER METADATOS DEL ARCHIVO (ost_file)
// Contiene el nombre original, el tipo MIME y el tamaño total en bytes.
// El tamaño se usa para el header Content-Length, que permite al navegador
// mostrar la barra de progreso de descarga.
// =============================================================================
$stmt = $db->prepare("SELECT name, type, size FROM ost_file WHERE id=?");
$stmt->bind_param('i', $file_id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) die('Archivo no encontrado');

$nombre = $file['name'];
$tipo   = $file['type'] ?: 'application/octet-stream'; // Fallback si no hay MIME

// =============================================================================
// 5. OBTENER CONTENIDO BINARIO POR CHUNKS (ost_file_chunk)
// osTicket divide los archivos en trozos (chunks) para evitar problemas con
// columnas BLOB de tamaño limitado. Se recuperan en orden por chunk_id.
// =============================================================================
$stmt2 = $db->prepare(
    "SELECT filedata FROM ost_file_chunk WHERE file_id=? ORDER BY chunk_id"
);
$stmt2->bind_param('i', $file_id);
$stmt2->execute();
$result = $stmt2->get_result();

// =============================================================================
// 6. DETERMINAR DISPOSICIÓN: inline vs attachment
// Los tipos de imagen y PDF se pueden previsualizar en el navegador (inline).
// El resto fuerza la descarga (attachment).
// =============================================================================
$inline_types = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'application/pdf',
];
$disposicion = in_array($tipo, $inline_types) ? 'inline' : 'attachment';

// =============================================================================
// 7. ENVIAR HEADERS HTTP
// Se envían antes de cualquier dato binario. El orden importa: Content-Type
// primero, luego Disposition y opcionalmente Content-Length.
// =============================================================================
header('Content-Type: ' . $tipo);
header('Content-Disposition: ' . $disposicion . '; filename="' . $nombre . '"');
if ($file['size']) header('Content-Length: ' . $file['size']); // Solo si el tamaño es conocido
header('Cache-Control: private, max-age=3600'); // Cacheable 1 hora en cliente

// =============================================================================
// 8. STREAMING DEL CONTENIDO (chunk a chunk)
// Se hace echo directo de cada trozo binario para no acumular todo el archivo
// en memoria, lo que permite servir archivos grandes sin agotar el límite de RAM.
// =============================================================================
while ($chunk = $result->fetch_assoc()) {
    echo $chunk['filedata'];
}

// =============================================================================
// 9. LIMPIEZA
// =============================================================================
$stmt2->close();
$db->close();