<?php
/**
 * =============================================================================
 * estadisticas.php — Panel de estadísticas interactivo de incidencias
 * =============================================================================
 *
 * Propósito:
 *   Página web (SPA) y API AJAX que muestra gráficas interactivas sobre los
 *   tickets de incidencias registrados en osTicket, con filtros en cascada,
 *   selección de rango temporal y exportación PNG/CSV.
 *
 * Modos de operación (según parámetros GET):
 *   Sin parámetros         → Renderiza la página HTML completa con Chart.js
 *   ?ajax=1&metric=X       → Devuelve JSON con datos para una gráfica concreta
 *   ?ajax_filters=1        → Devuelve JSON con las opciones disponibles para
 *                            los selectores de filtro (en cascada)
 *   ?ajax_detail=1&...     → Devuelve JSON con el detalle de una barra/sector
 *                            al hacer clic en una gráfica
 *
 * Métricas disponibles:
 *   - fecha       → Evolución temporal de tickets (línea)
 *   - plan        → Distribución por plan formativo (barras horizontales)
 *   - curso       → Top cursos con más incidencias (barras horizontales)
 *   - incidencia  → Top tipos de incidencia (barras horizontales)
 *   - plan_pct    → Porcentaje por plan (doughnut + lista)
 *
 * Filtros en cascada:
 *   Los 4 filtros (plan, sector, curso, incidencia) se populan dinámicamente
 *   y se reducen según las selecciones previas para evitar combinaciones vacías.
 *
 * Mantenimiento automático (solo en carga de página completa):
 *   - Inserta filas faltantes en ost_ticket__cdata
 *   - Sincroniza campos del formulario (form_id=18) a las columnas cdata
 *   - Distribuye el campo sector en las columnas sector_xxx según el plan
 *   - Normaliza valores JSON (formato osTicket) a texto plano
 *
 * Dependencias frontend:
 *   - Chart.js 4.4.0 (gráficas)
 *   - Tom Select 2.3.1 (filtros multi-select con búsqueda)
 *   - Font Awesome 6.4.0 (iconos)
 *   - Google Fonts Inter
 *
 * Dependencias backend:
 *   - PHP 8.0+, extensión mysqli
 *   - include/ost-config.php
 *
 * @package    GrupoATU\Incidencias
 * @author     Equipo de desarrollo Grupo ATU
 * @version    3.0
 * =============================================================================
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
            $_SESSION['estadisticas_auth'] = true;
            $_SESSION['estadisticas_user'] = $user;
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $login_error = 'Usuario o contraseña incorrectos.';
    }
}

// Verificar si está logueado
$is_logged_in = !empty($_SESSION['estadisticas_auth']) || !empty($_SESSION['admin_sso']);

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

/** 
 * (AQUÍ CONTINÚA TU CÓDIGO ORIGINAL CON LOS COMENTARIOS Y EL ini_set)
 */



ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_error.log');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

define('ROOT_DIR', '/var/www/vhosts/incidencias.grupoatu.com/httpdocs/osticket/');
define('INCLUDE_DIR', ROOT_DIR . 'include/');
$config_path = INCLUDE_DIR . 'ost-config.php';

if (!file_exists($config_path)) {
    die('No se encuentra ost-config.php');
}
require_once $config_path;

if (!defined('DBHOST') || !defined('DBUSER') || !defined('DBPASS') || !defined('DBNAME')) {
    die('Falta configuración de BD en ost-config.php');
}

// ======================= INICIO DE LA MODIFICACIÓN =======================

