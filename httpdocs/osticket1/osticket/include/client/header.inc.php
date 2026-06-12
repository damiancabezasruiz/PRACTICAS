<?php
if (!defined('OSTCLIENTINC')) die('Access Denied!');

// Título
$title = ($cfg && is_object($cfg) && $cfg->getTitle())
 ? $cfg->getTitle()
 : ('osTicket :: ' . __('Sistema de Tickets'));

$signin_url = ROOT_PATH . 'login.php'
 . ((isset($thisclient) && $thisclient) ? ('?e=' . urlencode($thisclient->getEmail())) : '');

// (Opcional) Mantengo tu cabecera tal como la tenías
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html<?php if (isset($lang) && $lang) echo ' lang="' . $lang . '"'; ?>>
<head>
 <!-- =========================
 META
 ========================== -->
 <meta charset="utf-8">
 <title><?php echo Format::htmlchars($title); ?></title>

 <!-- =========================
 FAVICON
 ========================== -->
 <link rel="icon" type="image/x-icon" href="/favicon.ico?v=1">
 <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=1">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">

 <!-- =========================
 CSS EXTERNO
 ========================== -->
 <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/osticket.css" media="screen">
 <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/custom.css">
 <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/theme.css" media="screen">

 <!-- =========================
 JS EXTERNO
 ========================== -->
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery.pjax.js"></script>
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/osticket.js"></script>

 <!-- IMPORTANTE: estos 3 son necesarios para que "Elegir" funcione en open.php -->
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/filedrop.field.js"></script>
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery.fileupload.js"></script>
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery.iframe-transport.js"></script>

 <!-- =========================
 CSS INTERNO
 ========================== -->
 <style>
 /* REORDENAR BOTONES */
 form { display: flex !important; flex-direction: column !important; }

 /* CANCELAR - IZQUIERDA */
 button[type="button"] { order: 1 !important; }

 /* CREAR/SUBMIT - MEDIO */
 button[type="submit"],
 input[type="submit"] { order: 2 !important; }

 /* RESTABLECER - DERECHA */
 button[type="reset"],
 input[type="reset"] { order: 3 !important; }

 /* MOSTRAR SOLO EL NOMBRE (si existe ese contenedor) */
 #file-name, .file-name { display: block !important; }

 /* MOSTRAR EN FILA */
 .button-group, .form-group {
   display: flex !important;
   gap: 12px !important;
   justify-content: center !important;
   flex-direction: row !important;
 }

 /* ESTRUCTURA GENERAL */
 html, body {
   width: 100% !important;
   height: 100vh !important;
   margin: 0 !important;
   padding: 5px !important;
   overflow: auto !important;
 }

 body {
   background: #000099;
   font-family: 'Segoe UI', sans-serif;
   padding: 5px;
 }

#container {
    background: #ffffff;
    border-radius: 15px;
    margin: 20px auto;
    max-width: 1300px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    width: 95%;
    padding: 0 5% 20px 5%;
    box-sizing: border-box !important;
    overflow-x: hidden;
}

/* AJUSTES PARA MÓVILES Y TABLETS */
@media (max-width: 900px) {
    #container {
        width: 96%;
        padding: 15px 4%;
        margin: 10px auto;
        border-radius: 10px;     /* Menos redondeo en pantallas pequeñas */
    }
}

