-- Tablas ya propuestas en schema.sql; aquí aseguramos columnas y algunos índices útiles.
USE finanzas;

-- Presupuestos: si ya existe, omitir
CREATE TABLE IF NOT EXISTS presupuestos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  categoria_id INT NOT NULL,
  monto_maximo DECIMAL(12,2) NOT NULL,
  mes TINYINT NOT NULL,
  anio SMALLINT NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
  UNIQUE KEY uk_presupuesto_categoria_mes (usuario_id, categoria_id, mes, anio)
) ENGINE=InnoDB;

CREATE INDEX idx_pres_usuario_fecha ON presupuestos (usuario_id, anio, mes);

-- Metas de ahorro: si ya existe, omitir
CREATE TABLE IF NOT EXISTS metas_ahorro (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  objetivo DECIMAL(12,2) NOT NULL,
  actual DECIMAL(12,2) DEFAULT 0,
  fecha_limite DATE NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Datos de ejemplo (reemplazar user id 1 por el demo real)
INSERT IGNORE INTO presupuestos (usuario_id,categoria_id,monto_maximo,mes,anio)
SELECT 1, c.id, 200.00, MONTH(CURDATE()), YEAR(CURDATE()) FROM categorias c WHERE c.nombre = 'Alimentación' LIMIT 1;

INSERT IGNORE INTO metas_ahorro (usuario_id,nombre,objetivo,actual,fecha_limite)
VALUES (1, 'Fondo Emergencia', 100000.00, 12000.00, DATE_ADD(CURDATE(), INTERVAL 6 MONTH));