// 1. CONDICIÓN PARA EJECUTAR EL MANTENIMIENTO
// Este bloque de código pesado y propenso a errores solo se ejecutará si NO es una petición AJAX.
if (!isset($_GET['ajax']) && !isset($_GET['ajax_filters']) && !isset($_GET['ajax_detail'])) {
    
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        // Se establece una conexión temporal solo para el mantenimiento
        $conexion_mantenimiento = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        $conexion_mantenimiento->set_charset('utf8mb4');

        /* Crear filas faltantes en cdata */
        $conexion_mantenimiento->query("
        INSERT IGNORE INTO ost_ticket__cdata (ticket_id)
        SELECT ticket_id
        FROM ost_ticket
        ");

        /* Rellenar PLAN vacío desde formulario osTicket */
        $conexion_mantenimiento->query("
        UPDATE ost_ticket__cdata cd
        JOIN ost_form_entry fe
            ON fe.object_id = cd.ticket_id
           AND fe.object_type = 'T'
           AND fe.form_id = 18
        JOIN ost_form_field ff
            ON ff.form_id = 18
           AND ff.name = 'plan'
        JOIN ost_form_entry_values fv
            ON fv.entry_id = fe.id
           AND fv.field_id = ff.id
        LEFT JOIN ost_list_items li
            ON li.id = CAST(SUBSTRING_INDEX(fv.value, ',', 1) AS UNSIGNED)
        SET cd.plan = COALESCE(NULLIF(TRIM(li.value), ''), fv.value)
        WHERE cd.plan IS NULL
           OR TRIM(cd.plan) = ''
        ");

        /* Distribuir sector en columnas específicas ... */
        foreach ([
            'asturias'                                         => 'sector_asturias',
            'castilla|cyl|le%c3%b3n|leon'                     => 'sector_cyl',
            'labora|valenciana|valencia|c\\.\\s*valenciana'   => 'sector_labora',
            'estatal'                                          => 'sector_estatal',
        ] as $patron => $col) {
            $likes = [];
            foreach (explode('|', $patron) as $p) {
                $p = $conexion_mantenimiento->real_escape_string(str_replace(['%c3%b3n','c\\.\\s*valenciana'], ['ó','c. valenciana'], $p));
                $likes[] = "LOWER(COALESCE(NULLIF(TRIM(li_plan.value),''), TRIM(cd.plan),'' )) LIKE '%{$p}%'";
            }
            $likeClause = implode(' OR ', $likes);
            $conexion_mantenimiento->query("
                UPDATE ost_ticket__cdata cd
                LEFT JOIN ost_list_items li_plan
                    ON cd.plan REGEXP '^[0-9]+'
                   AND li_plan.id = CAST(SUBSTRING_INDEX(cd.plan, ',', 1) AS UNSIGNED)
                LEFT JOIN ost_list_items li_sec
                    ON cd.sector REGEXP '^[0-9]+'
                   AND li_sec.id = CAST(SUBSTRING_INDEX(cd.sector, ',', 1) AS UNSIGNED)
                SET cd.`{$col}` = COALESCE(NULLIF(TRIM(li_sec.value),''), NULLIF(TRIM(cd.sector),''))
                WHERE ($likeClause)
                  AND cd.sector IS NOT NULL
                  AND TRIM(cd.sector) != ''
                  AND LOWER(TRIM(cd.sector)) NOT IN ('false','null')
                  AND (cd.`{$col}` IS NULL OR TRIM(cd.`{$col}`) = '')
            ");
        }
        
        $conexion_mantenimiento->close(); // Cerramos la conexión de mantenimiento

    } catch (Throwable $e) {
        // En lugar de 'die', solo lo registramos para no romper la página.
        error_log('ERROR en script de mantenimiento de datos: ' . $e->getMessage());
    }
}

// 2. CONEXIÓN PRINCIPAL PARA LA APLICACIÓN
// Esta conexión se establece SIEMPRE, para que las peticiones AJAX y la carga de la página la tengan disponible.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexion = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
    $conexion->set_charset('utf8mb4');
} catch (Throwable $e) {
    // Si la conexión principal falla, es un error fatal.
    // Respondemos con JSON si es una petición AJAX para evitar el error de "token 'E'".
    if (isset($_GET['ajax']) || isset($_GET['ajax_filters']) || isset($_GET['ajax_detail'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Error crítico de conexión a la Base de Datos.']);
        exit;
    }
    // Si no es AJAX, mostramos el error en pantalla.
    die('Error crítico de conexión a la BD: ' . htmlspecialchars($e->getMessage()));
}

// ======================= FIN DE LA MODIFICACIÓN =======================

// A partir de aquí sigue el resto de tu código PHP (las funciones, etc.)
// Ejemplo:
// function distribuir_sector_cdata(...) { ... }
// ...



/**
 * Distribuye el valor del campo `sector` en la columna sector_xxx correspondiente
 * según el plan del ticket. Se usa para tickets creados desde osTicket que no pasan
 * por incidencias.php y por tanto no tienen las columnas sector_xxx rellenas.
 */
function distribuir_sector_cdata(mysqli $db, int $ticket_id): void {
    $stmt = $db->prepare("
        SELECT
            COALESCE(NULLIF(TRIM(li_plan.value),''), TRIM(cd.plan), '')  AS plan_text,
            COALESCE(NULLIF(TRIM(li_sec.value), ''), TRIM(cd.sector), '') AS sector_text,
            TRIM(COALESCE(cd.sector_asturias,'')) AS sector_asturias,
            TRIM(COALESCE(cd.sector_cyl,''))      AS sector_cyl,
            TRIM(COALESCE(cd.sector_labora,''))   AS sector_labora,
            TRIM(COALESCE(cd.sector_estatal,''))  AS sector_estatal
        FROM ost_ticket__cdata cd
        LEFT JOIN ost_list_items li_plan
            ON cd.plan REGEXP '^[0-9]+'
           AND li_plan.id = CAST(SUBSTRING_INDEX(cd.plan,',',1) AS UNSIGNED)
        LEFT JOIN ost_list_items li_sec
            ON cd.sector REGEXP '^[0-9]+'
           AND li_sec.id = CAST(SUBSTRING_INDEX(cd.sector,',',1) AS UNSIGNED)
        WHERE cd.ticket_id = ?
        LIMIT 1
    ");
    if (!$stmt) return;
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return;

    $planText   = trim((string)($row['plan_text']   ?? ''));
    $sectorText = trim((string)($row['sector_text'] ?? ''));

    if ($sectorText === '' || strtolower($sectorText) === 'false' || strtolower($sectorText) === 'null') return;

    $planLow = mb_strtolower($planText, 'UTF-8');

    // Determinar columna destino y verificar que está vacía
    $col = '';
    if (str_contains($planLow, 'asturias') && $row['sector_asturias'] === '') {
        $col = 'sector_asturias';
    } elseif ((str_contains($planLow, 'castilla') || str_contains($planLow, 'cyl')
               || str_contains($planLow, 'le') && str_contains($planLow, 'n')) // León/leon
               && $row['sector_cyl'] === '') {
        $col = 'sector_cyl';
    } elseif ((str_contains($planLow, 'labora') || str_contains($planLow, 'valenciana')
               || str_contains($planLow, 'valencia'))
               && $row['sector_labora'] === '') {
        $col = 'sector_labora';
    } elseif (str_contains($planLow, 'estatal') && $row['sector_estatal'] === '') {
        $col = 'sector_estatal';
    }

    if ($col === '') return;

    $stmt = $db->prepare("UPDATE ost_ticket__cdata SET `$col` = ? WHERE ticket_id = ?");
    if (!$stmt) return;
    $stmt->bind_param('si', $sectorText, $ticket_id);
    $stmt->execute();
    $stmt->close();
}

function sincronizarTicketsNuevos(mysqli $db): void {
    $sql = "
        SELECT t.ticket_id
        FROM ost_ticket t
        LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id = t.ticket_id
        WHERE cd.ticket_id IS NULL
           OR cd.plan IS NULL
           OR TRIM(cd.plan) = ''
           OR cd.subject IS NULL
           OR TRIM(COALESCE(cd.plan, '')) LIKE '{%'
           OR TRIM(COALESCE(cd.sector, '')) LIKE '{%'
           OR TRIM(COALESCE(cd.incidencia, '')) LIKE '{%'
           OR TRIM(COALESCE(cd.curso, '')) LIKE '{%'
        ORDER BY t.ticket_id DESC
        LIMIT 200
    ";

    $res = $db->query($sql);
    if (!$res) return;

    while ($row = $res->fetch_assoc()) {
        $tid = (int)$row['ticket_id'];

        asegurar_cdata($db, $tid);
        sync_form_to_cdata($db, $tid, 18);
        distribuir_sector_cdata($db, $tid);
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


function sync_form_to_cdata(mysqli $db, int $ticket_id, int $form_id = 18): void
{
    /* =====================================================
       1. SACAR CAMPOS DEL FORMULARIO
    ===================================================== */
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
        $nombre = trim((string)$row['name']);
        $valor  = trim((string)$row['value']);

        if ($nombre !== '') {
            $campos[$nombre] = $valor;
        }
    }

    $stmt->close();

    if (empty($campos)) return;

    /* =====================================================
       2. COLUMNAS VÁLIDAS EN ost_ticket__cdata
    ===================================================== */
    $columnas_validas = [
        'subject',
        'plan',
        'sector',
        'sector_labora',
        'sector_cyl',
        'sector_asturias',
        'sector_estatal',
        'nombreAlu',
        'apellidosAlu',
        'tutor',
        'empresa',
        'accion',
        'grupo',
        'curso',
        'incidencia',
        'datos',
        'detalles',
        'dificultad',
        'razon',
        'motivo',
        'archivo'
    ];

    /* =====================================================
       3. DECODIFICAR JSON DE osTicket
       Ejemplo: {"15":"Asturias"} => Asturias
    ===================================================== */
    foreach ($campos as $campo => $valor) {
        $campos[$campo] = decode_json_value($valor);
    }

    /* =====================================================
       4. CONVERTIR IDS DE LISTAS A TEXTO
       Ejemplo: 15 => Asturias
    ===================================================== */
    $campos_lista = ['plan', 'incidencia', 'sector'];

    foreach ($campos_lista as $campoLista) {
        if (!isset($campos[$campoLista])) continue;

        $valor = trim((string)$campos[$campoLista]);
        if ($valor === '') continue;

        if (preg_match('/^\d+(,\d+)?$/', $valor)) {
            $idLista = (int)explode(',', $valor)[0];

            if ($idLista > 0) {
                $sqlLista = "SELECT value FROM ost_list_items WHERE id = ? LIMIT 1";
                $stmtLista = $db->prepare($sqlLista);

                if ($stmtLista) {
                    $stmtLista->bind_param('i', $idLista);
                    $stmtLista->execute();

                    $rLista = $stmtLista->get_result()->fetch_assoc();

                    if ($rLista && trim((string)$rLista['value']) !== '') {
                        $campos[$campoLista] = trim((string)$rLista['value']);
                    }

                    $stmtLista->close();
                }
            }
        }
    }

    /* =====================================================
       5. PREPARAR UPDATE DINÁMICO
    ===================================================== */
    $sets  = [];
    $types = '';
    $vals  = [];

    foreach ($campos as $campo => $valor) {
        if (!in_array($campo, $columnas_validas, true)) {
            continue;
        }

        $sets[]  = "`$campo` = ?";
        $types  .= 's';
        $vals[]  = $valor;
    }

    if (empty($sets)) return;

    /* =====================================================
       6. EJECUTAR UPDATE
    ===================================================== */
    $sqlUpdate = "
        UPDATE ost_ticket__cdata
        SET " . implode(', ', $sets) . "
        WHERE ticket_id = ?
    ";

    $types .= 'i';
    $vals[] = $ticket_id;

    $stmt = $db->prepare($sqlUpdate);
    if (!$stmt) {
        error_log("Error prepare UPDATE: " . $db->error);
        return;
    }

    $refs = [];
    foreach ($vals as $k => $v) {
        $refs[$k] = &$vals[$k];
    }

    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
        error_log("Error execute UPDATE: " . $stmt->error);
    }

    $stmt->close();
}
sincronizarTicketsNuevos($conexion);
normalizar_json_cdata_existente($conexion, 500);

function cdata_col_exists($conexion, $col) {
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        try {
            $res = $conexion->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ost_ticket__cdata'");
            if ($res) { while ($row = $res->fetch_assoc()) { $cols[] = $row['COLUMN_NAME']; } }
        } catch (Throwable $e) {}
    }
    return in_array($col, $cols, true);
}

function decode_json_value($valor) {
    $valor = trim((string)$valor);

    if ($valor === '') return '';

    $decoded = json_decode($valor, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
        $first = reset($decoded);

        if (is_array($first)) {
            $first = reset($first);
        }

        return trim((string)$first);
    }

    return $valor;
}

function normalizar_json_cdata_existente(mysqli $db, int $limit = 500): void
{
    $cols = [
        'plan',
        'sector',
        'incidencia',
        'curso',
        'sector_labora',
        'sector_cyl',
        'sector_asturias',
        'sector_estatal'
    ];

    $colsOk = [];
    foreach ($cols as $c) {
        if (cdata_col_exists($db, $c)) {
            $colsOk[] = $c;
        }
    }

    if (empty($colsOk)) return;

    $whereParts = [];
    foreach ($colsOk as $c) {
        $whereParts[] = "TRIM(COALESCE(`$c`, '')) LIKE '{%'";
    }

    $sql = "
        SELECT ticket_id, `" . implode("`, `", $colsOk) . "`
        FROM ost_ticket__cdata
        WHERE " . implode(' OR ', $whereParts) . "
        ORDER BY ticket_id DESC
        LIMIT " . (int)$limit . "
    ";

    $res = $db->query($sql);
    if (!$res) return;

    while ($row = $res->fetch_assoc()) {
        $ticket_id = (int)$row['ticket_id'];

        $sets  = [];
        $types = '';
        $vals  = [];

        foreach ($colsOk as $c) {
            if (!array_key_exists($c, $row)) continue;

            $old = trim((string)$row[$c]);
            $new = decode_json_value($old);

            if ($old !== '' && $new !== $old) {
                $sets[] = "`$c` = ?";
                $types .= 's';
                $vals[] = $new;
            }
        }

        if (empty($sets)) continue;

        $sqlUpdate = "
            UPDATE ost_ticket__cdata
            SET " . implode(', ', $sets) . "
            WHERE ticket_id = ?
        ";

        $types .= 'i';
        $vals[] = $ticket_id;

        $stmt = $db->prepare($sqlUpdate);
        if (!$stmt) continue;

        $refs = [];
        foreach ($vals as $k => $v) {
            $refs[$k] = &$vals[$k];
        }

        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);

        $stmt->execute();
        $stmt->close();
    }
}

function normalizar_texto_estadistica($texto) {
    $texto = decode_json_value($texto);
	$texto = trim((string)$texto);
    if ($texto === '') return '';
    $map = ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a','É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e','Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i','Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o','Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u','ñ'=>'n','Ñ'=>'n'];
    $texto = strtr($texto, $map);
    $texto = preg_replace('/[.\,\;\:\-\/\\\\\(\)\[\]\{\}\|]+/u', ' ', $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);
    return mb_strtolower(trim($texto), 'UTF-8');
}

function limpiar_label_visible($texto) {
    $texto = decode_json_value($texto);
    $texto = trim((string)$texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);

    return $texto === '' ? 'Sin dato' : $texto;
}

function resolver_label_plan($row) {
    $plan = trim((string)($row['plan_label'] ?? ''));
    if ($plan !== '') return $plan;

    foreach ([
        'sector_labora',
        'sector_cyl',
        'sector_asturias',
        'sector_estatal'
    ] as $c) {

        if (!isset($row[$c])) continue;

        $v = trim(html_entity_decode((string)$row[$c], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($v !== '' && strtolower($v) !== 'null') {
            return $v;
        }
    }

    return 'Sin dato';
}

function agrupar_filas_normalizadas($rows, $fieldResolver, $limit = 0) {
    $grouped = [];
    foreach ($rows as $row) {
        $labelOriginal = $fieldResolver($row);
        $labelVisible = limpiar_label_visible($labelOriginal);
        $clave = normalizar_texto_estadistica($labelVisible);
        if ($clave === '') $clave = 'sin dato';

        if (!isset($grouped[$clave])) {
            $grouped[$clave] = ['label' => $labelVisible, 'total' => 0];
        }
        $grouped[$clave]['total']++;
        if (mb_strlen($labelVisible, 'UTF-8') > mb_strlen($grouped[$clave]['label'], 'UTF-8') && $labelVisible !== 'Sin dato') {
            $grouped[$clave]['label'] = $labelVisible;
        }
    }
    $rowsOut = array_values($grouped);
    usort($rowsOut, function($a, $b){
        return $b['total'] <=> $a['total'] ?: strcasecmp($a['label'], $b['label']);
    });
    if ($limit > 0) $rowsOut = array_slice($rowsOut, 0, $limit);
    return $rowsOut;
}

function rango_fechas($range) {
    $now     = new DateTimeImmutable('now');
    // Fin del día actual (23:59:59) para no perder tickets creados más tarde durante el día
    $finDia  = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    switch ($range) {
        case 'day':     return [$now->setTime(0, 0, 0)->format('Y-m-d H:i:s'), $finDia, 'Día'];
        case 'week':    return [$now->modify('-6 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'), $finDia, 'Última semana'];
        case 'month':   return [$now->modify('-29 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'), $finDia, 'Último mes'];
        case 'quarter': return [$now->modify('-89 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'), $finDia, 'Último trimestre'];
        case 'year':    return [$now->modify('-364 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'), $finDia, '1 año'];
        default:        return [null, null, 'Histórico'];
    }
}

function render_range_buttons($metric) {
    $ranges = ['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'quarter' => 'Trimestre', 'year' => '1 año', 'historico' => 'Histórico'];
    $html = '<div class="range-group">';
    foreach ($ranges as $key => $label) {
        $html .= '<button type="button" class="range-btn" data-metric="' . htmlspecialchars($metric, ENT_QUOTES) . '" data-range="' . $key . '">' . $label . '</button>';
    }
    return $html . '</div>';
}

// FILTROS DINÁMICOS EN CASCADA: filtra por todos los parámetros recibidos
function get_filter_values($conexion, $applied_filters = []) {
    $where = ' WHERE t.ticket_id IS NOT NULL AND t.topic_id != 13 ';

    // Helper para escapar lista de valores
    $makeList = function(array $arr) use ($conexion) {
        return implode(',', array_map(function($v) use ($conexion){
            return "'" . $conexion->real_escape_string($v) . "'";
        }, $arr));
    };

    if (!empty($applied_filters['plan'])) {
        $list = $makeList((array)$applied_filters['plan']);
        $where .= " AND (
            (cd.plan REGEXP '^[0-9]+' AND li_plan.value IN ($list))
            OR (cd.plan NOT REGEXP '^[0-9]+' AND cd.plan IN ($list))
        )";
    }

    if (!empty($applied_filters['sector'])) {
        $list = $makeList((array)$applied_filters['sector']);
        $where .= " AND (
            cd.sector_labora IN ($list) OR cd.sector_cyl IN ($list)
            OR cd.sector_asturias IN ($list) OR cd.sector_estatal IN ($list)
        )";
    }

    if (!empty($applied_filters['curso'])) {
        $list = $makeList((array)$applied_filters['curso']);
        $where .= " AND cd.curso IN ($list)";
    }

    if (!empty($applied_filters['incidencia'])) {
        $list = $makeList((array)$applied_filters['incidencia']);
        $where .= " AND (
            (cd.incidencia REGEXP '^[0-9]+' AND li_incidencia.value IN ($list))
            OR (cd.incidencia NOT REGEXP '^[0-9]+' AND cd.incidencia IN ($list))
        )";
    }

    $sql = "
SELECT DISTINCT
    CASE WHEN cd.plan REGEXP '^[0-9]+' THEN COALESCE(NULLIF(TRIM(li_plan.value), ''), SUBSTRING_INDEX(cd.plan, ',', 1)) ELSE COALESCE(NULLIF(TRIM(cd.plan), ''), '') END AS plan_label,
    COALESCE(NULLIF(TRIM(cd.curso), ''), '') AS curso_raw,
    CASE WHEN cd.incidencia REGEXP '^[0-9]+' THEN COALESCE(NULLIF(TRIM(li_incidencia.value), ''), SUBSTRING_INDEX(cd.incidencia, ',', 1)) ELSE COALESCE(NULLIF(TRIM(cd.incidencia), ''), '') END AS incidencia_label,
    COALESCE(NULLIF(TRIM(cd.sector_labora), ''), '') AS sector_labora,
    COALESCE(NULLIF(TRIM(cd.sector_cyl), ''), '') AS sector_cyl,
    COALESCE(NULLIF(TRIM(cd.sector_asturias), ''), '') AS sector_asturias,
    COALESCE(NULLIF(TRIM(cd.sector_estatal), ''), '') AS sector_estatal
FROM ost_ticket__cdata cd
JOIN ost_ticket t ON t.ticket_id = cd.ticket_id AND t.topic_id != 13
LEFT JOIN ost_list_items li_plan ON cd.plan REGEXP '^[0-9]+' AND li_plan.id = CAST(SUBSTRING_INDEX(cd.plan, ',', 1) AS UNSIGNED)
LEFT JOIN ost_list_items li_incidencia ON cd.incidencia REGEXP '^[0-9]+' AND li_incidencia.id = CAST(SUBSTRING_INDEX(cd.incidencia, ',', 1) AS UNSIGNED)
$where";

    $res     = $conexion->query($sql);
    $filters = ['plan' => [], 'sector' => [], 'curso' => [], 'incidencia' => []];

    $clean = function($v) {
        $v   = trim(decode_json_value($v));
        $low = mb_strtolower($v, 'UTF-8');
        return ($v === '' || $low === 'null' || $low === 'false') ? '' : $v;
    };

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (($p = $clean($row['plan_label'] ?? ''))      !== '') $filters['plan'][$p]       = $p;
            if (($c = $clean($row['curso_raw'] ?? ''))       !== '') $filters['curso'][$c]      = $c;
            if (($i = $clean($row['incidencia_label'] ?? '')) !== '') $filters['incidencia'][$i] = $i;
            foreach (['sector_labora','sector_cyl','sector_asturias','sector_estatal'] as $s) {
                if (($sv = $clean($row[$s] ?? '')) !== '') $filters['sector'][$sv] = $sv;
            }
        }
    }

    foreach ($filters as &$arr) { $arr = array_values($arr); sort($arr, SORT_STRING | SORT_FLAG_CASE); }
    return $filters;
}

