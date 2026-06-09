<?php
session_start();

require_once("../config/Conexion.php");

$error = "";

if (isset($_POST["login"])) {

    $usuario = trim($_POST["usuario"]);
    $password = trim($_POST["password"]);

    $conexion = Conexion::getInstancia()->getConexion();

    $sql = "SELECT * FROM usuarios
            WHERE TRIM(usuario) = ?
            AND TRIM(password) = ?";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([$usuario, $password]);

    $usuarioBD = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuarioBD) {

        $_SESSION["usuario"] = $usuarioBD["usuario"];
        $_SESSION["rol"] = $usuarioBD["rol"];

        header("Location: lista.php");
        exit;

    } else {

        $error = "Usuario o contraseña incorrectos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login</title>

    <!-- ?v=2 fuerza a recargar el CSS actualizado -->
    <link rel="stylesheet" href="../public/css/login.css?v=2">

</head>

<body>

    <div class="login-container">

        <img src="../public/img/logo.jpg" class="logo">

        <h2>Iniciar Sesión</h2>

        <form action="login.php" method="post">

            <input type="text"
                   name="usuario"
                   placeholder="👤 Usuario"
                   required>

            <input type="password"
                   name="password"
                   placeholder="🔒 Contraseña"
                   required>

            <button type="submit" name="login">

                ENTRAR

            </button>

        </form>

        <?php if ($error != "") : ?>

            <p class="error">

                <?= $error ?>

            </p>

        <?php endif; ?>

    </div>

</body>

</html>