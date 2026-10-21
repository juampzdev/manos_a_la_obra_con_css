<?php
// session_start() tiene que ir SIEMPRE antes de imprimir cualquier HTML,
// es lo que nos permite leer si hay un usuario logueado ($_SESSION)
session_start();
require "config.php";

// ¿Eligió modo oscuro? Lo guarda tema.php en una cookie
$modo_oscuro = ($_COOKIE["tema"] ?? "") === "oscuro";

// Lo que el usuario buscó o el oficio que tocó (llegan por la dirección:
// index.php?buscar=pintor  o  index.php?oficio=Plomero)
$buscar = trim($_GET["buscar"] ?? "");
$oficio = trim($_GET["oficio"] ?? "");

// Traemos los profesionales desde la base de datos en vez de tenerlos
// escritos a mano en el HTML como antes
// Solo los que ya completaron su perfil (tienen oficio) y siguen en actividad
// (sin fecha de baja o con baja a futuro).
// Si hay una búsqueda o un oficio elegido, se agrega esa condición.
$sql = "SELECT * FROM profesionales
        WHERE oficio IS NOT NULL
          AND (fecha_baja IS NULL OR fecha_baja >= CURDATE())";
$tipos = "";
$valores = [];

if ($oficio !== "") {
    $sql .= " AND oficio = ?";
    $tipos .= "s";
    $valores[] = $oficio;
}
if ($buscar !== "") {
    // LIKE con % busca el texto en cualquier parte: "pint" encuentra "Pintor"
    $sql .= " AND (oficio LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR descripcion LIKE ? OR ubicacion LIKE ?)";
    $patron = "%" . $buscar . "%";
    $tipos .= "sssss";
    array_push($valores, $patron, $patron, $patron, $patron, $patron);
}
$sql .= " ORDER BY valoracion DESC, cantidad_resenas DESC";   // mejor valorados primero

$consulta = $conexion->prepare($sql);
if ($tipos !== "") {
    // ...$valores pasa cada elemento del array como un dato aparte
    $consulta->bind_param($tipos, ...$valores);
}
$consulta->execute();
$resultado_profesionales = $consulta->get_result();

// Título de la sección según lo que se esté mostrando
if ($oficio !== "") {
    $titulo_profesionales = $oficio;
} elseif ($buscar !== "") {
    $titulo_profesionales = "Resultados para \"" . $buscar . "\"";
} else {
    $titulo_profesionales = "Profesionales destacados";
}

// Los oficios del inicio: nombre y emoji. Cada tarjeta filtra por su oficio.
$oficios_destacados = [
    "Electricista" => "⚡",
    "Gasista"      => "🔥",
    "Plomero"      => "🚰",
    "Pintor"       => "🎨",
    "Carpintero"   => "🔨",
    "Albañil"      => "🧱",
];

// A dónde lleva el botón "Publicar servicio" según quién esté mirando
if (!isset($_SESSION["usuario_id"])) {
    $link_publicar = "registro.php";              // sin cuenta: que se registre
} elseif ($_SESSION["usuario_tipo"] === "profesional") {
    $link_publicar = "postular.php";              // profesional: a su perfil
} else {
    $link_publicar = "";                          // cliente: no publica
}

