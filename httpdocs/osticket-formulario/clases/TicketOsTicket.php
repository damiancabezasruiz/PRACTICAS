<?php

require_once "Solicitud.php";

class TicketOsTicket
{
    private static string $url =
        "http://localhost/osticket/api/tickets.json";

    private static string $apiKey =
        "3FBC8D0EEF540525030D06311062AFC2";

    public static function crearTicket(
        Solicitud $solicitud
    )
    {

        $datos = [

            "name" =>

                $solicitud->getNombre()
                . " "
                . $solicitud->getApellidos(),

            "email" =>

                $solicitud->getEmail(),

            "phone" =>

                $solicitud->getTelefono(),

            "subject" =>

                "Solicitud Acceso Curso",

            "message" =>

                "Curso: "
                . $solicitud->getCurso()

                . "\nDNI: "
                . $solicitud->getDni()

                . "\nUsuario necesita ayuda.",

            "ip" =>

                $_SERVER["REMOTE_ADDR"],

            "topicId" => 1
        ];

        $json = json_encode($datos);

        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            self::$url
        );

        curl_setopt(
            $ch,
            CURLOPT_POST,
            true
        );

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $json
        );

        curl_setopt(
            $ch,
            CURLOPT_RETURNTRANSFER,
            true
        );

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [

                "X-API-Key: "
                . self::$apiKey,

                "Content-Type: application/json"
            ]
        );

        $respuesta = curl_exec($ch);

        $codigo = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        return [

            "codigo" => $codigo,

            "respuesta" => $respuesta
        ];
    }
}