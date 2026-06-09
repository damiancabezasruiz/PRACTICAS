<?php

session_start();

session_destroy();

header("Location: vistas/login.php");
exit;