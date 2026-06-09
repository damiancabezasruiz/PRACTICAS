<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/Conexion.php";

$conexion = Conexion::getInstancia()->getConexion();

// FILTROS

$estado = $_GET["estado"] ?? "";

$curso = $_GET["curso"] ?? "";

// SQL

$sql = "SELECT *
        FROM solicitud_acceso
        WHERE 1=1";

$parametros = [];

// FILTRO ESTADO

if ($estado != "") {
    $sql .= " AND estado = ? ";
    $parametros[] = $estado;
}

// FILTRO CURSO

if ($curso != "") {
    $sql .= " AND curso LIKE ? ";
    $parametros[] = "%$curso%";
}

// ORDEN

$sql .= " ORDER BY fecha_solicitud DESC ";

// PREPARE

$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Panel Solicitudes</title>

<link rel="stylesheet" href="../public/css/header.css?v=10">
<link rel="stylesheet" href="../public/css/lista.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <?php require_once("header.php"); ?>

    <div class="contenedor">
        <div class="panel">
			
			
            <div class="acciones-superior"
     style="
     width:100%;
     display:flex;
     justify-content:space-between;
     align-items:center;
     ">

    <a href="crear_usuario.php"
       class="btn-crear">

        <i class="fa-solid fa-user-plus"></i>

        Añadir usuarios

    </a>

    <a href="empleado.php"
       class="btn-crear">

        <i class="fa-solid fa-users"></i>

        Ver usuarios

    </a>

</div>
            <h2>Panel de Solicitudes</h2>

            <form method="GET" class="form-filtros">

                <div class="filtros">

                    <select name="estado">

                        <option value="">Todos los estados</option>
                        <option value="PENDIENTE" <?= $estado == "PENDIENTE" ? "selected" : "" ?>>Pendiente</option>
                        <option value="EN_GESTION" <?= $estado == "EN_GESTION" ? "selected" : "" ?>>En gestión</option>
                        <option value="RESUELTO" <?= $estado == "RESUELTO" ? "selected" : "" ?>>Resuelto</option>

                    </select>

                    <input type="text" name="curso" placeholder="Curso" value="<?= htmlspecialchars($curso) ?>">

                </div>

                <button type="submit" class="btn-filtrar">Filtrar</button>

            </form>

            <table class="tabla">
                <thead>
                    <tr>
                        <th>Nº Solicitud</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Curso</th>
                        <th>Estado</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Fecha</th>
                        <th>Detalle</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($solicitudes as $s): ?>

                        <tr>
                            <td><?= $s["id"] ?></td>
                            <td><?= htmlspecialchars($s["nombre"]) ?></td>
                            <td><?= htmlspecialchars($s["apellido"]) ?></td>
                            <td><?= htmlspecialchars($s["curso"]) ?></td>

                            <td>
                                <?php

                                $clase = "";

                                if ($s["estado"] == "PENDIENTE") {
                                    $clase = "pendiente";
                                } elseif ($s["estado"] == "EN_GESTION") {
                                    $clase = "gestion";
                                } else {
                                    $clase = "resuelto";
                                }

                                ?>

                                <span class="estado <?= $clase ?>">
                                    <?= $s["estado"] ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars($s["telefono"]) ?></td>
                            <td><?= htmlspecialchars($s["email"]) ?></td>

                            <td>
                                <?= date("d/m/Y H:i:s", strtotime($s["fecha_solicitud"])) ?>
                            </td>

                            <td>
                                <a href="detalle.php?id=<?= $s["id"] ?>" class="ver">Ver</a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>
    </div>

</body>

</html>