<?php
require_once __DIR__ . '/db.php';

/* =========================
   SESIÓN
========================= */
ini_set('session.gc_maxlifetime', (8 * 60 * 60));
$__secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $__secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(0, '/', '', $__secure, true);
}

session_start();

if (
    empty($_SESSION['gestor_loggedin'])
    && !empty($_COOKIE['gestor_loggedin'])
    && $_COOKIE['gestor_loggedin'] === 'yes'
) {
    $_SESSION['gestor_loggedin']    = true;
    $_SESSION['gestor_persistente'] = true;
}

$logged = !empty($_SESSION['gestor_loggedin'])
    || (!empty($_COOKIE['gestor_loggedin']) && $_COOKIE['gestor_loggedin'] === 'yes');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    setcookie('gestor_loggedin', '', time() - 3600, '/');
    header('Location: Gestor.php');
    exit;
}

if (!$logged) {
    header('Location: Gestor.php');
    exit;
}

/* Página de origen (para el botón "Volver") */
$referer = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $allowed = ['Gestor.php', 'gestor_incidencias.php', 'gestor_valoraciones.php', 'gestor_valoraciones_tutores.php', 'fields.php', 'options.php'];
    $basename = basename($ref);
    if (in_array($basename, $allowed, true)) {
        $referer = htmlspecialchars($basename, ENT_QUOTES, 'UTF-8');
    }
}
if (!$referer) $referer = 'Gestor.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manual de uso — Gestor ATU</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
/* ===== RESET & VARIABLES ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --navy:   #000099;
    --navy2:  #001bb3;
    --blue:   #2563eb;
    --blue2:  #1d4ed8;
    --green:  #16a34a;
    --gold:   #f59e0b;
    --red:    #ef4444;
    --slate:  #1e293b;
    --muted:  #64748b;
    --border: #e2e8f0;
    --white:  #ffffff;
    --offwhite: #f8fafc;
    --card-shadow: 0 8px 30px rgba(0,0,153,.10);
}

html { scroll-behavior: smooth; }

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
    min-height: 100vh;
    padding: 20px 16px 60px;
    color: var(--slate);
}

/* ===== BOTÓN CERRAR SESIÓN FIJO ===== */
.btn-logout {
    position: fixed;
    top: 14px; right: 14px;
    z-index: 9997;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 900;
    font-size: 13px;
    color: #fff;
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    box-shadow: 0 12px 26px rgba(185,28,28,.30);
    transition: opacity .25s ease, transform .25s ease;
}

.btn-logout .dot {
    width: 10px; height: 10px;
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(255,255,255,.18);
}

.btn-logout:hover { transform: translateY(-2px); }

.btn-logout.oculto-scroll {
    opacity: 0;
    transform: translateY(-15px);
    pointer-events: none;
}

/* ===== BARRA DE ACCIONES (bajo el hero) ===== */
.barra-acciones {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 18px 28px;
    background: #f1f5f9;
    border-bottom: 1px solid var(--border);
}

.btn-accion {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13.5px;
    transition: transform .25s ease, box-shadow .25s ease;
    white-space: nowrap;
}

.btn-accion-inicio {
    color: #fff;
    background: linear-gradient(135deg, var(--blue), var(--blue2));
    box-shadow: 0 6px 18px rgba(37,99,235,.30);
}

.btn-accion-volver {
    color: #fff;
    background: linear-gradient(135deg, var(--green), #15803d);
    box-shadow: 0 6px 18px rgba(22,163,74,.30);
}

.btn-accion:hover { transform: translateY(-2px); }

/* ===== CONTENEDOR ===== */
.contenedor {
    max-width: 1140px;
    margin: 0 auto;
    background: var(--white);
    border-radius: 28px;
    box-shadow: 0 30px 70px rgba(0,0,0,.40);
    overflow: hidden;
    animation: slideUp .65s cubic-bezier(.2,.8,.2,1) both;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px); }
    to   { opacity: 1; transform: none; }
}

/* ===== CABECERA ===== */
.cabecera {
    padding: 14px 24px;
    text-align: center;
    background: linear-gradient(to bottom, #fff, #f8fafc);
    border-bottom: 1px solid var(--border);
}

.cabecera a img {
    max-width: 280px;
    display: block;
    margin: 0 auto;
    transition: transform .3s ease;
}

.cabecera a:hover img { transform: scale(1.04); }

/* ===== HERO BANNER ===== */
.hero {
    position: relative;
    overflow: hidden;
    padding: 32px 40px 28px;
    background: linear-gradient(135deg, #000099 0%, #1d4ed8 100%);
    text-align: center;
    color: #fff;
}

.hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,.07) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(255,255,255,.05) 0%, transparent 50%);
}

.hero-icon {
    font-size: 36px;
    margin-bottom: 10px;
    display: block;
    animation: float 3.5s ease-in-out infinite;
}