/* === MODIFICADO: APLICA FILTROS A LA CONSULTA PRINCIPAL === */
function metric_query($conexion, $metric, $range, $filters = [], $show_all = false) {
    $valid = ['plan', 'fecha', 'curso', 'incidencia', 'plan_pct'];
    if (!in_array($metric, $valid, true)) $metric = 'plan';

    [$from, $to, $rangeLabel] = rango_fechas($range);
    $where = " WHERE t.topic_id != 13 ";

    if ($from !== null) $where .= " AND t.created >= '" . $conexion->real_escape_string($from) . "' ";
    if ($to !== null) $where .= " AND t.created <= '" . $conexion->real_escape_string($to) . "' ";

    
// Filtros personalizados (MÚLTIPLES)

if (!empty($filters['plan'])) {
    $arr = is_array($filters['plan']) ? $filters['plan'] : [$filters['plan']];
    $arr = array_map([$conexion,'real_escape_string'], $arr);
    $arr = array_map(function($v){ return "'$v'"; }, $arr);
    $list = implode(',', $arr);
    $where .= " AND (
        (cd.plan REGEXP '^[0-9]+' AND li_plan.value IN ($list))
        OR (cd.plan NOT REGEXP '^[0-9]+' AND cd.plan IN ($list))
    ) ";
}

if (!empty($filters['sector'])) {
    $arr = is_array($filters['sector']) ? $filters['sector'] : [$filters['sector']];
    $arr = array_map([$conexion,'real_escape_string'], $arr);
    $arr = array_map(function($v){ return "'$v'"; }, $arr);
    $list = implode(',', $arr);
    $where .= " AND (
        cd.sector_labora IN ($list)
        OR cd.sector_cyl IN ($list)
        OR cd.sector_asturias IN ($list)
        OR cd.sector_estatal IN ($list)
    ) ";
}

if (!empty($filters['curso'])) {
    $arr = is_array($filters['curso']) ? $filters['curso'] : [$filters['curso']];
    $arr = array_map([$conexion,'real_escape_string'], $arr);
    $arr = array_map(function($v){ return "'$v'"; }, $arr);
    $list = implode(',', $arr);
    $where .= " AND cd.curso IN ($list) ";
}

if (!empty($filters['incidencia'])) {
    $arr = is_array($filters['incidencia']) ? $filters['incidencia'] : [$filters['incidencia']];
    $arr = array_map([$conexion,'real_escape_string'], $arr);
    $arr = array_map(function($v){ return "'$v'"; }, $arr);
    $list = implode(',', $arr);
    $where .= " AND (
        (cd.incidencia REGEXP '^[0-9]+' AND li_incidencia.value IN ($list))
        OR (cd.incidencia NOT REGEXP '^[0-9]+' AND cd.incidencia IN ($list))
    ) ";
}
	
    $joins = " LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id = t.ticket_id 
               LEFT JOIN ost_list_items li_plan ON cd.plan REGEXP '^[0-9]+' AND li_plan.id = CAST(SUBSTRING_INDEX(cd.plan, ',', 1) AS UNSIGNED) 
               LEFT JOIN ost_list_items li_incidencia ON cd.incidencia REGEXP '^[0-9]+' AND li_incidencia.id = CAST(SUBSTRING_INDEX(cd.incidencia, ',', 1) AS UNSIGNED) ";

    if ($metric === 'fecha') {
        if ($range === 'day') {
            $sql = "SELECT DATE_FORMAT(t.created, '%H:00') AS label, HOUR(t.created) AS ord, COUNT(DISTINCT t.ticket_id) AS total FROM ost_ticket t $joins $where GROUP BY 1, 2 ORDER BY 2 ASC";
        } elseif ($range === 'year' || $range === 'historico') {
            $sql = "SELECT DATE_FORMAT(t.created, '%m/%Y') AS label, DATE_FORMAT(t.created, '%Y-%m') AS ord, COUNT(DISTINCT t.ticket_id) AS total FROM ost_ticket t $joins $where GROUP BY 2, 1 ORDER BY 2 ASC";
        } else {
            $sql = "SELECT DATE_FORMAT(t.created, '%d/%m') AS label, DATE(t.created) AS ord, COUNT(DISTINCT t.ticket_id) AS total FROM ost_ticket t $joins $where GROUP BY 2, 1 ORDER BY 2 ASC";
        }

        $labels = []; $values = []; $total = 0;
        $res = $conexion->query($sql);
        while ($row = $res->fetch_assoc()) { $labels[] = $row['label']; $values[] = (int)$row['total']; $total += (int)$row['total']; }
        
        return ['ok' => true, 'metric' => $metric, 'range' => $range, 'rangeLabel' => $rangeLabel, 'labels' => $labels, 'values' => $values, 'total' => $total];
    }

   $sqlBase = "
 SELECT t.ticket_id, t.created,
 COALESCE(NULLIF(TRIM(cd.plan), ''), '') AS plan_raw,
 COALESCE(NULLIF(TRIM(cd.curso), ''), '') AS curso_raw,
 COALESCE(NULLIF(TRIM(cd.incidencia), ''), '') AS incidencia_raw,
 COALESCE(NULLIF(TRIM(cd.sector_labora), ''), '') AS sector_labora, 
 COALESCE(NULLIF(TRIM(cd.sector_cyl), ''), '') AS sector_cyl,
 COALESCE(NULLIF(TRIM(cd.sector_asturias), ''), '') AS sector_asturias, 
 COALESCE(NULLIF(TRIM(cd.sector_estatal), ''), '') AS sector_estatal,
 CASE 
   WHEN cd.plan REGEXP '^[0-9]+(,[0-9]+)?$' 
     THEN COALESCE(NULLIF(TRIM(li_plan.value), ''), SUBSTRING_INDEX(cd.plan, ',', 1))
   ELSE COALESCE(NULLIF(TRIM(cd.plan), ''), '') 
 END AS plan_label,
 CASE 
   WHEN cd.incidencia REGEXP '^[0-9]+(,[0-9]+)?$' 
     THEN COALESCE(NULLIF(TRIM(li_incidencia.value), ''), SUBSTRING_INDEX(cd.incidencia, ',', 1))
   ELSE COALESCE(NULLIF(TRIM(cd.incidencia), ''), '') 
 END AS incidencia_label
 FROM ost_ticket t 
 $joins
 $where";
        
    $res = $conexion->query($sqlBase);
    $rawRows = [];
    while ($row = $res->fetch_assoc()) $rawRows[] = $row;

    if ($metric === 'plan' || $metric === 'plan_pct') $rows = agrupar_filas_normalizadas($rawRows, function($row){ return resolver_label_plan($row); });
    elseif ($metric === 'curso') $rows = agrupar_filas_normalizadas($rawRows, function($row){ return $row['curso_raw'] ?? 'Sin dato'; }, $show_all ? 0 : 15);
    elseif ($metric === 'incidencia') $rows = agrupar_filas_normalizadas($rawRows, function($row){ return $row['incidencia_label'] ?? 'Sin dato'; });
    else $rows = [];

    $labels = []; $values = []; $total = 0;
    foreach ($rows as $row) { $labels[] = $row['label']; $values[] = (int)$row['total']; $total += (int)$row['total']; }
    return ['ok' => true, 'metric' => $metric, 'range' => $range, 'rangeLabel' => $rangeLabel, 'labels' => $labels, 'values' => $values, 'total' => $total];
}

