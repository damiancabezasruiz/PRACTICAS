<?php
/**
 * =============================================================================
 * options.php — Gestor de listas desplegables de los formularios de osTicket
 * =============================================================================
 *
 * Propósito:
 *   Herramienta del panel de gestión que permite editar las listas desplegables
 *   (campos de tipo list-N en osTicket) de los formularios de incidencias
 *   (form_id=18) y valoraciones (form_id=16), de forma independiente por sección.
 *   Los campos base (text, memo, files) se gestionan en fields.php.
 *
 * Contexto dinámico (?tipo=):
 *   - tipo=incidencias  → form_id = 18
 *   - tipo=valoraciones → form_id = 16
 *
 * APIs internas:
 *   ?api_ocultos_v5[&secret=atu2026]
 *     Devuelve JSON con todos los elementos ocultos de ambas secciones:
 *     opciones individuales (tipo='opcion'), campos desplegables completos
 *     (tipo='campo') y campos base (tipo='base'). La clave secreta permite
 *     acceso sin sesión (usado desde open.php para filtrar el formulario público).
 *
 *   ?api_desocultar  POST: field_id + form_id
 *     Elimina el registro de ocultación en custom_hidden_fields.
 *
 * Acciones POST ($_POST['accion']):
 *   'guardar_lista'        → Actualiza textos de opciones (ost_list_items.value)
 *                            y gestiona la visibilidad del campo y sus opciones
 *                            de forma independiente por form_id.
 *   'guardar_campo_lista'  → Actualiza solo el label del campo desplegable
 *                            y su visibilidad por form_id.
 *   'crear_lista'          → Crea una nueva ost_list + campo ost_form_field
 *                            asociado, todo en una transacción.
 *   'crear_campo_lista'    → Crea un campo ost_form_field sobre una ost_list
 *                            ya existente (reutilizar lista en otro cuestionario).
 *   'crear_opcion'         → Añade un nuevo ost_list_items a una lista existente.
 *                            La opción se crea siempre en osTicket; la visibilidad
 *                            inicial se controla en custom_hidden_fields.
 *
 * Tabla custom_hidden_fields (field_id, form_id, hidden):
 *   Permite que la misma lista/opción de osTicket sea visible en Incidencias
 *   y oculta en Valoraciones de forma totalmente independiente.
 *
 * Carga de datos (sección principal):
 *   Consulta ost_form_field WHERE type LIKE 'list-%' para el form_id activo,
 *   haciendo LEFT JOIN con custom_hidden_fields para obtener el estado de
 *   visibilidad específico de esta sección.
 *
 * Dependencias:
 *   - db.php ($mysqli)
 *   - Sesión PHP activa con 'gestor_loggedin' = true (excepto api_ocultos_v5)
 *   - ost_list, ost_list_items, ost_form_field (tablas de osTicket)
 *   - custom_hidden_fields (tabla personalizada Grupo ATU)
 *
 * @package    GrupoATU\Gestor
 * @author     Equipo de desarrollo Grupo ATU
 * @version    7.0
 * =============================================================================
 */

/**
 * GESTOR DE LISTAS DESPLEGABLES - GRUPO ATU
 * v7.0 - Restauración de Estilos, Independencia y Notificaciones
 */

require_once __DIR__ . '/db.php';

/* =========================================================
   1. CONFIGURACIÓN DE SESIÓN Y SEGURIDAD
   ========================================================= */
ini_set('session.gc_maxlifetime', (8 * 60 * 60));
$__secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $__secure, 'httponly' => true, 'samesite' => 'Lax']);
} else {
    session_set_cookie_params(0, '/', '', $__secure, true);
}
session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (session_id() !== '') session_destroy();
    setcookie('gestor_loggedin', '', time() - 3600, '/');
    header('Location: Gestor.php'); exit;
}

// API pública con clave secreta
$es_api_publica = isset($_GET['api_ocultos_v5']) && ($_GET['secret'] ?? '') === 'atu2026';

if (empty($_SESSION['gestor_loggedin']) && !$es_api_publica) {
    header('Location: Gestor.php');
    exit;
}

/* =========================================================
   2. CONTEXTO DINÁMICO (INCIDENCIAS: 18 / VALORACIONES: 16)
   ========================================================= */
$tipo_gestion = $_GET['tipo'] ?? 'incidencias';
if ($tipo_gestion === 'valoraciones') {
    $form_id_actual = 16;
    $nombre_seccion = 'Valoraciones';
} elseif ($tipo_gestion === 'valoraciones_tutores') {
    $form_id_actual = 22;
    $nombre_seccion = 'Valoración de Tutores';
} else {
    $form_id_actual = 18;
    $nombre_seccion = 'Incidencias';
}

/* =========================================================
   3. API PARA EL POP-UP (OCULTOS FILTRADOS POR FORM_ID)
   ========================================================= */
if (isset($_GET['api_ocultos_v5'])) {
    header('Content-Type: application/json; charset=utf-8');

    $secciones = [
        ['form_id' => 18, 'nombre' => 'Incidencias'],
        ['form_id' => 16, 'nombre' => 'Valoraciones'],
        ['form_id' => 22, 'nombre' => 'Valoración de Tutores'],
    ];

    $items = [];
    foreach ($secciones as $sec) {
        $fid = $sec['form_id'];

        // Opciones individuales ocultas
        $sqlApi = "SELECT i.id as item_id, i.value as opcion_nombre, f.label as pregunta_nombre,
                          'opcion' as tipo, $fid as form_id, '{$sec['nombre']}' as seccion
                   FROM custom_hidden_fields h
                   INNER JOIN ost_list_items i ON i.id = h.field_id
                   INNER JOIN ost_form_field f
                           ON f.type = CONCAT('list-', i.list_id)
                          AND f.form_id = $fid
                   WHERE h.form_id = $fid AND h.hidden = 1";
        $res = $mysqli->query($sqlApi);
        if ($res) while ($row = $res->fetch_assoc()) { $items[] = $row; }

        // Campos enteros (desplegables) ocultos
        $sqlCampos = "SELECT f.id as item_id, f.label as opcion_nombre, f.label as pregunta_nombre,
                             'campo' as tipo, $fid as form_id, '{$sec['nombre']}' as seccion
                      FROM custom_hidden_fields h
                      INNER JOIN ost_form_field f ON f.id = h.field_id
                      WHERE h.form_id = $fid AND h.hidden = 1
                        AND f.form_id = $fid AND f.type LIKE 'list-%'";
        $res2 = $mysqli->query($sqlCampos);
        if ($res2) while ($row = $res2->fetch_assoc()) { $items[] = $row; }

        // Campos base (text, memo, files…) ocultos
        $sqlBase = "SELECT f.id as item_id, f.label as opcion_nombre, f.type as pregunta_nombre,
                           'base' as tipo, $fid as form_id, '{$sec['nombre']}' as seccion
                    FROM custom_hidden_fields h
                    INNER JOIN ost_form_field f ON f.id = h.field_id
                    WHERE h.form_id = $fid AND h.hidden = 1
                      AND f.form_id = $fid
                      AND f.type NOT LIKE 'list-%'
                      AND f.type NOT IN ('info')
                    ORDER BY f.sort ASC";
        $res3 = $mysqli->query($sqlBase);
        if ($res3) while ($row = $res3->fetch_assoc()) { $items[] = $row; }
    }

    echo json_encode(['ok' => true, 'items' => $items, 'total' => count($items)]);
    exit;
}

// API para desocultar un elemento desde el popup
if (isset($_GET['api_desocultar'])) {
    header('Content-Type: application/json; charset=utf-8');
    $field_id = (int)($_POST['field_id'] ?? 0);
    $form_id  = (int)($_POST['form_id']  ?? 0);
    if ($field_id && $form_id) {
        $stmt = $mysqli->prepare("DELETE FROM custom_hidden_fields WHERE field_id = ? AND form_id = ?");
        $stmt->bind_param("ii", $field_id, $form_id);
        $stmt->execute();
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    }
    exit;
}