@keyframes float {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-8px); }
}

.hero h1 {
    font-size: clamp(20px, 3vw, 28px);
    font-weight: 800;
    letter-spacing: -.5px;
    position: relative;
    margin-bottom: 10px;
}

.hero p {
    font-size: 14px;
    opacity: .88;
    max-width: 560px;
    margin: 0 auto;
    line-height: 1.65;
    position: relative;
}

/* ===== ÍNDICE RÁPIDO ===== */
.indice {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 22px 32px;
    background: var(--offwhite);
    border-bottom: 1px solid var(--border);
}

.indice a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    background: #fff;
    color: var(--navy);
    border: 1.5px solid var(--border);
    transition: all .25s;
}

.indice a:hover {
    background: var(--navy);
    color: #fff;
    border-color: var(--navy);
    transform: translateY(-2px);
}

/* ===== CONTENIDO ===== */
.contenido {
    padding: clamp(24px, 5vw, 52px);
}

/* ===== SECCIÓN ===== */
.seccion {
    margin-bottom: 52px;
    scroll-margin-top: 80px;
}

.seccion-titulo {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    padding-bottom: 14px;
    border-bottom: 2px solid var(--border);
}

.seccion-num {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px; height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--navy), var(--blue));
    color: #fff;
    font-weight: 800;
    font-size: 17px;
    flex-shrink: 0;
}

.seccion-titulo h2 {
    font-size: clamp(18px, 3vw, 24px);
    font-weight: 800;
    color: var(--slate);
}

/* ===== CARDS GENÉRICAS ===== */
.grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.card {
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 20px;
    padding: 26px;
    box-shadow: var(--card-shadow);
    transition: transform .35s cubic-bezier(.175,.885,.32,1.275), box-shadow .35s;
    opacity: 0;
    transform: translateY(28px);
}

.card.visible {
    opacity: 1;
    transform: none;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(0,0,153,.14);
}

.card-icon {
    font-size: 32px;
    margin-bottom: 14px;
    display: block;
}

.card h3 {
    font-size: 17px;
    font-weight: 800;
    color: var(--slate);
    margin-bottom: 10px;
}

.card p, .card li {
    font-size: 14.5px;
    color: var(--muted);
    line-height: 1.75;
}

.card ul { padding-left: 18px; }
.card ul li { margin-bottom: 6px; }

/* ===== CARDS PASO A PASO ===== */
.pasos {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.paso {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    background: var(--offwhite);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    padding: 20px 22px;
    opacity: 0;
    transform: translateX(-20px);
    transition: opacity .5s ease, transform .5s ease;
}

.paso.visible {
    opacity: 1;
    transform: none;
}

.paso-num {
    min-width: 38px; height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--navy), var(--blue));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 15px;
    flex-shrink: 0;
}

.paso-body strong {
    display: block;
    font-size: 15px;
    font-weight: 800;
    color: var(--slate);
    margin-bottom: 6px;
}

.paso-body p {
    font-size: 14px;
    color: var(--muted);
    line-height: 1.7;
    margin: 0;
}

/* ===== PILLS / BADGES ===== */
.pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    vertical-align: middle;
}

