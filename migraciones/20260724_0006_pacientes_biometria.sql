-- Agrega campos biometricos a pacientes para firma digital, huella digital y fotografia.
-- Script idempotente compatible con MySQL 5.7/8 usando information_schema.

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pacientes'
      AND COLUMN_NAME = 'firma_digital'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pacientes ADD COLUMN firma_digital MEDIUMTEXT NULL AFTER email',
    'SELECT "pacientes.firma_digital ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pacientes'
      AND COLUMN_NAME = 'huella_digital'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pacientes ADD COLUMN huella_digital MEDIUMTEXT NULL AFTER firma_digital',
    'SELECT "pacientes.huella_digital ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pacientes'
      AND COLUMN_NAME = 'fotografia'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pacientes ADD COLUMN fotografia MEDIUMTEXT NULL AFTER huella_digital',
    'SELECT "pacientes.fotografia ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;