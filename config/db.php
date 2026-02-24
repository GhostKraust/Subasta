<?php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "subasta";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    exit("Error de conexion a la base de datos.");
}

if (!$mysqli->set_charset("utf8mb4")) {
    http_response_code(500);
    exit("Error al configurar charset.");
}