/* === ENDPOINT: filtros dinámicos en cascada === */
if (isset($_GET['ajax_filters'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Lee filter_X o filter_X[] y devuelve array limpio
    $rf = function(string $key) : array {
        $v = $_GET[$key] ?? $_GET[$key.'[]'] ?? null;
        if ($v === null) return [];
        return array_values(array_filter(is_array($v) ? $v : [$v], function($x){ return trim((string)$x) !== ''; }));
    };

    $applied = [];
    if ($t = $rf('filter_plan'))       $applied['plan']       = $t;
    if ($t = $rf('filter_sector'))     $applied['sector']     = $t;
    if ($t = $rf('filter_curso'))      $applied['curso']      = $t;
    if ($t = $rf('filter_incidencia')) $applied['incidencia'] = $t;

    $filter_data = get_filter_values($conexion, $applied);

    if (!empty($_GET['requesting'])) {
        $req = $_GET['requesting'];
        echo isset($filter_data[$req])
            ? json_encode(['ok' => true, 'filters' => [$req => $filter_data[$req]]], JSON_UNESCAPED_UNICODE)
            : json_encode(['ok' => false, 'msg' => 'Campo no válido']);
    } else {
        echo json_encode(['ok' => true, 'filters' => $filter_data], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (isset($_GET['ajax_detail']) && isset($_GET['metric']) && isset($_GET['label'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $metric = trim((string)($_GET['metric'] ?? '')); $label = trim((string)($_GET['label'] ?? ''));
        $range = trim((string)($_GET['range'] ?? 'historico'));
        [$from, $to, $rangeLabel] = rango_fechas($range);
        
        $whereParts = ["t.topic_id != 13"];

if ($from !== null) {
    $whereParts[] = "t.created >= '" . $conexion->real_escape_string($from) . "'";
}

if ($to !== null) {
    $whereParts[] = "t.created <= '" . $conexion->real_escape_string($to) . "'";
}

$where = " WHERE " . implode(" AND ", $whereParts);
		
		$filters = [
'plan' => 'cd.plan',
'curso' => 'cd.curso',
'incidencia' => 'cd.incidencia'
];

foreach ($filters as $key => $column) {
    if (!empty($_GET["filter_$key"])) {
        $vals = $_GET["filter_$key"];

        if (!is_array($vals)) {
            $vals = [$vals];
        }

        $safeVals = array_map(function($v) use ($conexion) {
            return "'" . $conexion->real_escape_string($v) . "'";
        }, $vals);

        if (!empty($safeVals)) {
            $where .= " AND $column IN (" . implode(",", $safeVals) . ")";
        }
    }
}

if (!empty($_GET["filter_sector"])) {

    $vals = $_GET["filter_sector"];

    if (!is_array($vals)) {
        $vals = [$vals];
    }

    $safeVals = array_map(function($v) use ($conexion){
        return "'" . $conexion->real_escape_string($v) . "'";
    }, $vals);

    $lista = implode(",", $safeVals);

    $where .= " AND (
        cd.sector_labora IN ($lista)
        OR cd.sector_cyl IN ($lista)
        OR cd.sector_asturias IN ($lista)
        OR cd.sector_estatal IN ($lista)
    )";
}
		
        $sqlFiltro = "
            SELECT t.ticket_id, t.created,
                COALESCE(NULLIF(TRIM(cd.plan), ''), '') AS plan_raw, COALESCE(NULLIF(TRIM(cd.curso), ''), '') AS curso_raw,
                COALESCE(NULLIF(TRIM(cd.incidencia), ''), '') AS incidencia_raw,
                COALESCE(NULLIF(TRIM(cd.sector_labora), ''), '') AS sector_labora, COALESCE(NULLIF(TRIM(cd.sector_cyl), ''), '') AS sector_cyl, 
                COALESCE(NULLIF(TRIM(cd.sector_asturias), ''), '') AS sector_asturias, COALESCE(NULLIF(TRIM(cd.sector_estatal), ''), '') AS sector_estatal,
                CASE WHEN cd.plan REGEXP '^[0-9]+' THEN COALESCE(NULLIF(TRIM(li_plan.value), ''), SUBSTRING_INDEX(cd.plan, ',', 1)) ELSE COALESCE(NULLIF(TRIM(cd.plan), ''), '') END AS plan_label,
                CASE WHEN cd.incidencia REGEXP '^[0-9]+' THEN COALESCE(NULLIF(TRIM(li_incidencia.value), ''), SUBSTRING_INDEX(cd.incidencia, ',', 1)) ELSE COALESCE(NULLIF(TRIM(cd.incidencia), ''), '') END AS incidencia_label
            FROM ost_ticket t LEFT JOIN ost_ticket__cdata cd ON cd.ticket_id = t.ticket_id
            LEFT JOIN ost_list_items li_plan ON cd.plan REGEXP '^[0-9]+' AND li_plan.id = CAST(SUBSTRING_INDEX(cd.plan, ',', 1) AS UNSIGNED)
            LEFT JOIN ost_list_items li_incidencia ON cd.incidencia REGEXP '^[0-9]+' AND li_incidencia.id = CAST(SUBSTRING_INDEX(cd.incidencia, ',', 1) AS UNSIGNED)
            $where";
            
        $rowsFiltro = [];
        $rFil = $conexion->query($sqlFiltro);
        while ($row = $rFil->fetch_assoc()) $rowsFiltro[] = $row;

        $labelNorm = normalizar_texto_estadistica($label);

       if ($metric === 'fecha') {

    if (preg_match('/^(\d{2})\/(\d{4})$/', $label, $m)) {
        // formato 07/2025
        $mes = $m[1];
        $anio = $m[2];

        $rowsDetalle = array_values(array_filter($rowsFiltro, function($row) use ($mes,$anio){
            $fecha = strtotime($row['created'] ?? '');
            return date('m', $fecha) === $mes && date('Y', $fecha) === $anio;
        }));

    } elseif (preg_match('/^(\d{2})\/(\d{2})$/', $label, $m)) {
        // formato 29/04
        $dia = $m[1];
        $mes = $m[2];
        $anio = date('Y');

        $rowsDetalle = array_values(array_filter($rowsFiltro, function($row) use ($dia,$mes,$anio){
            $fecha = strtotime($row['created'] ?? '');
            return date('d', $fecha)===$dia &&
                   date('m', $fecha)===$mes &&
                   date('Y', $fecha)===$anio;
        }));

    } else {
        $rowsDetalle = $rowsFiltro;
    }
} else {
            $rowsDetalle = array_values(array_filter($rowsFiltro, function($row) use ($metric, $labelNorm){
                if ($metric === 'plan' || $metric === 'plan_pct') $v = resolver_label_plan($row);
                elseif ($metric === 'incidencia') $v = $row['incidencia_label'] ?? '';
                elseif ($metric === 'curso') $v = $row['curso_raw'] ?? '';
                else $v = '';
                return normalizar_texto_estadistica($v) === $labelNorm;
            }));
        }

        $totalDetalle = count($rowsDetalle);
        $fechaMin = null; $fechaMax = null;
        foreach ($rowsDetalle as $row) {
            $fc = $row['created'] ?? null;
            if ($fc) { if ($fechaMin === null || $fc < $fechaMin) $fechaMin = $fc; if ($fechaMax === null || $fc > $fechaMax) $fechaMax = $fc; }
        }

        $resumen = ['total' => $totalDetalle, 'fecha_min' => $fechaMin, 'fecha_max' => $fechaMax];
        $cursos = agrupar_filas_normalizadas($rowsDetalle, function($row){
    return $row['curso_raw'] ?? 'Sin dato';
});

$tipos = agrupar_filas_normalizadas($rowsDetalle, function($row){
    return $row['incidencia_label'] ?? 'Sin dato';
});
        
        echo json_encode(['ok' => true, 'metric' => $metric, 'label' => $label, 'rangeLabel' => $rangeLabel, 'resumen' => $resumen, 'cursos' => $cursos, 'tipos' => $tipos], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (isset($_GET['ajax']) && isset($_GET['metric'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $metric = $_GET['metric'] ?? 'plan';
        $range  = $_GET['range']  ?? 'year';

        $rf = function(string $key) : array {
            $v = $_GET[$key] ?? $_GET[$key.'[]'] ?? null;
            if ($v === null) return [];
            return array_values(array_filter(is_array($v) ? $v : [$v], function($x){ return trim((string)$x) !== ''; }));
        };

        $filters = [];
        if ($t = $rf('filter_plan'))       $filters['plan']       = $t;
        if ($t = $rf('filter_sector'))     $filters['sector']     = $t;
        if ($t = $rf('filter_curso'))      $filters['curso']      = $t;
        if ($t = $rf('filter_incidencia')) $filters['incidencia'] = $t;

        $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1';
        echo json_encode(metric_query($conexion, $metric, $range, $filters, $show_all), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error cargando datos'], JSON_UNESCAPED_UNICODE);
    }
    $conexion->close();
    exit;
}

$default_ranges = ['plan' => 'year', 'fecha' => 'year', 'curso' => 'year', 'incidencia' => 'year', 'plan_pct' => 'year'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estadísticas - Grupo ATU</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
	<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>
:root{
 --primary:#6366f1; --primary-dark:#4f46e5; --primary-bg:#eef2ff;
 --success:#10b981; --success-bg:#d1fae5; --success-row:#f0fdf4;
 --warning:#f59e0b; --warning-bg:#fef3c7; --warning-row:#fffbeb;
 --info:#06b6d4; --info-bg:#cffafe; --info-row:#ecfeff;
 --orange:#ef4444; --orange-bg:#fee2e2; --orange-row:#fef2f2;
 --bg-body:#f1f5f9; --bg-card:#ffffff;
 --bg-sidebar:#0f172a; --bg-sidebar-hover:#1e293b; --bg-sidebar-active:#6366f1;
 --text-primary:#0f172a; --text-secondary:#475569; --text-muted:#94a3b8; --text-sidebar:#94a3b8;
 --border:#e2e8f0; --border-light:#f1f5f9; --shadow-lg:0 10px 15px -3px rgba(0,0,0,0.10);
 --radius-sm:6px; --radius:10px; --radius-lg:14px;
 --transition:all 0.3s cubic-bezier(0.4,0,0.2,1);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg-body);color:var(--text-primary);overflow-x:hidden;line-height:1.5;}

/* SIDEBAR & HEADER  */
.sidebar{position:fixed;top:0;left:0;width:240px;height:100vh;background:var(--bg-sidebar);z-index:1000;transition:var(--transition);display:flex;flex-direction:column;overflow:hidden;}
.sidebar.collapsed{width:75px}
.sidebar-header{padding:22px 18px;display:flex;align-items:center;justify-content:center;border-bottom:1px solid rgba(255,255,255,.06);min-height:70px;}
.sidebar-brand{display:flex;align-items:center;justify-content:center;width:100%;color:#fff;font-size:20px;font-weight:800;white-space:nowrap;overflow:hidden;transition:var(--transition);}
.brand-short{display:none}
.sidebar.collapsed .brand-full{display:none}
.sidebar.collapsed .brand-short{display:flex;align-items:center;justify-content:center;font-size:18px;}
.sidebar-menu{flex:1;padding:20px 10px}
.menu-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:var(--radius);color:var(--text-sidebar);text-decoration:none;font-size:14px;font-weight:500;cursor:pointer;transition:var(--transition);margin-bottom:4px;position:relative;white-space:nowrap;overflow:hidden;}
.menu-item:hover{background:var(--bg-sidebar-hover);color:#fff}
.menu-item.active{background:var(--bg-sidebar-active);color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.4);}
.menu-item i{width:20px;text-align:center;font-size:15px;flex-shrink:0;}
.sidebar.collapsed .menu-item .menu-text{opacity:0;width:0}
.sidebar-collapse-btn{width:100%;padding:14px 18px;display:flex;align-items:center;justify-content:center;gap:0;background:transparent;border:none;border-top:1px solid rgba(255,255,255,.06);color:var(--text-muted);cursor:pointer;font-size:13px;transition:var(--transition);}
.sidebar-collapse-btn:hover{background:var(--bg-sidebar-hover);color:#fff}
.sidebar-collapse-btn .collapse-text{display:none}

.main-content{margin-left:240px;transition:var(--transition);min-height:100vh;}
.sidebar.collapsed~.main-content{margin-left:68px}
.header{background:rgba(255,255,255,.95);box-shadow:0 2px 8px rgba(0,0,0,.05);border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:0;}
.header-left{display:flex;align-items:center;gap:16px}
.header h1{font-size:18px;font-weight:700}
.header-date{font-size:13px;color:var(--text-secondary);padding:6px 14px;background:var(--bg-body);border-radius:var(--radius-sm);font-weight:500;display:flex;align-items:center;gap:8px;}
.header-date i{color:var(--primary)}


/* HEADER con transición para ocultar/mostrar */
.header {
  background: rgba(255,255,255,.95);
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
  border-bottom: 1px solid var(--border);
  padding: 0 28px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;                    /* Por encima de todo */
  transition: transform 0.3s ease, opacity 0.3s ease;  /* Transición suave */
  transform: translateY(0);         /* Posición inicial visible */
  opacity: 1;
}

/* Clase para ocultar el header al hacer scroll */
.header.hide {
  transform: translateY(-100%);     /* Se mueve hacia arriba (oculto) */
  opacity: 0;                       /* Se vuelve transparente */
  pointer-events: none;             /* No interfiere con clics */
}	
	
	
/* GRÁFICAS Y TARJETAS EXACTAS */
.page-content{padding:24px 28px}
.chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;}
.chart-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 6px 18px rgba(15,23,42,.04);padding:16px;min-height:420px;}
.chart-card.wide{grid-column:1/-1}
.chart-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;}
.chart-title{font-size:15px;font-weight:800;letter-spacing:-.2px}
.chart-sub{font-size:12px;color:var(--text-muted);margin-top:2px}
.metric-total{display:inline-flex;align-items:center;gap:8px;background:var(--primary-bg);color:var(--primary);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;white-space:nowrap;}

/* BOTONES RANGO EXACTOS */
.range-group{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;}
.range-btn{border:1px solid var(--border);background:#fff;color:var(--text-secondary);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:600;cursor:pointer;transition:var(--transition);}
.range-btn:hover{border-color:var(--primary);color:var(--primary);}
.range-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.25);}
.chart-box{position:relative;height:285px;}
.chart-card.wide .chart-box{height:300px}
.pct-list{margin-top:12px;display:grid;gap:8px;max-height:120px;overflow:auto;padding-right:4px;}
.pct-item{display:flex;align-items:center;justify-content:space-between;gap:10px;background:var(--bg-body);padding:8px 10px;border-radius:8px;font-size:12px;}
.pct-item strong{color:var(--primary)}

/* TOAST & OVERLAYS */
.toast{position:fixed;top:20px;right:20px;background:var(--bg-sidebar);color:#fff;padding:14px 22px;border-radius:var(--radius);font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:var(--shadow-lg);transform:translateY(-120px);opacity:0;transition:all .35s ease;z-index:9999;}
.toast.show{transform:translateY(0);opacity:1}
.toast.success i{color:var(--success)}
.toast.info i{color:var(--info)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;backdrop-filter:blur(4px);}
.sidebar-overlay.active{display:block}

/* RESPONSIVE ORIGINAL */
@media(max-width:1100px){ .chart-grid{grid-template-columns:1fr} .chart-card.wide{grid-column:auto} }
@media(max-width:768px){ .sidebar{transform:translateX(-100%);width:240px} .sidebar.mobile-open{transform:translateX(0)} .main-content{margin-left:0} .page-content{padding:16px} .header{padding:0 16px} }

/* =======================================================
   ESTILOS NUEVOS PARA LOS FILTROS
======================================================= */
.filters-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; align-items: center; }
.filter-select { flex: 1; min-width: 130px; padding: 6px 8px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; color: var(--text-secondary); background: #fff; cursor: pointer; outline: none; transition: var(--transition); }
.filter-select:hover, .filter-select:focus { border-color: var(--primary); }
.filter-reset-btn { padding: 6px 10px; border: 1px solid var(--border); background: #fff; color: var(--text-muted); border-radius: var(--radius-sm); cursor: pointer; transition: var(--transition); font-size: 13px; display:flex; align-items:center; justify-content:center;}
.filter-reset-btn:hover { background: var(--orange-bg); color: var(--orange); border-color: var(--orange); }

	
/* TOM SELECT */

.ts-wrapper {
  position: relative;
  width: 100%;
  z-index: auto;
}

.ts-control {
  min-height: 34px;
  border: 1.5px solid var(--border) !important;
  border-radius: 6px !important;
  background: #fff !important;
}

.ts-dropdown {
  position: absolute !important;
  z-index: 20 !important;
  background: #fff !important;
  border: 1px solid var(--border) !important;
  border-radius: 8px !important;
  box-shadow: 0 10px 20px rgba(0,0,0,.15);
}
	
/* =============================
   BOTÓN DESCARGAR PRO
============================= */

.btn-download {
  display:inline-flex;
  align-items:center;
  margin-right: -185px;
  gap:8px;
  padding:10px 18px;
  border-radius:10px;
  font-size:13px;
  font-weight:600;
  border:1px solid var(--primary);
  background:var(--primary-bg);
  color:var(--primary-dark);
}

.btn-download:hover {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 6px 18px rgba(99,102,241,.35);
}

#exportModal h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 14px;
}

#exportModal label {
  font-size: 13px;
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 6px 0;
  cursor: pointer;
}

#exportModal input[type="checkbox"] {
  accent-color: var(--primary);
  width: 16px;
  height: 16px;
}
	
.export-info {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 16px;
  line-height: 1.5;
}	

	
	.export-actions {
  display:flex;
  gap:12px;
  justify-content:flex-end;
  margin-top:20px;
}

.btn-export {
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 16px;
  border-radius:10px;
  font-size:13px;
  font-weight:600;
  border:none;
  cursor:pointer;
  transition:.25s ease;
}

.btn-export.primary {
  background: var(--primary);
  color:#fff;
}

.btn-export.primary:hover {
  transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(99,102,241,.35);
}

.btn-export.success {
  background:#10b981;
  color:#fff;
}

.btn-export.success:hover {
  transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(16,185,129,.35);
}

.btn-export.neutral {
  background:#f1f5f9;
  color:#475569;
}

.btn-export.neutral:hover {
  background:#F52E07;
  color:#000000;
}
	
	


/* TOM SELECT - Ajustes para versión vertical */
.ts-wrapper {
  position: relative;
  width: 100%;           
}

.ts-control {
  min-height: 45px !important;  
  border: 1.5px solid var(--border) !important;
  border-radius: 6px !important;
  background: #fff !important;
  padding: 8px 12px !important; 
}

.ts-wrapper.dropdown-active {
  z-index: 1000 !important;  
}
	
.ts-dropdown {
  position: absolute !important;
  z-index: 10000 !important;
  background: #fff !important;
  border: 1px solid var(--border) !important;
  border-radius: 8px !important;
  box-shadow: 0 10px 20px rgba(0,0,0,.15);
  width: 100% !important;     
  margin-top: 4px !important; 
}	

	/* =======================================================
   ESTILOS NUEVOS PARA LOS FILTROS
======================================================= */
.filters-row { 
  display: flex; 
  flex-direction: column;
  gap: 12px; 
  margin-bottom: 12px; 
  align-items: stretch;
  position: relative;
  z-index: auto;             
}

.filter-select { 
  flex: 1; 
  min-width: 100%;
  min-height: 45px;
  padding: 10px 12px;
  border: 1px solid var(--border); 
  border-radius: var(--radius-sm); 
  font-size: 13px; 
  color: var(--text-secondary); 
  background: #fff; 
  cursor: pointer; 
  outline: none; 
  transition: var(--transition);
  position: relative;
  z-index: 5;               /* Por encima del contenido base */
}

.filter-select:hover, .filter-select:focus { 
  border-color: var(--primary); 
}

.filter-reset-btn { 
  padding: 10px 14px;
  min-height: 45px;
  width: 100%;
  border: 1px solid var(--border); 
  background: #fff; 
  color: var(--text-muted); 
  border-radius: var(--radius-sm); 
  cursor: pointer; 
  transition: var(--transition); 
  font-size: 13px; 
  display: flex; 
  align-items: center; 
  justify-content: center;
  position: relative;
  z-index: 5;
}

.filter-reset-btn:hover { 
  background: var(--orange-bg); 
  color: var(--orange); 
  border-color: var(--orange); 
}

/* TOM SELECT  */
.ts-wrapper {
  position: relative;
  width: 100%;
  z-index: 15;            
}

.ts-control {
  min-height: 45px !important;
  border: 1.5px solid var(--border) !important;
  border-radius: 6px !important;
  background: #fff !important;
  padding: 8px 12px !important;
  position: relative;
  z-index: 5;
}

.ts-dropdown {
  position: absolute !important;
  z-index: 100 !important; 
  background: #fff !important;
  border: 1px solid var(--border) !important;
  border-radius: 8px !important;
  box-shadow: 0 10px 20px rgba(0,0,0,.15) !important;
  width: 100% !important;
}

/* Asegurar que las gráficas estén por debajo */
.chart-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 6px 18px rgba(15,23,42,.04);
  padding: 16px;
  min-height: 420px;
  position: relative;
  z-index: 1;             
}

.chart-box {
  position: relative;
  height: 285px;
  z-index: 1;             
}

.chart-card.wide .chart-box {
  height: 300px;
  z-index: 1;
}

/* Range buttons también deben estar accesibles */
.range-group {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 12px;
  position: relative;
  z-index: 8;               
}
	
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
			<span class="brand-full"><a style="text-decoration: none; color: inherit;" href= "https://incidencias.grupoatu.com/">Grupo ATU</a></span>
            <span class="brand-short"><a style="text-decoration: none; color: inherit;" href= "https://incidencias.grupoatu.com/">ATU</a></span>
        </div>
    </div>
    <nav class="sidebar-menu">
        <a href="incidencias.php" class="menu-item" data-tooltip="Incidencias">
            <i class="fas fa-exclamation-triangle"></i>
            <span class="menu-text">Tabla de Incidencias</span>
        </a>
        <a href="estadisticas.php" class="menu-item active" data-tooltip="Estadísticas">
            <i class="fas fa-chart-bar"></i>
            <span class="menu-text" >Estadísticas Incidencias</span>
        </a>
			<hr class="linea-menu"> <!-- Línea normal -->
		<a href="valoraciones.php" class="menu-item" data-tooltip="Valoraciones">
            <i class="fa-solid fa-clipboard-list"></i>
            <span class="menu-text" >Tabla de Valoraciones</span>
        </a>
		<a href="https://incidencias.grupoatu.com/osticket/estadisticas_valoraciones.php" class="menu-item" data-tooltip="EstadísticasValo">
            <i class="fas fa-chart-pie"></i>
            <span class="menu-text" >Estadísticas Valoraciones</span>
        </a>
		<hr class="linea-menu"> <!-- Línea normal -->
		<a href="coordinadores.php"  class="menu-item">
			<i class="fa-solid fa-user-tie"></i>
			<span>Tabla de Coodinadores</span>
		</a>
    </nav>
    <button class="sidebar-collapse-btn" onclick="toggleSidebar()" title="Contraer menú">
        <i class="fas fa-chevron-left"></i>
        <span class="collapse-text">Contraer</span>
    </button>
</aside>

<main class="main-content">
    <header class="header">
        <div class="header-left">
            <button class="mobile-menu-btn" onclick="toggleMobileSidebar()" style="display:none">
                <i class="fas fa-bars"></i>
            </button>
            <h1 style="font-size:30px">Estadísticas</h1>
        </div>
		<button class="btn-download" onclick="openExportModal()">
  <i class="fas fa-download"></i>
  Descargar estadísticas
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
            <?php echo htmlspecialchars($_SESSION['estadisticas_user'] ?? $_SESSION['user_name'] ?? ''); ?>
        </span>
        <a href="?logout=1" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #fee2e2; color: #ef4444; text-decoration: none; transition: 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='#fff';" onmouseout="this.style.background='#fee2e2'; this.style.color='#ef4444';" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
    </header>

    <div class="page-content">
        <div class="chart-grid">

            <!-- 1. INCIDENCIAS POR FECHA -->
            <div class="chart-card wide">
                <div class="chart-head">
                    <div>
                        <div class="chart-title">Incidencias por fecha</div>
                        <div class="chart-sub"><span id="range-fecha">Último mes</span></div>
                    </div>
                    <div class="metric-total"><span id="total-fecha">0</span> tickets</div>
                </div>
                
                <!-- FILTROS AÑADIDOS -->
                <div class="filters-row">
                    <select multiple class="filter-select-multi" data-metric="fecha" data-filter="plan"></select>
                    <select multiple class="filter-select-multi" data-metric="fecha" data-filter="sector"></select>
                    <select multiple class="filter-select-multi" data-metric="fecha" data-filter="curso"></select>
                    <select multiple class="filter-select-multi" data-metric="fecha" data-filter="incidencia"></select>
                    <button class="filter-reset-btn" data-metric="fecha" title="Limpiar filtros"><i class="fas fa-times-circle"></i></button>
                </div>

                <?php echo render_range_buttons('fecha'); ?>
                <div class="chart-box"><canvas id="chart-fecha"></canvas></div>
            </div>

            <!-- 2. INCIDENCIAS POR PLAN -->
            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <div class="chart-title">Incidencias por plan</div>
                        <div class="chart-sub"><span id="range-plan">1 año</span></div>
                    </div>
                    <div class="metric-total"><span id="total-plan">0</span> tickets</div>
                </div>

                <!-- FILTROS AÑADIDOS -->
                <div class="filters-row">
                    <select multiple class="filter-select-multi" data-metric="plan" data-filter="plan"></select>
                    <select multiple class="filter-select-multi" data-metric="plan" data-filter="sector"></select>
                    <select multiple class="filter-select-multi" data-metric="plan" data-filter="curso"></select>
                    <select multiple class="filter-select-multi" data-metric="plan" data-filter="incidencia"></select>
                    <button class="filter-reset-btn" data-metric="plan" title="Limpiar filtros"><i class="fas fa-times-circle"></i></button>
                </div>

                <?php echo render_range_buttons('plan'); ?>
                <div class="chart-box"><canvas id="chart-plan"></canvas></div>
            </div>

            <!-- 3. INCIDENCIAS POR CURSO -->
            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <div class="chart-title">Incidencias por curso</div>
                        <div class="chart-sub"><span id="range-curso">Histórico</span></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <button id="btn-show-all-curso" type="button"onclick="openAllCoursesModal()"style="font-size:11px;font 								weight:600;padding:5px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;color:var(--text-												secondary);cursor:pointer;transition:var(--transition)"
 						title="Ver todos los cursos en una ventana">
 						Ver todos
						</button>
                        <div class="metric-total"><span id="total-curso">0</span> tickets</div>
                    </div>
                </div>

                <!-- FILTROS AÑADIDOS -->
                <div class="filters-row">
                    <select multiple class="filter-select-multi" data-metric="curso" data-filter="plan"></select>
                    <select multiple class="filter-select-multi" data-metric="curso" data-filter="sector"></select>
                    <select multiple class="filter-select-multi" data-metric="curso" data-filter="curso"></select>
                    <select multiple class="filter-select-multi" data-metric="curso" data-filter="incidencia"></select>
                    <button class="filter-reset-btn" data-metric="curso" title="Limpiar filtros"><i class="fas fa-times-circle"></i></button>
                </div>

                <?php echo render_range_buttons('curso'); ?>
                <div class="chart-box"><canvas id="chart-curso"></canvas></div>
            </div>

            <!-- 4. INCIDENCIAS POR TIPO -->
            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <div class="chart-title">Incidencias por tipo</div>
                        <div class="chart-sub"><span id="range-incidencia">Último mes</span></div>
                    </div>
                    <div class="metric-total"><span id="total-incidencia">0</span> tickets</div>
                </div>

                <!-- FILTROS AÑADIDOS -->
                <div class="filters-row">
                    <select multiple class="filter-select-multi" data-metric="incidencia" data-filter="plan"></select>
                    <select multiple class="filter-select-multi" data-metric="incidencia" data-filter="sector"></select>
                    <select multiple class="filter-select-multi" data-metric="incidencia" data-filter="curso"></select>
                    <select multiple class="filter-select-multi" data-metric="incidencia" data-filter="incidencia"></select>
                    <button class="filter-reset-btn" data-metric="incidencia" title="Limpiar filtros"><i class="fas fa-times-circle"></i></button>
                </div>

                <?php echo render_range_buttons('incidencia'); ?>
                <div class="chart-box"><canvas id="chart-incidencia"></canvas></div>
            </div>

            <!-- 5. % INCIDENCIAS POR PLAN -->
            <div class="chart-card">
                <div class="chart-head">
                    <div>
                        <div class="chart-title">% incidencias por plan</div>
                        <div class="chart-sub"><span id="range-plan_pct">1 año</span></div>
                    </div>
                    <div class="metric-total"><span id="total-plan_pct">0</span> tickets</div>
                </div>

                <!-- FILTROS AÑADIDOS -->
                <div class="filters-row">
                    <select multiple class="filter-select-multi" data-metric="plan_pct" data-filter="plan"></select>
                    <select multiple class="filter-select-multi" data-metric="plan_pct" data-filter="sector"></select>
                    <select multiple class="filter-select-multi" data-metric="plan_pct" data-filter="curso"></select>
                    <select multiple class="filter-select-multi" data-metric="plan_pct" data-filter="incidencia"></select>
                    <button class="filter-reset-btn" data-metric="plan_pct" title="Limpiar filtros"><i class="fas fa-times-circle"></i></button>
                </div>

                <?php echo render_range_buttons('plan_pct'); ?>
                <div class="chart-box"><canvas id="chart-plan_pct"></canvas></div>
                <div class="pct-list" id="planPctList"></div>
            </div>

        </div>
    </div>
</main>

<!-- MODAL DETALLE (ORIGINAL) -->
<div id="detailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100001;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;width:100%;max-width:980px;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.25)">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <h3 id="detailModalTitle" style="margin:0;font-size:16px">Detalle</h3>
            <button type="button" onclick="closeDetailModal();return false;" style="border:none;background:none;font-size:24px;cursor:pointer">&times;</button>
        </div>
        <div id="detailModalBody" style="padding:20px;max-height:70vh;overflow:auto"></div>
    </div>
</div>

<div id="exportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center">
 <div style="background:#fff;width:100%;max-width:520px;border-radius:14px;padding:24px">
  <h3 style="margin-bottom:18px">Descargar estadísticas</h3>
<p class="export-info">
  Selecciona las estadísticas que deseas descargar.
  Puedes exportarlas en formato imagen (PNG) o Excel (CSV).
</p>
  <div style="display:grid;gap:8px;margin-bottom:16px">
   <label><input type="checkbox" value="fecha" checked> Incidencias por fecha</label>
   <label><input type="checkbox" value="plan" checked> Incidencias por plan</label>
   <label><input type="checkbox" value="curso" checked> Incidencias por curso</label>
   <label><input type="checkbox" value="incidencia" checked> Incidencias por tipo</label>
   <label><input type="checkbox" value="plan_pct" checked> % incidencias por plan</label>
  </div>

  <div style="display:flex;gap:12px;justify-content:flex-end">
   <div class="export-actions">
  <button onclick="exportCharts('png')" class="btn-export primary">
    <i class="fas fa-image"></i> Descargar PNG
  </button>

  <button onclick="exportCharts('excel')" class="btn-export success">
    <i class="fas fa-file-excel"></i> Descargar Excel
  </button>

  <button onclick="closeExportModal()" class="btn-export neutral">
    Cancelar
  </button>
</div>
  </div>
 </div>
</div>
	
	
<div id="allCoursesModal"
 style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100000;align-items:center;justify-content:center;padding:20px">
 
 <div style="background:#fff;width:100%;max-width:760px;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.25);display:flex;flex-direction:column;max-height:85vh">
  
  <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:12px">
   <h3 style="margin:0;font-size:16px">Todos los cursos</h3>
   <button type="button" onclick="closeAllCoursesModal()" style="border:none;background:none;font-size:24px;cursor:pointer">&times;</button>
  </div>

  <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0">
   <input id="allCoursesSearch"
          type="text"
          placeholder="Buscar curso..."
          oninput="renderAllCoursesList(this.value)"
          style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
  </div>

  <div id="allCoursesModalBody" style="padding:20px;overflow:auto">
   Cargando...
  </div>

 </div>
</div>
	
	
<script>
// ─── UI BÁSICA ────────────────────────────────────────────────────────────────
function updateClock(){
    var n=new Date(),p=function(x){return String(x).padStart(2,'0');};
    document.getElementById('liveDateText').textContent=p(n.getDate())+'/'+p(n.getMonth()+1)+'/'+n.getFullYear()+' '+p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());
}
updateClock(); setInterval(updateClock,1000);

function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');}
function toggleMobileSidebar(){
    document.getElementById('sidebar').classList.toggle('mobile-open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
function showToast(msg,type){
    var t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    t.querySelector('i').className=type==='success'?'fas fa-check-circle':'fas fa-info-circle';
    t.className='toast '+type+' show';
    setTimeout(function(){t.classList.remove('show');},3000);
}

// ─── AUXILIARES ───────────────────────────────────────────────────────────────
function colorPalette(i){
    var c=['#6366f1','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f97316','#3b82f6','#84cc16','#ec4899','#22c55e','#eab308','#0ea5e9','#a855f7'];
    return c[i%c.length];
}
function truncateLabel(text,max){text=String(text||'');return text.length>max?text.slice(0,max-1)+'…':text;}
function escapeHtml(text){return String(text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}

// ─── ESTADO GLOBAL ────────────────────────────────────────────────────────────
//var defaultRanges = <?php echo json_encode($default_ranges, JSON_UNESCAPED_UNICODE); ?>;
//var savedRanges={};
//try{savedRanges=JSON.parse(localStorage.getItem('estadisticasRanges')||'{}');}catch(e){}
//var _oldDefaults={fecha:'month',curso:'historico',incidencia:'month'};
//Object.keys(_oldDefaults).forEach(function(m){
  //  if(savedRanges[m]===_oldDefaults[m]) delete savedRanges[m];
//});
//var chartRanges=Object.assign({},defaultRanges,savedRanges);
//var charts={};
	
// ─── ESTADO GLOBAL ────────────────────────────────────────────────────────────
var defaultRanges = <?php echo json_encode($default_ranges, JSON_UNESCAPED_UNICODE); ?>;
var chartRanges=Object.assign({},defaultRanges);
var charts={};
	
// Filtros actuales por métrica. Estructura: {plan:{plan:[],sector:[],...}, ...}
var currentFilters={fecha:{},plan:{},curso:{},incidencia:{},plan_pct:{}};

var availableFilters={plan:[],sector:[],curso:[],incidencia:[]};
var tomInstances={};

// Los gráficos NO se cargan hasta que initializeApp lo ordena explícitamente.
// No hay ningún flag ni condición: initializeTomSelects() NUNCA llama loadMetric.

function saveFilters(){
    try{localStorage.setItem('estadisticasFilters',JSON.stringify(currentFilters));}catch(e){}
}

// ─── RANGOS ───────────────────────────────────────────────────────────────────
function syncButtons(metric,range){
    document.querySelectorAll('.range-btn[data-metric="'+metric+'"]').forEach(function(btn){
        btn.classList.toggle('active',btn.dataset.range===range);
    });
}
function setRange(metric,range){
    chartRanges[metric]=range;
  //  try{localStorage.setItem('estadisticasRanges',JSON.stringify(chartRanges));}catch(e){}
    syncButtons(metric,range);
    loadMetric(metric,range,currentFilters[metric]||{});
}

// ─── CONSTRUCCIÓN DE URL ──────────────────────────────────────────────────────
// URLSearchParams codifica [] como %5B%5D; PHP lo lee como "filter_plan%5B%5D".
// Para que PHP lo trate como array usamos el nombre literal "filter_plan[]"
// construyendo la query string manualmente.
function buildUrl(metric,range,filters,showAll){
    var parts=['ajax=1','metric='+encodeURIComponent(metric),'range='+encodeURIComponent(range)];
    if(metric==='curso'&&showAll===true) parts.push('show_all=1');
    if(filters&&typeof filters==='object'){
        ['plan','sector','curso','incidencia'].forEach(function(key){
            var vals=filters[key];
            if(!vals) return;
            if(!Array.isArray(vals)) vals=[vals];
            vals.forEach(function(v){
                if(v!==null&&v!==undefined&&String(v).trim()!=='')
                    parts.push('filter_'+key+'%5B%5D='+encodeURIComponent(v));
            });
        });
    }
    return 'estadisticas.php?'+parts.join('&');
}

// ─── GRÁFICAS ─────────────────────────────────────────────────────────────────
function renderPctList(labels,values){
    var wrap=document.getElementById('planPctList');
    if(!wrap) return;
    var total=values.reduce(function(a,b){return a+b;},0);
    if(!total){wrap.innerHTML='<div class="pct-item"><span>Sin datos</span><strong>0%</strong></div>';return;}
    wrap.innerHTML=labels.map(function(label,i){
        var v=values[i]||0,pct=((v*100)/total).toFixed(1);
        return '<div class="pct-item"><span title="'+escapeHtml(label)+'">'+escapeHtml(label)+'</span><strong>'+pct+'% ('+v+')</strong></div>';
    }).join('');
}

function createChart(metric,data){
    var canvas=document.getElementById('chart-'+metric);
    if(!canvas) return;
    var ctx=canvas.getContext('2d');
    if(charts[metric]) charts[metric].destroy();
    var rawLabels=data.labels||[],values=data.values||[],labels=rawLabels.slice();
    var type=(metric==='fecha')?'line':(metric==='plan_pct'?'doughnut':'bar');
    if(metric==='curso') labels=rawLabels.map(function(l){return truncateLabel(l,40);});
    else if(metric==='incidencia') labels=rawLabels.map(function(l){return truncateLabel(l,34);});
    else if(metric==='plan') labels=rawLabels.map(function(l){return truncateLabel(l,34);});
    var cfg={
        type:type,
        data:{labels:labels,datasets:[{label:'Tickets',data:values,borderColor:'#6366f1',
            backgroundColor:labels.map(function(_,i){return colorPalette(i);}),
            borderWidth:2,borderSkipped:false,clip:false,fill:metric==='fecha',tension:0.35,
            pointRadius:metric==='fecha'?3:0,pointHoverRadius:metric==='fecha'?5:0,hoverRadius:8,hitRadius:12}]},
        options:{responsive:true,maintainAspectRatio:false,animation:false,
            interaction:{mode:'nearest',axis:metric==='fecha'?'x':'y',intersect:false},
            onClick:function(evt,elements){
                if(!elements||!elements.length) return;
                openDetailModal(metric,rawLabels[elements[0].index]||'',chartRanges[metric]||defaultRanges[metric]);
            },
            plugins:{legend:{display:false},tooltip:{displayColors:false,padding:10,
                callbacks:{
                    title:function(items){return rawLabels[items[0].dataIndex]||'';},
                    label:function(ctx2){
                        var v=(metric==='fecha')?ctx2.parsed.y:(metric==='plan_pct'?ctx2.parsed:ctx2.parsed.x);
                        if(metric==='plan_pct'){var tot=values.reduce(function(a,b){return a+b;},0);return 'Tickets: '+v+' ('+(tot?((v*100)/tot).toFixed(1):0)+'%)';}
                        return 'Tickets: '+v;
                    }
                }
            }},
            scales:{}}
    };
    if(metric==='fecha'){
        cfg.data.datasets[0].backgroundColor='rgba(99,102,241,.15)';
        cfg.options.scales={x:{grid:{color:'#eef2ff'},ticks:{color:'#64748b',maxRotation:0,autoSkip:true}},y:{beginAtZero:true,grid:{color:'#eef2ff'},ticks:{color:'#64748b',precision:0}}};
    } else if(metric==='plan_pct'){
        cfg.options.cutout='62%';
    } else {
        cfg.options.indexAxis='y';
        cfg.options.scales={x:{beginAtZero:true,grid:{color:'#eef2ff'},ticks:{color:'#64748b',precision:0}},y:{grid:{display:false},ticks:{color:'#64748b',autoSkip:false,font:{size:metric==='curso'?11:12}}}};
        cfg.data.datasets[0].borderRadius=8;
        cfg.data.datasets[0].barThickness=metric==='curso'?14:18;
        cfg.data.datasets[0].maxBarThickness=metric==='curso'?16:20;
        cfg.options.layout={padding:{right:12}};
    }
    charts[metric]=new Chart(ctx,cfg);
}

function loadMetric(metric,range,filters){
    fetch(buildUrl(metric,range,filters||{},false))
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.ok) throw new Error(d.msg||'Error');
            var te=document.getElementById('total-'+metric);
            var re=document.getElementById('range-'+metric);
            if(te) te.textContent=d.total||0;
            if(re) re.textContent=d.rangeLabel||'';
            createChart(metric,d);
            if(metric==='plan_pct') renderPctList(d.labels||[],d.values||[]);
        })
        .catch(function(err){console.error('loadMetric['+metric+']',err);});
}

function loadAllMetrics(){
    ['fecha','plan','curso','incidencia','plan_pct'].forEach(function(metric){
        loadMetric(metric,chartRanges[metric]||defaultRanges[metric],currentFilters[metric]||{});
    });
}

// ─── MODAL TODOS LOS CURSOS ───────────────────────────────────────────────────
var allCoursesRows=[];
function openAllCoursesModal(){
    var modal=document.getElementById('allCoursesModal');
    var body=document.getElementById('allCoursesModalBody');
    var search=document.getElementById('allCoursesSearch');
    if(!modal||!body) return;
    body.innerHTML='Cargando...';
    if(search) search.value='';
    modal.style.display='flex';
    var range=chartRanges['curso']||defaultRanges['curso'];
    fetch(buildUrl('curso',range,currentFilters['curso']||{},true))
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.ok) throw new Error(d.msg||'Error');
            allCoursesRows=(d.labels||[]).map(function(label,i){return{label:label,total:d.values[i]||0};});
            renderAllCoursesList('');
        })
        .catch(function(e){
            console.error(e);
            body.innerHTML='<div style="background:#f8fafc;padding:12px;border-radius:8px">No se pudieron cargar los cursos.</div>';
        });
}
function closeAllCoursesModal(){
    var m=document.getElementById('allCoursesModal');
    if(m) m.style.display='none';
}
function renderAllCoursesList(search){
    var body=document.getElementById('allCoursesModalBody');
    if(!body) return;
    search=String(search||'').toLowerCase();
    var rows=allCoursesRows.filter(function(r){return String(r.label||'').toLowerCase().indexOf(search)!==-1;});
    if(!rows.length){body.innerHTML='<div style="background:#f8fafc;padding:12px;border-radius:8px">No hay cursos para mostrar.</div>';return;}
    var html='<div style="display:grid;gap:6px">';
    rows.forEach(function(row){
        var el=escapeHtml(row.label);
        html+='<div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;padding:10px 14px;border-radius:8px;cursor:pointer;transition:.15s" onclick="closeAllCoursesModal();openDetailModal(\'curso\',\''+el.replace(/'/g,"\\'")+'\',(chartRanges[\'curso\']||defaultRanges[\'curso\']))" onmouseover="this.style.background=\'#eef2ff\'" onmouseout="this.style.background=\'#f8fafc\'"><span style="font-size:13px">'+el+'</span><strong style="color:#6366f1;margin-left:12px">'+row.total+'</strong></div>';
    });
    html+='</div>';
    body.innerHTML=html;
}
document.addEventListener('click',function(e){if(e.target&&e.target.id==='allCoursesModal') closeAllCoursesModal();});

// ─── MODAL DETALLE ────────────────────────────────────────────────────────────
function closeDetailModal(){document.getElementById('detailModal').style.display='none';}
document.addEventListener('click',function(e){if(e.target&&e.target.id==='detailModal') closeDetailModal();});

function openDetailModal(metric,label,range){
    var p=new URLSearchParams();
    p.set('ajax_detail','1');p.set('metric',metric);p.set('label',label);p.set('range',range);
    var filtros=currentFilters[metric]||{};
    ['plan','sector','curso','incidencia'].forEach(function(key){
        var vals=filtros[key];
        if(vals){(Array.isArray(vals)?vals:[vals]).forEach(function(v){p.append('filter_'+key+'[]',v);});}
    });
    fetch('estadisticas.php?'+p.toString())
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.ok) throw new Error(d.msg||'Error');
            document.getElementById('detailModalTitle').textContent='Detalle: '+d.label;
            var html='<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px">';
            html+='<div style="background:#f8fafc;padding:12px;border-radius:10px"><strong>Total tickets</strong><div style="margin-top:6px;font-size:20px">'+escapeHtml(d.resumen.total||0)+'</div></div>';
            html+='<div style="background:#f8fafc;padding:12px;border-radius:10px"><strong>Desde</strong><div style="margin-top:6px">'+escapeHtml(d.resumen.fecha_min||'-')+'</div></div>';
            html+='<div style="background:#f8fafc;padding:12px;border-radius:10px"><strong>Hasta</strong><div style="margin-top:6px">'+escapeHtml(d.resumen.fecha_max||'-')+'</div></div>';
            html+='</div><div style="margin-bottom:18px"><strong>Rango aplicado:</strong> '+escapeHtml(d.rangeLabel||'-')+'</div>';
            html+='<h4 style="margin:14px 0 8px">Cursos relacionados</h4><div style="display:grid;gap:8px">';
            if((d.cursos||[]).length) d.cursos.forEach(function(row){html+='<div style="display:flex;justify-content:space-between;background:#f8fafc;padding:10px 12px;border-radius:8px"><span>'+escapeHtml(row.label)+'</span><strong>'+escapeHtml(row.total)+'</strong></div>';});
            else html+='<div style="background:#f8fafc;padding:10px 12px;border-radius:8px">Sin datos</div>';
            html+='</div><h4 style="margin:18px 0 8px">Tipos de incidencia</h4><div style="display:grid;gap:8px">';
            if((d.tipos||[]).length) d.tipos.forEach(function(row){html+='<div style="display:flex;justify-content:space-between;background:#f8fafc;padding:10px 12px;border-radius:8px"><span>'+escapeHtml(row.label)+'</span><strong>'+escapeHtml(row.total)+'</strong></div>';});
            else html+='<div style="background:#f8fafc;padding:10px 12px;border-radius:8px">Sin datos</div>';
            html+='</div>';
            document.getElementById('detailModalBody').innerHTML=html;
            document.getElementById('detailModal').style.display='flex';
        })
        .catch(function(e){showToast(e.message||'No se pudo cargar el detalle','info');});
}

