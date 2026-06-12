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

// Sesión válida desde coordinadores.php también da acceso
$is_logged_in = !empty($_SESSION['coord_stats_auth'])
             || !empty($_SESSION['admin_sso'])
             || !empty($_SESSION['incidencias_auth']);

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
        body{background:linear-gradient(135deg,#2d3a9e 0%,#3b4fd8 50%,#4f63e7 100%);font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        .lc{background:#fff;padding:48px 50px 44px;border-radius:20px;box-shadow:0 24px 64px rgba(0,0,0,.18);width:100%;max-width:440px}
        .lc-header{text-align:center;margin-bottom:32px}
        .lc-header h1{font-size:26px;font-weight:700;color:#1a1a2e;line-height:1.3;margin-bottom:8px}
        .lc-header p{color:#8a8fa8;font-size:14px}
        .fg{margin-bottom:20px}
        .fg label{display:block;font-size:13px;font-weight:600;color:#3a3f5c;margin-bottom:7px}
        .fg input{width:100%;height:52px;border:2px solid #e8eaf6;border-radius:12px;padding:0 18px;font-size:15px;font-family:'Inter',sans-serif;background:#f0f2ff;color:#1a1a2e;transition:border-color .25s,box-shadow .25s;box-sizing:border-box}
        .fg input:focus{outline:none;border-color:#5c6bc0;box-shadow:0 0 0 3px rgba(92,107,192,.18);background:#fff}
        .btn-l{width:100%;height:52px;background:#6c63ff;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;transition:background .25s,transform .1s;margin-top:6px;letter-spacing:.3px}
        .btn-l:hover{background:#574fd6}
        .btn-l:active{transform:scale(.98)}
        .err{background:#fff0f0;color:#b91c1c;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:13px;border-left:4px solid #f87171}
    </style></head><body><div class="lc">
        <div class="lc-header">
            <h1>🔐 Acceso Coordinadores</h1>
            <p>Introduce tus credenciales</p>
        </div>
        <?php if ($login_error): ?><div class="err">❌ <?= htmlspecialchars($login_error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="fg"><label>Usuario</label><input type="text" name="login_username" required autofocus></div>
            <div class="fg"><label>Contraseña</label><input type="password" name="login_password" required></div>
            <button type="submit" class="btn-l">Iniciar Sesión</button>
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

define('COORD_FORM_ID', 22);

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
        case 'custom':
            $df = $GLOBALS['export_date_from'] ?? null;
            $dt = $GLOBALS['export_date_to']   ?? null;
            $lbl = ($df ? substr($df,0,10) : '—') . ' → ' . ($dt ? substr($dt,0,10) : '—');
            return [$df, $dt, $lbl];
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
$CAMPOS_COORD  = ['planes','sectores','acciones','grupos','nombrescursos'];
$LABELS_COORD  = ['planes'=>'Plan','sectores'=>'Sector','acciones'=>'Acción','grupos'=>'Grupo','nombrescursos'=>'Curso'];

// ── WHERE ─────────────────────────────────────────────────────────────────
function where_coord(mysqli $db, array $filters): string {
    $fid = COORD_FORM_ID;
    $w = " WHERE EXISTS(SELECT 1 FROM ost_form_entry fe_chk WHERE fe_chk.object_id=t.ticket_id AND fe_chk.form_id={$fid}) ";
    foreach ($filters as $campo => $vals) {
        $vals = array_values(array_filter(array_map('trim', (array)$vals)));
        if (empty($vals)) continue;
        $ors = [];
        foreach ($vals as $v) {
            $ve  = $db->real_escape_string($v);
            // Convertir caracteres no-ASCII a \uXXXX literal (como hace osTicket al guardar JSON)
            $v_escaped = preg_replace_callback('/[^\x00-\x7F]/u', function($m) {
                $cp = mb_ord($m[0], 'UTF-8');
                return sprintf('\u%04x', $cp);
            }, $v);
            $veu = $db->real_escape_string($v_escaped);
            $ce  = $db->real_escape_string($campo);
            $ors[] = "EXISTS(SELECT 1 FROM ost_form_entry fe2
                JOIN ost_form_entry_values fev2 ON fev2.entry_id=fe2.id
                JOIN ost_form_field ff2 ON ff2.id=fev2.field_id
                WHERE fe2.object_id=t.ticket_id AND fe2.form_id={$fid}
                AND ff2.name='{$ce}' AND (fev2.value LIKE '%{$ve}%' OR fev2.value LIKE '%{$veu}%'))";
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
        $fid = COORD_FORM_ID;
        $q = $db->query("SELECT DISTINCT fev.value v
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
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
    $ce  = $db->real_escape_string($campo);
    $fid = COORD_FORM_ID;
    // Los valores pueden venir como JSON {"id":"valor"} — extraemos el valor numérico
    $q = $db->query("SELECT ROUND(AVG(v),2) AS m FROM (
        SELECT CAST(NULLIF(TRIM(
            CASE
                WHEN fev.value LIKE '{%' THEN
                    REGEXP_REPLACE(REGEXP_REPLACE(fev.value, '^\\{\"[^\"]+\":', ''), '\\}$', '')
                ELSE fev.value
            END
        ),'') AS DECIMAL(10,2)) AS v
        FROM ost_ticket t
        JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        {$where} AND ff.name='{$ce}' AND fev.value IS NOT NULL AND TRIM(fev.value)!=''
    ) sub WHERE v IS NOT NULL AND v REGEXP '^[0-9]'");
    return $q ? (float)($q->fetch_assoc()['m'] ?? 0) : 0;
}

// ── MÉTRICAS ──────────────────────────────────────────────────────────────
function metric_query_coord(mysqli $db, string $metric, string $range, array $filters): array {
    $validas = ['evolucion','planes','alumnos','incidencias_valoracion'];
    if (!in_array($metric, $validas, true)) $metric = 'evolucion';

    [$from, $to, $rangeLabel] = rango_coord($range);
    $where  = where_coord($db, $filters);
    $where  = where_rango_coord($where, $db, $from, $to);
    $labels = []; $values = []; $total = 0;
    $fid    = COORD_FORM_ID;

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
        // Agrupa por PLAN para visión global; al filtrar por plan se verá por acción
        $group_field = (count($filters['planes'] ?? []) === 1) ? 'acciones' : 'planes';
        $q = $db->query("
            SELECT
                MAX(CASE WHEN ff.name='{$group_field}' THEN fev.value END) AS grupo_label,
                SUM(CASE WHEN ff.name='ndealumnos'   THEN CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED) ELSE 0 END) AS matriculados,
                SUM(CASE WHEN ff.name='nfinalizados' THEN CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED) ELSE 0 END) AS finalizados
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name IN ('{$group_field}','ndealumnos','nfinalizados')
            GROUP BY t.ticket_id");
        $temp = [];
        while ($r = $q->fetch_assoc()) {
            $a = trim(val_c($r['grupo_label'] ?? '')); if ($a === '') $a = 'Sin dato';
            if (!isset($temp[$a])) $temp[$a] = [0, 0];
            $temp[$a][0] += (int)$r['matriculados'];
            $temp[$a][1] += (int)$r['finalizados'];
        }
        uasort($temp, function($a,$b){ return $b[0] <=> $a[0]; });
        $temp = array_slice($temp, 0, 12, true);
        $values2 = [];
        foreach ($temp as $a => $d) { $labels[] = $a; $values[] = $d[0]; $values2[] = $d[1]; $total += $d[0]; }
        $axis_label = empty($filters['planes']) ? 'Plan' : 'Acción';
        return ['ok'=>true,'metric'=>$metric,'range'=>$range,'rangeLabel'=>$rangeLabel,
                'labels'=>$labels,'values'=>$values,'values2'=>$values2,'total'=>$total,
                'label1'=>'Matriculados','label2'=>'Finalizados','axisLabel'=>$axis_label];

    // ── 4. DESEMPEÑO TUTOR/A ─────────────────────────────────────────────
    } elseif ($metric === 'desempeno_tutor') {
        $indicadores = [
            'dominiosdeloscontenidos'   => 'Dominio contenidos',
            'resoluciondedudas'         => 'Resolución de dudas',
            'calidadesdelseguimiento'   => 'Calidad seguimiento',
            'claridaddelosseguimientos' => 'Claridad seguimientos',
            'correccionyrevision'       => 'Corrección y revisión',
        ];
        $where_d = $where;
        foreach ($indicadores as $campo => $label) {
            $media = media_campo_coord($db, $campo, $where_d);
            $labels[] = $label; $values[] = round($media, 2); $total++;
        }

    // ── 5. DESEMPEÑO PERSONA DE APOYO ────────────────────────────────────
    } elseif ($metric === 'desempeno_apoyo') {
        $indicadores = [
            'atencionyacompañamiento' => 'Atención y acompañamiento',
            'resoluciondeincidencias' => 'Resolución de incidencias',
            'rapidezyeficacia'        => 'Rapidez y eficacia',
            'seguimientodelalumnado'  => 'Seguimiento alumnado',
            'realizaciondeacciones'   => 'Realización de acciones',
            'correccion'              => 'Corrección pruebas',
        ];
        $where_d = $where;
        foreach ($indicadores as $campo => $label) {
            $media = media_campo_coord($db, $campo, $where_d);
            $labels[] = $label; $values[] = round($media, 2); $total++;
        }

    // ── 6. DESEMPEÑO EQUIPO DINAMIZACIÓN ─────────────────────────────────
    } elseif ($metric === 'desempeno_dinamizacion') {
        $indicadores = [
            'realizallamadas'        => 'Realiza llamadas',
            'alumnadoinactivo'       => 'Detecta alumnado inactivo',
            'llevaacaboacciones'     => 'Acciones de reactivación',
            'registracorrectamente'  => 'Registra actualizaciones',
            'detectadeformatemprana' => 'Detecta dificultades',
        ];
        $where_d = $where;
        foreach ($indicadores as $campo => $label) {
            $media = media_campo_coord($db, $campo, $where_d);
            $labels[] = $label; $values[] = round($media, 2); $total++;
        }

    // ── 7. VALORACIÓN GLOBAL ─────────────────────────────────────────────
    } elseif ($metric === 'incidencias_valoracion') {
        $group_field = 'planes';
        $gf = $db->real_escape_string($group_field);

        // Paso 1: obtener etiqueta de grupo por ticket
        $grupos = [];
        $qg = $db->query("SELECT t.ticket_id, fev.value AS grp
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name='{$gf}' AND fev.value IS NOT NULL AND TRIM(fev.value)!=''");
        if ($qg) while ($r = $qg->fetch_assoc()) {
            $grupos[(int)$r['ticket_id']] = trim(val_c($r['grp']));
        }

        // Paso 2: obtener valoracion por ticket
        $vals = [];
        $qv = $db->query("SELECT t.ticket_id, fev.value AS val
            FROM ost_ticket t
            JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            {$where} AND ff.name='valoracionglobal'
            AND fev.value IS NOT NULL AND TRIM(fev.value)!=''");
        if ($qv) while ($r = $qv->fetch_assoc()) {
            $v = trim(val_c($r['val']));
            if (is_numeric($v)) $vals[(int)$r['ticket_id']] = (float)$v;
        }

        // Paso 3: cruzar y calcular media por grupo
        $temp = [];
        foreach ($vals as $tid => $score) {
            $grp = $grupos[$tid] ?? null;
            if ($grp === null || $grp === '') continue;
            if (!isset($temp[$grp])) $temp[$grp] = ['sum'=>0,'n'=>0];
            $temp[$grp]['sum'] += $score;
            $temp[$grp]['n']++;
        }
        uasort($temp, function($a,$b){ return ($b['sum']/$b['n']) <=> ($a['sum']/$a['n']); });
        foreach ($temp as $grp => $d2) {
            $labels[] = $grp;
            $values[] = round($d2['sum'] / $d2['n'], 2);
            $total   += $d2['n'];
        }
        $axis_label = empty($filters['planes']) ? 'Plan' : (empty($filters['acciones']) ? 'Acción' : 'Curso');
        return ['ok'=>true,'metric'=>$metric,'range'=>$range,'rangeLabel'=>$rangeLabel,
                'labels'=>$labels,'values'=>$values,'total'=>$total,'axisLabel'=>$axis_label];
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
        $metric    = trim((string)($_GET['metric']    ?? 'evolucion'));
        $range     = trim((string)($_GET['range']     ?? 'year'));
        $date_from = trim((string)($_GET['date_from'] ?? ''));
        $date_to   = trim((string)($_GET['date_to']   ?? ''));
        $filters   = [];
        foreach ($CAMPOS_COORD as $c) { if ($t = $rf('filter_'.$c)) $filters[$c] = $t; }
        if ($date_from !== '' || $date_to !== '') {
            $GLOBALS['export_date_from'] = $date_from ? $date_from . ' 00:00:00' : null;
            $GLOBALS['export_date_to']   = $date_to   ? $date_to   . ' 23:59:59' : null;
            $range = 'custom';
        } else {
            $GLOBALS['export_date_from'] = null;
            $GLOBALS['export_date_to']   = null;
        }
        if (isset($_GET['debug_where'])) {
            $w = where_coord($db, $filters);
            echo json_encode(['ok'=>true,'where'=>$w]); exit;
        }
        echo json_encode(metric_query_coord($db, $metric, $range, $filters), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'msg'=>'Error cargando datos'], JSON_UNESCAPED_UNICODE); }
    $db->close(); exit;
}

// ── ENDPOINT: ajax_kpis ───────────────────────────────────────────────────
if (isset($_GET['ajax_kpis'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $fid = COORD_FORM_ID;
        $whereKpi = "WHERE EXISTS(SELECT 1 FROM ost_form_entry fe_k WHERE fe_k.object_id=ticket_id AND fe_k.form_id={$fid})";
        $total      = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket {$whereKpi}")->fetch_assoc()['c'];
        $alumnos    = (int)$db->query("SELECT SUM(CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED)) s
            FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            WHERE ff.name='ndealumnos' AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['s'] ?? 0;
        $finalizados = (int)$db->query("SELECT SUM(CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED)) s
            FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
            JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
            JOIN ost_form_field ff ON ff.id=fev.field_id
            WHERE ff.name='nfinalizados' AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['s'] ?? 0;
        $tasa = $alumnos > 0 ? round($finalizados / $alumnos * 100, 1) : 0;
        echo json_encode(['ok'=>true,'total'=>$total,'alumnos'=>$alumnos,'finalizados'=>$finalizados,'tasa'=>$tasa], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}


// ── KPIs (carga inicial) ──────────────────────────────────────────────────
try {
    $fid = COORD_FORM_ID;
    $whereKpi = "WHERE EXISTS(SELECT 1 FROM ost_form_entry fe_k WHERE fe_k.object_id=ticket_id AND fe_k.form_id={$fid})";
    $kpi_total  = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket {$whereKpi}")->fetch_assoc()['c'];
    $kpi_hoy    = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket {$whereKpi} AND DATE(created)=CURDATE()")->fetch_assoc()['c'];
    $kpi_semana = (int)$db->query("SELECT COUNT(*) c FROM ost_ticket {$whereKpi} AND created>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'];
    $kpi_val    = $db->query("SELECT ROUND(AVG(CAST(fev.value AS DECIMAL(10,2))),1) m
        FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='valoracionglobal' AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['m'] ?? '—';
    $kpi_alumnos = (int)$db->query("SELECT SUM(CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED)) s
        FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='ndealumnos' AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['s'] ?? 0;
    $kpi_finalizados = (int)$db->query("SELECT SUM(CAST(NULLIF(TRIM(fev.value),'') AS UNSIGNED)) s
        FROM ost_ticket t JOIN ost_form_entry fe ON fe.object_id=t.ticket_id AND fe.form_id={$fid}
        JOIN ost_form_entry_values fev ON fev.entry_id=fe.id
        JOIN ost_form_field ff ON ff.id=fev.field_id
        WHERE ff.name='nfinalizados' AND fev.value REGEXP '^[0-9]'")->fetch_assoc()['s'] ?? 0;
} catch (Throwable $e) {
    $kpi_total = $kpi_hoy = $kpi_semana = $kpi_alumnos = $kpi_finalizados = 0; $kpi_val = '—';
}

$default_ranges = [
    'evolucion'              => 'day',
    'planes'                 => 'day',
    'alumnos'                => 'day',
    'incidencias_valoracion' => 'day',
];

$graficas = [
    ['evolucion',              'Evolución de registros',                'fa-chart-line',       'line'],
    ['planes',                 'Planes',                                'fa-layer-group',      'bar'],
    ['alumnos',                'Alumnos matriculados · finalizados',    'fa-users',            'bar_multi'],
    ['incidencias_valoracion', 'Valoración media por plan',            'fa-star-half-stroke', 'bar'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estadísticas Coordinadores — Grupo ATU</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ── VARIABLES (idénticas a coordinadores.php) ──────────────────── */
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
    --transition:all .3s cubic-bezier(.4,0,.2,1);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--text)}

/* ── SIDEBAR (copia exacta de coordinadores.php) ── */
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
.linea-menu{border:none;border-top:1px solid rgba(255,255,255,.1);margin:8px 14px}

/* ── MAIN ── */
.main-content{margin-left:var(--sidebar-width);transition:var(--transition);min-height:100vh}
.sidebar.collapsed~.main-content{margin-left:var(--sidebar-collapsed)}

/* ── HEADER ── */
.header{height:var(--header-height);background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:400;transition:transform .3s,opacity .3s}
.header.hide{transform:translateY(-100%);opacity:0;pointer-events:none}
.header h1{margin:0;font-size:26px;font-weight:700}
.header-right{font-size:15px;display:flex;gap:8px;align-items:center;color:var(--text);font-weight:500}

/* ── HERO (mismo degradado teal) ── */
.hero{background:linear-gradient(135deg,#134e4a,#0d9488);padding:28px 32px;color:#fff}
.hero h2{margin:0 0 20px;font-size:22px;font-weight:700}
.hero-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}
.hero-card{background:rgba(255,255,255,.15);padding:20px 24px;border-radius:14px;backdrop-filter:blur(4px)}
.hero-card .label{font-size:13px;opacity:.85;margin-bottom:4px}
.hero-card .value{font-size:32px;font-weight:800;line-height:1}
.hero-card .sub{font-size:11px;opacity:.75;margin-top:6px}

/* ── CONTAINER ── */
.page-content{padding:24px 28px}

/* ── GRID DE GRÁFICAS ── */
.charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:22px}

/* ── CHART BOX ── */
.chart-box{background:var(--bg-card);border-radius:14px;border:1px solid var(--border);box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:visible;position:relative}
.chart-box-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.chart-title{font-size:14px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.chart-title i{color:var(--primary)}
.metric-total{display:inline-flex;align-items:center;gap:6px;background:var(--primary-bg);color:var(--primary-dark);border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;white-space:nowrap}

/* ── RANGOS ── */
.range-group{display:flex;gap:6px;flex-wrap:wrap;padding:10px 20px 0;position:relative;z-index:8}
.range-btn{border:1px solid var(--border);background:#fff;color:var(--text-secondary);border-radius:999px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:var(--transition)}
.range-btn:hover{border-color:var(--primary);color:var(--primary)}
.range-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(13,148,136,.25)}

/* ── LEYENDA DE NOTA (valoración) ── */
.score-legend{display:flex;flex-wrap:wrap;gap:6px;padding:8px 20px 4px;align-items:center}
.score-band-btn{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:999px;border:2px solid #e2e8f0;background:#fff;color:#475569;font-size:11px;font-weight:600;cursor:pointer;transition:.2s;font-family:'Inter',sans-serif;white-space:nowrap}
.score-band-btn:hover{border-color:#94a3b8;color:#1e293b}
.score-band-btn.active{background:#1e293b;color:#fff;border-color:#1e293b}
.score-band-btn[data-color].active{color:#fff}
.score-band-all{border-style:dashed}
.score-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;display:inline-block}

/* ── FILTROS BAR ── */
.filters-bar{display:flex;flex-wrap:wrap;gap:10px;padding:12px 20px;background:#f8fafc;border-bottom:1px solid var(--border);align-items:flex-end;position:relative;z-index:20}
.filter-pill{display:flex;flex-direction:column;gap:3px;flex:1;min-width:110px;max-width:190px}
.filter-pill label{font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px}
.filter-actions{display:flex;align-items:flex-end;gap:6px;flex-shrink:0}
.btn-limpiar{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--text-secondary);font-size:11px;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap;font-family:'Inter',sans-serif}
.btn-limpiar:hover{background:#fee2e2;border-color:#fca5a5;color:#dc2626}

/* ── CANVAS ── */
.chart-canvas-wrap{padding:18px 20px 20px}
canvas{width:100%!important;height:285px!important}

/* ── BADGE INCIDENCIAS ── */
.incid-badge{display:flex;gap:16px;padding:12px 20px;background:#f0fdfa;border-top:1px solid #99f6e4;font-size:13px;color:#0f766e;align-items:center;flex-wrap:wrap}
.incid-badge strong{color:#0d9488;font-size:18px}

/* ── LOADING ── */
.chart-loading{display:none;position:absolute;inset:0;background:rgba(255,255,255,.8);border-radius:14px;align-items:center;justify-content:center;z-index:10}
.chart-loading.active{display:flex}
.spinner{width:32px;height:32px;border:3px solid var(--primary-bg);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── TOAST ── */
.toast{position:fixed;top:20px;right:20px;background:#0f172a;color:#fff;padding:14px 22px;border-radius:var(--radius);font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);transform:translateY(-120px);opacity:0;transition:all .35s;z-index:99999}
.toast.show{transform:translateY(0);opacity:1}
.toast .ok-icon{color:#10b981}
.toast .info-icon{color:#0d9488}

/* ── EXPORTAR BTN ── */
.btn-export-main{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;border:none;background:linear-gradient(135deg,#0d9488,#0f766e);color:#fff;cursor:pointer;transition:all .25s;font-family:'Inter',sans-serif;box-shadow:0 4px 14px rgba(13,148,136,.35);letter-spacing:.2px;position:relative;overflow:hidden}
.btn-export-main::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,0);transition:background .2s}
.btn-export-main:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(13,148,136,.45)}
.btn-export-main:hover::after{background:rgba(255,255,255,.08)}
.btn-export-main:active{transform:translateY(0);box-shadow:0 2px 8px rgba(13,148,136,.3)}
.btn-export-main i{font-size:14px;transition:transform .3s}
.btn-export-main:hover i{transform:translateY(2px)}

/* ── MODAL ── */
@keyframes modalIn{from{opacity:0;transform:scale(.94) translateY(18px)}to{opacity:1;transform:scale(1) translateY(0)}}
@keyframes overlayIn{from{opacity:0}to{opacity:1}}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(2,8,23,.7);backdrop-filter:blur(8px);z-index:99998;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{animation:overlayIn .2s ease both}
.modal-box{background:#fff;width:100%;max-width:800px;border-radius:24px;padding:0;box-shadow:0 40px 100px rgba(0,0,0,.28),0 8px 24px rgba(0,0,0,.12);overflow:hidden;animation:modalIn .3s cubic-bezier(.34,1.56,.64,1) both}
.modal-box h3{font-size:16px;font-weight:700;margin:0}
.modal-box label{font-size:13px;display:flex;gap:8px;align-items:center;padding:6px 0;cursor:pointer}
.modal-box input[type=checkbox]{accent-color:var(--primary);width:16px;height:16px}
.modal-info{font-size:13px;color:rgba(255,255,255,.75);margin:0;line-height:1.4}
.modal-header{background:linear-gradient(135deg,#0f766e 0%,#0d9488 55%,#14b8a6 100%);padding:22px 26px;display:flex;align-items:center;gap:16px;position:relative;overflow:hidden}
.modal-header::before{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:rgba(255,255,255,.06);border-radius:50%}
.modal-header::after{content:'';position:absolute;bottom:-60px;right:60px;width:120px;height:120px;background:rgba(255,255,255,.04);border-radius:50%}
.modal-header-icon{background:rgba(255,255,255,.2);width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,.15)}
.modal-body{padding:24px 26px;display:grid;grid-template-columns:1fr 1fr;gap:24px}
.modal-col{}
.modal-section-title{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:12px;display:flex;align-items:center;gap:7px}
.modal-actions{grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid #f1f5f9;margin-top:4px}

/* Botones de formato — tarjetas grandes */
.btn-modal{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:14px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .22s;font-family:'Inter',sans-serif;letter-spacing:.2px;position:relative;overflow:hidden}
.btn-modal::before{content:'';position:absolute;inset:0;background:linear-gradient(rgba(255,255,255,.12),rgba(255,255,255,0));pointer-events:none}
.btn-modal:active{transform:scale(.97)!important}
.btn-modal.primary{background:linear-gradient(135deg,#0d9488 0%,#059669 100%);color:#fff;box-shadow:0 4px 16px rgba(13,148,136,.35)}
.btn-modal.primary:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(13,148,136,.5)}
.btn-modal.success{background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:#fff;box-shadow:0 4px 16px rgba(59,130,246,.35)}
.btn-modal.success:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(37,99,235,.5)}
.btn-modal.neutral{background:#f8fafc;color:#64748b;border:1.5px solid #e2e8f0}
.btn-modal.neutral:hover{background:#fee2e2;color:#dc2626;border-color:#fecaca}

/* Tarjetas de formato visual */
.format-card{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:18px 14px;border-radius:16px;border:2px solid #e2e8f0;background:#f8fafc;cursor:pointer;transition:all .22s;font-family:'Inter',sans-serif;position:relative;overflow:hidden;text-align:center}
.format-card::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 60%,rgba(255,255,255,.3));pointer-events:none}
.format-card:hover{transform:translateY(-3px)}
.format-card:active{transform:scale(.97)}
.format-card .fc-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:2px;transition:transform .3s}
.format-card:hover .fc-icon{transform:scale(1.12) rotate(-3deg)}
.format-card .fc-label{font-size:14px;font-weight:800;letter-spacing:.2px}
.format-card .fc-sub{font-size:10px;font-weight:500;opacity:.7;line-height:1.3}
.format-card.png-card{border-color:#0d9488;background:linear-gradient(145deg,#f0fdf9,#ccfbf1);color:#0f766e;box-shadow:0 4px 20px rgba(13,148,136,.15)}
.format-card.png-card .fc-icon{background:linear-gradient(135deg,#0d9488,#14b8a6);color:#fff;box-shadow:0 4px 12px rgba(13,148,136,.4)}

.export-date-input{width:100%;height:40px;border:1.5px solid #e2e8f0;border-radius:10px;padding:0 14px;font-size:13px;font-family:Inter,sans-serif;color:#111827;background:#f8fafc;box-sizing:border-box;outline:none;transition:border-color .2s,background .2s,box-shadow .2s}
.export-date-input:focus{border-color:#0d9488;background:#fff;box-shadow:0 0 0 3px rgba(13,148,136,.12)}
.preset-btn{font-size:11px;padding:5px 12px;border:1.5px solid #e2e8f0;border-radius:20px;background:#f8fafc;color:#475569;cursor:pointer;font-weight:600;transition:all .18s;font-family:Inter,sans-serif}
.preset-btn:hover{border-color:#0d9488;color:#0d9488;background:#f0fdf9;transform:translateY(-1px)}
.preset-btn.active-preset{border-color:#0d9488!important;background:#0d9488!important;color:#fff!important;box-shadow:0 3px 10px rgba(13,148,136,.3)}
.check-row{display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;color:#1e293b;padding:9px 12px;border-radius:10px;transition:all .15s;font-weight:500;border:1.5px solid transparent}
.check-row:hover{background:#f0fdf9;border-color:#99f6e4;transform:translateX(2px)}
.check-row input[type=checkbox]{width:16px;height:16px;accent-color:#0d9488;cursor:pointer;flex-shrink:0}
.check-row:has(input:checked){background:#f0fdf9;border-color:#ccfbf1}

/* ── TOM SELECT ── */
.ts-wrapper{font-size:12px;position:relative;z-index:15}
.ts-control{min-height:32px!important;border:1.5px solid var(--border)!important;border-radius:6px!important;padding:4px 8px!important;box-shadow:none!important;background:#fff!important;font-size:12px!important}
.ts-control input{font-size:12px!important}
.ts-dropdown{border:1px solid var(--border)!important;border-radius:8px!important;z-index:99999!important;background:#fff!important;font-size:12px!important}
.ts-dropdown .option{font-size:12px;padding:8px 10px}
.ts-dropdown .active{background:var(--primary-bg)!important;color:var(--primary-dark)!important}
.ts-wrapper .item{background:var(--primary-bg)!important;color:var(--primary)!important;border-radius:4px!important;font-size:11px!important;padding:1px 5px!important}
.ts-wrapper .remove{color:var(--primary)!important}
.ts-wrapper.focus .ts-control{border-color:var(--primary)!important;box-shadow:0 0 0 3px rgba(13,148,136,.12)!important}

/* ── ANIMATIONS ── */
@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-18px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-30px)}to{opacity:1;transform:translateX(0)}}
@keyframes pulseGlow{0%,100%{box-shadow:0 0 0 0 rgba(13,148,136,0)}50%{box-shadow:0 0 0 8px rgba(13,148,136,.12)}}
.header{animation:fadeSlideDown .4s ease both}
.sidebar{animation:slideInLeft .4s cubic-bezier(.4,0,.2,1) both}
.menu-item{transition:var(--transition)}
.menu-item:hover{transform:translateX(4px)}
.menu-item.active{animation:pulseGlow 2.5s ease infinite}
.hero-card{animation:fadeSlideUp .45s ease both}
.hero-card:nth-child(1){animation-delay:.05s}
.hero-card:nth-child(2){animation-delay:.12s}
.hero-card:nth-child(3){animation-delay:.19s}
.hero-card:nth-child(4){animation-delay:.26s}
.hero-card:nth-child(5){animation-delay:.33s}
.hero-card:nth-child(6){animation-delay:.40s}

@media(max-width:900px){
    .sidebar{position:relative;width:100%;height:auto}.main-content{margin-left:0}
    .charts-grid{grid-template-columns:1fr}canvas{height:240px!important}
    .filter-pill{min-width:100px}
}
</style>
</head>
<body>

<div class="toast" id="toast"><i class="fas fa-check-circle ok-icon"></i><span id="toastMsg"></span></div>

<!-- ────────────── SIDEBAR (idéntico a coordinadores.php) ────────────── -->
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
        <hr class="linea-menu">
        <a href="valoraciones.php" class="menu-item"><i class="fa-solid fa-clipboard-list"></i><span>Tabla de Valoraciones</span></a>
        <a href="https://incidencias.grupoatu.com/osticket/estadisticas_valoraciones.php" class="menu-item"><i class="fas fa-chart-pie"></i><span>Estadisticas Valoraciones</span></a>
        <hr class="linea-menu">
        <a href="coordinadores.php"            class="menu-item"><i class="fa-solid fa-user-tie"></i><span>Tabla de Coordinadores</span></a>
        <a href="estadisticas_coordinadores.php" class="menu-item active"><i class="fas fa-chart-line"></i><span>Estadísticas Coordinadores</span></a>
    </nav>
    <button class="sidebar-collapse-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
</aside>

<!-- ────────────── MAIN ────────────── -->
<main class="main-content">

    <!-- HEADER -->
    <div class="header" id="mainHeader">
        <h1>Estadísticas Coordinadores</h1>
        <div style="display:flex;align-items:center;gap:16px">
            <button class="btn-export-main" onclick="openExportModal()"><i class="fas fa-arrow-down-to-line"></i> Descargar</button>
            <div class="header-right"><i class="fa-solid fa-calendar-days" style="color:var(--primary)"></i><span id="clock"></span></div>
            <div style="display:flex;align-items:center;gap:12px;border-left:1px solid var(--border);padding-left:20px">
                <span style="font-size:13px;color:var(--text-secondary);font-weight:600;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-user-circle" style="color:var(--primary);font-size:16px"></i>
                    <?= h_c($_SESSION['coord_stats_user'] ?? $_SESSION['incidencias_user'] ?? '') ?>
                </span>
                <a href="?logout=1" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#fee2e2;color:#ef4444;text-decoration:none;transition:.2s"
                   onmouseover="this.style.background='#ef4444';this.style.color='#fff'"
                   onmouseout="this.style.background='#fee2e2';this.style.color='#ef4444'"
                   title="Cerrar Sesión"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <!-- HERO -->
    <div class="hero">
        <h2>Panel de Estadísticas de Coordinadores</h2>
        <div class="hero-cards">
            <div class="hero-card">
                <div class="label">Total registros</div>
                <div class="value" id="kpi-total"><?= number_format($kpi_total) ?></div>
            </div>
            <div class="hero-card">
                <div class="label">Alumnos matriculados</div>
                <div class="value" id="kpi-alumnos"><?= number_format($kpi_alumnos) ?></div>
            </div>
            <div class="hero-card">
                <div class="label">Alumnos finalizados</div>
                <div class="value" id="kpi-finalizados"><?= number_format($kpi_finalizados) ?></div>
            </div>
            <div class="hero-card">
                <div class="label">Tasa de finalización</div>
                <?php $tasa = $kpi_alumnos > 0 ? round($kpi_finalizados / $kpi_alumnos * 100, 1) : 0; ?>
                <div class="value"><span id="kpi-tasa"><?= $tasa ?></span><span style="font-size:18px;font-weight:600;opacity:.8">%</span></div>

            </div>
        </div>
    </div>

    <!-- GRÁFICAS -->
    <div class="page-content">
        <div class="charts-grid">
        <?php foreach ($graficas as [$gid, $gtitle, $gicon, $gtype]): ?>
            <div class="chart-box" id="box-<?= $gid ?>">
                <div class="chart-loading" id="loading-<?= $gid ?>"><div class="spinner"></div></div>
                <div class="chart-box-header">
                    <div class="chart-title"><i class="fas <?= $gicon ?>"></i> <?= h_c($gtitle) ?></div>
                    <span id="total-<?= $gid ?>" style="display:none"></span>
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
                <?php if ($gid === 'incidencias_valoracion'): ?>
                <div class="score-legend" id="score-legend-<?= $gid ?>">
                    <?php
                    $bands = [
                        ['4','5',  '#15803d','4 – 5'],
                        ['3','3.9','#d97706','3'],
                        ['1','2.9','#b91c1c','1 – 2'],
                    ];
                    foreach ($bands as $b): ?>
                    <button type="button" class="score-band-btn" data-min="<?= $b[0] ?>" data-max="<?= $b[1] ?>" data-color="<?= $b[2] ?>" data-chart="<?= $gid ?>" title="Nota <?= $b[0] ?>–<?= $b[1] ?>">
                        <span class="score-dot" style="background:<?= $b[2] ?>"></span><?= $b[3] ?>
                    </button>
                    <?php endforeach; ?>
                    <button type="button" class="score-band-btn score-band-all active" data-min="" data-max="" data-chart="<?= $gid ?>">Todos</button>
                </div>
                <?php endif; ?>
                <div class="chart-canvas-wrap"><canvas id="<?= $gid ?>Chart"></canvas></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- MODAL EXPORTAR -->
<div class="modal-overlay" id="exportModal">
    <div class="modal-box">

        <!-- Header -->
        <div class="modal-header">
            <div class="modal-header-icon"><i class="fas fa-file-zipper"></i></div>
            <div style="position:relative;z-index:1">
                <h3 style="color:#fff;font-size:18px;font-weight:800;margin-bottom:3px">Descargar gráficas</h3>
                <p class="modal-info">ZIP con imágenes JPG de las gráficas seleccionadas</p>
            </div>
            <button onclick="closeExportModal()" style="margin-left:auto;position:relative;z-index:1;background:rgba(255,255,255,.15);border:none;color:#fff;width:36px;height:36px;border-radius:10px;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s;backdrop-filter:blur(4px)" onmouseover="this.style.background='rgba(255,255,255,.28)';this.style.transform='rotate(90deg)'" onmouseout="this.style.background='rgba(255,255,255,.15)';this.style.transform='rotate(0deg)'"><i class="fas fa-times"></i></button>
        </div>

        <!-- Body en dos columnas -->
        <div class="modal-body">

            <!-- Columna izquierda: fechas + formato -->
            <div class="modal-col">
                <div class="modal-section-title"><i class="fas fa-calendar-alt" style="color:#0d9488"></i> Período de exportación</div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
                    <div>
                        <label style="font-size:10px;font-weight:700;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Desde</label>
                        <input type="date" id="exportDateFrom" class="export-date-input">
                    </div>
                    <div>
                        <label style="font-size:10px;font-weight:700;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Hasta</label>
                        <input type="date" id="exportDateTo" class="export-date-input">
                    </div>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px">
                    <button type="button" class="preset-btn" onclick="setExportDatePreset('today',this)">Hoy</button>
                    <button type="button" class="preset-btn" onclick="setExportDatePreset('week',this)">Última semana</button>
                    <button type="button" class="preset-btn" onclick="setExportDatePreset('month',this)">Último mes</button>
                    <button type="button" class="preset-btn" onclick="setExportDatePreset('year',this)">Último año</button>
                    <button type="button" class="preset-btn" onclick="setExportDatePreset('all',this)">Histórico</button>
                </div>

                <!-- Botón de descarga -->
                <div style="margin-top:20px">
                    <button type="button" class="format-card png-card" onclick="exportCharts('png')" style="width:100%;flex-direction:row;gap:14px;padding:16px 20px;text-align:left">
                        <div class="fc-icon" style="width:44px;height:44px;flex-shrink:0"><i class="fas fa-file-zipper"></i></div>
                        <div>
                            <div class="fc-label" style="font-size:15px">Descargar ZIP</div>
                            <div class="fc-sub" style="font-size:11px;margin-top:2px">Imágenes JPG de las gráficas<br>seleccionadas en un archivo ZIP</div>
                        </div>
                        <i class="fas fa-arrow-down" style="margin-left:auto;font-size:16px;opacity:.7"></i>
                    </button>
                </div>
            </div>

            <!-- Columna derecha: gráficas -->
            <div class="modal-col">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div class="modal-section-title" style="margin-bottom:0"><i class="fas fa-chart-bar" style="color:#0d9488"></i> Gráficas a exportar</div>
                    <div style="display:flex;gap:5px">
                        <button type="button" onclick="toggleAllExportChecks(true)"  class="preset-btn" style="font-size:10px;padding:3px 10px">Todo</button>
                        <button type="button" onclick="toggleAllExportChecks(false)" class="preset-btn" style="font-size:10px;padding:3px 10px;border-color:#fca5a5;color:#ef4444" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background=''">Nada</button>
                    </div>
                </div>
                <div style="display:grid;gap:6px">
                    <?php foreach ($graficas as [$gid, $gtitle, $gicon]): ?>
                    <label class="check-row">
                        <input type="checkbox" value="<?= $gid ?>" checked>
                        <i class="fas <?= $gicon ?>" style="color:#0d9488;width:16px;text-align:center;font-size:13px"></i>
                        <?= h_c($gtitle) ?>
                    </label>
                    <?php endforeach; ?>
                </div>

              
            </div>

        </div><!-- /modal-body -->
    </div>
</div>

<script>
/* ── KPIs EN TIEMPO REAL ── */
(function(){
    var INTERVAL = 30000; // refresca cada 30 segundos

    function animarNumero(el, nuevoValor, esDecimal) {
        if (!el) return;
        var actual = parseFloat(el.textContent.replace(/[^0-9.]/g,'')) || 0;
        var diff   = nuevoValor - actual;
        if (Math.abs(diff) < 0.01) return;
        var pasos = 20, paso = 0;
        var intervalo = setInterval(function(){
            paso++;
            var v = actual + diff * (paso / pasos);
            el.textContent = esDecimal ? v.toFixed(1) : Math.round(v).toLocaleString('es-ES');
            if (paso >= pasos) {
                el.textContent = esDecimal ? nuevoValor.toFixed(1) : nuevoValor.toLocaleString('es-ES');
                clearInterval(intervalo);
            }
        }, 25);
    }

    function flashCard(el) {
        if (!el) return;
        var card = el.closest('.hero-card');
        if (!card) return;
        card.style.transition = 'background .3s';
        card.style.background = 'rgba(255,255,255,.30)';
        setTimeout(function(){ card.style.background = ''; }, 600);
    }

    function refrescarKpis() {
        fetch('estadisticas_coordinadores.php?ajax_kpis=1')
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) return;

                var elTotal      = document.getElementById('kpi-total');
                var elAlumnos    = document.getElementById('kpi-alumnos');
                var elFinaliz    = document.getElementById('kpi-finalizados');
                var elTasa       = document.getElementById('kpi-tasa');
                var elTasaSub    = document.getElementById('kpi-tasa-sub');

                animarNumero(elTotal,   d.total,       false);
                animarNumero(elAlumnos, d.alumnos,     false);
                animarNumero(elFinaliz, d.finalizados, false);
                animarNumero(elTasa,    d.tasa,        true);

                [elTotal, elAlumnos, elFinaliz, elTasa].forEach(flashCard);
            })
            .catch(function(e){ console.warn('KPI refresh error:', e); });
    }

    // Primera actualización al cargar (tras 5s para no solapar con la carga inicial)
    setTimeout(refrescarKpis, 5000);
    setInterval(refrescarKpis, INTERVAL);
})();

/* ── RELOJ ── */
(function(){
    var el = document.getElementById('clock');
    function tick(){
        var n=new Date(), p=function(x){return String(x).padStart(2,'0')};
        el.textContent = p(n.getDate())+'/'+p(n.getMonth()+1)+'/'+n.getFullYear()+', '+p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());
    }
    tick(); setInterval(tick,1000);
})();

/* ── HEADER HIDE ON SCROLL ── */
(function(){
    var last=0, hdr=document.getElementById('mainHeader');
    window.addEventListener('scroll',function(){
        var st=window.pageYOffset||document.documentElement.scrollTop;
        if(st>last&&st>80) hdr.classList.add('hide'); else hdr.classList.remove('hide');
        last=st<=0?0:st;
    });
})();

/* ── TOAST ── */
function showToast(msg,type){
    var t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    t.querySelector('i').className=type==='success'?'fas fa-check-circle ok-icon':'fas fa-info-circle info-icon';
    t.className='toast '+type+' show'; setTimeout(function(){t.classList.remove('show');},3000);
}

/* ── PALETA ── */
var PALETTE=['#0d9488','#0f766e','#14b8a6','#f59e0b','#6366f1','#3b82f6','#ec4899','#10b981','#ef4444','#8b5cf6','#f97316','#84cc16'];
function pal(n){ return Array.from({length:n},function(_,i){return PALETTE[i%PALETTE.length];}); }

// Paletas especializadas por métrica
var PALETTES={
    // Cada plan con su propio color diferenciado
    planes: ['#0d9488','#6366f1','#f59e0b','#ec4899','#3b82f6','#10b981','#ef4444','#8b5cf6','#f97316','#14b8a6','#84cc16','#06b6d4','#a855f7','#fb923c','#e11d48'],
    // Paleta para valoración: colores únicos y distintos por plan (sin semántica)
    incidencias_valoracion: ['#3b82f6','#8b5cf6','#06b6d4','#ec4899','#f59e0b','#10b981','#6366f1','#fb923c','#14b8a6','#a855f7','#0ea5e9','#d946ef','#22c55e','#f43f5e','#eab308'],
    // Un color distinto por cada radar (fácil de distinguir entre sí)
    radar_tutor:        {fill:'rgba(13,148,136,0.18)',   border:'#0d9488', point:'#0f766e'},
    radar_apoyo:        {fill:'rgba(99,102,241,0.18)',   border:'#6366f1', point:'#4f46e5'},
    radar_dinamizacion: {fill:'rgba(245,158,11,0.18)',   border:'#f59e0b', point:'#d97706'},
    // Barras agrupadas alumnos
    alumnos_mat: {fill:'rgba(13,148,136,0.22)',  border:'#0d9488'},
    alumnos_fin: {fill:'rgba(99,102,241,0.22)',  border:'#6366f1'},
};

function palKey(metric){
    if(metric==='incidencias_valoracion') return 'incidencias_valoracion';
    return 'planes'; // fallback multicolor genérico
}
function strHash(s){
    var h=0;
    for(var i=0;i<s.length;i++) h=(Math.imul(31,h)+s.charCodeAt(i))|0;
    return Math.abs(h);
}

// Mapa estable label → índice de paleta (se construye en la primera carga completa)
var labelColorMap = {};

function palColors(n,key,alpha,labels){
    var base=PALETTES[key]||PALETTE;
    return Array.from({length:n},function(_,i){
        var lbl = labels && labels[i];
        var idx;
        if(lbl && labelColorMap[lbl] !== undefined){
            idx = labelColorMap[lbl];
        } else if(lbl){
            // Fallback: hash estable por nombre
            idx = strHash(lbl) % base.length;
        } else {
            idx = i % base.length;
        }
        var hex=base[idx];
        var r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
        return alpha!=null?'rgba('+r+','+g+','+b+','+alpha+')':hex;
    });
}

/* ── ESTADO GLOBAL ── */
var defaultRanges = <?= json_encode($default_ranges, JSON_UNESCAPED_UNICODE) ?>;
var chartRanges   = Object.assign({}, defaultRanges);
var charts        = {};
var currentFilters = {};
<?php foreach ($graficas as [$gid]): ?>currentFilters['<?= $gid ?>']={};<?php endforeach; ?>
var availableFilters={}, tomInstances={};

/* ── RANGOS ── */
function syncButtons(metric,range){
    document.querySelectorAll('.range-btn[data-metric="'+metric+'"]').forEach(function(b){
        b.classList.toggle('active', b.dataset.range===range);
    });
}
function setRange(metric,range){ chartRanges[metric]=range; syncButtons(metric,range); loadMetric(metric); }
document.addEventListener('click',function(e){
    if(e.target.classList.contains('range-btn')) setRange(e.target.dataset.metric, e.target.dataset.range);
});

/* ── URL AJAX ── */
function buildUrl(metric){
    var range=chartRanges[metric]||defaultRanges[metric]||'historico';
    var parts=['ajax=1','metric='+encodeURIComponent(metric),'range='+encodeURIComponent(range)];
    var f=currentFilters[metric]||{};
    Object.keys(f).forEach(function(c){
        (Array.isArray(f[c])?f[c]:[f[c]]).forEach(function(v){
            if(v!==null&&v!==undefined&&String(v).trim()!=='')
                parts.push('filter_'+c+'%5B%5D='+encodeURIComponent(v));
        });
    });
    return 'estadisticas_coordinadores.php?'+parts.join('&');
}

/* ── TIPOS DE GRÁFICA ── */
var CHART_TYPES={
    evolucion:'line',planes:'bar',alumnos:'bar',
    desempeno_tutor:'bar',desempeno_apoyo:'bar',desempeno_dinamizacion:'bar',
    incidencias_valoracion:'bar'
};

// Paletas específicas para cada gráfica de desempeño
var DESEMPENO_PALETTES={
    desempeno_tutor:        ['#0d9488','#14b8a6','#2dd4bf','#0f766e','#134e4a'],
    desempeno_apoyo:        ['#6366f1','#818cf8','#a5b4fc','#4f46e5','#3730a3'],
    desempeno_dinamizacion: ['#f59e0b','#fbbf24','#fcd34d','#d97706','#b45309'],
};

function createChart(metric,d){
    var ctx=document.getElementById(metric+'Chart'); if(!ctx)return;
    var type=CHART_TYPES[metric]||'bar';
    var isDesempeno=['desempeno_tutor','desempeno_apoyo','desempeno_dinamizacion'].indexOf(metric)!==-1;
    var n=(d.labels||[]).length;

    var datasets;

    // ── Matriculados · Finalizados: barras agrupadas 2 colores distintos
    if(metric==='alumnos'&&d.values2){
        datasets=[
            {label:d.label1||'Matriculados',data:d.values,
                backgroundColor:PALETTES.alumnos_mat.fill,borderColor:PALETTES.alumnos_mat.border,
                borderWidth:2,borderRadius:4},
            {label:d.label2||'Finalizados', data:d.values2,
                backgroundColor:PALETTES.alumnos_fin.fill,borderColor:PALETTES.alumnos_fin.border,
                borderWidth:2,borderRadius:4}
        ];

    // ── Desempeño (tutor/apoyo/dinamización): barras horizontales, color propio por indicador
    } else if(isDesempeno){
        var dp=DESEMPENO_PALETTES[metric]||PALETTE;
        var bgD=Array.from({length:n},function(_,i){var c=dp[i%dp.length];var r=parseInt(c.slice(1,3),16),g=parseInt(c.slice(3,5),16),b=parseInt(c.slice(5,7),16);return'rgba('+r+','+g+','+b+',0.82)';});
        var bdD=Array.from({length:n},function(_,i){return dp[i%dp.length];});
        datasets=[{label:'Media (1-5)',data:d.values,
            backgroundColor:bgD,borderColor:bdD,
            borderWidth:1.5,borderRadius:5,borderSkipped:false}];

    // ── Línea evolución temporal
    } else if(type==='line'){
        datasets=[{label:'Registros',data:d.values,
            backgroundColor:'rgba(13,148,136,0.12)',borderColor:PALETTE[0],
            borderWidth:2.5,tension:.4,fill:true,
            pointBackgroundColor:PALETTE[0],pointBorderColor:'#fff',pointBorderWidth:2,
            pointRadius:4,pointHoverRadius:6}];

    // ── Barras multicolor: cada barra con su propio color
    } else {
        var pk=palKey(metric);
        var bgColors, bdColors;
        if(metric==='incidencias_valoracion'){
            bgColors=palColors(n,pk,0.75,d.labels);
            bdColors=d.values.map(function(v){
                var score=parseFloat(v);
                if(score>=4) return '#15803d'; // verde oscuro (4-5)
                if(score>=3) return '#d97706'; // ámbar (3)
                return '#b91c1c';              // rojo (1-2)
            });
        } else {
            bgColors=palColors(n,pk,0.78);
            bdColors=palColors(n,pk,null);
        }
        datasets=[{label:'Registros',data:d.values,
            backgroundColor:bgColors,
            borderColor:bdColors,
            borderWidth:3,borderRadius:5,borderSkipped:false}];
    }

    var cfg={type:type,data:{labels:d.labels,datasets:datasets},options:{
        responsive:true,maintainAspectRatio:false,
        plugins:{
            legend:{
                position:'top',
                display:metric==='alumnos'||type==='line',
                labels:{font:{size:11},boxWidth:14,padding:14,usePointStyle:true}
            },
            tooltip:{callbacks:{label:function(ctx){
                if(isDesempeno||metric==='incidencias_valoracion') return ' '+parseFloat(ctx.formattedValue).toFixed(2)+' / 5';
                return ' '+ctx.formattedValue;
            }}}
        },
        scales:{
            x:{grid:{color:'#f1f5f9'},ticks:{font:{size:11},maxRotation:35}},
            y:{grid:{color:'#f1f5f9'},ticks:{font:{size:11}},beginAtZero:true}
        }
    }};

    // Valoración: eje Y de 0 a 5
    if(metric==='incidencias_valoracion'){
        cfg.options.scales.y.max=5;
        cfg.options.scales.y.ticks=Object.assign({},cfg.options.scales.y.ticks,{
            callback:function(v){return v+' ★';}
        });
    }

    // Planes: siempre horizontal, legible con nombres largos
    if(metric==='planes'){
        cfg.options.indexAxis='y';
        cfg.options.scales={
            x:{grid:{color:'#f1f5f9'},ticks:{font:{size:11}},beginAtZero:true},
            y:{grid:{display:false},ticks:{font:{size:11}}}
        };
    }

    // Barras horizontales para desempeño (indicadores en eje Y)
    if(isDesempeno){
        cfg.options.indexAxis='y';
        cfg.options.scales={
            x:{grid:{color:'#f1f5f9'},ticks:{font:{size:11}},beginAtZero:true,max:5,
               title:{display:true,text:'Media (1-5)',font:{size:10},color:'#94a3b8'}},
            y:{grid:{display:false},ticks:{font:{size:11},color:'#475569'}}
        };
        cfg.options.plugins.legend.display=false;
    }

    if(charts[metric]) charts[metric].destroy();
    charts[metric]=new Chart(ctx,cfg);

    // Inicializar leyenda interactiva de nota
    if(metric==='incidencias_valoracion'){
        initScoreLegend(metric, d);
    }
}

/* ── LEYENDA INTERACTIVA DE NOTA ── */
var scoreLegendData = {}; // guarda {labels, values, bgColors, bdColors} por metric

function initScoreLegend(metric, d){
    var pk=palKey(metric);
    var n=d.labels.length;
    var bgFull=palColors(n,pk,0.75,d.labels);
    var bdFull=d.values.map(function(v){
        var s=parseFloat(v);
        if(s>=4) return '#15803d'; // verde oscuro (4-5)
        if(s>=3) return '#d97706'; // ámbar (3)
        return '#b91c1c';          // rojo (1-2)
    });
    scoreLegendData[metric]={labels:d.labels,values:d.values,bgFull:bgFull,bdFull:bdFull};

    // Bind botones
    var legend=document.getElementById('score-legend-'+metric);
    if(!legend) return;
    legend.querySelectorAll('.score-band-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
            legend.querySelectorAll('.score-band-btn').forEach(function(b){b.classList.remove('active');});
            btn.classList.add('active');
            var min=btn.dataset.min, max=btn.dataset.max;
            applyScoreFilter(metric, min===''?null:parseFloat(min), max===''?null:parseFloat(max));
        });
    });
}

function applyScoreFilter(metric, min, max){
    var chart=charts[metric];
    if(!chart||!scoreLegendData[metric]) return;
    var data=scoreLegendData[metric];
    var ds=chart.data.datasets[0];

    if(min===null && max===null){
        // Mostrar todos
        ds.backgroundColor=data.bgFull.slice();
        ds.borderColor=data.bdFull.slice();
        chart.data.labels=data.labels.slice();
        ds.data=data.values.slice();
    } else {
        // Filtrar: solo índices donde el valor entra en la banda
        var filtLabels=[], filtValues=[], filtBg=[], filtBd=[];
        data.values.forEach(function(v,i){
            var s=parseFloat(v);
            var inBand=(min===null||s>=min)&&(max===null||s<=max);
            if(inBand){
                filtLabels.push(data.labels[i]);
                filtValues.push(v);
                filtBg.push(data.bgFull[i]);
                filtBd.push(data.bdFull[i]);
            }
        });
        chart.data.labels=filtLabels;
        ds.data=filtValues;
        ds.backgroundColor=filtBg;
        ds.borderColor=filtBd;
    }
    chart.update();
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
        .catch(function(e){console.error('loadMetric['+metric+']',e); showToast('Error cargando '+metric,'info');})
        .finally(function(){if(loading) loading.classList.remove('active');});
}

function loadAllMetrics(){
    <?php foreach ($graficas as [$gid]): ?>loadMetric('<?= $gid ?>');<?php endforeach; ?>
}

function limpiarFiltros(metric){
    currentFilters[metric]={};
    document.querySelectorAll('[data-chart="'+metric+'"]').forEach(function(el){
        if(el.tomselect) el.tomselect.clear();
    });
    loadMetric(metric); showToast('Filtros limpiados','success');
}

/* ── CASCADA DE FILTROS ── */
var CASCADE_COORD=<?= json_encode($CAMPOS_COORD, JSON_UNESCAPED_UNICODE) ?>;

function updateDownstreamFilters(metric,changedField){
    var idx=CASCADE_COORD.indexOf(changedField);
    if(idx===-1||idx>=CASCADE_COORD.length-1) return;
    var active={};
    for(var i=0;i<=idx;i++){
        var f=CASCADE_COORD[i];
        if(currentFilters[metric]&&currentFilters[metric][f]&&currentFilters[metric][f].length)
            active[f]=currentFilters[metric][f];
    }
    var parts=['ajax_filters=1'];
    Object.keys(active).forEach(function(k){ active[k].forEach(function(v){ parts.push('filter_'+k+'%5B%5D='+encodeURIComponent(v)); }); });
    fetch('estadisticas_coordinadores.php?'+parts.join('&'))
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.ok||!data.filters) return;
            for(var j=idx+1;j<CASCADE_COORD.length;j++){
                var dep=CASCADE_COORD[j], newOpts=data.filters[dep]||[], key=metric+'_'+dep, ts=tomInstances[key];
                if(!ts) continue;
                var cur=ts.getValue(); ts.clear(true); ts.clearOptions();
                ts.addOptions(newOpts.map(function(o){return{value:o,text:o};}));
                var valid=(Array.isArray(cur)?cur:[cur]).filter(function(v){return newOpts.indexOf(v)!==-1;});
                if(valid.length){ts.setValue(valid,true); currentFilters[metric][dep]=valid;}
                else delete currentFilters[metric][dep];
                ts.refreshOptions(false);
            }
        }).catch(function(e){console.error('cascade error:',e);});
}

/* ── TOM SELECT ── */
function populateSelectOptions(){
    document.querySelectorAll('.chart-filter').forEach(function(sel){
        var campo=sel.dataset.field;
        var opts=(availableFilters&&availableFilters[campo])?availableFilters[campo]:[];
        sel.innerHTML=opts.map(function(v){return'<option value="'+v.replace(/"/g,'&quot;')+'">'+v+'</option>';}).join('');
    });
}

function initializeTomSelects(){
    document.querySelectorAll('.chart-filter').forEach(function(el){
        var metric=el.dataset.chart, campo=el.dataset.field, key=metric+'_'+campo;
        if(tomInstances[key]) tomInstances[key].destroy();
        var ts=new TomSelect(el,{
            plugins:['remove_button'],create:false,hideSelected:true,
            closeAfterSelect:false,placeholder:'Todos...',maxItems:null,dropdownParent:'body',
            onChange:function(vals){
                vals=Array.isArray(vals)?vals:(vals?[vals]:[]);
                if(!currentFilters[metric]) currentFilters[metric]={};
                if(vals.length>0) currentFilters[metric][campo]=vals;
                else delete currentFilters[metric][campo];
                loadMetric(metric); updateDownstreamFilters(metric,campo);
            }
        });
        tomInstances[key]=ts;
    });
}

async function initializeApp(){
    Object.keys(defaultRanges).forEach(function(m){ syncButtons(m, chartRanges[m]||defaultRanges[m]); });
    try{
        var resp=await fetch('estadisticas_coordinadores.php?ajax_filters=1');
        var data=await resp.json();
        if(!data.ok) throw new Error(data.msg||'Error');
        availableFilters=data.filters; populateSelectOptions(); initializeTomSelects();
    } catch(e){ console.error('Error cargando filtros:',e); populateSelectOptions(); initializeTomSelects(); }

    // Pre-cargar mapa de colores con rango histórico para tener TODOS los planes
    try {
        var rColor=await fetch('estadisticas_coordinadores.php?ajax=1&metric=incidencias_valoracion&range=historico');
        var dColor=await rColor.json();
        if(dColor.ok && dColor.labels && dColor.labels.length>0){
            var base=PALETTES[palKey('incidencias_valoracion')]||PALETTE;
            dColor.labels.forEach(function(lbl,i){ labelColorMap[lbl]=i%base.length; });
        }
    } catch(e){ console.warn('Color map preload error:',e); }

    loadAllMetrics();
}
document.addEventListener('DOMContentLoaded', initializeApp);
setInterval(loadAllMetrics, 60000); // refresco cada minuto

/* ── MODAL EXPORTAR ── */
function openExportModal(){
    var today = new Date().toISOString().slice(0,10);
    var dtFrom = document.getElementById('exportDateFrom');
    var dtTo   = document.getElementById('exportDateTo');
    if(dtTo && !dtTo.value) dtTo.value = today;
    var overlay = document.getElementById('exportModal');
    overlay.style.display='flex';
    overlay.classList.add('open');
}
function closeExportModal(){
    var overlay = document.getElementById('exportModal');
    overlay.style.display='none';
    overlay.classList.remove('open');
    // Resetear preset activo
    document.querySelectorAll('.preset-btn.active-preset').forEach(function(b){ b.classList.remove('active-preset'); });
}
document.getElementById('exportModal').addEventListener('click',function(e){ if(e.target===this) closeExportModal(); });

function toggleAllExportChecks(val){
    document.querySelectorAll('#exportModal input[type=checkbox]').forEach(function(cb){ cb.checked=val; });
}

function setExportDatePreset(preset, btn){
    var dtFrom = document.getElementById('exportDateFrom');
    var dtTo   = document.getElementById('exportDateTo');
    // Marcar botón activo
    document.querySelectorAll('.preset-btn').forEach(function(b){ b.classList.remove('active-preset'); });
    if(btn) btn.classList.add('active-preset');
    var today  = new Date();
    var fmt    = function(d){ return d.toISOString().slice(0,10); };
    var from   = new Date(today);
    dtTo.value = fmt(today);
    switch(preset){
        case 'today': from=new Date(today); break;
        case 'week':  from=new Date(today); from.setDate(from.getDate()-6); break;
        case 'month': from=new Date(today); from.setDate(from.getDate()-29); break;
        case 'year':  from=new Date(today); from.setFullYear(from.getFullYear()-1); break;
        case 'all':   dtFrom.value=''; dtTo.value=''; return;
    }
    dtFrom.value = fmt(from);
}

function buildExportUrl(metric){
    var range   = chartRanges[metric] || defaultRanges[metric] || 'historico';
    var dateFrom = (document.getElementById('exportDateFrom')||{}).value || '';
    var dateTo   = (document.getElementById('exportDateTo')||{}).value   || '';
    var parts = ['ajax=1','metric='+encodeURIComponent(metric)];
    if(dateFrom || dateTo){
        if(dateFrom) parts.push('date_from='+encodeURIComponent(dateFrom));
        if(dateTo)   parts.push('date_to='+encodeURIComponent(dateTo));
    } else {
        parts.push('range='+encodeURIComponent(range));
    }
    // Aplicar también los filtros activos de la gráfica
    var f = currentFilters[metric]||{};
    Object.keys(f).forEach(function(c){
        (Array.isArray(f[c])?f[c]:[f[c]]).forEach(function(v){
            if(v!==null&&v!==undefined&&String(v).trim()!=='')
                parts.push('filter_'+c+'%5B%5D='+encodeURIComponent(v));
        });
    });
    return 'estadisticas_coordinadores.php?'+parts.join('&');
}

function triggerDownload(url, filename){
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(function(){ document.body.removeChild(a); }, 300);
}

function exportCharts(type){
    var metrics = Array.from(document.querySelectorAll('#exportModal input[type=checkbox]:checked')).map(function(cb){return cb.value;});
    if(!metrics.length){ showToast('Selecciona al menos una gráfica','info'); return; }

    var dateFrom = (document.getElementById('exportDateFrom')||{}).value || '';
    var dateTo   = (document.getElementById('exportDateTo')||{}).value   || '';
    var rangoLbl = (dateFrom||dateTo) ? ((dateFrom||'inicio')+' a '+(dateTo||'hoy')) : 'Histórico';

    closeExportModal();
    showToast('Generando imágenes... espera un momento', 'info');

    function captureAndZip(JSZip){
        var zip = new JSZip();
        var PAD = 90;
        var done = 0;

        function captureMetric(metric){
            var canvas = document.getElementById(metric+'Chart');
            if(!canvas){ done++; if(done===metrics.length) saveZip(zip, JSZip); return; }
            var titleEl = document.querySelector('#box-'+metric+' .chart-title');
            var titleTxt = titleEl ? titleEl.textContent.trim() : metric;
            var nc = document.createElement('canvas');
            var cw = Math.max(canvas.width, 800);
            var ch = canvas.height;
            nc.width  = cw;
            nc.height = ch + PAD;
            var nctx = nc.getContext('2d');
            nctx.fillStyle = '#ffffff';
            nctx.fillRect(0, 0, nc.width, nc.height);
            nctx.fillStyle = '#0d9488';
            nctx.fillRect(0, 0, nc.width, PAD - 10);
            nctx.fillStyle = '#ffffff';
            nctx.font = 'bold 15px Arial, sans-serif';
            nctx.fillText('Estadísticas Coordinadores — Grupo ATU', 16, 24);
            nctx.font = '13px Arial, sans-serif';
            nctx.fillText(titleTxt, 16, 46);
            nctx.font = '11px Arial, sans-serif';
            nctx.fillStyle = 'rgba(255,255,255,0.85)';
            nctx.fillText('Período: ' + rangoLbl, 16, 66);
            if(cw !== canvas.width){
                nctx.drawImage(canvas, 0, PAD, cw, ch);
            } else {
                nctx.drawImage(canvas, 0, PAD);
            }
            nc.toBlob(function(blob){
                var reader = new FileReader();
                reader.onload = function(){
                    var b64 = reader.result.split(',')[1];
                    zip.file('coordinadores_'+metric+'.jpg', b64, {base64:true});
                    done++;
                    if(done === metrics.length) saveZip(zip, JSZip);
                };
                reader.readAsDataURL(blob);
            }, 'image/jpeg', 0.92);
        }

        var loaded = 0;
        metrics.forEach(function(metric){
            var url = buildExportUrl(metric);
            fetch(url)
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if(d.ok) createChart(metric, d);
                    setTimeout(function(){
                        requestAnimationFrame(function(){
                            requestAnimationFrame(function(){
                                loaded++;
                                captureMetric(metric);
                            });
                        });
                    }, 800);
                })
                .catch(function(){
                    loaded++;
                    captureMetric(metric);
                });
        });
    }

    function saveZip(zip, JSZip){
        zip.generateAsync({type:'blob'}).then(function(blob){
            var url = URL.createObjectURL(blob);
            triggerDownload(url, 'coordinadores_graficas_'+(dateFrom||'historico')+'.zip');
            setTimeout(function(){ URL.revokeObjectURL(url); }, 3000);
            showToast('✓ ZIP con '+metrics.length+' gráfica(s) descargado', 'success');
        });
    }

    if(typeof JSZip !== 'undefined'){
        captureAndZip(JSZip);
    } else {
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
        s.onload  = function(){ captureAndZip(window.JSZip); };
        s.onerror = function(){ showToast('Error cargando JSZip','info'); };
        document.head.appendChild(s);
    }
}
</script>
</body>
</html>