<?php
// Datos de conexión: tienen que ser los mismos que usás en SQLyog
$servidor = "localhost";
$usuario  = "root";
$clave    = "";
$base     = "manos_a_la_obra";

$conexion = new mysqli($servidor, $usuario, $clave, $base);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4"); // para que se vean bien las tildes y la ñ
