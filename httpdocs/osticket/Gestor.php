<?php
/**
 * =============================================================================
 * Gestor.php — Panel de administración personalizado del Gestor de Cuestionarios
 * =============================================================================
 *
 * Propósito:
 *   Punto de entrada principal del panel de gestión interno de Grupo ATU.
 *   Gestiona el ciclo completo de autenticación (login/logout) y, una vez
 *   autenticado, muestra el panel de navegación hacia las secciones de
 *   configuración de cuestionarios (Incidencias y Valoraciones).
 *
 * Modos de operación:
 *   Sin sesión activa → Muestra la pantalla de login con diseño corporativo.
 *   Con sesión activa → Muestra el panel de navegación principal.
 *   ?logout=1         → Destruye la sesión, limpia la cookie y redirige al login.
 *
 * Sistema de autenticación:
 *   - Credenciales hardcoded en $LOGIN_USER / $LOGIN_PASS (⚠ cambiar en producción).
 *   - Opción "Mantener sesión" (30 días): establece cookie 'gestor_loggedin=yes'.
 *   - Sin "Mantener sesión": sesión ligada a la pestaña del navegador mediante
 *     tab_id (sessionStorage) + $_SESSION['gestor_tab_id']. Esto evita que
 *     abrir el panel en otra pestaña de la misma ventana reutilice la sesión.
 *   - Validación de tab_id en el cliente (JS): si la pestaña no tiene el ID
 *     esperado, se fuerza el logout automáticamente.
 *
 * Seguridad:
 *   - Las cookies de sesión usan httponly=true, samesite=Lax.
 *   - La cookie persistente se elimina en logout.
 *   - El botón de logout también limpia sessionStorage.
 *
 * Panel principal (logueado):
 *   - Tarjeta → gestor_valoraciones.php          (form_id = 16)
 *   - Tarjeta → gestor_incidencias.php           (form_id = 18)
 *   - Tarjeta → gestor_valoraciones_tutores.php  (form_id = 22)
 *
 * Dependencias:
 *   - PHP con extensión session
 *   - custom/img/logo-atu-gestor.png (logo del panel)
 *
 * @package    GrupoATU\Gestor
 * @author     Equipo de desarrollo Grupo ATU
 * @version    2.0
 * =============================================================================
 */


session_start();
require_once __DIR__ . '/auth_admin_sso.php';

/* Credenciales */
$LOGIN_USER = 'IncidenciasAtu';
$LOGIN_PASS = 'D/*50smPm@7FPM@c£EUMU&';

$LOGIN_ADMIN      = 'Admin';
$LOGIN_ADMIN_PASS  = '1,<X8r0.5(Tl03?-gq]giU';

/* Rehidratar sesión si hay cookie persistente */
if (
    empty($_SESSION['gestor_loggedin'])
    && !empty($_COOKIE['gestor_loggedin'])
    && $_COOKIE['gestor_loggedin'] === 'yes'
) {
    $_SESSION['gestor_loggedin'] = true;
    $_SESSION['gestor_persistente'] = true;
}

/* Logout */
if (isset($_GET['logout'])) {
    $destino = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($_SESSION['admin_sso'])) {
        admin_sso_logout();
    } else {
        $_SESSION = [];
        if (session_id() !== '') session_destroy();
        setcookie('gestor_loggedin', '', time() - 3600, '/');
    }
    header('Location: ' . $destino);
    exit;
}

/* Login POST */
$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_gestor'])) {
    $usuario   = $_POST['usuario']    ?? '';
    $contrasena = $_POST['contraseña'] ?? '';
    $mantener  = !empty($_POST['mantener_sesion']);
    $tab_id    = trim($_POST['tab_id'] ?? '');

    if (
        ($usuario === $LOGIN_USER  && $contrasena === $LOGIN_PASS) ||
        ($usuario === $LOGIN_ADMIN && $contrasena === $LOGIN_ADMIN_PASS)
    ) {
        // Si es Admin, activar SSO global
        if ($usuario === $LOGIN_ADMIN) {
            admin_sso_activate();
        }

        $_SESSION['gestor_loggedin'] = true;
        $_SESSION['gestor_user']     = $usuario;

        if ($mantener) {
            $_SESSION['gestor_persistente'] = true;
            unset($_SESSION['gestor_tab_id']);
            setcookie('gestor_loggedin', 'yes', time() + (30 * 24 * 60 * 60), '/');
        } else {
            $_SESSION['gestor_persistente'] = false;
            if ($tab_id === '') $tab_id = bin2hex(random_bytes(16));
            $_SESSION['gestor_tab_id'] = $tab_id;
            setcookie('gestor_loggedin', '', time() - 3600, '/');
        }

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $login_error = true;
    }
}

