CREATE TABLE IF NOT EXISTS audit_trx (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entidad VARCHAR(50) NOT NULL,        -- e.g., transaccion
  entidad_id INT NOT NULL,             -- id de la transacción
  usuario_id INT NULL,                 -- quien hizo el cambio
  accion VARCHAR(30) NOT NULL,         -- update/create/delete
  antes JSON NULL,                     -- estado anterior
  despues JSON NULL,                   -- estado posterior
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entidad (entidad, entidad_id),
  INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
