<?php
/**
 * =============================================================================
 * estadisticas_coordinadores.php — Panel de estadísticas de coordinadores
 * topic_id=14 / form_id=22
 * =============================================================================
 */

session_start();
require_once __DIR__ . '/auth_admin_sso.php';

// ── MINILOGIN ──────────────────────────────────────────────────────────────
$USUARIOS_PERMITIDOS = [
    'Admin'          => '1,<X8r0.5(Tl03?-gq]giU',
    'IncidenciasAtu' => 'D/*50smPm@7FPM@c£EUMU&',
];

if (isset($_GET['logout'])) {
    if (!empty($_SESSION['admin_sso'])) admin_sso_logout();
    else session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'], $_POST['login_password'])) {
    $u = $_POST['login_username'];
    $p = $_POST['login_password'];
    if (isset($USUARIOS_PERMITIDOS[$u]) && $USUARIOS_PERMITIDOS[$u] === $p) {
        if ($u === ADMIN_SSO_USER) admin_sso_activate();
        else { $_SESSION['coord_stats_auth'] = true; $_SESSION['coord_stats_user'] = $u; }
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    } else { $login_error = 'Usuario o contraseña incorrectos.'; }
}

$is_logged_in = !empty($_SESSION['coord_stats_auth']) || !empty($_SESSION['admin_sso']);

