<?php

require_once "../config/app.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["usuario"])) {

    header("Location: login.php");
    exit;
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>public/css/header.css?v=9999">

<header class="header">

    <div class="top-bar">

        <div class="logo-container">

<img src="/osticket-formulario/public/img/logo.jpg?v=9999" class="logo">

        </div>

        <div class="usuario-container">

            <span class="usuario">
                <?= $_SESSION["usuario"] ?>
            </span>

            <a href="https://incidencias.grupoatu.com/osticket-formulario/logout.php">
                Cerrar sesión
            </a>

        </div>

    </div>

  <h2 class="header-titulo">
        Datos de ayuda 
    </h2>

 <div class="header-linea"></div>

</header>