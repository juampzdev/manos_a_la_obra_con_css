<?php
// Inicio de sesión con email y contraseña
session_start();
require "config.php";

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

$error = null;
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Buscamos el email en las dos tablas. La columna "tipo" la armamos
    // en la consulta para saber de cuál de las dos tablas vino
    $consulta = $conexion->prepare(
        "SELECT id, nombre, apellido, password, 'cliente' AS tipo FROM clientes WHERE email = ?
         UNION
         SELECT id, nombre, apellido, password, 'profesional' AS tipo FROM profesionales WHERE email = ?"
    );
    $consulta->bind_param("ss", $email, $email);
    $consulta->execute();
    $usuario = $consulta->get_result()->fetch_assoc();

    // password_verify compara lo que escribió con la contraseña encriptada
    if ($usuario && password_verify($password, $usuario["password"])) {
        $_SESSION["usuario_id"]       = $usuario["id"];
        $_SESSION["usuario_nombre"]   = $usuario["nombre"];
        $_SESSION["usuario_apellido"] = $usuario["apellido"];
        $_SESSION["usuario_tipo"]     = $usuario["tipo"];   // "cliente" o "profesional"
        header("Location: index.php");
        exit;
    }

    // No decimos cuál de los dos está mal, a propósito
    $error = "Email o contraseña incorrectos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-form <?php echo $modo_oscuro ? "oscuro" : ""; ?>">

    <form action="login.php" method="post" class="formulario">
        <a href="index.php" class="volver">← Volver al inicio</a>
        <h1>Ingresar</h1>

        <?php if ($error): ?>
            <p class="mensaje-error"><?php echo $error; ?></p>
        <?php endif; ?>

        <label>Email
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </label>

        <label>Contraseña
            <input type="password" name="password" required>
        </label>

        <button type="submit">Ingresar</button>
        <p class="pie-form">¿No tenés cuenta? <a href="registro.php">Creá una</a></p>
    </form>

</body>
</html>
