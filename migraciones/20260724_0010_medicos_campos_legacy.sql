-- Paridad legacy de doctor para CRUD de medicos en sistema moderno
-- Agrega columnas historicas usadas en flujo ocupacional/legacy

SET @schema := DATABASE();

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'dni'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN dni VARCHAR(20) NULL AFTER apellido',
  'SELECT "skip medicos.dni"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'direccion'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN direccion VARCHAR(255) NULL AFTER rne',
  'SELECT "skip medicos.direccion"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'telefono'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN telefono VARCHAR(30) NULL AFTER direccion',
  'SELECT "skip medicos.telefono"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'celular'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN celular VARCHAR(30) NULL AFTER telefono',
  'SELECT "skip medicos.celular"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
