<?php
// Perfil público de un profesional: sus datos, sus reseñas y
// el formulario para que un cliente deje la suya.
// Se entra desde el botón "Ver perfil" de cada tarjeta: perfil.php?id=3
session_start();
require "config.php";

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

// (int) convierte lo que venga en un número: si alguien escribe texto, queda 0
$id = (int) ($_GET["id"] ?? 0);

$es_cliente = ($_SESSION["usuario_tipo"] ?? "") === "cliente";
$error = null;

// ---------- 1. Datos del profesional ----------
// Se busca ANTES de guardar una reseña, para no aceptar reseñas de profesionales
// que no existen o que ya no están en actividad.
// Mismas condiciones que en el inicio: perfil completo y en actividad.
// Si no lo encuentra, $profesional queda en null y la página muestra "Perfil no disponible".
// LEFT JOIN trae el nombre del barrio. Como las dos tablas tienen una columna "id",
// hay que aclarar de cuál hablamos: profesionales.id
$consulta = $conexion->prepare(
    "SELECT profesionales.*, barrios.barrio,
            (destacado_hasta IS NOT NULL AND destacado_hasta >= CURDATE()) AS destacado
     FROM profesionales
     LEFT JOIN barrios ON barrios.id = profesionales.barrio_id
     WHERE profesionales.id = ?
       AND oficio IS NOT NULL
       AND (fecha_baja IS NULL OR fecha_baja >= CURDATE())"
);
$consulta->bind_param("i", $id);
$consulta->execute();
$profesional = $consulta->get_result()->fetch_assoc();

// ---------- 2. Guardar una reseña nueva ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $puntaje    = (int) ($_POST["puntaje"] ?? 0);
    $comentario = trim($_POST["comentario"] ?? "");

    if (!$profesional) {
        $error = "Este profesional no está disponible.";
    } elseif (!$es_cliente) {
        $error = "Solo los clientes pueden dejar reseñas.";
    } elseif ($puntaje < 1 || $puntaje > 5 || $comentario === "") {
        $error = "Elegí un puntaje y escribí un comentario.";
    } elseif (mb_strlen($comentario) > 300) {
        $error = "El comentario no puede pasar los 300 caracteres.";
    } else {
        // ¿Ya dejó una reseña a este profesional? (la base tampoco lo permitiría,
        // por el UNIQUE, pero así mostramos un mensaje claro)
        $buscar = $conexion->prepare("SELECT id FROM resenas WHERE profesional_id = ? AND cliente_id = ?");
        $buscar->bind_param("ii", $id, $_SESSION["usuario_id"]);
        $buscar->execute();

        if ($buscar->get_result()->num_rows > 0) {
            $error = "Ya dejaste una reseña a este profesional.";
        } else {
            $insertar = $conexion->prepare(
                "INSERT INTO resenas (profesional_id, cliente_id, puntaje, comentario) VALUES (?, ?, ?, ?)"
            );
            $insertar->bind_param("iiis", $id, $_SESSION["usuario_id"], $puntaje, $comentario);
            $insertar->execute();

            // Recalculamos el promedio y la cantidad que se muestran en la tarjeta
            $recalcular = $conexion->prepare(
                "UPDATE profesionales
                 SET valoracion       = (SELECT ROUND(AVG(puntaje), 1) FROM resenas WHERE profesional_id = ?),
                     cantidad_resenas = (SELECT COUNT(*) FROM resenas WHERE profesional_id = ?)
                 WHERE id = ?"
            );
            $recalcular->bind_param("iii", $id, $id, $id);
            $recalcular->execute();

            // Volvemos a cargar la página con GET, así si toca F5 no se manda dos veces
            header("Location: perfil.php?id=" . $id . "&ok=1#resenas");
            exit;
        }
    }
}

// ---------- 3. Sus reseñas, de la más nueva a la más vieja ----------
// JOIN trae el nombre del cliente que escribió cada reseña desde la tabla clientes
$resenas = [];
$ya_reseno = false;
if ($profesional) {
    $consulta = $conexion->prepare(
        "SELECT resenas.puntaje, resenas.comentario, resenas.creado_en, resenas.cliente_id,
                clientes.nombre, clientes.apellido
         FROM resenas
         JOIN clientes ON clientes.id = resenas.cliente_id
         WHERE resenas.profesional_id = ?
         ORDER BY resenas.creado_en DESC, resenas.id DESC"
    );
    $consulta->bind_param("i", $id);
    $consulta->execute();
    $resenas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($resenas as $r) {
        if ($es_cliente && $r["cliente_id"] == $_SESSION["usuario_id"]) {
            $ya_reseno = true;
        }
    }
}

