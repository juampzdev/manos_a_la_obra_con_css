-- =========================================================
--  Base de datos de "Manos a la Obra"
--  En SQLyog: Database > Import > Execute SQL Script, o pegalo y ejecutá todo con Shift+F9.
--  OJO: borra las tablas si ya existían, así arrancás de cero.
--
--  Las cuentas se guardan en DOS tablas separadas:
--    - clientes:      los que buscan un trabajador
--    - profesionales: los que ofrecen un servicio (y su perfil)
--  Y una tercera tabla une a las dos:
--    - resenas:       la opinión que un cliente deja sobre un profesional
--  Además hay una tabla de apoyo:
--    - barrios:       los barrios de Rosario agrupados por zona, para "Profesionales cerca"
-- =========================================================

CREATE DATABASE IF NOT EXISTS manos_a_la_obra
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE manos_a_la_obra;
SET NAMES utf8mb4;   -- para que las tildes de los datos de ejemplo se guarden bien

-- Se borran en orden: primero las que apuntan a otras (resenas -> profesionales -> barrios)
DROP TABLE IF EXISTS resenas;
DROP TABLE IF EXISTS profesionales;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS barrios;
DROP TABLE IF EXISTS usuarios;   -- tabla de la versión anterior, ya no se usa

-- ---------------------------------------------------------
-- Barrios: cada barrio pertenece a una zona de la ciudad.
-- Sirve para la sección "Profesionales cerca" del inicio:
-- primero se muestran los del mismo barrio y después los de la misma zona.
-- ---------------------------------------------------------
CREATE TABLE barrios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barrio VARCHAR(60) NOT NULL UNIQUE,
    zona VARCHAR(30) NOT NULL
);

INSERT INTO barrios (barrio, zona) VALUES
('Centro', 'Centro'), ('Pichincha', 'Centro'), ('Abasto', 'Centro'),
('Echesortu', 'Centro'), ('Lourdes', 'Centro'), ('República de la Sexta', 'Centro'),
('Alberdi', 'Norte'), ('Arroyito', 'Norte'), ('Refinería', 'Norte'), ('La Florida', 'Norte'),
('Fisherton', 'Noroeste'), ('Ludueña', 'Noroeste'), ('Empalme Graneros', 'Noroeste'),
('Bella Vista', 'Oeste'), ('Godoy', 'Oeste'), ('Triángulo', 'Oeste'),
('Saladillo', 'Sur'), ('Tablada', 'Sur'), ('Las Flores', 'Sur');

-- ---------------------------------------------------------
-- Clientes: solo los datos de la cuenta
-- ---------------------------------------------------------
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    apellido VARCHAR(60) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- encriptada con password_hash()
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Profesionales: datos de la cuenta + perfil público
-- Los campos del perfil quedan vacíos (NULL) al crear la cuenta
-- y se completan después en postular.php
-- ---------------------------------------------------------
CREATE TABLE profesionales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Cuenta
    nombre VARCHAR(60) NOT NULL,
    apellido VARCHAR(60) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    iniciales VARCHAR(5) NOT NULL,
    -- Perfil (se completa en "Postular servicio")
    oficio VARCHAR(60) NULL,
    descripcion VARCHAR(300) NULL,
    ubicacion VARCHAR(100) NULL,             -- ciudad
    domicilio VARCHAR(120) NULL,             -- calle y número (no se muestra en la tarjeta)
    barrio_id INT NULL,                      -- en qué barrio trabaja (apunta a la tabla barrios)
    telefono VARCHAR(30) NULL,
    experiencia INT NULL,
    fecha_alta DATE NULL,
    fecha_baja DATE NULL,
    -- Promedio y cantidad de reseñas. Se recalculan solos cada vez que
    -- un cliente deja una reseña (ver perfil.php)
    valoracion DECIMAL(2,1) DEFAULT 0,
    cantidad_resenas INT DEFAULT 0,
    -- Plan Destacado (pago mensual): hasta qué día aparece primero y con la insignia.
    -- NULL = no tiene el plan. Si la fecha ya pasó, el plan venció y vuelve a ser normal.
    destacado_hasta DATE NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barrio_id) REFERENCES barrios(id)
);

