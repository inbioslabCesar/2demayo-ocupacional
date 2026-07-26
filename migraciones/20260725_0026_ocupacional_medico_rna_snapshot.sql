-- ============================================================================
-- HOSTINGER PRODUCCION - SNAPSHOT RNA EN ORDEN OCUPACIONAL
-- Destino esperado: u330560936_so2demayo
-- Ejecutar despues de 20260725_0025_medicos_rna_clinica.sql.
-- ============================================================================

SET NAMES utf8mb4;
USE `u330560936_so2demayo`;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada, 'OK' AS preflight_base;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db_name AND table_name='ocupacional_ordenes')=1
  AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_rna_snapshot')=0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_rna_snapshot VARCHAR(30) NULL AFTER medico_rne_snapshot',
  'SELECT "skip ocupacional_ordenes.medico_rna_snapshot"'
);
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SELECT 'medico_rna_snapshot' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_rna_snapshot';

SELECT 'FIN OCUPACIONAL RNA' AS resultado, DATABASE() AS base_aplicada;