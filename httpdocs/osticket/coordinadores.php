<?php
/**
 * =============================================================================
 * coordinadores.php — Gestión principal de incidencias (CRUD + adjuntos)
 * =============================================================================
 *
 * Propósito:
 *   Página web principal y API AJAX del sistema de gestión de incidencias
 *   de Grupo ATU. Permite crear, editar, listar y resolver incidencias de
 *   alumnos, integrándose con osTicket como backend de tickets.
 *
 * Modos de operación (según parámetros GET/POST):
 *   Sin parámetros      → Renderiza la página HTML principal (lista de tickets)
 *   POST action=crear   → Crea un nuevo ticket en osTicket + cdata
 *   POST action=editar  → Actualiza campos de un ticket existente
 *   POST action=borrar  → Elimina un ticket y sus datos asociados
 *   GET  action=ver     → Devuelve JSON con el detalle de un ticket
 *   GET  action=lista   → Devuelve JSON paginado con la lista de tickets
 *   POST action=adjunto → Sube un archivo adjunto al ticket
 *   POST action=estado  → Cambia el estado de un ticket
 *
 * Arquitectura de datos:
 *   osTicket nativo:
 *     - ost_ticket          → datos base del ticket (número, estado, fechas)
 *     - ost_thread          → hilo de conversación
 *     - ost_form_entry      → entrada de formulario personalizado (form_id=22)
 *     - ost_form_entry_values → valores de los campos del formulario
 *     - ost_file / ost_file_chunk → archivos adjuntos
 *
 *   Extensión personalizada:
 *     - ost_ticket__cdata   → campos extendidos desnormalizados para
 *                             consultas rápidas sin JOIN a form_entry_values
 *
 * Campos extendidos (ost_ticket__cdata):
 *   Alumno:      nombreAlu, apellidosAlu
 *   Formación:   plan, sector_labora, sector_cyl, sector_asturias,
 *                sector_estatal, tutor, empresa, accion, grupo, curso
 *   Incidencia:  incidencia, detalles, razon, dificultad, datos,
 *                motivo, fotos, medidas
 *   Resolución:  solucion_manual, fecha_solucion_manual, estado_texto
 *
 * Resolución de valores de listas osTicket:
 *   osTicket almacena los valores de campos lista como IDs numéricos o JSON.
 *   Este archivo los resuelve a texto legible mediante:
 *     - cargar_mapa_lista_items() → carga el mapa ID→valor desde ost_list_items
 *     - resolver_valor_lista()    → convierte ID/JSON a texto
 *     - limpiar_html_osticket()   → convierte HTML a texto plano
 *
 * Sistema de adjuntos:
 *   Los archivos se almacenan en osTicket (ost_file + ost_file_chunk) y se
 *   sirven a través de archivo.php. Las referencias se guardan en cdata.fotos
 *   como lista separada por comas de IDs.
 *
 * Logs de depuración:
 *   debug_error.log — errores PHP y excepciones capturadas
 *
 * Dependencias:
 *   - PHP 8.0+ (named arguments, match, str_contains, etc.)
 *   - osTicket instalado en ROOT_DIR con form_id=22 configurado
 *   - Extensión mysqli
 *   - Constante FORM_INCIDENCIAS_ID = 22
 *
 * @package    GrupoATU\Incidencias
 * @author     Equipo de desarrollo Grupo ATU
 * @version    4.0
 * =============================================================================
 */

// ============================================================
// CONFIGURACIÓN DE ERRORES Y TIMEZONE
// ARCHIVO INCIDENCIAS.PHP
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ini_set('error_log', __DIR__ . '/debug_error.log');

// Capturar errores fatales y devolverlos como JSON en peticiones POST
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Error fatal: ' . $error['message'] . ' línea ' . $error['line']]);
        }
    }
});


date_default_timezone_set('Europe/Madrid');

// ============================================================
// MINI LOGIN — usuarios y contraseñas múltiples
// Añade o elimina entradas del array según necesites.
// Las contraseñas se guardan como hash (password_hash).
// Para generar un hash nuevo: php -r "echo password_hash('tuClave', PASSWORD_DEFAULT);"
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin_sso.php';

$USUARIOS_INCIDENCIAS = [
    'IncidenciasAtu'        => 'D/*50smPm@7FPM@c£EUMU&',
    'Admin'                 => '1,<X8r0.5(Tl03?-gq]giU',
];

// Cerrar sesión
if (isset($_GET['logout'])) {
    if (!empty($_SESSION['admin_sso'])) {
        admin_sso_logout();
    } else {
        session_destroy();
    }
    header('Location: coordinadores.php');
    exit;
}

// Procesar login
if (isset($_POST['_login_user'], $_POST['_login_password'])) {
    $u = trim($_POST['_login_user']);
    $p = $_POST['_login_password'];
    if (isset($USUARIOS_INCIDENCIAS[$u]) && $p === $USUARIOS_INCIDENCIAS[$u]) {
        if ($u === ADMIN_SSO_USER) {
            admin_sso_activate();
        } else {
            $_SESSION['incidencias_auth'] = true;
            $_SESSION['incidencias_user'] = $u;
        }
        header('Location: coordinadores.php');
        exit;
    } else {
        $login_error = 'Usuario o contraseña incorrectos.';
    }
}

