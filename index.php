<?php
// Página de inicio. De arriba hacia abajo tiene:
//   1. Encabezado: logo, buscador, botones de cuenta y menú.
//   2. Aviso "Completá tu perfil" (solo para profesionales sin perfil).
//   3. Portada (hero) con los botones principales.
//   4. Oficios más buscados: cada tarjeta filtra por su oficio.
//   5. Profesionales cerca: el visitante elige su barrio.
//   6. Profesionales destacados (o los resultados de la búsqueda / del filtro).
//   7. Pie de página.
// Primero, en PHP, se buscan todos los datos; después viene el HTML que los muestra.

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

// ---------- Profesionales (sección 6) ----------
// Solo los que ya completaron su perfil (tienen oficio) y siguen en actividad
// (sin fecha de baja o con baja a futuro).
// Si hay una búsqueda o un oficio elegido, se agrega esa condición.
// LEFT JOIN trae el nombre del barrio desde la tabla barrios. Es LEFT para no dejar
// afuera a los profesionales que todavía no eligieron barrio (barrio_id vacío).
// La columna "destacado" la armamos en la consulta: vale 1 si tiene el plan Destacado
// vigente (fecha de hoy o futura) y 0 si no lo tiene o ya venció.
// La columna "mejor_valorado" también la armamos acá: vale 1 si tiene promedio de 4.5 o más
// Y al menos 3 reseñas. Pedimos 3 reseñas para que no alcance con una sola de 5 estrellas.
// A diferencia del plan Destacado, esto no se paga: se gana con las reseñas de los clientes.
$sql = "SELECT profesionales.*, barrios.barrio,
               (destacado_hasta IS NOT NULL AND destacado_hasta >= CURDATE()) AS destacado,
               (valoracion >= 4.5 AND cantidad_resenas >= 3) AS mejor_valorado
        FROM profesionales
        LEFT JOIN barrios ON barrios.id = profesionales.barrio_id
        WHERE oficio IS NOT NULL
          AND (fecha_baja IS NULL OR fecha_baja >= CURDATE())";
$tipos = "";
$valores = [];

if ($oficio !== "") {
    $sql .= " AND (profesionales.oficio = ? OR EXISTS (SELECT 1 FROM profesional_oficios po WHERE po.profesional_id = profesionales.id AND po.oficio = ?))";
    $tipos .= "ss";
    $valores[] = $oficio;
    $valores[] = $oficio;
}
if ($buscar !== "") {
    // LIKE con % busca el texto en cualquier parte: "pint" encuentra "Pintor"
    $sql .= " AND (oficio LIKE ? OR EXISTS (SELECT 1 FROM profesional_oficios po WHERE po.profesional_id = profesionales.id AND po.oficio LIKE ?) OR nombre LIKE ? OR apellido LIKE ? OR descripcion LIKE ?
              OR ubicacion LIKE ? OR barrios.barrio LIKE ?)";
    $patron = "%" . $buscar . "%";
    $tipos .= "sssssss";
    array_push($valores, $patron, $patron, $patron, $patron, $patron, $patron, $patron);
}
// Primero los del plan Destacado (1 antes que 0), después los que tienen
// la insignia "Mejor valorado" y al final el resto, de mayor a menor valoración
$sql .= " ORDER BY destacado DESC, mejor_valorado DESC, valoracion DESC, cantidad_resenas DESC";

$consulta = $conexion->prepare($sql);
if ($tipos !== "") {
    // ...$valores pasa cada elemento del array como un dato aparte
    $consulta->bind_param($tipos, ...$valores);
}
$consulta->execute();
$resultado_profesionales = $consulta->get_result();
$profesionales_lista = [];
while ($fila_profesional = $resultado_profesionales->fetch_assoc()) {
    $profesionales_lista[$fila_profesional["id"]] = $fila_profesional;
}
if ($profesionales_lista) {
    $ids_lista = implode(",", array_map("intval", array_keys($profesionales_lista)));
    $resultado_oficios = $conexion->query("SELECT profesional_id, oficio FROM profesional_oficios WHERE profesional_id IN ($ids_lista) ORDER BY id");
    while ($fila_oficio = $resultado_oficios->fetch_assoc()) {
        $id_p = $fila_oficio["profesional_id"];
        $lista = $profesionales_lista[$id_p]["oficios_lista"] ?? $profesionales_lista[$id_p]["oficio"];
        if ($fila_oficio["oficio"] !== $profesionales_lista[$id_p]["oficio"]) $lista .= ", " . $fila_oficio["oficio"];
        $profesionales_lista[$id_p]["oficios_lista"] = $lista;
    }
}
$profesionales_lista = array_values($profesionales_lista);

