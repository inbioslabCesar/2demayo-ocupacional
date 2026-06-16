-- Fase 2 Salud Ocupacional: Catalogo de examenes por empresa
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

CREATE TABLE IF NOT EXISTS ocupacional_catalogo_empresas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id INT UNSIGNED NOT NULL,
  examen_id INT UNSIGNED NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_catalogo_empresa_examen (empresa_id, examen_id),
  KEY idx_ocup_catalogo_empresa (empresa_id),
  KEY idx_ocup_catalogo_examen (examen_id),
  KEY idx_ocup_catalogo_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
