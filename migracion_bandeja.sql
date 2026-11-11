-- Ejecutar una sola vez sobre manos_a_la_obra para agregar la bandeja de entrada
-- sin borrar las cuentas ni los demás datos que ya existen.
-- (Si preferís arrancar de cero, alcanza con volver a ejecutar database.sql.)
USE manos_a_la_obra;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_remitente VARCHAR(12) NOT NULL,
    remitente_id INT NULL,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    asunto VARCHAR(60) NOT NULL,
    mensaje VARCHAR(2000) NOT NULL,
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mensaje_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensaje_id INT NOT NULL,
    archivo VARCHAR(60) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    FOREIGN KEY (mensaje_id) REFERENCES mensajes(id) ON DELETE CASCADE
);

-- La cuenta del equipo (INSERT IGNORE: si ya existe, no hace nada)
INSERT IGNORE INTO administradores (nombre, email, password) VALUES
('Administración', 'iseiclaude@gmail.com', '$2y$10$p2ZzoZ5AicfAvKKOXYfJFOnp1dnK9F8UAUHSltBw3LZps7t9ogPDm');
