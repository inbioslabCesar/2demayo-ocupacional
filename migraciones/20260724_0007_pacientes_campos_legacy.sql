-- Agrega campos legacy de paciente para paridad funcional (ocupacion y relacionados).
-- Script idempotente usando information_schema.

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'tipo_documento');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN tipo_documento VARCHAR(30) NULL AFTER dni', 'SELECT "skip pacientes.tipo_documento"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'lugarnacimiento');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN lugarnacimiento VARCHAR(180) NULL AFTER fecha_nacimiento', 'SELECT "skip pacientes.lugarnacimiento"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'calle');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN calle VARCHAR(120) NULL AFTER direccion', 'SELECT "skip pacientes.calle"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'urbanizacion');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN urbanizacion VARCHAR(180) NULL AFTER calle', 'SELECT "skip pacientes.urbanizacion"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'ocupacion');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN ocupacion VARCHAR(180) NULL AFTER urbanizacion', 'SELECT "skip pacientes.ocupacion"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'hijos');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN hijos INT NULL AFTER ocupacion', 'SELECT "skip pacientes.hijos"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'hijosdependientes');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN hijosdependientes INT NULL AFTER hijos', 'SELECT "skip pacientes.hijosdependientes"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'departamento');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN departamento VARCHAR(120) NULL AFTER procedencia', 'SELECT "skip pacientes.departamento"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'provincia');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN provincia VARCHAR(120) NULL AFTER departamento', 'SELECT "skip pacientes.provincia"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'distrito');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN distrito VARCHAR(120) NULL AFTER provincia', 'SELECT "skip pacientes.distrito"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'gradoinstruccion');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN gradoinstruccion VARCHAR(120) NULL AFTER distrito', 'SELECT "skip pacientes.gradoinstruccion"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'estadocivil');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN estadocivil VARCHAR(80) NULL AFTER gradoinstruccion', 'SELECT "skip pacientes.estadocivil"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'nombrepadre');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN nombrepadre VARCHAR(180) NULL AFTER estadocivil', 'SELECT "skip pacientes.nombrepadre"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'nombremadre');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN nombremadre VARCHAR(180) NULL AFTER nombrepadre', 'SELECT "skip pacientes.nombremadre"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'acompanante');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN acompanante VARCHAR(180) NULL AFTER nombremadre', 'SELECT "skip pacientes.acompanante"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'trabajoresidencia');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN trabajoresidencia TINYINT(1) NULL AFTER acompanante', 'SELECT "skip pacientes.trabajoresidencia"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'tiemporesidencia');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN tiemporesidencia INT NULL AFTER trabajoresidencia', 'SELECT "skip pacientes.tiemporesidencia"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'celular');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pacientes ADD COLUMN celular VARCHAR(30) NULL AFTER telefono', 'SELECT "skip pacientes.celular"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE pacientes SET tipo_documento = COALESCE(NULLIF(tipo_documento, ''), 'dni');
