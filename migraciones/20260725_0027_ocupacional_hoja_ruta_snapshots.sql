-- ============================================================================
-- HOSTINGER PRODUCCION - SNAPSHOTS PARA HOJA DE RUTA OCUPACIONAL
-- Destino esperado: u330560936_so2demayo
-- Ejecutar despues de 20260725_0026_ocupacional_medico_rna_snapshot.sql.
-- ============================================================================

SET NAMES utf8mb4;
USE `u330560936_so2demayo`;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada, 'OK' AS preflight_base;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle')=1
  AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='grupo_snapshot')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN grupo_snapshot VARCHAR(100) NULL AFTER examen_descripcion',
  'SELECT "skip ocupacional_orden_detalle.grupo_snapshot"'
);
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='subgrupo_snapshot')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN subgrupo_snapshot VARCHAR(100) NULL AFTER grupo_snapshot',
  'SELECT "skip ocupacional_orden_detalle.subgrupo_snapshot"'
);
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='grupo_orden_snapshot')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN grupo_orden_snapshot INT NOT NULL DEFAULT 0 AFTER subgrupo_snapshot',
  'SELECT "skip ocupacional_orden_detalle.grupo_orden_snapshot"'
);
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='examen_orden_snapshot')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN examen_orden_snapshot INT NOT NULL DEFAULT 0 AFTER grupo_orden_snapshot',
  'SELECT "skip ocupacional_orden_detalle.examen_orden_snapshot"'
);
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema=@db_name
  AND table_name='ocupacional_orden_detalle'
  AND column_name IN ('grupo_snapshot','subgrupo_snapshot','grupo_orden_snapshot','examen_orden_snapshot')
ORDER BY ordinal_position;

SELECT 'FIN HOJA RUTA SNAPSHOTS' AS resultado, DATABASE() AS base_aplicada;
