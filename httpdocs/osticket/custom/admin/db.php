<?php
// Datos extraídos de tu ost-config.php
$db_host = 'localhost';
$db_name = 'bd_ostickets_atu';
$db_user = 'user_bd_atu';
$db_pass = 'q5DzXzsN!wf0*qj1';

// Conexión principal
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die("Fallo de conexión: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8");
$prefix = 'ost_'; // Prefijo de tus tablas
?>
