-- Ejecutar una sola vez sobre manos_a_la_obra para habilitar varios servicios
-- sin eliminar cuentas ni otros datos existentes.
CREATE TABLE IF NOT EXISTS profesional_oficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    oficio VARCHAR(60) NOT NULL,
    UNIQUE KEY profesional_un_oficio (profesional_id, oficio),
    FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
);

-- Copia a la tabla nueva el oficio principal que ya tiene cada profesional.
INSERT IGNORE INTO profesional_oficios (profesional_id, oficio)
SELECT id, oficio FROM profesionales WHERE oficio IS NOT NULL;
