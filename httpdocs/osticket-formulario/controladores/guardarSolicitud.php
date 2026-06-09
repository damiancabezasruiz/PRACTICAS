<?php

require_once "../config/Conexion.php";
require_once "../clases/Solicitud.php";

// Crear objeto
$solicitud = new Solicitud( $_POST["nombre"], $_POST["apellidos"], $_POST["dni"], $_POST["curso"], $_POST["telefono"],
$_POST["email"]);

// Conexión
$conexion = Conexion::getInstancia() ->getConexion();

// SQL
$sql = "INSERT INTO solicitud_acceso( nombre, apellido,dni, curso, telefono, email)
VALUES(?,?,?,?,?,?)";

// Preparar
$stmt =$conexion->prepare($sql);

// Ejecutar
$stmt->execute([$solicitud->getNombre(),$solicitud->getApellidos(), $solicitud->getDni(), $solicitud->getCurso(),
$solicitud->getTelefono(),$solicitud->getEmail()]);

// ID generado
$id = $conexion->lastInsertId();

// Redirección

header( "Location: ../vistas/confirmacion.php?id=$id");

exit;