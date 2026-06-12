<?php
/******************************************************************
 * valoraciones.php — CON MINI LOGIN INTEGRADO
 ******************************************************************/
error_reporting(E_ALL);
ini_set('display_errors',0);
ini_set('log_errors',1);
date_default_timezone_set('Europe/Madrid');

// ============================================================
// MINI LOGIN — Usuarios y Contraseñas
// ============================================================
session_start();
require_once __DIR__ . '/auth_admin_sso.php';

// Array de usuarios permitidos (puedes agregar o eliminar)
$USUARIOS_VALORACIONES = [
    'IncidenciasAtu'   => 'D/*50smPm@7FPM@c£EUMU&',
    'Admin'            => '1,<X8r0.5(Tl03?-gq]giU',
];

// Cerrar sesión
if (isset($_GET['logout'])) {
    if (!empty($_SESSION['admin_sso'])) {
        admin_sso_logout();
    } else {
        session_destroy();
    }
    header('Location: valoraciones.php');
    exit;
}

// Procesar login
if (isset($_POST['_login_user'], $_POST['_login_password'])) {
    $u = trim($_POST['_login_user']);
    $p = $_POST['_login_password'];

    if (isset($USUARIOS_VALORACIONES[$u]) && $p === $USUARIOS_VALORACIONES[$u]) {
        if ($u === ADMIN_SSO_USER) {
            admin_sso_activate();
        } else {
            $_SESSION['valoraciones_auth'] = true;
            $_SESSION['valoraciones_user'] = $u;
        }
        header('Location: valoraciones.php');
        exit;
    } else {
        $login_error = 'Usuario o contraseña incorrectos.';
    }
}

