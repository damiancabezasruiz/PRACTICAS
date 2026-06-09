<?php
/**
 * =============================================================================
 * fields.php — Gestor de campos base del formulario dinámico de osTicket
 * =============================================================================
 *
 * Propósito:
 *   Herramienta del panel de gestión que permite editar los campos de tipo
 *   texto, texto largo y adjunto de los formularios de incidencias (form_id=18)
 *   y valoraciones (form_id=16) de osTicket, de forma independiente por sección.
 *   Los campos de tipo lista desplegable (list-*) se gestionan en options.php.
 *
 * Contexto dinámico (?tipo=):
 *   - tipo=incidencias  → form_id = 18
 *   - tipo=valoraciones → form_id = 16
 *
 * APIs internas (parámetros GET especiales):
 *   ?api_ocultos_campos  → Devuelve JSON con todos los elementos ocultos
 *                          (campos base + campos desplegables + opciones)
 *                          para el modal "Ver Ocultos". Usado por el frontend JS.
 *   ?api_desocultar_campo → POST: elimina el registro de ocultación de un campo
 *                           en custom_hidden_fields para el form_id indicado.
 *
 * Acciones POST ($_POST['accion']):
 *   'editar_campo'  → Actualiza label y hint en ost_form_field (global para osTicket)
 *                     y gestiona la visibilidad INDEPENDIENTE por form_id en
 *                     custom_hidden_fields (INSERT/DELETE según estado deseado).
 *   'crear_campo'   → Inserta un nuevo campo en ost_form_field con el tipo
 *                     (text/memo/files), flags y configuration correctos.
 *                     Asigna el sort automáticamente al final del formulario.
 *
 * Lógica de visibilidad independiente:
 *   La tabla custom_hidden_fields (field_id, form_id, hidden) permite que
 *   el mismo campo de osTicket sea visible en Incidencias y oculto en
 *   Valoraciones (o viceversa), sin modificar ost_form_field directamente.
 *
 * Carga de datos:
 *   Consulta ost_form_field JOIN custom_hidden_fields filtrando por form_id,
 *   excluyendo tipos list-* (gestionados en options.php) e info (no editables).
 *
 * Dependencias:
 *   - db.php ($mysqli)
 *   - Sesión PHP activa con 'gestor_loggedin' = true
 *   - options.php (para la API de ocultos compartida)
 *
 * @package    GrupoATU\Gestor
 * @author     Equipo de desarrollo Grupo ATU
 * @version    2.0
 * =============================================================================
 */

/**
 * GESTOR DE CAMPOS BASE - GRUPO ATU
 * v2.0 - Independencia por form_id, diseño unificado con options.php
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

if (empty($_SESSION['gestor_loggedin']) && !(
    !empty($_COOKIE['gestor_loggedin']) && $_COOKIE['gestor_loggedin'] === 'yes'
)) {
    header('Location: Gestor.php'); exit;
}
if (!empty($_COOKIE['gestor_loggedin']) && $_COOKIE['gestor_loggedin'] === 'yes') {
    $_SESSION['gestor_loggedin'] = true;
}

/* =========================================================
   2. CONTEXTO DINÁMICO (INCIDENCIAS: 18 / VALORACIONES: 16)
   ========================================================= */
$tipo_gestion    = $_GET['tipo'] ?? 'incidencias';
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
   3. API PARA EL POP-UP DE CAMPOS OCULTOS
   ========================================================= */
if (isset($_GET['api_ocultos_campos'])) {
    header('Content-Type: application/json; charset=utf-8');

    $secciones = [
        ['form_id' => 18, 'nombre' => 'Incidencias'],
        ['form_id' => 16, 'nombre' => 'Valoraciones'],
        ['form_id' => 22, 'nombre' => 'Valoración de Tutores'],
    ];

    $items = [];
    foreach ($secciones as $sec) {
        $fid = $sec['form_id'];

        // Campos base (text, memo, files…)
        $sqlBase = "SELECT f.id as item_id, f.label as opcion_nombre, f.type as pregunta_nombre,
                           'base' as tipo, $fid as form_id, '{$sec['nombre']}' as seccion
                    FROM custom_hidden_fields h
                    INNER JOIN ost_form_field f ON f.id = h.field_id
                    WHERE h.form_id = $fid AND h.hidden = 1
                      AND f.form_id = $fid
                      AND f.type NOT LIKE 'list-%'
                      AND f.type NOT IN ('info')
                    ORDER BY f.sort ASC";
        $r1 = $mysqli->query($sqlBase);
        if ($r1) while ($row = $r1->fetch_assoc()) { $items[] = $row; }

        // Campos desplegables (lista completa) ocultos
        $sqlCampos = "SELECT f.id as item_id, f.label as opcion_nombre, f.label as pregunta_nombre,
                             'campo' as tipo, $fid as form_id, '{$sec['nombre']}' as seccion
                      FROM custom_hidden_fields h
                      INNER JOIN ost_form_field f ON f.id = h.field_id
                      WHERE h.form_id = $fid AND h.hidden = 1
                        AND f.form_id = $fid AND f.type LIKE 'list-%'";
        $r2 = $mysqli->query($sqlCampos);
        if ($r2) while ($row = $r2->fetch_assoc()) { $items[] = $row; }

        // Opciones individuales de lista ocultas
        $sqlOpc = "SELECT i.id as item_id, i.value as opcion_nombre, f.label as pregunta_nombre,
                          'opcion' as tipo, $fid as form_id, '{$sec['nombre']}' as seccion
                   FROM custom_hidden_fields h
                   INNER JOIN ost_list_items i ON i.id = h.field_id
                   INNER JOIN ost_form_field f
                           ON f.type = CONCAT('list-', i.list_id)
                          AND f.form_id = $fid
                   WHERE h.form_id = $fid AND h.hidden = 1";
        $r3 = $mysqli->query($sqlOpc);
        if ($r3) while ($row = $r3->fetch_assoc()) { $items[] = $row; }
    }

    echo json_encode(['ok' => true, 'items' => $items, 'total' => count($items)]);
    exit;
}