@media (max-width: 600px) {
    #container {
        width: 100%;
        padding: 10px 8px;
        margin: 5px auto;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2); /* Sombra más ligera en móvil */
    }
}

 #header {
   text-align: center;
   margin-bottom: 0;
   padding-top: 20px;
   padding-bottom: 0;
   line-height: 0;
   display: flex;
   flex-direction: column;
   align-items: center;
   gap: 2px;
 }

 #logo {
   display: block;
   line-height: 0;
   margin: 0;
   padding: 0;
   height: 140px;
   display: flex;
   align-items: center;
   justify-content: center;
 }

 #logo img {
   max-height: 140px !important;
   max-width: 300px !important;
   width: auto !important;
   height: 140px !important;
   object-fit: contain;
   display: block;
   transition: opacity 0.3s ease;
 }

 #nav {
   display: flex;
   justify-content: center;
   gap: 8px;
   padding: 0;
   background: transparent;
   border-radius: 50px;
   list-style: none;
   margin: 0;
   width: fit-content;
 }

 #nav li a {
   background: #000099 !important;
   color: white !important;
   margin-top: 0;
   padding: 11px 38px !important;
   border-radius: 50px !important;
   border: none !important;
   outline: none !important;
   font-weight: 700;
   text-decoration: none;
   font-size: 14px;
   letter-spacing: 0.3px;
   display: inline-block !important;
   box-shadow: 0 4px 14px rgba(0,0,153,0.4) !important;
   transition: all 0.2s ease !important;
 }
 #nav li a:hover {
   background: #0000cc !important;
   box-shadow: 0 6px 20px rgba(0,0,153,0.55) !important;
   transform: translateY(-2px);
 }

 /* GRID DE 3 COLUMNAS */
 .modo-triple form {
   display: grid !important;
   grid-template-columns: repeat(3, 1fr) !important;
   gap: 8px 25px !important;
 }
 .modo-triple table,
 .modo-triple tbody { display: contents !important; }

 .modo-triple .form-header,
 .modo-triple h1,
 .modo-triple h2,
 .modo-triple hr,
 .modo-triple .thread-entry,
 .modo-triple tr:has(h2),
 .modo-triple tr:has(.form-header) {
   grid-column: 1 / -1 !important;
   width: 100% !important;
   margin: 8px 0 2px 0 !important;
 }

 .modo-triple tr {
   display: flex;
   flex-direction: column !important;
   grid-column: span 1;
   justify-content: flex-end;
 }

 .modo-triple tr.mantener-hueco {
   visibility: hidden !important;
   pointer-events: none !important;
 }

 .modo-triple tr.ocultar-total { display: none !important; }

 .modo-triple td {
   display: block !important;
   width: 100% !important;
   padding: 0 !important;
   border: none !important;
 }

 .modo-triple label {
   font-weight: 700 !important;
   font-size: 12px !important;
   color: black;
   margin-bottom: 2px !important;
   text-transform: uppercase;
   display: block !important;
   min-height: 28px;
 }

 .modo-triple input,
 .modo-triple select,
 .modo-triple textarea {
   border: 1px solid #aaa !important;
   border-radius: 6px !important;
   padding: 6px !important;
   font-size: 13px !important;
   background: #fff !important;
   width: 100% !important;
   box-sizing: border-box !important;
 }

 .modo-triple tr:has(textarea),
 .modo-triple tr:has(.filedrop) {
   grid-column: 1 / -1 !important;
   display: flex !important;
 }

 .modo-triple textarea { height: 60px !important; }

 .modo-triple .buttons,
 .modo-triple .form-actions {
   grid-column: 1 / -1 !important;
   display: flex !important;
   justify-content: center !important;
   gap: 15px;
   padding-top: 15px;
 }

 input[type="submit"] {
   background: #28a745 !important;
   color: white !important;
   border: none;
   padding: 10px 30px;
   border-radius: 6px;
   font-weight: bold;
   cursor: pointer;
 }

 input[type="reset"] {
   background: #6c757d !important;
   color: white !important;
   border: none;
   padding: 10px 30px;
   border-radius: 6px;
   font-weight: bold;
   cursor: pointer;
 }

 input[type="button"] {
   background-color: #ff0000 !important;
   color: white !important;
   border: none;
   padding: 10px 30px;
   border-radius: 6px;
   font-weight: bold;
   cursor: pointer;
 }

 footer, #footer, .powered-by { display: none !important; }


 /* =========================================================
   ADJUNTOS (open.php): quitar vista previa y dejar SOLO nombre
   - NO ocultamos el contenedor entero (para no romper la UI)
   - ocultamos SOLO la parte "preview" típica de jQuery File Upload / Dropzone
 ========================================================= */

 /* jQuery File Upload (muy típico en osTicket): preview va en .preview y suele ser canvas/img */
 .filedrop .preview,
 .filedrop td.preview,
 .filedrop span.preview {
   display: none !important;
 }

 /* Dropzone (si existiera): ocultar SOLO la imagen, no el nombre */
 .filedrop .dz-image,
 .filedrop .dz-thumbnail,
 .filedrop .dz-preview .dz-image {
   display: none !important;
 }

 /* Cualquier miniatura/imagen/canvas que se cuele */
 .filedrop img,
 .filedrop canvas {
   display: none !important;
 }

 /* Asegurar que el nombre se vea */
 .filedrop .name,
 .filedrop .filename,
 .filedrop .file-name,
 .filedrop .dz-filename,
 .filedrop a.name {
   display: inline !important;
   font-size: 13px !important;
   font-weight: 600 !important;
   color: #111 !important;
 }

 /* Opcional: ocultar extras (tamaño/barra progreso) si aparecen */
 .filedrop .filesize,
 .filedrop .size,
 .filedrop .progress {
   display: none !important;
 }

 /* =========================================================
   ANIMACIONES BOTONES (tu bloque)
 ========================================================= */
 input[type="button"],
 input[name="cancel"] {
   background: red !important;
   color: white !important;
   padding: 10px 24px !important;
   border-radius: 8px !important;
   font-weight: 700 !important;
   font-size: 13px !important;
   border: none !important;
   cursor: pointer !important;
   transition: all 0.3s ease !important;
   position: relative !important;
   overflow: hidden !important;
 }
 input[type="button"]::before,
 input[name="cancel"]::before,
 button[type="submit"]::before,
 input[type="submit"]::before,
 button[type="reset"]::before,
 input[type="reset"]::before {
   content: '';
   position: absolute;
   top: 0;
   left: -100%;
   width: 100%;
   height: 100%;
   background: rgba(255, 255, 255, 0.2);
   transition: left 0.4s ease;
 }
 input[type="button"]:hover,
 input[name="cancel"]:hover {
   transform: translateY(-3px) !important;
   box-shadow: 0 8px 20px rgba(255, 0, 0, 0.4) !important;
 }
 input[type="button"]:hover::before,
 input[name="cancel"]:hover::before,
 button[type="submit"]:hover::before,
 input[type="submit"]:hover::before,
 button[type="reset"]:hover::before,
 input[type="reset"]:hover::before { left: 100%; }

 button[type="submit"],
 input[type="submit"] {
   background: green !important;
   color: white !important;
   padding: 10px 24px !important;
   border-radius: 8px !important;
   font-weight: 700 !important;
   font-size: 13px !important;
   border: none !important;
   cursor: pointer !important;
   transition: all 0.3s ease !important;
   position: relative !important;
   overflow: hidden !important;
 }
 button[type="submit"]:hover,
 input[type="submit"]:hover {
   transform: translateY(-3px) !important;
   box-shadow: 0 8px 20px green !important;
 }
 button[type="reset"],
 input[type="reset"] {
   background: grey !important;
   color: white !important;
   padding: 10px 24px !important;
   border-radius: 8px !important;
   font-weight: 700 !important;
   font-size: 13px !important;
   border: none !important;
   cursor: pointer !important;
   transition: all 0.3s ease !important;
   position: relative !important;
   overflow: hidden !important;
 }
 button[type="reset"]:hover,
 input[type="reset"]:hover {
   transform: translateY(-3px) !important;
   box-shadow: 0 8px 20px rgba(128, 128, 128, 0.4) !important;
 }

 a.new { font-size: 0 !important; pointer-events: none !important; }
 a.new::after { content: "Gestor de Campos" !important; font-size: 13px !important; font-weight: 700 !important; }
 a.home { font-size: 0 !important; }
 a.home::after { content: "Inicio centro de tickets" !important; font-size: 13px !important; font-weight: 700 !important; }
 a.txt-btn { font-size: 0 !important; }
 a.txt-btn::after { content: "Inicio centro de tickets" !important; font-size: 13px !important; font-weight: 700 !important; }
 a.status { display: none !important; }
 #new, a.new { display: none !important; }
 li:has(a#new), li:has(a.new) { display: none !important; }

 /* Ocultar todos los botones del nav excepto "Inicio centro de tickets" (a.home) */
 #nav li { display: none !important; }
 #nav li:has(a.home) { display: block !important; }

 /* Ajuste fino: sube el inicio del grid */
 .modo-triple .form-header,
 .modo-triple h1,
 .modo-triple h2,
 .modo-triple hr,
 .modo-triple .thread-entry,
 .modo-triple tr:has(h2),
 .modo-triple tr:has(.form-header){
   margin: 2px 0 0 0 !important;
   padding: 0 !important;
 }
 .modo-triple hr{ margin: 0 !important; }
 .modo-triple form{
   padding-top: 0 !important;
   margin-top: -6px !important;
 }
	 
	 #tickets {
    display: none !important;
}


