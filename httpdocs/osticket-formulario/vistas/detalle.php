<?php

require_once "../config/Conexion.php";


$conexion = Conexion::getInstancia()  ->getConexion();
$id = $_GET["id"] ?? 0;

// CAMBIAR ESTADO

if(isset($_GET["estado"])){
 $estado = $_GET["estado"];
 $sql = " UPDATE solicitud_acceso
          SET estado = ?
          WHERE id = ? ";

$stmt = $conexion->prepare($sql);
$stmt->execute([$estado,$id ]);
}

// GUARDAR NOTAS

if(isset($_POST["notas"])){

    $sql = " UPDATE solicitud_acceso
             SET notas = ?
              WHERE id = ?  ";

 $stmt = $conexion->prepare($sql);
 $stmt->execute([ $_POST["notas"], $id  ]);
}

// OBTENER SOLICITUD

$sql = "SELECT *
FROM solicitud_acceso
WHERE id = ?";

$stmt = $conexion->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">


<head>

    <meta charset="UTF-8">
<link rel="stylesheet" href="../public/css/header.css?v=9999">
   <link rel="stylesheet" href="../public/css/detalle.css?v=9999">

</head>

<body>
<?php require_once("header.php"); ?>
<div class="contenedor">
<div class="contenido-detalle">
    
  <h1 class="titulo-detalle"> Detalle de la Solicitud </h1>
 <div class="linea-detalle"></div>
 <div class="detalle">
<h2> Solicitud #<?= $solicitud["id"] ?> </h2>
 <p><b>Nombre:</b><?= $solicitud["nombre"] ?></p>
 <p><b>Apellidos: <?= $solicitud["apellido"] ?> </p>
<p> <b>DNI:</b> <?= $solicitud["dni"] ?> </p>
<p> <b>Curso:</b><?= $solicitud["curso"] ?></p>
<p> <b>Email:</b>  <?= $solicitud["email"] ?> </p>
 <p> <b>Teléfono:</b> <?= $solicitud["telefono"] ?> </p>
 <p> <b>Estado:</b><?= $solicitud["estado"] ?>

</p>

 </div>


<div class="linea-detalle"></div>
<h2 class="subtitulo">Cambiar estado  </h2>
<a href="?id=<?= $id ?>&estado=PENDIENTE" class="btn
<?= $solicitud["estado"] == "PENDIENTE" ? "pendiente-btn" : "azul" ?>"> Pendiente </a>

<a href="?id=<?= $id ?>&estado=EN_GESTION" class="btn 
<?= $solicitud["estado"] == "EN_GESTION" ? "gestion-btn" : "azul" ?>"> En gestión </a>

<a href="?id=<?= $id ?>&estado=RESUELTO" class="btn
<?= $solicitud["estado"] == "RESUELTO" ? "resuelto-btn" : "azul" ?>"> Resuelto </a>


 <a href="lista.php" class="volver"> ← Volver a la lista </a>


<div class="linea-detalle"></div>
<h2 class="subtitulo">  Contacto </h2>
 <div class="contacto">
<a href="tel:<?= $solicitud["telefono"] ?>"> 📞 Llamar </a>
<a  href="mailto:<?= $solicitud["email"] ?>"> ✉️ Email</a>
</div>

    <div class="linea-detalle"></div>

    <h2 class="subtitulo">Notas internas </h2>
<form method="POST">
 <textarea name="notas">
<?= $solicitud["notas"] ?>
</textarea>
<button>
 Guardar notas
  </button>

    </form>
</div>
</div>

</body>
</html>