<?php

require_once "../config/Conexion.php";

$conexion = Conexion::getInstancia()->getConexion();

$id = $_GET["id"];

/* GUARDAR */

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $usuario = $_POST["usuario"];

    $nombre = $_POST["nombre"];

    $departamento = $_POST["departamento"];

    $email = $_POST["email"];

    $estado = $_POST["estado"];

    $sql = "UPDATE empleados
            SET usuario = ?,
                nombre = ?,
                departamento = ?,
                email = ?,
                estado = ?
            WHERE id = ?";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $usuario,
        $nombre,
        $departamento,
        $email,
        $estado,
        $id
    ]);

    header("Location: empleado.php");

    exit;
}

/* OBTENER */

$sql = "SELECT *
        FROM empleados
        WHERE id = ?";

$stmt = $conexion->prepare($sql);

$stmt->execute([$id]);

$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Editar Empleado</title>

<link rel="stylesheet" href="../public/css/header.css?v=10">
<link rel="stylesheet" href="../public/css/crear_usuario.css?v=10">

</head>

<body>

<?php require_once("header.php"); ?>

<div class="contenedor">

<div class="panel-formulario">

<h2>Editar empleado</h2>

<form method="POST" class="formulario">

<input type="text"
       name="usuario"
       value="<?= $empleado["usuario"] ?>"
       required>

<input type="text"
       name="nombre"
       value="<?= $empleado["nombre"] ?>"
       required>

<input type="text"
       name="departamento"
       value="<?= $empleado["departamento"] ?>"
       required>

<input type="email"
       name="email"
       value="<?= $empleado["email"] ?>"
       required>

<select name="estado">

<option value="ACTIVO"
<?= $empleado["estado"] == "ACTIVO" ? "selected" : "" ?>>

ACTIVO

</option>

<option value="PENDIENTE"
<?= $empleado["estado"] == "PENDIENTE" ? "selected" : "" ?>>

PENDIENTE

</option>

<option value="BLOQUEADO"
<?= $empleado["estado"] == "BLOQUEADO" ? "selected" : "" ?>>

BLOQUEADO

</option>

</select>

<button type="submit" class="btn-guardar">

Guardar Cambios

</button>

</form>

</div>

</div>

</body>
</html>
