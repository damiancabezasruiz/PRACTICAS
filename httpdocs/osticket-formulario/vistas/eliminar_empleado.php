<?php

require_once "../config/Conexion.php";

$conexion = Conexion::getInstancia()->getConexion();

$id = $_GET["id"] ?? 0;

/* ELIMINAR EMPLEADO */

$sql = "DELETE FROM empleados
        WHERE id = ?";

$stmt = $conexion->prepare($sql);

$stmt->execute([$id]);

/* VOLVER */

header("Location: empleado.php");

exit;