// Título de la sección según lo que se esté mostrando
if ($oficio !== "") {
    $titulo_profesionales = $oficio;
} elseif ($buscar !== "") {
    $titulo_profesionales = "Resultados para \"" . $buscar . "\"";
} else {
    $titulo_profesionales = "Profesionales destacados";
}

// ---------- Profesionales cerca (sección 5) ----------
// La lista de barrios para elegir sale de la tabla barrios.
// ORDER BY barrio los trae en orden alfabético, así el visitante encuentra el suyo más rápido.
$barrios = [];
$resultado_barrios = $conexion->query("SELECT id, barrio, zona FROM barrios ORDER BY barrio");
while ($fila = $resultado_barrios->fetch_assoc()) {
    $barrios[$fila["id"]] = $fila;
}

// El barrio elegido llega por la dirección: index.php?barrio=11
$barrio_elegido = (int) ($_GET["barrio"] ?? 0);
$cercanos = [];
if (isset($barrios[$barrio_elegido])) {
    // Solo los de la MISMA ZONA que el barrio elegido.
    // Primero van los que tienen el plan Destacado. Después,
    // (barrios.id = ?) DESC pone primero a los del mismo barrio:
    // la comparación da 1 si es el mismo barrio y 0 si no, y DESC ordena de mayor a menor.
    // Dentro del mismo barrio, primero los "Mejor valorado" (misma regla que arriba).
    // LIMIT 6 trae como mucho seis.
    $consulta_cerca = $conexion->prepare(
        "SELECT profesionales.*, barrios.barrio,
                (destacado_hasta IS NOT NULL AND destacado_hasta >= CURDATE()) AS destacado,
                (valoracion >= 4.5 AND cantidad_resenas >= 3) AS mejor_valorado
         FROM profesionales
         JOIN barrios ON barrios.id = profesionales.barrio_id
         WHERE oficio IS NOT NULL
           AND (fecha_baja IS NULL OR fecha_baja >= CURDATE())
           AND barrios.zona = ?
         ORDER BY destacado DESC, (barrios.id = ?) DESC, mejor_valorado DESC, valoracion DESC
         LIMIT 6"
    );
    $zona_elegida = $barrios[$barrio_elegido]["zona"];
    $consulta_cerca->bind_param("si", $zona_elegida, $barrio_elegido);
    $consulta_cerca->execute();
    $cercanos = $consulta_cerca->get_result()->fetch_all(MYSQLI_ASSOC);
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

// Personalización del banner según el rol de la sesión.
$tipo_usuario = $_SESSION["usuario_tipo"] ?? "";

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
<body id="arriba" class="<?php echo $modo_oscuro ? "oscuro" : ""; ?>">

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
            <a href="#cerca">Cerca de vos</a>
            <a href="#profesionales">Profesionales</a>
            <a href="como_funciona.php">Cómo funciona</a>
            <a href="contacto.php">Contacto</a>
        </nav>
    </header>

    <?php if (isset($_GET["bienvenida"]) && isset($_SESSION["usuario_nombre"])): ?>
        <!-- Aviso de cuenta recién creada: guardar.php nos manda con ?bienvenida=1 si salió el mail -->
        <div class="aviso-bienvenida">
            <div class="contenedor">
                <p>✅ <strong>¡Tu cuenta está lista!</strong>
                    <?php if ($_GET["bienvenida"] === "1"): ?>
                        Te mandamos un correo de bienvenida. Si no lo ves, revisá la carpeta de spam.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

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
                <?php if ($tipo_usuario === "profesional"): ?>
                    <h1>Conectá con más clientes para tus próximos trabajos</h1>
                    <p>Mostrá tus servicios y hacé crecer tu trabajo con clientes de tu zona.</p>
                <?php elseif ($tipo_usuario === "cliente"): ?>
                    <h1>Encontrá a la persona indicada para tu próximo trabajo</h1>
                    <p>Buscá trabajadores de confianza para resolver lo que necesitás.</p>
                <?php else: ?>
                    <h1>Encontrá a la persona indicada para tu próximo trabajo</h1>
                    <p>Conectamos clientes con trabajadores de confianza en todos los oficios.</p>
                <?php endif; ?>
                <div class="hero-botones">
                    <?php if ($tipo_usuario === "profesional"): ?>
                        <a href="postular.php" class="boton boton-lleno"><?php echo $falta_perfil ? "Completar mi perfil" : "Ver mi perfil"; ?></a>
                    <?php elseif ($tipo_usuario === "cliente"): ?>
                        <a href="#profesionales" class="boton boton-lleno">Buscar profesionales</a>
                    <?php else: ?>
                        <a href="#profesionales" class="boton boton-lleno">Buscar profesionales</a>
                        <a href="registro.php" class="boton boton-borde">Publicar servicio</a>
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

    <!-- Profesionales cerca: el visitante elige su barrio y le mostramos los de su zona -->
    <section class="seccion seccion-cerca">
        <div class="contenedor">
            <h2 class="seccion-titulo" id="cerca">Profesionales cerca</h2>

            <form class="elegir-barrio" action="index.php#cerca" method="get">
                <label for="barrio">¿En qué barrio estás?</label>
                <select id="barrio" name="barrio" required>
                    <option value="" disabled <?php echo isset($barrios[$barrio_elegido]) ? "" : "selected"; ?>>Elegí tu barrio</option>
                    <?php foreach ($barrios as $id_barrio => $b): ?>
                        <option value="<?php echo $id_barrio; ?>" <?php echo $barrio_elegido === $id_barrio ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($b["barrio"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="boton boton-lleno">Ver cercanos</button>
            </form>

            <?php if (isset($barrios[$barrio_elegido])): ?>
                <?php if (count($cercanos) === 0): ?>
                    <p class="sin-resultados">Todavía no hay profesionales en la zona <?php echo htmlspecialchars($barrios[$barrio_elegido]["zona"]); ?>.</p>
                <?php else: ?>
                    <div class="profesionales-grid">
                        <?php foreach ($cercanos as $profesional): ?>
                            <?php
                            // La etiqueta dice si está en el mismo barrio o solo en la misma zona
                            $etiqueta = $profesional["barrio_id"] == $barrio_elegido
                                ? "🏠 En tu barrio"
                                : "📍 Zona " . $barrios[$barrio_elegido]["zona"];
                            include "tarjeta_profesional.php";
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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

                <?php if (count($profesionales_lista) === 0): ?>
                    <p class="sin-resultados">
                        <?php echo ($buscar !== "" || $oficio !== "")
                            ? "No encontramos profesionales con esa búsqueda."
                            : "Todavía no hay profesionales cargados."; ?>
                    </p>
                <?php endif; ?>

                <?php foreach ($profesionales_lista as $profesional): ?>
                    <?php
                    $etiqueta = "";   // en esta grilla las tarjetas no llevan etiqueta
                    include "tarjeta_profesional.php";
                    ?>
                <?php endforeach; ?>

            </div>
        </div>
    </section>

    <footer class="pie-pagina">
        <div class="contenedor">
            <div class="pie-confianza">
                <h2>Profesionales en los que podés confiar</h2>
                <ul>
                    <li>✓ Profesionales de tu zona</li>
                    <li>✓ Opiniones de clientes</li>
                    <li>✓ Ubicación y datos del profesional</li>
                    <li>✓ Contacto directo</li>
                    <li>✓ Compará diferentes opciones</li>
                </ul>
            </div>
            <div class="pie-ayuda">
                <p>¿Tenés dudas?</p>
                <a href="contacto.php" class="boton boton-lleno">Consultanos</a>
            </div>
        </div>
    </footer>

    <!-- Botón para volver arriba. Lleva al id="arriba" del <body>.
         Aparece recién al bajar cerca de la mitad de la página: lo hace style.css (parte 17), sin JavaScript -->
    <a href="#arriba" class="boton-subir" aria-label="Volver arriba" title="Volver arriba">↑</a>

</body>

</html>