// Dibuja las estrellas: 4 -> ★★★★☆
function estrellas($puntaje) {
    $llenas = (int) round($puntaje);
    return str_repeat("★", $llenas) . str_repeat("☆", 5 - $llenas);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profesional ? htmlspecialchars($profesional["nombre"] . " " . $profesional["apellido"]) . " | " : ""; ?>Manos a la Obra</title>
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
    </header>

    <main class="seccion">
        <div class="contenedor contenedor-angosto">
            <a href="index.php#profesionales" class="volver">← Volver a los profesionales</a>

            <?php if (!$profesional): ?>
                <div class="perfil-caja">
                    <h1>Perfil no disponible</h1>
                    <p>Este profesional no existe o ya no está en actividad.</p>
                </div>
            <?php else: ?>

                <!-- Datos del profesional -->
                <div class="perfil-caja perfil-cabecera <?php echo $profesional["destacado"] ? "destacada" : ""; ?>">
                    <div class="profesional-avatar perfil-avatar"><?php echo htmlspecialchars($profesional["iniciales"]); ?></div>
                    <div class="profesional-info">
                        <?php if ($profesional["destacado"]): ?>
                            <span class="insignia-destacado">⭐ Destacado</span>
                        <?php endif; ?>
                        <h1><?php echo htmlspecialchars($profesional["nombre"] . " " . $profesional["apellido"]); ?></h1>
                        <p class="oficio"><?php echo htmlspecialchars($profesional["oficio"]); ?></p>
                        <p class="valoracion">⭐ <?php echo $profesional["valoracion"]; ?>
                            (<?php echo $profesional["cantidad_resenas"] . ($profesional["cantidad_resenas"] == 1 ? " reseña" : " reseñas"); ?>)</p>
                    </div>
                </div>

                <div class="perfil-caja">
                    <h2>Sobre su trabajo</h2>
                    <p><?php echo htmlspecialchars($profesional["descripcion"] ?? ""); ?></p>
                    <ul class="perfil-datos">
                        <!-- Barrio y ciudad. El domicilio exacto no se muestra -->
                        <li>📍 <?php
                            if (!empty($profesional["barrio"])) {
                                echo htmlspecialchars($profesional["barrio"]) . ", ";
                            }
                            echo htmlspecialchars($profesional["ubicacion"] ?? "");
                        ?></li>
                        <li>📞 <?php echo htmlspecialchars($profesional["telefono"] ?? ""); ?></li>
                        <li>🛠️ <?php echo (int) $profesional["experiencia"]; ?> años de experiencia</li>
                        <?php if (!empty($profesional["fecha_alta"])): ?>
                            <!-- date("Y", ...) se queda solo con el año de la fecha -->
                            <li>📅 Trabaja desde <?php echo date("Y", strtotime($profesional["fecha_alta"])); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Reseñas -->
                <div class="perfil-caja" id="resenas">
                    <h2>Reseñas</h2>

                    <?php if (isset($_GET["ok"])): ?>
                        <p class="mensaje-ok">¡Gracias! Tu reseña ya está publicada.</p>
                    <?php endif; ?>

                    <?php if (count($resenas) === 0): ?>
                        <p class="texto-suave">Todavía no tiene reseñas.</p>
                    <?php endif; ?>

                    <?php foreach ($resenas as $r): ?>
                        <div class="resena">
                            <div class="resena-encabezado">
                                <strong><?php echo htmlspecialchars($r["nombre"] . " " . mb_substr($r["apellido"], 0, 1) . "."); ?></strong>
                                <span class="estrellas" aria-label="<?php echo $r["puntaje"]; ?> de 5"><?php echo estrellas($r["puntaje"]); ?></span>
                            </div>
                            <p><?php echo htmlspecialchars($r["comentario"]); ?></p>
                            <span class="resena-fecha"><?php echo date("d/m/Y", strtotime($r["creado_en"])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dejar una reseña: depende de quién esté mirando -->
                <div class="perfil-caja">
                    <?php if (!isset($_SESSION["usuario_id"])): ?>
                        <p class="texto-suave">¿Trabajaste con <?php echo htmlspecialchars($profesional["nombre"]); ?>?
                            <a href="login.php">Ingresá</a> con tu cuenta de cliente para dejar una reseña.</p>
                    <?php elseif (!$es_cliente): ?>
                        <p class="texto-suave">Solo los clientes pueden dejar reseñas.</p>
                    <?php elseif ($ya_reseno): ?>
                        <p class="texto-suave">Ya dejaste tu reseña a este profesional.</p>
                    <?php else: ?>
                        <form action="perfil.php?id=<?php echo $id; ?>#resenas" method="post" class="formulario formulario-plano">
                            <h2>Dejá tu reseña</h2>

                            <?php if ($error): ?>
                                <p class="mensaje-error"><?php echo $error; ?></p>
                            <?php endif; ?>

                            <label for="puntaje">Puntaje
                                <select id="puntaje" name="puntaje" required>
                                    <!-- Si hubo un error, queda elegido el puntaje que había puesto -->
                                    <?php $puntaje_elegido = (int) ($_POST["puntaje"] ?? 0); ?>
                                    <option value="" disabled <?php echo $puntaje_elegido === 0 ? "selected" : ""; ?>>Elegí de 1 a 5 estrellas</option>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $puntaje_elegido === $i ? "selected" : ""; ?>><?php echo estrellas($i) . " (" . $i . ")"; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </label>

                            <label for="comentario">Comentario
                                <textarea id="comentario" name="comentario" maxlength="300" required><?php echo htmlspecialchars($_POST["comentario"] ?? ""); ?></textarea>
                            </label>

                            <button type="submit">Publicar reseña</button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
    </main>

</body>
</html>