/* =========================================================
   LOGIN (si NO está logueado)
   ========================================================= */
if (empty($_SESSION['gestor_loggedin'])):
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso · Gestor ATU</title>
    <link rel="icon" type="image/png" href="custom/img/Logo-Gestor-Atu.png?v=1">
    <link rel="shortcut icon" type="image/png" href="custom/img/Logo-Gestor-Atu.png?v=1">
    <style>
        :root {
            --azul-atu:   #000099;
            --azul-btn:   #2563eb;
            --azul-btn2:  #1d4ed8;
            --verde-ok:   #16a34a;
            --gris-bg:    #f8fafc;
            --gris-borde: #e2e8f0;
            --texto-base: #1e293b;
            --muted:      #64748b;
        }

        @keyframes cardIn    { from { opacity:0; transform:translateY(20px) scale(.97) } to { opacity:1; transform:none } }
        @keyframes logoFloat { 0%,100% { transform:translateY(0) } 50% { transform:translateY(-5px) } }
        @keyframes blob1     { from { transform:translate(0,0) scale(1) }   to { transform:translate(50px,35px) scale(1.08) } }
        @keyframes blob2     { from { transform:translate(0,0) scale(1) }   to { transform:translate(-40px,-30px) scale(1.1) } }
        @keyframes blob3     { from { transform:translate(0,0) scale(1) }   to { transform:translate(30px,-40px) scale(1.06) } }
        @keyframes shake     { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-7px)} 40%{transform:translateX(7px)} 60%{transform:translateX(-5px)} 80%{transform:translateX(5px)} }
        @keyframes ripple    { from { transform:scale(0); opacity:.35 } to { transform:scale(4); opacity:0 } }
        @keyframes lineSlide { from { width:0 } to { width:100% } }
        @keyframes fadeUp    { from { opacity:0; transform:translateY(6px) } to { opacity:1; transform:none } }

        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0 }
        html, body { height:100% }

        body {
            min-height:100svh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family:'Segoe UI', system-ui, sans-serif;
            padding:clamp(12px,3vh,24px);
            background: linear-gradient(135deg, #000099 0%, #001bb3 55%, #0026cc 100%);
            position:relative;
            isolation:isolate;
            overflow:hidden;
        }

        /* Orbes de fondo animados */
        .bg-orb {
            position:absolute;
            border-radius:50%;
            filter:blur(50px);
            opacity:.3;
            z-index:0;
            pointer-events:none;
        }
        .bg-orb-1 {
            width:480px; height:480px;
            background:radial-gradient(circle, #60a5fa 0%, transparent 65%);
            top:-180px; left:-160px;
            animation:blob1 10s ease-in-out infinite alternate;
        }
        .bg-orb-2 {
            width:440px; height:440px;
            background:radial-gradient(circle, #22c55e 0%, transparent 65%);
            bottom:-180px; right:-160px;
            animation:blob2 12s ease-in-out infinite alternate;
        }
        .bg-orb-3 {
            width:300px; height:300px;
            background:radial-gradient(circle, #a78bfa 0%, transparent 65%);
            top:50%; right:15%;
            animation:blob3 14s ease-in-out infinite alternate;
            opacity:.18;
        }

        /* Patrón de puntos sutil */
        body::before {
            content:'';
            position:absolute;
            inset:0;
            z-index:0;
            background-image: radial-gradient(circle, rgba(255,255,255,.08) 1px, transparent 1px);
            background-size:28px 28px;
        }

        .login-box {
            position:relative;
            z-index:1;
            width:min(460px,100%);
            background:#fff;
            border-radius:28px;
            box-shadow:
                0 0 0 1px rgba(255,255,255,.12),
                0 35px 80px rgba(0,0,0,.4),
                0 10px 30px rgba(0,0,153,.3);
            overflow:hidden;
            animation:cardIn .5s cubic-bezier(.2,.9,.2,1) both;
        }
        .login-box.shake { animation:shake .45s ease; }

        /* Cabecera */
        .login-head {
            padding:32px 32px 24px;
            text-align:center;
            background:linear-gradient(to bottom, #fff, #f8fafc);
            border-bottom:1px solid var(--gris-borde);
            position:relative;
        }
        .login-head::after {
            content:'';
            position:absolute;
            left:0; bottom:0;
            height:3px; width:0;
            background:linear-gradient(90deg, var(--azul-btn), #22c55e);
            animation:lineSlide .8s .3s cubic-bezier(.4,0,.2,1) forwards;
        }

        .login-logo { margin-bottom:16px }
        .login-logo img {
            width:clamp(160px,28vh,230px);
            height:auto;
            display:block;
            margin:0 auto;
            animation:logoFloat 4.5s ease-in-out infinite;
            cursor:pointer;
            transition:filter .2s, transform .2s;
        }
        .login-logo img:hover { filter:brightness(1.04); transform:translateY(-2px) }

        .login-title {
            font-size:clamp(19px,3.4vh,25px);
            font-weight:900;
            letter-spacing:-.4px;
            background:linear-gradient(135deg, var(--azul-atu), var(--azul-btn));
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
            margin-bottom:5px;
        }
        .login-subtitle { font-size:13px; color:var(--muted); font-weight:700 }

        /* Cuerpo del form */
        .login-body { padding:26px 32px 28px }

        .error-box {
            display:flex;
            align-items:center;
            gap:10px;
            padding:12px 16px;
            border-radius:14px;
            margin-bottom:18px;
            background:linear-gradient(135deg,#fff1f2,#ffe4e6);
            border:1px solid #fecaca;
            color:#b91c1c;
            font-size:13px;
            font-weight:800;
            animation:fadeUp .3s ease;
        }
        .error-box.is-hidden { display:none }
        .error-dot {
            width:10px; height:10px; border-radius:50%;
            background:#ef4444;
            box-shadow:0 0 0 4px rgba(239,68,68,.18);
            flex-shrink:0;
        }

        .form-group { margin-bottom:16px }
        .form-group label {
            display:block;
            font-size:12px;
            font-weight:900;
            color:var(--texto-base);
            letter-spacing:.08em;
            text-transform:uppercase;
            margin-bottom:7px;
        }
        .input-wrap { position:relative }
        .input-icon {
            position:absolute;
            left:14px; top:50%;
            transform:translateY(-50%);
            font-size:16px;
            pointer-events:none;
            opacity:.5;
            transition:opacity .2s;
        }
        .form-group input {
            width:100%;
            padding:13px 14px 13px 42px;
            border-radius:14px;
            border:1.8px solid var(--gris-borde);
            font-size:14px;
            font-family:inherit;
            outline:none;
            background:#fff;
            color:var(--texto-base);
            transition:border-color .2s, box-shadow .2s, transform .2s;
        }
        .form-group input:focus {
            border-color:var(--azul-btn);
            box-shadow:0 0 0 5px rgba(37,99,235,.12);
            transform:translateY(-1px);
        }
        .form-group input:focus + .input-icon,
        .input-wrap:focus-within .input-icon { opacity:1 }

        .checkbox-group {
            display:flex; align-items:center; gap:10px;
            margin:14px 0 20px;
            font-size:13px; font-weight:800; color:#334155;
        }
        .checkbox-group input[type=checkbox] {
            width:18px; height:18px;
            accent-color:var(--azul-btn);
            cursor:pointer;
            padding:0;
            border-radius:5px;
        }
        .checkbox-group label { cursor:pointer; font-size:13px }

        .btn-login {
            width:100%;
            padding:14px;
            border:none; border-radius:14px;
            cursor:pointer;
            font-size:14px; font-weight:900;
            letter-spacing:.8px; text-transform:uppercase;
            color:#fff;
            background:linear-gradient(135deg, var(--azul-btn), var(--azul-btn2));
            box-shadow:0 10px 28px rgba(37,99,235,.28);
            transition:transform .22s, box-shadow .22s, filter .22s;
            position:relative; overflow:hidden;
        }
        .btn-login:hover {
            transform:translateY(-2px);
            box-shadow:0 16px 36px rgba(37,99,235,.36);
            filter:brightness(1.05);
        }
        .btn-login:active { transform:translateY(0) }

        /* Ripple al hacer click */
        .btn-login .ripple {
            position:absolute;
            border-radius:50%;
            background:rgba(255,255,255,.3);
            width:60px; height:60px;
            margin-top:-30px; margin-left:-30px;
            animation:ripple .6s ease-out forwards;
            pointer-events:none;
        }

        .login-foot {
            padding:14px 32px;
            text-align:center;
            background:linear-gradient(to bottom, #fff, var(--gris-bg));
            border-top:1px solid var(--gris-borde);
            font-size:12px; font-weight:800;
            color:var(--muted);
        }
        .lock-icon { margin-right:5px; opacity:.6 }

        @media (prefers-reduced-motion:reduce) { *, *::before, *::after { animation:none!important; transition:none!important } }
    </style>
</head>
<body>
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>

    <div class="login-box" id="loginBox">
        <div class="login-head">
            <div class="login-logo">
                <a href="Gestor.php">
                    <img src="custom/img/logo-atu-gestor.png" alt="Logo Grupo ATU">
                </a>
            </div>
            <h1 class="login-title">Gestor de Campos</h1>
            <p class="login-subtitle">Ingresa tus credenciales para acceder</p>
        </div>

        <div class="login-body">
            <div class="error-box <?php echo $login_error ? '' : 'is-hidden'; ?>" id="errorBox">
                <span class="error-dot"></span>
                Usuario o contraseña incorrectos. Inténtalo de nuevo.
            </div>

            <form id="loginForm" method="post" autocomplete="off">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <div class="input-wrap">
                        <input type="text" id="usuario" name="usuario" required autofocus placeholder="Introduce tu usuario">
                        <span class="input-icon">👤</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="contraseña">Contraseña</label>
                    <div class="input-wrap">
                        <input type="password" id="contraseña" name="contraseña" required placeholder="••••••••">
                        <span class="input-icon">🔒</span>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="mantener_sesion" name="mantener_sesion">
                    <label for="mantener_sesion">Mantener sesión iniciada (30 días)</label>
                </div>

                <input type="hidden" name="tab_id" id="tab_id" value="">
                <input type="hidden" name="login_gestor" value="1">
                <button type="submit" class="btn-login" id="btnLogin">Acceder al Gestor</button>
            </form>
        </div>

        <div class="login-foot">
            <span class="lock-icon">🔐</span> Acceso restringido · Grupo ATU
        </div>
    </div>

<script>
/* Tab ID */
(function(){
    const KEY  = 'gestor_tab_id';
    const form = document.getElementById('loginForm');
    const chk  = document.getElementById('mantener_sesion');
    const hid  = document.getElementById('tab_id');
    function genId(){
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return String(Date.now()) + '_' + Math.random().toString(16).slice(2);
    }
    form.addEventListener('submit', function(){
        if (chk.checked) { sessionStorage.removeItem(KEY); hid.value=''; return; }
        let id = sessionStorage.getItem(KEY);
        if (!id) { id = genId(); sessionStorage.setItem(KEY, id); }
        hid.value = id;
    });
})();

/* Ripple en botón */
document.getElementById('btnLogin').addEventListener('click', function(e){
    const r = document.createElement('span');
    r.className = 'ripple';
    const rect = this.getBoundingClientRect();
    r.style.left = (e.clientX - rect.left) + 'px';
    r.style.top  = (e.clientY - rect.top)  + 'px';
    this.appendChild(r);
    setTimeout(() => r.remove(), 700);
});

/* Shake si hay error */
<?php if ($login_error): ?>
(function(){
    const box = document.getElementById('loginBox');
    box.classList.add('shake');
    setTimeout(() => box.classList.remove('shake'), 500);
})();
<?php endif; ?>
</script>
</body>
</html>
<?php
exit;
endif;

/* =========================================================
   PANEL LOGUEADO
   ========================================================= */
$persistente = !empty($_SESSION['gestor_persistente'])
    || (!empty($_COOKIE['gestor_loggedin']) && $_COOKIE['gestor_loggedin'] === 'yes');

$tabEsperado = (!$persistente && !empty($_SESSION['gestor_tab_id']))
    ? $_SESSION['gestor_tab_id']
    : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Cuestionarios · ATU</title>
    <link rel="icon" type="image/png" href="custom/img/Logo-Gestor-Atu.png?v=1">
    <link rel="shortcut icon" type="image/png" href="custom/img/Logo-Gestor-Atu.png?v=1">
    <style>
        /* =========================================================
           VARIABLES — igual que options.php y fields.php
           ========================================================= */
        :root {
            --azul-atu:       #000099;
            --azul-btn:       #2563eb;
            --azul-btn2:      #1d4ed8;
            --verde-ok:       #16a34a;
            --naranja-oculto: #f97316;
            --rojo-cerrar:    #ef4444;
            --gris-bg:        #f8fafc;
            --gris-borde:     #e2e8f0;
            --texto-base:     #1e293b;
            --muted:          #64748b;
        }

        /* =========================================================
           ANIMACIONES
           ========================================================= */
        @keyframes fadeInCard   { from { opacity:0; transform:translateY(18px) } to { opacity:1; transform:none } }
        @keyframes popModal     { from { opacity:0; transform:scale(.96) } to { opacity:1; transform:scale(1) } }
        @keyframes floatLogo    { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-4px)} }
        @keyframes pulseActive  { 0%{box-shadow:0 0 0 0 rgba(37,99,235,.4)} 70%{box-shadow:0 0 0 10px rgba(37,99,235,0)} 100%{box-shadow:0 0 0 0 rgba(37,99,235,0)} }
        @keyframes lineSlide    { from{width:0} to{width:100%} }
        @keyframes cardStagger  { from{opacity:0;transform:translateY(24px) scale(.98)} to{opacity:1;transform:none} }
        @keyframes arrowBounce  { 0%,100%{transform:translateX(0)} 50%{transform:translateX(5px)} }
        @keyframes shimmer      { from{background-position:200% center} to{background-position:-200% center} }

        *, *::before, *::after { box-sizing:border-box }

        body {
            font-family:'Segoe UI', system-ui, sans-serif;
            background:var(--azul-atu);
            margin:0; padding:20px;
            color:var(--texto-base);
            overflow-x:hidden;
            min-height:100vh;
        }

        /* =========================================================
           CONTENEDOR PRINCIPAL — idéntico a options/fields
           ========================================================= */
        .main-wrapper {
            max-width:1100px; margin:0 auto;
            background:#fff;
            border-radius:25px;
            box-shadow:0 30px 70px rgba(0,0,0,.5);
            animation:popModal .4s ease-out;
            overflow:hidden;
        }

        /* =========================================================
           CABECERA CON LOGO
           ========================================================= */
        .header-brand {
            padding:35px;
            text-align:center;
            border-bottom:1px solid var(--gris-borde);
            background:#fff;
            position:relative;
        }
        .header-brand::after {
            content:'';
            position:absolute;
            left:0; bottom:0;
            height:3px; width:0;
            background:linear-gradient(90deg, var(--azul-btn), #22c55e);
            animation:lineSlide .9s .2s cubic-bezier(.4,0,.2,1) forwards;
        }
        .logo-link { display:inline-block; text-decoration:none }
        .header-brand img {
            max-width:300px; width:100%; height:auto; display:block; margin:0 auto;
            animation:floatLogo 4.5s ease-in-out infinite;
            cursor:pointer;
            transition:filter .2s, transform .2s;
        }
        .logo-link:hover img { filter:brightness(1.04); transform:translateY(-2px) }
        .logo-link:active img { opacity:.92 }

        /* =========================================================
           BARRA NAV — idéntica a options/fields
           ========================================================= */
        .nav-bar {
            display:flex; gap:12px; padding:18px 35px;
            background:var(--gris-bg);
            border-bottom:1px solid var(--gris-borde);
            align-items:center; flex-wrap:wrap;
        }
        .btn-nav-styled {
            border:none; padding:12px 22px; border-radius:14px;
            background:#fff; color:var(--texto-base);
            cursor:pointer; font-weight:850; font-size:14px;
            text-decoration:none; display:inline-flex; align-items:center; gap:9px;
            box-shadow:0 4px 6px rgba(0,0,0,.03);
            transition:transform .3s, box-shadow .3s, background .3s;
            font-family:inherit;
        }
        .btn-nav-styled:hover {
            transform:translateY(-3px);
            box-shadow:0 8px 18px rgba(0,0,0,.08);
        }
        .btn-nav-styled.active {
            background:var(--azul-btn); color:#fff;
            animation:pulseActive 2s infinite;
        }
        .btn-nav-styled.active:hover { transform:translateY(-3px) }
        .btn-grad-manual {
            margin-left:auto;
            background:linear-gradient(135deg, #fbbf24, #ef4444);
            color:#fff !important;
            box-shadow:0 8px 20px rgba(239,68,68,.22);
        }
        .btn-grad-manual:hover { filter:brightness(1.06) }

        /* =========================================================
           CUERPO PRINCIPAL
           ========================================================= */
        .body-container { padding:44px 40px 50px }

        /* Hero */
        .hero {
            text-align:center;
            max-width:680px;
            margin:0 auto 48px;
            animation:fadeInCard .5s ease-out both;
        }
        .hero h2 {
            margin:0 0 14px;
            font-size:clamp(28px,4vw,42px);
            font-weight:900;
            letter-spacing:-.5px;
            color:var(--azul-atu);
            line-height:1.1;
        }
        .hero h2 span {
            background:linear-gradient(135deg, var(--azul-btn), #22c55e);
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
        }
        .hero p {
            margin:0;
            color:var(--muted);
            font-size:16px; line-height:1.75;
        }

        /* =========================================================
           TARJETAS DE ACCESO — mismo estilo que list-panel
           ========================================================= */
        .accesos-grid {
            display:grid;
            grid-template-columns:repeat(3, 1fr);
            gap:24px;
            max-width:1100px;
            margin:0 auto;
        }

        .acceso-card {
            position:relative;
            display:flex; flex-direction:column;
            text-decoration:none; color:inherit;
            background:#fff;
            border:1.5px solid var(--gris-borde);
            border-radius:22px;
            padding:28px;
            box-shadow:0 4px 14px rgba(0,0,0,.04);
            transition:transform .3s cubic-bezier(.165,.84,.44,1),
                        box-shadow .3s, border-color .3s;
            overflow:hidden;
            opacity:0;
        }
        .acceso-card.visible { animation:cardStagger .55s cubic-bezier(.165,.84,.44,1) both }
        .acceso-card:nth-child(1) { animation-delay:.05s }
        .acceso-card:nth-child(2) { animation-delay:.15s }
        .acceso-card:nth-child(3) { animation-delay:.25s }

        .acceso-card:hover {
            transform:translateY(-6px) scale(1.01);
            box-shadow:0 20px 40px rgba(0,0,0,.1);
            border-color:var(--azul-btn);
        }

        /* Barra de color superior — igual que list-panel en options */
        .acceso-card::before {
            content:'';
            position:absolute; top:0; left:0;
            width:100%; height:4px;
            border-radius:22px 22px 0 0;
            transition:height .3s ease;
        }
        .acceso-card:hover::before { height:6px }

        .acceso-valoraciones::before         { background:linear-gradient(90deg, #2563eb, #60a5fa) }
        .acceso-incidencias::before          { background:linear-gradient(90deg, #16a34a, #22c55e) }
        .acceso-valoraciones-tutores::before { background:linear-gradient(90deg, #7c3aed, #a78bfa) }

        /* Icono */
        .acceso-icono {
            width:52px; height:52px; border-radius:16px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:24px; line-height:1;
            margin-bottom:18px;
            transition:transform .3s, box-shadow .3s;
        }
        .acceso-card:hover .acceso-icono { transform:scale(1.1) rotate(-4deg) }
        .acceso-valoraciones .acceso-icono { background:#dbeafe; color:#1d4ed8; box-shadow:0 4px 14px rgba(37,99,235,.15) }
        .acceso-incidencias  .acceso-icono { background:#dcfce7; color:#15803d; box-shadow:0 4px 14px rgba(22,163,74,.15) }
        .acceso-valoraciones-tutores .acceso-icono { background:#ede9fe; color:#6d28d9; box-shadow:0 4px 14px rgba(124,58,237,.15) }

        .acceso-card h3 {
            margin:0 0 10px;
            font-size:20px; font-weight:900;
            color:var(--texto-base);
            line-height:1.2;
        }
        .acceso-card p {
            margin:0 0 20px;
            color:var(--muted);
            font-size:14px; line-height:1.65;
            flex:1;
        }

        /* Pie de tarjeta */
        .acceso-pie {
            display:flex; justify-content:space-between; align-items:center;
            gap:10px; flex-wrap:wrap; margin-top:auto;
            padding-top:18px;
            border-top:1px solid var(--gris-borde);
        }
        .acceso-tag {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 13px; border-radius:999px;
            font-size:12px; font-weight:900;
        }
        .acceso-valoraciones .acceso-tag {
            background:#eff6ff; color:#1d4ed8;
            border:1px solid #bfdbfe;
        }
        .acceso-incidencias .acceso-tag {
            background:#ecfdf5; color:#15803d;
            border:1px solid #bbf7d0;
        }
        .acceso-valoraciones-tutores .acceso-tag {
            background:#f5f3ff; color:#6d28d9;
            border:1px solid #ddd6fe;
        }
        .acceso-abrir {
            display:inline-flex; align-items:center; gap:5px;
            font-size:13px; font-weight:900;
            color:var(--muted);
            transition:color .2s, gap .2s;
        }
        .acceso-card:hover .acceso-abrir {
            color:var(--azul-btn);
        }
        .acceso-abrir .arrow {
            display:inline-block;
            transition:transform .3s;
        }
        .acceso-card:hover .acceso-abrir .arrow {
            transform:translateX(4px);
        }

        /* =========================================================
           BOTÓN CERRAR SESIÓN — igual que options/fields
           ========================================================= */
        .btn-logout {
            position:fixed; top:16px; right:18px; z-index:99999;
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 16px; border-radius:14px;
            background:linear-gradient(135deg, #dc2626, #ef4444);
            color:#fff; font-size:13px; font-weight:900;
            text-decoration:none; letter-spacing:.3px;
            box-shadow:0 6px 18px rgba(220,38,38,.35);
            transition:opacity .25s, transform .25s;
        }
        .btn-logout:hover { filter:brightness(1.08); transform:translateY(-1px) }
        .btn-logout .dot {
            width:10px; height:10px; border-radius:50%;
            background:#fff;
            box-shadow:0 0 0 4px rgba(255,255,255,.18);
        }
        .btn-logout.oculto-scroll { opacity:0; transform:translateY(-14px) scale(.98); pointer-events:none }

        /* =========================================================
           RESPONSIVE
           ========================================================= */
        @media (max-width:900px) {
            .body-container { padding:28px 24px 36px }
            .nav-bar { padding:14px 24px }
            .header-brand { padding:24px }
        }
        @media (max-width:640px) {
            body { padding:10px }
            .nav-bar { padding:12px 14px; gap:8px; overflow-x:auto; flex-wrap:nowrap }
            .btn-nav-styled { flex-shrink:0; font-size:13px; padding:10px 16px }
            .accesos-grid { grid-template-columns:1fr; gap:16px }
            .hero h2 { font-size:28px }
            .quick-links { flex-direction:column }
        }

        @media (prefers-reduced-motion:reduce) { *, *::before, *::after { animation:none!important; transition:none!important } }
    </style>
</head>
<body>

<!-- Botón Cerrar Sesión -->
<a class="btn-logout" id="btnLogout" href="?logout=1" title="Cerrar sesión">
    <span class="dot"></span>
    Cerrar sesión
</a>

<script>
/* Validación sesión por pestaña */
(function(){
    const persistente = <?php echo $persistente ? 'true' : 'false'; ?>;
    if (persistente) return;
    const expected = <?php echo json_encode($tabEsperado); ?>;
    const KEY = 'gestor_tab_id';
    const current = sessionStorage.getItem(KEY);
    if (!expected || !current || current !== expected) {
        window.location.replace('?logout=1');
    }
})();
</script>

<script>
/* Ocultar botón logout al hacer scroll */
(function(){
    const btn = document.getElementById('btnLogout');
    if (!btn) return;
    btn.addEventListener('click', function(){
        try { sessionStorage.removeItem('gestor_tab_id'); } catch(e){}
    });
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

<div class="main-wrapper">

    <!-- CABECERA CON LOGO -->
    <div class="header-brand">
        <a class="logo-link" href="Gestor.php" title="Inicio" aria-label="Inicio del gestor">
            <img src="custom/img/logo-atu-gestor.png" alt="Logo Grupo ATU">
        </a>
    </div>

    <!-- BARRA DE NAVEGACIÓN -->
    <div class="nav-bar">
        <button class="btn-nav-styled active">🏠 Inicio</button>
        <a href="gestor_incidencias.php" class="btn-nav-styled">⚠️ Incidencias</a>
        <a href="gestor_valoraciones.php" class="btn-nav-styled">📝 Valoraciones</a>
        <a href="gestor_valoraciones_tutores.php" class="btn-nav-styled">🎓 Val. Tutores</a>
        <a href="ayuda.php" class="btn-nav-styled btn-grad-manual">📘 Manual</a>
    </div>

    <!-- CUERPO PRINCIPAL -->
    <div class="body-container">

        <!-- HERO -->
        <div class="hero">
            <h2>Gestor de <span>cuestionarios</span></h2>
        </div>

        <!-- TARJETAS PRINCIPALES -->
        <div class="accesos-grid" id="accesosGrid">

            <!-- VALORACIONES -->
            <a href="gestor_valoraciones.php" class="acceso-card acceso-valoraciones">
                <div class="acceso-icono">📝</div>
                <h3>Cuestionario Valoraciones</h3>
                <p>Gestiona los campos, listas desplegables y opciones del cuestionario de valoraciones.</p>
                <div class="acceso-pie">
                    <span class="acceso-tag">🟣 Valoraciones</span>
                    <span class="acceso-abrir">Entrar <span class="arrow">→</span></span>
                </div>
            </a>

            <!-- INCIDENCIAS -->
            <a href="gestor_incidencias.php" class="acceso-card acceso-incidencias">
                <div class="acceso-icono">⚠️</div>
                <h3>Cuestionario Incidencias</h3>
                <p>Gestiona los campos, listas desplegables y opciones del cuestionario de incidencias.</p>
                <div class="acceso-pie">
                    <span class="acceso-tag">🔵 Incidencias</span>
                    <span class="acceso-abrir">Entrar <span class="arrow">→</span></span>
                </div>
            </a>

            <!-- VALORACIÓN DE TUTORES -->
            <a href="gestor_valoraciones_tutores.php" class="acceso-card acceso-valoraciones-tutores">
                <div class="acceso-icono">🎓</div>
                <h3>Valoración de Tutores</h3>
                <p>Gestiona los campos, listas desplegables y opciones del cuestionario de valoración de tutores.</p>
                <div class="acceso-pie">
                    <span class="acceso-tag">🟣 Val. Tutores</span>
                    <span class="acceso-abrir">Entrar <span class="arrow">→</span></span>
                </div>
            </a>

        </div>



    </div><!-- /.body-container -->
</div><!-- /.main-wrapper -->

<script>
/* Animar tarjetas con IntersectionObserver */
(function(){
    const cards = document.querySelectorAll('.acceso-card');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function(entries){
            entries.forEach(function(e){
                if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
            });
        }, { threshold:.1 });
        cards.forEach(function(c){ io.observe(c); });
    } else {
        cards.forEach(function(c){ c.classList.add('visible'); });
    }
})();

/* Logo: click en misma página = scroll suave al top */
(function(){
    const a = document.querySelector('.header-brand .logo-link');
    if (!a) return;
    a.addEventListener('click', function(e){
        try {
            const u = new URL(a.getAttribute('href'), location.href);
            if (u.origin === location.origin && u.pathname === location.pathname) {
                e.preventDefault();
                window.scrollTo({ top:0, behavior:'smooth' });
            }
        } catch(err){}
    });
})();

/* Mantener posición de scroll */
(function(){
    const KEY = 'scrollY:' + location.pathname;
    const ARM = 'scrollARM:' + location.pathname;
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    function save(){ try { sessionStorage.setItem(KEY, String(window.scrollY||0)); } catch(e){} }
    function arm() { try { sessionStorage.setItem(ARM, String(Date.now())); } catch(e){} }
    let raf=null;
    window.addEventListener('scroll', function(){ if(raf) return; raf=requestAnimationFrame(()=>{raf=null;save();}) }, { passive:true });
    window.addEventListener('beforeunload', save);
    document.addEventListener('submit', function(){ save(); arm(); }, true);
    function navType(){ try{ const n=performance.getEntriesByType('navigation')[0]; return n?n.type:''; }catch(e){return '';} }
    function shouldRestore(){ const t=navType(); if(t==='reload'||t==='back_forward') return true; try{ const a=parseInt(sessionStorage.getItem(ARM)||'0',10)||0; if(a&&(Date.now()-a)<120000) return true; }catch(e){} return false; }
    function restore(){ if(!shouldRestore()) return; try{sessionStorage.removeItem(ARM);}catch(e){} let y=0; try{y=parseInt(sessionStorage.getItem(KEY)||'0',10)||0;}catch(e){} if(y<=0) return; requestAnimationFrame(()=>window.scrollTo(0,y)); }
    window.addEventListener('pageshow', restore);
    window.addEventListener('load', restore);
})();
</script>

</body>
</html>