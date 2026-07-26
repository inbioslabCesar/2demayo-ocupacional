-- Flujo clinico de observacion, interconsulta, respuesta y levantamiento.

CREATE TABLE IF NOT EXISTS ocupacional_interconsultas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  orden_detalle_id INT UNSIGNED NOT NULL,
  especialidad VARCHAR(120) NOT NULL,
  motivo TEXT NOT NULL,
  diagnostico_cie10 VARCHAR(20) NULL,
  diagnostico_descripcion VARCHAR(255) NULL,
  observaciones TEXT NULL,
  estado ENUM('solicitada','respondida','levantada','anulada') NOT NULL DEFAULT 'solicitada',
  especialista_nombre VARCHAR(180) NULL,
  respuesta TEXT NULL,
  respuesta_documento VARCHAR(255) NULL,
  respuesta_at DATETIME NULL,
  respondida_by INT NULL,
  levantamiento TEXT NULL,
  recomendacion TEXT NULL,
  resultado_levantamiento ENUM('FAVORABLE','NO_FAVORABLE') NULL,
  medico_levantamiento_id INT NULL,
  medico_levantamiento_nombre_snapshot VARCHAR(220) NULL,
  medico_levantamiento_cmp_snapshot VARCHAR(30) NULL,
  levantamiento_at DATETIME NULL,
  levantada_by INT NULL,
  anulacion_motivo VARCHAR(255) NULL,
  anulada_at DATETIME NULL,
  anulada_by INT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_inter_orden (orden_id),
  KEY idx_ocup_inter_detalle_estado (orden_detalle_id, estado),
  CONSTRAINT fk_ocup_inter_orden
    FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ocup_inter_detalle
    FOREIGN KEY (orden_detalle_id) REFERENCES ocupacional_orden_detalle(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db_name := DATABASE();

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_interconsultas'
    AND BINARY column_name = BINARY 'medico_levantamiento_id'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_interconsultas ADD COLUMN medico_levantamiento_id INT NULL AFTER resultado_levantamiento',
  'SELECT "skip medico_levantamiento_id"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_interconsultas'
    AND BINARY column_name = BINARY 'medico_levantamiento_nombre_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_interconsultas ADD COLUMN medico_levantamiento_nombre_snapshot VARCHAR(220) NULL AFTER medico_levantamiento_id',
  'SELECT "skip medico_levantamiento_nombre_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_interconsultas'
    AND BINARY column_name = BINARY 'medico_levantamiento_cmp_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_interconsultas ADD COLUMN medico_levantamiento_cmp_snapshot VARCHAR(30) NULL AFTER medico_levantamiento_nombre_snapshot',
  'SELECT "skip medico_levantamiento_cmp_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'ocupacional interconsultas ready' AS resultado;