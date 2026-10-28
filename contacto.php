<?php
// Formulario de contacto: el mensaje (con imágenes opcionales) llega por mail
// a la casilla del equipo. Para que el mail salga de verdad, XAMPP tiene que
// tener configurado el envío con Gmail (ver README.md, "Envío de mails").
session_start();

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

// A dónde llegan los mensajes
$correo_destino = "iseiclaude@gmail.com";

$asuntos = ["Consulta general", "Problema con mi cuenta", "Reportar un profesional", "Plan Destacado", "Sugerencia"];

// Límites de las imágenes adjuntas
$max_imagenes = 3;
$max_peso     = 2 * 1024 * 1024;   // 2 MB por imagen, en bytes
$tipos_permitidos = ["image/jpeg", "image/png", "image/gif", "image/webp"];

$error = null;

// Si está logueado, ya le completamos el nombre
$nombre  = trim(($_SESSION["usuario_nombre"] ?? "") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$email   = "";
// El asunto puede venir elegido desde otra página: contacto.php?asunto=Plan+Destacado
// Solo lo usamos si es uno de la lista
$asunto  = in_array($_GET["asunto"] ?? "", $asuntos) ? $_GET["asunto"] : "";
$mensaje = "";

// ---------- Enviar el mensaje ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Si lo que se manda pesa más de lo que PHP acepta (post_max_size en php.ini),
    // PHP descarta todo y $_POST llega vacío
    if (empty($_POST) && ($_SERVER["CONTENT_LENGTH"] ?? 0) > 0) {
        $error = "Las imágenes pesan demasiado. Probá con menos o más livianas.";
    } else {
        $nombre  = trim($_POST["nombre"] ?? "");
        $email   = trim($_POST["email"] ?? "");
        $asunto  = $_POST["asunto"] ?? "";
        $mensaje = trim($_POST["mensaje"] ?? "");

        if ($nombre === "" || $email === "" || $mensaje === "" || !in_array($asunto, $asuntos)) {
            $error = "Completá todos los campos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // filter_var también rechaza saltos de línea: así nadie puede
            // meter cabeceras de mail falsas en el campo email
            $error = "Ese email no es válido.";
        } elseif (mb_strlen($mensaje) > 2000) {
            $error = "El mensaje no puede pasar los 2000 caracteres.";
        }
    }

    // ---------- Revisar las imágenes ----------
    // Con <input type="file" name="imagenes[]" multiple>, $_FILES["imagenes"] trae
    // una lista por cada dato: ["name"][0], ["name"][1]... ["tmp_name"][0], etc.
    // La posición (0, 1, 2) es la misma imagen en todas las listas.
    $adjuntos = [];
    // is_array: nos aseguramos de que llegó una lista (si alguien manda el campo sin los []
    // llega un solo archivo suelto y el for de abajo fallaría)
    if ($error === null && isset($_FILES["imagenes"]) && is_array($_FILES["imagenes"]["name"])) {
        $archivos = $_FILES["imagenes"];

        for ($i = 0; $i < count($archivos["name"]); $i++) {
            // Si no eligió ninguna imagen, igual llega una posición con este error: la salteamos
            if ($archivos["error"][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($archivos["error"][$i] !== UPLOAD_ERR_OK || $archivos["size"][$i] > $max_peso) {
                $error = "Cada imagen puede pesar hasta 2 MB.";
                break;
            }

            // getimagesize lee el archivo de verdad: si no es una imagen devuelve false.
            // No alcanza con mirar la extensión, porque cualquiera puede renombrar un archivo.
            $info = getimagesize($archivos["tmp_name"][$i]);
            if ($info === false || !in_array($info["mime"], $tipos_permitidos)) {
                $error = "Solo se pueden adjuntar imágenes JPG, PNG, GIF o WEBP.";
                break;
            }

            $adjuntos[] = [
                // Dejamos solo letras, números, puntos y guiones en el nombre del archivo
                "nombre" => preg_replace("/[^A-Za-z0-9._-]/", "_", basename($archivos["name"][$i])),
                "tipo"   => $info["mime"],
                "ruta"   => $archivos["tmp_name"][$i],
            ];
        }

        if ($error === null && count($adjuntos) > $max_imagenes) {
            $error = "Podés adjuntar hasta " . $max_imagenes . " imágenes.";
        }
    }

    // ---------- Armar y mandar el mail ----------
    if ($error === null) {
        // Un mail con adjuntos se arma en partes ("multipart"), separadas por un
        // texto que no aparece en ningún otro lado: el límite
        $limite = "limite_" . md5(uniqid());

        $cabeceras  = "From: Manos a la Obra <" . $correo_destino . ">\r\n";
        $cabeceras .= "Reply-To: " . $email . "\r\n";   // al tocar "Responder" le contestás a quien escribió
        $cabeceras .= "MIME-Version: 1.0\r\n";
        $cabeceras .= "Content-Type: multipart/mixed; boundary=\"" . $limite . "\"";

        // Primera parte: el texto del mensaje
        $cuerpo  = "--" . $limite . "\r\n";
        $cuerpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $cuerpo .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $cuerpo .= "Nombre: " . $nombre . "\r\n";
        $cuerpo .= "Email: " . $email . "\r\n";
        $cuerpo .= "Asunto: " . $asunto . "\r\n";
        $cuerpo .= "Imágenes adjuntas: " . count($adjuntos) . "\r\n\r\n";
        $cuerpo .= $mensaje . "\r\n";

        // Una parte más por cada imagen. Los archivos se mandan "traducidos" a texto
        // con base64, porque un mail solo puede llevar texto
        foreach ($adjuntos as $adjunto) {
            $cuerpo .= "--" . $limite . "\r\n";
            $cuerpo .= "Content-Type: " . $adjunto["tipo"] . "; name=\"" . $adjunto["nombre"] . "\"\r\n";
            $cuerpo .= "Content-Transfer-Encoding: base64\r\n";
            $cuerpo .= "Content-Disposition: attachment; filename=\"" . $adjunto["nombre"] . "\"\r\n\r\n";
            $cuerpo .= chunk_split(base64_encode(file_get_contents($adjunto["ruta"]))) . "\r\n";
        }
        $cuerpo .= "--" . $limite . "--";   // con dos guiones al final se cierra el mail

        // El asunto también se codifica en base64 para que lleguen bien las tildes
        $titulo = "=?UTF-8?B?" . base64_encode("[Manos a la Obra] " . $asunto . " - " . $nombre) . "?=";

        // mail() devuelve true si el mail salió. El @ evita que PHP muestre su
        // aviso técnico en la página: en su lugar mostramos un mensaje claro
        if (@mail($correo_destino, $titulo, $cuerpo, $cabeceras)) {
            // Volvemos a cargar con GET, así si toca F5 no se manda dos veces
            header("Location: contacto.php?enviado=1");
            exit;
        }
        $error = "No pudimos enviar el mensaje. Probá de nuevo en unos minutos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-form <?php echo $modo_oscuro ? "oscuro" : ""; ?>">

    <!-- enctype="multipart/form-data" es obligatorio para poder mandar archivos.
         Sin eso, el formulario manda solo los nombres de las imágenes y no las imágenes. -->
    <form action="contacto.php" method="post" enctype="multipart/form-data" class="formulario formulario-ancho">
        <a href="index.php" class="volver">← Volver al inicio</a>
        <h1>Contacto</h1>
        <p class="subtitulo">¿Tenés una duda, un problema o una sugerencia? Escribinos y te respondemos por mail.</p>

        <?php if (isset($_GET["enviado"])): ?>
            <p class="mensaje-ok">¡Gracias! Recibimos tu mensaje y te vamos a responder a la brevedad.</p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="mensaje-error"><?php echo $error; ?> Si habías elegido imágenes, volvé a elegirlas.</p>
        <?php endif; ?>

        <div class="fila">
            <label for="nombre">Nombre
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
            </label>
            <label for="email">Tu email
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </label>
        </div>

        <label for="asunto">Asunto
            <select id="asunto" name="asunto" required>
                <option value="" disabled <?php echo $asunto === "" ? "selected" : ""; ?>>Elegí una opción</option>
                <?php foreach ($asuntos as $a): ?>
                    <option value="<?php echo $a; ?>" <?php echo $asunto === $a ? "selected" : ""; ?>><?php echo $a; ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label for="mensaje">Mensaje
            <textarea id="mensaje" name="mensaje" maxlength="2000" class="mensaje-largo" required><?php echo htmlspecialchars($mensaje); ?></textarea>
        </label>

        <label for="imagenes">Imágenes (opcional)
            <span class="ayuda">Hasta 3 imágenes JPG, PNG, GIF o WEBP de 2 MB cada una. Para elegir varias, mantené apretado Ctrl.</span>
            <!-- name con [] y multiple: deja elegir varios archivos y PHP los recibe como una lista.
                 accept hace que la ventana para elegir muestre solo imágenes. -->
            <input type="file" id="imagenes" name="imagenes[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
        </label>

        <button type="submit">Enviar mensaje</button>
    </form>

</body>
</html>