// Mostrar pantalla de login si no está autenticado
if (empty($_SESSION['incidencias_auth'])) {
    // Permitir peticiones AJAX sin romper (devuelven 401)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['ajax_sectores_fila']) || isset($_GET['ajax_filtro_opciones'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
        exit;
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - Coordinadores ATU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Inter',sans-serif;
            background:linear-gradient(135deg,#2d3a9e 0%,#3b4fd8 50%,#4f63e7 100%);
            height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .login-card{
            background:#fff;
            border-radius:20px;
            padding:40px 50px;
            box-shadow:0 20px 60px rgba(0,0,0,.3);
            width:100%;
            max-width:420px;
        }
        .login-header{
            text-align:center;
            margin-bottom:30px;
        }
        .login-header h1{
            font-size:28px;
            color:#0f172a;
            margin-bottom:8px;
        }
        .login-header p{
            color:#64748b;
            font-size:14px;
        }
        .form-group{
            margin-bottom:20px;
        }
        .form-group label{
            display:block;
            font-size:13px;
            font-weight:600;
            color:#334155;
            margin-bottom:6px;
        }
        .form-group input{
            width:100%;
            height:46px;
            border:2px solid #e2e8f0;
            border-radius:10px;
            padding:0 16px;
            font-size:15px;
            font-family:'Inter',sans-serif;
            transition:border-color .3s;
        }
        .form-group input:focus{
            outline:none;
            border-color:#6c63ff;
            box-shadow:0 0 0 3px rgba(108,99,255,.15);
        }
        .btn-login{
            width:100%;
            height:48px;
            background:#6c63ff;
            color:#fff;
            border:none;
            border-radius:10px;
            font-size:15px;
            font-weight:700;
            cursor:pointer;
            transition:background .3s;
        }
        .btn-login:hover{
            background:#574fd6;
        }
        .error-msg{
            background:#fee2e2;
            color:#991b1b;
            padding:12px 16px;
            border-radius:8px;
            margin-bottom:20px;
            font-size:13px;
            border-left:4px solid #dc2626;
        }

        /* keep these dummy rules to avoid parse errors below */
        .field .eye { display:none; }
        .btn-login-old {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>🔐 Acceso Coordinadores</h1>
            <p>Introduce tus credenciales</p>
        </div>

        <?php if (!empty($login_error)): ?>
            <div class="error-msg">❌ <?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>

        <form method="POST" action="coordinadores.php">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="_login_user" required autofocus>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="_login_password" required>
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
}
// ============================================================
// FIN LOGIN — usuario autenticado, continúa la página normal
// ============================================================


// ============================================================
// RUTAS Y CARGA DE ARCHIVOS DE OSTICKET
// ============================================================

if (!defined('INCLUDE_DIR')) {
    define('INCLUDE_DIR', dirname(__FILE__) . '/include/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__) . '/');
}

if (!defined('BOOTSTRAP_LOADED')) {
    define('BOOTSTRAP_LOADED', true);
    // osTicket bootstrap.php intenta modificar ini de sesión → cerrarla antes de cargarlo
    $__session_backup = $_SESSION ?? [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    require_once INCLUDE_DIR . 'ost-config.php';
    require_once ROOT_DIR . 'main.inc.php';
    require_once INCLUDE_DIR . 'class.file.php';
    // Restaurar nuestra sesión tras la carga de osTicket
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = array_merge($_SESSION ?? [], $__session_backup);
    unset($__session_backup);
} else {
    require_once INCLUDE_DIR . 'class.file.php';
}

define('FORM_INCIDENCIAS_ID', 22);

// Verificar si la clase Ticket está disponible
if (!class_exists('Ticket')) {
    error_log('ERROR CRÍTICO: Clase Ticket no cargada');
    $_TICKET_API_AVAILABLE = false;
} else {
    $_TICKET_API_AVAILABLE = true;
}

require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.thread.php';
require_once INCLUDE_DIR . 'class.attachment.php';
require_once INCLUDE_DIR . 'class.file.php';

error_log('Ticket exists? ' . (class_exists('Ticket') ? 'SI' : 'NO'));


// Verificar configuración de base de datos
if (!defined('DBHOST') || !defined('DBUSER') || !defined('DBPASS') || !defined('DBNAME')) {
    die('❌ Configuración BD no encontrada');
}

/* =========================================================
   HELPERS RESOLUCIÓN OSTICKET
========================================================= */

/**
 * Cargar el mapa de IDs a valores desde las tablas ost_list_items
 * @param mysqli $conexion Conexión a la base de datos
 * @return array Mapa de ID => valor
 */
function cargar_mapa_lista_items(mysqli $conexion): array {
    $mapa = [];
    $tablas = [
        ['tabla' => 'ost_list_items', 'id' => 'id', 'value' => 'value'],
        ['tabla' => 'ost_list_item', 'id' => 'id', 'value' => 'value'],
    ];
    
    foreach ($tablas as $t) {
        try {
            $check = $conexion->query("SHOW TABLES LIKE '{$t['tabla']}'");
            if ($check && $check->num_rows > 0) {
                $res = $conexion->query("SELECT `{$t['id']}` AS id, `{$t['value']}` AS value FROM `{$t['tabla']}`");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $id = (int)$row['id'];
                        $val = trim((string)$row['value']);
                        if ($id > 0 && $val !== '') {
                            $mapa[$id] = $val;
                        }
                    }
                    if (!empty($mapa)) break;
                }
            }
        } catch (Exception $e) {}
    }
    return $mapa;
}


function obtener_status_id_sin_asignar(mysqli $conexion): int {
    // Estos son tus estados “mapeados”
    $ids_fijos = [6,7,8,9];
    $notIn = implode(',', array_map('intval', $ids_fijos));

    // 1) Preferir un estado OPEN que NO sea 6/7/8/9
    $sql = "SELECT id
            FROM ost_ticket_status
            WHERE state='open' AND id NOT IN ($notIn)
            ORDER BY id ASC
            LIMIT 1";
    $r = $conexion->query($sql);
    if ($r && ($row = $r->fetch_assoc()))
        return (int)$row['id'];

    // 2) Fallback: cualquier estado que no sea 6/7/8/9
    $sql = "SELECT id
            FROM ost_ticket_status
            WHERE id NOT IN ($notIn)
            ORDER BY id ASC
            LIMIT 1";
    $r = $conexion->query($sql);
    if ($r && ($row = $r->fetch_assoc()))
        return (int)$row['id'];

    return 0;
}


/**
 * Resolver valores de listas desplegables (pueden estar como ID, JSON o texto)
 * @param mixed $valor Valor a resolver
 * @param array $mapa_items Mapa de IDs a valores
 * @return string Valor resuelto
 */
function resolver_valor_lista(mixed $valor, array $mapa_items): string {
    $valor = trim((string)$valor);
    if ($valor === '' || strtolower($valor) === 'false') return '';
    
    // Intentar decodificar JSON
    if (str_starts_with($valor, '{') || str_starts_with($valor, '[')) {
        $json = json_decode($valor, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json) && !empty($json)) {
            $first = reset($json);
            if (is_array($first)) {
                $first = reset($first);
            }
            return trim((string)$first);
        }
    }
    
    // Si es un ID numérico o "123,456" (formato osTicket de listas desplegables), buscar en el mapa
    // Se extrae el primer segmento antes de la coma para manejar ambos formatos
    $primerPart = trim(explode(',', $valor)[0]);
    if (preg_match('/^\d+$/', $primerPart)) {
        $id = (int)$primerPart;
        if ($id > 0 && isset($mapa_items[$id])) {
            return trim((string)$mapa_items[$id]);
        }
        // Si era un entero puro y no está en el mapa, devolver el valor original
        if ($primerPart === $valor) return $valor;
    }
    
    return $valor;
}

/**
 * Limpiar HTML de osTicket y convertirlo a texto plano
 * @param mixed $valor Valor con posible HTML
 * @return string Texto limpio
 */
function limpiar_html_osticket(mixed $valor): string {
    $valor = trim((string)$valor);
    if ($valor === '' || strtolower($valor) === 'false') return '';
    if ($valor === strip_tags($valor)) return $valor;
    
    // Convertir etiquetas HTML a saltos de línea
    $valor = preg_replace('/<br\s*\/?>/i', "\n", $valor);
    $valor = preg_replace('/<\/p>/i', "\n", $valor);
    $valor = preg_replace('/<li[^>]*>/i', "\n- ", $valor);
    
    // Eliminar todas las etiquetas HTML
    $valor = strip_tags($valor);
    
    // Decodificar entidades HTML
    $valor = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Normalizar saltos de línea múltiples
    $valor = preg_replace('/\n{3,}/', "\n\n", $valor);
    
    return trim($valor);
}

/**
 * Normalizar espacios en blanco múltiples
 * @param mixed $valor Texto a normalizar
 * @return string Texto normalizado
 */
function normalizar_texto(mixed $valor): string {
    $valor = trim((string)$valor);
    return preg_replace('/\s+/', ' ', $valor);
}

/**
 * Verificar si un texto contiene alguna de las palabras dadas
 * @param string $texto Texto donde buscar
 * @param array $palabras Array de palabras a buscar
 * @return bool True si encuentra alguna palabra
 */
function contiene_palabra(string $texto, array $palabras): bool {
    $texto = mb_strtolower(normalizar_texto($texto), 'UTF-8');
    foreach ($palabras as $p) {
        $p = mb_strtolower(normalizar_texto($p), 'UTF-8');
        if ($p !== '' && str_contains($texto, $p)) {
            return true;
        }
    }
    return false;
}

/**
 * Detectar en qué columna de sector debe ir según el plan
 * @param string $planTexto Nombre del plan
 * @return string Nombre de la columna de sector
 */
function detectar_columna_sector_por_plan(string $planTexto): string {
    $plan = mb_strtolower(normalizar_texto($planTexto), 'UTF-8');
    if ($plan === '') return '';
    
    if (contiene_palabra($plan, ['asturias'])) return 'sector_asturias';
    if (contiene_palabra($plan, ['castilla y león', 'castilla leon', 'cyl'])) return 'sector_cyl';
    if (contiene_palabra($plan, ['labora', 'comunidad valenciana', 'c. valenciana', 'valencia', 'valenciana'])) return 'sector_labora';
    if (contiene_palabra($plan, ['estatal'])) return 'sector_estatal';
    
    return '';
}

/**
 * Distribuir el sector principal en las columnas específicas según el plan
 * @param array &$fila Fila de datos (modificada por referencia)
 * @param array $mapa_items Mapa de IDs a valores
 */
function distribuir_sector_por_plan(array &$fila, array $mapa_items): void {
    $plan = resolver_valor_lista($fila['plan'] ?? '', $mapa_items);
    $sectorPrincipal = resolver_valor_lista($fila['sector'] ?? '', $mapa_items);
    
    $fila['plan'] = $plan;
    $fila['sector'] = $sectorPrincipal;
    
    // Resolver sectores específicos
    foreach (['sector_labora','sector_cyl','sector_asturias','sector_estatal'] as $c) {
        $fila[$c] = resolver_valor_lista($fila[$c] ?? '', $mapa_items);
    }
    
    // Verificar si ya hay sectores separados
    $haySectoresSeparados = false;
    foreach (['sector_labora','sector_cyl','sector_asturias','sector_estatal'] as $c) {
        if (trim((string)($fila[$c] ?? '')) !== '' && strtolower(trim((string)$fila[$c])) !== 'false') {
            $haySectoresSeparados = true;
            break;
        }
    }
    
    // Si no hay sectores separados pero sí sector principal, distribuirlo según el plan
    if (!$haySectoresSeparados && $sectorPrincipal !== '') {
        $colDestino = detectar_columna_sector_por_plan($plan);
        if ($colDestino !== '') {
            $fila[$colDestino] = $sectorPrincipal;
        }
    }
}

/**
 * Procesar una fila de datos: resolver listas, limpiar HTML y distribuir sectores
 * @param array &$fila Fila de datos (modificada por referencia)
 * @param array $mapa_items Mapa de IDs a valores
 * @param array $campos_lista Campos que son listas desplegables
 * @param array $campos_html Campos que contienen HTML
 */

function procesar_fila_osticket(array &$fila, array $mapa_items, array $campos_lista, array $campos_html): void {
    // Resolver campos de tipo lista
    foreach ($campos_lista as $campo) {
        if (isset($fila[$campo]) && $fila[$campo] !== null) {
            $fila[$campo] = resolver_valor_lista($fila[$campo], $mapa_items);
        }
    }
    
    // Limpiar campos con HTML
    foreach ($campos_html as $campo) {
        if (isset($fila[$campo]) && $fila[$campo] !== null) {
            $fila[$campo] = limpiar_html_osticket($fila[$campo]);
        }
    }
    
    // Distribuir sectores según plan
    distribuir_sector_por_plan($fila, $mapa_items);
	
}

/**
 * Obtener parámetro GET como array limpio
 * @param string $key Nombre del parámetro
 * @return array Valores únicos y no vacíos
 */
function get_array_param(string $key): array {
    $value = $_GET[$key] ?? [];
    if (!is_array($value)) $value = [$value];
    $out = [];
    foreach ($value as $v) {
        $v = trim((string)$v);
        if ($v !== '') $out[] = $v;
    }
    return array_values(array_unique($out));
}

/* =========================================================
   POST BORRAR TICKET
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_borrar_ticket'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conexion = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        $conexion->set_charset('utf8mb4');
        
        $tid = intval($_POST['ticket_id'] ?? 0);
        
        if ($tid <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Ticket inválido']);
            exit;
        }
        
        $conexion->begin_transaction();
        
        // Borrar datos personalizados del ticket
        $stmt = $conexion->prepare("DELETE FROM ost_ticket__cdata WHERE ticket_id=?");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->close();
        
        // Borrar el ticket principal
        $stmt = $conexion->prepare("DELETE FROM ost_ticket WHERE ticket_id=?");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $stmt->close();
        
        $conexion->commit();
        $conexion->close();
        
        echo json_encode([
            'ok' => true,
            'msg' => 'Ticket borrado correctamente'
        ]);
        exit;
        
    } catch (Exception $e) {
        if (isset($conexion)) {
            try { $conexion->rollback(); } catch (Exception $x) {}
            $conexion->close();
        }
        echo json_encode([
            'ok' => false,
            'msg' => 'Error al borrar ticket: ' . $e->getMessage()
        ]);
        exit;
    }
}

function obtener_adjuntos_ticket_sql(mysqli $conexion, int $ticketId): array {

    // Thread del ticket
    $stmt = $conexion->prepare("SELECT id FROM ost_thread WHERE object_type='T' AND object_id=? LIMIT 1");
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $th = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$th || empty($th['id'])) return [];
    $threadId = (int)$th['id'];

    // Adjuntos del thread 
    $sql = "
        SELECT a.file_id, COALESCE(f.name, a.name) AS nombre
        FROM ost_thread_entry te
        JOIN ost_attachment a ON a.object_id = te.id
        LEFT JOIN ost_file f ON f.id = a.file_id
        WHERE te.thread_id = ?
          AND a.file_id IS NOT NULL AND a.file_id > 0
        ORDER BY a.id DESC
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $fid = (int)$r['file_id'];
        $nom = trim((string)($r['nombre'] ?? ''));
        if ($fid > 0) $out[(string)$fid] = ($nom !== '' ? $nom : ('archivo_'.$fid));
    }
    $stmt->close();

    return $out;
}


function sincronizarTicketsNuevos(mysqli $db): void {

    $sql = "
SELECT t.ticket_id
FROM ost_ticket t
LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id = t.ticket_id
WHERE t.topic_id = 14
  AND (
    cd.ticket_id IS NULL
    OR cd.plan IS NULL
    OR TRIM(cd.plan) = ''
    OR cd.subject IS NULL
  )
ORDER BY t.ticket_id DESC
LIMIT 25
";

    $res = $db->query($sql);

    while ($row = $res->fetch_assoc()) {

        $tid = (int)$row['ticket_id'];

        asegurar_cdata($db, $tid);
        sync_form_to_cdata($db, $tid, 22);
    }
}

function asegurar_cdata(mysqli $db, int $ticket_id): void {
    $sql = "INSERT IGNORE INTO ost_ticket__cdata (ticket_id) VALUES (?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Error prepare asegurar_cdata: " . $db->error);
        return;
    }
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $stmt->close();
}	
	
function sync_form_to_cdata(mysqli $db, int $ticket_id, int $form_id = 22): void {

    // 🔹 Obtener valores del formulario
    $sql = "
        SELECT ff.name, fev.value
        FROM ost_form_entry fe
        JOIN ost_form_entry_values fev ON fev.entry_id = fe.id
        JOIN ost_form_field ff ON ff.id = fev.field_id
        WHERE fe.object_id = ?
        AND fe.object_type = 'T'
        AND fe.form_id = ?
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Error prepare SELECT: " . $db->error);
        return;
    }

    $stmt->bind_param('ii', $ticket_id, $form_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $campos = [];
    while ($row = $res->fetch_assoc()) {
        $campos[$row['name']] = $row['value'];
    }
    $stmt->close();

    if (empty($campos)) {
        error_log("⚠️ No hay datos de formulario para ticket $ticket_id");
        return;
    }

    // 🔹 SOLO columnas válidas en ost_ticket__cdata (evita error 500)
    // Incluye nombres de campos del form 18 (incidencias) y form 22 (coordinadores)
    $columnas_validas = [
        // form 18 - incidencias
        'plan','sector','nombreAlu','apellidosAlu','tutor','empresa',
        'accion','grupo','curso','incidencia','datos','detalles',
        'dificultad','razon','motivo','archivo','expediente',
        // form 22 - coordinadores (nombres de variable en ost_form_field)
        'expedientes','planes','sectores','acciones','grupos','nombrescursos',
        'ndealumnos','nfinalizados','tutores','personasdeapoyo','equiposdedinamización',
        'dominiosdeloscontenidos','resoluciondedudas','calidadesdelseguimiento',
        'claridaddelosseguimientos','correccionyrevision','OBSERVACIONES',
        'atencionyacompañamiento','resoluciondeincidencias','rapidezyeficacia',
        'seguimientodelalumnado','realizaciondeacciones','correccion','observaciones2',
        'realizallamadas','alumnadoinactivo','llevaacaboacciones','registracorrectamente',
        'detectadeformatemprana','observaciones3','participaciongeneral',
        'satisfacciónpercibida','principalesdificultades','observaciones4',
        'perfilalumnado','sehandetectadoincidencias','describir','fueroncorrectas',
        'observaciones5','valoracionglobal','conclusionesgenerales','apectosdestacables',
        'aspectosprioritarios','necesidadesdetectadasenlat','existiocordinacion',
        'informaciones','informacion2','informacion3','informacion4',
        'informacion5','informacion6','informacion7',
    ];

    $sets = [];
    $types = '';
    $vals = [];

    foreach ($campos as $campo => $valor) {

        if (!in_array($campo, $columnas_validas)) {
            error_log("❌ Campo ignorado: $campo");
            continue;
        }

        $sets[] = "`$campo` = ?";
        $types .= 's';
        $vals[] = $valor;
    }

    if (empty($sets)) {
        error_log("⚠️ No hay columnas válidas para actualizar ticket $ticket_id");
        return;
    }

    // 🔹 UPDATE dinámico
    $sqlUpdate = "UPDATE ost_ticket__cdata SET " . implode(',', $sets) . " WHERE ticket_id = ?";
    $types .= 'i';
    $vals[] = $ticket_id;

    $stmt = $db->prepare($sqlUpdate);
    if (!$stmt) {
        error_log("Error prepare UPDATE: " . $db->error);
        return;
    }

    // 🔹 bind_param compatible con todos los PHP
    $refs = [];
    foreach ($vals as $key => $value) {
        $refs[$key] = &$vals[$key];
    }

    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
        error_log("Error execute UPDATE: " . $stmt->error);
    }

    $stmt->close();
}

/* =========================================================
   POST DUPLICAR TICKET
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_duplicar_ticket'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conexion = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        $conexion->set_charset('utf8mb4');
        
        $tid = intval($_POST['ticket_id'] ?? 0);
        
        if ($tid <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Ticket inválido']);
            exit;
        }
        
        $conexion->begin_transaction();
        
        /* =========================
           OBTENER TICKET ORIGINAL
        ========================= */
        $stmt = $conexion->prepare("SELECT * FROM ost_ticket WHERE ticket_id=? LIMIT 1");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $ticketOriginal = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$ticketOriginal) {
            throw new Exception('No se encontró el ticket original');
        }
        
        /* =========================
           OBTENER CDATA ORIGINAL
        ========================= */
        $stmt = $conexion->prepare("SELECT * FROM ost_ticket__cdata WHERE ticket_id=? LIMIT 1");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
       $cdataOriginal = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        
        // Generar número único para el ticket duplicado
        $nuevoNumero = 'DUP-' . date('YmdHis') . '-' . mt_rand(100, 999);
        $ahora = date('Y-m-d H:i:s');
        
        /* =========================
           CREAR NUEVO TICKET
        ========================= */
        // Preparar variables del ticket original
        $ticket_pid = 0;
        $number = $nuevoNumero;
        $user_id = (int)($ticketOriginal['user_id'] ?? 0);
        $user_email_id = (int)($ticketOriginal['user_email_id'] ?? 0);
        $status_id = (int)($ticketOriginal['status_id'] ?? 0);
        $dept_id = (int)($ticketOriginal['dept_id'] ?? 0);
        $sla_id = (int)($ticketOriginal['sla_id'] ?? 0);
        $topic_id = (int)($ticketOriginal['topic_id'] ?? 0);
        $staff_id = (int)($ticketOriginal['staff_id'] ?? 0);
        $team_id = (int)($ticketOriginal['team_id'] ?? 0);
        $email_id = (int)($ticketOriginal['email_id'] ?? 0);
        $lock_id = (int)($ticketOriginal['lock_id'] ?? 0);
        $flags = (int)($ticketOriginal['flags'] ?? 0);
        $sort = (int)($ticketOriginal['sort'] ?? 0);
        $ip_address = (string)($ticketOriginal['ip_address'] ?? '');
        $source = (string)($ticketOriginal['source'] ?? 'Other');
        $source_extra = isset($ticketOriginal['source_extra']) ? (string)$ticketOriginal['source_extra'] : null;
        $isoverdue = (int)($ticketOriginal['isoverdue'] ?? 0);
        $isanswered = (int)($ticketOriginal['isanswered'] ?? 0);
        $duedate = !empty($ticketOriginal['duedate']) ? $ticketOriginal['duedate'] : null;
        $est_duedate = !empty($ticketOriginal['est_duedate']) ? $ticketOriginal['est_duedate'] : null;
        $reopened = !empty($ticketOriginal['reopened']) ? $ticketOriginal['reopened'] : null;
        $closed = null;
        $lastupdate = $ahora;
        $created = $ahora;
        $updated = $ahora;
        
        // SQL para insertar el nuevo ticket
        $sql = "
        INSERT INTO ost_ticket (
            ticket_pid,
            number,
            user_id,
            user_email_id,
            status_id,
            dept_id,
            sla_id,
            topic_id,
            staff_id,
            team_id,
            email_id,
            lock_id,
            flags,
            sort,
            ip_address,
            source,
            source_extra,
            isoverdue,
            isanswered,
            duedate,
            est_duedate,
            reopened,
            closed,
            lastupdate,
            created,
            updated
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
        ";
        
        $stmt = $conexion->prepare($sql);
        $types = 'isiiiiiiiiiiisssiissssssss';
        $stmt->bind_param(
            $types,
            $ticket_pid,
            $number,
            $user_id,
            $user_email_id,
            $status_id,
            $dept_id,
            $sla_id,
            $topic_id,
            $staff_id,
            $team_id,
            $email_id,
            $lock_id,
            $flags,
            $sort,
            $ip_address,
            $source,
            $source_extra,
            $isoverdue,
            $isanswered,
            $duedate,
            $est_duedate,
            $reopened,
            $closed,
            $lastupdate,
            $created,
            $updated
        );
        $stmt->execute();
        $nuevoTid = $conexion->insert_id;
        $stmt->close();
        
        if ($nuevoTid <= 0) {
            throw new Exception('No se pudo crear el nuevo ticket');
        }
        
        /* =========================
           CREAR NUEVO CDATA
        ========================= */
        $sqlCdata = "
        INSERT INTO ost_ticket__cdata (
            ticket_id,
            subject,
            plan,
            sector,
            priority,
            nombreAlu,
            apellidosAlu,
            tutor,
            empresa,
            accion,
            grupo,
            curso,
            incidencia,
            detalles,
            razon,
            dificultad,
            datos,
            motivo,
            Archivo,
            medidas,
            solucion_manual,
            fecha_solucion_manual,
            sector_labora,
            sector_cyl,
            sector_asturias,
            sector_estatal,
            fotos,
            estado_texto
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
        ";
        
        $stmt = $conexion->prepare($sqlCdata);
        
        // Preparar variables del cdata original (con valores por defecto seguros)
        $subject = isset($cdataOriginal['subject']) ? (string)$cdataOriginal['subject'] : '';
        $plan = isset($cdataOriginal['plan']) ? (string)$cdataOriginal['plan'] : '';
        $sector = isset($cdataOriginal['sector']) ? (string)$cdataOriginal['sector'] : '';
        $priority = isset($cdataOriginal['priority']) ? (string)$cdataOriginal['priority'] : '';
        $nombreAlu = isset($cdataOriginal['nombreAlu']) ? (string)$cdataOriginal['nombreAlu'] : '';
        $apellidosAlu = isset($cdataOriginal['apellidosAlu']) ? (string)$cdataOriginal['apellidosAlu'] : '';
        $tutor = isset($cdataOriginal['tutor']) ? (string)$cdataOriginal['tutor'] : '';
        $empresa = isset($cdataOriginal['empresa']) ? (string)$cdataOriginal['empresa'] : '';
        $accion = isset($cdataOriginal['accion']) ? (string)$cdataOriginal['accion'] : '';
        $grupo = isset($cdataOriginal['grupo']) ? (string)$cdataOriginal['grupo'] : '';
        $curso = isset($cdataOriginal['curso']) ? (string)$cdataOriginal['curso'] : '';
        $incidencia = isset($cdataOriginal['incidencia']) ? (string)$cdataOriginal['incidencia'] : '';
        $detalles = isset($cdataOriginal['detalles']) ? (string)$cdataOriginal['detalles'] : '';
        $razon = isset($cdataOriginal['razon']) ? (string)$cdataOriginal['razon'] : '';
        $dificultad = isset($cdataOriginal['dificultad']) ? (string)$cdataOriginal['dificultad'] : '';
        $datos = isset($cdataOriginal['datos']) ? (string)$cdataOriginal['datos'] : '';
        $motivo = isset($cdataOriginal['motivo']) ? (string)$cdataOriginal['motivo'] : '';
        $Archivo = (string)($cdataOriginal['Archivo'] ?? $cdataOriginal['archivo'] ?? '');
        $medidas = isset($cdataOriginal['medidas']) ? (string)$cdataOriginal['medidas'] : '';
        $solucion_manual = isset($cdataOriginal['solucion_manual']) ? (string)$cdataOriginal['solucion_manual'] : '';
        $fecha_solucion_manual = !empty($cdataOriginal['fecha_solucion_manual']) ? $cdataOriginal['fecha_solucion_manual'] : null;
        $sector_labora = isset($cdataOriginal['sector_labora']) ? (string)$cdataOriginal['sector_labora'] : '';
        $sector_cyl = isset($cdataOriginal['sector_cyl']) ? (string)$cdataOriginal['sector_cyl'] : '';
        $sector_asturias = isset($cdataOriginal['sector_asturias']) ? (string)$cdataOriginal['sector_asturias'] : '';
        $sector_estatal = isset($cdataOriginal['sector_estatal']) ? (string)$cdataOriginal['sector_estatal'] : '';
        $fotos = isset($cdataOriginal['fotos']) ? (string)$cdataOriginal['fotos'] : '';
        $estado_texto = isset($cdataOriginal['estado_texto']) ? (string)$cdataOriginal['estado_texto'] : '';
		$vf = trim((string)$fotos);
$va = trim((string)$Archivo);

if (($vf === '' || strtolower($vf) === 'false' || strtolower($vf) === 'null')
    && ($va !== '' && strtolower($va) !== 'false' && strtolower($va) !== 'null')) {
    // si Archivo trae JSON tipo {"5":"...png"}, lo replicamos en fotos
    $fotos = $Archivo;
}
        
		// Si no hay archivo guardado en cdata, copiar adjuntos del thread del ticket original por SQL
$valFotos = trim((string)$fotos);
$valArch  = trim((string)$Archivo);

$esVacio = function($v) {
    $v = trim((string)$v);
    return ($v === '' || strtolower($v) === 'false' || strtolower($v) === 'null');
};

if ($esVacio($fotos) && $esVacio($Archivo)) {
    $adj = obtener_adjuntos_ticket_sql($conexion, $tid);
    if (!empty($adj)) {
        $fotos = json_encode($adj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
		
		// Si no hay fotos/Archivo en cdata, intentar sacar adjuntos del thread del ticket original
if (
    (trim((string)$fotos) === '' || strtolower(trim((string)$fotos)) === 'false')
    && (trim((string)$Archivo) === '' || strtolower(trim((string)$Archivo)) === 'false')
    && $_TICKET_API_AVAILABLE
) {
    try {
        $ticketOrig = Ticket::lookup($tid); // $tid = ticket original
        if ($ticketOrig) {
            $thread = $ticketOrig->getThread();
            $archivos = [];

            if ($thread) {
                foreach ($thread->getEntries() as $entry) {
                    $atts = $entry->getAttachments();
                    if ($atts) foreach ($atts as $att) {
                        if (isset($att->file) && $att->file) {
                            $file = $att->file;
                            $fid = (int)$file->getId();
                            if ($fid > 0) {
                                $archivos[(string)$fid] = $file->getName();
                            }
                        }
                    }
                }
            }

            // Guardar en cd.fotos como JSON { "fileId":"nombre" }
            if (!empty($archivos)) {
                $fotos = json_encode($archivos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
    } catch (Throwable $e) {
        error_log('No se pudieron copiar adjuntos del ticket original: ' . $e->getMessage());
    }
}
		
        $stmt->bind_param(
            'isssssssssssssssssssssssssss',
            $nuevoTid,
            $subject,
            $plan,
            $sector,
            $priority,
            $nombreAlu,
            $apellidosAlu,
            $tutor,
            $empresa,
            $accion,
            $grupo,
            $curso,
            $incidencia,
            $detalles,
            $razon,
            $dificultad,
            $datos,
            $motivo,
            $Archivo,
            $medidas,
            $solucion_manual,
            $fecha_solucion_manual,
            $sector_labora,
            $sector_cyl,
            $sector_asturias,
            $sector_estatal,
            $fotos,
            $estado_texto
        );
        $stmt->execute();
        $stmt->close();
        

// ============================================================
// CREAR ENTRY PARA FORMULARIO PERSONALIZADO (ID=18)
// ============================================================
$sql_entry_custom = "INSERT INTO ost_form_entry (form_id, object_id, object_type, created, updated)
                     VALUES (22, ?, 'T', NOW(), NOW())";
$stmt = $conexion->prepare($sql_entry_custom);
$stmt->bind_param('i', $nuevoTid);
$stmt->execute();
$nuevo_entry_custom_id = $conexion->insert_id;
$stmt->close();
//  SINCRONIZAR FORMULARIO → CDATA
asegurar_cdata($conexion, $nuevoTid);
sync_form_to_cdata($conexion, $nuevoTid, 22);
// Copiar valores del formulario personalizado del ticket original
if ($nuevo_entry_custom_id > 0) {
    $sql_copy_custom = "INSERT INTO ost_form_entry_values (entry_id, field_id, value)
                        SELECT ?, fev.field_id, fev.value
                        FROM ost_form_entry fe
                        JOIN ost_form_entry_values fev ON fev.entry_id = fe.id
                        WHERE fe.object_id = ?
                        AND fe.object_type = 'T'
                        AND fe.form_id = 22";
    $stmt = $conexion->prepare($sql_copy_custom);
    $stmt->bind_param('ii', $nuevo_entry_custom_id, $tid);
    $stmt->execute();
    $stmt->close();
}


        // Sincronizar con osTicket si está disponible
        if ($_TICKET_API_AVAILABLE) {
            try {
                $nuevoTicket = Ticket::lookup($nuevoTid);
                if ($nuevoTicket) {
                    $nuevoTicket->subject = $subject;
                    if (method_exists($nuevoTicket, 'save')) {
                        $nuevoTicket->save();
                    }
                }
            } catch (Exception $e) {
                error_log('Nota: No se pudo sincronizar ticket duplicado en osTicket: ' . $e->getMessage());
            }
        }
        
        $conexion->commit();
        $conexion->close();
        
        echo json_encode([
            'ok' => true,
            'msg' => 'Ticket duplicado correctamente',
            'nuevo_ticket_id' => $nuevoTid,
            'nuevo_numero' => $nuevoNumero
        ]);
        exit;
        
    } catch (Exception $e) {
        if (isset($conexion)) {
            try { $conexion->rollback(); } catch (Exception $x) {}
            $conexion->close();
        }
        echo json_encode([
            'ok' => false,
            'msg' => 'Error al duplicar ticket: ' . $e->getMessage()
        ]);
        exit;
    }
}

/* =========================================================
   FUNCIÓN PARA ACTUALIZAR CAMPO EN osTicket CORRECTAMENTE
========================================================= */

function actualizarCampoOsTicket(mysqli $conexion, int $ticket_id, string $campo_nombre, mixed $nuevo_valor): bool {

    $form_id = 22;

    // =========================
    // NORMALIZAR VALOR
    // =========================
    if ($campo_nombre === 'fecha_solucion_manual') {
        $v = trim((string)$nuevo_valor);

        if ($v === '') {
            $nuevo_valor = null;
        } else {
            if (strlen($v) === 10) {
                $v .= ' 00:00:00';
            }
            $nuevo_valor = $v;
        }
    } else {
        $nuevo_valor = trim((string)$nuevo_valor);
    }

    $campo_safe = str_replace('`', '', $campo_nombre);

    // =========================
    // 1) ACTUALIZAR CDATA (solo si la columna existe en la tabla)
    // =========================
    try {
        $sql = "UPDATE ost_ticket__cdata SET `$campo_safe` = ? WHERE ticket_id = ?";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $nuevo_valor, $ticket_id);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // La columna puede no existir en cdata para el form 22 — continuar igualmente
        error_log("cdata update skipped for $campo_safe: " . $e->getMessage());
    }

    // =========================
    // 2) BUSCAR / CREAR ENTRY
    // =========================
    $sql = "SELECT id FROM ost_form_entry 
            WHERE object_id=? 
            AND object_type='T'
            AND form_id=?
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('ii', $ticket_id, $form_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $entry_id = (int)$row['id'];
    } else {
        $sql = "INSERT INTO ost_form_entry
                (form_id, object_id, object_type, created, updated)
                VALUES (?, ?, 'T', NOW(), NOW())";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('ii', $form_id, $ticket_id);
        $stmt->execute();
        $entry_id = $conexion->insert_id;
        $stmt->close();

        if ($entry_id <= 0) {
            return true;
        }
    }

    // =========================
    // 3) FIELD ID
    // =========================
    $sql = "SELECT id 
            FROM ost_form_field
            WHERE name=? AND form_id=?
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('si', $campo_nombre, $form_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return true;
    }

    $field_id = (int)$row['id'];

    // =========================
    // 4) UPSERT DIRECTO
    // =========================
    $sql = "
        INSERT INTO ost_form_entry_values (entry_id, field_id, value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE value=VALUES(value)
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('iis', $entry_id, $field_id, $nuevo_valor);
    $stmt->execute();
    $stmt->close();

    return true;
}

/* =========================================================
   POST GUARDAR CAMPOS 
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_guardar'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $resultado_ok = false;
    $resultado_msg = '';
    
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conexion = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        $conexion->set_charset('utf8mb4');
        
        $tid = intval($_POST['ticket_id'] ?? 0);
        $campo = trim($_POST['campo'] ?? '');
        $valor = $_POST['valor'] ?? '';
        
        if ($tid <= 0) {
            throw new Exception('Ticket inválido');
        }
        
        // CASO 1: ESTADO DEL TICKET
        if ($campo === 'estado_ticket') {
    $valorTrim = trim((string)$valor);

    // Si viene vacío => “Sin asignar”
    if ($valorTrim === '') {
        $status_id = obtener_status_id_sin_asignar($conexion);
        if ($status_id <= 0) {
            $conexion->close();
            echo json_encode(['ok' => false, 'msg' => 'No se encontró un status OPEN válido para “Sin asignar”']);
            exit;
        }
    } else {
        $status_id = (int)$valorTrim;
    }

    // Actualizar en ost_ticket
    $stmt = $conexion->prepare("UPDATE ost_ticket SET status_id=? WHERE ticket_id=?");
    $stmt->bind_param('ii', $status_id, $tid);
    $resultado_ok = $stmt->execute();
    $stmt->close();

    // Sincronizar con osTicket si está disponible
    if ($resultado_ok && $_TICKET_API_AVAILABLE) {
        try {
            $ticket = Ticket::lookup($tid);
            if ($ticket && method_exists($ticket, 'setStatus')) {
                $ticket->setStatus($status_id);
            }
        } catch (Exception $e) {
            error_log('Nota: No se pudo sincronizar estado en osTicket: ' . $e->getMessage());
        }
    }

    $conexion->close();
    echo json_encode(['ok' => $resultado_ok]);
    exit;
}
        
        // CASO 2: FECHA DE CREACIÓN
        if ($campo === 'fecha_creacion') {
            $valor = trim($valor);
            $v = strlen($valor) === 10 ? $valor . ' 00:00:00' : $valor;
            $stmt = $conexion->prepare("UPDATE ost_ticket SET created=? WHERE ticket_id=?");
            $stmt->bind_param('si', $v, $tid);
            $resultado_ok = $stmt->execute();
            $stmt->close();
            
            if ($resultado_ok && $_TICKET_API_AVAILABLE) {
                try {
                    $ticket = Ticket::lookup($tid);
                    if ($ticket) {
                        $ticket->created = $v;
                        if (method_exists($ticket, 'save')) {
                            $ticket->save();
                        }
                    }
                } catch (Exception $e) {
                    error_log('Nota: No se pudo sincronizar fecha en osTicket: ' . $e->getMessage());
                }
            }
            
            $conexion->close();
            echo json_encode(['ok' => $resultado_ok]);
            exit;
        }
        
        // CASO 3: OTROS CAMPOS (en formularios de osTicket)
        // Campos fijos que no están en ost_form_field pero son válidos
        $permitidos_fijos = [
            'subject','plan','sector',
            'sector_labora','sector_cyl','sector_asturias','sector_estatal',
            'tutor','nombreAlu','apellidosAlu','empresa','accion','grupo',
            'curso','incidencia','detalles','razon','dificultad','datos',
            'motivo','fotos','medidas','solucion_manual','fecha_solucion_manual',
            'estado_texto','priority','Archivo'
        ];

        // Validación dinámica: acepta cualquier campo que exista en ost_form_field
        // Esto cubre automáticamente todos los campos del form 22 y cualquier campo futuro
        $campoSafe = str_replace('`', '', $campo);
        $stmtCheck = $conexion->prepare(
            "SELECT id FROM ost_form_field WHERE name = ? LIMIT 1"
        );
        $stmtCheck->bind_param('s', $campoSafe);
        $stmtCheck->execute();
        $existeCampo = (bool)$stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if (!$existeCampo && !in_array($campo, $permitidos_fijos, true)) {
            $conexion->close();
            echo json_encode([
                'ok' => false,
                'msg' => "Campo '$campo' no permitido"
            ]);
            exit;
        }
        
        // Actualizar en osTicket usando la función helper
        $resultado_ok = actualizarCampoOsTicket($conexion, $tid, $campo, $valor);
        
        $conexion->close();
        
        if ($resultado_ok) {
            echo json_encode([
                'ok' => true,
                'msg' => 'Campo actualizado correctamente'
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'msg' => "No se pudo actualizar '$campo'. Revisa los logs del servidor."
            ]);
        }
        exit;
        
    } catch (Exception $e) {
        if (isset($conexion)) {
            $conexion->close();
        }
        echo json_encode([
            'ok' => false,
            'msg' => 'Error al guardar campo: ' . $e->getMessage()
        ]);
        exit;
    }
}

/* =========================================================
   CONEXIÓN A BASE DE DATOS
========================================================= */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexion = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
    $conexion->set_charset('utf8mb4');

    // Sync deshabilitado en coordinadores.php — los campos del form 22
    // pueden no existir en ost_ticket__cdata, así que se leen directo del formulario.

} catch (Exception $e) {
    die('❌ Error BD: ' . htmlspecialchars($e->getMessage()));
}
// Cargar mapa de IDs a valores desde las tablas de listas
$mapa_lista_items = cargar_mapa_lista_items($conexion);

/* =======================================================
   ENDPOINT AJAX: SECTORES PARA FILA (al cambiar plan en tabla)
   Devuelve las opciones de cada columna de sector según el plan.
======================================================= */
if (isset($_GET['ajax_sectores_fila'])) {
    header('Content-Type: application/json; charset=utf-8');
    $planVal = trim($_GET['plan'] ?? '');
    echo json_encode([
        'ok'              => true,
        'sector_labora'   => obtener_opciones_sector_por_plan($conexion, $planVal, 'sector_labora'),
        'sector_cyl'      => obtener_opciones_sector_por_plan($conexion, $planVal, 'sector_cyl'),
        'sector_asturias' => obtener_opciones_sector_por_plan($conexion, $planVal, 'sector_asturias'),
        'sector_estatal'  => obtener_opciones_sector_por_plan($conexion, $planVal, 'sector_estatal'),
    ]);
    exit;
}

/* =======================================================
   ENDPOINT AJAX: CONTADORES HERO
   Devuelve total, hoy y 7días de tickets del form 22.
======================================================= */
if (isset($_GET['ajax_hero_counters'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $baseWhere = "EXISTS (
            SELECT 1 FROM ost_form_entry fe_h
            WHERE fe_h.object_id = t.ticket_id
            AND fe_h.object_type = 'T'
            AND fe_h.form_id = 22
        )";
        $rTotal = $conexion->query("SELECT COUNT(DISTINCT ticket_id) AS total FROM ost_ticket t WHERE $baseWhere");
        $total  = (int)($rTotal->fetch_assoc()['total'] ?? 0);

        $rHoy = $conexion->query("
            SELECT
                SUM(DATE(t.created) = CURDATE()) AS hoy,
                SUM(t.created >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS siete
            FROM ost_ticket t WHERE $baseWhere
        ");
        $row = $rHoy->fetch_assoc();
        echo json_encode(['ok'=>true, 'total'=>$total, 'hoy'=>(int)($row['hoy']??0), 'siete'=>(int)($row['siete']??0)]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'total'=>0,'hoy'=>0,'siete'=>0]);
    }
    exit;
}

/* =======================================================
   NUEVO ENDPOINT AJAX PARA FILTROS DEPENDIENTES
======================================================= */
if (isset($_GET['ajax_filtro_opciones'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // La variable $conexion ya existe en este punto del script. La reutilizamos.
    global $conexion; 

    $campo_solicitado = $_GET['campo'] ?? '';
    $filtros_aplicados = [];
    if (!empty($_GET['filter_plan'])) {
        $filtros_aplicados['plan'] = $_GET['filter_plan'];
    }
    
    $opciones = [];
    if ($campo_solicitado === 'sector') {
        // Usamos la nueva función dependiente
        $opciones = obtener_opciones_sector_dependiente($conexion, $filtros_aplicados['plan'] ?? []);
    }
    
    echo json_encode(['ok' => true, 'opciones' => $opciones]);
    exit;
}


// Definir qué campos son de tipo lista desplegable
$campos_tipo_lista = [
    'plan','sector',
    'sector_labora','sector_cyl','sector_asturias','sector_estatal',
    'tutor','empresa','incidencia',
    'detalles','razon','dificultad','datos'
];

// Definir qué campos contienen HTML
$campos_tipo_html = [
    'motivo','medidas','solucion_manual'
];

/* =========================================================
   ESTADOS (mapeo de IDs de osTicket a estados personalizados)
========================================================= */
$MAPA_FIJO = [6 => 'iniciada', 7 => 'en_curso', 8 => 'cerrada', 9 => 'enviada'];
$estados_arr = [];
$mapa_estados = [];

try {
    $q = $conexion->query("SELECT id,name,state FROM ost_ticket_status ORDER BY id");
    while ($r = $q->fetch_assoc()) {
        $r['estado_real'] = $MAPA_FIJO[$r['id']] ?? 'otro';
        $estados_arr[] = $r;
        $mapa_estados[$r['id']] = $r['estado_real'];
    }
} catch (Exception $e) {}

/* =========================================================
   COLUMNAS CDATA (verificar qué campos existen en la tabla)
========================================================= */
$cdata_cols = [];
try {
    $res = $conexion->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ost_ticket__cdata'");
    while ($row = $res->fetch_assoc()) {
        $cdata_cols[] = strtolower($row['COLUMN_NAME']);
    }
} catch (Exception $e) {}

// =========================================================
// CARGAR CAMPOS DINÁMICAMENTE DESDE FORMULARIO ID=22
// =========================================================
$campos_form22 = [];
try {
    $res22 = $conexion->query(
        "SELECT name, label, type FROM ost_form_field WHERE form_id=22 AND name!='' ORDER BY sort ASC"
    );
    while ($row22 = $res22->fetch_assoc()) {
        $n = trim($row22['name']);
        $t = trim($row22['type'] ?: 'text');
        // Excluir campos de tipo 'info': solo muestran texto estático, no tienen respuesta
        if ($n !== '' && $t !== 'info') {
            $campos_form22[] = [
                'name'  => $n,
                'label' => trim($row22['label'] ?: $n),
                'type'  => $t,
            ];
        }
    }
} catch (Exception $e) {}

$nombres_form22 = array_column($campos_form22, 'name');
$campos_fijos = ['subject','fecha_solucion_manual','estado_texto','priority'];
$campos = $nombres_form22;
foreach ($campos_fijos as $cf) {
    if (!in_array($cf, $campos)) $campos[] = $cf;
}

// Construir SELECT dinámico desde formulario personalizado (form_id=22)
// Solo lee del formulario - no hace COALESCE con cdata para evitar errores de columna
$selects = [];
foreach ($campos as $c) {
    $cSafe = addslashes($c);
    $selects[] = "(SELECT fev.value 
         FROM ost_form_entry fe_custom
         JOIN ost_form_entry_values fev ON fev.entry_id = fe_custom.id
         JOIN ost_form_field ff ON ff.id = fev.field_id
         WHERE fe_custom.object_id = t.ticket_id 
         AND fe_custom.object_type = 'T'
         AND fe_custom.form_id = 22
         AND ff.name = '{$cSafe}'
         LIMIT 1) AS `{$cSafe}`";
}
// Añadir campo subject desde cdata como fallback
$cd_sql = implode(', ', $selects);
if (empty($selects)) {
    // Si no hay campos en form 22, usar un literal para no romper el SELECT
    $cd_sql = "NULL AS _placeholder";
}

/* =========================================================
   FILTROS (funciones auxiliares)
========================================================= */

/**
 * Verificar si un valor es inválido para filtros desplegables
 */
function es_valor_invalido_desplegable(mixed $valor, string $campo = ''): bool {
    $valor = normalizar_texto($valor);
    if ($valor === '' || strtolower($valor) === 'false') return true;
    return false;
}

/**
 * Agregar opción única al array de opciones
 */
function agregar_opcion_unica(array &$unicos, mixed $valor): void {
    $valor = normalizar_texto($valor);
    if ($valor === '' || strtolower($valor) === 'false') return;
    $clave = mb_strtolower($valor, 'UTF-8');
    if (!isset($unicos[$clave])) $unicos[$clave] = $valor;
}


/**
 * Obtener opciones disponibles para un filtro desplegable
 */
function obtener_opciones_filtro(mysqli $conexion, string $campo, string $tablaAlias = 'cd'): array {

    $opciones = [];
    $unicos = [];

    $campoPermitido = [
 'subject','plan',
 'sector_labora','sector_cyl','sector_asturias','sector_estatal',
 'tutor','nombreAlu','apellidosAlu','empresa',
 'accion','grupo','curso','incidencia',
 'detalles','razon','dificultad','datos'
];

    if (!in_array($campo, $campoPermitido, true)) {
        return [];
    }

// ==========================================================
// Campos de texto libre (NO listas osTicket)
// ==========================================================
$camposTextoLibre = [
    'curso',
    'grupo',
    'accion',
    'nombreAlu',
    'apellidosAlu',
    'subject'
];

if (in_array($campo, $camposTextoLibre, true)) {
    try {
        $campoSafe = str_replace('`', '', $campo);
        $sql = "SELECT DISTINCT `{$campoSafe}`
                FROM ost_ticket__cdata
                WHERE `{$campoSafe}` IS NOT NULL
                AND TRIM(`{$campoSafe}`) != ''
                ORDER BY `{$campoSafe}` ASC";
        $res = $conexion->query($sql);
        while ($row = $res->fetch_assoc()) {
            $valor = trim((string)$row[$campo]);
            if ($valor !== '') {
                $unicos[$valor] = $valor;
            }
        }
    } catch (Exception $e) {}
    return array_values($unicos);
}
	
    try {
        $sqlField = "SELECT type
                     FROM ost_form_field
                     WHERE name = ?
                     LIMIT 1";

        $stmt = $conexion->prepare($sqlField);
        $stmt->bind_param('s', $campo);
        $stmt->execute();
        $field = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($field && preg_match('/^list-(\d+)$/', $field['type'], $m)) {

            $listId = (int)$m[1];

            $sqlOpciones = "SELECT value
                            FROM ost_list_items
                            WHERE list_id = ?
                            AND value IS NOT NULL
                            AND TRIM(value) != ''
                            ORDER BY sort ASC";

            $stmt = $conexion->prepare($sqlOpciones);
            $stmt->bind_param('i', $listId);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $valor = trim((string)$row['value']);
                if ($valor !== '') {
                    $unicos[$valor] = $valor;
                }
            }

            $stmt->close();
        }

    } catch (Exception $e) {}

    return array_values($unicos);
}


function obtener_opciones_sector_por_plan(mysqli $conexion, string $planValor, string $columna = ''): array {

    $mapa_lista_items = cargar_mapa_lista_items($conexion);

    // Resolver el plan: puede venir como ID numérico desde cdata
    $planResuelto = resolver_valor_lista($planValor, $mapa_lista_items);

    // Detectar a qué columna de sector corresponde este plan
    $planDetectado = detectar_columna_sector_por_plan($planResuelto);

    // Si el plan no tiene region asociada (ej. Bonificada), no mostrar sectores
    if ($planDetectado === '') {
        return [];
    }

    // Si se pide una columna concreta y el plan no corresponde a ella, devolver vacio
    if ($columna !== '' && $planDetectado !== $columna) {
        return [];
    }

    // Columna a usar: la pedida explicitamente o la detectada por plan
    $col = ($columna !== '') ? $columna : $planDetectado;

    // Columnas permitidas para evitar inyeccion SQL
    $cols_permitidas = ['sector_labora', 'sector_cyl', 'sector_asturias', 'sector_estatal'];
    if (!in_array($col, $cols_permitidas, true)) {
        return [];
    }

    $unicos = [];

    // 1) Valores ya guardados en la columna especifica (sector_labora, sector_cyl, etc.)
    $sql = "SELECT DISTINCT `$col` AS val
            FROM ost_ticket__cdata
            WHERE `$col` IS NOT NULL AND TRIM(`$col`) != '' AND LOWER(TRIM(`$col`)) != 'false'";
    $res = $conexion->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $v = resolver_valor_lista(trim($row['val']), $mapa_lista_items);
            if ($v !== '') $unicos[$v] = $v;
        }
    }

    // 2) Valores en la columna 'sector' antigua cuyo plan corresponde a $col
    $sql2 = "SELECT plan, sector FROM ost_ticket__cdata
             WHERE sector IS NOT NULL AND TRIM(sector) != '' AND LOWER(TRIM(sector)) != 'false'";
    $res2 = $conexion->query($sql2);
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $planFila   = resolver_valor_lista($row['plan'] ?? '', $mapa_lista_items);
            $sectorFila = resolver_valor_lista($row['sector'] ?? '', $mapa_lista_items);
            if ($sectorFila === '') continue;
            if (detectar_columna_sector_por_plan($planFila) === $col) {
                $unicos[$sectorFila] = $sectorFila;
            }
        }
    }

    natcasesort($unicos);
    return array_values($unicos);
}


/**
 * Obtener opciones únicas de todos los campos de sector
 */
function obtener_opciones_sector_unificado(mysqli $conexion): array {
    global $mapa_lista_items;
    $opciones = [];
    $unicos = [];
    try {
        $sql = "SELECT sector, plan, sector_labora, sector_cyl, sector_asturias, sector_estatal
                FROM ost_ticket__cdata
                ORDER BY ticket_id DESC";
        $res = $conexion->query($sql);
        while ($row = $res->fetch_assoc()) {
            $tmp = [
                'plan' => $row['plan'] ?? '',
                'sector' => $row['sector'] ?? '',
                'sector_labora' => $row['sector_labora'] ?? '',
                'sector_cyl' => $row['sector_cyl'] ?? '',
                'sector_asturias' => $row['sector_asturias'] ?? '',
                'sector_estatal' => $row['sector_estatal'] ?? '',
            ];
            distribuir_sector_por_plan($tmp, $mapa_lista_items);
            agregar_opcion_unica($unicos, $tmp['sector_labora'] ?? '');
            agregar_opcion_unica($unicos, $tmp['sector_cyl'] ?? '');
            agregar_opcion_unica($unicos, $tmp['sector_asturias'] ?? '');
            agregar_opcion_unica($unicos, $tmp['sector_estatal'] ?? '');
        }
        natcasesort($unicos);
        $opciones = array_values($unicos);
    } catch (Exception $e) {}
    return $opciones;
}

function obtener_opciones_sector_por_tipo(mysqli $conexion, string $tipo): array {
    global $mapa_lista_items;

    $unicos = [];

    try {
        $sql = "SELECT plan, sector
                FROM ost_ticket__cdata
                WHERE sector IS NOT NULL
                AND TRIM(sector) != ''";

        $res = $conexion->query($sql);

        while ($row = $res->fetch_assoc()) {

            $plan = resolver_valor_lista($row['plan'] ?? '', $mapa_lista_items);
            $sector = resolver_valor_lista($row['sector'] ?? '', $mapa_lista_items);

            if ($sector === '') continue;

            $col = detectar_columna_sector_por_plan($plan);

            if ($tipo === 'sector_labora' && $col === 'sector_labora') {
                $unicos[$sector] = $sector;
            }

            if ($tipo === 'sector_cyl' && $col === 'sector_cyl') {
                $unicos[$sector] = $sector;
            }

            if ($tipo === 'sector_asturias' && $col === 'sector_asturias') {
                $unicos[$sector] = $sector;
            }

            if ($tipo === 'sector_estatal' && $col === 'sector_estatal') {
                $unicos[$sector] = $sector;
            }
        }

    } catch (Exception $e) {}

    natcasesort($unicos);
    return array_values($unicos);
}

/**
 * Obtener opciones para sector, opcionalmente filtrando por plan.
 */
function obtener_opciones_sector_dependiente(mysqli $conexion, array $filtros_plan = []) {
    $where_adicional = '';
    if (!empty($filtros_plan)) {
        $escaped_plans = array_map(function($p) use ($conexion) {
            return "'" . $conexion->real_escape_string($p) . "'";
        }, $filtros_plan);
        $plan_list = implode(',', $escaped_plans);
        
        // Construimos un WHERE para los planes seleccionados
        $where_adicional = " AND (
            (cd.plan REGEXP '^[0-9]+' AND li_plan.value IN ($plan_list))
            OR (cd.plan NOT REGEXP '^[0-9]+' AND cd.plan IN ($plan_list))
        )";
    }

    $unicos = [];
    $sql = "
        SELECT cd.plan, cd.sector, cd.sector_labora, cd.sector_cyl, cd.sector_asturias, cd.sector_estatal 
        FROM ost_ticket__cdata cd 
        LEFT JOIN ost_list_items li_plan ON cd.plan REGEXP '^[0-9]+' AND li_plan.id = CAST(SUBSTRING_INDEX(cd.plan, ',', 1) AS UNSIGNED)
        WHERE 1=1 $where_adicional
        ORDER BY cd.ticket_id DESC
    ";
    
    try {
        $res = $conexion->query($sql);
        global $mapa_lista_items;
        while ($row = $res->fetch_assoc()) {
            distribuir_sector_por_plan($row, $mapa_lista_items);
            agregar_opcion_unica($unicos, $row['sector_labora'] ?? '');
            agregar_opcion_unica($unicos, $row['sector_cyl'] ?? '');
            agregar_opcion_unica($unicos, $row['sector_asturias'] ?? '');
            agregar_opcion_unica($unicos, $row['sector_estatal'] ?? '');
        }
    } catch (Exception $e) {}
    
    natcasesort($unicos);
    return array_values($unicos);
}


/**
 * Renderizar select múltiple para filtros 
 */
function render_select_filtro_multi(string $name, string $label, array $selected, array $options, string $placeholder = '- Selecciona una o varias -'): string {
    $html = '<div class="filter-group">';
    $html .= '<label for="filter-' . $name . '">' . htmlspecialchars($label) . '</label>';
    
    // AÑADIMOS LA CLASE "filter-select-multi" Y UN ID ÚNICO
    $html .= '<select id="filter-' . $name . '" name="' . htmlspecialchars($name) . '[]" class="filter-select-multi" multiple data-placeholder="' . htmlspecialchars($placeholder) . '" data-filter-name="' . $name . '">';
    
    foreach ($options as $opt) {
        $sel = in_array((string)$opt, $selected, true) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($opt) . '"' . $sel . '>' . htmlspecialchars($opt) . '</option>';
    }
    
    $html .= '</select></div>';
    return $html;
}

/* =========================================================
   CAPTURAR FILTROS DESDE $_GET
========================================================= */
$f_estado = trim($_GET['estado'] ?? 'todos');
$f_desde = trim($_GET['desde'] ?? '');
$f_hasta = trim($_GET['hasta'] ?? '');
$f_buscar = trim($_GET['buscar'] ?? '');

// Filtros individuales: solo los campos que existen en el form 22
$filtros_individuales = ['motivo' => trim($_GET['motivo'] ?? '')];
foreach ($campos_form22 as $cf22f) {
    $filtros_individuales[$cf22f['name']] = get_array_param($cf22f['name']);
}

/* =========================================================
   CONSTRUCCIÓN DE LA CONSULTA SQL BASE
========================================================= */

// SQL base para seleccionar tickets con sus datos
$sql_base = "SELECT
    t.ticket_id,
    t.number AS numero_ticket,
    t.created AS fecha_creacion,
    t.status_id,
    s.name AS estado_nombre,
    cd.subject AS cdata_subject,
    $cd_sql
FROM ost_ticket t
JOIN ost_ticket_status s ON s.id=t.status_id
LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id=t.ticket_id
WHERE EXISTS (
    SELECT 1 FROM ost_form_entry fe_chk 
    WHERE fe_chk.object_id = t.ticket_id 
    AND fe_chk.object_type = 'T' 
    AND fe_chk.form_id = 22
)";

$sql_where = " AND 1=1";  // Sin filtros extra de topic para coordinadores

/* =========================================================
   APLICAR FILTROS A LA CONSULTA SQL
========================================================= */

// Filtro por estado
if ($f_estado !== 'todos') {
    if ($f_estado === 'sin_asignar') {
        $ids_conocidos = array_map('intval', array_keys($MAPA_FIJO));
        if (!empty($ids_conocidos)) {
            $sql_where .= " AND t.status_id NOT IN (" . implode(',', $ids_conocidos) . ")";
        }
    } else {
        $ids = [];
        foreach ($mapa_estados as $id => $er) {
            if ($er === $f_estado) $ids[] = intval($id);
        }
        if (!empty($ids)) {
            $sql_where .= " AND t.status_id IN (" . implode(',', $ids) . ")";
        }
    }
}

// Filtro por rango de fechas
if ($f_desde !== '') {
    $desde = $conexion->real_escape_string($f_desde);
    $sql_where .= " AND t.created >= '{$desde} 00:00:00'";
}
if ($f_hasta !== '') {
    $hasta = $conexion->real_escape_string($f_hasta);
    $sql_where .= " AND t.created <= '{$hasta} 23:59:59'";
}

// Filtro de búsqueda general (busca en los valores del formulario form_id=22)
if ($f_buscar !== '') {
    $b = $conexion->real_escape_string($f_buscar);
    $sql_where .= " AND (
        t.number LIKE '%{$b}%'
        OR EXISTS (
            SELECT 1 FROM ost_form_entry fe_b
            JOIN ost_form_entry_values fev_b ON fev_b.entry_id = fe_b.id
            WHERE fe_b.object_id = t.ticket_id
            AND fe_b.object_type = 'T'
            AND fe_b.form_id = 22
            AND fev_b.value LIKE '%{$b}%'
        )
    )";
}

