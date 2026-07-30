-- 20260730_0040_ocupacional_acople_laboratorio_moderno.sql
-- Objetivo:
-- 1) Vincular examenes ocupacionales con catalogo moderno de laboratorio.
-- 2) Guardar snapshot estructurado para uso dinamico en formatos ocupacionales.
-- 3) Congelar snapshot en detalle de orden para trazabilidad historica.

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- A) ocupacional_examenes_generales: columnas de acople laboratorio
-- ---------------------------------------------------------------------------

SET @tbl_exam := 'ocupacional_examenes_generales';

SET @sql_add_origen := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_exam
      AND column_name = 'origen_datos'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_examenes_generales ADD COLUMN origen_datos VARCHAR(20) NOT NULL DEFAULT "manual" AFTER estado'
);
PREPARE stmt_add_origen FROM @sql_add_origen;
EXECUTE stmt_add_origen;
DEALLOCATE PREPARE stmt_add_origen;

SET @sql_add_lab_exam_id := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_exam
      AND column_name = 'laboratorio_examen_id'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_examenes_generales ADD COLUMN laboratorio_examen_id INT NULL AFTER origen_datos'
);
PREPARE stmt_add_lab_exam_id FROM @sql_add_lab_exam_id;
EXECUTE stmt_add_lab_exam_id;
DEALLOCATE PREPARE stmt_add_lab_exam_id;

SET @sql_add_lab_version_id := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_exam
      AND column_name = 'laboratorio_version_id'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_examenes_generales ADD COLUMN laboratorio_version_id INT NULL AFTER laboratorio_examen_id'
);
PREPARE stmt_add_lab_version_id FROM @sql_add_lab_version_id;
EXECUTE stmt_add_lab_version_id;
DEALLOCATE PREPARE stmt_add_lab_version_id;

SET @sql_add_lab_snapshot := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_exam
      AND column_name = 'laboratorio_snapshot_json'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_examenes_generales ADD COLUMN laboratorio_snapshot_json LONGTEXT NULL AFTER laboratorio_version_id'
);
PREPARE stmt_add_lab_snapshot FROM @sql_add_lab_snapshot;
EXECUTE stmt_add_lab_snapshot;
DEALLOCATE PREPARE stmt_add_lab_snapshot;

SET @sql_add_idx_lab_exam := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_exam
      AND index_name = 'idx_ocup_examen_lab_origen'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_examenes_generales ADD INDEX idx_ocup_examen_lab_origen (laboratorio_examen_id)'
);
PREPARE stmt_add_idx_lab_exam FROM @sql_add_idx_lab_exam;
EXECUTE stmt_add_idx_lab_exam;
DEALLOCATE PREPARE stmt_add_idx_lab_exam;

SET @sql_add_uq_lab_exam := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_exam
      AND index_name = 'uq_ocup_examen_lab_origen'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_examenes_generales ADD UNIQUE INDEX uq_ocup_examen_lab_origen (laboratorio_examen_id)'
);
PREPARE stmt_add_uq_lab_exam FROM @sql_add_uq_lab_exam;
EXECUTE stmt_add_uq_lab_exam;
DEALLOCATE PREPARE stmt_add_uq_lab_exam;

UPDATE ocupacional_examenes_generales
SET origen_datos = 'manual'
WHERE COALESCE(origen_datos, '') = '';

-- ---------------------------------------------------------------------------
-- B) ocupacional_orden_detalle: snapshot congelado por orden
-- ---------------------------------------------------------------------------

SET @tbl_det := 'ocupacional_orden_detalle';

SET @sql_add_det_snapshot := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_det
      AND column_name = 'examen_snapshot_json'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN examen_snapshot_json LONGTEXT NULL AFTER examen_orden_snapshot'
);
PREPARE stmt_add_det_snapshot FROM @sql_add_det_snapshot;
EXECUTE stmt_add_det_snapshot;
DEALLOCATE PREPARE stmt_add_det_snapshot;

SET @sql_add_idx_det_snapshot := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = @tbl_det
      AND index_name = 'idx_ocup_detalle_orden_id'
  ),
  'SELECT 1',
  'ALTER TABLE ocupacional_orden_detalle ADD INDEX idx_ocup_detalle_orden_id (orden_id)'
);
PREPARE stmt_add_idx_det_snapshot FROM @sql_add_idx_det_snapshot;
EXECUTE stmt_add_idx_det_snapshot;
DEALLOCATE PREPARE stmt_add_idx_det_snapshot;

COMMIT;
