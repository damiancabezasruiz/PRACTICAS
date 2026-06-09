<?php

require_once "../config/Conexion.php";

$conexion = Conexion::getInstancia()->getConexion();

/* FILTROS */

$email = $_GET["email"] ?? "";

$departamento = $_GET["departamento"] ?? "";

/* SQL */

$sql = "SELECT * FROM empleados
        WHERE 1=1";

$parametros = [];

/* FILTRO EMAIL */

if($email != ""){

    $sql .= " AND email LIKE ? ";

    $parametros[] = "%$email%";
}

/* FILTRO DEPARTAMENTO */

if($departamento != ""){

    $sql .= " AND departamento LIKE ? ";

    $parametros[] = "%$departamento%";
}

/* ORDEN */

$sql .= " ORDER BY fecha_creacion DESC";

/* QUERY */

$stmt = $conexion->prepare($sql);

$stmt->execute($parametros);

$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Gestión empleados</title>

<link rel="stylesheet" href="../public/css/header.css?v=10">
<link rel="stylesheet" href="../public/css/empleados.css?v=10">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<?php require_once("header.php"); ?>

<div class="contenedor">

    <div class="panel">

        <div class="acciones-superior">

            <a href="crear_usuario.php" class="btn-crear">

                <i class="fa-solid fa-user-plus"></i>

                Añadir usuario

            </a>

        </div>

        <h2>Gestión de usuarios</h2>

        <!-- FILTROS -->

        <form method="GET" class="form-filtros">

            <div class="filtros">

                <input type="text"
                       name="email"
                       placeholder="Buscar por email"
                       value="<?= htmlspecialchars($email) ?>">

                <input type="text"
                       name="departamento"
                       placeholder="Departamento"
                       value="<?= htmlspecialchars($departamento) ?>">

                <button type="submit" class="btn-filtrar">

                    Filtrar

                </button>

            </div>

        </form>

        <!-- TABLA -->

        <table class="tabla">

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Departamento</th>
                    <th>Email</th>
                    <th>Contraseña</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th>Acciones</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach($empleados as $e): ?>

                    <tr>

                        <td><?= $e["id"] ?></td>

                        <td><?= htmlspecialchars($e["usuario"]) ?></td>

                        <td><?= htmlspecialchars($e["nombre"]) ?></td>

                        <td><?= htmlspecialchars($e["departamento"]) ?></td>

                        <td><?= htmlspecialchars($e["email"]) ?></td>

                        <td class="password">

                            ••••••••

                        </td>

                        <td>

                            <span class="estado <?= strtolower($e["estado"]) ?>">

                                <?= $e["estado"] ?>

                            </span>

                        </td>

                        <td>

                            <?php

                            if($e["ultimo_acceso"]){

                                echo date(
                                    "d/m/Y H:i",
                                    strtotime($e["ultimo_acceso"])
                                );

                            }else{

                                echo "-";
                            }

                            ?>

                        </td>
<td class="acciones">

    <a href="ver_empleado.php?id=<?= $e["id"] ?>">

        Ver

    </a>

    <a href="editar_empleado.php?id=<?= $e["id"] ?>">

        Editar

    </a>
	
	    <a href="eliminar_empleado.php?id=<?= $e["id"] ?>"
       class="btn-eliminar"
       onclick="return confirm('¿Seguro que quieres eliminar este empleado?')">

        Eliminar

    </a>

   

</td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>