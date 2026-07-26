-- ============================================================================
-- HOSTINGER PRODUCCION - RNA OPCIONAL DEL MEDICO
-- Destino esperado: u330560936_bd2DeMayo
-- Ejecutar despues de 20260725_0024_hostinger_produccion_clinica.sql.
-- ============================================================================

SET NAMES utf8mb4;
USE `u330560936_bd2DeMayo`;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada, 'OK' AS preflight_base;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db_name AND table_name='medicos')=1
  AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='medicos' AND column_name='rna')=0,
  'ALTER TABLE medicos ADD COLUMN rna VARCHAR(30) NULL AFTER rne',
  'SELECT "skip medicos.rna"'
);
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SELECT 'medicos_rna' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema=@db_name AND table_name='medicos' AND column_name='rna';

SELECT 'FIN CLINICA RNA' AS resultado, DATABASE() AS base_aplicada;