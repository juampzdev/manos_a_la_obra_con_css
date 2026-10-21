-- =========================================================
--  Base de datos de "Manos a la Obra"
--  En SQLyog: Database > Import > Execute SQL Script, o pegalo y ejecutá todo con Shift+F9.
--  OJO: borra las tablas si ya existían, así arrancás de cero.
--
--  Las cuentas se guardan en DOS tablas separadas:
--    - clientes:      los que buscan un trabajador
--    - profesionales: los que ofrecen un servicio (y su perfil)
-- =========================================================

CREATE DATABASE IF NOT EXISTS manos_a_la_obra
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE manos_a_la_obra;
SET NAMES utf8mb4;   -- para que las tildes de los datos de ejemplo se guarden bien

DROP TABLE IF EXISTS profesionales;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;   -- tabla de la versión anterior, ya no se usa

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
    ubicacion VARCHAR(100) NULL,
    telefono VARCHAR(30) NULL,
    experiencia INT NULL,
    fecha_alta DATE NULL,
    fecha_baja DATE NULL,
    valoracion DECIMAL(2,1) DEFAULT 0,
    cantidad_resenas INT DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Datos de ejemplo. La contraseña de todas estas cuentas es: 123456
-- ---------------------------------------------------------
INSERT INTO profesionales (nombre, apellido, email, password, iniciales, oficio, descripcion, ubicacion, telefono, experiencia, fecha_alta, valoracion, cantidad_resenas) VALUES
('Juan',   'Pérez', 'juan@ejemplo.com',   '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'JP', 'Electricista', 'Instalaciones domiciliarias y tableros.', 'Rosario Centro', '341 555-1111', 12, '2014-03-01', 4.8, 32),
('María',  'Gómez', 'maria@ejemplo.com',  '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'MG', 'Pintor',       'Pintura de interiores y exteriores.',    'Fisherton',      '341 555-2222',  8, '2018-06-15', 4.9, 21),
('Carlos', 'Ruiz',  'carlos@ejemplo.com', '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq', 'CR', 'Plomero',      'Destapaciones y arreglos de pérdidas.',  'Echesortu',      '341 555-3333', 15, '2011-01-10', 4.6, 18);

INSERT INTO clientes (nombre, apellido, email, password) VALUES
('Lucía', 'Fernández', 'lucia@ejemplo.com', '$2y$10$ioq8MZwfjHbqKlICL2TU7.Kb122ljeebjRXsbXTO5Z1s.r0jHowrq');