.pill-green  { background: #dcfce7; color: #166534; }
.pill-gold   { background: #fef9c3; color: #854d0e; }
.pill-blue   { background: #dbeafe; color: #1e40af; }
.pill-red    { background: #fee2e2; color: #991b1b; }
.pill-slate  { background: #f1f5f9; color: #334155; }

/* ===== ALERTA INFO ===== */
.alerta {
    display: flex;
    gap: 14px;
    padding: 18px 22px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-size: 14.5px;
    line-height: 1.65;
    border: 1.5px solid;
}

.alerta-icon { font-size: 22px; flex-shrink: 0; }
.alerta p    { margin: 0; }

.alerta-info   { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.alerta-warn   { background: #fefce8; border-color: #fde047; color: #854d0e; }
.alerta-ok     { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }

/* ===== DIAGRAMA FLUJO ===== */
.flujo {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin: 24px 0;
    padding: 22px;
    background: linear-gradient(135deg, #eff6ff, #f8fafc);
    border: 1.5px solid #bfdbfe;
    border-radius: 18px;
}

.flujo-paso {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 120px;
    text-align: center;
}

.flujo-burbuja {
    width: 56px; height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    background: linear-gradient(135deg, var(--navy), var(--blue));
    box-shadow: 0 8px 18px rgba(37,99,235,.3);
    animation: pulse 2.5s ease-in-out infinite;
}

.flujo-burbuja:nth-child(1) { animation-delay: 0s; }

@keyframes pulse {
    0%,100% { transform: scale(1); box-shadow: 0 8px 18px rgba(37,99,235,.3); }
    50%      { transform: scale(1.07); box-shadow: 0 12px 26px rgba(37,99,235,.45); }
}

.flujo-paso span {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--slate);
}

.flujo-arrow {
    font-size: 22px;
    color: var(--blue);
    opacity: .6;
    flex-shrink: 0;
}

/* ===== FAQ ===== */
.faq-lista { display: flex; flex-direction: column; gap: 12px; }

details.faq {
    border: 1.5px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: box-shadow .3s;
}

details.faq[open] {
    box-shadow: 0 8px 24px rgba(0,0,153,.10);
    border-color: #bfdbfe;
}

details.faq summary {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
    cursor: pointer;
    background: var(--offwhite);
    font-weight: 700;
    font-size: 15px;
    color: var(--slate);
    list-style: none;
    user-select: none;
    transition: background .2s;
}

details.faq summary::-webkit-details-marker { display: none; }

details.faq[open] summary {
    background: linear-gradient(135deg, #eff6ff, #f0f9ff);
    color: var(--navy);
}

.faq-toggle {
    margin-left: auto;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 900;
    transition: background .2s, transform .3s;
    flex-shrink: 0;
}

details.faq[open] .faq-toggle {
    background: var(--navy);
    color: #fff;
    transform: rotate(45deg);
}

.faq-body {
    padding: 20px 24px 22px;
    font-size: 14.5px;
    color: var(--muted);
    line-height: 1.8;
    border-top: 1.5px solid var(--border);
    background: #fff;
    animation: slideDown .3s ease;
}

.faq-body ul  { padding-left: 18px; margin-top: 10px; }
.faq-body li  { margin-bottom: 6px; }
.faq-body strong { color: var(--slate); }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: none; }
}

/* ===== TABLA COMPARATIVA ===== */
.tabla-estados {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1.5px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    font-size: 14px;
    margin-top: 4px;
}

.tabla-estados th {
    background: linear-gradient(135deg, var(--navy), var(--blue));
    color: #fff;
    padding: 13px 18px;
    text-align: left;
    font-weight: 700;
}

.tabla-estados td {
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    vertical-align: top;
}

.tabla-estados tr:nth-child(even) td { background: var(--offwhite); }

/* ===== SEPARADOR ===== */
.sep {
    height: 1px;
    background: linear-gradient(to right, transparent, var(--border), transparent);
    margin: 48px 0;
}

/* ===== FOOTER DEL MANUAL ===== */
.manual-footer {
    margin-top: 52px;
    padding: 32px;
    text-align: center;
    background: linear-gradient(135deg, #f0f9ff, #eff6ff);
    border-top: 1.5px solid #bfdbfe;
    border-radius: 0 0 28px 28px;
}

.manual-footer p {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 18px;
}

.btn-volver-abajo {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 28px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--navy), var(--blue));
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
    box-shadow: 0 8px 20px rgba(0,0,153,.25);
    transition: transform .25s, box-shadow .25s;
}

.btn-volver-abajo:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 28px rgba(0,0,153,.30);
}

/* ===== BARRA PROGRESO LECTURA ===== */
#barra-progreso {
    position: fixed;
    top: 0; left: 0;
    width: 0%;
    height: 4px;
    background: linear-gradient(to right, var(--gold), var(--blue));
    z-index: 99999;
    transition: width .1s linear;
    border-radius: 0 4px 4px 0;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 640px) {
    .barra-acciones { gap: 8px; padding: 14px 16px; }
    .btn-accion { padding: 9px 14px; font-size: 12.5px; }
    .flujo { flex-direction: column; align-items: flex-start; }
    .flujo-arrow { transform: rotate(90deg); }
    .hero { padding: 26px 20px 22px; }
    .indice { padding: 16px 20px; }
    .contenido { padding: 22px 18px; }
}
</style>
</head>
<body>

<!-- Barra de progreso de lectura -->
<div id="barra-progreso"></div>

<!-- Botón cerrar sesión fijo -->
<a class="btn-logout" id="btnLogout" href="?logout=1">
    <span class="dot"></span>
    Cerrar sesión
</a>

<div class="contenedor">

    <!-- CABECERA CON LOGO -->
    <div class="cabecera">
        <a href="Gestor.php" title="Ir al inicio">
            <img src="custom/img/logo-atu-gestor.png" alt="Logo ATU Gestor">
        </a>
    </div>

    <!-- HERO -->
    <div class="hero">
        <span class="hero-icon">📖</span>
        <h1>Manual de uso del Gestor</h1>
        <p>Guía completa para configurar y administrar los formularios de Ostiket.</p>
    </div>

    <!-- BARRA DE ACCIONES -->
    <div class="barra-acciones">
        <a class="btn-accion btn-accion-volver" href="<?= $referer ?>">← Volver</a>
        <a class="btn-accion btn-accion-inicio" href="Gestor.php">🏠 Inicio</a>
    </div>

    <!-- ÍNDICE RÁPIDO -->
    <nav class="indice" aria-label="Índice del manual">
        <a href="#sec-que-es">¿Qué es el Gestor?</a>
        <a href="#sec-acceso">Acceso</a>
        <a href="#sec-incidencias">Incidencias</a>
        <a href="#sec-valoraciones">Valoraciones</a>
        <a href="#sec-val-tutores">Val. Tutores</a>
        <a href="#sec-campos">Campos</a>
        <a href="#sec-listas">Listas</a>
        <a href="#sec-estados">Estados</a>
        <a href="#sec-orden">Cómo empezar</a>
        <a href="#sec-faq">Preguntas frecuentes</a>
    </nav>

    <div class="contenido">

        <!-- ===== 1. QUÉ ES ===== -->
        <section class="seccion" id="sec-que-es">
            <div class="seccion-titulo">
                <div class="seccion-num">1</div>
                <h2>¿Qué es el Gestor?</h2>
            </div>

            <div class="alerta alerta-info">
                <span class="alerta-icon">💡</span>
                <p>El Gestor es una herramienta de administración que permite <strong>personalizar los formularios del portal</strong> sin necesidad de tocar código. Lo que configures aquí se reflejará automáticamente en los cuestionarios que ven los usuarios.</p>
            </div>

            <div class="grid-2">
                <div class="card">
                    <span class="card-icon">🛠</span>
                    <h3>Cuestionario de Incidencias</h3>
                    <p>Gestiona los campos y listas desplegables del formulario que usan los usuarios para reportar incidencias en el portal.</p>
                </div>
                <div class="card">
                    <span class="card-icon">⭐</span>
                    <h3>Cuestionario de Valoraciones</h3>
                    <p>Configura los campos y opciones del formulario de valoraciones que rellenan los usuarios al puntuar el servicio.</p>
                </div>
                <div class="card">
                    <span class="card-icon">🎓</span>
                    <h3>Valoración de Coordinadores sobre el Docente</h3>
                    <p>Gestiona los campos y listas del cuestionario de valoración de tutores que rellenan los coordinadores para evaluar el desempeño del equipo docente y de apoyo.</p>
                </div>
                <div class="card">
                    <span class="card-icon">🧩</span>
                    <h3>Campos del formulario</h3>
                    <p>Son las preguntas o entradas de cada formulario: su nombre visible, el texto de ayuda, el tipo (texto libre o lista desplegable) y si están activos u ocultos.</p>
                </div>
                <div class="card">
                    <span class="card-icon">▾</span>
                    <h3>Listas de selección</h3>
                    <p>Son los menús desplegables con sus opciones. Por ejemplo, la lista "Tipo de incidencia" puede tener opciones como "Avería", "Consulta" o "Sugerencia".</p>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 2. ACCESO ===== -->
        <section class="seccion" id="sec-acceso">
            <div class="seccion-titulo">
                <div class="seccion-num">2</div>
                <h2>Cómo acceder al sistema</h2>
            </div>

            <div class="pasos">
                <div class="paso">
                    <div class="paso-num">1</div>
                    <div class="paso-body">
                        <strong>Introduce tus credenciales</strong>
                        <p>Accede con tu usuario y contraseña en la pantalla de inicio del Gestor.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">2</div>
                    <div class="paso-body">
                        <strong>Opción "Mantener sesión iniciada"</strong>
                        <p>Si marcas esta opción, podrás seguir accediendo durante <strong>30 días</strong> sin volver a introducir la contraseña. Ideal si usas un equipo personal.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">3</div>
                    <div class="paso-body">
                        <strong>Sin "Mantener sesión": modo pestaña</strong>
                        <p>La sesión se mantiene mientras la pestaña esté abierta. Si cierras el navegador o la pestaña, tendrás que volver a iniciar sesión.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">4</div>
                    <div class="paso-body">
                        <strong>Cerrar sesión</strong>
                        <p>Usa el botón <span class="pill pill-red">🔴 Cerrar sesión</span> (esquina superior derecha) cuando termines, especialmente en equipos compartidos.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 3. INCIDENCIAS ===== -->
        <section class="seccion" id="sec-incidencias">
            <div class="seccion-titulo">
                <div class="seccion-num">3</div>
                <h2>Sección de Incidencias</h2>
            </div>

            <div class="alerta alerta-warn">
                <span class="alerta-icon">⚠️</span>
                <p>Todo lo que cambies aquí afecta directamente al formulario de incidencias del portal. Los cambios son <strong>inmediatos</strong>.</p>
            </div>

            <div class="flujo">
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🏠</div>
                    <span>Panel principal</span>
                </div>
                <div class="flujo-arrow">→</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🛠</div>
                    <span>Incidencias</span>
                </div>
                <div class="flujo-arrow">→</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🧩</div>
                    <span>Campos base</span>
                </div>
                <div class="flujo-arrow">+</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">▾</div>
                    <span>Listas</span>
                </div>
            </div>

            <div class="alerta alerta-info" style="margin-top:0;">
                <span class="alerta-icon">🔵</span>
                <p>Al terminar, usa el botón azul <strong>"← Volver al panel principal"</strong> en la parte inferior de la página para regresar al inicio.</p>
            </div>

            <div class="grid-2">
                <div class="card">
                    <span class="card-icon">🧩</span>
                    <h3>Campos base de Incidencias</h3>
                    <ul>
                        <li>Gestiona los campos del formulario de incidencias.</li>
                        <li>Puedes cambiar el nombre que ve el usuario.</li>
                        <li>Añadir o editar el texto de ayuda que aparece bajo cada campo.</li>
                        <li>Activar o desactivar campos según las necesidades.</li>
                    </ul>
                </div>
                <div class="card">
                    <span class="card-icon">▾</span>
                    <h3>Listas de Incidencias</h3>
                    <ul>
                        <li>Administra las opciones de los menús desplegables (sectores, grupos, tipos, etc.).</li>
                        <li>Añade nuevas opciones o desactiva las que ya no se usen.</li>
                        <li>Las opciones desactivadas no aparecen para el usuario pero no se pierden.</li>
                    </ul>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 4. VALORACIONES ===== -->
        <section class="seccion" id="sec-valoraciones">
            <div class="seccion-titulo">
                <div class="seccion-num">4</div>
                <h2>Sección de Valoraciones</h2>
            </div>

            <div class="alerta alerta-warn">
                <span class="alerta-icon">⚠️</span>
                <p>Todo lo que cambies aquí afecta directamente al formulario de valoraciones del portal. Los cambios son <strong>inmediatos</strong>.</p>
            </div>

            <div class="flujo">
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🏠</div>
                    <span>Panel principal</span>
                </div>
                <div class="flujo-arrow">→</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">⭐</div>
                    <span>Valoraciones</span>
                </div>
                <div class="flujo-arrow">→</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🧩</div>
                    <span>Campos base</span>
                </div>
                <div class="flujo-arrow">+</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">▾</div>
                    <span>Listas</span>
                </div>
            </div>

            <div class="alerta alerta-info" style="margin-top:0;">
                <span class="alerta-icon">🔵</span>
                <p>Al terminar, usa el botón azul <strong>"← Volver al panel principal"</strong> en la parte inferior de la página para regresar al inicio.</p>
            </div>

            <div class="grid-2">
                <div class="card">
                    <span class="card-icon">🧩</span>
                    <h3>Campos base de Valoraciones</h3>
                    <ul>
                        <li>Configura los campos del cuestionario de valoraciones.</li>
                        <li>Edita nombres, textos de ayuda y visibilidad.</li>
                        <li>Controla qué campos son visibles y cuáles están ocultos.</li>
                    </ul>
                </div>
                <div class="card">
                    <span class="card-icon">▾</span>
                    <h3>Listas de Valoraciones</h3>
                    <ul>
                        <li>Administra las opciones de los selectores del cuestionario.</li>
                        <li>Añade, edita u oculta opciones de cada menú.</li>
                        <li>Mantén coherencia entre los campos y sus listas asociadas.</li>
                    </ul>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 5. VALORACIÓN DE TUTORES ===== -->
        <section class="seccion" id="sec-val-tutores">
            <div class="seccion-titulo">
                <div class="seccion-num">🎓</div>
                <h2>Sección de Valoración de Tutores</h2>
            </div>

            <div class="alerta alerta-warn">
                <span class="alerta-icon">⚠️</span>
                <p>Todo lo que cambies aquí afecta directamente al cuestionario de valoración de coordinadores sobre el docente del portal. Los cambios son <strong>inmediatos</strong>.</p>
            </div>

            <div class="flujo">
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🏠</div>
                    <span>Panel principal</span>
                </div>
                <div class="flujo-arrow">→</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🎓</div>
                    <span>Val. Tutores</span>
                </div>
                <div class="flujo-arrow">→</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">🧩</div>
                    <span>Campos base</span>
                </div>
                <div class="flujo-arrow">+</div>
                <div class="flujo-paso">
                    <div class="flujo-burbuja">▾</div>
                    <span>Listas</span>
                </div>
            </div>

            <div class="alerta alerta-info" style="margin-top:0;">
                <span class="alerta-icon">🟣</span>
                <p>Este cuestionario evalúa el desempeño del <strong>tutor/a</strong>, la <strong>persona de apoyo</strong> y el <strong>equipo de dinamización</strong>, así como el análisis del alumnado e incidencias relevantes del curso.</p>
            </div>

            <div class="grid-2">
                <div class="card">
                    <span class="card-icon">🧩</span>
                    <h3>Campos base de Val. Tutores</h3>
                    <ul>
                        <li>Gestiona los campos de texto e informativos del cuestionario (expediente, plan, sector, acción, nombre del curso, tutor, etc.).</li>
                        <li>Edita el nombre visible de cada campo y el texto de ayuda.</li>
                        <li>Activa u oculta campos según las necesidades del momento.</li>
                        <li>Los campos de tipo <span class="pill pill-slate">info</span> son encabezados de sección y no son editables desde aquí.</li>
                    </ul>
                </div>
                <div class="card">
                    <span class="card-icon">▾</span>
                    <h3>Listas de Val. Tutores</h3>
                    <ul>
                        <li>Administra los menús de valoración del desempeño (escala 1–4), participación, satisfacción, dificultades, etc.</li>
                        <li>Añade, edita u oculta opciones de cada desplegable.</li>
                        <li>Las listas son independientes de las de Incidencias y Valoraciones.</li>
                    </ul>
                </div>
            </div>

            <div class="alerta alerta-ok" style="margin-top:24px;">
                <span class="alerta-icon">📋</span>
                <p>Este formulario tiene <strong>52 campos</strong> organizados en secciones: datos generales, desempeño del tutor/a, desempeño de la persona de apoyo, desempeño del equipo de dinamización, análisis del alumnado, incidencias relevantes y valoración global final.</p>
            </div>
        </section>

        <!-- ===== 6. CAMPOS ===== -->
        <section class="seccion" id="sec-campos">
            <div class="seccion-titulo">
                <div class="seccion-num">6</div>
                <h2>Gestión de campos del formulario</h2>
            </div>

            <p style="color:var(--muted);margin-bottom:22px;font-size:15px;">Los <strong>campos</strong> son cada una de las preguntas o entradas que aparecen en el formulario. Aquí puedes personalizarlos sin límites.</p>

            <div class="pasos">
                <div class="paso">
                    <div class="paso-num">➕</div>
                    <div class="paso-body">
                        <strong>Crear un campo nuevo</strong>
                        <p>Pulsa el botón "Nuevo campo", escribe su nombre (lo que verá el usuario) y elige el tipo: <span class="pill pill-blue">Texto libre</span> si el usuario escribe lo que quiera, o <span class="pill pill-slate">Lista desplegable</span> si debe elegir entre opciones.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">✏️</div>
                    <div class="paso-body">
                        <strong>Editar un campo existente</strong>
                        <p>Haz clic en el icono de edición (lápiz) junto al campo. Podrás cambiar el nombre, el texto de ayuda que aparece bajo el campo, o el tipo de campo.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">👁️</div>
                    <div class="paso-body">
                        <strong>Activar o desactivar un campo</strong>
                        <p>Usa el interruptor o botón de estado para cambiar entre <span class="pill pill-green">Activo</span> (visible en el formulario) y <span class="pill pill-gold">Oculto</span> (no aparece pero no se borra).</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">🔗</div>
                    <div class="paso-body">
                        <strong>Asignar una lista a un campo</strong>
                        <p>Si el campo es de tipo "Lista desplegable", deberás asociarle una de las listas de selección creadas previamente. Sin lista asignada, el campo no podrá mostrar opciones.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 7. LISTAS ===== -->
        <section class="seccion" id="sec-listas">
            <div class="seccion-titulo">
                <div class="seccion-num">7</div>
                <h2>Gestión de listas de selección</h2>
            </div>

            <p style="color:var(--muted);margin-bottom:22px;font-size:15px;">Las <strong>listas</strong> son los menús desplegables con sus opciones. Cada lista puede usarse en uno o más campos de tipo "Lista desplegable".</p>

            <div class="pasos">
                <div class="paso">
                    <div class="paso-num">➕</div>
                    <div class="paso-body">
                        <strong>Crear una lista nueva</strong>
                        <p>Pulsa "Nueva lista" y ponle un nombre descriptivo, por ejemplo: "Tipo de incidencia" o "Valoración del servicio".</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">📝</div>
                    <div class="paso-body">
                        <strong>Añadir opciones a la lista</strong>
                        <p>Accede a la lista y usa el botón "Añadir opción". Cada opción es un elemento que el usuario podrá seleccionar en el menú. Puedes añadir tantas como necesites.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">👁️</div>
                    <div class="paso-body">
                        <strong>Ocultar una opción</strong>
                        <p>Si una opción ya no debe aparecer (pero no quieres perder su historial), puedes <span class="pill pill-gold">Ocultarla</span>. No se mostrará al usuario pero el sistema la conserva.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">✏️</div>
                    <div class="paso-body">
                        <strong>Editar una opción</strong>
                        <p>Haz clic en el lápiz junto a cualquier opción para cambiar su nombre o estado en cualquier momento.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 8. ESTADOS ===== -->
        <section class="seccion" id="sec-estados">
            <div class="seccion-titulo">
                <div class="seccion-num">8</div>
                <h2>Estados: Activo vs Oculto</h2>
            </div>

            <div class="alerta alerta-ok">
                <span class="alerta-icon">✅</span>
                <p>Los elementos <strong>nunca se borran permanentemente</strong>. Ocultarlos es siempre reversible: puedes volver a activarlos cuando quieras sin perder ningún dato histórico.</p>
            </div>

            <table class="tabla-estados">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>¿Aparece en el formulario?</th>
                        <th>¿Se conservan los datos?</th>
                        <th>¿Se puede reactivar?</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="pill pill-green">✅ Activo</span></td>
                        <td>Sí, es visible para el usuario</td>
                        <td>Sí</td>
                        <td>Ya está activo</td>
                    </tr>
                    <tr>
                        <td><span class="pill pill-gold">🙈 Oculto</span></td>
                        <td>No, el usuario no lo ve</td>
                        <td>Sí, todo el historial se conserva</td>
                        <td>Sí, en cualquier momento</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="sep"></div>

        <!-- ===== 9. ORDEN DE TRABAJO ===== -->
        <section class="seccion" id="sec-orden">
            <div class="seccion-titulo">
                <div class="seccion-num">9</div>
                <h2>¿Cómo empezar? Orden recomendado</h2>
            </div>

            <div class="alerta alerta-info">
                <span class="alerta-icon">💡</span>
                <p>Sigue este orden para evitar errores. Los campos de tipo lista necesitan que la lista ya exista antes de poder asociarse.</p>
            </div>

            <div class="pasos">
                <div class="paso">
                    <div class="paso-num">1</div>
                    <div class="paso-body">
                        <strong>Primero: crea las listas de selección</strong>
                        <p>Antes de crear campos de tipo "Lista desplegable", entra en "Listas desplegables" y crea todas las listas que vayas a necesitar con sus opciones.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">2</div>
                    <div class="paso-body">
                        <strong>Después: crea o edita los campos</strong>
                        <p>Con las listas ya creadas, accede a "Campos base" y configura cada campo: nombre, tipo y, si es desplegable, asocia la lista correspondiente.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">3</div>
                    <div class="paso-body">
                        <strong>Activa solo lo que sea necesario</strong>
                        <p>Mantén ocultos los campos que no se usen actualmente. Un formulario limpio y conciso mejora la experiencia del usuario.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">4</div>
                    <div class="paso-body">
                        <strong>Prueba antes de dar por terminado</strong>
                        <p>Abre el portal en otra pestaña y comprueba que el formulario se ve y funciona como esperas. Si algo no cuadra, vuelve al Gestor y ajústalo.</p>
                    </div>
                </div>
                <div class="paso">
                    <div class="paso-num">5</div>
                    <div class="paso-body">
                        <strong>Haz cambios progresivos</strong>
                        <p>Es mejor hacer un cambio, comprobar el resultado, y luego continuar. Así es más fácil detectar si algo no sale como esperabas.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="sep"></div>

        <!-- ===== 9. FAQ ===== -->
        <section class="seccion" id="sec-faq">
            <div class="seccion-titulo">
                <div class="seccion-num">❓</div>
                <h2>Preguntas frecuentes</h2>
            </div>

            <div class="faq-lista">

                <details class="faq">
                    <summary>
                        <span>👁️</span>
                        <span>He activado un campo pero no aparece en el formulario del portal</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>Comprueba lo siguiente paso a paso:</p>
                        <ul>
                            <li><strong>¿Está realmente activo?</strong> Entra en "Campos base" y confirma que el estado sea <span class="pill pill-green">Activo</span>.</li>
                            <li><strong>¿Estás en la sección correcta?</strong> Recuerda que Incidencias, Valoraciones y Valoración de Tutores tienen sus propios campos independientes.</li>
                            <li><strong>Recarga el portal</strong> con Ctrl + F5 para forzar la actualización de la caché del navegador.</li>
                            <li>Si el campo es de tipo lista, <strong>verifica que tenga una lista asignada</strong> con opciones activas.</li>
                        </ul>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>📋</span>
                        <span>Una opción de un menú desplegable ha desaparecido del portal</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>Lo más probable es que la opción esté <span class="pill pill-gold">Oculta</span>. Sigue estos pasos:</p>
                        <ul>
                            <li>Ve a "Listas desplegables" en la sección correspondiente (Incidencias, Valoraciones o Valoración de Tutores).</li>
                            <li>Abre la lista donde debería estar la opción.</li>
                            <li>Busca la opción — si aparece con estado "Oculto", pulsa el botón para activarla de nuevo.</li>
                        </ul>
                        <p>Los datos históricos no se pierden; solo era invisible para el usuario.</p>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>🔐</span>
                        <span>Se cierra la sesión sola y tengo que volver a entrar</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>Existen dos motivos habituales:</p>
                        <ul>
                            <li><strong>No marcaste "Mantener sesión"</strong> al iniciar sesión: en ese caso, la sesión expira cuando cierras la pestaña o el navegador.</li>
                            <li><strong>Han pasado más de 8 horas</strong> de inactividad: la sesión expira por seguridad.</li>
                        </ul>
                        <p>Si usas tu propio equipo y quieres evitarlo, marca la opción <strong>"Mantener sesión iniciada"</strong> la próxima vez que accedas.</p>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>🔄</span>
                        <span>He hecho cambios pero el portal sigue mostrando lo anterior</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>El navegador a veces guarda una copia antigua de la página. Prueba esto:</p>
                        <ul>
                            <li>Pulsa <strong>Ctrl + F5</strong> (o Cmd + Shift + R en Mac) en la pestaña del portal para forzar la recarga completa.</li>
                            <li>Si sigue igual, abre el portal en una ventana de incógnito.</li>
                            <li>Verifica en el Gestor que el cambio se guardó correctamente (el estado refleja lo que querías).</li>
                        </ul>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>🗑️</span>
                        <span>¿Puedo eliminar un campo o una opción de forma permanente?</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>El sistema está diseñado para <strong>no borrar permanentemente</strong> ningún elemento, ya que podría afectar a registros históricos ya guardados.</p>
                        <p>La opción correcta es <strong>Ocultar</strong> el elemento. Así deja de aparecer en el formulario pero el historial queda intacto. Si en el futuro necesitas recuperarlo, solo tienes que activarlo de nuevo.</p>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>🔗</span>
                        <span>¿Qué pasa si creo un campo de tipo lista sin asignarle ninguna lista?</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>El campo aparecerá en el formulario pero <strong>el menú desplegable estará vacío</strong>, sin opciones para elegir. El usuario no podrá seleccionar nada.</p>
                        <p>Asegúrate siempre de que:</p>
                        <ul>
                            <li>La lista asociada existe y tiene al menos una opción <span class="pill pill-green">Activa</span>.</li>
                            <li>El campo tiene esa lista correctamente asignada en su configuración.</li>
                        </ul>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>🏠</span>
                        <span>¿Cómo vuelvo al panel principal?</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p>Tienes varias formas de volver al inicio:</p>
                        <ul>
                            <li>Haz clic en el <strong>logo de ATU</strong> en la parte superior de cualquier página del Gestor.</li>
                            <li>Usa el botón <strong>"🏠 Inicio"</strong> que aparece en la esquina superior derecha.</li>
                            <li>En las páginas de Incidencias, Valoraciones y Valoración de Tutores, usa el botón azul <strong>"← Volver al panel principal"</strong> que encontrarás al final de la página.</li>
                        </ul>
                    </div>
                </details>

                <details class="faq">
                    <summary>
                        <span>📊</span>
                        <span>¿Los cambios en el Gestor afectan a los datos ya enviados?</span>
                        <span class="faq-toggle">+</span>
                    </summary>
                    <div class="faq-body">
                        <p><strong>No.</strong> Los datos que los usuarios ya han enviado a través del portal se guardan con la configuración que había en ese momento y <strong>no se modifican</strong>.</p>
                        <p>Los cambios en el Gestor solo afectan a cómo se muestra el formulario <strong>a partir de ese momento</strong>. El historial siempre queda intacto.</p>
                    </div>
                </details>

            </div>
        </section>

    </div><!-- fin .contenido -->

    <!-- FOOTER -->
    <div class="manual-footer">
        <a class="btn-volver-abajo" href="<?= $referer ?>">← Volver a la página anterior</a>
    </div>

</div><!-- fin .contenedor -->

<script>
/* ===== BARRA DE PROGRESO ===== */
(function () {
    const barra = document.getElementById('barra-progreso');
    window.addEventListener('scroll', function () {
        const scrolled = window.scrollY;
        const total    = document.body.scrollHeight - window.innerHeight;
        const pct      = total > 0 ? (scrolled / total) * 100 : 0;
        barra.style.width = pct + '%';
    }, { passive: true });
})();

/* ===== OCULTAR LOGOUT AL BAJAR ===== */
(function () {
    const btn  = document.getElementById('btnLogout');
    let lastY  = 0;
    window.addEventListener('scroll', function () {
        const y = window.scrollY;
        if (y > lastY && y > 60) {
            btn.classList.add('oculto-scroll');
        } else {
            btn.classList.remove('oculto-scroll');
        }
        lastY = y;
    }, { passive: true });
})();

/* ===== ANIMACIÓN TARJETAS ===== */
(function () {
    const io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.card').forEach(function (el) { io.observe(el); });
})();

/* ===== ANIMACIÓN PASOS ===== */
(function () {
    const io2 = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry, i) {
            if (entry.isIntersecting) {
                setTimeout(function () {
                    entry.target.classList.add('visible');
                }, 80);
                io2.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.paso').forEach(function (el) { io2.observe(el); });
})();
</script>

</body>
</html>