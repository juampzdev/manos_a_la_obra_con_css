<?php
// Muestra una imagen adjunta a un mensaje de Contacto: imagen_mensaje.php?id=4
// La usa bandeja.php en sus <img>. Solo para administradores: la carpeta adjuntos/
// no se puede abrir directo desde el navegador (por su .htaccess), así que esta
// página es la única forma de ver las imágenes, y antes revisa la sesión.
session_start();
require "config.php";

if (($_SESSION["usuario_tipo"] ?? "") !== "admin") {
    http_response_code(403);   // 403 = "prohibido"
    exit;
}

$id = (int) ($_GET["id"] ?? 0);
$consulta = $conexion->prepare("SELECT archivo, tipo FROM mensaje_imagenes WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$imagen = $consulta->get_result()->fetch_assoc();

// basename deja solo el nombre del archivo: aunque en la base hubiera algo como
// "../config.php", nunca se lee un archivo fuera de la carpeta adjuntos/
$ruta = $imagen ? __DIR__ . "/adjuntos/" . basename($imagen["archivo"]) : "";

if (!$imagen || !is_file($ruta)) {
    http_response_code(404);   // 404 = "no encontrado"
    exit;
}

// Le decimos al navegador que lo que sigue es una imagen (image/png, image/jpeg...)
// y le mandamos el archivo tal cual con readfile
header("Content-Type: " . $imagen["tipo"]);
readfile($ruta);