// ─── FILTROS EN CASCADA ───────────────────────────────────────────────────────
// Cuando cambia cualquier filtro, se recalculan las opciones de los filtros
// posteriores en la cadena: plan → sector → curso → incidencia
var CASCADE_ORDER=['plan','sector','curso','incidencia'];

function updateDownstreamFilters(metric, changedFilterType){
    var startIdx=CASCADE_ORDER.indexOf(changedFilterType);
    if(startIdx===-1||startIdx>=CASCADE_ORDER.length-1) return; // nada downstream

    // Los filtros activos hasta e incluyendo el que acaba de cambiar
    var activeFilters={};
    for(var i=0;i<=startIdx;i++){
        var f=CASCADE_ORDER[i];
        if(currentFilters[metric]&&currentFilters[metric][f]&&currentFilters[metric][f].length)
            activeFilters[f]=currentFilters[metric][f];
    }

    // Construir URL para obtener opciones filtradas
    var parts=['ajax_filters=1'];
    Object.keys(activeFilters).forEach(function(key){
        activeFilters[key].forEach(function(v){
            parts.push('filter_'+key+'%5B%5D='+encodeURIComponent(v));
        });
    });

    fetch('estadisticas.php?'+parts.join('&'))
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.ok||!data.filters) return;
            // Actualizar cada filtro downstream
            for(var j=startIdx+1;j<CASCADE_ORDER.length;j++){
                var depType=CASCADE_ORDER[j];
                var newOptions=data.filters[depType]||[];
                var key=metric+'_'+depType;
                var ts=tomInstances[key];
                if(!ts) continue;
                var currentVal=ts.getValue();
                ts.clear(true);
                ts.clearOptions();
                ts.addOptions(newOptions.map(function(o){return{value:o,text:o};}));
                // Mantener solo los valores que siguen siendo válidos
                var valid=(Array.isArray(currentVal)?currentVal:[currentVal]).filter(function(v){return newOptions.indexOf(v)!==-1;});
                if(valid.length){
                    ts.setValue(valid,true);
                    if(!currentFilters[metric]) currentFilters[metric]={};
                    currentFilters[metric][depType]=valid;
                } else {
                    if(currentFilters[metric]) delete currentFilters[metric][depType];
                }
                ts.refreshOptions(false);
            }
        })
        .catch(function(e){console.error('cascade error:',e);});
}

