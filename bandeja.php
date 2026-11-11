<?php
// Bandeja de entrada: los mensajes que la gente manda desde Contacto.
// Solo pueden entrar los administradores (tabla administradores).
// Los mensajes se separan en tres pestañas según quién los mandó:
//   bandeja.php?tipo=cliente  /  ?tipo=profesional  /  ?tipo=visitante (sin cuenta)
// Cada mensaje se puede responder (la respuesta llega por mail a quien escribió
// y queda guardada en la tabla respuestas), marcar como leído o no leído, o borrar.
session_start();
require "config.php";
require "correos.php";   // la función enviar_respuesta()

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

// ---------- Solo administradores ----------
// Sin sesión, al login. Con sesión de cliente o profesional, al inicio.
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION["usuario_tipo"] !== "admin") {
    header("Location: index.php");
    exit;
}

// Las tres pestañas: el valor que se guarda en la base => el texto que se ve
$pestanias = [
    "cliente"     => "👤 Clientes",
    "profesional" => "🛠️ Profesionales",
    "visitante"   => "✉️ Sin cuenta",
];
// La pestaña elegida llega por la dirección. Si no es una de la lista, mostramos Clientes
$tipo = array_key_exists($_GET["tipo"] ?? "", $pestanias) ? $_GET["tipo"] : "cliente";

// ---------- 1. Acciones: responder, marcar como leído / no leído, o borrar ----------
// Llegan por POST desde los botones de cada mensaje
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id     = (int) ($_POST["id"] ?? 0);
    $accion = $_POST["accion"] ?? "";
    $aviso  = "";   // qué cartel mostrar al volver (ver "Avisos" más abajo)

    if ($accion === "responder") {
        $texto = trim($_POST["respuesta"] ?? "");

        // Buscamos el mensaje: necesitamos el email, el nombre, el asunto y lo que escribió
        $buscar = $conexion->prepare("SELECT nombre, email, asunto, mensaje FROM mensajes WHERE id = ?");
        $buscar->bind_param("i", $id);
        $buscar->execute();
        $original = $buscar->get_result()->fetch_assoc();

        if (!$original) {
            $aviso = "no_existe";
        } elseif ($texto === "" || mb_strlen($texto) > 2000) {
            $aviso = "vacia";
        } else {
            // 1) Le mandamos la respuesta por mail (true si salió, false si no)
            $enviada = enviar_respuesta($original["email"], $original["nombre"],
                                        $original["asunto"], $original["mensaje"], $texto) ? 1 : 0;

            // 2) La guardamos igual, salga o no el mail, así queda registro de qué se le dijo
            $guardar = $conexion->prepare("INSERT INTO respuestas (mensaje_id, texto, enviada) VALUES (?, ?, ?)");
            $guardar->bind_param("isi", $id, $texto, $enviada);
            $guardar->execute();

            // 3) Un mensaje respondido ya está leído
            $marcar = $conexion->prepare("UPDATE mensajes SET leido = 1 WHERE id = ?");
            $marcar->bind_param("i", $id);
            $marcar->execute();

            $aviso = $enviada ? "enviada" : "no_enviada";
        }
    } elseif ($accion === "leido" || $accion === "no_leido") {
        $leido = $accion === "leido" ? 1 : 0;
        $cambiar = $conexion->prepare("UPDATE mensajes SET leido = ? WHERE id = ?");
        $cambiar->bind_param("ii", $leido, $id);
        $cambiar->execute();
    } elseif ($accion === "borrar") {
        // Primero borramos los archivos de las imágenes de la carpeta adjuntos/.
        // Las filas de mensaje_imagenes se borran solas por el ON DELETE CASCADE.
        $imagenes = $conexion->prepare("SELECT archivo FROM mensaje_imagenes WHERE mensaje_id = ?");
        $imagenes->bind_param("i", $id);
        $imagenes->execute();
        foreach ($imagenes->get_result()->fetch_all(MYSQLI_ASSOC) as $imagen) {
            $ruta = __DIR__ . "/adjuntos/" . basename($imagen["archivo"]);
            if (is_file($ruta)) {
                unlink($ruta);   // unlink borra un archivo
            }
        }
        $borrar = $conexion->prepare("DELETE FROM mensajes WHERE id = ?");
        $borrar->bind_param("i", $id);
        $borrar->execute();
    }

    // Volvemos a la misma pestaña con GET (Post/Redirect/Get: F5 no repite la acción).
    // abierto=id deja ese mensaje abierto, y #mensaje-id hace bajar la página hasta él
    $volver = "bandeja.php?tipo=" . $tipo;
    if ($aviso !== "") {
        $volver .= "&aviso=" . $aviso . "&abierto=" . $id . "#mensaje-" . $id;
    }
    header("Location: " . $volver);
    exit;
}

