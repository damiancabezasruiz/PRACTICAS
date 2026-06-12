<?php
/**
 * auth_admin_sso.php — SSO compartido para usuario Admin
 * -------------------------------------------------------
 * Incluir este archivo al inicio de cada página (DESPUÉS de session_start()).
 *
 * Lógica:
 *   - Si Admin hace login en CUALQUIERA de los 5 archivos, se establece
 *     $_SESSION['admin_sso'] = true.
 *   - Al entrar en cualquier otra página, si admin_sso está activo,
 *     se rellena automáticamente la variable de sesión que esa página necesita.
 *   - Si Admin hace logout en cualquier página, se destruye TODA la sesión
 *     (incluyendo admin_sso), cerrando el acceso en todos los sitios.
 */

// ── Credenciales Admin (deben coincidir con las de cada archivo) ──────────────
define('ADMIN_SSO_USER', 'Admin');
define('ADMIN_SSO_PASS', '1,<X8r0.5(Tl03?-gq]giU');

// ── Si Admin autenticó en algún archivo, propagar la sesión SSO ──────────────
//    Detectamos login Admin por cualquiera de las variables de sesión existentes.
if (empty($_SESSION['admin_sso'])) {
    $admin_ya_logueado =
        (!empty($_SESSION['gestor_loggedin'])       && ($_SESSION['gestor_user']        ?? '') === ADMIN_SSO_USER) ||
        (!empty($_SESSION['valoraciones_auth'])      && ($_SESSION['valoraciones_user']  ?? '') === ADMIN_SSO_USER) ||
        (!empty($_SESSION['incidencias_auth'])       && ($_SESSION['incidencias_user']   ?? '') === ADMIN_SSO_USER) ||
        (!empty($_SESSION['logged_in'])              && ($_SESSION['user_name']          ?? '') === ADMIN_SSO_USER) ||
        (!empty($_SESSION['estadisticas_auth'])      && ($_SESSION['estadisticas_user']  ?? '') === ADMIN_SSO_USER) ||
        (!empty($_SESSION['estadisticas_val_auth'])  && ($_SESSION['estadisticas_val_user'] ?? '') === ADMIN_SSO_USER);

    if ($admin_ya_logueado) {
        $_SESSION['admin_sso'] = true;
    }
}

// ── Si el SSO está activo, rellenar TODAS las variables de sesión ─────────────
if (!empty($_SESSION['admin_sso'])) {
    $_SESSION['gestor_loggedin']      = true;
    $_SESSION['gestor_user']          = ADMIN_SSO_USER;
    $_SESSION['gestor_persistente']   = true;  // evita el chequeo de tab_id en Gestor

    $_SESSION['valoraciones_auth']    = true;
    $_SESSION['valoraciones_user']    = ADMIN_SSO_USER;

    $_SESSION['incidencias_auth']     = true;
    $_SESSION['incidencias_user']     = ADMIN_SSO_USER;

    $_SESSION['logged_in']            = true;
    $_SESSION['user_name']            = ADMIN_SSO_USER;

    $_SESSION['estadisticas_auth']    = true;
    $_SESSION['estadisticas_user']    = ADMIN_SSO_USER;

    $_SESSION['estadisticas_val_auth'] = true;
    $_SESSION['estadisticas_val_user'] = ADMIN_SSO_USER;
}

/**
 * Llamar a esta función al procesar un login exitoso de Admin en cualquier página.
 * Activa el SSO y rellena todas las sesiones de golpe.
 */
function admin_sso_activate(): void {
    $_SESSION['admin_sso']            = true;
    $_SESSION['gestor_loggedin']      = true;
    $_SESSION['gestor_user']          = ADMIN_SSO_USER;
    $_SESSION['gestor_persistente']   = true;  // evita el chequeo de tab_id en Gestor
    $_SESSION['valoraciones_auth']    = true;
    $_SESSION['valoraciones_user']    = ADMIN_SSO_USER;
    $_SESSION['incidencias_auth']     = true;
    $_SESSION['incidencias_user']     = ADMIN_SSO_USER;
    $_SESSION['logged_in']            = true;
    $_SESSION['user_name']            = ADMIN_SSO_USER;
    $_SESSION['estadisticas_auth']    = true;
    $_SESSION['estadisticas_user']    = ADMIN_SSO_USER;
    $_SESSION['estadisticas_val_auth'] = true;
    $_SESSION['estadisticas_val_user'] = ADMIN_SSO_USER;
}

/**
 * Llamar a esta función en el logout cuando el usuario es Admin.
 * Destruye toda la sesión SSO.
 */
function admin_sso_logout(): void {
    $_SESSION = [];
    if (session_id() !== '') session_destroy();
    setcookie('gestor_loggedin', '', time() - 3600, '/');
}