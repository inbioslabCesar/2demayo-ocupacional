-- Vinculo estructurado y snapshot del medico responsable de la aptitud.
-- No se agrega FK porque el maestro medicos pertenece a la base clinica central.

SET @db_name := DATABASE();

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'medico_responsable_id'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_responsable_id INT NULL AFTER medico_responsable',
  'SELECT "skip medico_responsable_id"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'medico_nombre_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_nombre_snapshot VARCHAR(220) NULL AFTER medico_responsable_id',
  'SELECT "skip medico_nombre_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'medico_especialidad_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_especialidad_snapshot VARCHAR(150) NULL AFTER medico_nombre_snapshot',
  'SELECT "skip medico_especialidad_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'medico_cmp_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_cmp_snapshot VARCHAR(30) NULL AFTER medico_especialidad_snapshot',
  'SELECT "skip medico_cmp_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'medico_rne_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_rne_snapshot VARCHAR(30) NULL AFTER medico_cmp_snapshot',
  'SELECT "skip medico_rne_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'medico_firma_snapshot'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_firma_snapshot LONGTEXT NULL AFTER medico_rne_snapshot',
  'SELECT "skip medico_firma_snapshot"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'aptitud_registrada_by'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN aptitud_registrada_by INT NULL AFTER medico_firma_snapshot',
  'SELECT "skip aptitud_registrada_by"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'aptitud_registrada_at'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN aptitud_registrada_at DATETIME NULL AFTER aptitud_registrada_by',
  'SELECT "skip aptitud_registrada_at"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY index_name = BINARY 'idx_ocup_orden_medico_responsable'
);
SET @sql := IF(
  @index_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD INDEX idx_ocup_orden_medico_responsable (medico_responsable_id)',
  'SELECT "skip idx_ocup_orden_medico_responsable"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'ocupacional medico responsable snapshot ready' AS resultado;