// ─── TOM SELECT ───────────────────────────────────────────────────────────────
function populateSelectOptions(){
    document.querySelectorAll('.filter-select-multi').forEach(function(select){
        var ft=select.dataset.filter;
        var opts=(availableFilters&&availableFilters[ft])?availableFilters[ft]:[];
        select.innerHTML=opts.map(function(v){return '<option value="'+escapeHtml(v)+'">'+escapeHtml(v)+'</option>';}).join('');
    });
}

function initializeTomSelects(){
    document.querySelectorAll('.filter-select-multi').forEach(function(select){
        var metric=select.dataset.metric;
        var filterType=select.dataset.filter;
        var key=metric+'_'+filterType;
        if(tomInstances[key]) tomInstances[key].destroy();

        var ts=new TomSelect(select,{
            plugins:['remove_button'],
            create:false,
            allowEmptyOption:true,
            placeholder:'- Selecciona '+filterType+' -',
            maxOptions:5000,
            hideSelected:false,
            // IMPORTANTE: onChange se llama SOLO por interacción del usuario.
            // Durante initializeApp se usa setValue(..., true) que NO dispara onChange.
            onChange:function(vals){
                vals=Array.isArray(vals)?vals:(vals?[vals]:[]);
                if(!currentFilters[metric]) currentFilters[metric]={};
                if(vals.length>0){ currentFilters[metric][filterType]=vals; }
                else { delete currentFilters[metric][filterType]; }
                saveFilters();
                // Recargar gráfica con los nuevos filtros
                loadMetric(metric,chartRanges[metric]||defaultRanges[metric],currentFilters[metric]||{});
                // Actualizar opciones de filtros posteriores en la cascada
                updateDownstreamFilters(metric,filterType);
            }
        });

        // Restaurar selecciones guardadas sin disparar onChange (silent=true)
        var saved=(currentFilters[metric]||{})[filterType];
        if(saved&&saved.length) ts.setValue(saved,true);

        tomInstances[key]=ts;
    });
}

