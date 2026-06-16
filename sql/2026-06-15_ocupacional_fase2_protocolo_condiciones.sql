-- Fase 2 Salud Ocupacional: Condiciones por examen dentro de protocolo
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

CREATE TABLE IF NOT EXISTS ocupacional_protocolo_condiciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocolo_id INT UNSIGNED NOT NULL,
  catalogo_id INT UNSIGNED NOT NULL,
  puesto_trabajo VARCHAR(120) NULL,
  sexo ENUM('M','F') NULL,
  edad_min TINYINT UNSIGNED NULL,
  edad_max TINYINT UNSIGNED NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_cond_proto_catalogo (protocolo_id, catalogo_id),
  KEY idx_ocup_cond_puesto (puesto_trabajo),
  KEY idx_ocup_cond_sexo (sexo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
