<?php
/**
 * estadisticas_valoraciones.php
 * Panel de estadísticas interactivo de valoraciones (topic_id=13, form_id=16)
 * Mismo patrón que estadisticas.php: AJAX, rangos, filtros en cascada, exportación.
 */



session_start();
require_once __DIR__ . '/auth_admin_sso.php';

// ========================================================================
// 1. CONFIGURACIÓN DEL MINILOGIN (Usuario => Contraseña)
// ========================================================================
$USUARIOS_PERMITIDOS = [
    'Admin' => '1,<X8r0.5(Tl03?-gq]giU',
    'IncidenciasAtu' => 'D/*50smPm@7FPM@c£EUMU&'
];

// Procesar cierre de sesión
if (isset($_GET['logout'])) {
    if (!empty($_SESSION['admin_sso'])) {
        admin_sso_logout();
    } else {
        session_destroy();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$login_error = '';
// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'], $_POST['login_password'])) {
    $user = $_POST['login_username'];
    $pass = $_POST['login_password'];

    if (isset($USUARIOS_PERMITIDOS[$user]) && $USUARIOS_PERMITIDOS[$user] === $pass) {
        if ($user === ADMIN_SSO_USER) {
            admin_sso_activate();
        } else {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_name'] = $user;
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $login_error = 'Usuario o contraseña incorrectos.';
    }
}

// Verificar si está logueado
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Si NO está logueado
if (!$is_logged_in) {
    // Si es una petición AJAX (las gráficas intentando cargar datos), devolver error JSON
    if (isset($_GET['ajax']) || isset($_GET['ajax_filters']) || isset($_GET['ajax_detail'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Sesión caducada. Por favor, recarga la página para iniciar sesión.']);
        exit;
    }
    
    // Si es una visita normal, mostrar la pantalla de Login y detener el script
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Iniciar Sesión - Estadísticas</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .login-container { background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 360px; text-align: center; }
            .login-container i { font-size: 40px; color: #6366f1; margin-bottom: 15px; }
            .login-container h2 { margin: 0 0 25px 0; color: #0f172a; font-size: 22px; }
            .login-form input { width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-family: 'Inter'; font-size: 14px; outline: none; transition: border 0.3s; }
            .login-form input:focus { border-color: #6366f1; }
            .login-form button { width: 100%; padding: 12px; background: #6366f1; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
            .login-form button:hover { background: #4f46e5; }
            .login-error { color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <i class="fas fa-chart-pie"></i>
            <h2>Acceso a Estadísticas</h2>
            <?php if ($login_error): ?>
                <div class="login-error"><i class="fas fa-exclamation-circle"></i> <?php echo $login_error; ?></div>
            <?php endif; ?>
            <form class="login-form" method="POST">
                <input type="text" name="login_username" placeholder="Usuario" required autofocus>
                <input type="password" name="login_password" placeholder="Contraseña" required>
                <button type="submit">Iniciar Sesión</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit; // Detiene la ejecución para que no se procese la base de datos si no hay login
}
// ========================================================================
// FIN DEL MINILOGIN
// ========================================================================


ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_error_val.log');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

define('ROOT_DIR',    '/var/www/vhosts/incidencias.grupoatu.com/httpdocs/osticket/');
define('INCLUDE_DIR', ROOT_DIR . 'include/');

$config_path = INCLUDE_DIR . 'ost-config.php';
if (!file_exists($config_path)) die('No se encuentra ost-config.php');
require_once $config_path;
if (!defined('DBHOST') || !defined('DBUSER') || !defined('DBPASS') || !defined('DBNAME'))
    die('Falta configuración de BD en ost-config.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
    $db->set_charset('utf8mb4');
} catch (Throwable $e) {
    if (isset($_GET['ajax']) || isset($_GET['ajax_filters']) || isset($_GET['ajax_detail'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Error de conexión a la BD']);
        exit;
    }
    die('Error crítico de conexión a la BD: ' . htmlspecialchars($e->getMessage()));
}

/* =============================================================================
   HELPERS GENERALES
============================================================================= */

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function valor_val($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (substr($v, 0, 1) === '{') {
        $j = json_decode($v, true);
        if (is_array($j)) return trim(reset($j));
    }
    return $v;
}

/* =============================================================================
   RANGOS TEMPORALES
============================================================================= */

function rango_fechas_val(string $range): array {
    $now    = new DateTimeImmutable('now');
    $finDia = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    switch ($range) {
        case 'day':     return [$now->setTime(0,0,0)->format('Y-m-d H:i:s'), $finDia, 'Día'];
        case 'week':    return [$now->modify('-6 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $finDia, 'Última semana'];
        case 'month':   return [$now->modify('-29 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $finDia, 'Último mes'];
        case 'quarter': return [$now->modify('-89 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $finDia, 'Último trimestre'];
        case 'year':    return [$now->modify('-364 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $finDia, '1 año'];
        default:        return [null, null, 'Histórico'];
    }
}

function render_range_buttons_val(string $metric): string {
    $ranges = ['year' => '1 año'];
    $html = '<div class="range-group">';
    foreach ($ranges as $key => $label)
        $html .= '<button type="button" class="range-btn" data-metric="' . h($metric) . '" data-range="' . $key . '">' . $label . '</button>';
    return $html . '</div>';
}

/* =============================================================================
   CAMPOS DEL FORMULARIO DE VALORACIONES (form_id = 16)
============================================================================= */

$CAMPOS_VAL = ['empresa','expediente','plan','sector','tutor_a','accion','grupo','curso','satisfaccion'];

$LABELS_VAL = [
    'empresa'      => 'Empresa',
    'expediente'   => 'Expediente',
    'plan'         => 'Plan',
    'sector'       => 'Sector',
    'tutor_a'      => 'Tutor/a',
    'accion'       => 'Acción',
    'grupo'        => 'Grupo',
    'curso'        => 'Curso',
    'satisfaccion' => 'Satisfacción',
];

/* =============================================================================
   CONSTRUCCIÓN DEL WHERE
============================================================================= */

/**
 * Devuelve la cláusula WHERE base (topic_id=13) + condiciones de filtro.
 * Cada filtro busca en los campos del form_id=16 mediante EXISTS.
 */
function where_val(mysqli $db, array $filters): string {
    $where = " WHERE t.topic_id = 13 ";
    foreach ($filters as $campo => $vals) {
        if (empty($vals)) continue;
        $vals = array_values(array_filter(array_map('trim', (array)$vals)));
        if (empty($vals)) continue;
        $ors = [];
        foreach ($vals as $v) {
            $ve = $db->real_escape_string($v);
            $ce = $db->real_escape_string($campo);
            $ors[] = "EXISTS(
                SELECT 1 FROM ost_form_entry fe2
                JOIN ost_form_entry_values fev2 ON fev2.entry_id = fe2.id
                JOIN ost_form_field ff2 ON ff2.id = fev2.field_id
                WHERE fe2.object_id = t.ticket_id AND fe2.form_id = 16
                AND ff2.name = '{$ce}' AND fev2.value LIKE '%{$ve}%'
            )";
        }
        $where .= " AND (" . implode(' OR ', $ors) . ") ";
    }
    return $where;
}

/** Añade restricciones de rango de fechas al WHERE. */
function where_rango_val(string $where, mysqli $db, ?string $from, ?string $to): string {
    if ($from !== null) $where .= " AND t.created >= '" . $db->real_escape_string($from) . "' ";
    if ($to   !== null) $where .= " AND t.created <= '" . $db->real_escape_string($to)   . "' ";
    return $where;
}

/* =============================================================================
   FILTROS EN CASCADA
============================================================================= */

function get_filter_values_val(mysqli $db, array $applied, array $campos): array {
    $where   = where_val($db, $applied);
    $filters = [];
    foreach ($campos as $campo) {
        $filters[$campo] = [];
        $ce = $db->real_escape_string($campo);
        $q  = $db->query("
            SELECT DISTINCT fev.value v
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id = t.ticket_id AND fe.form_id = 16
            JOIN ost_form_entry_values fev ON fev.entry_id = fe.id
            JOIN ost_form_field ff ON ff.id = fev.field_id
            {$where} AND ff.name = '{$ce}'
            AND fev.value IS NOT NULL AND TRIM(fev.value) != ''
            ORDER BY fev.value ASC
        ");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $v = trim(valor_val($r['v']));
                if ($v !== '') $filters[$campo][$v] = $v;
            }
        }
        $filters[$campo] = array_values($filters[$campo]);
        sort($filters[$campo], SORT_STRING | SORT_FLAG_CASE);
    }
    return $filters;
}

/* =============================================================================
   CONSULTA DE MÉTRICAS
============================================================================= */

function metric_query_val(mysqli $db, string $metric, string $range, array $filters): array {
    $valid = ['evolucion','satisfaccion','empresas'];
    if (!in_array($metric, $valid, true)) $metric = 'evolucion';

    [$from, $to, $rangeLabel] = rango_fechas_val($range);
    $where = where_val($db, $filters);
    $where = where_rango_val($where, $db, $from, $to);

    $labels = []; $values = []; $total = 0;

    if ($metric === 'evolucion') {
        if ($range === 'day') {
            $sql = "SELECT DATE_FORMAT(t.created,'%H:00') label, HOUR(t.created) ord, COUNT(*) total
                    FROM ost_ticket t {$where} GROUP BY 1,2 ORDER BY 2 ASC";
        } elseif ($range === 'year' || $range === 'historico') {
            $sql = "SELECT DATE_FORMAT(t.created,'%m/%Y') label, DATE_FORMAT(t.created,'%Y-%m') ord, COUNT(*) total
                    FROM ost_ticket t {$where} GROUP BY 2,1 ORDER BY 2 ASC";
        } else {
            $sql = "SELECT DATE_FORMAT(t.created,'%d/%m') label, DATE(t.created) ord, COUNT(*) total
                    FROM ost_ticket t {$where} GROUP BY 2,1 ORDER BY 2 ASC";
        }
        $q = $db->query($sql);
        while ($r = $q->fetch_assoc()) { $labels[] = $r['label']; $values[] = (int)$r['total']; $total += (int)$r['total']; }

    } elseif ($metric === 'satisfaccion') {
        $q = $db->query("
            SELECT fev.value label, COUNT(*) total
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id = t.ticket_id AND fe.form_id = 16
            JOIN ost_form_entry_values fev ON fev.entry_id = fe.id
            JOIN ost_form_field ff ON ff.id = fev.field_id
            {$where} AND ff.name = 'satisfaccion'
            GROUP BY fev.value ORDER BY CAST(fev.value AS UNSIGNED)
        ");
        while ($r = $q->fetch_assoc()) { $labels[] = $r['label']; $values[] = (int)$r['total']; $total += (int)$r['total']; }

    } elseif ($metric === 'empresas') {
        $q = $db->query("
            SELECT MAX(CASE WHEN ff.name='empresa' THEN fev.value END) empresa,
                   COUNT(DISTINCT t.ticket_id) total
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id = t.ticket_id AND fe.form_id = 16
            JOIN ost_form_entry_values fev ON fev.entry_id = fe.id
            JOIN ost_form_field ff ON ff.id = fev.field_id
            {$where}
            GROUP BY t.ticket_id
        ");
        $temp = [];
        while ($r = $q->fetch_assoc()) {
            $e = trim(valor_val($r['empresa']));
            if ($e === '') $e = 'Sin empresa';
            $temp[$e] = ($temp[$e] ?? 0) + 1;
        }
        arsort($temp);
        foreach (array_slice($temp, 0, 10, true) as $k => $v) { $labels[] = $k; $values[] = $v; $total += $v; }

   
    }

    return ['ok' => true, 'metric' => $metric, 'range' => $range,
            'rangeLabel' => $rangeLabel, 'labels' => $labels, 'values' => $values, 'total' => $total];
}

/* =============================================================================
   LECTURA DE FILTROS GET
============================================================================= */

$rf = function(string $key): array {
    $v = $_GET[$key] ?? $_GET[$key . '[]'] ?? null;
    if ($v === null) return [];
    return array_values(array_filter(is_array($v) ? $v : [$v], function($x){ return trim((string)$x) !== ''; }));
};

/* =============================================================================
   ENDPOINT: ajax_filters — opciones de filtros en cascada
============================================================================= */

if (isset($_GET['ajax_filters'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $CAMPOS_VAL;
        $applied = [];
        foreach ($CAMPOS_VAL as $c) {
            if ($t = $rf('filter_' . $c)) $applied[$c] = $t;
        }
        $filter_data = get_filter_values_val($db, $applied, $CAMPOS_VAL);
        echo json_encode(['ok' => true, 'filters' => $filter_data], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* =============================================================================
   ENDPOINT: ajax — datos de una métrica
============================================================================= */

if (isset($_GET['ajax']) && isset($_GET['metric'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $CAMPOS_VAL;
        $metric  = trim((string)($_GET['metric'] ?? 'evolucion'));
        $range   = trim((string)($_GET['range']  ?? 'historico'));
        $filters = [];
        foreach ($CAMPOS_VAL as $c) {
            if ($t = $rf('filter_' . $c)) $filters[$c] = $t;
        }
        echo json_encode(metric_query_val($db, $metric, $range, $filters), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error cargando datos: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    $db->close();
    exit;
}

/* =============================================================================
   ENDPOINT: ajax_detail — detalle de un punto/barra al hacer clic
============================================================================= */

if (isset($_GET['ajax_detail']) && isset($_GET['metric']) && isset($_GET['label'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $metric = trim((string)($_GET['metric'] ?? ''));
        $label  = trim((string)($_GET['label']  ?? ''));
        $range  = trim((string)($_GET['range']  ?? 'historico'));

        [$from, $to, $rangeLabel] = rango_fechas_val($range);

        // Construir filtros activos
        $filters = [];
        foreach ($CAMPOS_VAL as $c) {
            if ($t = $rf('filter_' . $c)) $filters[$c] = $t;
        }

        $where = where_val($db, $filters);
        $where = where_rango_val($where, $db, $from, $to);

        // Obtener todos los tickets del rango+filtros con sus campos relevantes
        $sql = "
            SELECT t.ticket_id, t.created,
                MAX(CASE WHEN ff.name='curso'        THEN fev.value END) AS curso_raw,
                MAX(CASE WHEN ff.name='empresa'      THEN fev.value END) AS empresa_raw,
                MAX(CASE WHEN ff.name='plan'         THEN fev.value END) AS plan_raw,
                MAX(CASE WHEN ff.name='satisfaccion' THEN fev.value END) AS satisfaccion_raw
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id = t.ticket_id AND fe.form_id = 16
            JOIN ost_form_entry_values fev ON fev.entry_id = fe.id
            JOIN ost_form_field ff ON ff.id = fev.field_id
            {$where}
            GROUP BY t.ticket_id, t.created
        ";
        $q = $db->query($sql);
        $rowsAll = [];
        while ($r = $q->fetch_assoc()) $rowsAll[] = $r;

        // Filtrar por la etiqueta del punto clicado según métrica
        if ($metric === 'evolucion') {
            if (preg_match('/^(\d{2})\/(\d{4})$/', $label, $m)) {
                $mes = $m[1]; $anio = $m[2];
                $rowsDetalle = array_values(array_filter($rowsAll, function($row) use ($mes, $anio) {
                    $f = strtotime($row['created'] ?? '');
                    return date('m', $f) === $mes && date('Y', $f) === $anio;
                }));
            } elseif (preg_match('/^(\d{2})\/(\d{2})$/', $label, $m)) {
                $dia = $m[1]; $mes = $m[2]; $anio = date('Y');
                $rowsDetalle = array_values(array_filter($rowsAll, function($row) use ($dia, $mes, $anio) {
                    $f = strtotime($row['created'] ?? '');
                    return date('d', $f) === $dia && date('m', $f) === $mes && date('Y', $f) === $anio;
                }));
            } elseif (preg_match('/^(\d{1,2}):00$/', $label, $m)) {
                $hora = (int)$m[1];
                $rowsDetalle = array_values(array_filter($rowsAll, function($row) use ($hora) {
                    return (int)date('G', strtotime($row['created'] ?? '')) === $hora;
                }));
            } else {
                $rowsDetalle = $rowsAll;
            }
        } elseif ($metric === 'satisfaccion') {
            $rowsDetalle = array_values(array_filter($rowsAll, function($row) use ($label) {
                return trim((string)($row['satisfaccion_raw'] ?? '')) === $label;
            }));
        } elseif ($metric === 'empresas') {
            $rowsDetalle = array_values(array_filter($rowsAll, function($row) use ($label) {
                $e = trim(valor_val($row['empresa_raw'] ?? ''));
                if ($e === '') $e = 'Sin empresa';
                return $e === $label;
            }));
        } else {
            $rowsDetalle = $rowsAll;
        }

        $totalDetalle = count($rowsDetalle);
        $fechaMin = null; $fechaMax = null;
        foreach ($rowsDetalle as $row) {
            $fc = $row['created'] ?? null;
            if ($fc) {
                if ($fechaMin === null || $fc < $fechaMin) $fechaMin = $fc;
                if ($fechaMax === null || $fc > $fechaMax) $fechaMax = $fc;
            }
        }

        // Agrupar por curso
        $cursos = [];
        foreach ($rowsDetalle as $row) {
            $c = trim(valor_val($row['curso_raw'] ?? ''));
            if ($c === '') $c = 'Sin dato';
            $cursos[$c] = ($cursos[$c] ?? 0) + 1;
        }
        arsort($cursos);
        $cursosArr = [];
        foreach ($cursos as $k => $v) $cursosArr[] = ['label' => $k, 'total' => $v];

        echo json_encode([
            'ok'         => true,
            'metric'     => $metric,
            'label'      => $label,
            'rangeLabel' => $rangeLabel,
            'resumen'    => ['total' => $totalDetalle, 'fecha_min' => $fechaMin, 'fecha_max' => $fechaMax],
            'cursos'     => $cursosArr,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    $db->close();
    exit;
}

/* =============================================================================
   DATOS KPI (solo en carga de página completa)
============================================================================= */

try {
    $kpi_total  = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket WHERE topic_id=13")->fetch_assoc()['c'];
    $kpi_hoy    = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket WHERE topic_id=13 AND DATE(created)=CURDATE()")->fetch_assoc()['c'];
    $kpi_semana = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket WHERE topic_id=13 AND created>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'];
    $kpi_media  = $db->query("
        SELECT ROUND(AVG(CAST(fev.value AS DECIMAL(10,2))),1) media
        FROM ost_ticket t
        JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id=16
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='satisfaccion' AND t.topic_id=13 AND fev.value REGEXP '^[0-9]+'
    ")->fetch_assoc()['media'] ?? 0;
} catch (Throwable $e) {
    $kpi_total = $kpi_hoy = $kpi_semana = 0;
    $kpi_media = 0;
}

$default_ranges = [
    'evolucion'    => 'year',
    'satisfaccion' => 'year',
    'empresas'     => 'year',
];

$graficas = [
    ['evolucion',    'Evolución de valoraciones',   'fa-chart-line',       'line'],
    ['satisfaccion', 'Distribución satisfacción',   'fa-star-half-stroke', 'bar'],
    ['empresas',     'Top empresas',                'fa-building',         'doughnut'],
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Estadísticas Valoraciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>
/* ── VARIABLES ── */
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
    --orange:#ef4444;
    --orange-bg:#fee2e2;
    --transition:all 0.3s cubic-bezier(0.4,0,0.2,1);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--text)}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-width);height:100vh;background:#0f172a;z-index:1000;display:flex;flex-direction:column;transition:var(--transition);overflow:hidden}
.sidebar.collapsed{width:var(--sidebar-collapsed)}
.sidebar-header{padding:22px 18px;display:flex;justify-content:center;border-bottom:1px solid rgba(255,255,255,.06)}
.sidebar-brand{display:flex;align-items:center;justify-content:center;width:100%;color:#fff;font-size:20px;font-weight:800;white-space:nowrap;overflow:hidden}
.sidebar-brand a{color:#fff;text-decoration:none;font-weight:800}
.short{display:none}
.sidebar.collapsed .full{display:none}
.sidebar.collapsed .short{display:inline}
.sidebar-menu{flex:1;padding:20px 10px;overflow:hidden}
.menu-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:14px;font-weight:500;cursor:pointer;transition:var(--transition);margin-bottom:4px;white-space:nowrap;overflow:hidden}
.menu-item i{width:20px;text-align:center;font-size:15px;flex-shrink:0}
.menu-item:hover{background:#1e293b;color:#fff}
.menu-item.active{background:#6366f1;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.4)}
.sidebar.collapsed .menu-item span{display:none}
.sidebar.collapsed .menu-item{justify-content:center;gap:0}
.sidebar-collapse-btn{width:100%;padding:14px;background:none;border:none;border-top:1px solid rgba(255,255,255,.06);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:var(--transition)}
.sidebar-collapse-btn:hover{background:#1e293b;color:#fff}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-width);transition:var(--transition);min-height:100vh}
.sidebar.collapsed~.main{margin-left:var(--sidebar-collapsed)}

/* ── HEADER ── */
.header{height:var(--header-height);background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:400;transition:transform .3s ease,opacity .3s ease;transform:translateY(0);opacity:1}
.header.hide{transform:translateY(-100%);opacity:0;pointer-events:none}
.header h1{margin:0;font-size:26px;font-weight:700}
.header-date{font-size:13px;color:var(--text-secondary);padding:6px 14px;background:var(--bg);border-radius:6px;font-weight:500;display:flex;align-items:center;gap:8px}
.header-date i{color:var(--primary)}

/* ── CONTAINER ── */
.container{padding:24px 28px}

/* ── KPI CARDS ── */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px}
.card{background:#fff;border-radius:14px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.05);border-left:4px solid var(--primary)}
.card .label{font-size:12px;color:#6b7280;font-weight:500;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.card .value{font-size:30px;font-weight:800;color:var(--text)}

/* ── CHART GRID ── */
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:22px}

/* ── CHART BOX ── */
.chart-box{background:#fff;border-radius:14px;border:1px solid var(--border);box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:visible;position:relative}
.chart-box-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.chart-title{font-size:14px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.chart-title i{color:var(--primary)}
.metric-total{display:inline-flex;align-items:center;gap:8px;background:var(--primary-bg);color:var(--primary);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;white-space:nowrap}

/* ── RANGOS ── */
.range-group{display:flex;gap:6px;flex-wrap:wrap;padding:10px 20px 0;position:relative;z-index:8}
.range-btn{border:1px solid var(--border);background:#fff;color:var(--text-secondary);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:600;cursor:pointer;transition:var(--transition)}
.range-btn:hover{border-color:var(--primary);color:var(--primary)}
.range-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.25)}

/* ── FILTROS HORIZONTALES ── */
.filters-bar{display:flex;flex-wrap:wrap;gap:10px;padding:12px 20px;background:#f8fafc;border-bottom:1px solid var(--border);align-items:flex-end;position:relative;z-index:20}
.filter-pill{display:flex;flex-direction:column;gap:3px;flex:1;min-width:120px;max-width:200px}
.filter-pill label{font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px}
.filter-actions{display:flex;align-items:flex-end;gap:6px;flex-shrink:0}
.btn-limpiar{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--text-secondary);font-size:11px;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap;font-family:'Inter',sans-serif}
.btn-limpiar:hover{background:#fee2e2;border-color:#fca5a5;color:#dc2626}

/* ── CANVAS ── */
.chart-canvas-wrap{padding:18px 20px 20px}
canvas{width:100%!important;height:285px!important}

/* ── TOM SELECT ── */
.ts-wrapper{font-size:12px;position:relative;z-index:15}
.ts-control{min-height:32px!important;border:1.5px solid var(--border)!important;border-radius:6px!important;padding:4px 8px!important;box-shadow:none!important;background:#fff!important;font-size:12px!important}
.ts-control input{font-size:12px!important}
.ts-dropdown{border:1px solid var(--border)!important;border-radius:8px!important;z-index:99999!important;background:#fff!important;font-size:12px!important}
.ts-dropdown .option{font-size:12px;padding:8px 10px}
.ts-dropdown .active{background:var(--primary-bg)!important;color:var(--primary-dark)!important}
.ts-wrapper .item{background:var(--primary-bg)!important;color:var(--primary)!important;border-radius:4px!important;font-size:11px!important;padding:1px 5px!important}
.ts-wrapper .remove{color:var(--primary)!important}
.ts-wrapper.focus .ts-control{border-color:var(--primary)!important;box-shadow:0 0 0 3px rgba(99,102,241,.12)!important}

/* ── LOADING OVERLAY ── */
.chart-loading{display:none;position:absolute;inset:0;background:rgba(255,255,255,.8);border-radius:14px;align-items:center;justify-content:center;z-index:10}
.chart-loading.active{display:flex}
.spinner{width:32px;height:32px;border:3px solid #e0e7ff;border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── TOAST ── */
.toast{position:fixed;top:20px;right:20px;background:#0f172a;color:#fff;padding:14px 22px;border-radius:var(--radius);font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);transform:translateY(-120px);opacity:0;transition:all .35s ease;z-index:99999}
.toast.show{transform:translateY(0);opacity:1}
.toast.success i{color:#10b981}
.toast.info i{color:#06b6d4}

/* ── BOTÓN EXPORTAR ── */
.btn-download{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;margin-right: -50px;border:1px solid var(--primary);background:var(--primary-bg);color:var(--primary-dark);cursor:pointer;transition:.2s;font-family:'Inter',sans-serif}
.btn-download:hover{background:var(--primary);color:#fff;box-shadow:0 6px 18px rgba(99,102,241,.35)}
.btn-export{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:.25s;font-family:'Inter',sans-serif}
.btn-export.primary{background:var(--primary);color:#fff}
.btn-export.primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(99,102,241,.35)}
.btn-export.success{background:#10b981;color:#fff}
.btn-export.success:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(16,185,129,.35)}
.btn-export.neutral{background:#f1f5f9;color:#475569}
.btn-export.neutral:hover{background:#fee2e2;color:#dc2626}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99998;align-items:center;justify-content:center}
.modal-box{background:#fff;width:100%;max-width:520px;border-radius:14px;padding:24px}
.modal-box h3{font-size:16px;font-weight:700;margin-bottom:14px}
.modal-box label{font-size:13px;display:flex;gap:8px;align-items:center;padding:6px 0;cursor:pointer}
.modal-box input[type=checkbox]{accent-color:var(--primary);width:16px;height:16px}
.modal-info{font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.5}
.modal-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:20px}

/* ── RESPONSIVE ── */
@media(max-width:900px){
    .sidebar{position:relative;width:100%;height:auto}
    .main{width:100%;margin-left:0}
    .grid{grid-template-columns:1fr}
    canvas{height:240px!important}
    .filter-pill{min-width:100px}
}
</style>
</head>
<body>

<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span class="full"><a href="https://incidencias.grupoatu.com/">Grupo ATU</a></span>
            <span class="short"><a href="https://incidencias.grupoatu.com/">ATU</a></span>
        </div>
    </div>
    <nav class="sidebar-menu">
        <a href="incidencias.php" class="menu-item">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Tabla de Incidencias</span>
        </a>
        <a href="estadisticas.php" class="menu-item">
            <i class="fas fa-chart-bar"></i>
            <span>Estadísticas Incidencias</span>
        </a>
		<hr class="linea-menu"> <!-- Línea normal -->
        <a href="valoraciones.php" class="menu-item">
            <i class="fa-solid fa-clipboard-list"></i>
            <span>Tabla de Valoraciones</span>
        </a>
        <a href="estadisticas_valoraciones.php" class="menu-item active">
            <i class="fas fa-chart-pie"></i>
            <span>Estadísticas Valoraciones</span>
        </a>
		<hr class="linea-menu"> <!-- Línea normal -->
		<a href="coordinadores.php"  class="menu-item">
			<i class="fa-solid fa-user-tie"></i>
			<span>Tabla de Coodinadores</span>
		</a>
		<a href="estadisticas_coordinadores.php" class="menu-item">
			<i class="fa-solid fa-chart-line"></i>
			<span>Estadísticas Coordinadores</span>
		</a>
    </nav>
    <button class="sidebar-collapse-btn" onclick="toggleSidebar()">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
</aside>

<!-- ═══════════════════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════════════════ -->
<div class="main">

    <!-- HEADER -->
    <div class="header" id="mainHeader">
        <h1>Estadísticas Valoraciones</h1>
        <button class="btn-download" onclick="openExportModal()">
            <i class="fas fa-download"></i> Descargar estadísticas
        </button>
        <div style="display: flex; align-items: center; gap: 20px;">
    <!-- Fecha y hora -->
    <div class="header-date">
        <i class="far fa-calendar-alt"></i>
        <span id="liveDateText"><?php echo date('d/m/Y H:i:s'); ?></span>
    </div>
    
    <!-- Info del usuario y Botón Cerrar Sesión -->
    <div style="display: flex; align-items: center; gap: 12px; border-left: 1px solid var(--border); padding-left: 20px;">
        <span style="font-size: 13px; color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-user-circle" style="color: var(--primary); font-size: 16px;"></i> 
            <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
        </span>
        <a href="?logout=1" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #fee2e2; color: #ef4444; text-decoration: none; transition: 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='#fff';" onmouseout="this.style.background='#fee2e2'; this.style.color='#ef4444';" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
    </div>

    <div class="container">

        <!-- KPI CARDS -->
        <div class="cards">
            <div class="card">
                <div class="label">Total valoraciones</div>
                <div class="value"><?= number_format($kpi_total) ?></div>
            </div>
            <div class="card">
                <div class="label">Valoraciones hoy</div>
                <div class="value"><?= number_format($kpi_hoy) ?></div>
            </div>
            <div class="card">
                <div class="label">Últimos 7 días</div>
                <div class="value"><?= number_format($kpi_semana) ?></div>
            </div>
            <div class="card">
                <div class="label">Media satisfacción</div>
                <div class="value"><?= h($kpi_media) ?>/10</div>
            </div>
        </div>

        <!-- GRÁFICAS -->
        <div class="grid">

        <?php foreach ($graficas as [$gid, $gtitle, $gicon, $gtype]): ?>
            <div class="chart-box" id="box-<?= $gid ?>">
                <div class="chart-loading" id="loading-<?= $gid ?>"><div class="spinner"></div></div>

                <!-- Cabecera -->
                <div class="chart-box-header">
                    <div class="chart-title">
                        <i class="fas <?= $gicon ?>"></i> <?= $gtitle ?>
                    </div>
                    <div class="metric-total">
                        <span id="total-<?= $gid ?>">—</span> valoraciones
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filters-bar">
                    <?php foreach ($CAMPOS_VAL as $campo): ?>
                    <div class="filter-pill">
                        <label><?= h($LABELS_VAL[$campo] ?? ucfirst($campo)) ?></label>
                        <select class="chart-filter"
                                data-chart="<?= $gid ?>"
                                data-field="<?= h($campo) ?>"
                                id="sel-<?= $gid ?>-<?= $campo ?>"
                                multiple>
                        </select>
                    </div>
                    <?php endforeach; ?>
                    <div class="filter-actions">
                        <button class="btn-limpiar" onclick="limpiarFiltros('<?= $gid ?>')">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Botones de rango -->
                <?= render_range_buttons_val($gid) ?>

                <!-- Canvas -->
                <div class="chart-canvas-wrap">
                    <canvas id="<?= $gid ?>Chart"></canvas>
                </div>
            </div>
        <?php endforeach; ?>

        </div><!-- /grid -->
    </div><!-- /container -->
</div><!-- /main -->

<!-- ═══════════════════════════════════════════════════════════
     MODAL DETALLE (click en gráfica)
═══════════════════════════════════════════════════════════ -->
<div id="detailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100001;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;width:100%;max-width:980px;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.25)">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <h3 id="detailModalTitle" style="margin:0;font-size:16px">Detalle</h3>
            <button type="button" onclick="closeDetailModal();return false;" style="border:none;background:none;font-size:24px;cursor:pointer">&times;</button>
        </div>
        <div id="detailModalBody" style="padding:20px;max-height:70vh;overflow:auto"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL EXPORTAR
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="exportModal">
    <div class="modal-box">
        <h3>Descargar estadísticas</h3>
        <p class="modal-info">Selecciona las estadísticas que deseas descargar. Puedes exportarlas en formato imagen (PNG) o datos (CSV).</p>
        <div style="display:grid;gap:4px;margin-bottom:8px">
            <?php foreach ($graficas as [$gid, $gtitle]): ?>
            <label><input type="checkbox" value="<?= $gid ?>" checked> <?= h($gtitle) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="modal-actions">
            <button onclick="exportCharts('png')"   class="btn-export primary"><i class="fas fa-image"></i> PNG</button>
            <button onclick="exportCharts('excel')" class="btn-export success"><i class="fas fa-file-excel"></i> CSV</button>
            <button onclick="closeExportModal()"    class="btn-export neutral">Cancelar</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════ -->
<script>
/* ── RELOJ ── */
(function clock(){
    var el=document.getElementById('liveDateText');
    function tick(){
        var n=new Date(),p=function(x){return String(x).padStart(2,'0');};
        el.textContent=p(n.getDate())+'/'+p(n.getMonth()+1)+'/'+n.getFullYear()+' '+p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());
    }
    tick(); setInterval(tick,1000);
})();

/* ── SIDEBAR ── */
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); }

/* ── TOAST ── */
function showToast(msg,type){
    var t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    t.querySelector('i').className=(type==='success'?'fas fa-check-circle':'fas fa-info-circle');
    t.className='toast '+type+' show';
    setTimeout(function(){t.classList.remove('show');},3000);
}

/* ── PALETA ── */
var PALETTE=['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6','#f97316','#84cc16'];
function pal(n){return Array.from({length:n},function(_,i){return PALETTE[i%PALETTE.length];});}

/* ── ESTADO GLOBAL ── */
var defaultRanges = <?= json_encode($default_ranges, JSON_UNESCAPED_UNICODE) ?>;
var chartRanges   = Object.assign({}, defaultRanges);
var charts        = {};

// Filtros activos por métrica: { evolucion:{empresa:[...], ...}, ... }
var currentFilters = {};
<?php foreach ($graficas as [$gid]): ?>
currentFilters['<?= $gid ?>'] = {};
<?php endforeach; ?>

var availableFilters = {};
var tomInstances     = {};

/* ── SINCRONIZAR BOTONES DE RANGO ── */
function syncButtons(metric, range){
    document.querySelectorAll('.range-btn[data-metric="'+metric+'"]').forEach(function(btn){
        btn.classList.toggle('active', btn.dataset.range === range);
    });
}

function setRange(metric, range){
    chartRanges[metric] = range;
    syncButtons(metric, range);
    loadMetric(metric);
}

/* ── CONSTRUIR URL AJAX ── */
function buildUrl(metric){
    var range = chartRanges[metric] || defaultRanges[metric] || 'historico';
    var parts = ['ajax=1', 'metric='+encodeURIComponent(metric), 'range='+encodeURIComponent(range)];
    var f = currentFilters[metric] || {};
    Object.keys(f).forEach(function(campo){
        var vals = Array.isArray(f[campo]) ? f[campo] : [f[campo]];
        vals.forEach(function(v){
            if (v !== null && v !== undefined && String(v).trim() !== '')
                parts.push('filter_'+campo+'%5B%5D='+encodeURIComponent(v));
        });
    });
    return 'estadisticas_valoraciones.php?'+parts.join('&');
}

/* ── TIPOS DE GRÁFICA ── */
var CHART_TYPES = {
    evolucion:    'line',
    satisfaccion: 'bar',
    empresas:     'doughnut',
};

/* ── CREAR / ACTUALIZAR GRÁFICA ── */
function createChart(metric, d){
    var ctx = document.getElementById(metric+'Chart');
    if (!ctx) return;
    var type    = CHART_TYPES[metric] || 'bar';
    var isMulti = ['doughnut','pie'].includes(type);

    var cfg = {
        type: type,
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Valoraciones',
                data: d.values,
                backgroundColor: isMulti ? pal(d.labels.length) : PALETTE[0]+'33',
                borderColor:     isMulti ? pal(d.labels.length) : PALETTE[0],
                borderWidth: 2,
                tension: 0.35,
                fill: type === 'line',
                pointBackgroundColor: PALETTE[0],
                pointRadius: type === 'line' ? 3 : 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: function(evt, elements, chart) {
                if (!elements || !elements.length) return;
                var idx = elements[0].index;
                var clickedLabel = chart.data.labels[idx] || '';
                openDetailModal(metric, clickedLabel, chartRanges[metric] || defaultRanges[metric]);
            },
            plugins: {
                legend: {
                    position: isMulti ? 'right' : 'top',
                    labels: { font:{size:11}, boxWidth:12, padding:12 }
                },
                tooltip: { callbacks: { label: function(ctx){ return ' '+ctx.formattedValue; } } }
            },
            scales: isMulti ? {} : {
                x: { grid:{color:'#f1f5f9'}, ticks:{font:{size:11}} },
                y: { grid:{color:'#f1f5f9'}, ticks:{font:{size:11}}, beginAtZero:true }
            }
        }
    };

    if (charts[metric]) { charts[metric].destroy(); }
    charts[metric] = new Chart(ctx, cfg);
}

/* ── CARGAR MÉTRICA VÍA AJAX ── */
function loadMetric(metric){
    var loading = document.getElementById('loading-'+metric);
    if (loading) loading.classList.add('active');

    fetch(buildUrl(metric))
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) throw new Error(d.msg || 'Error');
            var te = document.getElementById('total-'+metric);
            if (te) te.textContent = d.total || 0;
            syncButtons(metric, d.range);
            createChart(metric, d);
        })
        .catch(function(e){ console.error('loadMetric['+metric+']', e); showToast('Error cargando '+metric,'info'); })
        .finally(function(){ if (loading) loading.classList.remove('active'); });
}

function loadAllMetrics(){
    <?php foreach ($graficas as [$gid]): ?>
    loadMetric('<?= $gid ?>');
    <?php endforeach; ?>
}

/* ── MODAL DETALLE ── */
function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; }
document.addEventListener('click', function(e) { if (e.target && e.target.id === 'detailModal') closeDetailModal(); });

function escapeHtml(text) {
    return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function openDetailModal(metric, label, range) {
    var p = new URLSearchParams();
    p.set('ajax_detail', '1');
    p.set('metric', metric);
    p.set('label', label);
    p.set('range', range);
    var filtros = currentFilters[metric] || {};
    Object.keys(filtros).forEach(function(key) {
        var vals = filtros[key];
        if (vals) { (Array.isArray(vals) ? vals : [vals]).forEach(function(v) { p.append('filter_' + key + '[]', v); }); }
    });
    fetch('estadisticas_valoraciones.php?' + p.toString())
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) throw new Error(d.msg || 'Error');
            document.getElementById('detailModalTitle').textContent = 'Detalle: ' + d.label;
            var html = '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px">';
            html += '<div style="background:#f8fafc;padding:12px;border-radius:10px"><strong>Total tickets</strong><div style="margin-top:6px;font-size:20px">' + escapeHtml(d.resumen.total || 0) + '</div></div>';
            html += '<div style="background:#f8fafc;padding:12px;border-radius:10px"><strong>Desde</strong><div style="margin-top:6px">' + escapeHtml(d.resumen.fecha_min || '-') + '</div></div>';
            html += '<div style="background:#f8fafc;padding:12px;border-radius:10px"><strong>Hasta</strong><div style="margin-top:6px">' + escapeHtml(d.resumen.fecha_max || '-') + '</div></div>';
            html += '</div><div style="margin-bottom:18px"><strong>Rango aplicado:</strong> ' + escapeHtml(d.rangeLabel || '-') + '</div>';
            html += '<h4 style="margin:14px 0 8px">Cursos relacionados</h4><div style="display:grid;gap:8px">';
            if ((d.cursos || []).length) {
                d.cursos.forEach(function(row) {
                    html += '<div style="display:flex;justify-content:space-between;background:#f8fafc;padding:10px 12px;border-radius:8px"><span>' + escapeHtml(row.label) + '</span><strong>' + escapeHtml(row.total) + '</strong></div>';
                });
            } else {
                html += '<div style="background:#f8fafc;padding:10px 12px;border-radius:8px">Sin datos</div>';
            }
            html += '</div>';
            document.getElementById('detailModalBody').innerHTML = html;
            document.getElementById('detailModal').style.display = 'flex';
        })
        .catch(function(e) { showToast(e.message || 'No se pudo cargar el detalle', 'info'); });
}

/* ── LIMPIAR FILTROS ── */
function limpiarFiltros(metric){
    currentFilters[metric] = {};
    document.querySelectorAll('[data-chart="'+metric+'"]').forEach(function(el){
        if (el.tomselect) el.tomselect.clear();
    });
    loadMetric(metric);
    showToast('Filtros limpiados','success');
}

/* ── FILTROS EN CASCADA ── */
var CASCADE_VAL = <?= json_encode($CAMPOS_VAL, JSON_UNESCAPED_UNICODE) ?>;

function updateDownstreamFilters(metric, changedField){
    var startIdx = CASCADE_VAL.indexOf(changedField);
    if (startIdx === -1 || startIdx >= CASCADE_VAL.length - 1) return;

    var activeFilters = {};
    for (var i = 0; i <= startIdx; i++){
        var f = CASCADE_VAL[i];
        if (currentFilters[metric] && currentFilters[metric][f] && currentFilters[metric][f].length)
            activeFilters[f] = currentFilters[metric][f];
    }

    var parts = ['ajax_filters=1'];
    Object.keys(activeFilters).forEach(function(key){
        activeFilters[key].forEach(function(v){
            parts.push('filter_'+key+'%5B%5D='+encodeURIComponent(v));
        });
    });

    fetch('estadisticas_valoraciones.php?'+parts.join('&'))
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.ok || !data.filters) return;
            for (var j = startIdx+1; j < CASCADE_VAL.length; j++){
                var depType    = CASCADE_VAL[j];
                var newOptions = data.filters[depType] || [];
                var key        = metric+'_'+depType;
                var ts         = tomInstances[key];
                if (!ts) continue;
                var currentVal = ts.getValue();
                ts.clear(true); ts.clearOptions();
                ts.addOptions(newOptions.map(function(o){ return {value:o, text:o}; }));
                var valid = (Array.isArray(currentVal)?currentVal:[currentVal]).filter(function(v){ return newOptions.indexOf(v) !== -1; });
                if (valid.length){ ts.setValue(valid, true); currentFilters[metric][depType] = valid; }
                else { delete currentFilters[metric][depType]; }
                ts.refreshOptions(false);
            }
        })
        .catch(function(e){ console.error('cascade error:', e); });
}

/* ── POPULAR SELECTS CON OPCIONES ── */
function populateSelectOptions(){
    document.querySelectorAll('.chart-filter').forEach(function(select){
        var campo = select.dataset.field;
        var opts  = (availableFilters && availableFilters[campo]) ? availableFilters[campo] : [];
        select.innerHTML = opts.map(function(v){
            return '<option value="'+v.replace(/"/g,'&quot;')+'">'+v+'</option>';
        }).join('');
    });
}

/* ── INICIALIZAR TOM SELECT ── */
function initializeTomSelects(){
    document.querySelectorAll('.chart-filter').forEach(function(el){
        var metric = el.dataset.chart;
        var campo  = el.dataset.field;
        var key    = metric+'_'+campo;
        if (tomInstances[key]) { tomInstances[key].destroy(); }

        var ts = new TomSelect(el, {
            plugins: ['remove_button'],
            create: false,
            hideSelected: true,
            closeAfterSelect: false,
            placeholder: 'Todos...',
            maxItems: null,
            dropdownParent: 'body',
            onChange: function(vals){
                vals = Array.isArray(vals) ? vals : (vals ? [vals] : []);
                if (!currentFilters[metric]) currentFilters[metric] = {};
                if (vals.length > 0) currentFilters[metric][campo] = vals;
                else delete currentFilters[metric][campo];
                loadMetric(metric);
                updateDownstreamFilters(metric, campo);
            }
        });

        tomInstances[key] = ts;
    });
}

/* ── INICIALIZACIÓN PRINCIPAL ── */
async function initializeApp(){
    // Sincronizar botones de rango
    Object.keys(defaultRanges).forEach(function(m){ syncButtons(m, chartRanges[m] || defaultRanges[m]); });

    // Cargar opciones de filtros
    try {
        var resp = await fetch('estadisticas_valoraciones.php?ajax_filters=1');
        var data = await resp.json();
        if (!data.ok) throw new Error(data.msg || 'Error al cargar filtros');
        availableFilters = data.filters;
        populateSelectOptions();
        initializeTomSelects();
    } catch(e) {
        console.error('Error cargando filtros:', e);
        populateSelectOptions();
        initializeTomSelects();
    }

    // Cargar todas las gráficas
    loadAllMetrics();
}

document.addEventListener('DOMContentLoaded', initializeApp);

// Auto-refresco cada 60 segundos
setInterval(loadAllMetrics, 60000);

/* ── RANGOS: event listeners ── */
document.addEventListener('click', function(e){
    if (e.target.classList.contains('range-btn'))
        setRange(e.target.dataset.metric, e.target.dataset.range);
});

/* ── EXPORTACIÓN ── */
function openExportModal() { document.getElementById('exportModal').style.display='flex'; }
function closeExportModal(){ document.getElementById('exportModal').style.display='none'; }

document.getElementById('exportModal').addEventListener('click', function(e){
    if (e.target === this) closeExportModal();
});

function exportCharts(type){
    var metrics = Array.from(document.querySelectorAll('#exportModal input[type=checkbox]:checked')).map(function(cb){ return cb.value; });
    if (type === 'png'){
        metrics.forEach(function(metric){
            var canvas = document.getElementById(metric+'Chart');
            if (!canvas) return;
            var nc = document.createElement('canvas');
            nc.width = canvas.width; nc.height = canvas.height + 80;
            var nctx = nc.getContext('2d');
            nctx.fillStyle='#fff'; nctx.fillRect(0,0,nc.width,nc.height);
            nctx.fillStyle='#000'; nctx.font='bold 16px Inter,sans-serif';
            nctx.fillText('Estadísticas Valoraciones: '+metric, 20, 25);
            nctx.font='12px Inter,sans-serif';
            var ftxt = Object.keys(currentFilters[metric]||{}).map(function(k){ return k+': '+(currentFilters[metric][k]||[]).join(', '); }).join(' | ');
            nctx.fillText('Filtros: '+(ftxt||'Ninguno'), 20, 50);
            nctx.drawImage(canvas, 0, 80);
            var a = document.createElement('a');
            a.download = 'valoraciones_'+metric+'.png';
            a.href = nc.toDataURL('image/png');
            a.click();
        });
    } else if (type === 'excel'){
        metrics.forEach(function(metric){
            fetch(buildUrl(metric))
                .then(function(r){ return r.json(); })
                .then(function(d){
                    var rows = [['Etiqueta','Total']];
                    (d.labels||[]).forEach(function(label,i){ rows.push(['"'+label+'"', d.values[i]||0]); });
                    var csv  = rows.map(function(r){ return r.join(','); }).join('\n');
                    var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
                    var a    = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'valoraciones_'+metric+'.csv';
                    a.click();
                });
        });
    }
    closeExportModal();
}

/* ── SCROLL: ocultar/mostrar header ── */
(function(){
    var lastST = 0;
    var hdr    = document.getElementById('mainHeader');
    window.addEventListener('scroll', function(){
        var st = window.pageYOffset || document.documentElement.scrollTop;
        if (st > lastST && st > 80) { if (hdr) hdr.classList.add('hide'); }
        else { if (hdr) hdr.classList.remove('hide'); }
        lastST = st <= 0 ? 0 : st;
    });
})();
</script>
</body>
</html>