<?php
// Tarjeta de un profesional. La usan las dos grillas del inicio
// ("Profesionales cerca" y "Profesionales destacados"), así el HTML está en un solo lugar.
// No se abre sola: index.php la incluye con include "tarjeta_profesional.php";
// Antes de incluirla, la página tiene que tener:
//   $profesional -> la fila del profesional (con la columna "barrio" del JOIN)
//   $etiqueta    -> texto opcional arriba del nombre, por ejemplo "En tu barrio"

// Si alguien la abre directo en el navegador, no hay profesional para mostrar
if (!isset($profesional)) {
    exit;
}
?>
<!-- Si tiene el plan Destacado, la tarjeta lleva la clase "destacada" y el borde se pinta de naranja -->
<div class="profesional-card <?php echo !empty($profesional["destacado"]) ? "destacada" : ""; ?>">
    <div class="profesional-avatar"><?php echo htmlspecialchars($profesional["iniciales"]); ?></div>
    <div class="profesional-info">
        <?php if ($etiqueta !== ""): ?>
            <span class="etiqueta-cerca"><?php echo htmlspecialchars($etiqueta); ?></span>
        <?php endif; ?>
        <?php if (!empty($profesional["destacado"])): ?>
            <span class="insignia-destacado">⭐ Destacado</span>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($profesional["nombre"] . " " . $profesional["apellido"]); ?></h3>
        <p class="oficio"><?php echo htmlspecialchars($profesional["oficio"]); ?></p>
        <!-- "1 reseña" en singular y "2 reseñas" en plural -->
        <p class="valoracion">⭐ <?php echo $profesional["valoracion"]; ?>
            (<?php echo $profesional["cantidad_resenas"] . ($profesional["cantidad_resenas"] == 1 ? " reseña" : " reseñas"); ?>)</p>
        <!-- Se muestra el barrio y la ciudad, nunca el domicilio exacto -->
        <p class="ubicacion">📍 <?php
            if (!empty($profesional["barrio"])) {
                echo htmlspecialchars($profesional["barrio"]) . ", ";
            }
            echo htmlspecialchars($profesional["ubicacion"] ?? "");
        ?></p>
        <?php if (!empty($profesional["telefono"])): ?>
            <p class="telefono">📞 <?php echo htmlspecialchars($profesional["telefono"]); ?></p>
        <?php endif; ?>
        <a href="perfil.php?id=<?php echo $profesional["id"]; ?>" class="boton boton-borde">Ver perfil</a>
    </div>
</div>
