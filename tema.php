<?php
// Cambia entre modo claro y modo oscuro. No se ve: hace el cambio y vuelve.
// La elección se guarda en una cookie del navegador por un año,
// así la próxima vez que entre ya aparece con el modo que eligió.
// Cada página lee esa cookie y, si dice "oscuro", le pone la clase "oscuro" al <body>.

$actual = $_COOKIE["tema"] ?? "claro";
$nuevo  = $actual === "oscuro" ? "claro" : "oscuro";

// setcookie(nombre, valor, vencimiento, carpeta). time() es "ahora" en segundos.
setcookie("tema", $nuevo, time() + 60 * 60 * 24 * 365, "/");

// Volvemos a la página donde estaba. HTTP_REFERER es la dirección de la página anterior,
// por ejemplo http://localhost/manos_a_la_obra_con_css/perfil.php?id=2
// Nos quedamos solo con "perfil.php?id=2" para no mandar al usuario a otro sitio.
$volver = "index.php";
if (!empty($_SERVER["HTTP_REFERER"])) {
    $partes = parse_url($_SERVER["HTTP_REFERER"]);
    $pagina = basename($partes["path"] ?? "");
    if (substr($pagina, -4) === ".php") {
        $volver = $pagina . (isset($partes["query"]) ? "?" . $partes["query"] : "");
    }
}

header("Location: " . $volver);
exit;
