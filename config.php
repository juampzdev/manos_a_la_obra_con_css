<?php
// Conexión a la base de datos. Todas las páginas que usan la base
// empiezan con: require "config.php";  y después usan $conexion.

// Datos de conexión: tienen que ser los mismos que usás en SQLyog
$servidor = "localhost";
$usuario  = "root";
$clave    = "";
$base     = "manos_a_la_obra";

// Desde PHP 8.1, si mysqli no puede conectarse (MySQL apagado, base inexistente,
// contraseña mal) "lanza una excepción": corta todo y muestra un error técnico largo.
// Con try / catch la atajamos y mostramos un mensaje claro de qué revisar.
try {
    $conexion = new mysqli($servidor, $usuario, $clave, $base);
} catch (mysqli_sql_exception $e) {
    die("No se pudo conectar a la base de datos. Revisá que MySQL esté prendido en XAMPP "
        . "y que hayas ejecutado database.sql. Detalle: " . $e->getMessage());
}

$conexion->set_charset("utf8mb4"); // para que se vean bien las tildes y la ñ
