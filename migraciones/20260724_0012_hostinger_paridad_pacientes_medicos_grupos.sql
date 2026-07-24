-- =====================================================
-- PARIDAD PRODUCCION vs DESARROLLO (PACIENTES/MEDICOS)
-- Fecha: 2026-07-24
-- Seguro para ejecutar en phpMyAdmin (idempotente)
-- =====================================================

SET @db_name := DATABASE();

-- -----------------------------------------------------
-- 1) PACIENTES: BIOMETRIA (0006)
-- -----------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pacientes'
      AND COLUMN_NAME = 'firma_digital'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pacientes ADD COLUMN firma_digital MEDIUMTEXT NULL AFTER email',
    'SELECT "skip pacientes.firma_digital"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pacientes'
      AND COLUMN_NAME = 'huella_digital'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pacientes ADD COLUMN huella_digital MEDIUMTEXT NULL AFTER firma_digital',
    'SELECT "skip pacientes.huella_digital"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pacientes'
      AND COLUMN_NAME = 'fotografia'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pacientes ADD COLUMN fotografia MEDIUMTEXT NULL AFTER huella_digital',
    'SELECT "skip pacientes.fotografia"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2) PACIENTES: CAMPOS LEGACY (0007)
-- -----------------------------------------------------
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

-- -----------------------------------------------------
-- 3) MEDICOS: CAMPOS LEGACY (0010)
-- -----------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'dni'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN dni VARCHAR(20) NULL AFTER apellido',
  'SELECT "skip medicos.dni"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'direccion'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN direccion VARCHAR(255) NULL AFTER rne',
  'SELECT "skip medicos.direccion"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'telefono'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN telefono VARCHAR(30) NULL AFTER direccion',
  'SELECT "skip medicos.telefono"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'medicos' AND COLUMN_NAME = 'celular'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE medicos ADD COLUMN celular VARCHAR(30) NULL AFTER telefono',
  'SELECT "skip medicos.celular"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 4) OCUPACIONAL: MAESTRO GRUPOS/SUBGRUPOS (0011)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS ocupacional_grupos_examenes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(180) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_nombre_parent (nombre, parent_id),
  KEY idx_parent (parent_id),
  CONSTRAINT fk_oge_parent FOREIGN KEY (parent_id)
    REFERENCES ocupacional_grupos_examenes(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ocupacional_grupos_examenes (nombre, parent_id, estado)
SELECT DISTINCT TRIM(general), NULL, 'activo'
FROM ocupacional_examenes_generales
WHERE TRIM(COALESCE(general,'')) <> ''
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO ocupacional_grupos_examenes (nombre, parent_id, estado)
SELECT DISTINCT
  TRIM(COALESCE(subgrupo, examen)),
  g.id,
  'activo'
FROM ocupacional_examenes_generales e
JOIN ocupacional_grupos_examenes g
  ON g.parent_id IS NULL
 AND g.nombre = TRIM(e.general)
WHERE TRIM(COALESCE(general,'')) <> ''
  AND TRIM(COALESCE(subgrupo, examen, '')) <> ''
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- -----------------------------------------------------
-- 5) VALIDACION POST-EJECUCION
-- -----------------------------------------------------
SELECT 'ok_table_ocupacional_grupos_examenes' AS check_name,
       COUNT(*) AS total
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'ocupacional_grupos_examenes';

SELECT 'pacientes_columnas_clave' AS check_name,
       SUM(CASE WHEN COLUMN_NAME = 'firma_digital' THEN 1 ELSE 0 END) AS firma_digital,
       SUM(CASE WHEN COLUMN_NAME = 'huella_digital' THEN 1 ELSE 0 END) AS huella_digital,
       SUM(CASE WHEN COLUMN_NAME = 'fotografia' THEN 1 ELSE 0 END) AS fotografia,
       SUM(CASE WHEN COLUMN_NAME = 'tipo_documento' THEN 1 ELSE 0 END) AS tipo_documento,
       SUM(CASE WHEN COLUMN_NAME = 'ocupacion' THEN 1 ELSE 0 END) AS ocupacion,
       SUM(CASE WHEN COLUMN_NAME = 'celular' THEN 1 ELSE 0 END) AS celular
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'pacientes'
  AND COLUMN_NAME IN ('firma_digital','huella_digital','fotografia','tipo_documento','ocupacion','celular');

SELECT 'medicos_columnas_clave' AS check_name,
       SUM(CASE WHEN COLUMN_NAME = 'dni' THEN 1 ELSE 0 END) AS dni,
       SUM(CASE WHEN COLUMN_NAME = 'direccion' THEN 1 ELSE 0 END) AS direccion,
       SUM(CASE WHEN COLUMN_NAME = 'telefono' THEN 1 ELSE 0 END) AS telefono,
       SUM(CASE WHEN COLUMN_NAME = 'celular' THEN 1 ELSE 0 END) AS celular
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'medicos'
  AND COLUMN_NAME IN ('dni','direccion','telefono','celular');

SELECT 'total_grupos_subgrupos' AS check_name, COUNT(*) AS total
FROM ocupacional_grupos_examenes;