// ─── BOTÓN RESET FILTROS ──────────────────────────────────────────────────────
document.querySelectorAll('.filter-reset-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        var metric=this.dataset.metric;
        currentFilters[metric]={};
        saveFilters();
        document.querySelectorAll('.filter-select-multi[data-metric="'+metric+'"]').forEach(function(s){
            var key=metric+'_'+s.dataset.filter;
            if(tomInstances[key]) tomInstances[key].clear(true);
        });
        loadMetric(metric,chartRanges[metric]||defaultRanges[metric],{});
        showToast('Filtros limpiados','success');
        // Restaurar todas las opciones en cascada (sin filtro activo)
        fetch('estadisticas.php?ajax_filters=1')
            .then(function(r){return r.json();})
            .then(function(data){
                if(!data.ok||!data.filters) return;
                CASCADE_ORDER.forEach(function(ft){
                    var newOpts=data.filters[ft]||[];
                    var key=metric+'_'+ft;
                    var ts=tomInstances[key];
                    if(!ts) return;
                    ts.clear(true); ts.clearOptions();
                    ts.addOptions(newOpts.map(function(o){return{value:o,text:o};}));
                    ts.refreshOptions(false);
                });
            })
            .catch(function(e){console.error(e);});
    });
});

// ─── RANGOS (event listeners) ─────────────────────────────────────────────────
document.querySelectorAll('.range-btn').forEach(function(btn){
    btn.addEventListener('click',function(){setRange(this.dataset.metric,this.dataset.range);});
});

