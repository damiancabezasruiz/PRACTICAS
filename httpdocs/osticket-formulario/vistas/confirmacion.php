<?php

require_once("../config/Conexion.php");

$conexion = Conexion::getInstancia()->getConexion();

$sql = "INSERT INTO solicitud_acceso
(nombre, apellido, dni, curso, telefono, email)
VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conexion->prepare($sql);

$stmt->execute([

    $_POST["nombre"],
    $_POST["apellidos"],
    $_POST["dni"],
    $_POST["curso"],
    $_POST["telefono"],
    $_POST["email"]

]);

$id = $conexion->lastInsertId();

?>
<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="UTF-8">
<title> Solicitud enviada </title>
<link rel="stylesheet" href="../public/css/confirmacion.css"></head>
<body>
<div class="card">

    <img src="../public/img/logo.jpg" class="logo">
<h1> ¡Solicitud enviada!</h1>
<div class="linea"></div>
<p class="texto">

        Gracias por contactar con ATU.

        Hemos recibido correctamente tu solicitud.

    </p>

    <div class="numero">   Nº Solicitud #<?= $id ?> </div>
 <p class="texto">

        Nuestro equipo revisará tu caso
        lo antes posible.

    </p>

    <a href="formulario.php" class="boton">  Nueva solicitud </a>

</div>
</body>
</html>