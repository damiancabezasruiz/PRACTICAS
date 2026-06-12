<?php
/**
 * =============================================================================
 * gestor_valoraciones.php — Panel de navegación del Gestor: sección Valoraciones
 * =============================================================================
 *
 * Propósito:
 *   Página intermedia del panel de gestión personalizado que actúa como
 *   hub de navegación para las herramientas de configuración del cuestionario
 *   de valoraciones (form_id = 16 en osTicket).
 *
 * Acceso:
 *   Requiere sesión de gestor activa ($_SESSION['gestor_loggedin']).
 *   Si no está autenticado, redirige a Gestor.php.
 *   Incluye lógica de cierre de sesión (?logout=1).
 *
 * Navegación disponible:
 *   - fields.php?tipo=valoraciones  → Gestión de campos base del formulario
 *     de valoraciones: nombre visible, texto de ayuda (hint), visibilidad.
 *   - options.php?tipo=valoraciones → Gestión de listas desplegables
 *     del cuestionario de valoraciones: opciones, visibilidad por sección.
 *
 * Aviso al usuario:
 *   Muestra un banner de advertencia indicando que los cambios afectan
 *   directamente al cuestionario de valoraciones del portal.
 *
 * Dependencias:
 *   - Sesión PHP activa con 'gestor_loggedin' = true
 *   - Gestor.php (login y panel principal)
 *   - fields.php, options.php
 *
 * @package    GrupoATU\Gestor
 * @author     Equipo de desarrollo Grupo ATU
 * @version    1.0
 * =============================================================================
 */

session_start();

// Lógica para cerrar sesión
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (session_id() !== '') session_destroy();
    header('Location: Gestor.php');
    exit;
}

if (empty($_SESSION['gestor_loggedin'])) {
    header("Location: Gestor.php");
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión - Valoraciones</title>

<style>
:root {
    --primary-blue: #000099;
    --accent-blue: #2563eb;
    --accent-gold: #f59e0b;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --bg-gradient: linear-gradient(135deg, #000099 0%, #001bb3 100%);
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg-gradient);
    margin: 0;
    padding: 20px;
    color: var(--text-main);
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

/* --- BOTÓN CERRAR SESIÓN --- */
.btn-logout {
    position: fixed;
    top: 14px; right: 14px;
    z-index: 9997;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 1000;
    font-size: 13px;
    color: #fff;
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    box-shadow: 0 12px 26px rgba(185, 28, 28, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition: all 0.22s ease;
}

.btn-logout .dot {
    width: 10px; height: 10px;
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.18);
}

.btn-logout:hover {
    transform: translateY(-2px);
}

/* --- CONTENEDOR PRINCIPAL --- */
.contenedor {
    max-width: 1000px;
    width: 100%;
    background: #ffffff;
    border-radius: 28px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    animation: slideUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
    margin-top: 10px;
}

/* --- CABECERA --- */
.cabecera {
    padding: 14px 20px;
    text-align: center;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}

.cabecera img {
    max-width: 280px;
    width: 100%;
    height: auto;
}

/* --- CUERPO --- */
.contenido {
    padding: clamp(15px, 3vw, 30px) clamp(25px, 5vw, 55px) clamp(25px, 5vw, 55px);
}

.hero {
    text-align: center;
    margin-bottom: 16px;
}

.hero h2 {
    margin: 0 0 12px;
    font-size: clamp(28px, 4vw, 36px);
    font-weight: 800;
    color: #0f172a;
}

/* --- AVISO DE CAMBIOS --- */
.aviso-cambios {
    background: #fefce8;
    border: 1px solid #fef08a;
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 35px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: fadeIn 1s ease-out 0.3s backwards;
}

.aviso-cambios i {
    font-size: 24px;
}

.aviso-cambios p {
    margin: 0;
    color: #854d0e;
    font-size: 14.5px;
    font-weight: 600;
    line-height: 1.4;
}

/* --- TARJETAS --- */
.accesos-principales {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.acceso-card {
    position: relative;
    display: flex;
    flex-direction: column;
    text-decoration: none;
    color: inherit;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 22px;
    padding: 32px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    animation: fadeIn 0.8s ease-out backwards;
}

.acceso-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; width: 100%; height: 6px;
    background: var(--accent-blue);
}

.acceso-card.lista::before {
    background: var(--accent-gold);
}

.acceso-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
}

.acceso-card h3 {
    margin: 0 0 12px;
    font-size: 24px;
    font-weight: 700;
}

.acceso-card p {
    margin: 0;
    color: var(--text-muted);
    font-size: 15px;
    line-height: 1.6;
    flex-grow: 1;
}

.flecha {
    margin-top: 20px;
    font-size: 14px;
    font-weight: 800;
    color: var(--accent-blue);
    display: flex;
    align-items: center;
    gap: 5px;
    opacity: 0.6;
    transition: 0.3s;
}

.acceso-card:hover .flecha {
    opacity: 1;
    transform: translateX(6px);
}

/* --- BOTÓN VOLVER --- */
.volver-link {
    display: flex;
    justify-content: center;
}

.volver {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    background: linear-gradient(135deg, #000099, #2563eb);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
    border-radius: 99px;
    box-shadow: 0 8px 20px rgba(0, 0, 153, 0.35);
    transition: all 0.3s ease;
}

.volver:hover {
    background: linear-gradient(135deg, #0000cc, #3b82f6);
    box-shadow: 0 12px 28px rgba(0, 0, 153, 0.45);
    transform: translateX(-5px);
}

/* --- ANIMACIONES --- */
@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.acceso-card:nth-child(2) { animation-delay: 0.15s; }

.acceso-card::after {
    content: "";
    position: absolute;
    top: 0; left: -100%;
    width: 50%; height: 100%;
    background: linear-gradient(to right, transparent, rgba(255,255,255,0.4), transparent);
    transform: skewX(-25deg);
}

.acceso-card:hover::after {
    left: 150%;
    transition: 0.7s;
}
</style>
</head>
<body>

<!-- Botón de logout -->
<a class="btn-logout" href="?logout=1" title="Cerrar sesión">
    <span class="dot"></span>
    Cerrar sesión
</a>

<div class="contenedor">
 <div class="cabecera">
   <a href="Gestor.php">
     <img src="custom/img/logo-atu-gestor.png" alt="Logo">
   </a>
 </div>

 <div class="contenido">
   <div class="hero">
     <h2>Cuestionario Valoraciones</h2>
   </div>

   <!-- AVISO DE CAMBIOS -->
   <div class="aviso-cambios">
     <i>⚠️</i>
     <p>Atención: Todos los cambios realizados en esta sección se reflejarán automáticamente en el cuestionario de valoraciones del portal.</p>
   </div>

   <div class="accesos-principales">
     <a href="fields.php?tipo=valoraciones" class="acceso-card">
       <h3>⭐ Campos base</h3>
       <p>Gestiona nombres, textos de ayuda, visibilidad y orden de los campos del formulario.</p>
       <div class="flecha">Configurar campos ➔</div>
     </a>

     <a href="options.php?tipo=valoraciones" class="acceso-card lista">
       <h3>▾ Listas desplegables</h3>
       <p>Administra las opciones internas de cada selector y menús de selección múltiple.</p>
       <div class="flecha">Editar listas ➔</div>
     </a>
   </div>

   <div class="volver-link">
     <a href="Gestor.php" class="volver">← Volver al panel principal</a>
   </div>
 </div>
</div>

</body>
</html>