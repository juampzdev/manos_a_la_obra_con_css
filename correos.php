<?php
// Los mails que manda el sitio a los usuarios:
//   - enviar_bienvenida(): al crear una cuenta (lo usa guardar.php)
//   - enviar_respuesta():  cuando el equipo responde un mensaje de Contacto (lo usa bandeja.php)
// No se abre solo: esas páginas lo incluyen con require y llaman a la función que necesitan.
// Usa la función mail() de PHP: para que salga de verdad, XAMPP tiene que tener
// configurado el envío con Gmail (ver README.md, "Envío de mails").

// Desde qué casilla salen los mails
$correo_remitente = "iseiclaude@gmail.com";

// ---------------------------------------------------------
// Partes que comparten los dos mails
// ---------------------------------------------------------

// Arma la página HTML del mail: encabezado naranja con el logo, el contenido y un pie.
// Los programas de mail (Gmail, Outlook) no leen style.css: los estilos
// van adentro de cada etiqueta con style="...". Los colores son los del sitio.
function plantilla_correo($contenido, $pie) {
    return "
<!DOCTYPE html>
<html lang=\"es\">
<body style=\"margin: 0; padding: 24px; background: #f6f7f9; font-family: Arial, sans-serif; color: #1f2937;\">
  <div style=\"max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden;\">
    <div style=\"background: #e8590c; color: #ffffff; padding: 24px;\">
      <div style=\"font-size: 26px;\">🏠 <strong>Manos a la Obra</strong></div>
    </div>
    <div style=\"padding: 24px; font-size: 15px; line-height: 1.6;\">
      $contenido
    </div>
    <div style=\"padding: 16px 24px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;\">
      $pie
    </div>
  </div>
</body>
</html>";
}

// Manda un mail en HTML. Devuelve true si salió y false si no.
function enviar_correo_html($email, $asunto, $cuerpo) {
    global $correo_remitente;

    // Content-Type: text/html le dice al programa de mail que el cuerpo es HTML (no texto plano)
    $cabeceras  = "From: Manos a la Obra <" . $correo_remitente . ">\r\n";
    $cabeceras .= "Reply-To: " . $correo_remitente . "\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8";

    // El asunto se codifica en base64 para que lleguen bien las tildes y los signos ¡!
    $asunto = "=?UTF-8?B?" . base64_encode($asunto) . "?=";

    // El @ evita que PHP muestre su aviso técnico si el envío falla
    // (por ejemplo, si XAMPP no tiene configurado el mail)
    return @mail($email, $asunto, $cuerpo, $cabeceras);
}

// Dirección del sitio, para los botones de los mails. Se arma con la página actual:
// HTTP_HOST es "localhost" y dirname(SCRIPT_NAME) es "/manos_a_la_obra_con_css",
// así que queda "http://localhost/manos_a_la_obra_con_css".
// rtrim saca la barra del final si la hubiera, para que no quede "//postular.php"
function direccion_sitio() {
    return "http://" . $_SERVER["HTTP_HOST"] . rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
}

