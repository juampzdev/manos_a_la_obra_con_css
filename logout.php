<?php
// Cierra la sesión y vuelve al inicio. No se ve: hace el trabajo y redirige.
session_start();     // hay que "abrir" la sesión para poder cerrarla
session_unset();     // borra los datos guardados ($_SESSION queda vacío)
session_destroy();   // elimina la sesión del servidor
header("Location: index.php");
exit;
