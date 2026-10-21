# 🏠 Manos a la Obra

Sitio que conecta a clientes con trabajadores de oficios (electricistas, plomeros, gasistas, etc.).
Cualquiera puede ver y buscar a los profesionales publicados. Para publicarse, un profesional se crea
una cuenta y completa su perfil. Los clientes pueden dejar reseñas.

Proyecto de la materia **Práctica Profesionalizante** (Analista Funcional en Sistemas).

## Tecnologías

| Herramienta | Para qué se usa |
|---|---|
| HTML | Estructura de cada página |
| CSS | Todo el aspecto visual, en un solo archivo: `style.css` |
| PHP | Lee y guarda datos en la base, maneja la sesión |
| MySQL (XAMPP) | Base de datos `manos_a_la_obra` |
| SQLyog | Ver y editar la base de forma visual |

## Archivos

| Archivo | Para qué sirve |
|---|---|
| `index.php` | Inicio. Muestra los oficios y los profesionales. Tiene el buscador, el filtro por oficio y la sección "Profesionales cerca". |
| `tarjeta_profesional.php` | No se abre sola. Es la tarjeta de un profesional que `index.php` incluye en sus dos grillas. |
| `perfil.php` | Perfil público de un profesional, con sus reseñas y el formulario para dejar una. |
| `tema.php` | No se ve. Cambia entre modo claro y oscuro y guarda la elección en una cookie. |
| `registro.php` | Formulario para crear una cuenta (cliente o profesional). |
| `guardar.php` | No se ve. Guarda la cuenta en la tabla `clientes` o `profesionales`. |
| `login.php` | Formulario para ingresar. Busca la cuenta en las dos tablas. |
| `logout.php` | No se ve. Cierra la sesión. |
| `postular.php` | El profesional completa o edita su perfil. |
| `config.php` | Datos de conexión a la base. Lo incluyen todas las páginas. |
| `style.css` | Todos los estilos del sitio. |
| `database.sql` | Crea la base con las tablas `clientes`, `profesionales`, `resenas` y `barrios`, y carga datos de ejemplo. |

## Base de datos

- **clientes**: datos de la cuenta.
- **profesionales**: datos de la cuenta y el perfil público (oficio, descripción, ciudad, domicilio,
  barrio, teléfono, etc.). El domicilio no se muestra en la página: solo el barrio y la ciudad.
- **barrios**: los barrios de Rosario con su zona (Centro, Norte, Noroeste, Oeste, Sur).
  "Profesionales cerca" muestra primero los del barrio elegido y después los de la misma zona.
- **resenas**: la opinión de un cliente sobre un profesional (puntaje de 1 a 5 y comentario).
  Un cliente puede dejar una sola reseña por profesional. Cada vez que se agrega una,
  se recalculan `valoracion` y `cantidad_resenas` en la tabla `profesionales`.

## Puesta en marcha

1. Copiar la carpeta en `C:\xampp\htdocs\manos_a_la_obra_con_css\`.
2. En el panel de XAMPP, prender **Apache** y **MySQL**.
3. En SQLyog, conectarse a `localhost` con usuario `root` y contraseña vacía.
4. Ejecutar `database.sql` completo (**Shift+F9**, no F9). Al refrescar con F5 tienen que aparecer
   las tablas `clientes`, `profesionales`, `resenas` y `barrios`.
5. Entrar a **http://localhost/manos_a_la_obra_con_css/index.php**.
   Siempre por el navegador, nunca con doble clic en el archivo.

> ⚠️ `database.sql` borra las tablas si ya existían: al volver a ejecutarlo se pierden las cuentas
> creadas y quedan solo las de ejemplo.

## Cuentas de ejemplo

Todas tienen la contraseña **123456**.

| Tipo | Email |
|---|---|
| Profesional | `juan@ejemplo.com` (Electricista, Centro) |
| Profesional | `maria@ejemplo.com` (Pintor, Fisherton) |
| Profesional | `carlos@ejemplo.com` (Plomero, Echesortu) |
| Profesional | `ana@ejemplo.com` (Gasista, Ludueña) |
| Profesional | `diego@ejemplo.com` (Carpintero, Arroyito) |
| Cliente | `lucia@ejemplo.com` |
| Cliente | `martin@ejemplo.com` |

## Errores comunes

| Lo que aparece | Qué revisar |
|---|---|
| `Unknown database 'manos_a_la_obra'` | No se ejecutó `database.sql`. |
| `Table ... doesn't exist` o `bind_param() on bool` | El script se ejecutó a medias o es una versión vieja. Volver a ejecutarlo con Shift+F9. |
| Se ve el código PHP | Se abrió con doble clic. Entrar por `http://localhost/...`. |
| La página no carga | Apache o MySQL apagados en XAMPP. |
| La página se ve sin estilos | `style.css` no está en la misma carpeta que los `.php`. |