if (!$is_logged_in) {
    if (isset($_GET['ajax']) || isset($_GET['ajax_filters'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Sesión caducada.']); exit;
    }
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Acceso — Estadísticas Coordinadores</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body{background:#f1f5f9;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
        .lc{background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.05);width:100%;max-width:360px;text-align:center}
        .lc i{font-size:40px;color:#6366f1;margin-bottom:15px}
        .lc h2{margin:0 0 25px;color:#0f172a;font-size:22px}
        .lc input{width:100%;padding:12px 15px;margin-bottom:15px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;font-family:'Inter';font-size:14px;outline:none;transition:border .3s}
        .lc input:focus{border-color:#6366f1}
        .lc button{width:100%;padding:12px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}
        .lc button:hover{background:#4f46e5}
        .err{color:#ef4444;background:#fee2e2;padding:10px;border-radius:6px;font-size:13px;margin-bottom:15px}
    </style></head><body><div class="lc">
        <i class="fas fa-user-tie"></i><h2>Estadísticas Coordinadores</h2>
        <?php if ($login_error): ?><div class="err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($login_error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="login_username" placeholder="Usuario" required autofocus>
            <input type="password" name="login_password" placeholder="Contraseña" required>
            <button type="submit">Iniciar Sesión</button>
        </form></div></body></html><?php exit;
}
// ── FIN MINILOGIN ──────────────────────────────────────────────────────────

ini_set('display_errors', 0); ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_error_coord_stats.log');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

if (!defined('ROOT_DIR'))    define('ROOT_DIR',    '/var/www/vhosts/incidencias.grupoatu.com/httpdocs/osticket/');
if (!defined('INCLUDE_DIR')) define('INCLUDE_DIR', ROOT_DIR . 'include/');

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
    if (isset($_GET['ajax']) || isset($_GET['ajax_filters'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Error de conexión a la BD']); exit;
    }
    die('Error crítico de conexión a la BD: ' . htmlspecialchars($e->getMessage()));
}

define('COORD_TOPIC_ID', 14);
define('COORD_FORM_ID',  22);

// ── HELPERS ───────────────────────────────────────────────────────────────
function h_c($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function val_c($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    if ($v[0] === '{') { $j = json_decode($v, true); if (is_array($j)) return trim(reset($j)); }
    return $v;
}

// ── RANGOS ────────────────────────────────────────────────────────────────
function rango_coord(string $r): array {
    $now = new DateTimeImmutable('now');
    $fin = $now->setTime(23,59,59)->format('Y-m-d H:i:s');
    switch ($r) {
        case 'day':     return [$now->setTime(0,0,0)->format('Y-m-d H:i:s'), $fin, 'Día'];
        case 'week':    return [$now->modify('-6 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $fin, 'Última semana'];
        case 'month':   return [$now->modify('-29 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $fin, 'Último mes'];
        case 'quarter': return [$now->modify('-89 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $fin, 'Último trimestre'];
        case 'year':    return [$now->modify('-364 days')->setTime(0,0,0)->format('Y-m-d H:i:s'), $fin, '1 año'];
        default:        return [null, null, 'Histórico'];
    }
}

function render_rango_coord(string $metric): string {
    $btns = ['day'=>'Día','week'=>'Semana','month'=>'Mes','quarter'=>'Trimestre','year'=>'1 año','historico'=>'Histórico'];
    $html = '<div class="range-group">';
    foreach ($btns as $k => $l)
        $html .= '<button type="button" class="range-btn" data-metric="'.h_c($metric).'" data-range="'.$k.'">'.$l.'</button>';
    return $html . '</div>';
}

// ── CAMPOS DE FILTRO ──────────────────────────────────────────────────────
$CAMPOS_COORD = ['planes','sectores','acciones','grupos','nombrescursos'];
$LABELS_COORD = ['planes'=>'Plan','sectores'=>'Sector','acciones'=>'Acción','grupos'=>'Grupo','nombrescursos'=>'Curso'];

// ── WHERE ─────────────────────────────────────────────────────────────────
function where_coord(mysqli $db, array $filters): string {
    $w = " WHERE t.topic_id = " . COORD_TOPIC_ID . " ";
    foreach ($filters as $campo => $vals) {
        $vals = array_values(array_filter(array_map('trim', (array)$vals)));
        if (empty($vals)) continue;
        $ors = [];
        foreach ($vals as $v) {
            $ve = $db->real_escape_string($v);
            $ce = $db->real_escape_string($campo);
            $ors[] = "EXISTS(SELECT 1 FROM ost_form_entry fe2
                JOIN ost_form_entry_values fev2 ON fev2.entry_id=fe2.id
                JOIN ost_form_field ff2 ON ff2.id=fev2.field_id
                WHERE fe2.object_id=t.ticket_id AND fe2.form_id=".COORD_FORM_ID."
                AND ff2.name='{$ce}' AND fev2.value LIKE '%{$ve}%')";
        }
        $w .= " AND (" . implode(' OR ', $ors) . ") ";
    }
    return $w;
}

function where_rango_coord(string $w, mysqli $db, ?string $from, ?string $to): string {
    if ($from) $w .= " AND t.created >= '" . $db->real_escape_string($from) . "' ";
    if ($to)   $w .= " AND t.created <= '" . $db->real_escape_string($to)   . "' ";
    return $w;
}

// ── FILTROS EN CASCADA ────────────────────────────────────────────────────
function get_filters_coord(mysqli $db, array $applied, array $campos): array {
    $where = where_coord($db, $applied);
    $out = [];
    foreach ($campos as $campo) {
        $out[$campo] = [];
        $ce = $db->real_escape_string($campo);
        $q = $db->query("SELECT DISTINCT fev.value v
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id=".COORD_FORM_ID."
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name='{$ce}' AND fev.value IS NOT NULL AND TRIM(fev.value)!=''
            ORDER BY fev.value ASC");
        if ($q) while ($r = $q->fetch_assoc()) {
            $v = trim(val_c($r['v']));
            if ($v !== '') $out[$campo][$v] = $v;
        }
        $out[$campo] = array_values($out[$campo]);
        sort($out[$campo], SORT_STRING | SORT_FLAG_CASE);
    }
    return $out;
}

// ── HELPER: media de un campo numérico ───────────────────────────────────
function media_campo_coord(mysqli $db, string $campo, string $where): float {
    $ce = $db->real_escape_string($campo);
    $fid = COORD_FORM_ID;
    $q = $db->query("SELECT ROUND(AVG(CAST(NULLIF(TRIM(fev.value),'') AS DECIMAL(10,2))),2) AS m
        FROM ost_ticket t
        JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        {$where} AND ff.name='{$ce}' AND fev.value REGEXP '^[0-9]'");
    return $q ? (float)($q->fetch_assoc()['m'] ?? 0) : 0;
}

// ── MÉTRICAS ──────────────────────────────────────────────────────────────
function metric_query_coord(mysqli $db, string $metric, string $range, array $filters): array {
    $validas = ['evolucion','planes','alumnos','desempeno_tutor','desempeno_apoyo','desempeno_dinamizacion','incidencias_valoracion'];
    if (!in_array($metric, $validas, true)) $metric = 'evolucion';

    [$from, $to, $rangeLabel] = rango_coord($range);
    $where = where_coord($db, $filters);
    $where = where_rango_coord($where, $db, $from, $to);
    $labels = []; $values = []; $total = 0;
    $fid = COORD_FORM_ID;

    // ── 1. EVOLUCIÓN TEMPORAL ────────────────────────────────────────────
    if ($metric === 'evolucion') {
        if ($range === 'day') {
            $sql = "SELECT DATE_FORMAT(t.created,'%H:00') label, HOUR(t.created) ord, COUNT(*) total FROM ost_ticket t {$where} GROUP BY 1,2 ORDER BY 2 ASC";
        } elseif (in_array($range, ['year','historico'], true)) {
            $sql = "SELECT DATE_FORMAT(t.created,'%m/%Y') label, DATE_FORMAT(t.created,'%Y-%m') ord, COUNT(*) total FROM ost_ticket t {$where} GROUP BY 2,1 ORDER BY 2 ASC";
        } else {
            $sql = "SELECT DATE_FORMAT(t.created,'%d/%m') label, DATE(t.created) ord, COUNT(*) total FROM ost_ticket t {$where} GROUP BY 2,1 ORDER BY 2 ASC";
        }
        $q = $db->query($sql);
        while ($r = $q->fetch_assoc()) { $labels[] = $r['label']; $values[] = (int)$r['total']; $total += (int)$r['total']; }

    // ── 2. DISTRIBUCIÓN POR PLAN ─────────────────────────────────────────
    } elseif ($metric === 'planes') {
        $q = $db->query("SELECT fev.value label, COUNT(DISTINCT t.ticket_id) total
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name='planes' AND fev.value IS NOT NULL AND TRIM(fev.value)!=''
            GROUP BY fev.value ORDER BY total DESC LIMIT 15");
        while ($r = $q->fetch_assoc()) {
            $lbl = trim(val_c($r['label'])); if ($lbl === '') $lbl = 'Sin dato';
            $labels[] = $lbl; $values[] = (int)$r['total']; $total += (int)$r['total'];
        }

    // ── 3. ALUMNOS MATRICULADOS VS FINALIZADOS ───────────────────────────
    } elseif ($metric === 'alumnos') {
        // Agrupa por acción: suma ndealumnos y nfinalizados
        $q = $db->query("
            SELECT
                MAX(CASE WHEN ff.name='acciones'    THEN fev.value END) AS accion,
                SUM(CASE WHEN ff.name='ndealumnos'  THEN CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED) ELSE 0 END) AS matriculados,
                SUM(CASE WHEN ff.name='nfinalizados' THEN CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED) ELSE 0 END) AS finalizados
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name IN ('acciones','ndealumnos','nfinalizados')
            GROUP BY t.ticket_id");
        $temp = [];
        while ($r = $q->fetch_assoc()) {
            $a = trim(val_c($r['accion'] ?? '')); if ($a === '') $a = 'Sin acción';
            if (!isset($temp[$a])) $temp[$a] = [0, 0];
            $temp[$a][0] += (int)$r['matriculados'];
            $temp[$a][1] += (int)$r['finalizados'];
        }
        uasort($temp, function($a,$b){ return $b[0] <=> $a[0]; });
        $temp = array_slice($temp, 0, 12, true);
        $values2 = [];
        foreach ($temp as $a => $d) { $labels[] = $a; $values[] = $d[0]; $values2[] = $d[1]; $total += $d[0]; }
        return ['ok'=>true,'metric'=>$metric,'range'=>$range,'rangeLabel'=>$rangeLabel,
                'labels'=>$labels,'values'=>$values,'values2'=>$values2,'total'=>$total,
                'label1'=>'Matriculados','label2'=>'Finalizados'];

    // ── 4. DESEMPEÑO TUTOR/A (media de 6 indicadores) ────────────────────
    } elseif ($metric === 'desempeno_tutor') {
        $indicadores = [
            'dominiosdeloscontenidos'   => 'Dominio contenidos',
            'resoluciondedudas'         => 'Resolución de dudas',
            'calidadesdelseguimiento'   => 'Calidad seguimiento',
            'claridaddelosseguimientos' => 'Claridad seguimientos',
            'correccionyrevision'       => 'Corrección y revisión',
            'OBSERVACIONES'             => 'Observaciones (campo)',
        ];
        // Usamos los 5 campos numéricos reales (excluimos OBSERVACIONES que es texto)
        $indicadores_num = [
            'dominiosdeloscontenidos'   => 'Dominio contenidos',
            'resoluciondedudas'         => 'Resolución de dudas',
            'calidadesdelseguimiento'   => 'Calidad seguimiento',
            'claridaddelosseguimientos' => 'Claridad seguimientos',
            'correccionyrevision'       => 'Corrección y revisión',
        ];
        foreach ($indicadores_num as $campo => $label) {
            $media = media_campo_coord($db, $campo, $where);
            $labels[] = $label; $values[] = $media; $total++;
        }

    // ── 5. DESEMPEÑO PERSONA DE APOYO ────────────────────────────────────
    } elseif ($metric === 'desempeno_apoyo') {
        $indicadores = [
            'atencionyacompañamiento'   => 'Atención y acompañamiento',
            'resoluciondeincidencias'   => 'Resolución de incidencias',
            'rapidezyeficacia'          => 'Rapidez y eficacia',
            'seguimientodelalumnado'    => 'Seguimiento alumnado',
            'realizaciondeacciones'     => 'Realización de acciones',
            'correccion'                => 'Corrección pruebas',
        ];
        foreach ($indicadores as $campo => $label) {
            $media = media_campo_coord($db, $campo, $where);
            $labels[] = $label; $values[] = $media; $total++;
        }

    // ── 6. DESEMPEÑO EQUIPO DINAMIZACIÓN ─────────────────────────────────
    } elseif ($metric === 'desempeno_dinamizacion') {
        $indicadores = [
            'realizallamadas'       => 'Realiza llamadas',
            'alumnadoinactivo'      => 'Detecta alumnado inactivo',
            'llevaacaboacciones'    => 'Lleva a cabo acciones reactivación',
            'registracorrectamente' => 'Registra actualizaciones',
            'detectadeformatemprana'=> 'Detecta dificultades',
        ];
        foreach ($indicadores as $campo => $label) {
            $media = media_campo_coord($db, $campo, $where);
            $labels[] = $label; $values[] = $media; $total++;
        }

    // ── 7. INCIDENCIAS DETECTADAS + VALORACIÓN GLOBAL ───────────────────
    } elseif ($metric === 'incidencias_valoracion') {
        // Distribución de la valoración global (1-5)
        $q = $db->query("SELECT fev.value label, COUNT(*) total
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name='valoracionglobal'
            AND fev.value IS NOT NULL AND TRIM(fev.value)!=''
            GROUP BY fev.value ORDER BY CAST(fev.value AS UNSIGNED) ASC");
        while ($r = $q->fetch_assoc()) {
            $lbl = trim(val_c($r['label'])); if ($lbl === '') continue;
            $labels[] = $lbl; $values[] = (int)$r['total']; $total += (int)$r['total'];
        }
        // Añadir % incidencias detectadas como dato extra
        $total_tickets = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket t {$where}")->fetch_assoc()['c'];
        $con_incidencias = (int)$db->query("SELECT COUNT(DISTINCT t.ticket_id) c
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name='sehandetectadoincidencias' AND LOWER(TRIM(fev.value)) NOT IN ('no','false','','0')")->fetch_assoc()['c'];
        $pct = $total_tickets > 0 ? round($con_incidencias / $total_tickets * 100, 1) : 0;
        return ['ok'=>true,'metric'=>$metric,'range'=>$range,'rangeLabel'=>$rangeLabel,
                'labels'=>$labels,'values'=>$values,'total'=>$total,
                'incidencias_pct'=>$pct,'con_incidencias'=>$con_incidencias,'total_tickets'=>$total_tickets];
    }

    return ['ok'=>true,'metric'=>$metric,'range'=>$range,'rangeLabel'=>$rangeLabel,
            'labels'=>$labels,'values'=>$values,'total'=>$total];
}

// ── LECTURA DE FILTROS ────────────────────────────────────────────────────
$rf = function(string $key): array {
    $v = $_GET[$key] ?? $_GET[$key.'[]'] ?? null;
    if ($v === null) return [];
    return array_values(array_filter(is_array($v) ? $v : [$v], fn($x) => trim((string)$x) !== ''));
};

// ── ENDPOINT: ajax_filters ────────────────────────────────────────────────
if (isset($_GET['ajax_filters'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $CAMPOS_COORD;
        $applied = [];
        foreach ($CAMPOS_COORD as $c) { if ($t = $rf('filter_'.$c)) $applied[$c] = $t; }
        echo json_encode(['ok'=>true,'filters'=>get_filters_coord($db, $applied, $CAMPOS_COORD)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
    exit;
}

// ── ENDPOINT: ajax ────────────────────────────────────────────────────────
if (isset($_GET['ajax'], $_GET['metric'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        global $CAMPOS_COORD;
        $metric  = trim((string)($_GET['metric'] ?? 'evolucion'));
        $range   = trim((string)($_GET['range']  ?? 'year'));
        $filters = [];
        foreach ($CAMPOS_COORD as $c) { if ($t = $rf('filter_'.$c)) $filters[$c] = $t; }
        echo json_encode(metric_query_coord($db, $metric, $range, $filters), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>'Error cargando datos'], JSON_UNESCAPED_UNICODE); }
    $db->close(); exit;
}

// ── KPIs ──────────────────────────────────────────────────────────────────
try {
    $tid = COORD_TOPIC_ID; $fid = COORD_FORM_ID;
    $kpi_total  = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket WHERE topic_id={$tid}")->fetch_assoc()['c'];
    $kpi_hoy    = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket WHERE topic_id={$tid} AND DATE(created)=CURDATE()")->fetch_assoc()['c'];
    $kpi_semana = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket WHERE topic_id={$tid} AND created>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'];
    $kpi_val    = $db->query("SELECT ROUND(AVG(CAST(fev.value AS DECIMAL(10,2))),1) m
        FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='valoracionglobal' AND t.topic_id={$tid} AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['m'] ?? '—';
    // Total alumnos matriculados
    $kpi_alumnos = (int)$db->query("SELECT SUM(CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED)) s
        FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='ndealumnos' AND t.topic_id={$tid} AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['s'] ?? 0;
    $kpi_finalizados = (int)$db->query("SELECT SUM(CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED)) s
        FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='nfinalizados' AND t.topic_id={$tid} AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['s'] ?? 0;
} catch (Throwable $e) {
    $kpi_total = $kpi_hoy = $kpi_semana = $kpi_alumnos = $kpi_finalizados = 0; $kpi_val = '—';
}

$default_ranges = [
    'evolucion'              => 'year',
    'planes'                 => 'year',
    'alumnos'                => 'year',
    'desempeno_tutor'        => 'year',
    'desempeno_apoyo'        => 'year',
    'desempeno_dinamizacion' => 'year',
    'incidencias_valoracion' => 'year',
];

$graficas = [
    ['evolucion',              'Evolución de registros',          'fa-chart-line',  'line'],
    ['planes',                 'Distribución por plan',           'fa-layer-group', 'bar'],
    ['alumnos',                'Alumnos matriculados vs finalizados', 'fa-users',   'bar_multi'],
    ['desempeno_tutor',        'Desempeño del/la tutor/a',        'fa-chalkboard-user', 'radar'],
    ['desempeno_apoyo',        'Desempeño persona de apoyo',      'fa-hands-helping',   'radar'],
    ['desempeno_dinamizacion', 'Desempeño equipo dinamización',   'fa-bolt',            'radar'],
    ['incidencias_valoracion', 'Valoración global (1-5)',         'fa-star-half-stroke','bar'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Estadísticas Coordinadores — Grupo ATU</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>
:root{
    --primary:#6366f1;--primary-dark:#4f46e5;--primary-bg:#eef2ff;
    --bg:#f4f7fb;--bg-card:#fff;--border:#e2e8f0;
    --text:#0f172a;--text-secondary:#475569;--text-muted:#94a3b8;
    --radius:10px;--sidebar-width:240px;--sidebar-collapsed:77px;
    --header-height:72px;--orange:#ef4444;--orange-bg:#fee2e2;
    --transition:all .3s cubic-bezier(.4,0,.2,1);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text)}

/* SIDEBAR */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-width);height:100vh;background:#0f172a;z-index:1000;display:flex;flex-direction:column;transition:var(--transition);overflow:hidden}
.sidebar.collapsed{width:var(--sidebar-collapsed)}
.sidebar-header{padding:22px 18px;display:flex;justify-content:center;border-bottom:1px solid rgba(255,255,255,.06)}
.sidebar-brand{display:flex;align-items:center;justify-content:center;width:100%;color:#fff;font-size:20px;font-weight:800;white-space:nowrap;overflow:hidden}
.sidebar-brand a{color:#fff;text-decoration:none;font-weight:800}
.short{display:none}.sidebar.collapsed .full{display:none}.sidebar.collapsed .short{display:inline}
.sidebar-menu{flex:1;padding:20px 10px;overflow:hidden}
.menu-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:14px;font-weight:500;cursor:pointer;transition:var(--transition);margin-bottom:4px;white-space:nowrap;overflow:hidden}
.menu-item i{width:20px;text-align:center;font-size:15px;flex-shrink:0}
.menu-item:hover{background:#1e293b;color:#fff}
.menu-item.active{background:#6366f1;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.4)}
.sidebar.collapsed .menu-item span{display:none}.sidebar.collapsed .menu-item{justify-content:center;gap:0}
.sidebar-collapse-btn{width:100%;padding:14px;background:none;border:none;border-top:1px solid rgba(255,255,255,.06);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:var(--transition)}
.sidebar-collapse-btn:hover{background:#1e293b;color:#fff}
hr.linea-menu{border:none;border-top:1px solid rgba(255,255,255,.08);margin:8px 14px}

/* MAIN */
.main{margin-left:var(--sidebar-width);transition:var(--transition);min-height:100vh}
.sidebar.collapsed~.main{margin-left:var(--sidebar-collapsed)}

/* HEADER */
.header{height:var(--header-height);background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:400;transition:transform .3s,opacity .3s;transform:translateY(0);opacity:1}
.header.hide{transform:translateY(-100%);opacity:0;pointer-events:none}
.header h1{margin:0;font-size:26px;font-weight:700}
.header-date{font-size:13px;color:var(--text-secondary);padding:6px 14px;background:var(--bg);border-radius:6px;font-weight:500;display:flex;align-items:center;gap:8px}
.header-date i{color:var(--primary)}

/* CONTAINER */
.container{padding:24px 28px}

/* KPI CARDS */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px}
.card{background:#fff;border-radius:14px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.05);border-left:4px solid var(--primary)}
.card.green{border-left-color:#10b981}.card.amber{border-left-color:#f59e0b}.card.rose{border-left-color:#ef4444}.card.purple{border-left-color:#8b5cf6}
.card .label{font-size:11px;color:#6b7280;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.card .value{font-size:28px;font-weight:800;color:var(--text)}
.card .sub{font-size:11px;color:var(--text-muted);margin-top:4px}

/* GRID */
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:22px}

/* CHART BOX */
.chart-box{background:#fff;border-radius:14px;border:1px solid var(--border);box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:visible;position:relative}
.chart-box-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.chart-title{font-size:14px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.chart-title i{color:var(--primary)}
.metric-total{display:inline-flex;align-items:center;gap:8px;background:var(--primary-bg);color:var(--primary);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;white-space:nowrap}

/* RANGOS */
.range-group{display:flex;gap:6px;flex-wrap:wrap;padding:10px 20px 0;position:relative;z-index:8}
.range-btn{border:1px solid var(--border);background:#fff;color:var(--text-secondary);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:600;cursor:pointer;transition:var(--transition)}
.range-btn:hover{border-color:var(--primary);color:var(--primary)}
.range-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.25)}

/* FILTROS */
.filters-bar{display:flex;flex-wrap:wrap;gap:10px;padding:12px 20px;background:#f8fafc;border-bottom:1px solid var(--border);align-items:flex-end;position:relative;z-index:20}
.filter-pill{display:flex;flex-direction:column;gap:3px;flex:1;min-width:120px;max-width:200px}
.filter-pill label{font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px}
.filter-actions{display:flex;align-items:flex-end;gap:6px;flex-shrink:0}
.btn-limpiar{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--text-secondary);font-size:11px;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap;font-family:'Inter',sans-serif}
.btn-limpiar:hover{background:#fee2e2;border-color:#fca5a5;color:#dc2626}

/* CANVAS */
.chart-canvas-wrap{padding:18px 20px 20px}
canvas{width:100%!important;height:285px!important}

/* BADGE INCIDENCIAS */
.incid-badge{display:flex;gap:16px;padding:12px 20px;background:#fff7ed;border-top:1px solid #fed7aa;font-size:13px;color:#92400e;align-items:center;flex-wrap:wrap}
.incid-badge strong{color:#c2410c;font-size:18px}

/* TOM SELECT */
.ts-wrapper{font-size:12px;position:relative;z-index:15}
.ts-control{min-height:32px!important;border:1.5px solid var(--border)!important;border-radius:6px!important;padding:4px 8px!important;box-shadow:none!important;background:#fff!important;font-size:12px!important}
.ts-control input{font-size:12px!important}
.ts-dropdown{border:1px solid var(--border)!important;border-radius:8px!important;z-index:99999!important;background:#fff!important;font-size:12px!important}
.ts-dropdown .option{font-size:12px;padding:8px 10px}
.ts-dropdown .active{background:var(--primary-bg)!important;color:var(--primary-dark)!important}
.ts-wrapper .item{background:var(--primary-bg)!important;color:var(--primary)!important;border-radius:4px!important;font-size:11px!important;padding:1px 5px!important}
.ts-wrapper .remove{color:var(--primary)!important}
.ts-wrapper.focus .ts-control{border-color:var(--primary)!important;box-shadow:0 0 0 3px rgba(99,102,241,.12)!important}

/* LOADING */
.chart-loading{display:none;position:absolute;inset:0;background:rgba(255,255,255,.8);border-radius:14px;align-items:center;justify-content:center;z-index:10}
.chart-loading.active{display:flex}
.spinner{width:32px;height:32px;border:3px solid #e0e7ff;border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* TOAST */
.toast{position:fixed;top:20px;right:20px;background:#0f172a;color:#fff;padding:14px 22px;border-radius:var(--radius);font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);transform:translateY(-120px);opacity:0;transition:all .35s;z-index:99999}
.toast.show{transform:translateY(0);opacity:1}
.toast.success i{color:#10b981}.toast.info i{color:#06b6d4}

/* BOTÓN EXPORTAR */
.btn-download{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;margin-right:-50px;border:1px solid var(--primary);background:var(--primary-bg);color:var(--primary-dark);cursor:pointer;transition:.2s;font-family:'Inter',sans-serif}
.btn-download:hover{background:var(--primary);color:#fff;box-shadow:0 6px 18px rgba(99,102,241,.35)}
.btn-export{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:.25s;font-family:'Inter',sans-serif}
.btn-export.primary{background:var(--primary);color:#fff}
.btn-export.primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(99,102,241,.35)}
.btn-export.success{background:#10b981;color:#fff}
.btn-export.success:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(16,185,129,.35)}
.btn-export.neutral{background:#f1f5f9;color:#475569}
.btn-export.neutral:hover{background:#fee2e2;color:#dc2626}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99998;align-items:center;justify-content:center}
.modal-box{background:#fff;width:100%;max-width:520px;border-radius:14px;padding:24px}
.modal-box h3{font-size:16px;font-weight:700;margin-bottom:14px}
.modal-box label{font-size:13px;display:flex;gap:8px;align-items:center;padding:6px 0;cursor:pointer}
.modal-box input[type=checkbox]{accent-color:var(--primary);width:16px;height:16px}
.modal-info{font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.5}
.modal-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:20px}

@media(max-width:900px){
    .sidebar{position:relative;width:100%;height:auto}.main{width:100%;margin-left:0}
    .grid{grid-template-columns:1fr}canvas{height:240px!important}.filter-pill{min-width:100px}
}
</style>
</head>
<body>

<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

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
        <a href="estadisticas.php" class="menu-item"><i class="fas fa-chart-bar"></i><span>Estadísticas Incidencias</span></a>
        <hr class="linea-menu">
        <a href="valoraciones.php" class="menu-item"><i class="fa-solid fa-clipboard-list"></i><span>Tabla de Valoraciones</span></a>
        <a href="estadisticas_valoraciones.php" class="menu-item"><i class="fas fa-chart-pie"></i><span>Estadísticas Valoraciones</span></a>
        <hr class="linea-menu">
        <a href="coordinadores.php" class="menu-item"><i class="fa-solid fa-user-tie"></i><span>Tabla de Coordinadores</span></a>
        <a href="estadisticas_coordinadores.php" class="menu-item active"><i class="fas fa-chart-column"></i><span>Estadísticas Coordinadores</span></a>
    </nav>
    <button class="sidebar-collapse-btn" onclick="toggleSidebar()"><i class="fa-solid fa-chevron-left"></i></button>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="header" id="mainHeader">
        <h1>Estadísticas Coordinadores</h1>
        <button class="btn-download" onclick="openExportModal()"><i class="fas fa-download"></i> Descargar estadísticas</button>
        <div style="display:flex;align-items:center;gap:20px;">
            <div class="header-date"><i class="far fa-calendar-alt"></i><span id="liveDateText"><?= date('d/m/Y H:i:s') ?></span></div>
            <div style="display:flex;align-items:center;gap:12px;border-left:1px solid var(--border);padding-left:20px;">
                <span style="font-size:13px;color:var(--text-secondary);font-weight:600;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-user-circle" style="color:var(--primary);font-size:16px;"></i>
                    <?= h_c($_SESSION['coord_stats_user'] ?? '') ?>
                </span>
                <a href="?logout=1" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#fee2e2;color:#ef4444;text-decoration:none;transition:.2s;"
                   onmouseover="this.style.background='#ef4444';this.style.color='#fff';"
                   onmouseout="this.style.background='#fee2e2';this.style.color='#ef4444';"
                   title="Cerrar Sesión"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- KPI CARDS -->
        <div class="cards">
            <div class="card">
                <div class="label">Total registros</div>
                <div class="value"><?= number_format($kpi_total) ?></div>
            </div>
            <div class="card green">
                <div class="label">Alumnos matriculados</div>
                <div class="value"><?= number_format($kpi_alumnos) ?></div>
            </div>
            <div class="card amber">
                <div class="label">Alumnos finalizados</div>
                <div class="value"><?= number_format($kpi_finalizados) ?></div>
                <?php if ($kpi_alumnos > 0): ?>
                <div class="sub"><?= round($kpi_finalizados / $kpi_alumnos * 100, 1) ?>% tasa finalización</div>
                <?php endif; ?>
            </div>
            <div class="card purple">
                <div class="label">Valoración media global</div>
                <div class="value"><?= h_c($kpi_val) ?>/5</div>
            </div>
            <div class="card">
                <div class="label">Registros hoy</div>
                <div class="value"><?= number_format($kpi_hoy) ?></div>
            </div>
            <div class="card">
                <div class="label">Últimos 7 días</div>
                <div class="value"><?= number_format($kpi_semana) ?></div>
            </div>
        </div>

        <!-- GRÁFICAS -->
        <div class="grid">
        <?php foreach ($graficas as [$gid, $gtitle, $gicon, $gtype]): ?>
            <div class="chart-box" id="box-<?= $gid ?>">
                <div class="chart-loading" id="loading-<?= $gid ?>"><div class="spinner"></div></div>
                <div class="chart-box-header">
                    <div class="chart-title"><i class="fas <?= $gicon ?>"></i> <?= $gtitle ?></div>
                    <div class="metric-total"><span id="total-<?= $gid ?>">—</span> registros</div>
                </div>
                <!-- Filtros -->
                <div class="filters-bar">
                    <?php foreach ($CAMPOS_COORD as $campo): ?>
                    <div class="filter-pill">
                        <label><?= h_c($LABELS_COORD[$campo] ?? ucfirst($campo)) ?></label>
                        <select class="chart-filter" data-chart="<?= $gid ?>" data-field="<?= h_c($campo) ?>" id="sel-<?= $gid ?>-<?= $campo ?>" multiple></select>
                    </div>
                    <?php endforeach; ?>
                    <div class="filter-actions">
                        <button class="btn-limpiar" onclick="limpiarFiltros('<?= $gid ?>')"><i class="fas fa-times"></i> Limpiar</button>
                    </div>
                </div>
                <?= render_rango_coord($gid) ?>
                <!-- Badge incidencias (solo para incidencias_valoracion) -->
                <?php if ($gid === 'incidencias_valoracion'): ?>
                <div class="incid-badge" id="incid-badge" style="display:none">
                    <span>Registros con incidencias detectadas:</span>
                    <strong id="incid-count">—</strong>
                    <span>(<span id="incid-pct">—</span>% del total)</span>
                </div>
                <?php endif; ?>
                <div class="chart-canvas-wrap"><canvas id="<?= $gid ?>Chart"></canvas></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL EXPORTAR -->
<div class="modal-overlay" id="exportModal">
    <div class="modal-box">
        <h3>Descargar estadísticas</h3>
        <p class="modal-info">Selecciona las estadísticas que deseas descargar en formato imagen (PNG) o datos (CSV).</p>
        <div style="display:grid;gap:4px;margin-bottom:8px">
            <?php foreach ($graficas as [$gid, $gtitle]): ?>
            <label><input type="checkbox" value="<?= $gid ?>" checked> <?= h_c($gtitle) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="modal-actions">
            <button onclick="exportCharts('png')"   class="btn-export primary"><i class="fas fa-image"></i> PNG</button>
            <button onclick="exportCharts('excel')" class="btn-export success"><i class="fas fa-file-excel"></i> CSV</button>
            <button onclick="closeExportModal()"    class="btn-export neutral">Cancelar</button>
        </div>
    </div>
</div>

<script>
/* RELOJ */
(function(){ var el=document.getElementById('liveDateText');
    function tick(){ var n=new Date(),p=function(x){return String(x).padStart(2,'0');};
        el.textContent=p(n.getDate())+'/'+p(n.getMonth()+1)+'/'+n.getFullYear()+' '+p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds()); }
    tick(); setInterval(tick,1000); })();

function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); }

function showToast(msg,type){ var t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    t.querySelector('i').className=(type==='success'?'fas fa-check-circle':'fas fa-info-circle');
    t.className='toast '+type+' show'; setTimeout(function(){t.classList.remove('show');},3000); }

var PALETTE=['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6','#f97316','#84cc16'];
function pal(n){ return Array.from({length:n},function(_,i){return PALETTE[i%PALETTE.length];}); }

var defaultRanges = <?= json_encode($default_ranges, JSON_UNESCAPED_UNICODE) ?>;
var chartRanges   = Object.assign({}, defaultRanges);
var charts        = {};
var currentFilters = {};
<?php foreach ($graficas as [$gid]): ?>currentFilters['<?= $gid ?>']={};<?php endforeach; ?>
var availableFilters={}, tomInstances={};

function syncButtons(metric,range){
    document.querySelectorAll('.range-btn[data-metric="'+metric+'"]').forEach(function(b){ b.classList.toggle('active',b.dataset.range===range); }); }
function setRange(metric,range){ chartRanges[metric]=range; syncButtons(metric,range); loadMetric(metric); }

function buildUrl(metric){
    var range=chartRanges[metric]||defaultRanges[metric]||'historico';
    var parts=['ajax=1','metric='+encodeURIComponent(metric),'range='+encodeURIComponent(range)];
    var f=currentFilters[metric]||{};
    Object.keys(f).forEach(function(c){ (Array.isArray(f[c])?f[c]:[f[c]]).forEach(function(v){
        if(v!==null&&v!==undefined&&String(v).trim()!=='') parts.push('filter_'+c+'%5B%5D='+encodeURIComponent(v)); }); });
    return 'estadisticas_coordinadores.php?'+parts.join('&'); }

/* TIPOS DE GRÁFICA */
var CHART_TYPES={evolucion:'line',planes:'bar',alumnos:'bar',
    desempeno_tutor:'radar',desempeno_apoyo:'radar',desempeno_dinamizacion:'radar',
    incidencias_valoracion:'bar'};

function createChart(metric,d){
    var ctx=document.getElementById(metric+'Chart'); if(!ctx)return;
    var type=CHART_TYPES[metric]||'bar';
    var isMulti=['doughnut','pie'].includes(type);
    var isRadar=type==='radar';

    var datasets;
    if(metric==='alumnos'&&d.values2){
        datasets=[
            {label:d.label1||'Matriculados',data:d.values,backgroundColor:PALETTE[0]+'55',borderColor:PALETTE[0],borderWidth:2},
            {label:d.label2||'Finalizados', data:d.values2,backgroundColor:PALETTE[4]+'55',borderColor:PALETTE[4],borderWidth:2}
        ];
    } else if(isRadar){
        datasets=[{label:'Media (1-4)',data:d.values,
            backgroundColor:'rgba(99,102,241,.15)',borderColor:PALETTE[0],borderWidth:2,
            pointBackgroundColor:PALETTE[0],pointRadius:4}];
    } else {
        datasets=[{label:'Registros',data:d.values,
            backgroundColor:isMulti?pal(d.labels.length):PALETTE[0]+'33',
            borderColor:isMulti?pal(d.labels.length):PALETTE[0],
            borderWidth:2,tension:.35,fill:type==='line',
            pointBackgroundColor:PALETTE[0],pointRadius:type==='line'?3:0}];
    }

    var cfg={type:type,data:{labels:d.labels,datasets:datasets},options:{
        responsive:true,maintainAspectRatio:false,
        plugins:{
            legend:{position:isMulti?'right':(metric==='alumnos'||isRadar?'top':'top'),
                labels:{font:{size:11},boxWidth:12,padding:12}},
            tooltip:{callbacks:{label:function(ctx){return ' '+ctx.formattedValue;}}}
        },
        scales:isMulti||isRadar?{}:{
            x:{grid:{color:'#f1f5f9'},ticks:{font:{size:11}}},
            y:{grid:{color:'#f1f5f9'},ticks:{font:{size:11}},beginAtZero:true}
        }
    }};
    if(isRadar){ cfg.options.scales={r:{min:0,max:4,ticks:{stepSize:1,font:{size:10}},pointLabels:{font:{size:11}}}}; }

    if(charts[metric]) charts[metric].destroy();
    charts[metric]=new Chart(ctx,cfg);

    // Badge incidencias
    if(metric==='incidencias_valoracion'){
        var badge=document.getElementById('incid-badge');
        if(badge&&d.incidencias_pct!==undefined){
            document.getElementById('incid-count').textContent=d.con_incidencias||0;
            document.getElementById('incid-pct').textContent=d.incidencias_pct||0;
            badge.style.display='flex';
        }
    }
}

function loadMetric(metric){
    var loading=document.getElementById('loading-'+metric);
    if(loading) loading.classList.add('active');
    fetch(buildUrl(metric))
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.ok) throw new Error(d.msg||'Error');
            var te=document.getElementById('total-'+metric);
            if(te) te.textContent=d.total||0;
            syncButtons(metric,d.range);
            createChart(metric,d);
        })
        .catch(function(e){console.error('loadMetric['+metric+']',e);showToast('Error cargando '+metric,'info');})
        .finally(function(){if(loading) loading.classList.remove('active');});
}

function loadAllMetrics(){
    <?php foreach ($graficas as [$gid]): ?>loadMetric('<?= $gid ?>');<?php endforeach; ?>
}

function limpiarFiltros(metric){
    currentFilters[metric]={};
    document.querySelectorAll('[data-chart="'+metric+'"]').forEach(function(el){ if(el.tomselect) el.tomselect.clear(); });
    loadMetric(metric); showToast('Filtros limpiados','success');
}

var CASCADE_COORD=<?= json_encode($CAMPOS_COORD, JSON_UNESCAPED_UNICODE) ?>;
function updateDownstreamFilters(metric,changedField){
    var idx=CASCADE_COORD.indexOf(changedField);
    if(idx===-1||idx>=CASCADE_COORD.length-1) return;
    var active={};
    for(var i=0;i<=idx;i++){ var f=CASCADE_COORD[i];
        if(currentFilters[metric]&&currentFilters[metric][f]&&currentFilters[metric][f].length) active[f]=currentFilters[metric][f]; }
    var parts=['ajax_filters=1'];
    Object.keys(active).forEach(function(k){ active[k].forEach(function(v){ parts.push('filter_'+k+'%5B%5D='+encodeURIComponent(v)); }); });
    fetch('estadisticas_coordinadores.php?'+parts.join('&'))
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.ok||!data.filters) return;
            for(var j=idx+1;j<CASCADE_COORD.length;j++){
                var dep=CASCADE_COORD[j],newOpts=data.filters[dep]||[],key=metric+'_'+dep,ts=tomInstances[key];
                if(!ts) continue;
                var cur=ts.getValue(); ts.clear(true); ts.clearOptions();
                ts.addOptions(newOpts.map(function(o){return{value:o,text:o};}));
                var valid=(Array.isArray(cur)?cur:[cur]).filter(function(v){return newOpts.indexOf(v)!==-1;});
                if(valid.length){ts.setValue(valid,true);currentFilters[metric][dep]=valid;}
                else delete currentFilters[metric][dep];
                ts.refreshOptions(false);
            }
        }).catch(function(e){console.error('cascade error:',e);});
}

function populateSelectOptions(){
    document.querySelectorAll('.chart-filter').forEach(function(sel){
        var campo=sel.dataset.field;
        var opts=(availableFilters&&availableFilters[campo])?availableFilters[campo]:[];
        sel.innerHTML=opts.map(function(v){return'<option value="'+v.replace(/"/g,'&quot;')+'">'+v+'</option>';}).join('');
    });
}

function initializeTomSelects(){
    document.querySelectorAll('.chart-filter').forEach(function(el){
        var metric=el.dataset.chart,campo=el.dataset.field,key=metric+'_'+campo;
        if(tomInstances[key]) tomInstances[key].destroy();
        var ts=new TomSelect(el,{plugins:['remove_button'],create:false,hideSelected:true,
            closeAfterSelect:false,placeholder:'Todos...',maxItems:null,dropdownParent:'body',
            onChange:function(vals){
                vals=Array.isArray(vals)?vals:(vals?[vals]:[]);
                if(!currentFilters[metric]) currentFilters[metric]={};
                if(vals.length>0) currentFilters[metric][campo]=vals;
                else delete currentFilters[metric][campo];
                loadMetric(metric); updateDownstreamFilters(metric,campo);
            }});
        tomInstances[key]=ts;
    });
}

async function initializeApp(){
    Object.keys(defaultRanges).forEach(function(m){syncButtons(m,chartRanges[m]||defaultRanges[m]);});
    try{
        var resp=await fetch('estadisticas_coordinadores.php?ajax_filters=1');
        var data=await resp.json();
        if(!data.ok) throw new Error(data.msg||'Error');
        availableFilters=data.filters; populateSelectOptions(); initializeTomSelects();
    }catch(e){ console.error('Error cargando filtros:',e); populateSelectOptions(); initializeTomSelects(); }
    loadAllMetrics();
}
document.addEventListener('DOMContentLoaded',initializeApp);
setInterval(loadAllMetrics,60000);
document.addEventListener('click',function(e){ if(e.target.classList.contains('range-btn')) setRange(e.target.dataset.metric,e.target.dataset.range); });

function openExportModal(){ document.getElementById('exportModal').style.display='flex'; }
function closeExportModal(){ document.getElementById('exportModal').style.display='none'; }
document.getElementById('exportModal').addEventListener('click',function(e){if(e.target===this)closeExportModal();});

function exportCharts(type){
    var metrics=Array.from(document.querySelectorAll('#exportModal input[type=checkbox]:checked')).map(function(cb){return cb.value;});
    if(type==='png'){
        metrics.forEach(function(metric){
            var canvas=document.getElementById(metric+'Chart'); if(!canvas) return;
            var nc=document.createElement('canvas'); nc.width=canvas.width; nc.height=canvas.height+80;
            var nctx=nc.getContext('2d'); nctx.fillStyle='#fff'; nctx.fillRect(0,0,nc.width,nc.height);
            nctx.fillStyle='#000'; nctx.font='bold 16px Inter,sans-serif';
            nctx.fillText('Estadísticas Coordinadores: '+metric,20,25);
            nctx.font='12px Inter,sans-serif';
            var ftxt=Object.keys(currentFilters[metric]||{}).map(function(k){return k+': '+(currentFilters[metric][k]||[]).join(', ');}).join(' | ');
            nctx.fillText('Filtros: '+(ftxt||'Ninguno'),20,50);
            nctx.drawImage(canvas,0,80);
            var a=document.createElement('a'); a.download='coordinadores_'+metric+'.png'; a.href=nc.toDataURL('image/png'); a.click();
        });
    } else if(type==='excel'){
        metrics.forEach(function(metric){
            fetch(buildUrl(metric)).then(function(r){return r.json();}).then(function(d){
                var rows=[['Etiqueta','Total']];
                (d.labels||[]).forEach(function(label,i){rows.push(['"'+label+'"',d.values[i]||0]);});
                var csv=rows.map(function(r){return r.join(',');}).join('\n');
                var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
                var a=document.createElement('a'); a.href=URL.createObjectURL(blob);
                a.download='coordinadores_'+metric+'.csv'; a.click();
            });
        });
    }
    closeExportModal();
}

(function(){ var lastST=0,hdr=document.getElementById('mainHeader');
    window.addEventListener('scroll',function(){
        var st=window.pageYOffset||document.documentElement.scrollTop;
        if(st>lastST&&st>80){if(hdr)hdr.classList.add('hide');}else{if(hdr)hdr.classList.remove('hide');}
        lastST=st<=0?0:st; }); })();
</script>
</body>
</html>