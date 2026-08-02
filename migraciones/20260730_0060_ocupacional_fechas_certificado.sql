-- Fechas de referencia para certificado ocupacional por orden.
-- Reglas de negocio:
-- - certificado_fecha_evaluacion: si es NULL se usa fecha_orden.
-- - certificado_fecha_emision: si es NULL se usa certificado_fecha_evaluacion (o fecha_orden por arrastre).

SET @db_name := DATABASE();

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'certificado_fecha_evaluacion'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN certificado_fecha_evaluacion DATE NULL AFTER fecha_orden',
  'SELECT "skip certificado_fecha_evaluacion"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE BINARY table_schema = BINARY @db_name
    AND BINARY table_name = BINARY 'ocupacional_ordenes'
    AND BINARY column_name = BINARY 'certificado_fecha_emision'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN certificado_fecha_emision DATE NULL AFTER certificado_fecha_evaluacion',
  'SELECT "skip certificado_fecha_emision"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'ocupacional fechas certificado ready' AS resultado;
