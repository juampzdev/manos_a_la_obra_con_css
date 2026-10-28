<?php
// Perfil del profesional: la primera vez lo completa ("Postular servicio")
// y después lo puede editar. Guarda los datos en SU fila de la tabla profesionales.
session_start();
require "config.php";

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

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

// Los barrios para la lista desplegable salen de la tabla barrios.
// Armamos un array id => fila, así después es fácil revisar si el barrio elegido existe.
$barrios = [];
$resultado_barrios = $conexion->query("SELECT id, barrio, zona FROM barrios ORDER BY zona, barrio");
while ($fila = $resultado_barrios->fetch_assoc()) {
    $barrios[$fila["id"]] = $fila;
}

// Revisa que una fecha tenga el formato AAAA-MM-DD y que exista de verdad
// (por ejemplo, rechaza "2024-02-31" o un texto cualquiera).
// createFromFormat arma la fecha a partir del texto; si al volver a escribirla
// no da exactamente lo mismo, es que el texto no era una fecha válida.
function fecha_valida($fecha) {
    $fecha_armada = DateTime::createFromFormat("Y-m-d", $fecha);
    return $fecha_armada && $fecha_armada->format("Y-m-d") === $fecha;
}

// ---------- Guardar el formulario ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $oficio      = $_POST["profesion"] ?? "";
    $fecha_alta  = $_POST["fecha_alta"] ?? "";
    $fecha_baja  = $_POST["fecha_baja"] ?? "";
    $descripcion = trim($_POST["descripcion"] ?? "");
    $ciudad      = trim($_POST["ciudad"] ?? "");
    $domicilio   = trim($_POST["domicilio"] ?? "");
    $barrio_id   = (int) ($_POST["barrio"] ?? 0);
    $experiencia = (int) ($_POST["experiencia"] ?? 0);
    $telefono    = trim($_POST["telefono"] ?? "");

    // Fecha de baja vacía = sigue en actividad (NULL en la base)
    $fecha_baja = $fecha_baja === "" ? null : $fecha_baja;

    if (!in_array($oficio, $oficios) || $fecha_alta === "" || $descripcion === ""
        || $ciudad === "" || $domicilio === "" || $telefono === "") {
        $error = "Completá todos los campos obligatorios.";
    } elseif (!isset($barrios[$barrio_id])) {
        $error = "Elegí tu barrio de la lista.";
    } elseif (mb_strlen($descripcion) > 300) {
        $error = "La descripción no puede pasar los 300 caracteres.";
    } elseif (mb_strlen($ciudad) > 100 || mb_strlen($domicilio) > 120 || mb_strlen($telefono) > 30) {
        // Los mismos largos que las columnas de la base: si no, MySQL daría error
        $error = "La ciudad, el domicilio o el teléfono son demasiado largos.";
    } elseif (!fecha_valida($fecha_alta) || ($fecha_baja !== null && !fecha_valida($fecha_baja))) {
        $error = "Revisá las fechas: alguna no es válida.";
    } elseif ($fecha_alta > date("Y-m-d")) {
        // Las fechas AAAA-MM-DD se pueden comparar como texto: "2030-01-01" > "2026-10-28"
        $error = "La fecha de alta no puede ser futura.";
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
             SET oficio = ?, descripcion = ?, ubicacion = ?, domicilio = ?, barrio_id = ?,
                 telefono = ?, experiencia = ?, fecha_alta = ?, fecha_baja = ?
             WHERE id = ?"
        );
        $sql->bind_param("ssssisissi", $oficio, $descripcion, $ciudad, $domicilio, $barrio_id,
                         $telefono, $experiencia, $fecha_alta, $fecha_baja, $usuario_id);
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
$barrio_actual = $error !== null ? (int) ($_POST["barrio"] ?? 0) : (int) ($perfil["barrio_id"] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil profesional | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-form <?php echo $modo_oscuro ? "oscuro" : ""; ?>">

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
                <!-- max = hoy: el calendario no deja elegir una fecha futura -->
                <input type="date" id="fecha_alta" name="fecha_alta" max="<?php echo date("Y-m-d"); ?>" value="<?php echo valor("fecha_alta", "fecha_alta"); ?>" required>
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
                <input type="text" id="ciudad" name="ciudad" maxlength="100" autocomplete="address-level2" value="<?php echo valor("ciudad", "ubicacion"); ?>" required>
            </label>
            <label for="experiencia">Años de experiencia
                <input type="number" id="experiencia" name="experiencia" min="0" max="60" inputmode="numeric" value="<?php echo valor("experiencia", "experiencia"); ?>" required>
            </label>
        </div>

        <div class="fila">
            <label for="domicilio">Domicilio
                <span class="ayuda">Calle y número. No se muestra en tu tarjeta.</span>
                <input type="text" id="domicilio" name="domicilio" maxlength="120" autocomplete="street-address" value="<?php echo valor("domicilio", "domicilio"); ?>" required>
            </label>
            <label for="barrio">Barrio
                <span class="ayuda">Con esto te mostramos a los clientes cercanos.</span>
                <select id="barrio" name="barrio" required>
                    <option value="" disabled <?php echo $barrio_actual === 0 ? "selected" : ""; ?>>Elegí tu barrio</option>
                    <?php foreach ($barrios as $id_barrio => $b): ?>
                        <option value="<?php echo $id_barrio; ?>" <?php echo $barrio_actual === $id_barrio ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($b["barrio"] . " (zona " . $b["zona"] . ")"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label for="telefono">Teléfono de contacto
            <input type="tel" id="telefono" name="telefono" maxlength="30" autocomplete="tel" value="<?php echo valor("telefono", "telefono"); ?>" required>
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