// Si es un profesional que todavía no completó su perfil (oficio vacío),
// le mostramos un aviso arriba, porque mientras tanto no aparece en el inicio
$falta_perfil = false;
if (($_SESSION["usuario_tipo"] ?? "") === "profesional") {
    $consulta_perfil = $conexion->prepare("SELECT oficio FROM profesionales WHERE id = ?");
    $consulta_perfil->bind_param("i", $_SESSION["usuario_id"]);
    $consulta_perfil->execute();
    $fila = $consulta_perfil->get_result()->fetch_assoc();
    $falta_perfil = $fila && $fila["oficio"] === null;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manos a la Obra</title>
    <link rel="stylesheet" href="style.css">
</head>

<!-- Con modo oscuro, el body lleva la clase "oscuro" y style.css cambia los colores -->
<body class="<?php echo $modo_oscuro ? "oscuro" : ""; ?>">

    <header class="header-principal">
        <div class="barra-superior">
            <a href="index.php" class="logo">
                <span class="logo-icono">🏠</span>
                <span class="logo-texto"><strong>Manos</strong><span>a la Obra</span></span>
            </a>
            <!-- method="get": lo buscado viaja en la dirección (index.php?buscar=...) -->
            <form class="buscador" action="index.php#profesionales" method="get">
                <input type="search" name="buscar" placeholder="¿Qué servicio necesitás?" value="<?php echo htmlspecialchars($buscar); ?>">
                <button type="submit" aria-label="Buscar">🔍</button>
            </form>
            <div class="acciones">
                <!-- Botón de modo oscuro: tema.php cambia el modo y vuelve a esta página -->
                <a href="tema.php" class="boton-tema" title="<?php echo $modo_oscuro ? "Activar modo claro" : "Activar modo oscuro"; ?>">
                    <?php echo $modo_oscuro ? "☀️" : "🌙"; ?>
                </a>
                <?php if (isset($_SESSION["usuario_nombre"])): ?>
                    <!-- Si hay sesion iniciada, mostramos el nombre y el boton de Salir -->
                    <span class="link-ingresar">Hola, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?></span>
                    <?php if (($_SESSION["usuario_tipo"] ?? "") === "profesional"): ?>
                        <a href="postular.php" class="btn-publicar">Postular servicio</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-publicar">Salir</a>
                <?php else: ?>
                    <!-- Si no hay sesion, mostramos el link de Ingresar -->
                    <a href="login.php" class="link-ingresar">Ingresar</a>
                    <a href="registro.php" class="btn-publicar">Crear cuenta</a>
                <?php endif; ?>
            </div>
        </div>
        <nav class="barra-menu">
            <a href="index.php" class="activo">Inicio</a>
            <a href="#oficios">Oficios</a>
            <a href="#profesionales">Profesionales</a>
            <a href="#">Cómo funciona</a>
            <a href="#">Contacto</a>
        </nav>
    </header>

    <?php if ($falta_perfil): ?>
        <!-- Aviso para el profesional que todavía no cargó su perfil -->
        <div class="aviso-perfil">
            <div class="contenedor">
                <p>👋 <strong>¡Bienvenido/a, <?php echo htmlspecialchars($_SESSION["usuario_nombre"]); ?>!</strong>
                    Completá tu perfil para aparecer en el inicio y que los clientes te encuentren.</p>
                <a href="postular.php" class="boton boton-lleno">Completar mi perfil</a>
            </div>
        </div>
    <?php endif; ?>

    <section class="hero">
        <div class="contenedor">
            <div class="hero-texto">
                <h1>Encontrá a la persona indicada para tu próximo trabajo</h1>
                <p>Conectamos clientes con trabajadores de confianza en todos los oficios.</p>
                <div class="hero-botones">
                    <a href="#profesionales" class="boton boton-lleno">Buscar profesionales</a>
                    <?php if ($link_publicar !== ""): ?>
                        <a href="<?php echo $link_publicar; ?>" class="boton boton-borde">Publicar servicio</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="seccion">
        <div class="contenedor">
            <h2 class="seccion-titulo" id="oficios">Oficios más buscados</h2>
            <div class="oficios-grid">
                <?php foreach ($oficios_destacados as $nombre_oficio => $icono): ?>
                    <!-- urlencode arma bien la dirección aunque el oficio tenga tildes o espacios -->
                    <a href="index.php?oficio=<?php echo urlencode($nombre_oficio); ?>#profesionales"
                       class="oficio-card <?php echo $oficio === $nombre_oficio ? "elegido" : ""; ?>">
                        <span class="icono"><?php echo $icono; ?></span>
                        <p><?php echo $nombre_oficio; ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <section class="seccion">
        <div class="contenedor">
            <div class="seccion-encabezado">
                <h2 class="seccion-titulo" id="profesionales"><?php echo htmlspecialchars($titulo_profesionales); ?></h2>
                <?php if ($buscar !== "" || $oficio !== ""): ?>
                    <a href="index.php#profesionales" class="ver-todos">✕ Ver todos</a>
                <?php endif; ?>
            </div>
            <div class="profesionales-grid">

                <?php if ($resultado_profesionales->num_rows === 0): ?>
                    <p class="sin-resultados">
                        <?php echo ($buscar !== "" || $oficio !== "")
                            ? "No encontramos profesionales con esa búsqueda."
                            : "Todavía no hay profesionales cargados."; ?>
                    </p>
                <?php endif; ?>

                <?php while ($profesional = $resultado_profesionales->fetch_assoc()): ?>
                    <div class="profesional-card">
                        <div class="profesional-avatar"><?php echo htmlspecialchars($profesional["iniciales"]); ?></div>
                        <div class="profesional-info">
                            <h3><?php echo htmlspecialchars($profesional["nombre"] . " " . $profesional["apellido"]); ?></h3>
                            <p class="oficio"><?php echo htmlspecialchars($profesional["oficio"]); ?></p>
                            <p class="valoracion">⭐ <?php echo $profesional["valoracion"]; ?> (<?php echo $profesional["cantidad_resenas"]; ?> reseñas)</p>
                            <p class="ubicacion">📍 <?php echo htmlspecialchars($profesional["ubicacion"] ?? ""); ?></p>
                            <?php if (!empty($profesional["telefono"])): ?>
                                <p class="telefono">📞 <?php echo htmlspecialchars($profesional["telefono"]); ?></p>
                            <?php endif; ?>
                            <a href="perfil.php?id=<?php echo $profesional["id"]; ?>" class="boton boton-borde">Ver perfil</a>
                        </div>
                    </div>
                <?php endwhile; ?>

            </div>
        </div>
    </section>
        <footer class="pie-pagina">
        <div class="contenedor">
            <div class="pie-confianza">
                <h2>Profesionales en los que podés confiar</h2>
                <ul>
                    <li>✓ Perfiles verificados</li>
                    <li>✓ Opiniones de clientes</li>
                    <li>✓ Ubicación y datos del profesional</li>
                    <li>✓ Contacto directo</li>
                    <li>✓ Compará diferentes opciones</li>
                </ul>
            </div>
            <div class="pie-ayuda">
                <p>¿Tenés dudas?</p>
                <a href="#" class="boton boton-lleno">Consultanos</a>
            </div>
        </div>
    </footer>

</body>

</html>
