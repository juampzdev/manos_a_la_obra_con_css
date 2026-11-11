<?php
// Bandeja de entrada: los mensajes que la gente manda desde Contacto.
// Solo pueden entrar los administradores (tabla administradores).
// Los mensajes se separan en tres pestañas según quién los mandó:
//   bandeja.php?tipo=cliente  /  ?tipo=profesional  /  ?tipo=visitante (sin cuenta)
// Cada mensaje se puede marcar como leído o no leído, responder por mail o borrar.
session_start();
require "config.php";

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

// ---------- 1. Acciones: marcar como leído / no leído, o borrar ----------
// Llegan por POST desde los botones de cada mensaje
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id     = (int) ($_POST["id"] ?? 0);
    $accion = $_POST["accion"] ?? "";

    if ($accion === "leido" || $accion === "no_leido") {
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

    // Volvemos a la misma pestaña con GET (Post/Redirect/Get: F5 no repite la acción)
    header("Location: bandeja.php?tipo=" . $tipo);
    exit;
}

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
                <details class="mensaje-bandeja <?php echo $m["leido"] ? "" : "sin-leer"; ?>">
                    <summary>
                        <span class="mensaje-de">
                            <?php if (!$m["leido"]): ?><span class="insignia-destacado">Nuevo</span><?php endif; ?>
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

                        <div class="mensaje-acciones">
                            <!-- mailto: abre el programa de mail con el destinatario y el asunto ya puestos.
                                 rawurlencode arma bien el asunto aunque tenga espacios o tildes -->
                            <a href="mailto:<?php echo htmlspecialchars($m["email"]); ?>?subject=<?php echo rawurlencode("Re: " . $m["asunto"]); ?>"
                               class="boton boton-lleno">Responder</a>

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
