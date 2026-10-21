<?php
// Recibe el formulario de registro.php y crea la cuenta.
// Según el tipo elegido, la guarda en la tabla "clientes" o en "profesionales".
session_start();
require "config.php";

// Si alguien entra directo a guardar.php sin mandar el formulario, lo sacamos
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: registro.php");
    exit;
}

$nombre   = trim($_POST["nombre"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$email    = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$tipo     = $_POST["tipo_usuario"] ?? "";

// Validaciones básicas
if ($nombre === "" || $apellido === "" || $email === "" || $password === ""
    || !in_array($tipo, ["profesional", "cliente"])) {
    header("Location: registro.php?error=campos");
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: registro.php?error=email");
    exit;
}
if (strlen($password) < 6) {
    header("Location: registro.php?error=corta");
    exit;
}

// ¿Ese email ya está usado? Hay que revisar en LAS DOS tablas,
// para que una misma persona no tenga una cuenta de cliente y otra de profesional
$consulta = $conexion->prepare(
    "SELECT id FROM clientes WHERE email = ?
     UNION
     SELECT id FROM profesionales WHERE email = ?"
);
$consulta->bind_param("ss", $email, $email);
$consulta->execute();
if ($consulta->get_result()->num_rows > 0) {
    header("Location: registro.php?error=existe");
    exit;
}

// Nunca se guarda la contraseña tal cual: se encripta con password_hash()
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($tipo === "profesional") {
    // ----- Va a la tabla profesionales -----
    // Las iniciales (ej: "AL") se usan en el círculo de su tarjeta
    $iniciales = mb_strtoupper(mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1));

    $insertar = $conexion->prepare(
        "INSERT INTO profesionales (nombre, apellido, email, password, iniciales) VALUES (?, ?, ?, ?, ?)"
    );
    $insertar->bind_param("sssss", $nombre, $apellido, $email, $hash, $iniciales);
} else {
    // ----- Va a la tabla clientes -----
    $insertar = $conexion->prepare(
        "INSERT INTO clientes (nombre, apellido, email, password) VALUES (?, ?, ?, ?)"
    );
    $insertar->bind_param("ssss", $nombre, $apellido, $email, $hash);
}
$insertar->execute();

// Lo dejamos logueado directamente.
// usuario_tipo nos dice en qué tabla está su usuario_id
$_SESSION["usuario_id"]       = $conexion->insert_id;
$_SESSION["usuario_nombre"]   = $nombre;
$_SESSION["usuario_apellido"] = $apellido;
$_SESSION["usuario_tipo"]     = $tipo;

// El profesional va a completar su perfil; el cliente vuelve al inicio
if ($tipo === "profesional") {
    header("Location: postular.php");
} else {
    header("Location: index.php");
}
exit;