// ---------- Avisos ----------
// Después de responder, la página vuelve con ?aviso=... y mostramos el cartel que corresponde
$avisos = [
    "enviada"    => ["ok",    "¡Listo! La respuesta se mandó por mail y quedó guardada en el mensaje."],
    "no_enviada" => ["error", "La respuesta quedó guardada, pero el mail no salió: falta configurar el envío de mails en XAMPP (ver README.md)."],
    "vacia"      => ["error", "Escribí la respuesta antes de enviarla (hasta 2000 caracteres)."],
    "no_existe"  => ["error", "Ese mensaje ya no existe."],
];
$aviso   = $avisos[$_GET["aviso"] ?? ""] ?? null;
$abierto = (int) ($_GET["abierto"] ?? 0);   // el mensaje que tiene que quedar abierto

// ---------- 2. Cuántos mensajes hay en cada pestaña ----------
// GROUP BY junta las filas por tipo_remitente: una fila por pestaña.
// SUM(leido = 0) cuenta los sin leer: (leido = 0) vale 1 si no está leído y 0 si lo está.
$cantidades = [];
$resultado = $conexion->query(
    "SELECT tipo_remitente, COUNT(*) AS total, SUM(leido = 0) AS sin_leer
     FROM mensajes GROUP BY tipo_remitente"
);
while ($fila = $resultado->fetch_assoc()) {
    $cantidades[$fila["tipo_remitente"]] = $fila;
}

// ---------- 3. Los mensajes de la pestaña elegida ----------
// Primero los sin leer, después del más nuevo al más viejo
$consulta = $conexion->prepare(
    "SELECT * FROM mensajes WHERE tipo_remitente = ? ORDER BY leido ASC, creado_en DESC, id DESC"
);
$consulta->bind_param("s", $tipo);
$consulta->execute();
$mensajes = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);

// Las imágenes de esos mensajes, agrupadas por mensaje: $imagenes_por_mensaje[id del mensaje] = [imágenes]
// JOIN con mensajes para traer solo las de esta pestaña, en una sola consulta
$imagenes_por_mensaje = [];
$consulta = $conexion->prepare(
    "SELECT mensaje_imagenes.*
     FROM mensaje_imagenes
     JOIN mensajes ON mensajes.id = mensaje_imagenes.mensaje_id
     WHERE mensajes.tipo_remitente = ?
     ORDER BY mensaje_imagenes.id"
);
$consulta->bind_param("s", $tipo);
$consulta->execute();
foreach ($consulta->get_result()->fetch_all(MYSQLI_ASSOC) as $imagen) {
    $imagenes_por_mensaje[$imagen["mensaje_id"]][] = $imagen;
}