// Verificar autenticación
if (empty($_SESSION['valoraciones_auth'])) {
    // Permitir peticiones AJAX (devuelven 401)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        isset($_GET['ajax_sector']) || 
        isset($_GET['ajax_filtro_opciones'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
        exit;
    }
    
    // Mostrar pantalla de login
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Login - Valoraciones ATU</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{
                font-family:'Inter',sans-serif;
                background:linear-gradient(135deg,#1e3a8a,#1d4ed8);
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
                border-color:#6366f1;
                box-shadow:0 0 0 3px rgba(99,102,241,.1);
            }
            .btn-login{
                width:100%;
                height:48px;
                background:#6366f1;
                color:#fff;
                border:none;
                border-radius:10px;
                font-size:15px;
                font-weight:700;
                cursor:pointer;
                transition:background .3s;
            }
            .btn-login:hover{
                background:#4f46e5;
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
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="login-header">
                <h1>🔐 Acceso Valoraciones</h1>
                <p>Introduce tus credenciales</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="error-msg">❌ <?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
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

if(!defined('INCLUDE_DIR')) define('INCLUDE_DIR', __DIR__.'/include/');
if(!defined('ROOT_DIR'))    define('ROOT_DIR',    __DIR__.'/');

require_once INCLUDE_DIR.'ost-config.php';
require_once ROOT_DIR.'main.inc.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(DBHOST,DBUSER,DBPASS,DBNAME);
$db->set_charset("utf8mb4");

/* ==========================================================
   HELPERS
========================================================== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function valor($v){
    $v = trim((string)$v);
    if($v === '') return '';
    if(substr($v,0,1) === '{'){
        $j = json_decode($v,true);
        if(is_array($j)) return trim(reset($j));
    }
    return $v;
}

function sat($n){
    $n = (int)$n;
    if($n <= 4) return '#dc2626';
    if($n <= 7) return '#d97706';
    return '#059669';
}

function get_array_param_val(string $key): array {
    $value = $_GET[$key] ?? [];
    if(!is_array($value)) $value = [$value];
    $out = [];
    foreach($value as $v){
        $v = trim((string)$v);
        if($v !== '') $out[] = $v;
    }
    return array_values(array_unique($out));
}

/* ==========================================================
   ELIMINAR
========================================================== */
if(isset($_GET['del'])){
    $ticket = (int)$_GET['del'];
    $db->query("DELETE FROM ost_form_entry_values WHERE entry_id IN(SELECT id FROM ost_form_entry WHERE object_id=$ticket)");
    $db->query("DELETE FROM ost_form_entry WHERE object_id=$ticket");
    $db->query("DELETE FROM ost_ticket WHERE ticket_id=$ticket");
    header("Location: valoraciones.php");
    exit;
}

/* ==========================================================
   CSV EXPORT
========================================================== */
if(isset($_GET['csv'])){
    header('Content-Type:text/csv; charset=utf-8');
    header('Content-Disposition:attachment; filename=valoraciones.csv');
    $out = fopen('php://output','w');
    fputcsv($out,['Fecha','Expediente','Empresa','Satisfaccion','Ticket'], ';');
    $csv = $db->query("
        SELECT t.number, t.created,
        MAX(CASE WHEN ff.name='expediente'   THEN fev.value END) expediente,
        MAX(CASE WHEN ff.name='empresa'      THEN fev.value END) empresa,
        MAX(CASE WHEN ff.name='satisfaccion' THEN fev.value END) satisfaccion
        FROM ost_ticket t
        JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id=16
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        GROUP BY t.ticket_id ORDER BY t.ticket_id DESC
    ");
    while($r = $csv->fetch_assoc()){
        fputcsv($out,[$r['created'],valor($r['expediente']),valor($r['empresa']),valor($r['satisfaccion']),$r['number']], ';');
    }
    fclose($out);
    exit;
}

/* ==========================================================
   AJAX: sector dependiente de plan
   Llamado con ?ajax_sector=1&plan[]=X&plan[]=Y
========================================================== */
if(isset($_GET['ajax_sector'])){
    header('Content-Type: application/json; charset=utf-8');

    $planes_sel = $_GET['plan'] ?? [];
    if(!is_array($planes_sel)) $planes_sel = [$planes_sel];
    $planes_sel = array_filter(array_map('trim', $planes_sel));

    $unicos = [];

    if(empty($planes_sel)){
        // Sin plan seleccionado → todos los sectores disponibles
        $res = $db->query("
            SELECT DISTINCT fev_s.value
            FROM ost_ticket t
            JOIN ost_form_entry fe  ON fe.object_id  = t.ticket_id AND fe.form_id = 16
            JOIN ost_form_entry_values fev_s ON fev_s.entry_id = fe.id
            JOIN ost_form_field ff_s ON ff_s.id = fev_s.field_id AND ff_s.name = 'sector'
            WHERE t.topic_id = 13
              AND fev_s.value IS NOT NULL AND TRIM(fev_s.value) != ''
            ORDER BY fev_s.value ASC
        ");
        if($res) while($row = $res->fetch_assoc()){
            $v = trim($row['value']);
            if($v !== '' && strtolower($v) !== 'false') $unicos[$v] = $v;
        }
    } else {
        // Con planes seleccionados → solo sectores que coexisten con esos planes en tickets reales
        $placeholders = implode(',', array_fill(0, count($planes_sel), '?'));
        $types        = str_repeat('s', count($planes_sel));

        $sql = "
            SELECT DISTINCT fev_s.value
            FROM ost_ticket t
            JOIN ost_form_entry fe     ON fe.object_id  = t.ticket_id AND fe.form_id = 16
            JOIN ost_form_entry_values fev_p ON fev_p.entry_id = fe.id
            JOIN ost_form_field ff_p   ON ff_p.id = fev_p.field_id AND ff_p.name = 'plan'
            JOIN ost_form_entry_values fev_s ON fev_s.entry_id = fe.id
            JOIN ost_form_field ff_s   ON ff_s.id = fev_s.field_id AND ff_s.name = 'sector'
            WHERE t.topic_id = 13
              AND fev_p.value IN ($placeholders)
              AND fev_s.value IS NOT NULL AND TRIM(fev_s.value) != ''
            ORDER BY fev_s.value ASC
        ";
        $stmt = $db->prepare($sql);
        if($stmt){
            $stmt->bind_param($types, ...$planes_sel);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()){
                $v = trim($row['value']);
                if($v !== '' && strtolower($v) !== 'false') $unicos[$v] = $v;
            }
            $stmt->close();
        }
    }

    natcasesort($unicos);
    echo json_encode(['ok' => true, 'opciones' => array_values($unicos)]);
    exit;
}

/* ==========================================================
   OPCIONES PARA FILTROS DESPLEGABLES
========================================================== */
function obtener_opciones_campo(mysqli $db, string $campo): array {
    $campo_safe = str_replace('`','',$campo);
    $unicos = [];
    // Intentar desde ost_form_field → lista osTicket
    $stmt = $db->prepare("SELECT type FROM ost_form_field WHERE name=? AND form_id=16 LIMIT 1");
    if($stmt){
        $stmt->bind_param('s',$campo);
        $stmt->execute();
        $field = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if($field && preg_match('/^list-(\d+)$/', $field['type'], $m)){
            $listId = (int)$m[1];
            $res = $db->query("SELECT value FROM ost_list_items WHERE list_id=$listId AND value IS NOT NULL AND TRIM(value)!='' ORDER BY sort ASC");
            if($res) while($row=$res->fetch_assoc()){
                $v = trim($row['value']);
                if($v!=='') $unicos[$v]=$v;
            }
            if(!empty($unicos)) return array_values($unicos);
        }
    }
    // Fallback: valores distintos de la tabla de entrada
    try {
        $res = $db->query("
            SELECT DISTINCT fev.value
            FROM ost_form_entry fe
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            WHERE fe.form_id=16 AND ff.name='{$campo_safe}'
            AND fev.value IS NOT NULL AND TRIM(fev.value)!=''
            ORDER BY fev.value ASC
        ");
        if($res) while($row=$res->fetch_assoc()){
            $v = trim(valor($row['value']));
            if($v!=='' && strtolower($v)!=='false') $unicos[$v]=$v;
        }
    } catch(Exception $e){}
    natcasesort($unicos);
    return array_values($unicos);
}

/* Obtener tutores dinámicamente desde la BD — field name='tutor_a', form_id=16, list-2 */
function obtener_tutores_valoraciones(mysqli $db): array {
    $unicos = [];

    try {
        // El campo tutor en form_id=16 se llama 'tutor_a' (type=list-2)
        $sqlField = "SELECT type FROM ost_form_field WHERE name = 'tutor_a' AND form_id = 16 LIMIT 1";
        $res   = $db->query($sqlField);
        $field = $res ? $res->fetch_assoc() : null;

        if ($field && preg_match('/^list-(\d+)$/', $field['type'], $m)) {
            $listId = (int)$m[1];
            $stmt = $db->prepare(
                "SELECT value FROM ost_list_items
                 WHERE list_id = ? AND value IS NOT NULL AND TRIM(value) != ''
                 ORDER BY sort ASC"
            );
            $stmt->bind_param('i', $listId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $v = trim((string)$row['value']);
                if ($v !== '') $unicos[$v] = $v;
            }
            $stmt->close();
        } else {
            // Fallback texto libre
            $result = $db->query(
                "SELECT DISTINCT tutor FROM ost_ticket__cdata
                 WHERE tutor IS NOT NULL AND TRIM(tutor) != ''
                 ORDER BY tutor ASC"
            );
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $v = trim((string)$row['tutor']);
                    if ($v !== '') $unicos[$v] = $v;
                }
            }
        }
    } catch (Exception $e) {}

    natcasesort($unicos);
    return array_values($unicos);
}

/* Nombres de los campos de preguntas largas en el formulario (form_id=16).
   Ajusta los 'name' si en tu BD difieren. */
$CAMPOS_PREGUNTAS = [
    'contenidos_actualizados'      => 'Contenidos actualizados (1-5)',
    'recursos_variados'            => 'Recursos variados (1-5)',
    'pruebas_acordes_nivel'        => 'Pruebas acordes al nivel (1-5)',
    'pruebas_acordes_temario'      => 'Pruebas acordes al temario (1-5)',
    'queja_contenidos'             => 'Quejas sobre contenidos',
    'mejoras_contenidos'           => 'Mejoras en contenidos',
    'utilidad_recursos'            => 'Utilidad de recursos (1-5)',
    'acompanamiento_coordinador'   => 'Acompañamiento coordinador/a (1-5)',
    'protocolo_tutorizacion'       => 'Protocolo de tutorización (1-5)',
    'formacion_atu'                => 'Formación ATU (1-5)',
    'equipo_coordinacion'          => 'Equipo de Coordinación (1-5)',
    'plataforma_moodle'            => 'Plataforma MOODLE (1-5)',
    'erp'                          => 'ERP (1-5)',
    'queja_recursos'               => 'Quejas sobre recursos',
    'mejoras_participacion'        => 'Mejoras para participación',
    'causas_no_finalizacion'       => 'Causas no finalización',
    'fechas_convenientes'          => 'Fechas convenientes',
    'falta_tiempo'                 => 'Falta de tiempo',
    'falta_motivacion'             => 'Falta de motivación',
    'problemas_familiares'         => 'Problemas familiares/salud',
    'perfil_inadecuado'            => 'Perfil inadecuado',
    'sin_habilidades_tec'          => 'Sin habilidades tecnológicas',
    'fallos_tecnicos'              => 'Fallos técnicos',
    'no_quiere_formacion'          => 'No quiere la formación',
    'justificacion_causas'         => 'Justificación causas',
    'mejoras_finalizacion'         => 'Mejoras tasa finalización',
];

$opciones_filtros = [
    'expediente'   => obtener_opciones_campo($db,'expediente'),
    'empresa'      => obtener_opciones_campo($db,'empresa'),
    'satisfaccion' => array_map('strval', range(1,10)),
    'plan'         => obtener_opciones_campo($db,'plan'),
    'sector'       => obtener_opciones_campo($db,'sector'),
    'tutor'        => obtener_tutores_valoraciones($db),
    'accion'       => obtener_opciones_campo($db,'accion'),
    'grupo'        => obtener_opciones_campo($db,'grupo'),
    'curso'        => obtener_opciones_campo($db,'curso'),
];

/* ==========================================================
   CAPTURAR FILTROS DESDE $_GET
========================================================== */
$f_desde    = trim($_GET['desde']   ?? '');
$f_hasta    = trim($_GET['hasta']   ?? '');
$f_buscar   = trim($_GET['buscar']  ?? '');
$f_sat_min  = trim($_GET['sat_min'] ?? '');
$f_sat_max  = trim($_GET['sat_max'] ?? '');
$f_pregunta_nombre = trim($_GET['pregunta_nombre'] ?? '');
$f_pregunta_valor  = trim($_GET['pregunta_valor']  ?? '');

$filtros = [
    'expediente'   => get_array_param_val('expediente'),
    'empresa'      => get_array_param_val('empresa'),
    'satisfaccion' => get_array_param_val('satisfaccion'),
    'plan'         => get_array_param_val('plan'),
    'sector'       => get_array_param_val('sector'),
    'tutor'        => get_array_param_val('tutor'),
    'accion'       => get_array_param_val('accion'),
    'grupo'        => get_array_param_val('grupo'),
    'curso'        => get_array_param_val('curso'),
];

/* ==========================================================
   CONSTRUCCIÓN SQL CON FILTROS
========================================================== */
$sql_where = " AND t.topic_id = 13";

if($f_desde !== ''){
    $d = $db->real_escape_string($f_desde);
    $sql_where .= " AND t.created >= '{$d} 00:00:00'";
}
if($f_hasta !== ''){
    $d = $db->real_escape_string($f_hasta);
    $sql_where .= " AND t.created <= '{$d} 23:59:59'";
}
if($f_buscar !== ''){
    $b = $db->real_escape_string($f_buscar);
    $sql_where .= " AND (
        t.number LIKE '%{$b}%'
        OR EXISTS(
            SELECT 1 FROM ost_form_entry fe2
            JOIN ost_form_entry_values fev2 ON fev2.entry_id=fe2.id
            WHERE fe2.object_id=t.ticket_id AND fe2.form_id=16
            AND fev2.value LIKE '%{$b}%'
        )
    )";
}

// Filtros de satisfacción numérica
if($f_sat_min !== '' && is_numeric($f_sat_min)){
    $mn = (int)$f_sat_min;
    $sql_where .= " AND (
        SELECT CAST(fev_s.value AS UNSIGNED)
        FROM ost_form_entry fe_s
        JOIN ost_form_entry_values fev_s ON fev_s.entry_id=fe_s.id
        JOIN ost_form_field ff_s ON ff_s.id=fev_s.field_id
        WHERE fe_s.object_id=t.ticket_id AND fe_s.form_id=16 AND ff_s.name='satisfaccion'
        LIMIT 1
    ) >= {$mn}";
}
if($f_sat_max !== '' && is_numeric($f_sat_max)){
    $mx = (int)$f_sat_max;
    $sql_where .= " AND (
        SELECT CAST(fev_s.value AS UNSIGNED)
        FROM ost_form_entry fe_s
        JOIN ost_form_entry_values fev_s ON fev_s.entry_id=fe_s.id
        JOIN ost_form_field ff_s ON ff_s.id=fev_s.field_id
        WHERE fe_s.object_id=t.ticket_id AND fe_s.form_id=16 AND ff_s.name='satisfaccion'
        LIMIT 1
    ) <= {$mx}";
}

// Filtro por pregunta específica (nombre del campo + valor buscado)
if($f_pregunta_nombre !== '' && $f_pregunta_valor !== ''){
    $pn = $db->real_escape_string($f_pregunta_nombre);
    $pv = $db->real_escape_string($f_pregunta_valor);
    $sql_where .= " AND EXISTS(
        SELECT 1 FROM ost_form_entry fe_p
        JOIN ost_form_entry_values fev_p ON fev_p.entry_id=fe_p.id
        JOIN ost_form_field ff_p ON ff_p.id=fev_p.field_id
        WHERE fe_p.object_id=t.ticket_id AND fe_p.form_id=16
        AND ff_p.name='{$pn}'
        AND fev_p.value LIKE '%{$pv}%'
    )";
}
foreach($filtros as $campo => $valores){
    if(empty($valores)) continue;
    $campo_safe = $db->real_escape_string($campo);
    $conds = [];
    foreach($valores as $v){
        $ve = $db->real_escape_string($v);
        if($campo === 'satisfaccion'){
            $conds[] = "EXISTS(
                SELECT 1 FROM ost_form_entry fe_f
                JOIN ost_form_entry_values fev_f ON fev_f.entry_id=fe_f.id
                JOIN ost_form_field ff_f ON ff_f.id=fev_f.field_id
                WHERE fe_f.object_id=t.ticket_id AND fe_f.form_id=16
                AND ff_f.name='{$campo_safe}'
                AND CAST(fev_f.value AS UNSIGNED)={$ve}
            )";
        } elseif ($campo === 'tutor') {
            // BLINDAJE PARA TUTOR: Busca en cualquier campo que contenga 'tutor'
            $conds[] = "EXISTS(
                SELECT 1 FROM ost_form_entry fe_f
                JOIN ost_form_entry_values fev_f ON fev_f.entry_id=fe_f.id
                JOIN ost_form_field ff_f ON ff_f.id=fev_f.field_id
                WHERE fe_f.object_id=t.ticket_id AND fe_f.form_id=16
                AND (LOWER(ff_f.name) LIKE '%tutor%' OR LOWER(ff_f.label) LIKE '%tutor%')
                AND fev_f.value LIKE '%{$ve}%'
            )";
        } else {
            $conds[] = "EXISTS(
                SELECT 1 FROM ost_form_entry fe_f
                JOIN ost_form_entry_values fev_f ON fev_f.entry_id=fe_f.id
                JOIN ost_form_field ff_f ON ff_f.id=fev_f.field_id
                WHERE fe_f.object_id=t.ticket_id AND fe_f.form_id=16
                AND ff_f.name='{$campo_safe}'
                AND fev_f.value LIKE '%{$ve}%'
            )";
        }
    }
    $sql_where .= " AND (" . implode(' OR ',$conds) . ")";
}
/* ==========================================================
   CONTADORES (para hero cards)
========================================================== */
$sql_count_base = "SELECT COUNT(DISTINCT t.ticket_id) c FROM ost_ticket t WHERE 1=1";
$total  = (int)$db->query($sql_count_base . $sql_where)->fetch_assoc()['c'];
$hoy    = (int)$db->query($sql_count_base . $sql_where . " AND DATE(t.created)=CURDATE()")->fetch_assoc()['c'];
$sem    = (int)$db->query($sql_count_base . $sql_where . " AND t.created>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'];

/* ==========================================================
   PAGINACIÓN
========================================================== */
$limit  = 40;
$page   = max(1,(int)($_GET['page'] ?? 1));
$pages  = max(1,(int)ceil($total/$limit));
if($page > $pages) $page = $pages;
$offset = ($page-1)*$limit;

/* ==========================================================
   LISTADO PRINCIPAL
========================================================== */
$sql_main = "
    SELECT t.ticket_id, t.number, t.created,
    MAX(CASE WHEN ff.name='expediente'   THEN fev.value END) expediente,
    MAX(CASE WHEN ff.name='empresa'      THEN fev.value END) empresa,
    MAX(CASE WHEN ff.name='satisfaccion' THEN fev.value END) satisfaccion
    FROM ost_ticket t
    JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id=16
    JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
    JOIN ost_form_field ff ON ff.id=fev.field_id
    WHERE 1=1 {$sql_where}
    GROUP BY t.ticket_id
    ORDER BY t.ticket_id DESC
    LIMIT {$limit} OFFSET {$offset}
";
$q = $db->query($sql_main);

/* ==========================================================
   PARÁMETROS DE PAGINACIÓN (mantener filtros)
========================================================== */
$params_pag = [
    'desde'           => $f_desde,
    'hasta'           => $f_hasta,
    'buscar'          => $f_buscar,
    'sat_min'         => $f_sat_min,
    'sat_max'         => $f_sat_max,
    'pregunta_nombre' => $f_pregunta_nombre,
    'pregunta_valor'  => $f_pregunta_valor,
];
foreach($filtros as $k => $v){
    if(!empty($v)) $params_pag[$k] = $v;
}

function url_pag_val(int $p, array $params): string {
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

/* helper para render de select múltiple (igual que incidencias) */
function render_select_filtro_multi_val(string $name, string $label, array $selected, array $options, string $placeholder='— Selecciona —'): string {
    $html  = '<div class="filter-group">';
    $html .= '<label for="filter-'.$name.'">'.htmlspecialchars($label).'</label>';
    $html .= '<select id="filter-'.$name.'" name="'.htmlspecialchars($name).'[]" class="filter-select-multi" multiple data-placeholder="'.htmlspecialchars($placeholder).'" data-filter-name="'.$name.'">';
    foreach($options as $opt){
        $sel = in_array((string)$opt, $selected, true) ? ' selected' : '';
        $html .= '<option value="'.h($opt).'"'.$sel.'>'.h($opt).'</option>';
    }
    $html .= '</select></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Valoraciones - Grupo ATU</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>
/* ── VARIABLES ──────────────────────────────────── */
:root{
    --primary:#6366f1;
    --primary-dark:#4f46e5;
    --primary-bg:#eef2ff;
    --bg:#f4f7fb;
    --bg-card:#fff;
    --border:#e2e8f0;
    --text:#0f172a;
    --text-secondary:#475569;
    --text-muted:#94a3b8;
    --radius:10px;
    --sidebar-width:240px;
    --sidebar-collapsed:77px;
    --header-height:72px;
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

/* ── MAIN ── */
.main{margin-left:var(--sidebar-width);transition:all .3s cubic-bezier(.4,0,.2,1);min-height:100vh}
.sidebar.collapsed~.main{margin-left:var(--sidebar-collapsed)}

/* ── HEADER ── */
.header{height:var(--header-height);background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:400}
.header h1{margin:0;font-size:26px;font-weight:700}
.header-right{font-size:15px;display:flex;gap:8px;align-items:center;color:var(--text);font-weight:500}

/* ── HERO ── */
.hero{background:linear-gradient(135deg,#1e3a8a,#1d4ed8);padding:24px;color:#fff}
.hero h2{margin:0}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:18px}
.card{background:rgba(255,255,255,.15);padding:18px;border-radius:14px}
.card small{display:block;font-size:13px;opacity:.85}
.card b{font-size:28px;font-weight:800}

/* ── PAGE CONTENT ── */
.page-content{padding:24px 28px}

/* ── PANEL ── */
.panel{background:var(--bg-card);border-radius:14px;border:1px solid var(--border);margin-bottom:20px;overflow:visible}

/* ── TOOLBAR ── */
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:18px 20px}
.csv-btn{display:inline-flex;align-items:center;gap:7px;background:#1d4ed8;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;transition:background .2s}
.csv-btn:hover{background:#1e40af}
.csv-btn.orange{background:#f97316}
.csv-btn.orange:hover{background:#ea6c0a}
.search-box{display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:0 14px;height:42px;min-width:240px;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:border-color .2s}
.search-box:focus-within{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.search-box input{border:none;outline:none;font-size:14px;color:var(--text);width:100%;background:transparent}
.search-box i{color:var(--text-muted);font-size:14px}

/* ── PANEL DE FILTROS ── */
.filters-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none}
.filters-header:hover{background:#fafafa;border-radius:14px 14px 0 0}
.filters-body{max-height:4000px;overflow:visible;transition:max-height .4s,padding .4s,opacity .3s;padding:0 20px 20px;opacity:1;position:relative;z-index:50}
.filters-body.collapsed{max-height:0;padding:0 20px;opacity:0;overflow:hidden;pointer-events:none}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.filter-group{display:flex;flex-direction:column;gap:4px;position:relative;z-index:50}
.filter-group label{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px}
.filter-group input,.filter-group select{height:38px;border:1.5px solid var(--border);border-radius:6px;padding:0 12px;font-size:13px;background:#fff;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s}
.filter-group input:focus,.filter-group select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.filter-actions{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;font-family:'Inter',sans-serif}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-secondary{background:var(--bg);color:var(--text-secondary);border:1px solid var(--border)}
.btn-secondary:hover{background:#e2e8f0}

/* separador de sección dentro del panel */
.filter-section-title{grid-column:1/-1;font-size:11px;font-weight:800;color:var(--primary-dark);text-transform:uppercase;letter-spacing:.6px;padding:10px 0 4px;border-top:1px solid var(--border);margin-top:6px}
.filter-section-title:first-child{border-top:none;margin-top:0;padding-top:0}

/* ── TOM SELECT ── */
.ts-wrapper{font-size:13px;position:relative}
.ts-control{min-height:38px;border:1.5px solid var(--border)!important;border-radius:6px!important;padding:6px 10px!important;box-shadow:none!important;background:#fff!important}
.ts-control input{font-size:13px!important}
.ts-dropdown{border:1px solid var(--border)!important;border-radius:8px!important;overflow:hidden;z-index:9999!important;background:#fff!important}
.ts-dropdown .option,.ts-dropdown .create{font-size:13px;padding:10px 12px}
.ts-dropdown .active{background:var(--primary-bg)!important;color:var(--primary-dark)!important}

/* ── GRUPO DE PREGUNTA: título + respuesta lado a lado ── */
.pregunta-row{
    grid-column:1/-1;
    display:grid;
    grid-template-columns:1fr 180px;
    gap:10px;
    align-items:end;
    padding:10px 0 6px;
    border-top:1px solid #f1f5f9;
}
.pregunta-row:first-of-type{border-top:none}
.pregunta-label{
    font-size:12px;font-weight:700;color:#334155;
    line-height:1.4;
    display:flex;align-items:flex-end;padding-bottom:2px;
}
.pregunta-select{
    height:38px;border:1.5px solid var(--border);border-radius:6px;
    padding:0 10px;font-size:13px;background:#fff;
    font-family:'Inter',sans-serif;outline:none;
    transition:border-color .2s;cursor:pointer;
    appearance:none;-webkit-appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg fill='%2364748b' viewBox='0 0 16 16' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 8px center;background-size:14px;
    padding-right:28px;
}
.pregunta-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.pregunta-select option[value=""]{color:#94a3b8;font-style:italic}

/* ── TABLA ── */
.table-section{background:var(--bg-card);border-radius:14px;border:1px solid var(--border);margin-bottom:20px;overflow:visible}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap}
.table-container{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{padding:13px 14px;background:#f8fafc;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);font-weight:700;white-space:nowrap;border-bottom:1px solid var(--border)}
td{padding:13px 14px;border-top:1px solid #f1f5f9;font-size:14px;vertical-align:middle}
tr:hover td{background:#f8fbff}
.badge{padding:5px 11px;border-radius:999px;color:#fff;font-size:12px;font-weight:700}

/* ── ACCIONES ── */
.actions{display:flex;gap:8px}
.btn-action{width:34px;height:34px;display:flex;align-items:center;justify-content:center;text-decoration:none;position:relative;border-radius:8px;border:none;cursor:pointer}
.view{background:#eff6ff;color:#1d4ed8}
.del{background:#fff1f2;color:#dc2626}
.btn-action:hover::after{content:attr(data-tip);position:absolute;top:-36px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:5px 9px;font-size:11px;white-space:nowrap;border-radius:6px;z-index:9999}

/* ── PAGINACIÓN ── */
.table-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border)}
.pagination{display:flex;gap:4px}
.page-btn{min-width:34px;height:34px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:600}
.page-btn.active{background:#6366f1;color:#fff;border-color:#6366f1}

/* ── TOAST ── */
.toast{position:fixed;top:30px;right:30px;background:#fff;padding:16px 24px;border-radius:10px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);transform:translateX(400px);opacity:0;transition:all .4s;z-index:999999;border-left:4px solid #10b981}
.toast.show{transform:translateX(0);opacity:1}
.toast.error{border-left-color:#ef4444}

/* ── MODAL ── */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);z-index:99999;display:none;align-items:center;justify-content:center;padding:20px}
.modal-content{background:#fff;width:100%;max-width:760px;border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column;max-height:90vh}
.modal-header{padding:20px 26px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:#f8fafc}
.modal-header h3{margin:0;font-size:16px;font-family:'Inter',sans-serif}
.close-btn{background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);line-height:1;padding:0}
.close-btn:hover{color:#ef4444}
.modal-body{padding:24px;overflow:auto}

/* info modal campos */
.detail-row{padding:11px 0;border-bottom:1px solid #edf2f7}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:12px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.detail-value{margin-top:4px;font-size:14px;color:var(--text)}

/* ── STAT PILL ── */
#stats-pill{display:flex;background:#1e3a8a;color:#fff;border-radius:10px;padding:8px 16px;font-size:13px;font-weight:600;align-items:center;gap:8px;white-space:nowrap}
</style>
</head>
<body>

<!-- TOAST -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<!-- MODAL DETALLE -->
<div id="popup" class="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-clipboard-list" style="color:#6366f1;margin-right:8px"></i> Detalle de Valoración</h3>
            <button onclick="closeModal()" class="close-btn">&times;</button>
        </div>
        <div class="modal-body" id="popupBody"></div>
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
        <a href="incidencias.php"  class="menu-item"><i class="fa-solid fa-triangle-exclamation"></i><span>Tabla de Incidencias</span></a>
        <a href="estadisticas.php" class="menu-item"><i class="fas fa-chart-bar"></i><span>Estadisticas Incidencias</span></a>
		<hr class="linea-menu"> <!-- Línea normal -->
        <a href="valoraciones.php" class="menu-item active"><i class="fa-solid fa-clipboard-list"></i><span> Tabla de Valoraciones</span></a>
        <a href="https://incidencias.grupoatu.com/osticket/estadisticas_valoraciones.php" class="menu-item"><i class="fas fa-chart-pie"></i><span>Estadisticas Valoraciones</span></a>
		<hr class="linea-menu"> <!-- Línea normal -->
		<a href="coordinadores.php"  class="menu-item"><i class="fa-solid fa-user-tie"></i><span>Tabla de Coodinadores</span></a>
		<a href="estadisticas_coordinadores.php" class="menu-item"><i class="fa-solid fa-chart-line"></i><span>Estadísticas Coordinadores</span></a>
    </nav>
    <button class="sidebar-collapse-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
</aside>

<!-- MAIN -->
<div class="main">

    <!-- HEADER -->
    <div class="header">
        <h1>Valoraciones</h1>
        <div style="display:flex;align-items:center;gap:20px;">
            <div class="header-right"><i class="fa-solid fa-calendar-days"></i><span id="clock"></span></div>
            <div style="display:flex;align-items:center;gap:12px;border-left:1px solid var(--border);padding-left:20px;">
                <span style="font-size:13px;color:var(--text-secondary);font-weight:600;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-user-circle" style="color:var(--primary);font-size:16px;"></i>
                    <?php echo htmlspecialchars($_SESSION['valoraciones_user'] ?? ''); ?>
                </span>
                <a href="?logout=1" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#fee2e2;color:#ef4444;text-decoration:none;transition:0.2s;" onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='#fee2e2';this.style.color='#ef4444';" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- HERO -->
    <div class="hero">
        <h2>Panel de Valoraciones</h2>
        <div class="cards">
            <div class="card"><small>Total<?= (array_filter(array_values($filtros)) || $f_desde || $f_hasta || $f_buscar || $f_sat_min || $f_sat_max) ? ' filtrado' : '' ?></small><b id="heroTotal"><?= $total ?></b></div>
            <div class="card"><small>Hoy</small><b id="heroHoy"><?= $hoy ?></b></div>
            <div class="card"><small>7 días</small><b id="heroSem"><?= $sem ?></b></div>
        </div>
    </div>

    <div class="page-content">

        <!-- PANEL DE FILTROS AVANZADOS -->
        <div class="panel">
            <div class="filters-header" onclick="toggleFiltros()">
                <div style="display:flex;align-items:center;gap:8px;font-size:15px;font-weight:600">
                    <i class="fa-solid fa-sliders" style="color:#6366f1"></i> Filtros avanzados
                    <?php
                    $hay_filtros = array_filter(array_values($filtros)) || $f_desde || $f_hasta || $f_buscar || $f_sat_min || $f_sat_max;
                    if($hay_filtros): ?>
                        <span style="background:#6366f1;color:#fff;font-size:11px;padding:2px 8px;border-radius:999px;font-weight:700">Activos</span>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-down" id="filtros-chevron"></i>
            </div>

            <div class="filters-body collapsed" id="fb">
                <form method="GET">
                    <div class="filters-grid">

                        <!-- ── SECCIÓN: FECHAS Y BÚSQUEDA ── -->
                        <div class="filter-section-title"><i class="fa-solid fa-calendar-days"></i> Fechas y búsqueda</div>

                        <div class="filter-group">
                            <label>Desde</label>
                            <input type="date" name="desde" value="<?= h($f_desde) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Hasta</label>
                            <input type="date" name="hasta" value="<?= h($f_hasta) ?>">
                        </div>
                        <div class="filter-group" style="grid-column:span 2">
                            <label>Búsqueda general</label>
                            <input type="text" name="buscar" value="<?= h($f_buscar) ?>" placeholder="Expediente, empresa, ticket...">
                        </div>

                        <!-- ── SECCIÓN: DATOS PRINCIPALES ── -->
                        <div class="filter-section-title"><i class="fa-solid fa-user"></i> Datos del ticket</div>

                        <?= render_select_filtro_multi_val('expediente','Expediente',$filtros['expediente'],$opciones_filtros['expediente']) ?>
                        <?= render_select_filtro_multi_val('empresa','Empresa',$filtros['empresa'],$opciones_filtros['empresa']) ?>
                        <?= render_select_filtro_multi_val('plan','Plan',$filtros['plan'],$opciones_filtros['plan']) ?>
                        <?= render_select_filtro_multi_val('sector','Sector',$filtros['sector'],$opciones_filtros['sector']) ?>
                        <?= render_select_filtro_multi_val('tutor','Tutor/a',$filtros['tutor'],$opciones_filtros['tutor']) ?>
                        <?= render_select_filtro_multi_val('accion','Acción',$filtros['accion'],$opciones_filtros['accion']) ?>
                        <?= render_select_filtro_multi_val('grupo','Grupo',$filtros['grupo'],$opciones_filtros['grupo']) ?>
                        <?= render_select_filtro_multi_val('curso','Curso',$filtros['curso'],$opciones_filtros['curso']) ?>

                        <!-- ── SECCIÓN: SATISFACCIÓN ── -->
                        <div class="filter-section-title"><i class="fa-solid fa-star"></i> Satisfacción general</div>

                        <?= render_select_filtro_multi_val('satisfaccion','Satisfacción (exacta)',$filtros['satisfaccion'],$opciones_filtros['satisfaccion']) ?>
                        <div class="filter-group">
                            <label>Satisfacción mínima (1-10)</label>
                            <input type="number" name="sat_min" min="1" max="10" value="<?= h($f_sat_min) ?>" placeholder="Min">
                        </div>
                        <div class="filter-group">
                            <label>Satisfacción máxima (1-10)</label>
                            <input type="number" name="sat_max" min="1" max="10" value="<?= h($f_sat_max) ?>" placeholder="Max">
                        </div>

                        <!-- ── SECCIÓN: CONTENIDOS Y PRUEBAS ── -->
                        <div class="filter-section-title"><i class="fa-solid fa-book"></i> Contenidos y pruebas (respuesta 1-5)</div>

                        <?php
                        $ops_15        = ['','1','2','3','4','5'];
                        $ops_15_labels = ['— Respuesta —','1','2','3','4','5'];
                        $ops_sino      = ['','Sí','No','SÍ','NO'];
                        $ops_sino_labels=['— Respuesta —','Sí','No','SÍ','NO'];

                        /* Helper inline para una fila pregunta+select */
                        function pregunta_row(string $param, string $titulo, string $val_actual, array $opciones): string {
                            $html  = '<div class="pregunta-row">';
                            $html .= '<div class="pregunta-label">'.htmlspecialchars($titulo).'</div>';
                            $html .= '<select class="pregunta-select" name="'.h($param).'">';
                            foreach($opciones as $i => $op){
                                $sel = ($val_actual !== '' && $val_actual === $op) ? ' selected' : '';
                                $label = is_array($GLOBALS['_ops_labels'][$param] ?? null)
                                    ? ($GLOBALS['_ops_labels'][$param][$i] ?? $op)
                                    : $op;
                                $html .= '<option value="'.h($op).'"'.$sel.'>'.h($op === '' ? '— Respuesta —' : $op).'</option>';
                            }
                            $html .= '</select></div>';
                            return $html;
                        }

                        $pq = []; // valores actuales de preguntas desde GET
                        $preguntas_keys = [
                            'pq_cont_actualizados','pq_recursos_variados',
                            'pq_pruebas_nivel','pq_pruebas_temario',
                            'pq_queja_contenidos','pq_mejoras_contenidos',
                            'pq_utilidad_recursos','pq_acomp_coordinador',
                            'pq_protocolo_tutor','pq_formacion_atu',
                            'pq_equipo_coord','pq_moodle','pq_erp',
                            'pq_queja_recursos','pq_mejoras_participacion',
                            'pq_causas_nofin','pq_fechas_convenientes',
                            'pq_falta_tiempo','pq_falta_motivacion',
                            'pq_problemas_familiares','pq_perfil_inadecuado',
                            'pq_sin_habilidades','pq_fallos_tecnicos',
                            'pq_no_quiere','pq_justificacion','pq_mejoras_finalizacion',
                        ];
                        foreach($preguntas_keys as $k) $pq[$k] = trim($_GET[$k] ?? '');

                        function pq_sel(string $key, string $titulo, array $opciones): string {
                            global $pq;
                            $val = $pq[$key] ?? '';
                            $html = '<div class="pregunta-row">';
                            $html .= '<div class="pregunta-label">'.h($titulo).'</div>';
                            $html .= '<select class="pregunta-select" name="'.h($key).'">';
                            foreach($opciones as $op){
                                $sel = ($val !== '' && strtolower($val) === strtolower($op)) ? ' selected' : '';
                                $html .= '<option value="'.h($op).'"'.$sel.'>'.h($op === '' ? '— Respuesta —' : $op).'</option>';
                            }
                            $html .= '</select></div>';
                            return $html;
                        }

                        $OPS_15   = ['','1','2','3','4','5'];
                        $OPS_SINO = ['','Sí','No'];

                        echo pq_sel('pq_cont_actualizados',
                            'Los contenidos del curso se mantienen actualizados', $OPS_15);
                        echo pq_sel('pq_recursos_variados',
                            'Los contenidos incluyen diferentes tipos de recursos y fuentes (vídeos, enlaces web, audios, etc.)', $OPS_15);
                        echo pq_sel('pq_pruebas_nivel',
                            'Las pruebas de evaluación son acordes al nivel del curso', $OPS_15);
                        echo pq_sel('pq_pruebas_temario',
                            'Las pruebas de evaluación corresponden al temario de la formación', $OPS_15);
                        ?>

                        <!-- ── SECCIÓN: RECURSOS Y APOYO ── -->
                        <div class="filter-section-title"><i class="fa-solid fa-toolbox"></i> Recursos y apoyo (respuesta 1-5)</div>

                        <?php
                        echo pq_sel('pq_utilidad_recursos',
                            '¿En qué medida te han resultado útiles los siguientes recursos para el seguimiento y desarrollo de la formación?', $OPS_15);
                        echo pq_sel('pq_acomp_coordinador',
                            'Acompañamiento del coordinador/a', $OPS_15);
                        echo pq_sel('pq_protocolo_tutor',
                            'Protocolo de tutorización', $OPS_15);
                        echo pq_sel('pq_formacion_atu',
                            'La formación recibida por parte de ATU (formación de inicio o cierre)', $OPS_15);
                        echo pq_sel('pq_equipo_coord',
                            'Equipo de Coordinación (tareas de Dinamización)', $OPS_15);
                        echo pq_sel('pq_moodle',
                            'Plataforma de trabajo (MOODLE)', $OPS_15);
                        echo pq_sel('pq_erp',
                            'ERP', $OPS_15);
                        ?>

                        <!-- ── SECCIÓN: CAUSAS DE NO FINALIZACIÓN ── -->
                        <div class="filter-section-title"><i class="fa-solid fa-triangle-exclamation"></i> Causas de no finalización (respuesta Sí/No)</div>

                        <?php
                        echo pq_sel('pq_fechas_convenientes',
                            'Las fechas de desarrollo o la duración del curso han sido las convenientes', $OPS_SINO);
                        echo pq_sel('pq_falta_tiempo',
                            'Falta de tiempo por parte del alumnado', $OPS_SINO);
                        echo pq_sel('pq_falta_motivacion',
                            'Falta de motivación', $OPS_SINO);
                        echo pq_sel('pq_problemas_familiares',
                            'Problemas familiares o de salud', $OPS_SINO);
                        echo pq_sel('pq_perfil_inadecuado',
                            'El perfil del alumnado no ha sido acorde al nivel de los contenidos', $OPS_SINO);
                        echo pq_sel('pq_sin_habilidades',
                            'El alumnado no disponía de habilidades tecnológicas', $OPS_SINO);
                        echo pq_sel('pq_fallos_tecnicos',
                            'Se producen fallos técnicos de plataforma', $OPS_SINO);
                        echo pq_sel('pq_no_quiere',
                            'No quiere hacer la formación / no se había apuntado', $OPS_SINO);
                        ?>

                        <!-- ── BOTONES ── -->
                        <div class="filter-section-title" style="border-top:none"></div>
                        <div class="filter-group filter-actions" style="grid-column:1/-1;justify-content:flex-end">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                            <a href="valoraciones.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Limpiar</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <!-- TABLA -->
        <div class="table-section">
            <div class="table-header">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <h3 style="font-size:15px;font-weight:700">Listado</h3>
                    <span style="font-size:13px;color:var(--text-muted)"><?= $q ? $q->num_rows : 0 ?>/<?= $total ?></span>
                    <a href="?<?= http_build_query(array_merge($params_pag,['csv'=>1])) ?>" class="btn btn-primary" style="font-size:13px">
                        <i class="fa-solid fa-download"></i> Exportar CSV
                    </a>
                    <form action="valoracion_import/valoraciones_upload.php" method="POST" enctype="multipart/form-data" id="formImport" style="display:contents">
                        <input type="file" name="archivo_csv" id="csv_file" style="display:none" onchange="document.getElementById('formImport').submit()">
                        <label for="csv_file" class="btn" style="background:#f97316;color:#fff;cursor:pointer;font-size:13px">
                            <i class="fa-solid fa-upload"></i> Importar CSV
                        </label>
                        <input type="hidden" name="importar_ahora" value="1">
                    </form>
                </div>
                <!-- Búsqueda rápida -->
                <div style="position:relative">
                    <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px"></i>
                    <input type="text" placeholder="Buscar en página..." id="tSearch" oninput="buscarRapido()" 
                           style="height:34px;width:220px;border:1px solid #e2e8f0;border-radius:6px;padding:0 10px 0 30px;font-size:12px;font-family:'Inter',sans-serif;outline:none">
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Acciones</th>
                            <th>Fecha</th>
                            <th>Expediente</th>
                            <th>Empresa</th>
                            <th>Satisfacción</th>
                            <th>Ticket</th>
                        </tr>
                    </thead>
                    <tbody id="tb">
                    <?php if($q && $q->num_rows > 0):
                        while($r = $q->fetch_assoc()):
                            $ticket = (int)$r['ticket_id'];
                            // Cargar todos los campos del ticket para el modal
                            $det = $db->query("
                                SELECT ff.label, fev.value
                                FROM ost_form_entry fe
                                JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
                                JOIN ost_form_field ff ON ff.id=fev.field_id
                                WHERE fe.object_id=$ticket
                                ORDER BY ff.sort
                            ");
                            $campos = [];
                            while($x = $det->fetch_assoc()){
                                $campos[$x['label']] = htmlspecialchars(valor($x['value']));
                            }
                            $campos_json = json_encode($campos);
                    ?>
                        <tr data-ticket="<?= $ticket ?>">
                            <td>
                                <div class="actions">
                                    <button type="button" class="btn-action view" data-tip="Ver valoración"
                                            onclick='openModal(<?= $campos_json ?>)'>
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <a href="?del=<?= $ticket ?>" onclick="return confirm('¿Eliminar valoración?')"
                                       class="btn-action del" data-tip="Eliminar valoración">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                            <td><?= h($r['created']) ?></td>
                            <td><?= h(valor($r['expediente'])) ?></td>
                            <td><?= h(valor($r['empresa'])) ?></td>
                            <td>
                                <?php $sv = valor($r['satisfaccion']); ?>
                                <?php if($sv !== ''): ?>
                                    <span class="badge" style="background:<?= sat($sv) ?>"><?= h($sv) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['number']) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">No se encontraron registros</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <div class="table-footer">
                <div style="font-size:13px;color:var(--text-muted)">Pág <?= $page ?>/<?= $pages ?> · <?= $total ?> total</div>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="<?= url_pag_val(1,$params_pag) ?>" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                        <a href="<?= url_pag_val($page-1,$params_pag) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <span class="page-btn active"><?= $page ?></span>
                    <?php if($page < $pages): ?>
                        <a href="<?= url_pag_val($page+1,$params_pag) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                        <a href="<?= url_pag_val($pages,$params_pag) ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            if(isset($_GET['importados'])){
                $ok = (int)$_GET['importados'];
                echo "<div id='msg_ok' style='background:#dcfce7;color:#166534;padding:14px;margin:12px 20px;border-radius:8px;text-align:center;font-weight:700;border:1px solid #bbf7d0;'>¡Éxito! Se han importado $ok registros.</div>
                <script>setTimeout(function(){var e=document.getElementById('msg_ok');if(e)e.style.display='none';},10000);</script>";
            }
            if(isset($_GET['error_db'])){
                echo "<div id='msg_edb' style='background:#fee2e2;color:#991b1b;padding:14px;margin:12px 20px;border-radius:8px;text-align:center;font-weight:700;border:1px solid #fecaca;'>Error: No se pudo importar.</div>
                <script>setTimeout(function(){var e=document.getElementById('msg_edb');if(e)e.style.display='none';},10000);</script>";
            }
            if(isset($_GET['error_file'])){
                echo "<div id='msg_ef' style='background:#fef9c3;color:#854d0e;padding:14px;margin:12px 20px;border-radius:8px;text-align:center;font-weight:700;border:1px solid #fef08a;'>Aviso: Archivo no válido.</div>
                <script>setTimeout(function(){var e=document.getElementById('msg_ef');if(e)e.style.display='none';},10000);</script>";
            }
            ?>
        </div>

    </div><!-- /page-content -->
</div><!-- /main -->

<script>
/* ── RELOJ ── */
function reloj(){
    var n=new Date();
    document.getElementById('clock').innerHTML=
        String(n.getDate()).padStart(2,'0')+'/'+
        String(n.getMonth()+1).padStart(2,'0')+'/'+
        n.getFullYear()+', '+
        String(n.getHours()).padStart(2,'0')+':'+
        String(n.getMinutes()).padStart(2,'0')+':'+
        String(n.getSeconds()).padStart(2,'0');
}
reloj(); setInterval(reloj,1000);

/* ── TOAST ── */
function showToast(m,t){
    var e=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=m;
    e.querySelector('i').className=t==='success'?'fas fa-check-circle':'fas fa-exclamation-circle';
    e.className='toast '+(t||'success')+' show';
    setTimeout(function(){e.classList.remove('show')},3000);
}

/* ── TOGGLE PANEL FILTROS ── */
function toggleFiltros(){
    var fb=document.getElementById('fb');
    var ic=document.getElementById('filtros-chevron');
    var open=fb.classList.toggle('collapsed');
    ic.style.transform=open?'':'rotate(180deg)';
}

/* ── Sincronizar label de pregunta con el select ── */
function syncPreguntaLabel(){
    var sel=document.getElementById('sel-pregunta-nombre');
    var inp=document.getElementById('inp-pregunta-valor');
    if(sel && inp && sel.value && inp.value===''){
        inp.focus();
    }
}

/* ── MODAL DETALLE ── */
function openModal(data){
    var html='';
    for(var campo in data){
        if(!data[campo]) continue;
        html+='<div class="detail-row">'
            +'<div class="detail-label">'+campo+'</div>'
            +'<div class="detail-value">'+data[campo]+'</div>'
            +'</div>';
    }
    document.getElementById('popupBody').innerHTML=html||'<p style="color:#94a3b8">Sin datos</p>';
    document.getElementById('popup').style.display='flex';
}
function closeModal(){
    document.getElementById('popup').style.display='none';
}

/* ── BÚSQUEDA RÁPIDA EN TABLA ── */
function buscarRapido(){
    var q=document.getElementById('tSearch').value.toLowerCase();
    document.querySelectorAll('#tb tr').forEach(function(r){
        r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';
    });
}

/* ── TOM SELECT con dependencia plan → sector ── */
var tsInstances = {};

document.querySelectorAll('.filter-select-multi').forEach(function(el){
    var filterName = el.dataset.filterName || '';
    var ts = new TomSelect(el,{
        plugins:['remove_button'],
        create:false,
        allowEmptyOption:true,
        placeholder:el.dataset.placeholder||'— Selecciona —',
        maxOptions:5000,
        closeAfterSelect:false,
        hideSelected:false,
        searchField:['text'],
        sortField:[{field:'text',direction:'asc'}],
        dropdownParent:'body',
        onInitialize: function(){
            tsInstances[filterName] = this;
            if(filterName === 'plan' && this.getValue().length > 0){
                actualizarSector(this.getValue());
            }
        },
        onChange: function(values){
            if(filterName === 'plan'){
                actualizarSector(values);
            }
        }
    });
});

/* Actualizar opciones del selector de Sector segun planes elegidos */
function actualizarSector(planesSeleccionados){
    var tsSector = tsInstances['sector'];
    if(!tsSector) return;

    var prevSelected = tsSector.getValue();

    var url = 'valoraciones.php?ajax_sector=1';
    if(Array.isArray(planesSeleccionados) && planesSeleccionados.length > 0){
        planesSeleccionados.forEach(function(p){
            url += '&plan[]=' + encodeURIComponent(p);
        });
    }

    tsSector.lock();

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(data){
            tsSector.unlock();
            if(!data.ok) return;

            var opciones = data.opciones;
            tsSector.clearOptions();
            opciones.forEach(function(v){
                tsSector.addOption({value: v, text: v});
            });
            tsSector.refreshOptions(false);

            var validos = prevSelected.filter(function(v){
                return opciones.indexOf(v) !== -1;
            });
            if(validos.length > 0){
                tsSector.setValue(validos, true);
            }

            var wrap = tsSector.wrapper;
            if(wrap){
                wrap.style.transition = 'box-shadow .3s';
                wrap.style.boxShadow  = '0 0 0 3px rgba(99,102,241,.25)';
                setTimeout(function(){ wrap.style.boxShadow = ''; }, 1200);
            }
        })
        .catch(function(){
            tsSector.unlock();
        });
}

/* ── Abrir panel si hay filtros activos ── */
<?php if($hay_filtros): ?>
document.addEventListener('DOMContentLoaded',function(){
    var fb=document.getElementById('fb');
    var ic=document.getElementById('filtros-chevron');
    fb.classList.remove('collapsed');
    ic.style.transform='rotate(180deg)';
});
<?php endif; ?>
</script>
</body>
</html>