/* Animación suave para que destaque sin molestar */
@keyframes pulseWarning {
    0%   { box-shadow: 0 0 0 rgba(241,196,15,0.4); }
    50%  { box-shadow: 0 0 10px rgba(255, 0, 0, 0.7); }
    100% { box-shadow: 0 0 0 rgba(241,196,15,0.4); }
}
 </style>

 <!-- =========================
 JS INTERNO
 ========================== -->
 <script>
 function toggleTriple() {
   document.body.classList.toggle('modo-triple');
   var txt = document.getElementById('txt-btn');
   if (txt) {
     txt.innerHTML = document.body.classList.contains('modo-triple')
       ? "VISTA NORMAL"
       : "3 COLUMNAS";
   }
 }
 </script>

 <script>
 document.addEventListener('DOMContentLoaded', function() {
   let logo = document.getElementById('logo');
   if (logo) {
     logo.href = 'javascript:void(0);';
     logo.onclick = function(e) {
       e.preventDefault();
       return false;
     };
   }
 });
 </script>

 <script>
 document.addEventListener('DOMContentLoaded', function() {
   let btnGestor = document.getElementById('gestor-campos');
   let btnVista = document.getElementById('txt-btn');
   if (btnGestor && btnVista) {
     let liGestor = btnGestor.parentElement;
     let liVista = btnVista.parentElement;
     let nav = liVista.parentElement;
     nav.insertBefore(liGestor, liVista);
   }
 });
 </script>

 <!-- FIX: labels con for inválido -->
 <script>
 (function () {
   function fixLabels(root) {
     root = root || document;
     root.querySelectorAll('label[for]').forEach(function(label) {
       var f = label.getAttribute('for');
       if (!f) return;
       if (document.getElementById(f)) return;
       if (document.getElementById('_' + f)) {
         label.setAttribute('for', '_' + f);
         return;
       }
       label.removeAttribute('for');
     });
   }
   document.addEventListener('DOMContentLoaded', function() {
     fixLabels(document);
     var dyn = document.getElementById('dynamic-form');
     if (dyn && window.MutationObserver) {
       new MutationObserver(function() { fixLabels(dyn); })
         .observe(dyn, { childList: true, subtree: true });
     }
   });
 })();
 </script>

 <!-- DEDUPE VISUAL: si el mismo nombre aparece 2 veces en la lista de adjuntos, borra el duplicado -->
 <script>
 document.addEventListener('DOMContentLoaded', function () {
   if (window.__OST_ATTACH_DEDUPE__) return;
   window.__OST_ATTACH_DEDUPE__ = true;

   function dedupeUploads(root) {
     const boxes = root.querySelectorAll('.filedrop .uploads, .filedrop .files, .uploads');
     boxes.forEach(function (box) {
       const seen = new Set();
       const rows = box.querySelectorAll('.upload, .file, li, tr');
       rows.forEach(function (row) {
         const nameEl = row.querySelector('.name, .filename, .file-name, a.name, .dz-filename, a');
         const name = (nameEl ? nameEl.textContent : '').trim();
         if (!name) return;
         if (seen.has(name)) row.remove();
         else seen.add(name);
       });
     });
   }

   dedupeUploads(document);

   if (window.MutationObserver) {
     new MutationObserver(function () { dedupeUploads(document); })
       .observe(document.body, { childList: true, subtree: true });
   }
 });
 </script>

 <!-- =========================
 JS EXTERNO FINAL
 ========================== -->
 <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/condicionales.js"></script>

 <!-- CAMBIO DE LOGO SEGÚN TEMA DE AYUDA -->
 <script>
 document.addEventListener('DOMContentLoaded', function () {

   var LOGOS = {
     'default':    '/osticket/images/Formulario-default.png',
     'Incidencia': '/osticket/images/Formulario-incidencias.png',
     'Valoración': '/osticket/images/Formulario-valoraciones.png'
   };

   function setLogo(key) {
     var img = document.getElementById('logo-img');
     if (!img) return;
     var src = LOGOS[key] || LOGOS['default'];
     if (img.getAttribute('src') === src) return;
     img.style.opacity = '0';
     setTimeout(function () {
       img.src = src;
       img.style.opacity = '1';
     }, 280);
   }

   function bindSelect() {
     var sel = document.querySelector('select[name="topicId"]');
     if (!sel || sel._logoBound) return;
     sel._logoBound = true;
     sel.addEventListener('change', function () {
       var text = this.options[this.selectedIndex].text.trim();
       setLogo(LOGOS[text] ? text : 'default');
     });
   }

   bindSelect();
   if (window.MutationObserver) {
     new MutationObserver(function () { bindSelect(); })
       .observe(document.body, { childList: true, subtree: true });
   }
 });
 </script>

</head>

<body class="<?php echo (basename($_SERVER['PHP_SELF'])=='open.php') ? 'modo-triple' : ''; ?>">
 <div id="container">
   <div id="header">
     <a id="logo" href="index.php" style="display:block;line-height:0;padding:0;margin:0;">
       <img id="logo-img" src="/osticket/images/Formulario-default.png" alt="Logo">
     </a>

     <ul id="nav">
     <?php
     if (isset($nav) && $nav && ($navs = $nav->getNavLinks())) {
         foreach ($navs as $name => $nav_l) {
             if ($name === 'tickets') { continue; }
             echo sprintf(
                 '<li><a class="%s %s" href="%s">%s</a></li>',
                 !empty($nav_l['active']) ? 'active' : '',
                 $name,
                 (ROOT_PATH . $nav_l['href']),
                 $nav_l['desc']
             );
         }
     }
     ?>
     </ul>
   </div>

   <div id="content">