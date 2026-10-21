<?php
// Perfil del profesional (antes era registro.html).
// Completa (o edita) los datos del perfil en la fila del profesional.
session_start();
require "config.php";

// Solo pueden entrar profesionales logueados
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION["usuario_tipo"] !== "profesional") {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION["usuario_id"];
$oficios = ["Electricista", "Plomero", "Gasista", "Carpintero", "Pintor", "Albañil",
            "Jardinero", "Técnico de aires acondicionados", "Cerrajero", "Otra"];
$mensaje = null;
$error = null;

// ---------- Guardar el formulario ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $oficio      = $_POST["profesion"] ?? "";
    $fecha_alta  = $_POST["fecha_alta"] ?? "";
    $fecha_baja  = $_POST["fecha_baja"] ?? "";
    $descripcion = trim($_POST["descripcion"] ?? "");
    $ciudad      = trim($_POST["ciudad"] ?? "");
    $experiencia = (int) ($_POST["experiencia"] ?? 0);
    $telefono    = trim($_POST["telefono"] ?? "");

    // Fecha de baja vacía = sigue en actividad (NULL en la base)
    $fecha_baja = $fecha_baja === "" ? null : $fecha_baja;

    if (!in_array($oficio, $oficios) || $fecha_alta === "" || $descripcion === ""
        || $ciudad === "" || $telefono === "") {
        $error = "Completá todos los campos obligatorios.";
    } elseif (mb_strlen($descripcion) > 300) {
        $error = "La descripción no puede pasar los 300 caracteres.";
    } elseif ($experiencia < 0 || $experiencia > 60) {
        $error = "Los años de experiencia tienen que estar entre 0 y 60.";
    } elseif ($fecha_baja !== null && $fecha_baja < $fecha_alta) {
        $error = "La fecha de baja no puede ser anterior a la de alta.";
    } else {
        // La cuenta del profesional ya existe en la tabla profesionales
        // (se creó al registrarse), así que solo actualizamos su fila.
        // Antes de guardar vemos si ya tenía el perfil completo, para el mensaje.
        $buscar = $conexion->prepare("SELECT oficio FROM profesionales WHERE id = ?");
        $buscar->bind_param("i", $usuario_id);
        $buscar->execute();
        $antes = $buscar->get_result()->fetch_assoc();
        $ya_tenia_perfil = $antes && $antes["oficio"] !== null;

        $sql = $conexion->prepare(
            "UPDATE profesionales
             SET oficio = ?, descripcion = ?, ubicacion = ?, telefono = ?,
                 experiencia = ?, fecha_alta = ?, fecha_baja = ?
             WHERE id = ?"
        );
        $sql->bind_param("ssssissi", $oficio, $descripcion, $ciudad, $telefono,
                         $experiencia, $fecha_alta, $fecha_baja, $usuario_id);
        $sql->execute();
        $mensaje = $ya_tenia_perfil ? "Perfil actualizado." : "¡Perfil publicado! Ya aparecés en el inicio.";
    }
}

// ---------- Cargar los datos actuales para rellenar el formulario ----------
$consulta = $conexion->prepare("SELECT * FROM profesionales WHERE id = ?");
$consulta->bind_param("i", $usuario_id);
$consulta->execute();
$perfil = $consulta->get_result()->fetch_assoc();

// Si hubo error, mostramos lo que escribió; si no, lo que hay en la base
function valor($campo_form, $campo_db) {
    global $perfil, $error;
    if ($error !== null) return htmlspecialchars($_POST[$campo_form] ?? "");
    return htmlspecialchars($perfil[$campo_db] ?? "");
}
$oficio_actual = $error !== null ? ($_POST["profesion"] ?? "") : ($perfil["oficio"] ?? "");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil profesional | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-form">

    <form action="postular.php" method="post" class="formulario formulario-ancho">
        <a href="index.php" class="volver">← Volver al inicio</a>
        <h1><?php echo !empty($perfil["oficio"]) ? "Editar mi perfil" : "Postular servicio"; ?></h1>
        <p class="subtitulo">Hola, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?>. Estos datos se muestran en tu tarjeta.</p>

        <?php if ($error): ?>
            <p class="mensaje-error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if ($mensaje): ?>
            <p class="mensaje-ok"><?php echo $mensaje; ?></p>
        <?php endif; ?>

        <label for="profesion">Profesión
            <select id="profesion" name="profesion" required>
                <option value="" disabled <?php echo $oficio_actual === "" ? "selected" : ""; ?>>Elegí una opción</option>
                <?php foreach ($oficios as $o): ?>
                    <option value="<?php echo $o; ?>" <?php echo $oficio_actual === $o ? "selected" : ""; ?>><?php echo $o; ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="fila">
            <label for="fecha_alta">Fecha de alta
                <span class="ayuda">Cuándo empezaste a ejercer.</span>
                <input type="date" id="fecha_alta" name="fecha_alta" value="<?php echo valor("fecha_alta", "fecha_alta"); ?>" required>
            </label>
            <label for="fecha_baja">Fecha de baja (opcional)
                <span class="ayuda">Dejala vacía si seguís en actividad.</span>
                <input type="date" id="fecha_baja" name="fecha_baja" value="<?php echo valor("fecha_baja", "fecha_baja"); ?>">
            </label>
        </div>

        <label for="descripcion">Descripción de tus servicios
            <span class="ayuda">Qué trabajos hacés y qué te diferencia.</span>
            <textarea id="descripcion" name="descripcion" maxlength="300" required><?php echo valor("descripcion", "descripcion"); ?></textarea>
            <span class="contador" id="contador" aria-live="off">0 / 300</span>
        </label>

        <div class="fila">
            <label for="ciudad">Ciudad
                <input type="text" id="ciudad" name="ciudad" autocomplete="address-level2" value="<?php echo valor("ciudad", "ubicacion"); ?>" required>
            </label>
            <label for="experiencia">Años de experiencia
                <input type="number" id="experiencia" name="experiencia" min="0" max="60" inputmode="numeric" value="<?php echo valor("experiencia", "experiencia"); ?>" required>
            </label>
        </div>

        <label for="telefono">Teléfono de contacto
            <input type="tel" id="telefono" name="telefono" autocomplete="tel" value="<?php echo valor("telefono", "telefono"); ?>" required>
        </label>

        <button type="submit">Guardar perfil</button>
    </form>

    <script>
        // Contador de caracteres de la descripción
        const texto = document.getElementById("descripcion");
        const contador = document.getElementById("contador");
        function actualizar() { contador.textContent = texto.value.length + " / 300"; }
        texto.addEventListener("input", actualizar);
        actualizar();
    </script>

</body>
</html>