// ---------------------------------------------------------
// 1. Bienvenida al crear una cuenta
//   $email  -> a quién se lo mandamos (ya validado con filter_var en guardar.php)
//   $nombre -> para saludarlo
//   $tipo   -> "cliente" o "profesional": cada uno recibe un texto distinto
// ---------------------------------------------------------
function enviar_bienvenida($email, $nombre, $tipo) {
    $sitio = direccion_sitio();

    // El nombre lo escribió el usuario: htmlspecialchars para que no pueda meter HTML en el mail
    $nombre = htmlspecialchars($nombre);

    // El texto cambia según el tipo de cuenta
    if ($tipo === "profesional") {
        $intro  = "Ya tenés tu cuenta de <strong>profesional</strong>. Para que los clientes te encuentren, "
                . "solo falta un paso: <strong>completar tu perfil</strong>.";
        $pasos  = ["Cargá tu oficio, una descripción de tus servicios, tu barrio y tu teléfono.",
                   "Apenas lo guardes, aparecés en el inicio y en las búsquedas de tu zona.",
                   "Cada cliente que trabaje con vos puede dejarte una reseña y sumar a tu valoración."];
        $boton_texto = "Completar mi perfil";
        $boton_link  = $sitio . "/postular.php";
        $extra  = "¿Querés aparecer primero? Con el <strong>plan Destacado</strong> tu tarjeta se muestra "
                . "arriba de todo, con una insignia. Pedilo desde \"Cómo funciona\" en el sitio.";
    } else {
        $intro  = "Ya tenés tu cuenta de <strong>cliente</strong>. Desde ahora podés encontrar "
                . "profesionales de confianza cerca tuyo.";
        $pasos  = ["Buscá por oficio o por palabra, o elegí tu barrio en \"Cerca de vos\".",
                   "Entrá al perfil de cada profesional para leer las reseñas de otros clientes.",
                   "Llamalo directo al teléfono de su perfil y, cuando termine el trabajo, dejale tu reseña."];
        $boton_texto = "Buscar profesionales";
        $boton_link  = $sitio . "/index.php#profesionales";
        $extra  = "Podés dejar una reseña por cada profesional con el que trabajes: "
                . "así ayudás a otros vecinos a elegir.";
    }

    // Los pasos como lista numerada
    $lista = "";
    foreach ($pasos as $paso) {
        $lista .= "<li style=\"margin-bottom: 8px;\">" . $paso . "</li>";
    }

    $contenido = "
      <h1 style=\"font-size: 22px; margin: 0 0 12px;\">¡Hola, $nombre! Te damos la bienvenida 👋</h1>
      <p style=\"margin: 0 0 16px;\">$intro</p>
      <ol style=\"padding-left: 20px; margin: 0 0 20px;\">$lista</ol>
      <p style=\"margin: 0 0 24px;\">
        <a href=\"$boton_link\" style=\"background: #e8590c; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: bold;\">$boton_texto</a>
      </p>
      <p style=\"margin: 0 0 16px; background: #fff1e8; padding: 12px 14px; border-radius: 8px;\">$extra</p>
      <p style=\"margin: 0;\">¿Tenés alguna duda? Respondé este mail o escribinos desde Contacto.</p>";

    $pie = "Recibiste este correo porque creaste una cuenta en Manos a la Obra con esta dirección.";

    return enviar_correo_html($email, "¡Bienvenido/a a Manos a la Obra!", plantilla_correo($contenido, $pie));
}

// ---------------------------------------------------------
// 2. Respuesta del equipo a un mensaje de Contacto
//   $email, $nombre -> a quién le respondemos (los datos que dejó en Contacto)
//   $asunto         -> el asunto de su mensaje, por ejemplo "Plan Destacado"
//   $mensaje        -> lo que escribió, para mostrárselo debajo de la respuesta
//   $respuesta      -> lo que le escribe el equipo desde la bandeja
// ---------------------------------------------------------
function enviar_respuesta($email, $nombre, $asunto, $mensaje, $respuesta) {
    $sitio = direccion_sitio();

    // Todo lo escribió una persona: htmlspecialchars para que no se pueda meter HTML.
    // nl2br respeta los saltos de línea (los convierte en <br>)
    $nombre    = htmlspecialchars($nombre);
    $asunto_h  = htmlspecialchars($asunto);
    $mensaje   = nl2br(htmlspecialchars($mensaje));
    $respuesta = nl2br(htmlspecialchars($respuesta));

    $contenido = "
      <h1 style=\"font-size: 22px; margin: 0 0 12px;\">¡Hola, $nombre!</h1>
      <p style=\"margin: 0 0 16px;\">Te respondemos sobre tu mensaje <strong>\"$asunto_h\"</strong>:</p>
      <div style=\"margin: 0 0 20px; padding: 14px 16px; border-left: 4px solid #e8590c; background: #fff1e8; border-radius: 0 8px 8px 0;\">$respuesta</div>
      <p style=\"margin: 0 0 6px; font-size: 13px; color: #6b7280;\">Tu mensaje:</p>
      <div style=\"margin: 0 0 24px; padding: 12px 14px; background: #f6f7f9; border-radius: 8px; font-size: 14px; color: #6b7280;\">$mensaje</div>
      <p style=\"margin: 0 0 24px;\">
        <a href=\"$sitio/index.php\" style=\"background: #e8590c; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: bold;\">Ir a Manos a la Obra</a>
      </p>
      <p style=\"margin: 0;\">Si necesitás algo más, respondé este mail.</p>";

    $pie = "Recibiste este correo porque nos escribiste desde el formulario de Contacto de Manos a la Obra.";

    return enviar_correo_html($email, "Re: " . $asunto . " - Manos a la Obra", plantilla_correo($contenido, $pie));
}
