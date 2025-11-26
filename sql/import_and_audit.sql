-- Ejecutar en la base de datos "finanzas"

-- 1) Tabla audit_trx (si no existe)
CREATE TABLE IF NOT EXISTS audit_trx (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entidad VARCHAR(50) NOT NULL,
  entidad_id INT NOT NULL,
  usuario_id INT NULL,
  accion VARCHAR(30) NOT NULL,
  antes JSON NULL,
  despues JSON NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entidad (entidad, entidad_id),
  INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Tabla import_jobs para deduplicación persistente (opción B)
CREATE TABLE IF NOT EXISTS import_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  origen VARCHAR(255) NULL,          -- nombre de archivo o descripción
  file_hash VARCHAR(64) NOT NULL,    -- hash del archivo o contenido
  filas_total INT DEFAULT 0,
  filas_importadas INT DEFAULT 0,
  estado ENUM('pending','done','error') DEFAULT 'pending',
  meta JSON NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario_id),
  UNIQUE KEY uk_usuario_filehash (usuario_id, file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Tabla import_rows para registrar cada fila (opcional, útil para auditoría/import preview)
CREATE TABLE IF NOT EXISTS import_rows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  fila_hash VARCHAR(64) NOT NULL,     -- hash de fecha|desc|monto
  fecha DATE NULL,
  descripcion VARCHAR(512) NULL,
  canal VARCHAR(255) NULL,
  monto DECIMAL(12,2) NULL,
  tipo ENUM('gasto','ingreso') NULL,
  saldo DECIMAL(12,2) NULL,
  estado ENUM('new','skipped','imported','error') DEFAULT 'new',
  detalle TEXT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES import_jobs(id) ON DELETE CASCADE,
  INDEX idx_job (job_id),
  UNIQUE KEY uk_job_fila (job_id, fila_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