-- ---------------------------------------------------------
-- Reseñas: cada fila es la opinión de UN cliente sobre UN profesional.
-- FOREIGN KEY: profesional_id y cliente_id tienen que existir en sus tablas.
-- ON DELETE CASCADE: si se borra el profesional o el cliente, se borran sus reseñas.
-- UNIQUE (profesional_id, cliente_id): un cliente deja una sola reseña por profesional.
-- ---------------------------------------------------------
CREATE TABLE resenas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    cliente_id INT NOT NULL,
    puntaje TINYINT NOT NULL,                -- de 1 a 5 estrellas
    comentario VARCHAR(300) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    UNIQUE (profesional_id, cliente_id)
);

-- ---------------------------------------------------------
-- Datos de ejemplo. La contraseña de todas estas cuentas es: 123456
-- ---------------------------------------------------------
-- barrio_id se busca por nombre con una subconsulta, así no hay que saber el número de memoria
INSERT INTO profesionales (nombre, apellido, email, password, iniciales, oficio, descripcion, ubicacion, domicilio, barrio_id, telefono, experiencia, fecha_alta) VALUES
('Juan',   'Pérez',    'juan@ejemplo.com',   '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'JP', 'Electricista', 'Instalaciones domiciliarias y tableros.', 'Rosario', 'Córdoba 1250',   (SELECT id FROM barrios WHERE barrio = 'Centro'),    '341 555-1111', 12, '2014-03-01'),
('María',  'Gómez',    'maria@ejemplo.com',  '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'MG', 'Pintor',       'Pintura de interiores y exteriores.',    'Rosario', 'Eva Perón 8100', (SELECT id FROM barrios WHERE barrio = 'Fisherton'), '341 555-2222',  8, '2018-06-15'),
('Carlos', 'Ruiz',     'carlos@ejemplo.com', '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'CR', 'Plomero',      'Destapaciones y arreglos de pérdidas.',  'Rosario', 'Mendoza 3500',   (SELECT id FROM barrios WHERE barrio = 'Echesortu'), '341 555-3333', 15, '2011-01-10'),
('Ana',    'López',    'ana@ejemplo.com',    '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'AL', 'Gasista',      'Instalaciones de gas y service de calefones.', 'Rosario', 'Junín 5200', (SELECT id FROM barrios WHERE barrio = 'Ludueña'),   '341 555-4444',  6, '2020-02-01'),
('Diego',  'Martínez', 'diego@ejemplo.com',  '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'DM', 'Carpintero',   'Muebles a medida y arreglo de aberturas.', 'Rosario', 'Alberdi 600',   (SELECT id FROM barrios WHERE barrio = 'Arroyito'),  '341 555-5555', 10, '2016-09-01');

INSERT INTO clientes (nombre, apellido, email, password) VALUES
('Lucía', 'Fernández', 'lucia@ejemplo.com', '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq'),
('Martín', 'Sosa',     'martin@ejemplo.com', '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq');

-- profesional_id y cliente_id son los id de las filas de arriba (Juan = 1, Lucía = 1, etc.)
INSERT INTO resenas (profesional_id, cliente_id, puntaje, comentario) VALUES
(1, 1, 5, 'Vino en el día y me dejó el tablero funcionando. Muy prolijo.'),
(1, 2, 4, 'Buen trabajo, llegó un poco tarde pero avisó.'),
(2, 1, 5, 'Pintó todo el living en dos días y dejó todo limpio.'),
(3, 2, 4, 'Solucionó la pérdida del baño rápido y a buen precio.');

-- Carlos tiene el plan Destacado por un mes desde hoy, para ver cómo funciona.
-- DATE_ADD(CURDATE(), INTERVAL 1 MONTH) = la fecha de hoy más un mes
UPDATE profesionales SET destacado_hasta = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
WHERE email = 'carlos@ejemplo.com';

-- Calculamos el promedio y la cantidad a partir de las reseñas cargadas
UPDATE profesionales
SET valoracion       = (SELECT IFNULL(ROUND(AVG(puntaje), 1), 0) FROM resenas WHERE resenas.profesional_id = profesionales.id),
    cantidad_resenas = (SELECT COUNT(*) FROM resenas WHERE resenas.profesional_id = profesionales.id);
