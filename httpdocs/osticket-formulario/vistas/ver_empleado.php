<?php

require_once "../config/Conexion.php";

$conexion = Conexion::getInstancia()->getConexion();

$id = $_GET["id"] ?? 0;

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

<title>Detalle Empleado</title>

<link rel="stylesheet" href="../public/css/header.css?v=10">
<link rel="stylesheet" href="../public/css/ver_empleado.css?v=10">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<?php require_once("header.php"); ?>

<div class="contenedor">

    <div class="detalle-box">

        <h2>Detalle del empleado</h2>

        <div class="linea"></div>

        <h3>Empleado #<?= $empleado["id"] ?></h3>

        <div class="datos">

            <p>

                <strong>Usuario:</strong>

                <?= htmlspecialchars($empleado["usuario"]) ?>

            </p>

            <p>

                <strong>Nombre:</strong>

                <?= htmlspecialchars($empleado["nombre"]) ?>

            </p>

            <p>

                <strong>Departamento:</strong>

                <?= htmlspecialchars($empleado["departamento"]) ?>

            </p>

            <p>

                <strong>Email:</strong>

                <?= htmlspecialchars($empleado["email"]) ?>

            </p>

            <p>

                <strong>Estado:</strong>

                <?= htmlspecialchars($empleado["estado"]) ?>

            </p>

            <p>

                <strong>Último acceso:</strong>

                <?php

                if($empleado["ultimo_acceso"]){

                    echo date(
                        "d/m/Y H:i",
                        strtotime($empleado["ultimo_acceso"])
                    );

                }else{

                    echo "-";
                }

                ?>

            </p>

        </div>

        <div class="linea"></div>

        <div class="acciones">

            <a href="editar_empleado.php?id=<?= $empleado["id"] ?>"
               class="btn-editar">

               Editar empleado

            </a>

            <a href="empleado.php"
               class="btn-volver">

               ← Volver a la lista

            </a>

        </div>

    </div>

</div>

</body>
</html>