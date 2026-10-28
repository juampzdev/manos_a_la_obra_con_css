<?php
// "Cómo funciona": explica el sitio según quién esté mirando.
//   - Sin cuenta: los dos caminos (buscar un profesional u ofrecer un servicio).
//   - Cliente: cómo buscar, contactar y dejar reseñas, y cuántas reseñas dejó.
//   - Profesional: cómo aparecer en el inicio y el estado de su perfil.
session_start();
require "config.php";

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

// "visitante", "cliente" o "profesional"
$tipo = $_SESSION["usuario_tipo"] ?? "visitante";

// ---------- Datos del cliente: cuántas reseñas dejó ----------
$cantidad_mis_resenas = 0;
if ($tipo === "cliente") {
    $consulta = $conexion->prepare("SELECT COUNT(*) AS cantidad FROM resenas WHERE cliente_id = ?");
    $consulta->bind_param("i", $_SESSION["usuario_id"]);
    $consulta->execute();
    $cantidad_mis_resenas = $consulta->get_result()->fetch_assoc()["cantidad"];
}

// ---------- Datos del profesional: el estado de su perfil ----------
$mi_perfil = null;
if ($tipo === "profesional") {
    $consulta = $conexion->prepare(
        "SELECT oficio, fecha_baja, valoracion, cantidad_resenas, destacado_hasta FROM profesionales WHERE id = ?"
    );
    $consulta->bind_param("i", $_SESSION["usuario_id"]);
    $consulta->execute();
    $mi_perfil = $consulta->get_result()->fetch_assoc();
}
// Mismas condiciones que usa el inicio para mostrar a un profesional
$perfil_completo = $mi_perfil && $mi_perfil["oficio"] !== null;
$dado_de_baja    = $mi_perfil && $mi_perfil["fecha_baja"] !== null && $mi_perfil["fecha_baja"] < date("Y-m-d");
// ¿Tiene el plan Destacado vigente? date("Y-m-d") es la fecha de hoy, por ejemplo "2026-10-28".
// Las fechas en ese formato se pueden comparar como texto: "2026-11-28" >= "2026-10-28"
$es_destacado    = $mi_perfil && $mi_perfil["destacado_hasta"] !== null && $mi_perfil["destacado_hasta"] >= date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cómo funciona | Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $modo_oscuro ? "oscuro" : ""; ?>">

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
                <?php if (isset($_SESSION["usuario_nombre"])): ?>
                    <span class="link-ingresar">Hola, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?></span>
                    <a href="logout.php" class="btn-publicar">Salir</a>
                <?php else: ?>
                    <a href="login.php" class="link-ingresar">Ingresar</a>
                    <a href="registro.php" class="btn-publicar">Crear cuenta</a>
                <?php endif; ?>
            </div>
        </div>
        <nav class="barra-menu">
            <a href="index.php">Inicio</a>
            <a href="index.php#oficios">Oficios</a>
            <a href="index.php#cerca">Cerca de vos</a>
            <a href="index.php#profesionales">Profesionales</a>
            <a href="como_funciona.php" class="activo">Cómo funciona</a>
            <a href="contacto.php">Contacto</a>
        </nav>
    </header>

    <main class="seccion">
        <div class="contenedor contenedor-angosto">

            <?php if ($tipo === "visitante"): ?>
                <!-- ===================== Sin cuenta ===================== -->
                <h1 class="como-titulo">¿Cómo funciona Manos a la Obra?</h1>
                <p class="como-intro">Conectamos a personas que necesitan un trabajo en su casa con profesionales de oficios de su zona.
                    Elegí lo que querés hacer:</p>

                <div class="perfil-caja">
                    <h2>🔍 Busco un profesional</h2>
                    <ol class="pasos">
                        <li><strong>Buscá.</strong> Escribí lo que necesitás en el buscador, tocá un oficio o elegí tu barrio en "Cerca de vos".</li>
                        <li><strong>Compará.</strong> Cada tarjeta muestra el oficio, el barrio y la valoración. Entrá a "Ver perfil" para leer la descripción y las reseñas.</li>
                        <li><strong>Contactalo.</strong> El teléfono está en su tarjeta y en su perfil: lo llamás directamente, sin intermediarios.</li>
                        <li><strong>Opiná.</strong> Si creás una cuenta de cliente, podés dejar una reseña para ayudar a otros.</li>
                    </ol>
                    <p class="texto-suave">Para buscar y ver perfiles no necesitás cuenta.</p>
                </div>

                <div class="perfil-caja">
                    <h2>🛠️ Ofrezco un servicio</h2>
                    <ol class="pasos">
                        <li><strong>Creá tu cuenta</strong> eligiendo el tipo <em>Profesional</em>.</li>
                        <li><strong>Completá tu perfil:</strong> oficio, descripción, barrio, años de experiencia y teléfono.</li>
                        <li><strong>Aparecé en el inicio.</strong> Los clientes te encuentran por tu oficio, tu nombre o tu barrio.</li>
                        <li><strong>Sumá reseñas.</strong> Cada opinión de un cliente actualiza tu valoración, y los mejor valorados aparecen primero.</li>
                    </ol>
                </div>

                <div class="como-acciones">
                    <a href="registro.php" class="boton boton-lleno">Crear cuenta</a>
                    <a href="login.php" class="boton boton-borde">Ya tengo cuenta</a>
                </div>

            <?php elseif ($tipo === "cliente"): ?>
                <!-- ===================== Cliente ===================== -->
                <h1 class="como-titulo">Hola, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?> 👋</h1>
                <p class="como-intro">Tenés una cuenta de <strong>cliente</strong>. Así podés aprovecharla:</p>

                <div class="perfil-caja estado-cuenta">
                    <div>
                        <span class="estado-numero"><?php echo $cantidad_mis_resenas; ?></span>
                        <span class="texto-suave"><?php echo $cantidad_mis_resenas == 1 ? "reseña dejada" : "reseñas dejadas"; ?></span>
                    </div>
                    <a href="index.php#profesionales" class="boton boton-lleno">Buscar profesionales</a>
                </div>

                <div class="perfil-caja">
                    <h2>Cómo encontrar al profesional indicado</h2>
                    <ol class="pasos">
                        <li><strong>Buscá</strong> por oficio, nombre o barrio en el buscador del <a href="index.php">inicio</a>.</li>
                        <li><strong>Usá "Cerca de vos":</strong> elegís tu barrio y te mostramos primero a los de tu barrio y después a los de tu zona.</li>
                        <li><strong>Leé las reseñas</strong> en "Ver perfil" antes de decidir.</li>
                        <li><strong>Llamalo</strong> al teléfono de su perfil para coordinar el trabajo.</li>
                    </ol>
                </div>

                <div class="perfil-caja">
                    <h2>Dejar una reseña</h2>
                    <ol class="pasos">
                        <li>Cuando termine el trabajo, entrá al perfil del profesional.</li>
                        <li>Abajo de todo vas a ver "Dejá tu reseña": elegí de 1 a 5 estrellas y contá cómo te fue.</li>
                        <li>Tu reseña se publica al instante y actualiza su valoración.</li>
                    </ol>
                    <p class="texto-suave">Podés dejar una sola reseña por profesional, así las opiniones son justas.</p>
                </div>

                <div class="perfil-caja">
                    <h2>¿Querés ofrecer tus servicios?</h2>
                    <p>Las cuentas de cliente no publican perfiles. Para aparecer como profesional, <a href="logout.php">salí</a>
                        y creá una cuenta nueva de tipo <em>Profesional</em> con otro email.</p>
                </div>

            <?php else: ?>
                <!-- ===================== Profesional ===================== -->
                <h1 class="como-titulo">Hola, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?> 👋</h1>
                <p class="como-intro">Tenés una cuenta de <strong>profesional</strong>. Así funciona tu presencia en el sitio:</p>

                <!-- El estado del perfil cambia según lo que hay en la base -->
                <div class="perfil-caja estado-cuenta">
                    <?php if (!$perfil_completo): ?>
                        <p class="mensaje-error">Todavía no completaste tu perfil, así que <strong>no aparecés en el inicio</strong>.</p>
                        <a href="postular.php" class="boton boton-lleno">Completar mi perfil</a>
                    <?php elseif ($dado_de_baja): ?>
                        <p class="mensaje-error">Tu perfil tiene una fecha de baja pasada, así que <strong>no aparecés en el inicio</strong>.
                            Si volviste a trabajar, borrá la fecha de baja.</p>
                        <a href="postular.php" class="boton boton-lleno">Editar mi perfil</a>
                    <?php else: ?>
                        <div>
                            <span class="estado-numero">⭐ <?php echo $mi_perfil["valoracion"]; ?></span>
                            <span class="texto-suave"><?php echo $mi_perfil["cantidad_resenas"] . ($mi_perfil["cantidad_resenas"] == 1 ? " reseña" : " reseñas"); ?> · Tu perfil está publicado</span>
                        </div>
                        <a href="perfil.php?id=<?php echo $_SESSION["usuario_id"]; ?>" class="boton boton-lleno">Ver mi perfil público</a>
                    <?php endif; ?>
                </div>

                <div class="perfil-caja">
                    <h2>Cómo aparecer en el inicio</h2>
                    <ol class="pasos">
                        <li><strong>Completá tu perfil</strong> en "Postular servicio": oficio, descripción, barrio, experiencia y teléfono.</li>
                        <li><strong>Dejá vacía la fecha de baja</strong> mientras sigas trabajando. Si ponés una fecha pasada, dejás de aparecer.</li>
                        <li>Podés <strong>editar tu perfil</strong> cuando quieras desde el mismo botón.</li>
                    </ol>
                </div>

                <!-- Plan Destacado: cambia según si lo tiene o no -->
                <div class="perfil-caja <?php echo $es_destacado ? "destacada" : ""; ?>">
                    <h2>⭐ Plan Destacado</h2>
                    <?php if ($es_destacado): ?>
                        <p class="mensaje-ok">Tu plan Destacado está activo hasta el
                            <strong><?php echo date("d/m/Y", strtotime($mi_perfil["destacado_hasta"])); ?></strong>.</p>
                        <p>Mientras esté activo, aparecés primero en el inicio, en tu oficio y en "Cerca de vos",
                            con la insignia "⭐ Destacado" en tu tarjeta y en tu perfil.</p>
                    <?php else: ?>
                        <p>Publicar tu perfil es gratis. Si querés más visibilidad, con el plan Destacado (mensual):</p>
                        <ol class="pasos">
                            <li>Aparecés <strong>primero</strong> en el inicio, en tu oficio y en "Cerca de vos".</li>
                            <li>Tu tarjeta y tu perfil muestran la insignia <strong>"⭐ Destacado"</strong>.</li>
                            <li>Tus reseñas y tu valoración siguen siendo las mismas: el plan no cambia lo que opinan los clientes.</li>
                        </ol>
                        <a href="contacto.php?asunto=<?php echo urlencode("Plan Destacado"); ?>" class="boton boton-lleno">Quiero ser Destacado</a>
                    <?php endif; ?>
                </div>

                <div class="perfil-caja">
                    <h2>Cómo te encuentran los clientes</h2>
                    <ol class="pasos">
                        <li>Por el <strong>buscador</strong>: buscan tu oficio, tu nombre, tu barrio o palabras de tu descripción.</li>
                        <li>Por las <strong>tarjetas de oficios</strong> del inicio.</li>
                        <li>Por <strong>"Cerca de vos"</strong>: si eligen tu barrio o uno de tu zona, aparecés en la lista.</li>
                    </ol>
                    <p class="texto-suave">Tu domicilio no se muestra en la página: solo tu barrio y tu ciudad.</p>
                </div>

                <div class="perfil-caja">
                    <h2>Reseñas y valoración</h2>
                    <ol class="pasos">
                        <li>Los clientes con cuenta pueden dejarte una reseña de 1 a 5 estrellas.</li>
                        <li>Tu valoración es el promedio de todas tus reseñas y se actualiza sola.</li>
                        <li>En el inicio, los profesionales mejor valorados aparecen primero.</li>
                    </ol>
                </div>

            <?php endif; ?>

            <!-- Preguntas frecuentes: las mismas para todos -->
            <div class="perfil-caja">
                <h2>Preguntas frecuentes</h2>
                <div class="pregunta-frecuente">
                    <h3>¿Puedo tener una cuenta de cliente y otra de profesional?</h3>
                    <p>Sí, pero con emails distintos. Un mismo email solo puede tener una cuenta.</p>
                </div>
                <div class="pregunta-frecuente">
                    <h3>¿Qué significa "⭐ Destacado"?</h3>
                    <p>Son profesionales con el plan Destacado: pagan un abono mensual para aparecer primero.
                        Las reseñas y la valoración no se pueden comprar: las escriben solo los clientes.</p>
                </div>
                <div class="pregunta-frecuente">
                    <h3>¿Se muestra mi dirección?</h3>
                    <p>No. De los profesionales solo se muestran el barrio y la ciudad. Los clientes no publican ningún dato.</p>
                </div>
                <div class="pregunta-frecuente">
                    <h3>¿Puedo cambiar una reseña que ya dejé?</h3>
                    <p>Por ahora no. Si necesitás corregirla, escribinos desde <a href="contacto.php">Contacto</a>.</p>
                </div>
                <div class="pregunta-frecuente">
                    <h3>¿Tengo otra duda?</h3>
                    <p>Escribinos desde <a href="contacto.php">Contacto</a> y te respondemos por mail.</p>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
