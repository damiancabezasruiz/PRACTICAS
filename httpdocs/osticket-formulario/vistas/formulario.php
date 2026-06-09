<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Formulario de ayuda para alumnado</title>
   <link rel="stylesheet" href="../public/css/header.css?v=9999">
   <link rel="stylesheet" href="../public/css/formulario.css?v=9999">
</head>

<body>

    <div class="card">

     <img src="/osticket-formulario/public/img/logo.jpg?v=9999" class="logo">
        <h3 class="titulo-superior">Ayuda para alumnado</h3>
        <div class="linea"></div>
        <h1>Formulario de ayuda para alumnado</h1>
        <p class="descripcion">

            Si tienes problemas para acceder a tu curso,
            rellena este formulario y te ayudaremos.

        </p>

        <form action="../vistas/confirmacion.php" method="POST">
            <input type="text" name="nombre" placeholder="👤 Nombre" required>
            <input type="text" name="apellidos" placeholder="👤 Apellidos" required>
            <input type="text" name="dni" placeholder="🪪 DNI" required>
            <input type="text" name="curso" placeholder="🎓 CURSO" >
            <input type="text" name="telefono" placeholder="📞 Teléfono">
            <input type="email" name="email" placeholder="✉️ Correo electrónico">
            <button type="submit"> ENVIAR SOLICITUD </button>
            <p class="descripcion">

                Gracias por confiar en Grupo atu

            </p>

        </form>

    </div>

</body>

</html>