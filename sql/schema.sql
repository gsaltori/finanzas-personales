CREATE DATABASE IF NOT EXISTS finanzas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finanzas;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  preferencias JSON NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  tipo ENUM('ingreso','gasto') NOT NULL,
  usuario_id INT NULL,
  presupuesto DECIMAL(12,2) DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE transacciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha DATE NOT NULL,
  categoria_id INT NULL,
  descripcion VARCHAR(500),
  tipo ENUM('ingreso','gasto') NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
  INDEX idx_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE presupuestos (
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

CREATE TABLE metas_ahorro (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  objetivo DECIMAL(12,2) NOT NULL,
  actual DECIMAL(12,2) DEFAULT 0,
  fecha_limite DATE NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Datos globales de ejemplo
INSERT INTO categorias (nombre,tipo,usuario_id,presupuesto) VALUES
('Alimentación','gasto',NULL,0),
('Transporte','gasto',NULL,0),
('Entretenimiento','gasto',NULL,0),
('Salario','ingreso',NULL,0),
('Otros ingresos','ingreso',NULL,0);
