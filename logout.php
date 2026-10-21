<?php
// Cierra la sesión y vuelve al inicio
session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit;
