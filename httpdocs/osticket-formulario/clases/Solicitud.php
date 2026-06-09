<?php

class Solicitud
{

    private $nombre;
    private $apellidos;
    private $dni;
    private $curso;
    private $telefono;
    private $email;


    public function __construct(string $nombre, string $apellidos, string $dni, string $curso, string $telefono, string $email)

    {

        $this->nombre = $nombre;

        $this->apellidos = $apellidos;

        $this->dni = $dni;

        $this->curso = $curso;

        $this->telefono = $telefono;

        $this->email = $email;
    }


    public function getNombre()
    {
        return $this->nombre;
    }

    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }


    public function getApellidos()
    {
        return $this->apellidos;
    }

    public function setApellidos($apellidos)
    {
        $this->apellidos = $apellidos;

        return $this;
    }

    public function getDni()
    {
        return $this->dni;
    }

    public function setDni($dni)
    {
        $this->dni = $dni;

        return $this;
    }

    public function getCurso()
    {
        return $this->curso;
    }


    public function getTelefono()
    {
        return $this->telefono;
    }


    public function setTelefono($telefono)
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }


    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }
}
