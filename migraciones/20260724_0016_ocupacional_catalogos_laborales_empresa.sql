-- Catalogos maestros de areas y puestos por empresa.
-- Mantiene compatibilidad con pacientes_ocupacionales, que conserva snapshots de texto.

CREATE TABLE IF NOT EXISTS ocupacional_catalogos_laborales_empresa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('area', 'puesto') NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  created_by BIGINT UNSIGNED DEFAULT NULL,
  updated_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_catalogo_laboral_empresa_tipo_nombre (empresa_id, tipo, nombre),
  KEY idx_ocup_catalogo_laboral_empresa_tipo_estado (empresa_id, tipo, estado),
  CONSTRAINT fk_ocup_catalogo_laboral_empresa
    FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ocupacional_catalogos_laborales_empresa
  (empresa_id, tipo, nombre, estado)
SELECT DISTINCT empresa_id, 'puesto', TRIM(puesto_trabajo), 'activo'
FROM pacientes_ocupacionales
WHERE puesto_trabajo IS NOT NULL AND TRIM(puesto_trabajo) <> '';

INSERT IGNORE INTO ocupacional_catalogos_laborales_empresa
  (empresa_id, tipo, nombre, estado)
SELECT DISTINCT empresa_id, 'area', TRIM(area_riesgo), 'activo'
FROM pacientes_ocupacionales
WHERE area_riesgo IS NOT NULL
  AND TRIM(area_riesgo) <> ''
  AND LOWER(TRIM(area_riesgo)) NOT IN ('bajo', 'medio', 'alto');