// Las respuestas que ya se mandaron, igual que las imágenes: agrupadas por mensaje
$respuestas_por_mensaje = [];
$consulta = $conexion->prepare(
    "SELECT respuestas.*
     FROM respuestas
     JOIN mensajes ON mensajes.id = respuestas.mensaje_id
     WHERE mensajes.tipo_remitente = ?
     ORDER BY respuestas.creado_en, respuestas.id"
);
$consulta->bind_param("s", $tipo);
$consulta->execute();
foreach ($consulta->get_result()->fetch_all(MYSQLI_ASSOC) as $respuesta) {
    $respuestas_por_mensaje[$respuesta["mensaje_id"]][] = $respuesta;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja de entrada | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body id="arriba" class="<?php echo $modo_oscuro ? "oscuro" : ""; ?>">

    <header class="header-principal">
        <div class="barra-superior">
            <a href="index.php" class="logo">
                <span class="logo-icono">🏠</span>
                <span class="logo-texto"><strong>Manos</strong><span>a la Obra</span></span>
            </a>
            <div class="acciones">
                <a href="tema.php" class="boton-tema" title="<?php echo $modo_oscuro ? "Activar modo claro" : "Activar modo oscuro"; ?>">
                    <?php echo $modo_oscuro ? "☀️" : "🌙"; ?>
                </a>
                <span class="link-ingresar">Hola, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?></span>
                <a href="logout.php" class="btn-publicar">Salir</a>
            </div>
        </div>
        <nav class="barra-menu">
            <a href="index.php">Inicio</a>
            <a href="como_funciona.php">Cómo funciona</a>
            <a href="contacto.php">Contacto</a>
            <a href="bandeja.php" class="activo">📥 Bandeja de entrada</a>
        </nav>
    </header>

    <main class="seccion">
        <div class="contenedor contenedor-bandeja">
            <h1 class="como-titulo">📥 Bandeja de entrada</h1>
            <p class="como-intro">Los mensajes que llegan desde Contacto, separados según quién los mandó.</p>

            <?php if ($aviso): ?>
                <p class="mensaje-<?php echo $aviso[0]; ?> aviso-bandeja"><?php echo $aviso[1]; ?></p>
            <?php endif; ?>

            <!-- Pestañas: son links a la misma página con otro ?tipo= -->
            <nav class="pestanias">
                <?php foreach ($pestanias as $valor => $texto): ?>
                    <?php
                    $total    = $cantidades[$valor]["total"] ?? 0;
                    $sin_leer = $cantidades[$valor]["sin_leer"] ?? 0;
                    ?>
                    <a href="bandeja.php?tipo=<?php echo $valor; ?>" class="pestania <?php echo $tipo === $valor ? "activa" : ""; ?>">
                        <?php echo $texto; ?> (<?php echo $total; ?>)
                        <?php if ($sin_leer > 0): ?>
                            <span class="contador-nuevos"><?php echo $sin_leer; ?> sin leer</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (count($mensajes) === 0): ?>
                <div class="perfil-caja">
                    <p class="texto-suave">No hay mensajes en esta pestaña.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($mensajes as $m): ?>
                <!-- <details> se abre y se cierra al tocar el <summary>, sin JavaScript.
                     Los mensajes sin leer llevan la clase "sin-leer" (borde naranja) -->
                <!-- id="mensaje-3": para volver justo a este mensaje después de responder.
                     open: el atributo que deja abierto un <details> -->
                <details id="mensaje-<?php echo $m["id"]; ?>"
                         class="mensaje-bandeja <?php echo $m["leido"] ? "" : "sin-leer"; ?>"
                         <?php echo $abierto === (int) $m["id"] ? "open" : ""; ?>>
                    <summary>
                        <span class="mensaje-de">
                            <?php if (!$m["leido"]): ?><span class="insignia-destacado">Nuevo</span><?php endif; ?>
                            <?php if (!empty($respuestas_por_mensaje[$m["id"]])): ?><span class="insignia-respondido">✓ Respondido</span><?php endif; ?>
                            <strong><?php echo htmlspecialchars($m["nombre"]); ?></strong>
                            · <?php echo htmlspecialchars($m["asunto"]); ?>
                        </span>
                        <span class="mensaje-fecha"><?php echo date("d/m/Y H:i", strtotime($m["creado_en"])); ?></span>
                    </summary>

                    <div class="mensaje-cuerpo">
                        <p class="texto-suave">
                            ✉️ <?php echo htmlspecialchars($m["email"]); ?>
                            <?php if ($m["tipo_remitente"] === "profesional" && $m["remitente_id"]): ?>
                                · <a href="perfil.php?id=<?php echo (int) $m["remitente_id"]; ?>">Ver su perfil</a>
                            <?php endif; ?>
                        </p>

                        <!-- nl2br respeta los saltos de línea que escribió la persona -->
                        <p><?php echo nl2br(htmlspecialchars($m["mensaje"])); ?></p>

                        <?php if (!empty($imagenes_por_mensaje[$m["id"]])): ?>
                            <div class="mensaje-imagenes">
                                <?php foreach ($imagenes_por_mensaje[$m["id"]] as $imagen): ?>
                                    <!-- target="_blank" abre la imagen en tamaño real en otra pestaña -->
                                    <a href="imagen_mensaje.php?id=<?php echo $imagen["id"]; ?>" target="_blank">
                                        <img src="imagen_mensaje.php?id=<?php echo $imagen["id"]; ?>"
                                             alt="<?php echo htmlspecialchars($imagen["nombre_original"]); ?>">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Respuestas que ya se le mandaron a este mensaje -->
                        <?php foreach ($respuestas_por_mensaje[$m["id"]] ?? [] as $r): ?>
                            <div class="respuesta-enviada">
                                <p class="respuesta-encabezado">
                                    <strong>Respuesta del equipo</strong> · <?php echo date("d/m/Y H:i", strtotime($r["creado_en"])); ?> ·
                                    <?php if ($r["enviada"]): ?>
                                        <span class="respuesta-ok">✓ Enviada por mail</span>
                                    <?php else: ?>
                                        <span class="respuesta-error">⚠ El mail no salió</span>
                                    <?php endif; ?>
                                </p>
                                <p><?php echo nl2br(htmlspecialchars($r["texto"])); ?></p>
                            </div>
                        <?php endforeach; ?>

                        <div class="mensaje-acciones">
                            <!-- Responder: otro <details> que al tocarlo muestra el formulario.
                                 La respuesta le llega por mail a quien escribió (la manda enviar_respuesta()) -->
                            <details class="responder">
                                <summary class="boton boton-lleno">Responder</summary>
                                <form action="bandeja.php?tipo=<?php echo $tipo; ?>" method="post" class="formulario formulario-plano">
                                    <input type="hidden" name="id" value="<?php echo $m["id"]; ?>">
                                    <input type="hidden" name="accion" value="responder">
                                    <label for="respuesta-<?php echo $m["id"]; ?>">
                                        Respuesta para <?php echo htmlspecialchars($m["nombre"]); ?>
                                        <span class="ayuda">Le llega por mail a <?php echo htmlspecialchars($m["email"]); ?>, con su mensaje debajo.</span>
                                        <textarea id="respuesta-<?php echo $m["id"]; ?>" name="respuesta" maxlength="2000" required></textarea>
                                    </label>
                                    <button type="submit">Enviar respuesta</button>
                                </form>
                            </details>

                            <form action="bandeja.php?tipo=<?php echo $tipo; ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $m["id"]; ?>">
                                <input type="hidden" name="accion" value="<?php echo $m["leido"] ? "no_leido" : "leido"; ?>">
                                <button type="submit" class="boton boton-borde">
                                    <?php echo $m["leido"] ? "Marcar como no leído" : "Marcar como leído"; ?>
                                </button>
                            </form>

                            <!-- Borrar pide confirmación con otro <details>: hay que tocar dos veces -->
                            <details class="confirmar-borrado">
                                <summary class="boton boton-borde">Borrar</summary>
                                <form action="bandeja.php?tipo=<?php echo $tipo; ?>" method="post">
                                    <input type="hidden" name="id" value="<?php echo $m["id"]; ?>">
                                    <input type="hidden" name="accion" value="borrar">
                                    <button type="submit" class="boton boton-peligro">Sí, borrar el mensaje</button>
                                </form>
                            </details>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>

        </div>
    </main>

    <a href="#arriba" class="boton-subir" aria-label="Volver arriba" title="Volver arriba">↑</a>

</body>
</html>
