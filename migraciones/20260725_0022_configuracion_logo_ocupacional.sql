-- Logo horizontal utilizado exclusivamente en certificados y documentos ocupacionales.

SET @db_name := DATABASE();
SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'configuracion_clinica'
    AND COLUMN_NAME = 'logo_ocupacional_url'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE configuracion_clinica ADD COLUMN logo_ocupacional_url VARCHAR(500) NULL AFTER logo_url',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;