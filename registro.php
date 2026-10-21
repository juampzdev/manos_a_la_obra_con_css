<?php
// Pantalla para crear una cuenta nueva (antes era login.html)
session_start();

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

// Si ya está logueado no tiene sentido registrarse de nuevo
if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

// guardar.php nos devuelve acá con ?error=... si algo salió mal
$errores = [
    "campos" => "Completá todos los campos.",
    "email"  => "Ese email no es válido.",
    "existe" => "Ya hay una cuenta con ese email.",
    "corta"  => "La contraseña tiene que tener al menos 6 caracteres.",
];
$error = $errores[$_GET["error"] ?? ""] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-form <?php echo $modo_oscuro ? "oscuro" : ""; ?>">

    <form action="guardar.php" method="post" class="formulario">
        <a href="index.php" class="volver">← Volver al inicio</a>
        <h1>Crear cuenta</h1>

        <?php if ($error): ?>
            <p class="mensaje-error"><?php echo $error; ?></p>
        <?php endif; ?>

        <div class="fila">
            <label>Nombre
                <input type="text" name="nombre" required>
            </label>
            <label>Apellido
                <input type="text" name="apellido" required>
            </label>
        </div>

        <label>Email
            <input type="email" name="email" required>
        </label>

        <label>Contraseña
            <input type="password" name="password" minlength="6" required>
        </label>

        <label>Tipo de usuario
            <select name="tipo_usuario" required>
                <option value="" disabled selected>Elegí una opción</option>
                <option value="profesional">Profesional</option>
                <option value="cliente">Cliente</option>
            </select>
        </label>

        <button type="submit">Crear cuenta</button>
        <p class="pie-form">¿Ya tenés cuenta? <a href="login.php">Ingresá</a></p>
    </form>

</body>
</html>
