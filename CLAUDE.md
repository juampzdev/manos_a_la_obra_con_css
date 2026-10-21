# Manos a la Obra — contexto para Claude

Proyecto de un estudiante de **Analista Funcional en Sistemas**, materia **Práctica Profesionalizante**.
Sitio que conecta clientes con trabajadores de oficios. Ver `README.md` para archivos, tablas y puesta en marcha.

## Tecnologías: SOLO estas

- **HTML, CSS, PHP y MySQL**, corriendo en **XAMPP**. La base se maneja con **SQLyog**.
- No agregar frameworks, librerías, Composer, npm, Bootstrap, Tailwind, jQuery ni JavaScript nuevo.
  El único JS que existe es el contador de caracteres de `postular.php`.
- Herramientas que uses solo para probar en tu entorno (otro motor de base, navegadores
  automatizados, scripts) no se suben al repositorio ni se mencionan como parte del proyecto.
- El estudiante tiene que poder explicar cada línea: código simple, sin abstracciones de más.

## Cómo está escrito el código

- Comentarios y textos en **español rioplatense** (voseo: "Completá", "Ingresá").
- Comentarios que explican el *por qué*, en tono didáctico, como en el resto de los archivos.
- PHP mezclado con HTML, con la sintaxis `if (...): ... endif;` / `while (...): ... endwhile;`.
- Base de datos con **mysqli** y siempre **consultas preparadas** (`prepare` + `bind_param`) cuando hay datos del usuario.
- Todo lo que se imprime y viene del usuario o de la base pasa por `htmlspecialchars()`.
- Contraseñas con `password_hash()` / `password_verify()`.
- Sesión: `$_SESSION["usuario_id"]` siempre va con `$_SESSION["usuario_tipo"]` (`"cliente"` o `"profesional"`),
  porque cada tabla numera sus filas por separado.
- Todos los estilos en **un solo** `style.css`, con las variables de color de `:root`
  y un bloque `@media (max-width: 760px)` para celular.

## Base de datos

`manos_a_la_obra` (utf8mb4), definida en `database.sql`: tablas `clientes`, `profesionales`, `resenas` y `barrios`.
Si cambiás la estructura, actualizá `database.sql` (es lo que el estudiante ejecuta en SQLyog)
y avisale que tiene que volver a ejecutarlo.

## Entorno del estudiante

Windows con XAMPP. La carpeta es `C:\xampp\htdocs\manos_a_la_obra_con_css\` y se entra por
`http://localhost/manos_a_la_obra_con_css/index.php`. Trae los cambios con `git pull`.