// Crear mapa de texto a IDs para búsquedas más inteligentes
$mapa_texto_a_ids = [];
foreach ($mapa_lista_items as $itemId => $itemVal) {
    $clave = mb_strtolower(trim($itemVal), 'UTF-8');
    if (!isset($mapa_texto_a_ids[$clave])) $mapa_texto_a_ids[$clave] = [];
    $mapa_texto_a_ids[$clave][] = (string)$itemId;
}

// Aplicar filtros individuales por campo (leen del formulario form_id=22, no de cdata)
foreach ($filtros_individuales as $campo => $valor) {
    if ($campo === 'motivo') continue;
    if (empty($valor)) continue;

    $condiciones = [];
    foreach ($valor as $v) {
        $valorEsc = $conexion->real_escape_string($v);
        $campoEsc = $conexion->real_escape_string($campo);
        $condiciones[] = "EXISTS (
            SELECT 1 FROM ost_form_entry fe_f
            JOIN ost_form_entry_values fev_f ON fev_f.entry_id = fe_f.id
            JOIN ost_form_field ff_f ON ff_f.id = fev_f.field_id
            WHERE fe_f.object_id = t.ticket_id
            AND fe_f.object_type = 'T'
            AND fe_f.form_id = 22
            AND ff_f.name = '{$campoEsc}'
            AND fev_f.value LIKE '%{$valorEsc}%'
        )";
    }
    if (!empty($condiciones)) {
        $sql_where .= " AND (" . implode(' OR ', $condiciones) . ")";
    }
}