/* =========================================================
   4. ACCIONES POST (GUARDADO INDEPENDIENTE)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    if ($accion === 'guardar_lista') {
        $id_lista_padre = (int)$_POST['list_id'];
        // items[] contiene: id, value, status para cada opción
        $items_raw = $_POST['items'] ?? [];

        $stmtU  = $mysqli->prepare("UPDATE ost_list_items SET value = ? WHERE id = ?");
        $stmtIns = $mysqli->prepare("INSERT INTO custom_hidden_fields (field_id, form_id, hidden) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hidden = 1");
        $stmtDel = $mysqli->prepare("DELETE FROM custom_hidden_fields WHERE field_id = ? AND form_id = ?");

        foreach ($items_raw as $item) {
            $id_item       = (int)($item['id'] ?? 0);
            $nuevo_texto   = trim($item['value'] ?? '');
            $estado_deseado = (int)($item['status'] ?? 1);

            if (!$id_item) continue;

            // Actualizar texto en osTicket (global)
            $stmtU->bind_param("si", $nuevo_texto, $id_item);
            $stmtU->execute();

            // Gestión de visibilidad SOLO para este form_id
            if ($estado_deseado === 0) {
                $stmtIns->bind_param("ii", $id_item, $form_id_actual);
                $stmtIns->execute();
            } else {
                $stmtDel->bind_param("ii", $id_item, $form_id_actual);
                $stmtDel->execute();
            }
        }

        // También guardar label y visibilidad del campo entero si vienen en el POST
        $field_id_campo = (int)($_POST["field_id"] ?? 0);
        if ($field_id_campo) {
            $nuevo_label = trim($_POST["label"] ?? "");
            $ocultar_campo = (int)($_POST["campo_oculto"] ?? 0);
            if ($nuevo_label) {
                $stmtL = $mysqli->prepare("UPDATE ost_form_field SET label = ?, updated = NOW() WHERE id = ?");
                $stmtL->bind_param("si", $nuevo_label, $field_id_campo);
                $stmtL->execute();
            }
            if ($ocultar_campo === 1) {
                $stmtIns->bind_param("ii", $field_id_campo, $form_id_actual);
                $stmtIns->execute();
            } else {
                $stmtDel->bind_param("ii", $field_id_campo, $form_id_actual);
                $stmtDel->execute();
            }
        }

        header("Location: options.php?tipo=$tipo_gestion&open=$id_lista_padre&ok=1");
        exit;
    }

    if ($accion === 'guardar_campo_lista') {
        $field_id    = (int)$_POST['field_id'];
        $nuevo_label = trim($_POST['label'] ?? '');
        $ocultar     = (int)$_POST['campo_oculto'];
        $open_lista  = (int)$_POST['open_lista'];

        if ($nuevo_label) {
            $stmtL = $mysqli->prepare("UPDATE ost_form_field SET label = ?, updated = NOW() WHERE id = ?");
            $stmtL->bind_param("si", $nuevo_label, $field_id);
            $stmtL->execute();
        }

        if ($ocultar === 1) {
            $stmtH = $mysqli->prepare("INSERT INTO custom_hidden_fields (field_id, form_id, hidden) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hidden = 1");
            $stmtH->bind_param("ii", $field_id, $form_id_actual);
        } else {
            $stmtH = $mysqli->prepare("DELETE FROM custom_hidden_fields WHERE field_id = ? AND form_id = ?");
            $stmtH->bind_param("ii", $field_id, $form_id_actual);
        }
        $stmtH->execute();

        header("Location: options.php?tipo=$tipo_gestion&open=$open_lista&ok=1");
        exit;
    }
}

    // ── ACCION: CREAR NUEVA LISTA DESPLEGABLE + CAMPO EN EL FORM ──────────────
    if ($accion === 'crear_lista') {
        $nuevo_nombre  = trim($_POST['nuevo_nombre_lista'] ?? '');
        $ocultar       = (int)($_POST['nuevo_lista_oculta'] ?? 0);
        if (!$nuevo_nombre) { header("Location: options.php?tipo=$tipo_gestion&err=1"); exit; }

        // 1. Crear la lista en ost_list
        $stmtL = $mysqli->prepare("INSERT INTO ost_list (name, name_plural, sort_mode, masks, configuration, created, updated) VALUES (?, ?, 'Alpha', 0, '', NOW(), NOW())");
        $plural = $nuevo_nombre . 's';
        $stmtL->bind_param("ss", $nuevo_nombre, $plural);
        $stmtL->execute();
        $nuevo_list_id = (int)$mysqli->insert_id;

        if (!$nuevo_list_id) { header("Location: options.php?tipo=$tipo_gestion&err=2"); exit; }

        // 2. Calcular sort
        $resSort = $mysqli->query("SELECT COALESCE(MAX(sort),0)+1 AS next_sort FROM ost_form_field WHERE form_id = $form_id_actual");
        $next_sort = $resSort ? (int)$resSort->fetch_assoc()['next_sort'] : 1;

        // 3. Crear el campo en ost_form_field
        $nuevo_type   = 'list-' . $nuevo_list_id;
        $nuevo_name   = 'lista_' . time();
        $nuevo_flags  = 30465;
        $nuevo_config = '{"multiselect":false,"widget":"dropdown","validator":"","regex":""}';
        $stmtF = $mysqli->prepare("INSERT INTO ost_form_field (form_id, type, label, name, hint, sort, flags, configuration, created, updated) VALUES (?, ?, ?, ?, '', ?, ?, ?, NOW(), NOW())");
        $stmtF->bind_param("isssiis", $form_id_actual, $nuevo_type, $nuevo_nombre, $nuevo_name, $next_sort, $nuevo_flags, $nuevo_config);
        $stmtF->execute();
        $nuevo_field_id = (int)$mysqli->insert_id;

        // 4. Visibilidad inicial
        if ($ocultar === 1 && $nuevo_field_id) {
            $stmtH = $mysqli->prepare("INSERT INTO custom_hidden_fields (field_id, form_id, hidden) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hidden = 1");
            $stmtH->bind_param("ii", $nuevo_field_id, $form_id_actual);
            $stmtH->execute();
        }

        header("Location: options.php?tipo=$tipo_gestion&ok=1&nuevo=lista");
        exit;
    }

    // ── ACCION: CREAR CAMPO DESPLEGABLE SOBRE LISTA EXISTENTE ─────────────────
    if ($accion === 'crear_campo_lista') {
        $list_id_existente = (int)$_POST['list_id_existente'];
        $nuevo_label       = trim($_POST['nuevo_label_campo'] ?? '');
        $ocultar           = (int)($_POST['nuevo_campo_oculto'] ?? 0);
        if (!$nuevo_label || !$list_id_existente) { header("Location: options.php?tipo=$tipo_gestion&err=3"); exit; }

        $resSort = $mysqli->query("SELECT COALESCE(MAX(sort),0)+1 AS next_sort FROM ost_form_field WHERE form_id = $form_id_actual");
        $next_sort = $resSort ? (int)$resSort->fetch_assoc()['next_sort'] : 1;
        $nuevo_type   = 'list-' . $list_id_existente;
        $nuevo_name   = 'campo_lista_' . time();
        $nuevo_flags  = 30465;
        $nuevo_config = '{"multiselect":false,"widget":"dropdown","validator":"","regex":""}';
        $stmtF = $mysqli->prepare("INSERT INTO ost_form_field (form_id, type, label, name, hint, sort, flags, configuration, created, updated) VALUES (?, ?, ?, ?, '', ?, ?, ?, NOW(), NOW())");
        $stmtF->bind_param("isssiis", $form_id_actual, $nuevo_type, $nuevo_label, $nuevo_name, $next_sort, $nuevo_flags, $nuevo_config);
        $stmtF->execute();
        $nuevo_field_id = (int)$mysqli->insert_id;

        if ($ocultar === 1 && $nuevo_field_id) {
            $stmtH = $mysqli->prepare("INSERT INTO custom_hidden_fields (field_id, form_id, hidden) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hidden = 1");
            $stmtH->bind_param("ii", $nuevo_field_id, $form_id_actual);
            $stmtH->execute();
        }

        header("Location: options.php?tipo=$tipo_gestion&ok=1&nuevo=campo");
        exit;
    }

    // ── ACCION: AÑADIR OPCION A LISTA EXISTENTE ───────────────────────────────
    if ($accion === 'crear_opcion') {
        $list_id_opcion = (int)$_POST['list_id_opcion'];
        $nuevo_valor    = trim($_POST['nuevo_valor_opcion'] ?? '');
        $ocultar        = (int)($_POST['nueva_opcion_oculta'] ?? 0);
        if (!$nuevo_valor || !$list_id_opcion) { header("Location: options.php?tipo=$tipo_gestion&err=4"); exit; }

        $resSort = $mysqli->query("SELECT COALESCE(MAX(sort),0)+1 AS next_sort FROM ost_list_items WHERE list_id = $list_id_opcion");
        $next_sort = $resSort ? (int)$resSort->fetch_assoc()['next_sort'] : 1;

        $stmtI = $mysqli->prepare("INSERT INTO ost_list_items (list_id, status, value, extra, sort, properties) VALUES (?, 1, ?, NULL, ?, '[]')");
        $stmtI->bind_param("isi", $list_id_opcion, $nuevo_valor, $next_sort);
        $stmtI->execute();
        $nuevo_item_id = (int)$mysqli->insert_id;

        if ($ocultar === 1 && $nuevo_item_id) {
            $stmtH = $mysqli->prepare("INSERT INTO custom_hidden_fields (field_id, form_id, hidden) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hidden = 1");
            $stmtH->bind_param("ii", $nuevo_item_id, $form_id_actual);
            $stmtH->execute();
        }

        header("Location: options.php?tipo=$tipo_gestion&ok=1&nuevo=opcion");
        exit;
    }

/* =========================================================
   5. CARGA DE LISTAS (SEGURIDAD PARA PANELES VACÍOS)
   ========================================================= */
$open_list_id = isset($_GET['open']) ? (int)$_GET['open'] : 0;
$cuestionario_listas = [];

$sqlMain = "SELECT f.id as field_id, f.label, CAST(SUBSTRING(f.type, 6) AS UNSIGNED) as id_real_lista, COALESCE(h.hidden, 0) as campo_oculto_aqui FROM ost_form_field f LEFT JOIN custom_hidden_fields h ON h.field_id = f.id AND h.form_id = $form_id_actual WHERE f.form_id = $form_id_actual AND f.type LIKE 'list-%' ORDER BY f.sort ASC";

