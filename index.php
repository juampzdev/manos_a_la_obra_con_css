<?php
// session_start() tiene que ir SIEMPRE antes de imprimir cualquier HTML,
// es lo que nos permite leer si hay un usuario logueado ($_SESSION)
session_start();
require "config.php";

// Traemos los profesionales desde la base de datos en vez de tenerlos
// escritos a mano en el HTML como antes
// Solo los que ya completaron su perfil (tienen oficio) y siguen en actividad
// (sin fecha de baja o con baja a futuro),
// ordenados por mejor valoración
$resultado_profesionales = $conexion->query(
    "SELECT * FROM profesionales
     WHERE oficio IS NOT NULL
       AND (fecha_baja IS NULL OR fecha_baja >= CURDATE())
     ORDER BY valoracion DESC, cantidad_resenas DESC"
);

// A dónde lleva el botón "Publicar servicio" según quién esté mirando
if (!isset($_SESSION["usuario_id"])) {
    $link_publicar = "registro.php";              // sin cuenta: que se registre
} elseif ($_SESSION["usuario_tipo"] === "profesional") {
    $link_publicar = "postular.php";              // profesional: a su perfil
} else {
    $link_publicar = "";                          // cliente: no publica
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

<body>

    <header class="header-principal">
        <div class="barra-superior">
            <a href="index.php" class="logo">
                <span class="logo-icono">🏠</span>
                <span class="logo-texto"><strong>Manos</strong><span>a la Obra</span></span>
            </a>
            <div class="buscador">
                <input type="text" placeholder="¿Qué servicio necesitás?">
                <button>🔍</button>
            </div>
            <div class="acciones">
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
            <a href="#" class="activo">Inicio</a>
            <a href="#">Oficios</a>
            <a href="#profesionales">Profesionales</a>
            <a href="#">Cómo funciona</a>
            <a href="#">Contacto</a>
        </nav>
    </header>


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
            <h2 class="seccion-titulo">Oficios más buscados</h2>
            <div class="oficios-grid">
                <div class="oficio-card"><span class="icono">⚡</span>
                    <p>Electricista</p>
                </div>
                <div class="oficio-card"><span class="icono">🔥</span>
                    <p>Gasista</p>
                </div>
                <div class="oficio-card"><span class="icono">🚰</span>
                    <p>Plomero</p>
                </div>
                <div class="oficio-card"><span class="icono">🎨</span>
                    <p>Pintor</p>
                </div>
                <div class="oficio-card"><span class="icono">🔨</span>
                    <p>Carpintero</p>
                </div>
                <div class="oficio-card"><span class="icono">🧱</span>
                    <p>Albañil</p>
                </div>
            </div>
        </div>
    </section>


    <section class="seccion">
        <div class="contenedor">
            <h2 class="seccion-titulo" id="profesionales">Profesionales destacados</h2>
            <div class="profesionales-grid">

                <?php if ($resultado_profesionales->num_rows === 0): ?>
                    <p>Todavía no hay profesionales cargados.</p>
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
                            <a href="#" class="boton boton-borde">Ver perfil</a>
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