// Filtro especial para motivo
$motivo_val = $filtros_individuales['motivo'];
if (is_array($motivo_val)) $motivo_val = implode(' ', $motivo_val);
$motivo_val = trim((string)$motivo_val);
if ($motivo_val !== '') {
    $motivoEsc = $conexion->real_escape_string($motivo_val);
    $sql_where .= " AND EXISTS (
        SELECT 1 FROM ost_form_entry fe_m
        JOIN ost_form_entry_values fev_m ON fev_m.entry_id = fe_m.id
        JOIN ost_form_field ff_m ON ff_m.id = fev_m.field_id
        WHERE fe_m.object_id = t.ticket_id
        AND fe_m.object_type = 'T'
        AND fe_m.form_id = 22
        AND ff_m.name = 'motivo'
        AND fev_m.value LIKE '%{$motivoEsc}%'
    )";
}

// SQL completa con orden descendente
$sql_full = $sql_base . $sql_where . " ORDER BY t.ticket_id DESC";

// SQL para contar total de resultados
$sql_count = "SELECT COUNT(DISTINCT t.ticket_id) as total
FROM ost_ticket t
JOIN ost_ticket_status s ON s.id=t.status_id
LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id=t.ticket_id
WHERE EXISTS (
    SELECT 1 FROM ost_form_entry fe_chk2
    WHERE fe_chk2.object_id = t.ticket_id
    AND fe_chk2.object_type = 'T'
    AND fe_chk2.form_id = 22
)" . $sql_where;

$total_resultados = 0;
try {
    $total_resultados = $conexion->query($sql_count)->fetch_assoc()['total'] ?? 0;
} catch (Exception $e) {}

/* =========================================================
   PAGINACIÓN
========================================================= */
$por_pagina = 40;
$f_pagina = max(1, intval($_GET['pagina'] ?? 1));
$total_paginas = max(1, (int)ceil($total_resultados / $por_pagina));
if ($f_pagina > $total_paginas) $f_pagina = $total_paginas;
$offset = ($f_pagina - 1) * $por_pagina;

// Ejecutar consulta con límite y offset para paginación
$resultado = null;
try {
    $resultado = $conexion->query($sql_full . " LIMIT $por_pagina OFFSET $offset");
} catch (Exception $e) {}

/* =========================================================
   CONTADORES POR ESTADO (ACTUALIZADOS CON FILTROS)
   ========================================================= */

$contadores = ['iniciada'=>0,'en_curso'=>0,'cerrada'=>0,'enviada'=>0,'sin_asignar'=>0];

try {
    // Usar la misma SQL base con filtros para contar por estado
    $sql_contador = "SELECT t.status_id, COUNT(DISTINCT t.ticket_id) as total
                     FROM ost_ticket t
                     JOIN ost_ticket_status s ON s.id=t.status_id
                     LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id=t.ticket_id
                     WHERE EXISTS (
                         SELECT 1 FROM ost_form_entry fe_chk3
                         WHERE fe_chk3.object_id = t.ticket_id
                         AND fe_chk3.object_type = 'T'
                         AND fe_chk3.form_id = 22
                     )" . $sql_where . "
                     GROUP BY t.status_id";

    $sq = $conexion->query($sql_contador);
    while ($st = $sq->fetch_assoc()) {

        $er = $mapa_estados[$st['status_id']] ?? 'sin_asignar';

        if (isset($contadores[$er])) {
            $contadores[$er] += (int)$st['total'];
        } else {
            $contadores['sin_asignar'] += (int)$st['total'];
        }

    }

} catch (Exception $e) {}

