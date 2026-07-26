-- ============================================================================
-- HOSTINGER PRODUCCION - BASE CLINICA
-- Destino esperado: u330560936_bd2DeMayo
-- Solo agrega columnas requeridas por Salud Ocupacional.
-- No recarga ubigeo, no trunca tablas y no modifica datos clinicos existentes.
-- ============================================================================

SET NAMES utf8mb4;
USE `u330560936_bd2DeMayo`;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada,
        'OK' AS preflight_base;

SELECT COUNT(*) AS tabla_pacientes FROM information_schema.tables
WHERE table_schema=@db_name AND table_name='pacientes';
SELECT COUNT(*) AS tabla_medicos FROM information_schema.tables
WHERE table_schema=@db_name AND table_name='medicos';
SELECT COUNT(*) AS tabla_configuracion FROM information_schema.tables
WHERE table_schema=@db_name AND table_name='configuracion_clinica';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='firma_digital')=0,'ALTER TABLE pacientes ADD COLUMN firma_digital MEDIUMTEXT NULL AFTER email','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='huella_digital')=0,'ALTER TABLE pacientes ADD COLUMN huella_digital MEDIUMTEXT NULL AFTER firma_digital','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='fotografia')=0,'ALTER TABLE pacientes ADD COLUMN fotografia MEDIUMTEXT NULL AFTER huella_digital','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='tipo_documento')=0,'ALTER TABLE pacientes ADD COLUMN tipo_documento VARCHAR(30) NULL AFTER dni','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='lugarnacimiento')=0,'ALTER TABLE pacientes ADD COLUMN lugarnacimiento VARCHAR(180) NULL AFTER fecha_nacimiento','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='calle')=0,'ALTER TABLE pacientes ADD COLUMN calle VARCHAR(120) NULL AFTER direccion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='urbanizacion')=0,'ALTER TABLE pacientes ADD COLUMN urbanizacion VARCHAR(180) NULL AFTER calle','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='ocupacion')=0,'ALTER TABLE pacientes ADD COLUMN ocupacion VARCHAR(180) NULL AFTER urbanizacion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='hijos')=0,'ALTER TABLE pacientes ADD COLUMN hijos INT NULL AFTER ocupacion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='hijosdependientes')=0,'ALTER TABLE pacientes ADD COLUMN hijosdependientes INT NULL AFTER hijos','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='departamento')=0,'ALTER TABLE pacientes ADD COLUMN departamento VARCHAR(120) NULL AFTER procedencia','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='provincia')=0,'ALTER TABLE pacientes ADD COLUMN provincia VARCHAR(120) NULL AFTER departamento','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='distrito')=0,'ALTER TABLE pacientes ADD COLUMN distrito VARCHAR(120) NULL AFTER provincia','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='gradoinstruccion')=0,'ALTER TABLE pacientes ADD COLUMN gradoinstruccion VARCHAR(120) NULL AFTER distrito','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='estadocivil')=0,'ALTER TABLE pacientes ADD COLUMN estadocivil VARCHAR(80) NULL AFTER gradoinstruccion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='nombrepadre')=0,'ALTER TABLE pacientes ADD COLUMN nombrepadre VARCHAR(180) NULL AFTER estadocivil','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='nombremadre')=0,'ALTER TABLE pacientes ADD COLUMN nombremadre VARCHAR(180) NULL AFTER nombrepadre','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='acompanante')=0,'ALTER TABLE pacientes ADD COLUMN acompanante VARCHAR(180) NULL AFTER nombremadre','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='trabajoresidencia')=0,'ALTER TABLE pacientes ADD COLUMN trabajoresidencia TINYINT(1) NULL AFTER acompanante','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='tiemporesidencia')=0,'ALTER TABLE pacientes ADD COLUMN tiemporesidencia INT NULL AFTER trabajoresidencia','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes' AND column_name='celular')=0,'ALTER TABLE pacientes ADD COLUMN celular VARCHAR(30) NULL AFTER telefono','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE pacientes SET tipo_documento='dni'
WHERE tipo_documento IS NULL OR TRIM(tipo_documento)='';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='medicos' AND column_name='dni')=0,'ALTER TABLE medicos ADD COLUMN dni VARCHAR(20) NULL AFTER apellido','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='medicos' AND column_name='direccion')=0,'ALTER TABLE medicos ADD COLUMN direccion VARCHAR(255) NULL AFTER rne','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='medicos' AND column_name='telefono')=0,'ALTER TABLE medicos ADD COLUMN telefono VARCHAR(30) NULL AFTER direccion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='medicos' AND column_name='celular')=0,'ALTER TABLE medicos ADD COLUMN celular VARCHAR(30) NULL AFTER telefono','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='medicos' AND column_name='rna')=0,'ALTER TABLE medicos ADD COLUMN rna VARCHAR(30) NULL AFTER rne','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='configuracion_clinica' AND column_name='logo_ocupacional_url')=0,'ALTER TABLE configuracion_clinica ADD COLUMN logo_ocupacional_url VARCHAR(500) NULL AFTER logo_url','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT 'pacientes_campos_ocupacionales' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema=@db_name AND table_name='pacientes'
  AND column_name IN ('firma_digital','huella_digital','fotografia','tipo_documento','ocupacion','departamento','provincia','distrito','celular');

SELECT 'medicos_campos_ocupacionales' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema=@db_name AND table_name='medicos'
  AND column_name IN ('dni','direccion','telefono','celular','rna');

SELECT 'logo_ocupacional' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema=@db_name AND table_name='configuracion_clinica'
  AND column_name='logo_ocupacional_url';

SELECT 'FIN CLINICA' AS resultado, DATABASE() AS base_aplicada;
