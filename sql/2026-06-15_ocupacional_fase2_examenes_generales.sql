-- Fase 2 Salud Ocupacional: Maestro de examenes generales
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

CREATE TABLE IF NOT EXISTS ocupacional_examenes_generales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  descripcion VARCHAR(160) NOT NULL,
  grupo VARCHAR(100) NOT NULL DEFAULT '',
  subgrupo VARCHAR(100) NULL,
  valores_normales TEXT NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  posicion INT NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_examenes_codigo (codigo),
  KEY idx_ocup_examenes_estado (estado),
  KEY idx_ocup_examenes_descripcion (descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