$resMain = $mysqli->query($sqlMain);
if($resMain) while ($row = $resMain->fetch_assoc()) { $cuestionario_listas[] = $row; }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Desplegables - <?php echo $nombre_seccion; ?></title>
    <link rel="icon" type="image/png" href="/osticket/custom/img/gestor-listas-desplegables.png?v=2">
    
    <style>
        /* =========================================================
           6. CSS: DISEÑO CORPORATIVO Y BOTONES ESTILIZADOS
           ========================================================= */
        :root {
            --azul-atu: #000099;
            --azul-btn: #2563eb;
            --verde-ok: #16a34a;
            --naranja-oculto: #f97316;
            --rojo-cerrar: #ef4444;
            --gris-bg: #f8fafc;
            --gris-borde: #e2e8f0;
            --texto-base: #1e293b;
        }

        * { box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: var(--azul-atu); 
            margin: 0; padding: 20px; 
            color: var(--texto-base);
            overflow-x: hidden;
        }

        /* Animaciones */
        @keyframes slideToast { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeInCard { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes popModal { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes pulseActive { 0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); } 100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); } }

        .main-wrapper { 
            max-width: 1100px; margin: 0 auto; 
            background: #ffffff; border-radius: 25px; 
            box-shadow: 0 30px 70px rgba(0,0,0,0.5); 
            animation: popModal 0.4s ease-out;
        }
        /* Primero y último hijo heredan el border-radius del wrapper */
        .main-wrapper > *:first-child { border-radius: 25px 25px 0 0; overflow: hidden; }
        .main-wrapper > *:last-child  { border-radius: 0 0 25px 25px; overflow: hidden; }

        /* Cabecera ATU con Logo */
        .header-brand { padding: 35px; text-align: center; border-bottom: 1px solid var(--gris-borde); background: #fff; }
        .header-brand img { max-width: 320px; transition: 0.3s; }
        .header-brand img:hover { transform: scale(1.03); }

        /* Barra de Navegación (Botones Estilizados Restaurados) */
        .nav-bar { 
            display: flex; gap: 15px; padding: 18px 35px; 
            background: var(--gris-bg); border-bottom: 1px solid var(--gris-borde); 
            align-items: center; flex-wrap: wrap;
        }
        .btn-nav-styled { 
            border: none; padding: 12px 24px; border-radius: 14px; 
            background: #fff; color: #1e293b; 
            cursor: pointer; font-weight: 850; font-size: 14px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03); transition: all 0.3s;
        }
        .btn-nav-styled:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,0.08); }
        .btn-nav-styled.active { background: var(--azul-btn); color: #fff; animation: pulseActive 2s infinite; }
        
        /* Botones Degradados Originales */
        .btn-grad-ocultos { margin-left: auto; background: linear-gradient(135deg, #22c55e, var(--azul-btn)); color: white !important; }
        .btn-grad-manual { background: linear-gradient(135deg, #fbbf24, #ef4444); color: white !important; }

        /* Contenido Central */
        .body-container { padding: 40px; }
        .page-header { margin: 0 0 35px 0; font-size: 26px; font-weight: 900; color: var(--azul-atu); display: flex; align-items: center; gap: 15px; }
        .page-header::before { content:''; width:8px; height:32px; background:var(--azul-btn); border-radius:10px; }

        /* Tarjetas Estilo Captura */
        .list-panel { 
            background: white; border: 1.5px solid var(--gris-borde); 
            border-radius: 22px; padding: 25px; margin-bottom: 20px; 
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            animation: fadeInCard 0.6s ease-out backwards;
        }
        .list-panel:hover { border-color: var(--azul-btn); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .list-panel.is-expanded { border: 2.5px solid var(--azul-btn); background: #f9fbff; transform: scale(1.01); }

        .list-panel-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; }
        .list-panel-header h3 { margin: 0; font-size: 18px; font-weight: 800; color: #334155; line-height: 1.4; flex: 1; }
        .list-panel-header-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; flex-shrink: 0; }

        /* Botones Acción UI */
        .btn-ui-action { padding: 12px 26px; border-radius: 12px; font-weight: 850; font-size: 13px; text-decoration: none; transition: 0.3s; border: none; cursor: pointer; }
        .btn-ui-edit { background: var(--azul-btn); color: white; }
        .btn-ui-close { background: var(--rojo-cerrar); color: white; }

        /* Panel de Edición de Opciones */
        .editor-wrapper { margin-top: 25px; border-top: 2px dashed #cbd5e1; padding-top: 25px; }
        .opt-edit-row { 
            display: flex; gap: 15px; align-items: center; 
            padding: 14px; border-radius: 18px; margin-bottom: 12px;
            transition: 0.3s;
        }
        .opt-edit-row:hover { background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.04); }
        
        .opt-input { 
            flex: 1; padding: 14px 20px; border-radius: 15px; 
            border: 1.8px solid var(--gris-borde); font-size: 15px;
            background: #fff; transition: 0.3s;
        }
        .opt-input:focus { border-color: var(--azul-btn); box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.08); outline: none; }

        /* Botones Pill de Estado */
        .pills-box { display: flex; gap: 8px; background: #f1f5f9; padding: 5px; border-radius: 30px; }
        .pill-item { 
            border: none; padding: 9px 22px; border-radius: 25px; 
            font-weight: 900; font-size: 11px; cursor: pointer; 
            background: transparent; color: #64748b; transition: 0.3s;
        }
        /* COLORES PEDIDOS */
        .pill-item.st-active { background: var(--verde-ok); color: white; }
        .pill-item.st-hidden { background: var(--naranja-oculto); color: white; }

        .btn-save-ui { background: var(--azul-atu); color: white; padding: 12px 24px; border-radius: 12px; border: none; font-weight: 900; cursor: pointer; transition: 0.3s; }
        .btn-save-ui:hover { transform: scale(1.06); filter: brightness(1.2); }

        .btn-save-lista {
            background: linear-gradient(135deg, var(--azul-atu), var(--azul-btn));
            color: white; padding: 10px 22px; border-radius: 12px; border: none;
            font-weight: 900; font-size: 13px; cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,153,0.22); white-space: nowrap;
        }
        .btn-save-lista:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 20px rgba(0,0,153,0.35); }

        /* Sistema de Toasts */
        .toast-container { position: fixed; top: 35px; right: 35px; z-index: 100001; pointer-events: none; }
        .toast-card { 
            background: #ffffff; border-left: 8px solid var(--verde-ok); padding: 22px 30px; 
            border-radius: 22px; box-shadow: 0 25px 55px rgba(0,0,0,0.25); 
            margin-bottom: 15px; display: flex; align-items: center; gap: 18px;
            animation: slideToast 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: auto;
        }

        /* Modal Pop-up — scroll bloqueado en fondo */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.70);
            backdrop-filter: blur(10px);
            display: none; align-items: center; justify-content: center;
            z-index: 10000;
            /* evita que el body haga scroll mientras el modal está abierto */
        }
        .modal-overlay.active { display: flex; }
        body.modal-open { overflow: hidden; }

        .modal-content-box {
            background: #fff;
            width: 92%; max-width: 860px;
            border-radius: 28px;
            box-shadow: 0 45px 100px rgba(0,0,0,0.55);
            animation: popModal 0.3s ease-out;
            /* flex column para que sólo el body haga scroll */
            display: flex; flex-direction: column;
            max-height: 88vh;
            overflow: hidden; /* no overflow en el contenedor */
        }

        /* ── Cabecera fija ── */
        .modal-header-styled {
            padding: 26px 35px 22px;
            border-bottom: 1.5px solid var(--gris-borde);
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(to right, #fff, var(--gris-bg));
            flex-shrink: 0;
        }

        /* ── Buscador fijo ── */
        .modal-search-wrap {
            padding: 16px 35px 0;
            flex-shrink: 0;
        }

        /* ── Pestañas fijas — mismo estilo que nav-bar ── */
        .modal-tabs-bar {
            display: flex; gap: 0; padding: 0 35px;
            border-bottom: 1.5px solid var(--gris-borde);
            background: var(--gris-bg);
            flex-shrink: 0;
        }
        .modal-tab-btn {
            background: none; border: none; cursor: pointer;
            padding: 14px 24px; font-size: 14px; font-weight: 850;
            color: #94a3b8;
            border-bottom: 3px solid transparent;
            margin-bottom: -1.5px;
            display: flex; align-items: center; gap: 9px;
            transition: color 0.2s;
        }
        .modal-tab-btn:hover { color: #334155; }
        .modal-tab-btn.active { color: var(--azul-btn); border-bottom-color: var(--azul-btn); }
        .modal-tab-badge {
            font-size: 11px; font-weight: 900; padding: 3px 9px;
            border-radius: 20px; background: #f1f5f9; color: #64748b;
            transition: 0.2s;
        }
        .modal-tab-btn.active .modal-tab-badge { background: #dbeafe; color: var(--azul-btn); }

        /* ── Cuerpo: ÚNICO lugar con scroll ── */
        .modal-body-area {
            flex: 1;
            min-height: 0;      /* clave: sin esto flex no recorta */
            overflow-y: auto;
            padding: 28px 35px 35px;
            overscroll-behavior: contain; /* no propaga scroll al body */
        }

        /* ── Paneles de pestaña ── */
        .modal-tab-panel { display: none; }
        .modal-tab-panel.active { display: block; }

        /* ── Tarjetas de elemento oculto — mismo estilo que list-panel ── */
        .modal-item-card {
            background: var(--gris-bg);
            border: 1.5px solid var(--gris-borde);
            border-radius: 18px;
            padding: 18px 22px;
            margin-bottom: 12px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            transition: all 0.25s;
        }
        .modal-item-card:hover { background: #fff; box-shadow: 0 5px 18px rgba(0,0,0,0.06); transform: translateX(4px); }
        .modal-item-card .item-nombre { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .modal-item-card .item-meta  { font-size: 12px; color: #64748b; }
        .modal-item-card .badge-tipo-campo  { background: #ede9fe; color: #7c3aed; font-size: 11px; font-weight: 900; padding: 2px 10px; border-radius: 20px; }
        .modal-item-card .badge-tipo-opcion { background: #f0fdf4; color: var(--verde-ok); font-size: 11px; font-weight: 900; padding: 2px 10px; border-radius: 20px; }

        /* Cabecera de grupo dentro del panel */
        .modal-grupo-header {
            display: flex; align-items: center; gap: 10px;
            margin: 20px 0 10px;
        }
        .modal-grupo-header:first-child { margin-top: 4px; }
        .modal-grupo-label {
            font-size: 11px; font-weight: 900; color: #94a3b8;
            letter-spacing: .08em; text-transform: uppercase;
        }
        .modal-grupo-line { flex: 1; height: 1px; background: var(--gris-borde); }
        
        /* (hidden-row-ui reemplazado por modal-item-card) */

        /* Buscador */
        .buscador-wrap { margin-bottom: 25px; }
        .buscador-input {
            width: 100%; padding: 14px 20px; border-radius: 15px;
            border: 1.8px solid var(--gris-borde); font-size: 15px;
            background: #fff; transition: 0.3s; outline: none;
        }
        .buscador-input:focus { border-color: var(--azul-btn); box-shadow: 0 0 0 5px rgba(37,99,235,0.08); }

        /* Buscador interno de opciones */
        .buscador-opciones-wrap { margin-bottom: 16px; }
        .buscador-opciones-input {
            width: 100%; padding: 11px 18px; border-radius: 12px;
            border: 1.8px solid var(--gris-borde); font-size: 14px;
            background: #fff; transition: 0.3s; outline: none;
        }
        .buscador-opciones-input:focus { border-color: var(--azul-btn); box-shadow: 0 0 0 4px rgba(37,99,235,0.08); }

        /* Badge visibilidad campo */
        .badge-campo-oculto  { background: #fff7ed; color: var(--naranja-oculto); padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; }
        .badge-campo-visible { background: #f0fdf4; color: var(--verde-ok);      padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; }

        /* Panel con campo oculto: borde azul */
        .list-panel.campo-oculto { border-color: var(--azul-btn) !important; background: #f9fbff; }

        /* Botón Cerrar Sesión (igual que Gestor.php) */

        /* ── Formularios inline de creación (igual estilo que fields.php) ── */
        .nuevo-inline {
            margin-bottom: 28px; border-radius: 20px;
            transition: all 0.35s cubic-bezier(0.165,0.84,0.44,1);
            box-shadow: 0 6px 20px rgba(37,99,235,0.18);
        }
        /* El border-radius lo aplican los hijos directos */
        .nuevo-toggle          { border-radius: 20px; }
        .is-open .nuevo-toggle { border-radius: 20px 20px 0 0; }
        .is-open .nuevo-form-body { border-radius: 0 0 20px 20px; overflow: hidden; }
        .nuevo-toggle {
            width: 100%; border: none; cursor: pointer;
            padding: 18px 28px; display: flex; align-items: center; gap: 14px;
            color: #fff; font-size: 15px; font-weight: 800;
            transition: all 0.25s; text-align: left;
            background: linear-gradient(135deg, #1a6fff 0%, #0047d4 100%);
            letter-spacing: 0.02em;
        }
        .nuevo-toggle:hover { background: linear-gradient(135deg, #0f5fe8 0%, #0038b8 100%); padding-left: 32px; }
        .is-open .nuevo-toggle { background: linear-gradient(135deg, #0d4fcf 0%, #002fa0 100%); }
        .nuevo-toggle-icon {
            width: 32px; height: 32px; border-radius: 50%;
            background: rgba(255,255,255,0.22); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 400; line-height: 1;
            transition: transform 0.35s cubic-bezier(0.68,-0.55,0.265,1.55);
            flex-shrink: 0; border: 2px solid rgba(255,255,255,0.35);
        }
        .is-open .nuevo-toggle-icon { transform: rotate(45deg); }
        .nuevo-form-body {
            display: none;
            background: linear-gradient(160deg, #0a2a6e 0%, #0047d4 100%);
        }
        .is-open .nuevo-form-body { display: block; animation: fadeInCard 0.3s ease-out; }
        .nuevo-form-inner { padding: 24px 28px; }
        .nuevo-form-label {
            font-size: 10px; font-weight: 900; color: rgba(148,163,184,1);
            letter-spacing: 0.1em; text-transform: uppercase; display: block; margin-bottom: 8px;
        }
        .nuevo-form-input {
            width: 100%; padding: 14px 20px; border-radius: 14px;
            border: 1.5px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.08); color: #f1f5f9;
            font-size: 15px; outline: none; transition: 0.3s; margin-bottom: 16px;
        }
        .nuevo-form-input::placeholder { color: rgba(148,163,184,0.7); }
        .nuevo-form-input:focus { border-color: rgba(96,165,250,0.8); background: rgba(255,255,255,0.12); box-shadow: 0 0 0 4px rgba(37,99,235,0.25); }
        .nuevo-form-select {
            width: 100%; padding: 13px 18px; border-radius: 14px;
            border: 1.5px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.08); color: #f1f5f9;
            font-size: 14px; outline: none; transition: 0.3s; margin-bottom: 16px;
            appearance: none; cursor: pointer;
        }
        .nuevo-form-select option { background: #1e3a8a; color: #f1f5f9; }
        .nuevo-form-actions {
            display: flex; gap: 12px; justify-content: flex-end;
            padding: 16px 28px 20px;
            background: rgba(0,0,0,0.25);
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .btn-cancelar-nuevo {
            padding: 11px 24px; border-radius: 12px;
            border: 1.5px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08); color: #cbd5e1;
            font-weight: 800; cursor: pointer; font-size: 13px; transition: 0.2s;
        }
        .btn-cancelar-nuevo:hover { background: rgba(255,255,255,0.14); color: #fff; }
        .btn-crear-nuevo {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white; padding: 11px 26px; border-radius: 12px; border: none;
            font-weight: 900; font-size: 13px; cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 14px rgba(22,163,74,0.4); display: flex; align-items: center; gap: 8px;
        }
        .btn-crear-nuevo:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 22px rgba(22,163,74,0.55); }
        .nuevo-pills-box { display: inline-flex; gap: 4px; background: rgba(255,255,255,0.08); padding: 4px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.12); }
        .nuevo-pill {
            border: none; padding: 7px 14px; border-radius: 25px;
            font-weight: 900; font-size: 11px; cursor: pointer;
            background: transparent; color: rgba(148,163,184,1); transition: 0.3s;
        }
        .nuevo-pill.st-active { background: var(--verde-ok); color: white; }
        .nuevo-pill.st-hidden { background: var(--naranja-oculto); color: white; }
        /* Botón añadir opción dentro del panel abierto */
        .btn-add-opcion {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 12px; border: 2px dashed rgba(37,99,235,0.4);
            background: #f9fbff; color: var(--azul-btn); font-weight: 800; font-size: 13px;
            cursor: pointer; transition: 0.3s; margin-top: 10px; width: 100%;
            justify-content: center;
        }
        .btn-add-opcion:hover { background: #eff6ff; border-color: var(--azul-btn); }
        .modo-pill {
            padding: 10px 16px; border-radius: 12px; border: 2px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08); color: rgba(203,213,225,1);
            font-weight: 800; font-size: 13px; cursor: pointer; transition: 0.2s;
        }
        .modo-pill:hover { background: rgba(255,255,255,0.14); color: #fff; }
        .modo-pill--active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white; border-color: #3b82f6;
            box-shadow: 0 4px 14px rgba(37,99,235,0.45);
        }
        /* ── Custom select con busqueda ── */
        .custom-select-wrap { margin-bottom: 16px; }

        .custom-select-trigger {
            display: flex; justify-content: space-between; align-items: center;
            padding: 13px 18px; border-radius: 14px;
            border: 1.5px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.12); color: rgba(148,163,184,0.85);
            font-size: 14px; cursor: pointer; transition: 0.2s; user-select: none;
        }
        .custom-select-trigger:hover { background: rgba(255,255,255,0.18); border-color: rgba(96,165,250,0.7); }
        .custom-select-trigger.is-open { border-color: #60a5fa; background: rgba(255,255,255,0.18); border-radius: 14px 14px 0 0; }
        .custom-select-trigger.has-value { color: #ffffff; font-weight: 700; }
        .custom-select-arrow { font-size: 12px; opacity: 0.7; transition: transform 0.22s; flex-shrink: 0; }
        .custom-select-trigger.is-open .custom-select-arrow { transform: rotate(180deg); opacity: 1; color: #60a5fa; }

        /* Dropdown en flujo normal — nunca se corta por overflow de ancestros */
        .custom-select-dropdown {
            display: none;
            background: #162d6e;
            border: 1.5px solid #60a5fa; border-top: none;
            border-radius: 0 0 14px 14px;
            margin-bottom: 4px;
        }
        .custom-select-dropdown.is-open { display: block; animation: fadeInCard 0.16s ease-out; }

        .custom-select-search {
            display: block; width: 100%; padding: 10px 14px;
            border: none; border-bottom: 1px solid rgba(255,255,255,0.12);
            background: rgba(0,0,0,0.18); color: #f1f5f9; font-size: 13px; outline: none;
            box-sizing: border-box;
        }
        .custom-select-search::placeholder { color: rgba(148,163,184,0.6); }
        .custom-select-search:focus { background: rgba(0,0,0,0.25); }

        /* Sin max-height ni scroll — todas las opciones visibles */
        .custom-select-options { max-height: 260px; overflow-y: auto; padding-bottom: 6px; scrollbar-width: thin; scrollbar-color: rgba(96,165,250,0.35) transparent; }
        .custom-select-options::-webkit-scrollbar { width: 5px; }
        .custom-select-options::-webkit-scrollbar-track { background: transparent; }
        .custom-select-options::-webkit-scrollbar-thumb { background: rgba(96,165,250,0.4); border-radius: 99px; }

        .custom-select-option {
            padding: 12px 18px; color: #c7d4f0; font-size: 14px; cursor: pointer;
            transition: background 0.12s;
        }
        .custom-select-option:last-child { border-radius: 0 0 12px 12px; }
        .custom-select-option:hover    { background: rgba(96,165,250,0.2); color: #fff; }
        .custom-select-option.selected { background: rgba(37,99,235,0.55); color: #fff; font-weight: 700; }
        .custom-select-option.selected::after { content: " ✓"; color: #93c5fd; }
        .custom-select-option.hidden   { display: none !important; }
        .add-opcion-form {
            display: none; margin-top: 12px; padding: 18px;
            background: #f9fbff; border-radius: 16px;
            border: 1.5px dashed var(--azul-btn);
        }
        .add-opcion-form.is-open { display: block; animation: fadeInCard 0.25s ease-out; }
        .btn-logout {
            position: fixed; top: 16px; right: 18px; z-index: 99999;
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 14px;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff; font-size: 13px; font-weight: 900;
            text-decoration: none; letter-spacing: .3px;
            box-shadow: 0 6px 18px rgba(220,38,38,.35);
            transition: opacity .25s, transform .25s;
        }
        .btn-logout:hover { filter: brightness(1.08); transform: translateY(-1px); }
        .btn-logout .dot { width: 10px; height: 10px; border-radius: 999px; background: #fff; box-shadow: 0 0 0 4px rgba(255,255,255,0.18); }
        .btn-logout.oculto-scroll { opacity: 0; transform: translateY(-14px) scale(.98); pointer-events: none; }
    </style>
</head>
<body>

<!-- Botón Cerrar Sesión -->
<a class="btn-logout" id="btnLogout" href="options.php?tipo=<?php echo $tipo_gestion; ?>&logout=1" title="Cerrar sesión">
    <span class="dot"></span>
    Cerrar sesión
</a>
<script>
(function(){
    const btn = document.getElementById('btnLogout');
    if (!btn) return;
    const THRESHOLD = 40;
    let ticking = false;
    function update(){
        const y = window.scrollY || document.documentElement.scrollTop || 0;
        btn.classList.toggle('oculto-scroll', y > THRESHOLD);
        ticking = false;
    }
    window.addEventListener('scroll', function(){
        if (!ticking) { requestAnimationFrame(update); ticking = true; }
    }, { passive:true });
    update();
})();
</script>
<div class="toast-container" id="notifArea">
    <?php if(isset($_GET['ok'])): ?>
        <div class="toast-card" id="toastSucc">
            <div style="background: #dcfce7; color: var(--verde-ok); width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:18px;">✓</div>
            <div>
                <?php if (isset($_GET['nuevo']) && $_GET['nuevo'] === 'lista'): ?>
                    <b style="display:block; font-size:17px; color:var(--azul-atu);">¡Lista creada!</b>
                    <span style="font-size:13px; color:#64748b;">La nueva lista desplegable ya está en <?php echo $nombre_seccion; ?>.</span>
                <?php elseif (isset($_GET['nuevo']) && $_GET['nuevo'] === 'campo'): ?>
                    <b style="display:block; font-size:17px; color:var(--azul-atu);">¡Campo creado!</b>
                    <span style="font-size:13px; color:#64748b;">El nuevo campo desplegable se ha añadido a <?php echo $nombre_seccion; ?>.</span>
                <?php elseif (isset($_GET['nuevo']) && $_GET['nuevo'] === 'opcion'): ?>
                    <b style="display:block; font-size:17px; color:var(--azul-atu);">¡Opción añadida!</b>
                    <span style="font-size:13px; color:#64748b;">La nueva opción se ha añadido a la lista correctamente.</span>
                <?php else: ?>
                    <b style="display:block; font-size:17px; color:var(--azul-atu);">¡Configuración Sincronizada!</b>
                    <span style="font-size:13px; color:#64748b;">Los cambios se han guardado exclusivamente para <?php echo $nombre_seccion; ?>.</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="main-wrapper">
    <!-- CABECERA CON LOGO -->
    <div class="header-brand">
        <a href="gestor_<?php echo $tipo_gestion; ?>.php">
            <img src="/osticket/custom/img/gestor-listas-desplegables.png?v=2" alt="ATU Formación">
        </a>
    </div>

    <!-- NAVEGACIÓN ESTILIZADA RESTAURADA -->
    <div class="nav-bar">
        <a href="gestor_<?php echo $tipo_gestion; ?>.php" class="btn-nav-styled">Atrás</a>
        <a href="fields.php?tipo=<?php echo $tipo_gestion; ?>" class="btn-nav-styled">Campos base</a>
        <button class="btn-nav-styled active">Listas desplegables</button>
        
        <button class="btn-nav-styled btn-grad-ocultos" onclick="abrirOcultosPopup()">👁 Ver Ocultos</button>
        <a href="ayuda.php" class="btn-nav-styled btn-grad-manual">📘 Manual de Operaciones</a>
    </div>

    <!-- CUERPO PRINCIPAL -->
    <div class="body-container">
        <h2 class="page-header">Personalizar Cuestionario: <?php echo $nombre_seccion; ?></h2>
        
        <!-- ── BLOQUE ÚNICO: CREAR DESPLEGABLE (LISTA NUEVA O CAMPO EN LISTA EXISTENTE) ── -->
        <div class="nuevo-inline" id="nuevoWrap">
            <button type="button" class="nuevo-toggle" onclick="toggleNuevo('nuevoWrap')">
                <span class="nuevo-toggle-icon" id="nuevoWrapIcon">＋</span>
                <span style="flex:1;">Añadir nuevo desplegable</span>
            </button>
            <div class="nuevo-form-body">
                <!-- Selector de modo -->
                <div style="display:flex; gap:10px; padding:20px 28px 0;">
                    <button type="button" class="modo-pill modo-pill--active" id="modo-campo-existente" onclick="setModo('campo_existente')" style="flex:1;">🔗 Campo en lista existente</button>
                    <button type="button" class="modo-pill" id="modo-nueva-lista" onclick="setModo('nueva_lista')" style="flex:1;">📋 Nueva lista + campo</button>
                </div>

                <!-- Modo 1: Nueva lista -->
                <form method="post" action="options.php?tipo=<?php echo $tipo_gestion; ?>" id="form-nueva-lista" style="display:none;">
                    <input type="hidden" name="accion" value="crear_lista">
                    <div class="nuevo-form-inner">
                        <label class="nuevo-form-label">📋 Nombre de la nueva lista *</label>
                        <input type="text" name="nuevo_nombre_lista" class="nuevo-form-input" placeholder="Ej: Motivos de baja" required>
                        <label class="nuevo-form-label">👁 Visibilidad inicial en <?php echo strtoupper($nombre_seccion); ?></label>
                        <input type="hidden" name="nuevo_lista_oculta" id="nuevo_lista_oculta_val" value="0">
                        <div class="nuevo-pills-box">
                            <button type="button" class="nuevo-pill st-active" id="pill-lista-visible" onclick="setNuevoPill('lista', 0)">Visible</button>
                            <button type="button" class="nuevo-pill" id="pill-lista-oculta" onclick="setNuevoPill('lista', 1)">Oculto</button>
                        </div>
                    </div>
                    <div class="nuevo-form-actions">
                        <button type="button" class="btn-cancelar-nuevo" onclick="toggleNuevo('nuevoWrap')">✕ Cancelar</button>
                        <button type="submit" class="btn-crear-nuevo">✅ Crear lista y campo</button>
                    </div>
                </form>

                <!-- Modo 2: Campo en lista existente -->
                <form method="post" action="options.php?tipo=<?php echo $tipo_gestion; ?>" id="form-campo-existente">
                    <input type="hidden" name="accion" value="crear_campo_lista">
                    <div class="nuevo-form-inner">
                        <label class="nuevo-form-label">📝 Nombre del campo en el cuestionario *</label>
                        <input type="text" name="nuevo_label_campo" class="nuevo-form-input" placeholder="Ej: Nivel de satisfacción" required>
                        <label class="nuevo-form-label">📋 Lista desplegable a usar *</label>
                        <input type="hidden" name="list_id_existente" id="lista-id-val" value="">
                        <div class="custom-select-wrap" id="customListaSelect">
                            <div class="custom-select-trigger" onclick="toggleCustomSelect()" id="customSelectTrigger">
                                <span id="customSelectText" style="color:rgba(148,163,184,0.8);">— Selecciona una lista —</span>
                                <span class="custom-select-arrow">▾</span>
                            </div>
                            <div class="custom-select-dropdown" id="customSelectDropdown">
                                <input type="text" class="custom-select-search" id="customSelectSearch" placeholder="Buscar lista..." oninput="filtrarListasCustom(this.value)" autocomplete="off">
                                <div class="custom-select-options" id="customSelectOptions">
                                    <?php
                                    $resAllListas2 = $mysqli->query("SELECT id, name FROM ost_list ORDER BY name ASC");
                                    if ($resAllListas2) while ($rl2 = $resAllListas2->fetch_assoc()):
                                    ?>
                                    <div class="custom-select-option" data-value="<?php echo $rl2['id']; ?>" data-label="<?php echo htmlspecialchars($rl2['name']); ?>" data-name="<?php echo strtolower(htmlspecialchars($rl2['name'])); ?>" onclick="selectLista(this)"><?php echo htmlspecialchars($rl2['name']); ?></div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                        <label class="nuevo-form-label" style="margin-top:14px;">👁 Visibilidad inicial en <?php echo strtoupper($nombre_seccion); ?></label>
                        <input type="hidden" name="nuevo_campo_oculto" id="nuevo_campo_oculto_val" value="0">
                        <div class="nuevo-pills-box">
                            <button type="button" class="nuevo-pill st-active" id="pill-campo-visible" onclick="setNuevoPill('campo', 0)">Visible</button>
                            <button type="button" class="nuevo-pill" id="pill-campo-oculto" onclick="setNuevoPill('campo', 1)">Oculto</button>
                        </div>
                    </div>
                    <div class="nuevo-form-actions">
                        <button type="button" class="btn-cancelar-nuevo" onclick="toggleNuevo('nuevoWrap')">✕ Cancelar</button>
                        <button type="submit" class="btn-crear-nuevo">✅ Crear campo</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- BUSCADOR DE DESPLEGABLES -->
        <div class="buscador-wrap">
            <input type="text" id="buscadorDesplegables" class="buscador-input" placeholder="Buscar desplegable por nombre...">
        </div>
        <div class="cards-layout-wrapper">
            <?php foreach ($cuestionario_listas as $idx => $item): ?>
                <?php 
                   $l_id = (int)$item['id_real_lista'];
                   $isOpen = ($open_list_id === $l_id); 
                ?>
                <div class="list-panel <?php echo ($isOpen ? 'is-expanded ' : '') . ($item['campo_oculto_aqui'] ? 'campo-oculto' : ''); ?>" id="lista-<?php echo $l_id; ?>" data-label="<?php echo strtolower(htmlspecialchars($item['label'])); ?>" style="animation-delay: <?php echo $idx * 0.08; ?>s">
                    <div class="list-panel-header">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <h3 style="margin:0;"><?php echo htmlspecialchars($item['label']); ?></h3>
                                <?php if ($item['campo_oculto_aqui']): ?>
                                    <span class="badge-campo-oculto">🔴 Oculto aquí</span>
                                <?php else: ?>
                                    <span class="badge-campo-visible">🟢 Visible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="list-panel-header-actions">
                            <a href="options.php?tipo=<?php echo $tipo_gestion; ?>&open=<?php echo $isOpen ? 0 : $l_id; ?>#lista-<?php echo $l_id; ?>" 
                               class="btn-ui-action <?php echo $isOpen ? 'btn-ui-close' : 'btn-ui-edit'; ?>">
                                <?php echo $isOpen ? 'Cerrar panel' : 'Editar'; ?>
                            </a>
                            <?php if ($isOpen): ?>
                            <button type="submit" form="form-lista-<?php echo $l_id; ?>" class="btn-save-lista">
                                💾 Guardar opciones
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isOpen): ?>
                        <div class="editor-wrapper">
                            <?php 
                            // CARGA INDEPENDIENTE: filtramos ocultos solo para este form_id
                            $sqlOpt = "SELECT i.*, COALESCE(h.hidden, 0) as esta_oculto_aqui 
                                       FROM ost_list_items i 
                                       LEFT JOIN custom_hidden_fields h ON h.field_id = i.id AND h.form_id = $form_id_actual
                                       WHERE i.list_id = $l_id ORDER BY i.sort ASC";
                            $resOpt = $mysqli->query($sqlOpt);
                            
                            if($resOpt && $resOpt->num_rows > 0):
                                $opciones = $resOpt->fetch_all(MYSQLI_ASSOC);
                            ?>
                            <!-- FORMULARIO ÚNICO: nombre + visibilidad del campo + opciones -->
                            <form method="post" action="options.php?tipo=<?php echo $tipo_gestion; ?>&open=<?php echo $l_id; ?>" id="form-lista-<?php echo $l_id; ?>">
                                <input type="hidden" name="accion" value="guardar_lista">
                                <input type="hidden" name="list_id" value="<?php echo $l_id; ?>">
                                <input type="hidden" name="field_id" value="<?php echo $item['field_id']; ?>">
                                <input type="hidden" name="campo_oculto" id="campo_oculto_<?php echo $l_id; ?>"
                                       value="<?php echo $item['campo_oculto_aqui']; ?>">

                                <!-- Nombre + visibilidad del desplegable entero -->
                                <div style="margin-bottom:20px; padding:20px; background:#f8fafc; border-radius:16px; border:1.5px dashed var(--azul-btn);">
                                    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                                        <div style="flex:1; min-width:200px;">
                                            <label style="display:block; font-size:12px; font-weight:900; color:#64748b; margin-bottom:6px;">NOMBRE DEL DESPLEGABLE</label>
                                            <input type="text" name="label" class="opt-input"
                                                   value="<?php echo htmlspecialchars($item['label']); ?>"
                                                   placeholder="Nombre del campo">
                                        </div>
                                        <div>
                                            <label style="display:block; font-size:12px; font-weight:900; color:#64748b; margin-bottom:6px;">VISIBILIDAD EN <?php echo strtoupper($nombre_seccion); ?></label>
                                            <div class="pills-box">
                                                <button type="button"
                                                        class="pill-item <?php echo !$item['campo_oculto_aqui'] ? 'st-active' : ''; ?>"
                                                        onclick="setCampoPill(this, 0, <?php echo $l_id; ?>)">Visible</button>
                                                <button type="button"
                                                        class="pill-item <?php echo $item['campo_oculto_aqui'] ? 'st-hidden' : ''; ?>"
                                                        onclick="setCampoPill(this, 1, <?php echo $l_id; ?>)">Oculto</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <!-- ── AÑADIR NUEVA OPCION A ESTA LISTA ── -->
                            <button type="button" class="btn-add-opcion" onclick="toggleAddOpcion(<?php echo $l_id; ?>)">
                                ＋ Añadir nueva opción a esta lista
                            </button>
                            <div class="add-opcion-form" id="add-opcion-<?php echo $l_id; ?>">
                                <!-- SIN <form> anidado — se envía por AJAX para evitar formularios anidados (inválido en HTML) -->
                                <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                                    <div style="flex:1; min-width:200px;">
                                        <label style="display:block; font-size:11px; font-weight:900; color:#64748b; margin-bottom:6px;">TEXTO DE LA NUEVA OPCIÓN</label>
                                        <input type="text" id="opc-valor-<?php echo $l_id; ?>" class="opt-input" placeholder="Ej: Opción nueva">
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:11px; font-weight:900; color:#64748b; margin-bottom:6px;">VISIBILIDAD</label>
                                        <input type="hidden" id="opc-hidden-<?php echo $l_id; ?>" value="0">
                                        <div class="pills-box">
                                            <button type="button" class="pill-item st-active" id="opc-pill-vis-<?php echo $l_id; ?>" onclick="setOpcPill(<?php echo $l_id; ?>, 0)">Activo</button>
                                            <button type="button" class="pill-item" id="opc-pill-ocu-<?php echo $l_id; ?>" onclick="setOpcPill(<?php echo $l_id; ?>, 1)">Oculto</button>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:8px;">
                                        <button type="button" class="btn-cancelar-nuevo" style="padding:10px 18px; border-radius:10px;" onclick="toggleAddOpcion(<?php echo $l_id; ?>)">✕</button>
                                        <button type="button" class="btn-crear-nuevo" style="padding:10px 18px; border-radius:10px;" onclick="submitNuevaOpcion(<?php echo $l_id; ?>, '<?php echo $tipo_gestion; ?>')">✅ Añadir</button>
                                    </div>
                                </div>
                            </div>
                                <!-- BUSCADOR INTERNO DE OPCIONES -->
                                <div class="buscador-opciones-wrap">
                                    <input type="text" class="buscador-opciones-input" placeholder="Buscar opción dentro de este desplegable..."
                                           oninput="filtrarOpciones(this, <?php echo $l_id; ?>)">
                                </div>

                                <?php foreach ($opciones as $idx => $op): ?>
                                    <input type="hidden" name="items[<?php echo $idx; ?>][id]" value="<?php echo $op['id']; ?>">
                                    <div class="opt-edit-row" data-value="<?php echo strtolower(htmlspecialchars($op['value'])); ?>">
                                        <input type="text" name="items[<?php echo $idx; ?>][value]" class="opt-input"
                                               value="<?php echo htmlspecialchars($op['value']); ?>">

                                        <!-- status real gestionado por pills -->
                                        <input type="hidden" name="items[<?php echo $idx; ?>][status]"
                                               value="<?php echo $op['esta_oculto_aqui'] ? 0 : 1; ?>"
                                               id="status-<?php echo $op['id']; ?>">
                                        <div class="pills-box">
                                            <button type="button"
                                                    class="pill-item <?php echo !$op['esta_oculto_aqui'] ? 'st-active' : ''; ?>"
                                                    onclick="setPill(this, <?php echo $op['id']; ?>, 1)">Activo</button>
                                            <button type="button"
                                                    class="pill-item <?php echo $op['esta_oculto_aqui'] ? 'st-hidden' : ''; ?>"
                                                    onclick="setPill(this, <?php echo $op['id']; ?>, 0)">Oculto</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            </form>


                            <?php else: ?>
                                <p style="text-align:center; padding:35px; color:#94a3b8;">Esta lista no contiene opciones registradas en osTicket.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- POP-UP MODAL DE OCULTOS -->
<div class="modal-overlay" id="modalOcultos">
    <div class="modal-content-box">

        <!-- CABECERA — fija, no hace scroll -->
        <div class="modal-header-styled">
            <div>
                <h3 style="margin:0 0 4px; font-size:21px; color:var(--azul-atu); display:flex; align-items:center; gap:10px;">
                    <span style="width:13px;height:13px;border-radius:50%;background:#ef4444;display:inline-block;flex-shrink:0;"></span>
                    Elementos Retirados
                </h3>
                <span style="font-size:13px; color:#64748b;">Haz clic en "Activar" para restaurar un elemento oculto</span>
            </div>
            <button onclick="cerrarOcultosPopup()" style="background:none; border:none; font-size:36px; cursor:pointer; color:#94a3b8; line-height:1; flex-shrink:0; padding:0 4px;">&times;</button>
        </div>

        <!-- BUSCADOR — fijo -->
        <div class="modal-search-wrap">
            <input type="text" id="buscadorModal" placeholder="🔍  Buscar por nombre..."
                   oninput="filtrarModal(this.value)"
                   style="width:100%; padding:11px 18px; border-radius:12px;
                          border:1.8px solid var(--gris-borde); font-size:14px; outline:none;
                          box-sizing:border-box; margin-bottom:14px; transition:0.2s;"
                   onfocus="this.style.borderColor='var(--azul-btn)'"
                   onblur="this.style.borderColor='var(--gris-borde)'">
        </div>

        <!-- PESTAÑAS — fijas -->
        <div class="modal-tabs-bar">
            <button class="modal-tab-btn active" id="mtab-incidencias" onclick="switchModalTab('incidencias')">
                🔵 Incidencias
                <span class="modal-tab-badge" id="mbadge-incidencias">0</span>
            </button>
            <button class="modal-tab-btn" id="mtab-valoraciones" onclick="switchModalTab('valoraciones')">
                🟣 Valoraciones
                <span class="modal-tab-badge" id="mbadge-valoraciones">0</span>
            </button>
            <button class="modal-tab-btn" id="mtab-valoraciones_tutores" onclick="switchModalTab('valoraciones_tutores')">
                🎓 Val. Tutores
                <span class="modal-tab-badge" id="mbadge-valoraciones_tutores">0</span>
            </button>
        </div>

        <!-- CUERPO — ÚNICO con scroll -->
        <div class="modal-body-area">
            <div class="modal-tab-panel active" id="mpanel-incidencias">
                <p style="text-align:center; color:#94a3b8; padding:50px 0;">Consultando base de datos…</p>
            </div>
            <div class="modal-tab-panel" id="mpanel-valoraciones">
                <p style="text-align:center; color:#94a3b8; padding:50px 0;">Consultando base de datos…</p>
            </div>
            <div class="modal-tab-panel" id="mpanel-valoraciones_tutores">
                <p style="text-align:center; color:#94a3b8; padding:50px 0;">Consultando base de datos…</p>
            </div>
        </div>

    </div>
</div>

<script>
/* =========================================================
   7. JAVASCRIPT: PERSISTENCIA, CONTROL DE PILLS Y POPUP
   ========================================================= */

// 1. MANTENER POSICIÓN DE SCROLL TRAS GUARDAR
(function(){
    const KEY_SCROLL = 'gestor_options_atu_pos';
    document.addEventListener('submit', () => sessionStorage.setItem(KEY_SCROLL, window.scrollY));
    window.addEventListener('load', () => {
        const storedY = sessionStorage.getItem(KEY_SCROLL);
        if(storedY) { window.scrollTo({top: parseInt(storedY), behavior: 'instant'}); sessionStorage.removeItem(KEY_SCROLL); }
    });
})();

// 2. CAMBIO DE ESTADO (PILLS VERDE/NARANJA) — referencia por id de opción
function setCampoPill(btn, valor, listaId) {
    document.getElementById('campo_oculto_' + listaId).value = valor;
    var pills = btn.parentElement.querySelectorAll('.pill-item');
    pills.forEach(function(p) { p.classList.remove('st-active', 'st-hidden'); });
    btn.classList.add(valor === 0 ? 'st-active' : 'st-hidden');
}

function setPill(btn, fieldId, val) {
    document.getElementById('status-' + fieldId).value = val;
    const all = btn.parentElement.querySelectorAll('.pill-item');
    all.forEach(p => p.classList.remove('st-active', 'st-hidden'));
    btn.classList.add(val === 1 ? 'st-active' : 'st-hidden');
}
// Alias de compatibilidad por si hay código antiguo
function setEstadoLocal(btn, val) {
    const form = btn.closest('form');
    form.querySelector('input[name="status"]').value = val;
    const all = btn.parentElement.querySelectorAll('.pill-item');
    all.forEach(p => p.classList.remove('st-active', 'st-hidden'));
    btn.classList.add(val === 1 ? 'st-active' : 'st-hidden');
}

// 3. API POP-UP — pestañas, scroll bloqueado en body, renderizado por sección
var _modalItems    = [];
var _modalTabActiva = 'incidencias';

function abrirOcultosPopup() {
    document.body.classList.add('modal-open');
    const modal = document.getElementById('modalOcultos');
    modal.classList.add('active');
    document.getElementById('buscadorModal').value = '';
    _modalTabActiva = 'incidencias';
    switchModalTab('incidencias');

    ['incidencias','valoraciones','valoraciones_tutores'].forEach(t => {
        document.getElementById('mpanel-' + t).innerHTML =
            '<p style="text-align:center;color:#94a3b8;padding:50px 0;">Consultando base de datos…</p>';
        document.getElementById('mbadge-' + t).textContent = '0';
    });

    fetch('options.php?api_ocultos_v5=1&tipo=incidencias')
        .then(r => r.json())
        .then(data => {
            _modalItems = data.items || [];
            renderModal(_modalItems);
        })
        .catch(() => {
            ['incidencias','valoraciones','valoraciones_tutores'].forEach(t => {
                document.getElementById('mpanel-' + t).innerHTML =
                    '<p style="text-align:center;color:#ef4444;padding:50px 0;">Error al cargar los datos.</p>';
            });
        });
}

function cerrarOcultosPopup() {
    document.getElementById('modalOcultos').classList.remove('active');
    document.body.classList.remove('modal-open');
}

function switchModalTab(tab) {
    _modalTabActiva = tab;
    ['incidencias','valoraciones','valoraciones_tutores'].forEach(t => {
        document.getElementById('mtab-'   + t).classList.toggle('active', t === tab);
        document.getElementById('mpanel-' + t).classList.toggle('active', t === tab);
    });
}

function renderModal(items) {
    const mapSec  = { 'Incidencias': 'incidencias', 'Valoraciones': 'valoraciones', 'Valoración de Tutores': 'valoraciones_tutores' };
    const _tipoLabel = { text: '✏️ Texto', memo: '📄 Texto largo', files: '📎 Archivo' };

    ['Incidencias','Valoraciones','Valoración de Tutores'].forEach(sec => {
        const panelId = mapSec[sec];
        const panel   = document.getElementById('mpanel-' + panelId);
        const badge   = document.getElementById('mbadge-' + panelId);
        const secItems = items.filter(i => i.seccion === sec);
        badge.textContent = secItems.length;

        if (secItems.length === 0) {
            panel.innerHTML = '<div style="text-align:center;padding:60px 0;color:#94a3b8;font-size:15px;">✅ No hay elementos ocultos en ' + sec + '.</div>';
            return;
        }

        // 3 grupos en orden fijo
        const grupos = [
            { key: 'campo',  label: '📋 Campos desplegables', badge: 'CAMPO COMPLETO', color: '#eff6ff', colorTxt: 'var(--azul-btn)' },
            { key: 'opcion', label: '🔘 Opciones de lista',   badge: 'OPCIÓN',          color: '#f0fdf4', colorTxt: 'var(--verde-ok)' },
            { key: 'base',   label: '✏️ Campos base',         badge: 'CAMPO BASE',      color: '#fff7ed', colorTxt: 'var(--naranja-oculto)' },
        ];

        let html = '';
        grupos.forEach(g => {
            const lista = secItems.filter(i => i.tipo === g.key);
            if (lista.length === 0) return;

            html += `<div class="modal-grupo-header">
                <span class="modal-grupo-label">${g.label}</span>
                <span class="modal-grupo-line"></span>
                <span style="font-size:11px;font-weight:900;color:#cbd5e1;">${lista.length}</span>
            </div>`;

            lista.forEach(i => {
                let metaHtml = '';
                if (i.tipo === 'campo') {
                    metaHtml = `<span class="item-meta">Desplegable completo oculto en ${sec}</span>`;
                } else if (i.tipo === 'opcion') {
                    metaHtml = `<span class="item-meta">📋 Desplegable: <strong>${i.pregunta_nombre}</strong></span>`;
                } else {
                    const tl = _tipoLabel[i.pregunta_nombre] || i.pregunta_nombre;
                    metaHtml = `<span class="item-meta">Tipo: <strong>${tl}</strong></span>`;
                }

                html += `<div class="modal-item-card modal-item-row"
                              data-nombre="${[i.opcion_nombre, i.pregunta_nombre, sec, g.label].join(' ').toLowerCase()}"
                              data-item-id="${i.item_id}" data-form-id="${i.form_id}">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap;">
                            <span class="item-nombre">${i.opcion_nombre}</span>
                            <span style="background:${g.color};color:${g.colorTxt};padding:3px 10px;border-radius:20px;font-size:11px;font-weight:900;">${g.badge}</span>
                        </div>
                        ${metaHtml}
                    </div>
                    <button onclick="desocultarItem(this, ${i.item_id}, ${i.form_id})"
                            class="btn-save-lista"
                            style="flex-shrink:0;padding:10px 20px;font-size:12px;border-radius:12px;">
                        ✓ Activar
                    </button>
                </div>`;
            });
        });

        panel.innerHTML = html;
    });
}

function filtrarModal(q) {
    function norm(t){ return (t||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim(); }
    const nq = norm(q);
    ['mpanel-incidencias','mpanel-valoraciones','mpanel-valoraciones_tutores'].forEach(id => {
        document.getElementById(id).querySelectorAll('.modal-item-row').forEach(row => {
            const txt = norm(row.dataset.nombre);
            row.style.display = (!nq || txt.includes(nq)) ? '' : 'none';
        });
    });
}

function desocultarItem(btn, fieldId, formId) {
    btn.disabled = true; btn.textContent = '…';
    fetch('options.php?api_desocultar=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `field_id=${fieldId}&form_id=${formId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const row = btn.closest('.modal-item-row');
            row.style.transition = 'opacity 0.3s, transform 0.3s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(28px)';
            setTimeout(() => {
                _modalItems = _modalItems.filter(x => !(x.item_id == fieldId && x.form_id == formId));
                renderModal(_modalItems);
                switchModalTab(_modalTabActiva);
                const q = document.getElementById('buscadorModal').value;
                if (q) filtrarModal(q);
            }, 320);
        } else {
            btn.disabled = false; btn.textContent = '✓ Activar';
            alert('Error al desocultar. Inténtalo de nuevo.');
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = '✓ Activar'; });
}

// cerrarOcultosPopup definido arriba junto con abrirOcultosPopup

// 4. AUTO-CERRADO TOAST
const toastSucc = document.getElementById('toastSucc');
if(toastSucc){
    setTimeout(() => {
        toastSucc.style.transition = '0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        toastSucc.style.opacity = '0';
        toastSucc.style.transform = 'translateX(50px) scale(0.9)';
        setTimeout(() => toastSucc.remove(), 600);
    }, 4500);
}

// 5. BUSCADOR DE DESPLEGABLES

function normalizarTexto(t) {
    return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}
document.getElementById('buscadorDesplegables').addEventListener('input', function() {
    const q = normalizarTexto(this.value.trim());
    document.querySelectorAll('.list-panel').forEach(function(panel) {
        const label = normalizarTexto(panel.getAttribute('data-label') || '');
        panel.style.display = label.includes(q) ? '' : 'none';
    });
});

// 6. BUSCADOR INTERNO DE OPCIONES
function filtrarOpciones(input, listaId) {
    const q = normalizarTexto(input.value.trim());
    const form = document.getElementById('form-lista-' + listaId);
    if (!form) return;
    form.querySelectorAll('.opt-edit-row').forEach(function(row) {
        const val = normalizarTexto(row.getAttribute('data-value') || '');
        row.style.display = val.includes(q) ? '' : 'none';
    });
}

document.getElementById('modalOcultos').addEventListener('click', function(e){
    if(e.target === this) cerrarOcultosPopup();
});
</script>

<script>
// ── NUEVOS FORMULARIOS INLINE ────────────────────────────────────────────

// Un solo panel abierto a la vez
function abrirPanel(listId) {
    // Cerrar todos los paneles
    document.querySelectorAll(".list-panel.is-expanded").forEach(function(p) {
        p.classList.remove("is-expanded");
    });
    // Abrir el solicitado
    var panel = document.getElementById("lista-" + listId);
    if (panel) {
        panel.classList.add("is-expanded");
        panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
}

// Toggle bloque creación
function toggleNuevo(wrapId) {
    document.getElementById(wrapId).classList.toggle("is-open");
}

// Selector de modo dentro del bloque unificado
var modoActual = "campo_existente";
function setModo(modo) {
    modoActual = modo;
    document.getElementById("form-nueva-lista").style.display     = (modo === "nueva_lista")    ? "" : "none";
    document.getElementById("form-campo-existente").style.display = (modo === "campo_existente") ? "" : "none";
    document.getElementById("modo-nueva-lista").className      = "modo-pill" + (modo === "nueva_lista"    ? " modo-pill--active" : "");
    document.getElementById("modo-campo-existente").className  = "modo-pill" + (modo === "campo_existente" ? " modo-pill--active" : "");
}

// ── Custom select con búsqueda ───────────────────────────────────────────
function toggleCustomSelect() {
    var trigger  = document.getElementById("customSelectTrigger");
    var dropdown = document.getElementById("customSelectDropdown");
    var isOpen   = dropdown.classList.contains("is-open");
    trigger.classList.toggle("is-open",  !isOpen);
    dropdown.classList.toggle("is-open", !isOpen);
    if (!isOpen) {
        var search = document.getElementById("customSelectSearch");
        search.value = "";
        document.querySelectorAll(".custom-select-option").forEach(function(o){ o.classList.remove("hidden"); });
        setTimeout(function(){ search.focus(); }, 50);
    }
}

function selectLista(el) {
    document.getElementById("lista-id-val").value = el.dataset.value;
    var textEl  = document.getElementById("customSelectText");
    var trigger = document.getElementById("customSelectTrigger");
    textEl.textContent = el.dataset.label;
    textEl.style.color = "";
    trigger.classList.remove("is-open");
    trigger.classList.add("has-value");
    document.querySelectorAll(".custom-select-option").forEach(function(o){ o.classList.remove("selected"); });
    el.classList.add("selected");
    document.getElementById("customSelectDropdown").classList.remove("is-open");
}

function _normalizar(t) {
    return (t || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
}

function filtrarListasCustom(q) {
    var nq = _normalizar(q);
    document.querySelectorAll(".custom-select-option").forEach(function(opt) {
        var nombre = _normalizar(opt.dataset.label || opt.textContent);
        opt.classList.toggle("hidden", nq !== "" && !nombre.includes(nq));
    });
}

function setNuevoPill(tipo, valor) {
    if (tipo === "lista") {
        document.getElementById("nuevo_lista_oculta_val").value = valor;
        document.getElementById("pill-lista-visible").className = "nuevo-pill" + (valor === 0 ? " st-active" : "");
        document.getElementById("pill-lista-oculta").className  = "nuevo-pill" + (valor === 1 ? " st-hidden" : "");
    } else if (tipo === "campo") {
        document.getElementById("nuevo_campo_oculto_val").value = valor;
        document.getElementById("pill-campo-visible").className = "nuevo-pill" + (valor === 0 ? " st-active" : "");
        document.getElementById("pill-campo-oculto").className  = "nuevo-pill" + (valor === 1 ? " st-hidden" : "");
    }
}

function toggleAddOpcion(listaId) {
    document.getElementById("add-opcion-" + listaId).classList.toggle("is-open");
}

function setOpcPill(listaId, valor) {
    document.getElementById("opc-hidden-" + listaId).value = valor;
    document.getElementById("opc-pill-vis-" + listaId).className = "pill-item" + (valor === 0 ? " st-active" : "");
    document.getElementById("opc-pill-ocu-" + listaId).className = "pill-item" + (valor === 1 ? " st-hidden" : "");
}

// ── ENVÍO AJAX PARA AÑADIR NUEVA OPCIÓN (evita formularios anidados inválidos en HTML) ──
function submitNuevaOpcion(listaId, tipoGestion) {
    var valorInput = document.getElementById("opc-valor-" + listaId);
    var valor = valorInput ? valorInput.value.trim() : "";
    var oculta = document.getElementById("opc-hidden-" + listaId).value || "0";

    if (!valor) {
        if (valorInput) {
            valorInput.focus();
            valorInput.style.borderColor = "#ef4444";
            setTimeout(function() { valorInput.style.borderColor = ""; }, 1800);
        }
        return;
    }

    sessionStorage.setItem("gestor_options_atu_pos", window.scrollY);

    var params = new URLSearchParams({
        accion: "crear_opcion",
        list_id_opcion: listaId,
        nuevo_valor_opcion: valor,
        nueva_opcion_oculta: oculta
    });

    fetch("options.php?tipo=" + encodeURIComponent(tipoGestion), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params.toString(),
        redirect: "manual"
    })
    .then(function() {
        window.location.href = "options.php?tipo=" + encodeURIComponent(tipoGestion) +
                               "&open=" + listaId + "&ok=1&nuevo=opcion#lista-" + listaId;
    })
    .catch(function() {
        alert("Error de red al añadir la opción. Inténtalo de nuevo.");
    });
}
</script>

</body>
</html>