// API para desocultar cualquier elemento desde el popup de fields
if (isset($_GET['api_desocultar_campo'])) {
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
   4. ACCIONES POST - GUARDAR CAMBIOS DE CAMPO
      La visibilidad se guarda CON form_id para que sea
      independiente entre Incidencias y Valoraciones.
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'editar_campo') {
        $id_campo   = (int)$_POST['id'];
        $nuevo_label = trim($_POST['label'] ?? '');
        $nuevo_hint  = trim($_POST['hint']  ?? '');
        $ocultar     = (int)$_POST['hidden_estado']; // 1 = oculto, 0 = visible

        // Actualiza nombre y ayuda en osTicket (global, igual que options hace con value)
        $stmt = $mysqli->prepare("UPDATE ost_form_field SET label = ?, hint = ?, updated = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $nuevo_label, $nuevo_hint, $id_campo);
        $stmt->execute();

        // Gestión INDEPENDIENTE por form_id (igual que options.php)
        if ($ocultar === 1) {
            $stmtH = $mysqli->prepare(
                "INSERT INTO custom_hidden_fields (field_id, form_id, hidden)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE hidden = 1"
            );
            $stmtH->bind_param("ii", $id_campo, $form_id_actual);
        } else {
            $stmtH = $mysqli->prepare(
                "DELETE FROM custom_hidden_fields WHERE field_id = ? AND form_id = ?"
            );
            $stmtH->bind_param("ii", $id_campo, $form_id_actual);
        }
        $stmtH->execute();

        $open_campo = (int)$_POST['open_campo'];
        header("Location: fields.php?tipo=$tipo_gestion&open=$open_campo&ok=1");
        exit;
    }

    if ($accion === 'crear_campo') {
        $nuevo_label  = trim($_POST['nuevo_label'] ?? '');
        $nuevo_hint   = trim($_POST['nuevo_hint']  ?? '');
        $nuevo_type   = trim($_POST['nuevo_type']  ?? 'text');
        $nuevo_name   = 'campo_' . time();
        $ocultar      = (int)($_POST['nuevo_hidden'] ?? 0);

        // Flags y configuration según tipo (valores reales extraídos de osTicket)
        if ($nuevo_type === 'files') {
            $nuevo_flags  = 13057;
            $nuevo_config = null;
        } elseif ($nuevo_type === 'memo') {
            $nuevo_flags  = 30465;
            $nuevo_config = '{"cols":"40","rows":"4","length":"","html":true,"placeholder":""}';
        } else {
            $nuevo_type   = 'text';
            $nuevo_flags  = 30465;
            $nuevo_config = '{"size":"16","length":"30","validator":"","regex":""}';
        }

        // Calcular siguiente sort
        $resSort = $mysqli->query("SELECT COALESCE(MAX(sort),0)+1 AS next_sort FROM ost_form_field WHERE form_id = $form_id_actual");
        $next_sort = $resSort ? (int)$resSort->fetch_assoc()['next_sort'] : 1;

        $stmtC = $mysqli->prepare(
            "INSERT INTO ost_form_field (form_id, type, label, name, hint, sort, flags, configuration, created, updated)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmtC->bind_param("issssiis", $form_id_actual, $nuevo_type, $nuevo_label, $nuevo_name, $nuevo_hint, $next_sort, $nuevo_flags, $nuevo_config);
        $stmtC->execute();
        $nuevo_id = (int)$mysqli->insert_id;

        if ($ocultar === 1 && $nuevo_id) {
            $stmtH2 = $mysqli->prepare(
                "INSERT INTO custom_hidden_fields (field_id, form_id, hidden) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE hidden = 1"
            );
            $stmtH2->bind_param("ii", $nuevo_id, $form_id_actual);
            $stmtH2->execute();
        }

        header("Location: fields.php?tipo=$tipo_gestion&ok=1&nuevo=1");
        exit;
    }
}

/* =========================================================
   5. CARGA DE CAMPOS FILTRADOS POR FORM_ID
      Excluimos tipos list-* (esos son de options.php) e info
   ========================================================= */
$open_campo_id = isset($_GET['open']) ? (int)$_GET['open'] : 0;
$campos = [];

$sqlCampos = "
    SELECT f.id, f.name, f.label, f.type, f.sort, f.hint,
           COALESCE(h.hidden, 0) AS esta_oculto_aqui
    FROM ost_form_field f
    LEFT JOIN custom_hidden_fields h
           ON h.field_id = f.id AND h.form_id = $form_id_actual
    WHERE f.form_id = $form_id_actual
      AND f.type NOT LIKE 'list-%'
      AND f.type NOT IN ('info')
    ORDER BY f.sort ASC, f.id ASC
";
$resCampos = $mysqli->query($sqlCampos);
if ($resCampos) while ($row = $resCampos->fetch_assoc()) { $campos[] = $row; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campos Base - <?php echo $nombre_seccion; ?></title>
    <link rel="icon" type="image/png" href="/osticket/custom/img/gestor-campos.png?v=2">

    <style>
        /* =========================================================
           CSS: DISEÑO UNIFICADO CON OPTIONS.PHP
           ========================================================= */
        :root {
            --azul-atu:      #000099;
            --azul-btn:      #2563eb;
            --verde-ok:      #16a34a;
            --naranja-oculto:#f97316;
            --rojo-cerrar:   #ef4444;
            --gris-bg:       #f8fafc;
            --gris-borde:    #e2e8f0;
            --texto-base:    #1e293b;
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
        @keyframes slideToast  { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeInCard  { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes popModal    { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes pulseActive { 0% { box-shadow: 0 0 0 0 rgba(37,99,235,0.4); } 70% { box-shadow: 0 0 0 10px rgba(37,99,235,0); } 100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); } }

        .main-wrapper {
            max-width: 1100px; margin: 0 auto;
            background: #ffffff; border-radius: 25px;
            box-shadow: 0 30px 70px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: popModal 0.4s ease-out;
        }

        /* Cabecera */
        .header-brand { padding: 35px; text-align: center; border-bottom: 1px solid var(--gris-borde); background: #fff; }
        .header-brand img { max-width: 320px; transition: 0.3s; }
        .header-brand img:hover { transform: scale(1.03); }

        /* Barra de Navegación */
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
        .btn-grad-ocultos { margin-left: auto; background: linear-gradient(135deg, #22c55e, var(--azul-btn)); color: white !important; }
        .btn-grad-manual  { background: linear-gradient(135deg, #fbbf24, #ef4444); color: white !important; }
        .btn-grad-nuevo   { background: linear-gradient(135deg, #7c3aed, #2563eb); color: white !important; }

        /* Contenido Central */
        .body-container { padding: 40px; }
        .page-header { margin: 0 0 35px 0; font-size: 26px; font-weight: 900; color: var(--azul-atu); display: flex; align-items: center; gap: 15px; }
        .page-header::before { content:''; width:8px; height:32px; background:var(--azul-btn); border-radius:10px; }

        /* Buscador */
        .buscador-wrap { margin-bottom: 25px; }
        .buscador-input {
            width: 100%; padding: 14px 20px; border-radius: 15px;
            border: 1.8px solid var(--gris-borde); font-size: 15px;
            background: #fff; transition: 0.3s; outline: none;
        }
        .buscador-input:focus { border-color: var(--azul-btn); box-shadow: 0 0 0 5px rgba(37,99,235,0.08); }

        /* Tarjetas de campo */
        .field-panel {
            background: white; border: 1.5px solid var(--gris-borde);
            border-radius: 22px; padding: 25px; margin-bottom: 20px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            animation: fadeInCard 0.6s ease-out backwards;
        }
        .field-panel:hover { border-color: var(--azul-btn); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .field-panel.is-expanded { border: 2.5px solid var(--azul-btn); background: #f9fbff; transform: scale(1.005); }

        .field-panel-header { display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .field-panel-header-info { flex: 1; min-width: 0; }
        .field-panel-header h3 { margin: 0 0 6px; font-size: 18px; font-weight: 800; color: #334155; line-height: 1.4; }
        .field-meta { font-size: 12px; color: #64748b; display: flex; gap: 12px; flex-wrap: wrap; }
        .field-meta span { background: #f1f5f9; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
        .badge-oculto  { background: #fff7ed !important; color: var(--naranja-oculto) !important; }
        .badge-visible { background: #f0fdf4 !important; color: var(--verde-ok) !important; }

        /* Botones acción */
        .btn-ui-action { padding: 12px 26px; border-radius: 12px; font-weight: 850; font-size: 13px; text-decoration: none; transition: 0.3s; border: none; cursor: pointer; white-space: nowrap; }
        .btn-ui-edit  { background: var(--azul-btn); color: white; }
        .btn-ui-close { background: var(--rojo-cerrar); color: white; }
        .btn-ui-action:hover { filter: brightness(1.15); transform: scale(1.04); }

        /* Editor de campo */
        .editor-wrapper { margin-top: 25px; border-top: 2px dashed #cbd5e1; padding-top: 25px; }

        .form-row {
            display: flex; gap: 15px; align-items: center;
            padding: 14px; border-radius: 18px; margin-bottom: 12px;
            transition: 0.3s;
        }
        .form-row:hover { background: #f8fafc; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }

        .field-input {
            flex: 1; padding: 14px 20px; border-radius: 15px;
            border: 1.8px solid var(--gris-borde); font-size: 15px;
            background: #fff; transition: 0.3s; outline: none;
        }
        .field-input:focus { border-color: var(--azul-btn); box-shadow: 0 0 0 5px rgba(37,99,235,0.08); }

        /* Pills de estado (igual que options.php) */
        .pills-box  { display: inline-flex; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 30px; width: fit-content; }
        .pill-item  {
            border: none; padding: 7px 14px; border-radius: 25px;
            font-weight: 900; font-size: 11px; cursor: pointer;
            background: transparent; color: #64748b; transition: 0.3s;
        }
        .pill-item.st-active { background: var(--verde-ok);      color: white; }
        .pill-item.st-hidden { background: var(--naranja-oculto); color: white; }

        .btn-save-ui {
            background: linear-gradient(135deg, var(--azul-atu), var(--azul-btn));
            color: white; padding: 10px 22px; border-radius: 12px; border: none;
            font-weight: 900; font-size: 13px; cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,153,0.22); white-space: nowrap;
        }
        .btn-save-ui:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 20px rgba(0,0,153,0.35); }

        /* Toast */
        .toast-container { position: fixed; top: 35px; right: 35px; z-index: 100001; pointer-events: none; }
        .toast-card {
            background: #ffffff; border-left: 8px solid var(--verde-ok); padding: 22px 30px;
            border-radius: 22px; box-shadow: 0 25px 55px rgba(0,0,0,0.25);
            margin-bottom: 15px; display: flex; align-items: center; gap: 18px;
            animation: slideToast 0.5s cubic-bezier(0.68,-0.55,0.265,1.55);
            pointer-events: auto;
        }

        /* Modal — scroll bloqueado en fondo */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,0.70);
            backdrop-filter: blur(10px); display: none; align-items: center;
            justify-content: center; z-index: 10000;
        }
        .modal-overlay.active { display: flex; }
        body.modal-open { overflow: hidden; }

        .modal-content-box {
            background: #fff; width: 92%; max-width: 860px; border-radius: 28px;
            box-shadow: 0 45px 100px rgba(0,0,0,0.55);
            animation: popModal 0.3s ease-out;
            display: flex; flex-direction: column;
            max-height: 88vh;
            overflow: hidden;
        }

        /* Cabecera fija */
        .modal-header-styled {
            padding: 26px 35px 22px; border-bottom: 1.5px solid var(--gris-borde);
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(to right, #fff, var(--gris-bg));
            flex-shrink: 0;
        }

        /* Buscador fijo */
        .modal-search-wrap { padding: 16px 35px 0; flex-shrink: 0; }

        /* Pestañas fijas */
        .modal-tabs-bar {
            display: flex; padding: 0 35px;
            border-bottom: 1.5px solid var(--gris-borde);
            background: var(--gris-bg); flex-shrink: 0;
        }
        .modal-tab-btn {
            background: none; border: none; cursor: pointer;
            padding: 14px 24px; font-size: 14px; font-weight: 850;
            color: #94a3b8; border-bottom: 3px solid transparent;
            margin-bottom: -1.5px; display: flex; align-items: center; gap: 9px;
            transition: color 0.2s;
        }
        .modal-tab-btn:hover { color: #334155; }
        .modal-tab-btn.active { color: var(--azul-btn); border-bottom-color: var(--azul-btn); }
        .modal-tab-badge {
            font-size: 11px; font-weight: 900; padding: 3px 9px;
            border-radius: 20px; background: #f1f5f9; color: #64748b; transition: 0.2s;
        }
        .modal-tab-btn.active .modal-tab-badge { background: #dbeafe; color: var(--azul-btn); }

        /* Cuerpo: ÚNICO con scroll */
        .modal-body-area {
            flex: 1; min-height: 0;
            overflow-y: auto;
            padding: 28px 35px 35px;
            overscroll-behavior: contain;
        }

        /* Paneles de pestaña */
        .modal-tab-panel { display: none; }
        .modal-tab-panel.active { display: block; }

        /* Tarjetas de elemento oculto — mismo estilo que field-panel */
        .modal-item-card {
            background: var(--gris-bg); border: 1.5px solid var(--gris-borde);
            border-radius: 18px; padding: 18px 22px; margin-bottom: 12px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            transition: all 0.25s;
        }
        .modal-item-card:hover { background: #fff; box-shadow: 0 5px 18px rgba(0,0,0,0.06); transform: translateX(4px); }
        .modal-item-card .item-nombre { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .modal-item-card .item-meta   { font-size: 12px; color: #64748b; }

        /* Separador de tipo dentro del panel */
        .modal-grupo-header {
            display: flex; align-items: center; gap: 10px; margin: 20px 0 10px;
        }
        .modal-grupo-header:first-child { margin-top: 4px; }
        .modal-grupo-label { font-size: 11px; font-weight: 900; color: #94a3b8; letter-spacing: .08em; text-transform: uppercase; }
        .modal-grupo-line  { flex: 1; height: 1px; background: var(--gris-borde); }

        /* Sin resultados */
        .no-campos { text-align: center; padding: 60px 20px; color: #94a3b8; }

        /* Nuevo campo inline */
        .nuevo-campo-inline {
            margin-bottom: 28px;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.165,0.84,0.44,1);
            box-shadow: 0 6px 20px rgba(37,99,235,0.18);
        }
        .nuevo-campo-toggle {
            width: 100%; border: none; cursor: pointer;
            padding: 18px 28px; display: flex; align-items: center; gap: 14px;
            color: #fff; font-size: 15px; font-weight: 800;
            transition: all 0.25s; text-align: left; justify-content: flex-start;
            background: linear-gradient(135deg, #1a6fff 0%, #0047d4 100%);
            letter-spacing: 0.02em;
        }
        .nuevo-campo-toggle:hover {
            background: linear-gradient(135deg, #0f5fe8 0%, #0038b8 100%);
            padding-left: 32px;
        }
        .is-open .nuevo-campo-toggle {
            background: linear-gradient(135deg, #0d4fcf 0%, #002fa0 100%);
        }
        .toggle-icon {
            width: 32px; height: 32px; border-radius: 50%;
            background: rgba(255,255,255,0.22); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 400; line-height: 1;
            transition: transform 0.35s cubic-bezier(0.68,-0.55,0.265,1.55);
            flex-shrink: 0; border: 2px solid rgba(255,255,255,0.35);
        }
        .is-open .toggle-icon { transform: rotate(45deg); }
        .toggle-label-text { flex: 1; }
        .toggle-badge {
            background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3);
            color: white; font-size: 11px; font-weight: 700; padding: 4px 12px;
            border-radius: 20px; letter-spacing: 0.05em; white-space: nowrap;
        }

        .nuevo-campo-form {
            display: none;
            background: linear-gradient(160deg, #0a2a6e 0%, #0047d4 100%);
            padding: 0;
        }
        .is-open .nuevo-campo-form { display: block; animation: fadeInCard 0.3s ease-out; }

        .nuevo-campo-inner {
            padding: 28px 28px 24px;
        }

        .nuevo-campo-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
            margin-bottom: 22px;
        }
        .nuevo-campo-field { display: flex; flex-direction: column; gap: 8px; }
        .nuevo-campo-field--tipo { grid-column: 1 / -1; }
        .nuevo-campo-field--vis { grid-column: 1 / -1; }
        .nuevo-campo-label {
            font-size: 10px; font-weight: 900; color: rgba(148,163,184,1);
            letter-spacing: 0.1em; text-transform: uppercase;
        }

        /* Input dentro del form oscuro */
        .nuevo-campo-form .field-input {
            background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.15);
            color: #f1f5f9;
            border-radius: 14px;
        }
        .nuevo-campo-form .field-input::placeholder { color: rgba(148,163,184,0.7); }
        .nuevo-campo-form .field-input:focus {
            border-color: rgba(96,165,250,0.8);
            background: rgba(255,255,255,0.12);
            box-shadow: 0 0 0 4px rgba(37,99,235,0.25);
        }

        /* Pills tipo dentro del form oscuro */
        .tipo-pills-wrap { display: flex; gap: 10px; flex-wrap: wrap; }
        .tipo-pill {
            display: inline-flex; align-items: center; gap: 9px;
            padding: 12px 22px; border-radius: 14px;
            border: 2px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.07); font-size: 13px; font-weight: 800;
            color: rgba(203,213,225,1); cursor: pointer; transition: 0.2s;
        }
        .tipo-pill:hover {
            border-color: rgba(96,165,250,0.7);
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .tipo-pill--active {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 4px 14px rgba(37,99,235,0.45);
        }
        .tipo-pill-icon { font-size: 16px; }

        /* Pills visibilidad dentro del form oscuro */
        .nuevo-campo-form .pills-box {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .nuevo-campo-form .pill-item { color: rgba(148,163,184,1); }

        /* Separador y acciones */
        .nuevo-campo-actions {
            display: flex; gap: 12px; justify-content: flex-end;
            padding: 18px 28px 22px;
            background: rgba(0,0,0,0.25);
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .btn-cancelar-nuevo {
            padding: 11px 24px; border-radius: 12px;
            border: 1.5px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08); color: #cbd5e1;
            font-weight: 800; cursor: pointer; font-size: 13px;
            transition: 0.2s;
        }
        .btn-cancelar-nuevo:hover { background: rgba(255,255,255,0.14); color: #fff; }
        .btn-crear-nuevo {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white; padding: 11px 26px; border-radius: 12px; border: none;
            font-weight: 900; font-size: 13px; cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 14px rgba(22,163,74,0.4); display: flex; align-items: center; gap: 8px;
        }
        .btn-crear-nuevo:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 22px rgba(22,163,74,0.55); }

        /* Botón Cerrar Sesión (igual que Gestor.php) */
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
<a class="btn-logout" id="btnLogout" href="fields.php?tipo=<?php echo $tipo_gestion; ?>&logout=1" title="Cerrar sesión">
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

<!-- Toast de confirmación -->
<div class="toast-container" id="notifArea">
    <?php if (isset($_GET['ok'])): ?>
        <div class="toast-card" id="toastSucc">
            <div style="background:#dcfce7;color:var(--verde-ok);width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:18px;">✓</div>
            <div>
                <?php if (isset($_GET['nuevo'])): ?>
                    <b style="display:block;font-size:17px;color:var(--azul-atu);">¡Campo creado!</b>
                    <span style="font-size:13px;color:#64748b;">El nuevo campo se ha añadido a <?php echo $nombre_seccion; ?>.</span>
                <?php else: ?>
                    <b style="display:block;font-size:17px;color:var(--azul-atu);">¡Campo actualizado!</b>
                    <span style="font-size:13px;color:#64748b;">Los cambios se aplican exclusivamente en <?php echo $nombre_seccion; ?>.</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="main-wrapper">

    <!-- CABECERA CON LOGO -->
    <div class="header-brand">
        <a href="gestor_<?php echo $tipo_gestion; ?>.php">
            <img src="/osticket/custom/img/gestor-campos.png?v=2" alt="ATU Gestión de Campos">
        </a>
    </div>

    <!-- NAVEGACIÓN -->
    <div class="nav-bar">
        <a href="gestor_<?php echo $tipo_gestion; ?>.php" class="btn-nav-styled">Atrás</a>
        <button class="btn-nav-styled active">Campos base</button>
        <a href="options.php?tipo=<?php echo $tipo_gestion; ?>" class="btn-nav-styled">Listas desplegables</a>
        <button class="btn-nav-styled btn-grad-ocultos" onclick="abrirOcultosPopup()">👁 Ver Ocultos</button>
        <a href="ayuda.php" class="btn-nav-styled btn-grad-manual">📘 Manual de Operaciones</a>
    </div>

    <!-- CUERPO PRINCIPAL -->
    <div class="body-container">
        <h2 class="page-header">Campos del Cuestionario: <?php echo $nombre_seccion; ?></h2>

        <!-- BUSCADOR -->
        <div class="buscador-wrap">
            <input type="text" id="buscadorCampos" class="buscador-input" placeholder="Buscar campo por nombre...">
        </div>

        <!-- NUEVO CAMPO: formulario inline debajo del buscador -->
        <div class="nuevo-campo-inline" id="nuevoCampoInline">
            <button type="button" class="nuevo-campo-toggle" id="toggleNuevoCampo" onclick="toggleNuevoCampoForm()">
                <span class="toggle-icon" id="toggleIcon">＋</span>
                <span class="toggle-label-text">Añadir nuevo campo</span>

            </button>

            <div class="nuevo-campo-form" id="nuevoCampoForm">
                <form method="post" action="fields.php?tipo=<?php echo $tipo_gestion; ?>">
                    <input type="hidden" name="accion" value="crear_campo">
                    <input type="hidden" name="nuevo_hint" value="">

                    <div class="nuevo-campo-inner">
                        <div class="nuevo-campo-grid">
                            <div class="nuevo-campo-field nuevo-campo-field--tipo" style="grid-column:1/-1;">
                                <label class="nuevo-campo-label">📝 Nombre del campo *</label>
                                <input type="text" name="nuevo_label" class="field-input" placeholder="Ej: Motivo de la incidencia" required>
                            </div>

                            <div class="nuevo-campo-field nuevo-campo-field--tipo">
                                <label class="nuevo-campo-label">⚙️ Tipo de campo</label>
                                <div class="tipo-pills-wrap">
                                    <input type="hidden" name="nuevo_type" id="nuevo_type_val" value="text">
                                    <button type="button" class="tipo-pill tipo-pill--active" data-tipo="text" onclick="setTipoPill(this, 'text')">
                                        <span class="tipo-pill-icon">✏️</span> Texto
                                    </button>
                                    <button type="button" class="tipo-pill" data-tipo="files" onclick="setTipoPill(this, 'files')">
                                        <span class="tipo-pill-icon">📎</span> Archivo adjunto
                                    </button>
                                    <button type="button" class="tipo-pill" data-tipo="memo" onclick="setTipoPill(this, 'memo')">
                                        <span class="tipo-pill-icon">📄</span> Texto largo
                                    </button>
                                </div>
                            </div>

                            <div class="nuevo-campo-field nuevo-campo-field--vis">
                                <label class="nuevo-campo-label">👁 Visibilidad inicial</label>
                                <div class="pills-box" style="display:inline-flex;">
                                    <button type="button" class="pill-item st-active" id="pill-nuevo-visible" onclick="setNuevoPill(0)">Visible</button>
                                    <button type="button" class="pill-item" id="pill-nuevo-oculto" onclick="setNuevoPill(1)">Oculto</button>
                                </div>
                                <input type="hidden" name="nuevo_hidden" id="nuevo_hidden_val" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="nuevo-campo-actions">
                        <button type="button" class="btn-cancelar-nuevo" onclick="toggleNuevoCampoForm()">✕ Cancelar</button>
                        <button type="submit" class="btn-crear-nuevo">✅ Crear campo</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- LISTA DE CAMPOS -->
        <?php if (empty($campos)): ?>
            <div class="no-campos">
                <p style="font-size:40px;">🔍</p>
                <p style="font-size:18px;font-weight:700;">No se encontraron campos para este formulario.</p>
                <p>form_id: <?php echo $form_id_actual; ?></p>
            </div>
        <?php else: ?>

            <?php foreach ($campos as $idx => $campo): ?>
                <?php $isOpen = ($open_campo_id === (int)$campo['id']); ?>

                <div class="field-panel <?php echo $isOpen ? 'is-expanded' : ''; ?>"
                     id="campo-<?php echo $campo['id']; ?>"
                     data-label="<?php echo strtolower(htmlspecialchars($campo['label'])); ?>"
                     style="animation-delay: <?php echo $idx * 0.06; ?>s">

                    <div class="field-panel-header">
                        <div class="field-panel-header-info">
                            <h3><?php echo htmlspecialchars($campo['label']); ?></h3>
                            <div class="field-meta">
                                <span class="<?php echo $campo['esta_oculto_aqui'] ? 'badge-oculto' : 'badge-visible'; ?>">
                                    <?php echo $campo['esta_oculto_aqui'] ? '🔴 Oculto aquí' : '🟢 Visible'; ?>
                                </span>
                            </div>
                        </div>

                        <!-- El enlace abre/cierra el panel (igual que options.php) -->
                        <a href="fields.php?tipo=<?php echo $tipo_gestion; ?>&open=<?php echo $isOpen ? 0 : $campo['id']; ?>#campo-<?php echo $campo['id']; ?>"
                           class="btn-ui-action <?php echo $isOpen ? 'btn-ui-close' : 'btn-ui-edit'; ?>">
                            <?php echo $isOpen ? 'Cerrar panel' : 'Editar'; ?>
                        </a>
                    </div>

                    <?php if ($isOpen): ?>
                    <div class="editor-wrapper">
                        <form method="post" action="fields.php?tipo=<?php echo $tipo_gestion; ?>&open=<?php echo $campo['id']; ?>">
                            <input type="hidden" name="accion"      value="editar_campo">
                            <input type="hidden" name="id"          value="<?php echo $campo['id']; ?>">
                            <input type="hidden" name="open_campo"  value="<?php echo $campo['id']; ?>">
                            <input type="hidden" name="hidden_estado" id="hidden_estado_<?php echo $campo['id']; ?>"
                                   value="<?php echo $campo['esta_oculto_aqui']; ?>">

                            <!-- Fila: Nombre visible -->
                            <div class="form-row">
                                <input type="text" name="label"
                                       class="field-input"
                                       placeholder="Nombre visible del campo"
                                       value="<?php echo htmlspecialchars($campo['label']); ?>">

                                <!-- Pills de visibilidad (misma lógica que options.php) -->
                                <div class="pills-box">
                                    <button type="button"
                                            class="pill-item <?php echo !$campo['esta_oculto_aqui'] ? 'st-active' : ''; ?>"
                                            onclick="setPillCampo(this, 0, <?php echo $campo['id']; ?>)">Visible</button>
                                    <button type="button"
                                            class="pill-item <?php echo $campo['esta_oculto_aqui'] ? 'st-hidden' : ''; ?>"
                                            onclick="setPillCampo(this, 1, <?php echo $campo['id']; ?>)">Oculto</button>
                                </div>

                                <button type="submit" class="btn-save-ui">💾 Guardar</button>
                            </div>

                            <?php if (!empty($campo['hint'])): ?>
                            <!-- Fila: Texto de ayuda (hint) -->
                            <div class="form-row">
                                <input type="text" name="hint"
                                       class="field-input"
                                       placeholder="Texto de ayuda (opcional)"
                                       value="<?php echo htmlspecialchars($campo['hint']); ?>">
                                <div style="width:160px;"></div><!-- espaciador para alinear -->
                                <div style="width:100px;"></div>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="hint" value="">
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div><!-- /.body-container -->
</div><!-- /.main-wrapper -->

<!-- MODAL DE CAMPOS OCULTOS -->
<div class="modal-overlay" id="modalOcultos">
    <div class="modal-content-box">

        <!-- CABECERA fija -->
        <div class="modal-header-styled">
            <div>
                <h3 style="margin:0 0 4px;font-size:21px;color:var(--azul-atu);display:flex;align-items:center;gap:10px;">
                    <span style="width:13px;height:13px;border-radius:50%;background:#ef4444;display:inline-block;flex-shrink:0;"></span>
                    Elementos Retirados
                </h3>
                <span style="font-size:13px;color:#64748b;">Haz clic en "Activar" para restaurar un elemento oculto</span>
            </div>
            <button onclick="cerrarOcultosPopup()" style="background:none;border:none;font-size:36px;cursor:pointer;color:#94a3b8;line-height:1;flex-shrink:0;padding:0 4px;">&times;</button>
        </div>

        <!-- BUSCADOR fijo -->
        <div class="modal-search-wrap">
            <input type="text" id="buscadorModal" placeholder="🔍  Buscar por nombre..."
                   oninput="filtrarModal(this.value)"
                   style="width:100%;padding:11px 18px;border-radius:12px;border:1.8px solid var(--gris-borde);
                          font-size:14px;outline:none;box-sizing:border-box;margin-bottom:14px;transition:0.2s;"
                   onfocus="this.style.borderColor='var(--azul-btn)'"
                   onblur="this.style.borderColor='var(--gris-borde)'">
        </div>

        <!-- PESTAÑAS fijas -->
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

        <!-- CUERPO: único con scroll -->
        <div class="modal-body-area">
            <div class="modal-tab-panel active" id="mpanel-incidencias">
                <p style="text-align:center;color:#94a3b8;padding:50px 0;">Consultando base de datos…</p>
            </div>
            <div class="modal-tab-panel" id="mpanel-valoraciones">
                <p style="text-align:center;color:#94a3b8;padding:50px 0;">Consultando base de datos…</p>
            </div>
            <div class="modal-tab-panel" id="mpanel-valoraciones_tutores">
                <p style="text-align:center;color:#94a3b8;padding:50px 0;">Consultando base de datos…</p>
            </div>
        </div>

    </div>
</div>

<script>
/* =========================================================
   JAVASCRIPT: PILLS, BUSCADOR, POPUP, SCROLL, TOAST
   ========================================================= */

// 1. MANTENER POSICIÓN DE SCROLL TRAS GUARDAR
(function(){
    const KEY = 'gestor_fields_atu_pos';
    document.addEventListener('submit', () => sessionStorage.setItem(KEY, window.scrollY));
    window.addEventListener('load', () => {
        const y = sessionStorage.getItem(KEY);
        if (y) { window.scrollTo({ top: parseInt(y), behavior: 'instant' }); sessionStorage.removeItem(KEY); }
    });
})();

// 2. PILLS DE VISIBILIDAD
// hidden_estado: 0 = visible, 1 = oculto  (al contrario que options donde status 1=activo / 0=oculto)
function setPillCampo(btn, valor, campoId) {
    document.getElementById('hidden_estado_' + campoId).value = valor;
    const pills = btn.parentElement.querySelectorAll('.pill-item');
    pills.forEach(p => p.classList.remove('st-active', 'st-hidden'));
    btn.classList.add(valor === 0 ? 'st-active' : 'st-hidden');
}

// 3. BUSCADOR EN TIEMPO REAL
function normalizarTexto(t) {
    return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}
document.getElementById('buscadorCampos').addEventListener('input', function() {
    const q = normalizarTexto(this.value.trim());
    document.querySelectorAll('.field-panel').forEach(panel => {
        const label = normalizarTexto(panel.getAttribute('data-label') || '');
        panel.style.display = label.includes(q) ? '' : 'none';
    });
});

// 4. POPUP CAMPOS OCULTOS — pestañas, scroll en body bloqueado, tarjetas con formato
var _modalItems     = [];
var _modalTabActiva = 'incidencias';

function abrirOcultosPopup() {
    document.body.classList.add('modal-open');
    document.getElementById('modalOcultos').classList.add('active');
    document.getElementById('buscadorModal').value = '';
    _modalTabActiva = 'incidencias';
    switchModalTab('incidencias');

    ['incidencias','valoraciones','valoraciones_tutores'].forEach(t => {
        document.getElementById('mpanel-' + t).innerHTML =
            '<p style="text-align:center;color:#94a3b8;padding:50px 0;">Consultando base de datos…</p>';
        document.getElementById('mbadge-' + t).textContent = '0';
    });

    fetch('options.php?api_ocultos_v5=1&tipo=<?php echo $tipo_gestion; ?>')
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
    const mapSec   = { 'Incidencias': 'incidencias', 'Valoraciones': 'valoraciones', 'Valoración de Tutores': 'valoraciones_tutores' };
    const _tipoLabel = { text: '✏️ Texto', memo: '📄 Texto largo', files: '📎 Archivo' };

    ['Incidencias','Valoraciones','Valoración de Tutores'].forEach(sec => {
        const panel   = document.getElementById('mpanel-' + mapSec[sec]);
        const badge   = document.getElementById('mbadge-' + mapSec[sec]);
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
                            class="btn-save-ui"
                            style="flex-shrink:0;padding:10px 20px;font-size:12px;border-radius:12px;">
                        ✓ Activar
                    </button>
                </div>`;
            });
        });

        panel.innerHTML = html;
    });
}

function desocultarItem(btn, fieldId, formId) {
    btn.disabled = true; btn.textContent = '…';
    fetch('fields.php?api_desocultar_campo=1', {
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

function filtrarModal(q) {
    function norm(t){ return (t||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim(); }
    const nq = norm(q);
    ['mpanel-incidencias','mpanel-valoraciones','mpanel-valoraciones_tutores'].forEach(id => {
        document.getElementById(id).querySelectorAll('.modal-item-row').forEach(row => {
            row.style.display = (!nq || norm(row.dataset.nombre).includes(nq)) ? '' : 'none';
        });
    });
}

// 5. AUTO-CERRADO DEL TOAST
const toast = document.getElementById('toastSucc');
if (toast) {
    setTimeout(() => {
        toast.style.transition = '0.6s cubic-bezier(0.68,-0.55,0.265,1.55)';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px) scale(0.9)';
        setTimeout(() => toast.remove(), 600);
    }, 4500);
}
</script>

<!-- JAVASCRIPT NUEVO CAMPO INLINE -->
<script>
function toggleNuevoCampoForm() {
    const wrap = document.getElementById('nuevoCampoInline');
    wrap.classList.toggle('is-open');
}
function setTipoPill(btn, tipo) {
    document.getElementById('nuevo_type_val').value = tipo;
    document.querySelectorAll('.tipo-pill').forEach(p => p.classList.remove('tipo-pill--active'));
    btn.classList.add('tipo-pill--active');
}
function setNuevoPill(valor) {
    document.getElementById('nuevo_hidden_val').value = valor;
    document.getElementById('pill-nuevo-visible').classList.toggle('st-active', valor === 0);
    document.getElementById('pill-nuevo-visible').classList.toggle('st-hidden', false);
    document.getElementById('pill-nuevo-oculto').classList.toggle('st-hidden', valor === 1);
    document.getElementById('pill-nuevo-oculto').classList.toggle('st-active', false);
}
</script>

</body>
</html>