// ─── INICIALIZACIÓN PRINCIPAL ─────────────────────────────────────────────────
// Orden garantizado y sin flags:
//  1. Cargar opciones de filtros del servidor
//  2. Popular <select> con esas opciones
//  3. Inicializar TomSelect y restaurar selecciones guardadas (silent, sin onChange)
//  4. Cargar todas las gráficas  ← esto ocurre SIEMPRE, incondicionalmente
async function initializeApp(){
    // Sincronizar botones de rango
    Object.keys(defaultRanges).forEach(function(m){syncButtons(m,chartRanges[m]||defaultRanges[m]);});

    try {
        var resp=await fetch('estadisticas.php?ajax_filters=1');
        var data=await resp.json();
        if(!data.ok) throw new Error(data.msg||'Error al cargar filtros');
        availableFilters=data.filters;
        populateSelectOptions();
        initializeTomSelects();
    } catch(e){
        console.error('Error cargando filtros:',e);
        // Si falla la carga de filtros, igual inicializamos TomSelect vacío
        populateSelectOptions();
        initializeTomSelects();
    }

    // Cargar gráficas SIEMPRE, haya o no filtros guardados
    loadAllMetrics();
}

document.addEventListener('DOMContentLoaded',initializeApp);
setInterval(loadAllMetrics,60000);

// ─── EXPORTACIÓN ──────────────────────────────────────────────────────────────
function openExportModal(){document.getElementById('exportModal').style.display='flex';}
function closeExportModal(){document.getElementById('exportModal').style.display='none';}
function getSelectedCharts(){return Array.from(document.querySelectorAll('#exportModal input[type=checkbox]:checked')).map(function(cb){return cb.value;});}
function exportCharts(type){
    var metrics=getSelectedCharts();
    if(type==='png'){
        metrics.forEach(function(metric){
            var canvas=document.getElementById('chart-'+metric);
            if(!canvas) return;
            var nc=document.createElement('canvas');nc.width=canvas.width;nc.height=canvas.height+80;
            var nctx=nc.getContext('2d');
            nctx.fillStyle='#fff';nctx.fillRect(0,0,nc.width,nc.height);
            nctx.fillStyle='#000';nctx.font='bold 16px Inter,sans-serif';nctx.fillText('Estadística: '+metric,20,25);
            var ftxt=Object.keys(currentFilters[metric]||{}).map(function(k){return k+': '+(currentFilters[metric][k]||[]).join(', ');}).join(' | ');
            nctx.font='12px Inter,sans-serif';nctx.fillText('Filtros: '+(ftxt||'Ninguno'),20,50);
            nctx.drawImage(canvas,0,80);
            var a=document.createElement('a');a.download='estadistica_'+metric+'.png';a.href=nc.toDataURL('image/png');a.click();
        });
    } else if(type==='excel'){
        metrics.forEach(function(metric){
            fetch(buildUrl(metric,chartRanges[metric]||defaultRanges[metric],currentFilters[metric]||{},false))
            .then(function(r){return r.json();})
            .then(function(d){
                var rows=[['Etiqueta','Total']];
                (d.labels||[]).forEach(function(label,i){rows.push([label,d.values[i]||0]);});
                var csv=rows.map(function(r){return r.join(',');}).join('\n');
                var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
                var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='estadistica_'+metric+'.csv';a.click();
            });
        });
    }
    closeExportModal();
}

// ─── SCROLL: ocultar header ───────────────────────────────────────────────────
var _lastST=0,_hdr=document.querySelector('.header');
window.addEventListener('scroll',function(){
    var st=window.pageYOffset||document.documentElement.scrollTop;
    if(st>_lastST&&st>80){if(_hdr)_hdr.classList.add('hide');}
    else{if(_hdr)_hdr.classList.remove('hide');}
    _lastST=st<=0?0:st;
});
</script>

</body>
</html>