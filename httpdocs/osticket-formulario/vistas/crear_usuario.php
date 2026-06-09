<?php

require_once "../config/Conexion.php";

$conexion = Conexion::getInstancia()->getConexion();

$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $usuario = $_POST["usuario"];

    $nombre = $_POST["nombre"];

    $departamento = $_POST["departamento"];

    $email = $_POST["email"];

    $password = password_hash(
        $_POST["password"],
        PASSWORD_DEFAULT
    );

    /* VALIDAR USUARIO */

    $sql = "SELECT id
            FROM empleados
            WHERE usuario = ?";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([$usuario]);

    if($stmt->fetch()){

        $error = "El usuario ya existe";

    }else{

        /* INSERT */

     $sql = "INSERT INTO empleados
        (usuario,nombre,departamento,email,password,ultimo_acceso)
        VALUES(?,?,?,?,?,NOW())";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            $usuario,
            $nombre,
            $departamento,
            $email,
            $password
        ]);

        header("Location: empleado.php");

        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Crear Usuario</title>

<link rel="stylesheet" href="../public/css/header.css?v=10">
<link rel="stylesheet" href="../public/css/crear_usuario.css?v=10">

</head>

<body>

<?php require_once("header.php"); ?>

<div class="contenedor">

    <div class="panel-formulario">

        <h2>Crear Usuario</h2>

        <?php if($error != ""): ?>

            <div class="error">

                <?= $error ?>

            </div>

        <?php endif; ?>

        <form method="POST" class="formulario">

            <input type="text"
                   name="usuario"
                   placeholder="Usuario"
                   required>

            <input type="text"
                   name="nombre"
                   placeholder="Nombre completo"
                   required>

            <input type="text"
                   name="departamento"
                   placeholder="Departamento"
                   required>

            <input type="email"
                   name="email"
                   placeholder="Email"
                   required>

            <input type="password"
                   name="password"
                   placeholder="Contraseña"
                   required>

            <button type="submit" class="btn-guardar">

                Crear Usuario

            </button>

        </form>

    </div>

</div>

</body>
</html>