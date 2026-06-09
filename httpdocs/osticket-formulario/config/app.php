<?php

if ($_SERVER['HTTP_HOST'] == 'localhost') {

    define("BASE_URL", "http://localhost/PHP/osticket-formulario/");

} else {

    define("BASE_URL", "https://tudominio.com/");
}