$total_tickets = array_sum($contadores);
if ($total_resultados > 0 && array_sum($contadores) === 0) {
    // Los contadores estaban vacíos pero hay resultados, recalcular
    $contadores = ['iniciada'=>0,'en_curso'=>0,'cerrada'=>0,'enviada'=>0,'sin_asignar'=>0];
    try {
        $sq = $conexion->query("
            SELECT t.status_id, COUNT(*) as total
            FROM ost_ticket t
            WHERE t.topic_id = 14" . $sql_where . "
            GROUP BY t.status_id
        ");
        
        while ($st = $sq->fetch_assoc()) {
            $er = $mapa_estados[$st['status_id']] ?? 'sin_asignar';
            if (isset($contadores[$er])) {
                $contadores[$er] += (int)$st['total'];
            } else {
                $contadores['sin_asignar'] += (int)$st['total'];
            }
        }
    } catch (Exception $e) {}
}
/* =========================================================
   CONTADORES HOY Y 7 DÍAS
========================================================= */
$tickets_hoy = 0;
$tickets_7dias = 0;
try {
    $r = $conexion->query("
        SELECT
            SUM(DATE(t.created) = CURDATE()) AS hoy,
            SUM(t.created >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS siete
        FROM ost_ticket t
        WHERE EXISTS (
            SELECT 1 FROM ost_form_entry fe_h
            WHERE fe_h.object_id = t.ticket_id
            AND fe_h.object_type = 'T'
            AND fe_h.form_id = 22
        )
    ");
    if ($r && $row = $r->fetch_assoc()) {
        $tickets_hoy   = (int)($row['hoy']   ?? 0);
        $tickets_7dias = (int)($row['siete']  ?? 0);
    }
} catch (Exception $e) {}

/* =========================================================
   CSV EXPORT (si se solicita con ?excel=1)
========================================================= */
if (isset($_GET['excel'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Coordinadores_' . date('d-m-Y') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    
    $out = fopen('php://output', 'w');
    
    // Cabeceras CSV dinámicas según campos del form 22
    $csv_headers = ['FECHA'];
    foreach ($campos_form22 as $cf22h) {
        $csv_headers[] = strtoupper($cf22h['label']);
    }
    $csv_headers[] = 'FECHA SOLUCIÓN';
    $csv_headers[] = 'ESTADO';
    $csv_headers[] = 'TICKET';
    fputcsv($out, $csv_headers, ',');
    
    // Datos del CSV
    try {
        $rcsv = $conexion->query($sql_full);
        if ($rcsv) while ($f = $rcsv->fetch_assoc()) {
            $row_csv = [date('d/m/Y', strtotime($f['fecha_creacion']))];
            foreach ($campos_form22 as $cf22h) {
                $row_csv[] = $f[$cf22h['name']] ?? '';
            }
            $fs_csv = !empty($f['fecha_solucion_manual']) ? date('d/m/Y', strtotime($f['fecha_solucion_manual'])) : '';
            $row_csv[] = $fs_csv;
            $row_csv[] = $f['estado_nombre'] ?? '';
            $row_csv[] = $f['numero_ticket'] ?? '';
            fputcsv($out, $row_csv, ',');
        }
    } catch (Exception $e) {}
    fclose($out);
    exit;
}

/* =========================================================
   OPCIONES PARA FILTROS DESPLEGABLES
========================================================= */
// Opciones de filtros: carga los valores únicos de cada campo del form 22
$opciones_filtros = [];
foreach ($campos_form22 as $cf22o) {
    $nombre = $cf22o['name'];
    try {
        $unicos_f = [];
        $stmt_f = $conexion->prepare(
            "SELECT DISTINCT fev_o.value
             FROM ost_form_entry fe_o
             JOIN ost_form_entry_values fev_o ON fev_o.entry_id = fe_o.id
             JOIN ost_form_field ff_o ON ff_o.id = fev_o.field_id
             WHERE fe_o.form_id = 22
             AND fe_o.object_type = 'T'
             AND ff_o.name = ?
             AND fev_o.value IS NOT NULL
             AND TRIM(fev_o.value) != ''
             ORDER BY fev_o.value ASC
             LIMIT 500"
        );
        if ($stmt_f) {
            $stmt_f->bind_param('s', $nombre);
            $stmt_f->execute();
            $res_f = $stmt_f->get_result();
            while ($row_f = $res_f->fetch_assoc()) {
                $v_f = trim((string)$row_f['value']);
                if ($v_f !== '') $unicos_f[] = $v_f;
            }
            $stmt_f->close();
        }
        $opciones_filtros[$nombre] = array_values(array_unique($unicos_f));
    } catch (Exception $e) {
        $opciones_filtros[$nombre] = [];
    }
}

/* =========================================================
   PARÁMETROS PARA PAGINACIÓN (mantener filtros al cambiar página)
========================================================= */
$params_pag = [
    'estado' => $f_estado,
    'desde' => $f_desde,
    'hasta' => $f_hasta,
    'buscar' => $f_buscar
];

foreach ($filtros_individuales as $k => $v) {
    if (is_array($v)) {
        if (!empty($v)) $params_pag[$k] = $v;
    } else {
        if ($v !== '') $params_pag[$k] = $v;
    }
}

/**
 * Generar URL para paginación con filtros
 */
function url_pag(int $p, array $params): string {
    $params['pagina'] = $p;
    return '?' . http_build_query($params);
}

/**
 * Renderizar celda editable de texto corto (input/textarea pequeño)
 */
function td_input(int $tid, string $campo, mixed $valor): string {
    return '<td><textarea class="cell-input cell-input-display" rows="2" oninput="autoGrow(this)" onchange="guardar(this,\'' . $campo . '\',' . $tid . ')">' . htmlspecialchars((string)$valor) . '</textarea><span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span></td>';
}

/**
 * Renderizar celda editable de textarea grande
 */
function td_textarea(int $tid, string $campo, mixed $valor): string {
    $extraClass = in_array($campo, ['motivo','medidas','solucion_manual'], true) ? ' cell-textarea-50' : '';
    return '<td><textarea class="cell-textarea' . $extraClass . '" onchange="guardar(this,\'' . $campo . '\',' . $tid . ')">' . htmlspecialchars((string)$valor) . '</textarea><span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span></td>';
}


/**
 * Renderizar celda editable con select desplegable
 */
function td_select(int $tid, string $campo, mixed $valor, array $opciones): string {

    $valorActual = trim((string)$valor);

    $html = '<td>';
    $html .= '<select class="cell-select-auto" onchange="guardar(this,\'' . $campo . '\',' . $tid . ')">';

    // Opción vacía
    $html .= '<option value=""></option>';

    $existeEnOpciones = false;

    foreach ($opciones as $opcion) {
        $selected = ($opcion === $valorActual) ? ' selected' : '';
        if ($selected) $existeEnOpciones = true;

        $html .= '<option value="' . htmlspecialchars($opcion) . '"' . $selected . '>'
              . htmlspecialchars($opcion)
              . '</option>';
    }

    // ✅ Si el valor importado NO está en cuestionario, mostrarlo arriba pero deshabilitado
    if ($valorActual !== '' && !$existeEnOpciones) {
        $html = '<td><select class="cell-select-auto" onchange="guardar(this,\'' . $campo . '\',' . $tid . ')">';
        $html .= '<option value="' . htmlspecialchars($valorActual) . '" selected>'
              . htmlspecialchars($valorActual)
              . '</option>';

        foreach ($opciones as $opcion) {
            $html .= '<option value="' . htmlspecialchars($opcion) . '">'
                  . htmlspecialchars($opcion)
                  . '</option>';
        }
    }

    $html .= '</select>';
    $html .= '<span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span>';
    $html .= '</td>';

    return $html;
}

/**
 * Renderizar celda del select de PLAN con data attributes para recarga dinámica de sectores
 */
function td_select_plan(int $tid, string $campo, mixed $valor, array $opciones): string {
    $valorActual = trim((string)$valor);

    $html = '<td>';
    $html .= '<select class="cell-select-auto" data-plan-select="1" data-tid="' . $tid . '"'
           . ' onchange="guardar(this,\'' . $campo . '\',' . $tid . '); recargarSectoresFila(this)">';
    $html .= '<option value=""></option>';

    $existeEnOpciones = false;
    foreach ($opciones as $opcion) {
        $selected = ($opcion === $valorActual) ? ' selected' : '';
        if ($selected) $existeEnOpciones = true;
        $html .= '<option value="' . htmlspecialchars($opcion) . '"' . $selected . '>'
               . htmlspecialchars($opcion) . '</option>';
    }

    if ($valorActual !== '' && !$existeEnOpciones) {
        $html = '<td><select class="cell-select-auto" data-plan-select="1" data-tid="' . $tid . '"'
              . ' onchange="guardar(this,\'' . $campo . '\',' . $tid . '); recargarSectoresFila(this)">';
        $html .= '<option value="' . htmlspecialchars($valorActual) . '" selected>'
               . htmlspecialchars($valorActual) . '</option>';
        foreach ($opciones as $opcion) {
            $html .= '<option value="' . htmlspecialchars($opcion) . '">'
                   . htmlspecialchars($opcion) . '</option>';
        }
    }

    $html .= '</select>';
    $html .= '<span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span>';
    $html .= '</td>';
    return $html;
}

/**
 * Renderizar celda de sector con data-sector-select para que JS la recargue al cambiar el plan
 */
function td_select_sector(int $tid, string $campo, mixed $valor, array $opciones): string {
    $valorActual = trim((string)$valor);

    $html = '<td>';
    $html .= '<select class="cell-select-auto" data-sector-select="' . htmlspecialchars($campo) . '" data-tid="' . $tid . '"'
           . ' onchange="guardar(this,\'' . $campo . '\',' . $tid . ')">';
    $html .= '<option value=""></option>';

    $existeEnOpciones = false;
    foreach ($opciones as $opcion) {
        $selected = ($opcion === $valorActual) ? ' selected' : '';
        if ($selected) $existeEnOpciones = true;
        $html .= '<option value="' . htmlspecialchars($opcion) . '"' . $selected . '>'
               . htmlspecialchars($opcion) . '</option>';
    }

    if ($valorActual !== '' && !$existeEnOpciones) {
        $html = '<td><select class="cell-select-auto" data-sector-select="' . htmlspecialchars($campo) . '" data-tid="' . $tid . '"'
              . ' onchange="guardar(this,\'' . $campo . '\',' . $tid . ')">';
        $html .= '<option value="' . htmlspecialchars($valorActual) . '" selected>'
               . htmlspecialchars($valorActual) . '</option>';
        foreach ($opciones as $opcion) {
            $html .= '<option value="' . htmlspecialchars($opcion) . '">'
                   . htmlspecialchars($opcion) . '</option>';
        }
    }

    $html .= '</select>';
    $html .= '<span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span>';
    $html .= '</td>';
    return $html;
}

/**
 * Extraer información de archivo desde valor JSON de osTicket
 */

function extraer_info_archivo_ost(mixed $valor): ?array {
    $valor = trim((string)$valor);
    if ($valor === '' || strtolower($valor) === 'false' || strtolower($valor) === 'null')
        return null;

    $json = json_decode($valor, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json) && !empty($json)) {
        foreach ($json as $fid => $nombre) {
            $fileId = (int)$fid;
            if ($fileId > 0) {
                return ['file_id' => $fileId, 'nombre' => (string)$nombre];
            }
        }
    }
    return null;
}

/**
 * Obtener URL de descarga de archivo de osTicket
 */
function url_archivo_ost(mixed $valor): ?string {
    $info = extraer_info_archivo_ost($valor);
    if (!$info) return null;
    try {
        $file = AttachmentFile::lookup((int)$info['file_id']);
        if (!$file) return null;
        return $file->getExternalDownloadUrl();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Renderizar celda de fotos/archivos (con enlaces)
 */
function td_fotos(int $tid, string $campo, mixed $valor): string {
    $valor = trim(str_replace(["\r","\n","\t"], '', (string)$valor));
    if ($valor === '' || strtolower($valor) === 'false') return '<td></td>';
    
    $json = json_decode($valor, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json) && count($json) > 0) {
        $fileId = (int) array_key_first($json);
        $nombre = (string) reset($json);
        if ($fileId > 0) {
            return '<td><div style="display:flex;flex-direction:column;gap:4px">
                <div style="display:flex;gap:10px">
                    <a href="archivo.php?id=' . $fileId . '&modo=ver" target="_blank" class="ticket-link">Ver archivo</a>
                </div></div></td>';
        }
    }
    
    if (preg_match('/^(https?:\/\/)/i', $valor)) {
        return '<td><a href="' . htmlspecialchars($valor) . '" target="_blank" class="ticket-link">Ver archivo</a></td>';
    }
    
    if (preg_match('/^www\./i', $valor)) {
        return '<td><a href="https://' . htmlspecialchars($valor) . '" target="_blank" class="ticket-link">Ver archivo</a></td>';
    }
    
    return '<td style="font-size:11px;color:#64748b">' . htmlspecialchars($valor) . '</td>';
}

/**
 * Extraer texto de fotos para display
 */
function extraer_fotos_display(mixed $valor): string {
    $valor = trim((string)$valor);
    if ($valor === '' || strtolower($valor) === 'false') return '';
    $json = json_decode($valor, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json) && count($json) > 0) {
        return (string) reset($json);
    }
    return $valor;
}

/**
 * Renderizar celda de fotos con detección automática desde thread de osTicket
 */
function td_fotos_visible(int $tid, mixed $valor): string {
    $valor = trim(str_replace(["\r","\n","\t"], '', (string)$valor));
    
    // Si ya hay valor en la BD, usarlo
    if ($valor !== '' && strtolower($valor) !== 'false') {
        return td_fotos($tid, 'fotos', $valor);
    }
    
    // Si no hay clase Ticket, mostrar error
    if (!class_exists('Ticket')) {
        return '<td style="font-size:11px;color:red">Ticket no cargada</td>';
    }
    
    try {
        $ticket = Ticket::lookup($tid);
        if (!$ticket) {
            return '<td style="font-size:11px;color:red">No ticket</td>';
        }
        
        $thread = $ticket->getThread();
        if (!$thread) {
            return '<td></td>';
        }
        
        // Buscar archivos adjuntos en el thread
        $archivos = [];
        $entries = 0;
        $adjuntos = 0;
        
        foreach ($thread->getEntries() as $entry) {
            $entries++;
            $atts = $entry->getAttachments();
            if ($atts) {
                foreach ($atts as $att) {
                    $adjuntos++;
                    if (isset($att->file) && $att->file) {
                        $file = $att->file;
                        $fid = (int)$file->getId();
                        if ($fid > 0) {
                            $archivos[$fid] = [
                                'file_id' => $fid,
                                'nombre' => $file->getName()
                            ];
                        }
                    }
                }
            }
        }
        
        // Renderizar enlaces a archivos
        $html = '<td><div style="display:flex;flex-direction:column;gap:4px">';
        foreach ($archivos as $it) {
            $html .= '<div style="display:flex;flex-direction:column;gap:4px">';
            $html .= '<div style="display:flex;gap:8px">';
            $html .= '<a href="archivo.php?id=' . (int)$it['file_id'] . '&modo=ver" target="_blank" class="ticket-link">Ver</a>'; 
            $html .= '</div></div>';
        }
        $html .= '</div></td>';
        return $html;
        
    } catch (Throwable $e) {
        return '<td style="font-size:11px;color:red">' . htmlspecialchars($e->getMessage()) . '</td>';
    }
}

/**
 * Renderizar fila completa de la tabla
 */
function render_fila(array $f, array $mapa): string {
    global $mapa_lista_items, $campos_tipo_lista, $campos_tipo_html;
    global $opciones_filtros, $conexion; 
    // Procesar datos de la fila
    procesar_fila_osticket($f, $mapa_lista_items, $campos_tipo_lista, $campos_tipo_html);
    
    $tid = (int)($f['ticket_id'] ?? 0);
    $statusIdFila = $f['status_id'] ?? 0;
    $er = $mapa[$statusIdFila] ?? 'sin_asignar';
    
    // Mapa de estados a clases CSS para colores de fila
    $map_cls = [
        'iniciada' => 'row-iniciada',
        'en_curso' => 'row-en-curso',
        'cerrada' => 'row-cerrada',
        'enviada' => 'row-enviada',
        'sin_asignar' => 'row-sin-asignar'
    ];
    $cls = $map_cls[$er] ?? 'row-sin-asignar';
    
    $tutorMostrar = $f['tutor'] ?? '';
    
    // Mapa de estados a etiquetas legibles
    $map_lbl = [
        'iniciada' => 'Iniciada',
        'en_curso' => 'En Curso',
        'cerrada' => 'Cerrada',
        'enviada' => 'Enviada'
    ];
    $estadoStr = $map_lbl[$er] ?? 'Sin Asignar';
    
    // Formatear fecha de solución
    $fs = !empty($f['fecha_solucion_manual']) 
        ? date('Y-m-d', strtotime($f['fecha_solucion_manual']))
        : '';
    
    // Obtener valor de fotos para display (priorizar 'fotos' sobre 'Archivo')
    $valorFotoDisplay = $f['fotos'] ?? '';
    if (trim((string)$valorFotoDisplay) === '' || strtolower(trim((string)$valorFotoDisplay)) === 'false') {
        $valorFotoDisplay = $f['Archivo'] ?? '';
    }
    $fotosDisplay = extraer_fotos_display($valorFotoDisplay);
    
    // Preparar datos para el modal de información (JSON con todos los datos)
    $infoModal = json_encode([
        'fecha' => date('d/m/Y', strtotime($f['fecha_creacion'] ?? 'now')),
        'expediente' => $f['subject'] ?? '',
        'plan' => $f['plan'] ?? '',
        'sector_labora' => $f['sector_labora'] ?? '',
        'sector_cyl' => $f['sector_cyl'] ?? '',
        'sector_asturias' => $f['sector_asturias'] ?? '',
        'sector_estatal' => $f['sector_estatal'] ?? '',
        'tutor' => $tutorMostrar,
        'nombre' => $f['nombreAlu'] ?? '',
        'apellidos' => $f['apellidosAlu'] ?? '',
        'empresa' => $f['empresa'] ?? '',
        'accion' => $f['accion'] ?? '',
        'grupo' => $f['grupo'] ?? '',
        'curso' => $f['curso'] ?? '',
        'incidencia' => $f['incidencia'] ?? '',
        'detalles' => $f['detalles'] ?? '',
        'razon' => $f['razon'] ?? '',
        'dificultad' => $f['dificultad'] ?? '',
        'datos' => $f['datos'] ?? '',
        'motivo' => $f['motivo'] ?? '',
        'fotos' => $fotosDisplay,
        'medidas' => $f['medidas'] ?? '',
        'solucion' => $f['solucion_manual'] ?? '',
        'fechasol' => $fs,
        'estado' => $estadoStr,
        'ticket' => $f['numero_ticket'] ?? ''
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // Construir HTML de la fila
    $h = '<tr class="'.$cls.'">';
    
    // ============================================================
    // COLUMNA DE ACCIONES (Ver/Copiar, Duplicar, Borrar)
    // ============================================================
    $h .= '<td style="min-width:160px;text-align:center">
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
            <button type="button" class="action-btn-sm action-tooltip"
                data-tooltip="Ver y copiar"
                style="background:#eef2ff;color:#4f46e5;border:1px solid #c7d2fe"
                onclick="openInfoModal(this)"
                data-info="'.htmlspecialchars($infoModal, ENT_QUOTES, 'UTF-8').'">
                <i class="fas fa-copy"></i>
            </button>
            <button type="button" class="action-btn-sm action-tooltip"
                data-tooltip="Duplicar ticket"
                style="background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd"
                onclick="duplicarTicket('.$tid.', this)">
                <i class="fas fa-plus-circle"></i>
            </button>
            <button type="button" class="action-btn-sm action-tooltip"
                data-tooltip="Borrar ticket"
                style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca"
                onclick="borrarTicket('.$tid.', this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </td>';
    
    // ============================================================
    // COLUMNA DE FECHA (editable)
    // ============================================================
    $h .= '<td class="td-date"><input type="date" class="cell-input" value="'.date('Y-m-d', strtotime($f['fecha_creacion'])).'" onchange="guardar(this,\'fecha_creacion\','.$tid.')"><span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span></td>';
    
    // ============================================================
    // COLUMNAS EDITABLES DE DATOS
    // ============================================================
    $h .= td_input($tid, 'subject', $f['subject'] ?? '');
    // Select de plan: añade data-plan-select y data-tid para que JS recargue sectores al cambiar
    $h .= td_select_plan($tid, 'plan', $f['plan'] ?? '', $opciones_filtros['plan'] ?? []);
$h .= td_select_sector($tid, 'sector_labora',
    $f['sector_labora'] ?? '',
    obtener_opciones_sector_por_plan($conexion, $f['plan'] ?? '', 'sector_labora')
);

$h .= td_select_sector($tid, 'sector_cyl',
    $f['sector_cyl'] ?? '',
    obtener_opciones_sector_por_plan($conexion, $f['plan'] ?? '', 'sector_cyl')
);

$h .= td_select_sector($tid, 'sector_asturias',
    $f['sector_asturias'] ?? '',
    obtener_opciones_sector_por_plan($conexion, $f['plan'] ?? '', 'sector_asturias')
);

$h .= td_select_sector($tid, 'sector_estatal',
    $f['sector_estatal'] ?? '',
    obtener_opciones_sector_por_plan($conexion, $f['plan'] ?? '', 'sector_estatal')
);
    $h .= td_input($tid, 'tutor', $tutorMostrar);
    $h .= td_input($tid, 'nombreAlu', $f['nombreAlu'] ?? '');
    $h .= td_input($tid, 'apellidosAlu', $f['apellidosAlu'] ?? '');
	$h .= td_select($tid, 'empresa', $f['empresa'] ?? '', $opciones_filtros['empresa'] ?? []);
    $h .= td_input($tid, 'accion', $f['accion'] ?? '');
    $h .= td_input($tid, 'grupo', $f['grupo'] ?? '');
    $h .= td_input($tid, 'curso', $f['curso'] ?? '');
	$h .= td_select($tid, 'incidencia', $f['incidencia'] ?? '', 		$opciones_filtros['incidencia'] ?? []);
	$h .= td_select($tid, 'detalles', $f['detalles'] ?? '', 			$opciones_filtros['detalles'] ?? []);
	$h .= td_select($tid, 'razon', $f['razon'] ?? '', 					$opciones_filtros['razon'] ?? []);
	$h .= td_select($tid, 'dificultad', $f['dificultad'] ?? '', 		$opciones_filtros['dificultad'] ?? []);
	$h .= td_select($tid, 'datos', $f['datos'] ?? '', 					$opciones_filtros['datos'] ?? []);
    $h .= td_textarea($tid, 'motivo', $f['motivo'] ?? '');
    
    // ============================================================
    // COLUMNA DE FOTOS (con fallback a 'Archivo')
    // ============================================================
    $valorFoto = $f['fotos'] ?? '';
    if (trim((string)$valorFoto) === '' || strtolower(trim((string)$valorFoto)) === 'false') {
        $valorFoto = $f['Archivo'] ?? '';
    }
    $h .= td_fotos_visible($tid, $valorFoto);
    
    // ============================================================
    // COLUMNAS DE MEDIDAS Y SOLUCIÓN
    // ============================================================
    $h .= td_textarea($tid, 'medidas', $f['medidas'] ?? '');
    $h .= td_textarea($tid, 'solucion_manual', $f['solucion_manual'] ?? '');
    
    // ============================================================
    // COLUMNA DE FECHA DE SOLUCIÓN (editable)
    // ============================================================
    $h .= '<td class="td-date"><input type="date" class="cell-input" value="'.$fs.'" onchange="guardar(this,\'fecha_solucion_manual\','.$tid.')"><span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span></td>';
    
    // ============================================================
    // COLUMNA DE ESTADO (select desplegable)
    // ============================================================
    $h .= '<td><select class="cell-select" onchange="guardar(this,\'estado_ticket\','.$tid.')">';
    $statusActual = $f['status_id'] ?? null;
    $sinAsignarSelected = (!$statusActual || !isset($mapa[$statusActual]) || $mapa[$statusActual] === 'otro') ? ' selected' : '';
    $h .= '<option value=""' . $sinAsignarSelected . '>— Sin asignar —</option>';
    
    foreach ($GLOBALS['estados_arr'] as $es) {
        if (!isset($map_lbl[$es['estado_real']])) continue;
        $sel = ($es['id'] == $statusActual) ? ' selected' : '';
        $h .= '<option value="'.$es['id'].'"'.$sel.'>'.$map_lbl[$es['estado_real']].'</option>';
    }
    $h .= '</select><span class="si"><i class="fas fa-check ok"></i><i class="fas fa-spinner fa-spin ld"></i><i class="fas fa-times er"></i></span></td>';
    
    // ============================================================
    // COLUMNA DE TICKET 
    // ============================================================
    $h .= '<td>' . htmlspecialchars($f['numero_ticket'] ?? '') . '</td>';
    
    return $h . '</tr>';
}

/**
 * Renderizar fila dinámica para Coordinadores (campos del form_id=22)
 */
function render_fila_coordinadores(array $f, array $mapa, array $campos_form22): string {
    global $mapa_lista_items, $campos_tipo_lista, $campos_tipo_html;

    foreach ($campos_form22 as $cf) {
        $n = $cf['name'];
        $tipo_cf = $cf['type'];
        if (isset($f[$n])) {
            // Resolver campos de lista por tipo osTicket (list-N) O por nombre en $campos_tipo_lista
            if (str_starts_with($tipo_cf, 'list-') || in_array($n, $campos_tipo_lista)) {
                $f[$n] = resolver_valor_lista($f[$n], $mapa_lista_items);
            } elseif (in_array($n, $campos_tipo_html)) {
                $f[$n] = limpiar_html_osticket($f[$n]);
            }
        }
    }

    $tid = (int)($f['ticket_id'] ?? 0);
    $statusIdFila = $f['status_id'] ?? 0;
    $er = $mapa[$statusIdFila] ?? 'sin_asignar';

    $map_cls = [
        'iniciada'    => 'row-iniciada',
        'en_curso'    => 'row-en-curso',
        'cerrada'     => 'row-cerrada',
        'enviada'     => 'row-enviada',
        'sin_asignar' => 'row-sin-asignar'
    ];
    $cls = $map_cls[$er] ?? 'row-sin-asignar';

    $map_lbl = [
        'iniciada' => 'Iniciada',
        'en_curso' => 'En Curso',
        'cerrada'  => 'Cerrada',
        'enviada'  => 'Enviada'
    ];
    $estadoStr = $map_lbl[$er] ?? 'Sin Asignar';

    $fs = !empty($f['fecha_solucion_manual'])
        ? date('Y-m-d', strtotime($f['fecha_solucion_manual']))
        : '';

    // ── JSON completo para el modal del ojo ──────────────────
    $allData = ['Fecha' => date('d/m/Y', strtotime($f['fecha_creacion'] ?? 'now'))];
    foreach ($campos_form22 as $cf) {
        $allData[$cf['label']] = $f[$cf['name']] ?? '';
    }
    // No incluir Estado ni Fecha Solución en el modal
    $allDataJson = json_encode($allData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // ── Valores de columnas fijas ─────────────────────────────
    // Expediente: buscar en form22 por nombre/label, luego fallback a subject/cdata
    $expediente = '';
    foreach ($campos_form22 as $cf) {
        $lbl = mb_strtolower($cf['label'], 'UTF-8');
        $nm  = mb_strtolower($cf['name'],  'UTF-8');
        if (in_array($nm, ['subject','expediente','exp']) || stripos($lbl, 'expediente') !== false || stripos($lbl, 'exp.') !== false) {
            $expediente = $f[$cf['name']] ?? '';
            break;
        }
    }
    // Fallback directo
    if ($expediente === '') $expediente = $f['subject'] ?? ($f['cdata_subject'] ?? ($f['expediente'] ?? ''));

    // Curso
    $curso = '';
    foreach ($campos_form22 as $cf) {
        if (stripos($cf['name'], 'curso') !== false || stripos($cf['label'], 'curso') !== false) {
            $curso = $f[$cf['name']] ?? ''; break;
        }
    }

    // Valoración global del equipo docente y de apoyo
    $valoracion = '';
    $valoracion_label = 'Valoración';
    foreach ($campos_form22 as $cf) {
        $lbl = mb_strtolower($cf['label'], 'UTF-8');
        $nm  = mb_strtolower($cf['name'],  'UTF-8');
        if (
            (stripos($lbl, 'valoraci') !== false && (stripos($lbl, 'global') !== false || stripos($lbl, 'equipo') !== false || stripos($lbl, 'docente') !== false)) ||
            (stripos($nm,  'valoraci') !== false && (stripos($nm,  'global') !== false || stripos($nm,  'equipo') !== false || stripos($nm,  'docente') !== false))
        ) {
            $valoracion = $f[$cf['name']] ?? '';
            $valoracion_label = $cf['label'];
            break;
        }
    }
    if ($valoracion === '') {
        foreach ($campos_form22 as $cf) {
            if (stripos($cf['label'], 'valoraci') !== false || stripos($cf['name'], 'valoraci') !== false) {
                $valoracion = $f[$cf['name']] ?? '';
                $valoracion_label = $cf['label'];
                break;
            }
        }
    }

    // Badge valoración escala 1-5
    $valoracion_html = '';
    if ($valoracion !== '' && is_numeric(trim($valoracion))) {
        $num = (float)trim($valoracion);
        $bg_v = $num >= 4 ? '#10b981' : ($num >= 3 ? '#f59e0b' : '#ef4444');
        $valoracion_html = '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border-radius:50%;background:'.$bg_v.';color:#fff;font-weight:700;font-size:13px">' . htmlspecialchars(trim($valoracion)) . '</span>';
    } else {
        $valoracion_html = '<span style="color:#94a3b8;font-size:12px">' . htmlspecialchars($valoracion ?: '—') . '</span>';
    }

    // ── HTML de la fila ───────────────────────────────────────
    $h = '<tr class="'.$cls.'">';

    // ACCIONES
    $h .= '<td style="min-width:90px;text-align:center">
        <div style="display:flex;gap:6px;justify-content:center">
            <button type="button" class="action-btn-sm action-tooltip"
                data-tooltip="Ver detalle"
                style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe"
                onclick="openDetalleModal(this)"
                data-info="'.htmlspecialchars($allDataJson, ENT_QUOTES, 'UTF-8').'">
                <i class="fas fa-eye"></i>
            </button>
            <button type="button" class="action-btn-sm action-tooltip"
                data-tooltip="Eliminar"
                style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5"
                onclick="borrarTicket('.$tid.', this)">
                <i class="fas fa-trash"></i>
            </button>
        </div></td>';

    // FECHA
    $h .= '<td style="white-space:nowrap;font-size:13px">'
        . htmlspecialchars(date('d/m/Y', strtotime($f['fecha_creacion'] ?? 'now')))
        . '</td>';

    // EXPEDIENTE
    $h .= '<td style="font-size:13px;font-weight:600">' . htmlspecialchars($expediente) . '</td>';

    // CURSO
    $h .= '<td style="font-size:13px">' . htmlspecialchars($curso) . '</td>';

    // VALORACIÓN GLOBAL
    $h .= '<td style="text-align:center">' . $valoracion_html . '</td>';

    // TICKET
    $h .= '<td style="font-size:13px;font-weight:600;color:#0f172a">'
        . htmlspecialchars($f['numero_ticket'] ?? '') . '</td>';

    return $h . '</tr>';
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coordinadores - Grupo ATU</title>

<!-- ============================================================
     FUENTES Y LIBRERÍAS EXTERNAS
============================================================ -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const miHeader = document.querySelector('.header');
    if (!miHeader) return;
    let ultimaPosicionScroll = 0;
    window.addEventListener("scroll", function() {
        let pos = window.pageYOffset || document.documentElement.scrollTop;
        if (pos > ultimaPosicionScroll && pos > 100) {
            miHeader.classList.add('hide');
        } else {
            miHeader.classList.remove('hide');
        }
        ultimaPosicionScroll = pos <= 0 ? 0 : pos;
    });
});
</script>

<style>
/* ── VARIABLES ──────────────────────────────────── */
:root{
    --primary:#0d9488;
    --primary-dark:#0f766e;
    --primary-bg:#ccfbf1;
    --bg:#f4f7fb;
    --bg-card:#fff;
    --border:#e2e8f0;
    --text:#0f172a;
    --text-secondary:#475569;
    --text-muted:#94a3b8;
    --radius:10px;
    --sidebar-width:240px;
    --sidebar-collapsed:68px;
    --header-height:72px;
    /* estado colors */
    --iniciada:#D12828;
    --en-curso:#87C6FF;
    --cerrada:#61FF9A;
    --enviada:#FFEC4D;
    --sin-asignar:#94a3b8;
    /* legacy aliases */
    --bg-body:#f4f7fb;
    --bg-sidebar:#0f172a;
    --text-primary:#0f172a;
    --radius-sm:6px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--text)}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-width);height:100vh;background:#0f172a;z-index:1000;display:flex;flex-direction:column;transition:all .3s cubic-bezier(.4,0,.2,1);overflow:hidden}
.sidebar.collapsed{width:var(--sidebar-collapsed)}
.sidebar-header{padding:22px 18px;display:flex;justify-content:center;border-bottom:1px solid rgba(255,255,255,.06)}
.sidebar-brand{display:flex;align-items:center;justify-content:center;width:100%;color:#fff;font-size:20px;font-weight:800;white-space:nowrap;overflow:hidden}
.sidebar-brand a{color:#fff;text-decoration:none;font-weight:800}
.short{display:none}
.sidebar.collapsed .full{display:none}
.sidebar.collapsed .short{display:inline}
.sidebar-menu{flex:1;padding:20px 10px;overflow:hidden}
.menu-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:14px;font-weight:500;cursor:pointer;transition:all .3s cubic-bezier(.4,0,.2,1);margin-bottom:4px;white-space:nowrap;overflow:hidden}
.menu-item i{width:20px;text-align:center;font-size:15px;flex-shrink:0}
.menu-item:hover{background:#1e293b;color:#fff}
.menu-item.active{background:#6366f1;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.4)}
.sidebar.collapsed .menu-item span{display:none}
.sidebar.collapsed .menu-item{justify-content:center;gap:0}
.sidebar-collapse-btn{width:100%;padding:14px;background:none;border:none;border-top:1px solid rgba(255,255,255,.06);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .3s}
.sidebar-collapse-btn:hover{background:#1e293b;color:#fff}
.linea-menu{border:none;border-top:1px solid rgba(255,255,255,.1);margin:8px 14px}

/* ── MAIN ── */
.main-content{margin-left:var(--sidebar-width);transition:all .3s cubic-bezier(.4,0,.2,1);min-height:100vh}
.sidebar.collapsed~.main-content{margin-left:var(--sidebar-collapsed)}

/* ── HEADER ── */
.header{height:var(--header-height);background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:400}
.header h1{margin:0;font-size:26px;font-weight:700}
.header-right{font-size:15px;display:flex;gap:8px;align-items:center;color:var(--text);font-weight:500}

/* ── HERO ── */
.hero{background:linear-gradient(135deg,#134e4a,#0d9488);padding:24px;color:#fff}
.hero h2{margin:0}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-top:18px}
.card{background:rgba(255,255,255,.15);padding:18px;border-radius:14px}
.card small{display:block;font-size:13px;opacity:.85}
.card b{font-size:28px;font-weight:800}

/* ── PAGE CONTENT ── */
.page-content{padding:24px 28px}

/* ── PANEL ── */
.panel,.estado-filter-panel,.filters-panel,.table-section{background:var(--bg-card);border-radius:14px;border:1px solid var(--border);margin-bottom:20px;overflow:visible}
.estado-filter-panel{padding:20px}
.panel-title{font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:14px;display:flex;align-items:center;gap:8px}

/* ── ESTADO FILTER BTNS ── */
.estado-filter-row{display:flex;gap:10px;flex-wrap:wrap}
.estado-btn{padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;border:2px solid transparent;display:inline-flex;align-items:center;gap:8px;color:#0f172a;transition:all .2s ease}
.estado-btn:hover{transform:translateY(-1px);filter:brightness(.95)}
.estado-btn.todos{border-color:var(--primary);background:var(--primary);color:#fff}
.estado-btn.iniciada{border-color:var(--iniciada);background:var(--iniciada);color:#fff}
.estado-btn.en_curso{border-color:var(--en-curso);background:var(--en-curso);color:#0f172a}
.estado-btn.cerrada{border-color:var(--cerrada);background:var(--cerrada);color:#0f172a}
.estado-btn.enviada{border-color:var(--enviada);background:var(--enviada);color:#0f172a}
.estado-btn.sin_asignar{border-color:var(--sin-asignar);background:var(--sin-asignar);color:#fff}
.estado-btn.active{transform:translateY(-1px);box-shadow:0 8px 18px rgba(0,0,0,.16);outline:2px solid rgba(255,255,255,.85);outline-offset:-4px}
.estado-btn .count{background:rgba(255,255,255,.35);color:inherit;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700}

/* ── TOOLBAR ── */
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:18px 20px}
.csv-btn{display:inline-flex;align-items:center;gap:7px;background:#0d9488;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;transition:background .2s}
.csv-btn:hover{background:#0f766e}
.search-box{display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:0 14px;height:42px;min-width:240px;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:border-color .2s}
.search-box:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px rgba(13,148,136,.12)}
.search-box input{border:none;outline:none;font-size:14px;color:var(--text);width:100%;background:transparent}
.search-box i{color:var(--text-muted);font-size:14px}

/* ── FILTROS ── */
.filters-header,.filters-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none}
.filters-header:hover{background:#fafafa;border-radius:14px 14px 0 0}
.filters-body{max-height:4000px;overflow:visible;transition:max-height .4s,padding .4s,opacity .3s;padding:0 20px 20px;opacity:1;position:relative;z-index:50}
.filters-body.collapsed{max-height:0;padding:0 20px;opacity:0;overflow:hidden;pointer-events:none}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.filter-group{display:flex;flex-direction:column;gap:4px;position:relative;z-index:50}
.filter-group label{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px}
.filter-group input,.filter-group select{height:38px;border:1.5px solid var(--border);border-radius:6px;padding:0 12px;font-size:13px;background:#fff;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s}
.filter-group input:focus,.filter-group select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(13,148,136,.1)}
.filter-actions{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}

/* ── BTNS ── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;font-family:'Inter',sans-serif}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-secondary{background:var(--bg);color:var(--text-secondary);border:1px solid var(--border)}
.btn-secondary:hover{background:#e2e8f0}

/* ── TOM SELECT ── */
.ts-wrapper{font-size:13px;position:relative}
.ts-control{min-height:38px;border:1.5px solid var(--border)!important;border-radius:6px!important;padding:6px 10px!important;box-shadow:none!important;background:#fff!important}
.ts-control input{font-size:13px!important}
.ts-dropdown{border:1px solid var(--border)!important;border-radius:8px!important;overflow:hidden;z-index:9999!important;background:#fff!important}
.ts-dropdown .option,.ts-dropdown .create{font-size:13px;padding:10px 12px}
.ts-dropdown .active{background:var(--primary-bg)!important;color:var(--primary-dark)!important}

/* ── TABLA ── */
.table-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap}
.table-header-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.table-header-left h3{font-size:15px;font-weight:700}
.table-header-left #tInfo{font-size:12px;color:var(--text-muted)}
.table-container{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{padding:13px 14px;background:#f8fafc;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);font-weight:700;white-space:nowrap;border-bottom:1px solid var(--border)}
td{padding:10px 12px;border-top:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
tr:hover td{background:#f0fdfa}
.badge{padding:5px 11px;border-radius:999px;color:#fff;font-size:12px;font-weight:700}

/* ── ROW COLORS ── */
.row-iniciada td,.row-en-curso td,.row-cerrada td,.row-enviada td,.row-sin-asignar td{background:#fff}

/* ── LEGEND ── */
.legend-bar{display:flex;gap:16px;padding:10px 20px;border-bottom:1px solid var(--border);font-size:12px;flex-wrap:wrap;color:var(--text-secondary)}
.legend-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px}
.legend-dot.iniciada{background:var(--iniciada)}
.legend-dot.en-curso{background:var(--en-curso)}
.legend-dot.cerrada{background:var(--cerrada)}
.legend-dot.enviada{background:var(--enviada)}
.legend-dot.sin-asignar{background:var(--sin-asignar)}

/* ── ACCIONES ── */
.actions{display:flex;gap:8px}
.btn-action,.action-btn-sm{width:34px;height:34px;display:flex;align-items:center;justify-content:center;text-decoration:none;position:relative;border-radius:8px;border:none;cursor:pointer;font-size:13px}
.view{background:#f0fdfa;color:#0d9488}
.del{background:#fff1f2;color:#dc2626}
.btn-action:hover::after{content:attr(data-tip);position:absolute;top:-36px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:5px 9px;font-size:11px;white-space:nowrap;border-radius:6px;z-index:9999}

/* ── ACTION TOOLTIPS ── */
.action-tooltip{position:relative;overflow:visible}
.action-tooltip::after{content:attr(data-tooltip);position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;font-size:10px;font-weight:600;padding:5px 8px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .18s ease,transform .18s ease;z-index:9999;box-shadow:0 4px 14px rgba(0,0,0,.18)}
.action-tooltip::before{content:'';position:absolute;bottom:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#0f172a;opacity:0;pointer-events:none;transition:opacity .18s ease}
.action-tooltip:hover::after,.action-tooltip:hover::before{opacity:1}

/* ── CELDAS EDITABLES ── */
.cell-input{width:100%;min-width:100px;border:1px solid transparent;border-radius:4px;padding:5px 8px;font-size:12px;font-family:'Inter',sans-serif;background:transparent;resize:none;line-height:1.4}
.cell-input:hover,.cell-input:focus{border-color:var(--border);background:#fff;outline:none}
.cell-input:focus{border-color:var(--primary);box-shadow:0 0 0 2px rgba(13,148,136,.1)}
.cell-input-display{min-height:32px;max-height:120px;overflow:auto}
.cell-textarea{width:100%;min-width:120px;max-width:320px;border:1px solid transparent;border-radius:4px;padding:5px 8px;font-size:12px;font-family:'Inter',sans-serif;background:transparent;resize:vertical;min-height:56px;line-height:1.4}
.cell-textarea:hover,.cell-textarea:focus{border-color:var(--border);background:#fff;outline:none}
.cell-textarea:focus{border-color:var(--primary);box-shadow:0 0 0 2px rgba(13,148,136,.1)}
.cell-textarea-50{min-height:80px}
.cell-select{width:100%;min-width:120px;padding:8px 6px;border:1px solid var(--border);border-radius:4px;font-size:11px;background:#fff;min-height:38px;line-height:1.4}
.cell-select option[value=""]{color:#cbd5e1;font-style:italic}
.cell-select-auto{width:auto;min-width:140px;max-width:100%;padding:8px 28px 8px 10px;border:1px solid var(--border);border-radius:6px;font-size:11px;background:#fff url('data:image/svg+xml;utf8,<svg fill="%23475569" height="16" viewBox="0 0 16 16" width="16" xmlns="http://www.w3.org/2000/svg"><path d="M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z"/></svg>') no-repeat right 8px center;background-size:12px;appearance:none;-webkit-appearance:none;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif}
.cell-select-auto:hover{border-color:#94a3b8;background-color:#f8fafc}
.cell-select-auto:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(13,148,136,.1)}
.cell-select-auto option[value=""]{color:#cbd5e1;font-style:italic}
.td-date .cell-input{min-width:120px}

/* ── SAVE INDICATOR ── */
.si{display:inline-flex;align-items:center;margin-left:4px;font-size:12px}
.si .ok{color:#10b981;display:none}
.si .er{color:#ef4444;display:none}
.si .ld{color:#94a3b8;display:none}

/* ── PAGINACIÓN ── */
.table-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted)}
.pagination{display:flex;gap:4px}
.page-btn{min-width:34px;height:34px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:600;cursor:pointer}
.page-btn.active{background:var(--primary);color:#fff;border-color:var(--primary)}

/* ── TOAST ── */
.toast{position:fixed;top:30px;right:30px;background:#fff;padding:16px 24px;border-radius:10px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);transform:translateX(400px);opacity:0;transition:all .4s;z-index:999999;border-left:4px solid #10b981}
.toast.show{transform:translateX(0);opacity:1}
.toast.error{border-left-color:#ef4444}
.toast.success{border-left-color:#10b981}

/* ── MODAL ── */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:99999;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.modal-content{background:#fff;width:100%;max-width:900px;border-radius:18px;box-shadow:0 30px 80px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column;max-height:90vh;animation:modalIn .25s cubic-bezier(.34,1.56,.64,1)}
.modal-header{padding:20px 26px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:#f8fafc}
.modal-header h3{margin:0;font-size:16px;font-family:'Inter',sans-serif}
.modal-footer{padding:14px 26px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#f8fafc}
.close-btn{background:rgba(0,0,0,.08);border:none;font-size:22px;cursor:pointer;color:#fff;line-height:1;padding:0;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;transition:background .2s,transform .15s}
.close-btn:hover{background:rgba(239,68,68,.85);transform:scale(1.1)}
.modal-body{padding:24px;overflow:auto}
.preview-sheet-wrap{overflow-x:auto}
.preview-sheet{border-collapse:collapse;font-size:12px;width:100%}
.preview-sheet th,.preview-sheet td{border:1px solid var(--border);padding:8px 12px;text-align:left;white-space:nowrap}
.preview-sheet th{background:#f8fafc;font-weight:700;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.4px}

/* ── TICKET LINK ── */
.ticket-link{color:var(--primary);text-decoration:none;font-size:12px;font-weight:600}
.ticket-link:hover{text-decoration:underline}

/* ── SCROLL ARROWS ── */
.scroll-arrow-fixed{position:fixed;z-index:900;width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;border:2px solid rgba(255,255,255,.9);box-shadow:0 3px 12px rgba(13,148,136,.4);display:flex;align-items:center;justify-content:center;font-size:15px;cursor:pointer;opacity:0;pointer-events:none;transition:opacity .2s ease}
.scroll-arrow-fixed.visible{opacity:1;pointer-events:auto}
.scroll-arrow-fixed:hover{background:var(--primary-dark)}
.action-btn-sm{background:#f0fdfa;color:#0d9488;border:1px solid #99f6e4;padding:4px 8px;border-radius:6px;cursor:pointer;transition:.2s;font-size:13px}
.action-btn-sm:hover{background:#0d9488;color:#fff}
.header.no-sticky{position:relative!important}

/* ── ANIMATIONS ── */
@keyframes modalIn{
    from{opacity:0;transform:scale(.93) translateY(20px)}
    to{opacity:1;transform:scale(1) translateY(0)}
}
@keyframes fadeSlideDown{
    from{opacity:0;transform:translateY(-18px)}
    to{opacity:1;transform:translateY(0)}
}
@keyframes fadeSlideUp{
    from{opacity:0;transform:translateY(18px)}
    to{opacity:1;transform:translateY(0)}
}
@keyframes fadeIn{
    from{opacity:0}
    to{opacity:1}
}
@keyframes slideInLeft{
    from{opacity:0;transform:translateX(-30px)}
    to{opacity:1;transform:translateX(0)}
}
@keyframes pulseGlow{
    0%,100%{box-shadow:0 0 0 0 rgba(13,148,136,.0)}
    50%{box-shadow:0 0 0 8px rgba(13,148,136,.12)}
}
@keyframes shimmer{
    0%{background-position:-600px 0}
    100%{background-position:600px 0}
}

/* Apply animations */
.header{animation:fadeSlideDown .4s ease both}
.sidebar{animation:slideInLeft .4s cubic-bezier(.4,0,.2,1) both}
.menu-item{transition:all .22s cubic-bezier(.4,0,.2,1)}
.menu-item:hover{transform:translateX(4px)}
.menu-item.active{animation:pulseGlow 2.5s ease infinite}

/* Hero cards entrance */
.hero-card{animation:fadeSlideUp .45s ease both}
.hero-card:nth-child(1){animation-delay:.05s}
.hero-card:nth-child(2){animation-delay:.12s}
.hero-card:nth-child(3){animation-delay:.19s}
.hero-card:nth-child(4){animation-delay:.26s}

/* Table rows entrance */
#tBody tr{animation:fadeIn .3s ease both}
#tBody tr:nth-child(1){animation-delay:.04s}
#tBody tr:nth-child(2){animation-delay:.08s}
#tBody tr:nth-child(3){animation-delay:.12s}
#tBody tr:nth-child(4){animation-delay:.16s}
#tBody tr:nth-child(5){animation-delay:.20s}
#tBody tr:nth-child(6){animation-delay:.24s}
#tBody tr:nth-child(7){animation-delay:.28s}
#tBody tr:nth-child(8){animation-delay:.32s}
#tBody tr:nth-child(9){animation-delay:.36s}
#tBody tr:nth-child(10){animation-delay:.40s}

/* Filter block */
#fb{animation:fadeSlideDown .35s ease both}

/* Buttons hover lift */
.btn{transition:transform .15s ease,box-shadow .15s ease}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(13,148,136,.18)}
.action-btn-sm{transition:all .18s ease}
.action-btn-sm:hover{transform:translateY(-1px)}
</style>
</head>
<body>

<button class="scroll-arrow-fixed" id="scrollArrowLeft" aria-label="Desplazar izquierda" tabindex="0"><i class="fas fa-chevron-left"></i></button>
<button class="scroll-arrow-fixed" id="scrollArrowRight" aria-label="Desplazar derecha" tabindex="0"><i class="fas fa-chevron-right"></i></button>

<!-- ============================================================
     TOAST DE NOTIFICACIONES
============================================================ -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<!-- ============================================================
     MODAL DE VISTA PREVIA
============================================================ -->
<div id="copyModal" class="modal-overlay" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-table" style="color:var(--primary);margin-right:8px"></i> Vista previa incidencia</h3>
            <button onclick="closeModal()" class="close-btn" style="background:#f1f5f9;color:#475569">&times;</button>
        </div>
        <div class="modal-body">
            <div class="preview-sheet-wrap" id="previewWrap"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="copyToExcelFromModal()"><i class="fas fa-copy"></i> Copiar</button>
        </div>
    </div>
</div>

<!-- MODAL DETALLE (botón ojo) -->
<div id="detalleModal" class="modal-overlay" onclick="if(event.target===this) closeDetalleModal()">
    <div class="modal-content" style="max-width:780px;border-radius:16px;overflow:hidden">
        <div class="modal-header" style="background:linear-gradient(135deg,#134e4a,#0d9488);color:#fff;padding:18px 26px;border-bottom:none">
            <h3 style="color:#fff;font-size:15px;font-weight:700;display:flex;align-items:center;gap:10px;margin:0">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.18)"><i class="fas fa-eye" style="font-size:13px"></i></span>
                Detalle del registro
            </h3>
            <button onclick="closeDetalleModal()" class="close-btn" style="color:rgba(255,255,255,.7);font-size:20px">&times;</button>
        </div>
        <div class="modal-body" id="detalleBody" style="padding:0;max-height:72vh;overflow-y:auto"></div>
        <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:12px 24px">
            <button onclick="closeDetalleModal()" style="font-size:14px;padding:10px 28px;font-weight:700;border-radius:10px;border:2px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:8px" onmouseover="this.style.background='#ef4444';this.style.color='#fff';this.style.borderColor='#ef4444'" onmouseout="this.style.background='#fff';this.style.color='#334155';this.style.borderColor='#cbd5e1'"><i class="fas fa-times"></i> Cerrar</button>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span class="full"><a href="https://incidencias.grupoatu.com/">Grupo ATU</a></span>
            <span class="short"><a href="https://incidencias.grupoatu.com/">ATU</a></span>
        </div>
    </div>
    <nav class="sidebar-menu">
        <a href="incidencias.php" class="menu-item"><i class="fa-solid fa-triangle-exclamation"></i><span>Tabla de Incidencias</span></a>
        <a href="estadisticas.php" class="menu-item"><i class="fas fa-chart-bar"></i><span>Estadisticas Incidencias</span></a>
        <hr class="linea-menu">
        <a href="valoraciones.php" class="menu-item"><i class="fa-solid fa-clipboard-list"></i><span>Tabla de Valoraciones</span></a>
        <a href="https://incidencias.grupoatu.com/osticket/estadisticas_valoraciones.php" class="menu-item"><i class="fas fa-chart-pie"></i><span>Estadisticas Valoraciones</span></a>
        <hr class="linea-menu">
        <a href="coordinadores.php" class="menu-item active"><i class="fa-solid fa-user-tie"></i><span>Tabla de Coordinadores</span></a>
        <a href="estadisticas_coordinadores.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Estadísticas Coordinadores</span></a>
    </nav>
    <button class="sidebar-collapse-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
</aside>

<!-- MAIN -->
<main class="main-content">
    <!-- HEADER -->
    <div class="header">
        <h1>Coordinadores</h1>
        <div style="display:flex;align-items:center;gap:20px;">
            <div class="header-right"><i class="fa-solid fa-calendar-days"></i><span id="clock"></span></div>
            <div style="display:flex;align-items:center;gap:12px;border-left:1px solid var(--border);padding-left:20px;">
                <span style="font-size:13px;color:var(--text-secondary);font-weight:600;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-user-circle" style="color:var(--primary);font-size:16px;"></i>
                    <?php echo htmlspecialchars($_SESSION['incidencias_user'] ?? $_SESSION['valoraciones_user'] ?? ''); ?>
                </span>
                <a href="?logout=1" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#fee2e2;color:#ef4444;text-decoration:none;transition:0.2s;" onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='#fee2e2';this.style.color='#ef4444';" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- HERO VERDE (color original) -->
    <div style="background:linear-gradient(135deg,#134e4a,#0d9488);padding:28px 32px;color:#fff">
        <h2 style="margin:0 0 20px 0;font-size:22px;font-weight:700">Panel de Coordinadores</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
            <div style="background:rgba(255,255,255,.15);padding:20px 24px;border-radius:14px">
                <div style="font-size:13px;opacity:.85;margin-bottom:4px">Total</div>
                <div style="font-size:32px;font-weight:800" id="heroTotal"><?= $total_tickets ?></div>
            </div>
            <div style="background:rgba(255,255,255,.15);padding:20px 24px;border-radius:14px">
                <div style="font-size:13px;opacity:.85;margin-bottom:4px">Hoy</div>
                <div style="font-size:32px;font-weight:800" id="heroHoy"><?= $tickets_hoy ?></div>
            </div>
            <div style="background:rgba(255,255,255,.15);padding:20px 24px;border-radius:14px">
                <div style="font-size:13px;opacity:.85;margin-bottom:4px">7 días</div>
                <div style="font-size:32px;font-weight:800" id="hero7dias"><?= $tickets_7dias ?></div>
            </div>
        </div>
    </div>

    <div class="page-content">
        
        <!-- ============================================================
             PANEL DE FILTROS AVANZADOS (colapsable)
        ============================================================ -->
        <div class="filters-panel">
            <div class="filters-header" 
			onclick="toggleFiltros()">
                <div><i class="fas fa-sliders-h" style="color:#6366f1"></i> Filtros</div>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="filters-body collapsed" id="fb">
                <form method="GET">
                    <input type="hidden" name="estado" value="<?= htmlspecialchars($f_estado) ?>">
                    <div class="filters-grid">
                        <!-- Filtro por rango de fechas -->
                        <div class="filter-group"><label>Desde</label><input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>"></div>
                        <div class="filter-group"><label>Hasta</label><input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>"></div>
                        
                        <!-- Búsqueda general -->
                        <div class="filter-group"><label>Búsqueda general</label><input type="text" name="buscar" value="<?= htmlspecialchars($f_buscar) ?>" placeholder="Expediente, tutor, empresa..."></div>
                        
                        <!-- Filtros dinámicos según campos del formulario 22 -->
                        <?php foreach ($campos_form22 as $cf22fil): ?>
                            <?= render_select_filtro_multi(
                                $cf22fil['name'],
                                $cf22fil['label'],
                                $filtros_individuales[$cf22fil['name']] ?? [],
                                $opciones_filtros[$cf22fil['name']] ?? []
                            ) ?>
                        <?php endforeach; ?>
                        
                        <!-- Botones de filtro -->
                        <div class="filter-group" style="justify-content:flex-end">
                            <div style="display:flex;gap:8px;margin-top:auto;flex-wrap:wrap">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                                <a href="?" class="btn btn-secondary"><i class="fas fa-undo"></i> Limpiar</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- ============================================================
             TABLA DE DATOS
        ============================================================ -->
        <div class="table-section" id="tableSection">
            
            <!-- Header de la tabla con acciones -->
            <div class="table-header">
                <div class="table-header-left">
                    <h3>Listado</h3>
                    <span id="tInfo"><?= $resultado ? $resultado->num_rows : 0 ?>/<?= $total_resultados ?></span>
                    <a href="?<?= http_build_query(array_merge($params_pag,['excel'=>1])) ?>" class="btn btn-primary">Exportar CSV</a>
                    <input type="file" id="importFile" accept=".csv,.xlsx,.ods" style="display:none" onchange="importar(this)">
                    <button class="btn btn-primary" onclick="document.getElementById('importFile').click()">Importar</button>
                </div>
                <!-- Búsqueda rápida en tabla -->
                <div style="position:relative">
                    <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px"></i>
                    <input type="text" placeholder="Buscar..." id="tSearch" oninput="buscar()" style="height:34px;width:220px;border:1px solid #e2e8f0;border-radius:6px;padding:0 10px 0 30px;font-size:12px">
                </div>
            </div>
            
            <!-- Leyenda de colores -->
            
            <!-- Contenedor con scroll horizontal -->
            <div class="table-container" id="tc">
                <table>
                    <thead>
                        <tr>
                            <th>Acciones</th>
                            <th>Fecha</th>
                            <th>Expediente</th>
                            <th>Curso</th>
                            <th>Val. Equipo Docente</th>
                            <th>Ticket</th>
                        </tr>
                    </thead>
                    <tbody id="tb">
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($fila = $resultado->fetch_assoc()): ?>
                                <?= render_fila_coordinadores($fila, $mapa_estados, $campos_form22) ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8">No se encontraron registros</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer de la tabla con paginación -->
            <div class="table-footer">
                <div>Pág <?= $f_pagina ?>/<?= $total_paginas ?> · <?= $total_resultados ?> total</div>
                <div class="pagination">
                    <?php if ($f_pagina > 1): ?>
                        <a href="<?= url_pag(1,$params_pag) ?>" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                        <a href="<?= url_pag($f_pagina-1,$params_pag) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <button class="page-btn active"><?= $f_pagina ?></button>
                    <?php if ($f_pagina < $total_paginas): ?>
                        <a href="<?= url_pag($f_pagina+1,$params_pag) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                        <a href="<?= url_pag($total_paginas,$params_pag) ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</main>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
/* ============================================================
   RELOJ EN TIEMPO REAL
============================================================ */
function clock(){
    var n=new Date();
    document.getElementById('clock').innerHTML=
        String(n.getDate()).padStart(2,'0')+'/'+
        String(n.getMonth()+1).padStart(2,'0')+'/'+
        n.getFullYear()+', '+
        String(n.getHours()).padStart(2,'0')+':'+
        String(n.getMinutes()).padStart(2,'0')+':'+
        String(n.getSeconds()).padStart(2,'0');
}
clock(); setInterval(clock,1000);

/* ============================================================
   MOSTRAR NOTIFICACIÓN TOAST
============================================================ */
function showToast(m,t){
    var e=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=m;
    e.querySelector('i').className=t==='success'?'fas fa-check-circle':'fas fa-exclamation-circle';
    e.className='toast '+t+' show';
    setTimeout(function(){e.classList.remove('show')},3000);
}

/* ============================================================
   GUARDAR CAMPO (AJAX)
   Envía el valor editado al servidor para actualizar BD
============================================================ */
function guardar(el,campo,tid){
    var val=el.value,td=el.closest('td'),si=td?td.querySelector('.si'):null;
    
    // Mostrar indicador de carga
    if(si){
        si.querySelector('.ok').style.display='none';
        si.querySelector('.er').style.display='none';
        si.querySelector('.ld').style.display='inline';
    }
    
    var fd=new FormData();
    fd.append('accion_guardar','1');
    fd.append('ticket_id',tid);
    fd.append('campo',campo);
    fd.append('valor',val);
    
    fetch('coordinadores.php',{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(si){
            si.querySelector('.ld').style.display='none';
            (d.ok?si.querySelector('.ok'):si.querySelector('.er')).style.display='inline';
            setTimeout(function(){
                si.querySelector('.ok').style.display='none';
                si.querySelector('.er').style.display='none';
            },2000);
        }
        if(d.ok){
            showToast('Guardado','success');
            
            // Si se cambió el estado, actualizar color de la fila
            if(campo==='estado_ticket'){
                var tr=el.closest('tr');
                tr.className='';
                var m={6:'row-iniciada',7:'row-en-curso',8:'row-cerrada',9:'row-enviada'};
                if(val&&m[val])tr.classList.add(m[val]);
                else tr.classList.add('row-sin-asignar');
            }
        } else {
            showToast(d.msg||'Error','error');
        }
    }).catch(function(e){
        if(si){
            si.querySelector('.ld').style.display='none';
            si.querySelector('.er').style.display='inline';
        }
        showToast(e.message||'Error','error');
    });
}

/* ============================================================
   RECARGAR SECTORES AL CAMBIAR PLAN EN UNA FILA
   Cuando el usuario cambia el plan de una fila, hace AJAX
   al endpoint ?ajax_sectores_fila&plan=X y actualiza cada
   columna de sector solo con las opciones que le corresponden.
============================================================ */
function recargarSectoresFila(planSelect) {
    var plan = planSelect.value;
    var tid  = planSelect.getAttribute('data-tid');
    var tr   = planSelect.closest('tr');
    if (!tr) return;

    // Los 4 selects de sector de esta fila, indexados por su nombre de columna
    var sectorSelects = tr.querySelectorAll('select[data-sector-select]');

    // Deshabilitar mientras carga
    sectorSelects.forEach(function(sel) { sel.disabled = true; });

    fetch('coordinadores.php?ajax_sectores_fila=1&plan=' + encodeURIComponent(plan))
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.ok) {
            sectorSelects.forEach(function(sel){ sel.disabled = false; });
            return;
        }

        sectorSelects.forEach(function(sel) {
            var columna      = sel.getAttribute('data-sector-select'); // ej. 'sector_labora'
            var opciones     = d[columna] || [];                        // lista para esa columna
            var valorActual  = sel.value;

            // Repoblar el select
            sel.innerHTML = '<option value=""></option>';
            opciones.forEach(function(op) {
                var opt = document.createElement('option');
                opt.value       = op;
                opt.textContent = op;
                if (op === valorActual) opt.selected = true;
                sel.appendChild(opt);
            });
            sel.disabled = false;

            // Si el valor guardado ya no está en las nuevas opciones → limpiar y guardar vacío en BD
            if (valorActual !== '' && opciones.indexOf(valorActual) === -1) {
                sel.value = '';
                var fd = new FormData();
                fd.append('accion_guardar', '1');
                fd.append('ticket_id', tid);
                fd.append('campo', columna);
                fd.append('valor', '');
                fetch('coordinadores.php', { method: 'POST', body: fd });
            }
        });
    })
    .catch(function() {
        sectorSelects.forEach(function(sel){ sel.disabled = false; });
    });
}

/* ============================================================
   BÚSQUEDA EN TABLA (filtrado del lado del cliente)
============================================================ */
function buscar(){
    var q=document.getElementById('tSearch').value.toLowerCase(),v=0;
    document.querySelectorAll('#tb tr').forEach(function(r){
        var s=r.textContent.toLowerCase().includes(q);
        r.style.display=s?'':'none';
        if(s)v++;
    });
    document.getElementById('tInfo').textContent=v+'/<?= $total_resultados ?>';
}

/* ============================================================
   IMPORTAR ARCHIVO
============================================================ */
function importar(input){
    if(!input.files||!input.files[0])return;
    if(!confirm('¿Importar '+input.files[0].name+'?')){input.value='';return;}
    
    var fd=new FormData();
    fd.append('archivo_excel',input.files[0]);
    showToast('Procesando...','success');
    
   fetch('importar_excel_coordinadores.php',{method:'POST',body:fd})
    .then(function(r){
        // Verificar que la respuesta es texto antes de parsear JSON
        if (!r.ok) {
            throw new Error('HTTP Error: ' + r.status);
        }
        return r.text(); // Obtener como texto primero
    })
    .then(function(texto){
        // Debug: mostrar lo que recibimos
        console.log('Respuesta recibida:', texto);
        
        // Intentar parsear JSON
        try {
            var d = JSON.parse(texto);
            if(d.ok){
                showToast(d.msg||'Importación completada','success');
                setTimeout(function(){location.reload()},2000);
            } else {
                showToast(d.msg||'Error al importar','error');
            }
        } catch(e) {
            console.error('Error al parsear JSON:', e);
            console.error('Respuesta recibida:', texto);
            showToast('Error: Respuesta inválida del servidor','error');
        }
        input.value='';
    })
    .catch(function(e){
        console.error('Error en fetch:', e);
        showToast(e.message||'Error al importar','error');
        input.value='';
    });
}
	
/* ============================================================
   ARRASTRAR SCROLL HORIZONTAL CON MOUSE
============================================================ */
var tc=document.getElementById('tc'),drag=false,sx,sl;
tc.addEventListener('mousedown',function(e){
    if(['INPUT','SELECT','TEXTAREA','A','BUTTON'].includes(e.target.tagName))return;
    drag=true;sx=e.pageX-tc.offsetLeft;sl=tc.scrollLeft;
});
tc.addEventListener('mousemove',function(e){
    if(!drag)return;
    e.preventDefault();
    tc.scrollLeft=sl-(e.pageX-tc.offsetLeft-sx);
});
document.addEventListener('mouseup',function(){drag=false});

/* ============================================================
   FLECHAS DE SCROLL HORIZONTAL (posicionamiento dinámico)
   Las flechas aparecen solo cuando hay scroll disponible
   y se posicionan dinámicamente según la visibilidad de la tabla
============================================================ */
(function(){
    var container=document.getElementById('tc'),
        section=document.getElementById('tableSection'),
        arrowL=document.getElementById('scrollArrowLeft'),
        arrowR=document.getElementById('scrollArrowRight'),
        scrollTimer=null,
        SPEED=18;
    
    // Calcular scroll máximo disponible
    function maxScroll(){return container.scrollWidth-container.clientWidth}
    
    // Obtener límites visibles de la tabla en viewport
    function getTableVisibleBounds(){
        var rect=section.getBoundingClientRect(),vpH=window.innerHeight,visTop=Math.max(rect.top,0),visBottom=Math.min(rect.bottom,vpH);
        if(visBottom<=visTop)return null;
        return{top:visTop,bottom:visBottom,midY:(visTop+visBottom)/2}
    }
    
    // Obtener ancho del sidebar
    function getSidebarWidth(){
        var sb=document.getElementById('sidebar');
        if(!sb)return 0;
        return sb.getBoundingClientRect().width
    }
    
    // Posicionar flechas según scroll y visibilidad
    function positionArrows(){
        var bounds=getTableVisibleBounds();
        if(!bounds){arrowL.classList.remove('visible');arrowR.classList.remove('visible');return}
        var m=maxScroll(),s=container.scrollLeft,sidebarW=getSidebarWidth(),arrowY=bounds.midY-20;
        arrowY=Math.max(bounds.top+4,Math.min(arrowY,bounds.bottom-44));
        if(m>0&&s>2){arrowL.style.top=arrowY+'px';arrowL.style.left=(sidebarW+6)+'px';arrowL.classList.add('visible')}else{arrowL.classList.remove('visible')}
        if(m>0&&s<m-2){arrowR.style.top=arrowY+'px';arrowR.style.right='6px';arrowR.style.left='auto';arrowR.classList.add('visible')}else{arrowR.classList.remove('visible')}
    }
    
    // Iniciar scroll continuo
    function startScroll(dir){
        stopScroll();
        container.scrollLeft+=dir*SPEED;
        positionArrows();
        scrollTimer=setInterval(function(){container.scrollLeft+=dir*SPEED;positionArrows()},16)
    }
    
    // Detener scroll continuo
    function stopScroll(){if(scrollTimer){clearInterval(scrollTimer);scrollTimer=null}}
    
    // Eventos de mouse
    arrowL.addEventListener('mousedown',function(e){e.preventDefault();startScroll(-1)});
    arrowR.addEventListener('mousedown',function(e){e.preventDefault();startScroll(1)});
    document.addEventListener('mouseup',stopScroll);
	// Doble clic = scroll al extremo
arrowL.addEventListener('dblclick', function(e){
    e.preventDefault();
    stopScroll();
    container.scrollTo({ left: 0, behavior: 'smooth' });
    positionArrows();
});

arrowR.addEventListener('dblclick', function(e){
    e.preventDefault();
    stopScroll();
    container.scrollTo({ left: container.scrollWidth, behavior: 'smooth' });
    positionArrows();
});
    
    // Eventos de teclado (accesibilidad)
    arrowL.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();startScroll(-1)}});
    arrowL.addEventListener('keyup',function(e){if(e.key==='Enter'||e.key===' ')stopScroll()});
    arrowR.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();startScroll(1)}});
    arrowR.addEventListener('keyup',function(e){if(e.key==='Enter'||e.key===' ')stopScroll()});
    
    // Eventos táctiles
    arrowL.addEventListener('touchstart',function(e){e.preventDefault();startScroll(-1)},{passive:false});
    arrowR.addEventListener('touchstart',function(e){e.preventDefault();startScroll(1)},{passive:false});
    document.addEventListener('touchend',stopScroll);
    document.addEventListener('touchcancel',stopScroll);
    
    // Actualizar posición en varios eventos
    container.addEventListener('scroll',positionArrows);
    window.addEventListener('scroll',positionArrows,{passive:true});
    window.addEventListener('resize',positionArrows);
    if(typeof ResizeObserver!=='undefined')new ResizeObserver(positionArrows).observe(container);
    positionArrows();
    window.addEventListener('load',function(){setTimeout(positionArrows,100);setTimeout(positionArrows,500)});
    
    // Observar cambios en el sidebar (colapsar/expandir)
    var sidebar=document.getElementById('sidebar');
    if(sidebar){
        var mo=new MutationObserver(function(){setTimeout(positionArrows,350)});
        mo.observe(sidebar,{attributes:true,attributeFilter:['class']});
    }
})();

/* =======================================================
    LÓGICA JAVASCRIPT PARA FILTROS DEPENDIENTES
======================================================= */

// Almacenará las instancias de TomSelect para poder manipularlas
const tomSelectInstances = {};

function updateDependentFilter(triggerFilterName, dependentFilterName) {
    const triggerSelect = tomSelectInstances[triggerFilterName];
    if (!triggerSelect) return;

    const triggerValues = triggerSelect.getValue();
    let url = `?ajax_filtro_opciones=1&campo=${dependentFilterName}`; // Usamos URL relativa

    if (triggerValues.length > 0) {
        const params = new URLSearchParams();
        triggerValues.forEach(val => params.append(`filter_${triggerFilterName}[]`, val));
        url += '&' + params.toString();
    }

    const dependentSelect = tomSelectInstances[dependentFilterName];
    if (!dependentSelect) return;

    dependentSelect.lock();
    dependentSelect.clearOptions();
    dependentSelect.addOption({value: '', text: 'Cargando...'});
    dependentSelect.refreshOptions(true);
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
    dependentSelect.unlock();
    const currentSelectedValues = dependentSelect.getValue(); // Guardamos lo que estaba seleccionado ANTES
    dependentSelect.clearOptions(); // Limpiamos todo

    if (data && data.ok && Array.isArray(data.opciones)) {
        // Añadimos las nuevas opciones que nos ha dado el PHP
        dependentSelect.addOptions(data.opciones.map(opt => ({value: opt, text: opt})));

        // Ahora, intentamos restaurar los valores que sigan siendo válidos
        const valuesToRestore = [];
        if (Array.isArray(currentSelectedValues)) {
            currentSelectedValues.forEach(val => {
                if (data.opciones.includes(val)) {
                    valuesToRestore.push(val);
                }
            });
        }

        if (valuesToRestore.length > 0) {
            dependentSelect.setValue(valuesToRestore, true); // true = no disparar onChange
        }
    } else {
        // Si el PHP no devuelve lo que esperamos, mostramos un mensaje
        dependentSelect.addOption({value: '', text: 'No hay opciones'});
        console.warn('Respuesta del servidor no válida para filtros:', data);
    }
})
.catch(e => {
    // Este catch ahora solo se ejecutará para errores de red REALES o de parseo JSON
    console.error("Error en la petición de filtros:", e);
    dependentSelect.unlock();
    dependentSelect.clearOptions();
    dependentSelect.addOption({value: '', text: 'Error de red'});
});
}

// Inicialización de todos los TomSelect 
document.querySelectorAll('.filter-select-multi').forEach(function(el) {
    const filterName = el.dataset.filterName;
    if (!filterName) return;

    const ts = new TomSelect(el, {
        plugins:['remove_button'],
        create:false,
        allowEmptyOption:true,
        placeholder:el.dataset.placeholder||'— Selecciona -',
        maxOptions:5000,
        closeAfterSelect:false,
        hideSelected:false,
        searchField:['text'],
        sortField:[{field:'text', direction:'asc'}],
        dropdownParent:'body',
        onInitialize: function() {
            tomSelectInstances[filterName] = this;

            // Si este es el selector de 'plan' y ya tiene un valor al cargar la página,
            // disparamos la actualización de 'sector' inmediatamente.
            if (filterName === 'plan' && this.getValue().length > 0) {
                updateDependentFilter('plan', 'sector');
            }
        },
        onChange: function(values) {
            // Esta parte es para cuando el usuario cambia el valor manualmente, DESPUÉS de que la página ha cargado.
            // ¡IMPORTANTE! El botón "Filtrar" no dispara esto, por eso necesitamos el onInitialize.
            if (filterName === 'plan') {
                updateDependentFilter('plan', 'sector');
            }
        }
    });
});
	
/* ============================================================
   MODAL DE INFORMACIÓN
============================================================ */
let currentModalData=null;

// Escapar HTML para evitar XSS
function escapeHtml(t){
    if(t===null||t===undefined)return'';
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')
}

// Limpiar texto para Excel (eliminar caracteres problemáticos)
function cleanForExcel(s){
    if(s===null||s===undefined) return '';
    return String(s)
        .replace(/\u00A0/g, ' ')
        .replace(/[¤]/g, '')
        .replace(/\r\n/g, ' ')
        .replace(/\n/g, ' ')
        .replace(/\r/g, ' ')
        .replace(/\t/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

// Obtener columnas en el orden exacto para Excel
function getExactExcelColumns(d){
    return[
        cleanForExcel(d.fecha),
        cleanForExcel(d.expediente),
        cleanForExcel(d.plan),
        cleanForExcel(d.sector_labora),
        cleanForExcel(d.sector_cyl),
        cleanForExcel(d.sector_asturias),
        cleanForExcel(d.sector_estatal),
        cleanForExcel(d.tutor),
        cleanForExcel(d.nombre),
        cleanForExcel(d.apellidos),
        cleanForExcel(d.empresa),
        cleanForExcel(d.accion),
        cleanForExcel(d.grupo),
        cleanForExcel(d.curso),
        cleanForExcel(d.incidencia),
        cleanForExcel(d.detalles),
        cleanForExcel(d.razon),
        cleanForExcel(d.dificultad),
        cleanForExcel(d.datos),
        cleanForExcel(d.motivo),
        cleanForExcel(d.fotos),
        cleanForExcel(d.medidas),
        cleanForExcel(d.solucion),
        cleanForExcel(d.fechasol),
        cleanForExcel(d.estado),
        cleanForExcel(d.ticket)
    ];
}

// Abrir modal con vista previa de la incidencia
function openInfoModal(btn){
    var raw=btn.getAttribute('data-info');
    if(!raw)return;
    currentModalData=JSON.parse(raw);
    
    var headers=[
        'FECHA','EXPEDIENTE','PLAN',
        'SECTOR LABORA','SECTOR CYL','SECTOR ASTURIAS','SECTOR ESTATAL',
        'TUTOR/A','NOMBRE','APELLIDOS','EMPRESA','ACCIÓN','GRUPO','CURSO',
        'INCIDENCIA','DETALLES','RAZÓN','DIFICULTAD','DATOS','MOTIVO','FOTOS','MEDIDAS','SOLUCIÓN','FECHA SOLUCIÓN',
        'ESTADO','TICKET'
    ];
    var values=getExactExcelColumns(currentModalData);
    
    var html='<table class="preview-sheet"><thead><tr>';
    headers.forEach(function(h){html+='<th>'+escapeHtml(h)+'</th>'});
    html+='</tr></thead><tbody><tr>';
    values.forEach(function(v){html+='<td>'+escapeHtml(v||'-')+'</td>'});
    html+='</tr></tbody></table>';
    
    document.getElementById('previewWrap').innerHTML=html;
    document.getElementById('copyModal').style.display='flex';
}

// Cerrar modal
function closeModal(){
    document.getElementById('copyModal').style.display='none'
}

/* ============================================================
   COPIAR DATOS AL PORTAPAPELES (formato Excel)
============================================================ */
async function copyToExcelFromModal(){
    if(!currentModalData) return;
    
    const headers = [
        'FECHA','EXPEDIENTE','PLAN',
        'SECTOR LABORA','SECTOR CYL','SECTOR ASTURIAS','SECTOR ESTATAL',
        'TUTOR/A','NOMBRE','APELLIDOS','EMPRESA','ACCIÓN','GRUPO','CURSO',
        'INCIDENCIA','DETALLES','RAZÓN','DIFICULTAD','DATOS','MOTIVO','FOTOS','MEDIDAS','SOLUCIÓN','FECHA SOLUCIÓN',
        'ESTADO','TICKET'
    ];
    
    const values = getExactExcelColumns(currentModalData).map(function(v){
        return cleanForExcel(v);
    });
    
    const plainText = values.join('\t');
    
    let html = '<table border="1" cellspacing="0" cellpadding="4">';
    html += '<tbody><tr>';
    values.forEach(function(v){
        html += '<td>' + escapeHtml(v || '') + '</td>';
    });
    html += '</tr></tbody></table>';
    
    try {
        if (navigator.clipboard && window.ClipboardItem) {
            const item = new ClipboardItem({
                'text/plain': new Blob([plainText], { type: 'text/plain' }),
                'text/html': new Blob([html], { type: 'text/html' })
            });
            await navigator.clipboard.write([item]);
            showToast('Información copiada','success');
        } else {
            fallbackCopyHtmlTable(html, plainText);
        }
    } catch (e) {
        fallbackCopyHtmlTable(html, plainText);
    }
}

// Fallback para navegadores que no soportan ClipboardItem
function fallbackCopyHtmlTable(html, plainText){
    const div = document.createElement('div');
    div.contentEditable = true;
    div.style.position = 'fixed';
    div.style.left = '-9999px';
    div.style.top = '-9999px';
    div.innerHTML = html;
    document.body.appendChild(div);
    const range = document.createRange();
    range.selectNodeContents(div);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch(e) {
        ok = false;
    }
    sel.removeAllRanges();
    document.body.removeChild(div);
    if (ok) {
        showToast('Información copiada','success');
    } else {
        fallbackCopy(plainText);
    }
}

// Fallback adicional para copiar solo texto plano
function fallbackCopy(text){
    var ta=document.createElement('textarea');
    ta.value=text;
    ta.setAttribute('readonly','');
    ta.style.position='fixed';
    ta.style.top='-9999px';
    ta.style.left='-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0,999999);
    try{
        document.execCommand('copy');
        showToast('Copiado en columnas para Excel','success');
    }catch(e){
        showToast('No se pudo copiar','error');
    }
    document.body.removeChild(ta);
}

/* ============================================================
   AUTO-GROW TEXTAREA (ajustar altura según contenido)
============================================================ */
function autoGrow(el){
    if(el.classList.contains('cell-textarea-50')) return;
    el.style.height='auto';
    el.style.height=el.scrollHeight+'px';
}

/* ============================================================
   ACTUALIZAR CONTADORES HERO (sin recargar página)
============================================================ */
function actualizarContadoresHero() {
    fetch('coordinadores.php?ajax_hero_counters=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) {
                var elTotal  = document.getElementById('heroTotal');
                var elHoy    = document.getElementById('heroHoy');
                var el7dias  = document.getElementById('hero7dias');
                if (elTotal)  animarNumero(elTotal,  d.total);
                if (elHoy)    animarNumero(elHoy,    d.hoy);
                if (el7dias)  animarNumero(el7dias,  d.siete);
            }
        })
        .catch(function(){});
}

function animarNumero(el, nuevoValor) {
    var actual = parseInt(el.textContent, 10) || 0;
    var diff = nuevoValor - actual;
    if (diff === 0) return;
    var pasos = 12;
    var paso = 0;
    var intervalo = setInterval(function() {
        paso++;
        el.textContent = Math.round(actual + (diff * paso / pasos));
        if (paso >= pasos) {
            el.textContent = nuevoValor;
            clearInterval(intervalo);
        }
    }, 30);
}

/* ============================================================
   DUPLICAR TICKET
============================================================ */
function duplicarTicket(tid, btn){
    if(!confirm('¿Quieres duplicar este ticket? Se creará un ticket nuevo con la misma información.')){
        return;
    }
    
    var fd = new FormData();
    fd.append('accion_duplicar_ticket', '1');
    fd.append('ticket_id', tid);
    
    fetch('coordinadores.php', {
        method: 'POST',
        body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.ok){
            showToast(d.msg || 'Ticket duplicado', 'success');
            actualizarContadoresHero();
            setTimeout(function(){
                location.reload();
            }, 1200);
        } else {
            showToast(d.msg || 'Error al duplicar', 'error');
        }
    })
    .catch(function(e){
        showToast(e.message || 'Error al duplicar', 'error');
    });
}

/* ============================================================
   BORRAR TICKET
============================================================ */
function borrarTicket(tid, btn){
    if(!confirm('¿Seguro que quieres borrar este ticket? Se eliminará completamente.')){
        return;
    }
    
    if(!confirm('Confirmación final: se borrará el ticket de la página y de osTicket. ¿Continuar?')){
        return;
    }
    
    var fd = new FormData();
    fd.append('accion_borrar_ticket', '1');
    fd.append('ticket_id', tid);
    
    fetch('coordinadores.php', {
        method: 'POST',
        body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if(d.ok){
            showToast(d.msg || 'Ticket borrado', 'success');
            
            // Eliminar fila de la tabla
            var tr = btn.closest('tr');
            if(tr){
                tr.remove();
            }
            
            // Actualizar contador tabla
            var info = document.getElementById('tInfo');
            if(info){
                var txt = info.textContent.split('/');
                if(txt.length === 2){
                    var visibles = parseInt(txt[0], 10);
                    var total = parseInt(txt[1], 10);
                    if(!isNaN(visibles) && visibles > 0) visibles--;
                    if(!isNaN(total) && total > 0) total--;
                    info.textContent = visibles + '/' + total;
                }
            }

            // Actualizar contadores del hero
            actualizarContadoresHero();
        } else {
            showToast(d.msg || 'Error al borrar', 'error');
        }
    })
    .catch(function(e){
        showToast(e.message || 'Error al borrar', 'error');
    });
}

function toggleFiltros() {
    const fb = document.getElementById('fb');
    const header = document.querySelector('.header');

    fb.classList.toggle('collapsed');

    if (!fb.classList.contains('collapsed')) {
        header.classList.add('no-sticky');
    } else {
        header.classList.remove('no-sticky');
    }
}	

/* ============================================================
   MODAL DETALLE (botón ojo)
============================================================ */
function openDetalleModal(btn) {
    var raw = btn.getAttribute('data-info');
    if (!raw) return;
    var data = JSON.parse(raw);

    var ocultar = ['Estado', 'Fecha Solución', 'estado', 'fecha solución', 'fecha solucion'];

    var visibles = Object.keys(data).filter(function(k) {
        return !ocultar.some(function(o){ return o.toLowerCase() === k.toLowerCase(); });
    });

    var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#e2e8f0">';

    visibles.forEach(function(k, i) {
        var v = String(data[k] ?? '');
        var isEmpty = v === '' || v === 'null' || v === 'undefined';

        var valHtml;
        if (!isEmpty && /^[1-5](\.\d+)?$/.test(v.trim()) && k.toLowerCase().includes('valorac')) {
            var num = parseFloat(v);
            var bg = num >= 4 ? '#10b981' : num >= 3 ? '#f59e0b' : '#ef4444';
            valHtml = '<span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:'+bg+';color:#fff;font-weight:800;font-size:15px;box-shadow:0 2px 8px '+bg+'66">'+escapeHtml(v)+'</span>';
        } else {
            valHtml = '<span style="color:'+(isEmpty?'#cbd5e1':'#0f172a')+';font-size:13px;font-weight:'+(isEmpty?'400':'600')+';line-height:1.5">'+(isEmpty?'—':escapeHtml(v))+'</span>';
        }

        var bg = i % 2 === 0 ? '#ffffff' : '#f0fdfa';
        html += '<div style="background:'+bg+';padding:14px 18px;display:flex;flex-direction:column;gap:6px;min-height:68px;border-bottom:1px solid #e2e8f0">'
              + '<div style="font-size:10.5px;font-weight:700;color:#0d9488;text-transform:uppercase;letter-spacing:.6px;line-height:1.3">'+escapeHtml(k)+'</div>'
              + '<div>'+valHtml+'</div>'
              + '</div>';
    });

    // Si número impar, rellenar celda vacía para que la cuadrícula quede completa
    if (visibles.length % 2 !== 0) {
        html += '<div style="background:#f8fafc"></div>';
    }

    html += '</div>';
    document.getElementById('detalleBody').innerHTML = html;
    document.getElementById('detalleModal').style.display = 'flex';
}

function closeDetalleModal() {
    document.getElementById('detalleModal').style.display = 'none';
}
</script>
</body>
</html>