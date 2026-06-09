<?php

class Conexion
{
    private static $instancia = null;

    private $host;
    private $bd;
    private $usuario;
    private $password;

    private $conexion;

    private function __construct()
    {

        if ($_SERVER['HTTP_HOST'] == 'localhost') {

            // LOCAL XAMPP

            $this->host = "localhost";
            $this->bd = "puntaca1_solicitudes_cursos";
            $this->usuario = "root";
            $this->password = "";

        } else {

            // HOSTING
$this->host = "localhost";
$this->bd = "solicitudes_cursos";
$this->usuario = "solicitudes_user";
$this->password = "Solicitudes@2026";
        }

        try {

            $this->conexion = new PDO(
                "mysql:host=" . $this->host .
                ";dbname=" . $this->bd .
                ";charset=utf8",
                $this->usuario,
                $this->password
            );

            $this->conexion->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

        } catch (PDOException $e) {

            die("Error conexión: " . $e->getMessage());
        }
    }

    public static function getInstancia()
    {
        if (self::$instancia == null) {
            self::$instancia = new Conexion();
        }

        return self::$instancia;
    }

    public function getConexion()
    {
        return $this->conexion;
    }
}