-- Edicion y anulacion auditable de registros ocupacionales.
-- Anulado revierte un alta errada; retirado representa una baja laboral real.

ALTER TABLE pacientes_ocupacionales
  MODIFY COLUMN estado_laboral ENUM('activo', 'retirado', 'anulado') NOT NULL DEFAULT 'activo';

SET @db_name := DATABASE();

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'pacientes_ocupacionales'
      AND column_name = 'anulacion_motivo'
  ),
  'SELECT 1',
  'ALTER TABLE pacientes_ocupacionales ADD COLUMN anulacion_motivo VARCHAR(255) NULL AFTER estado_laboral'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'pacientes_ocupacionales'
      AND column_name = 'anulado_at'
  ),
  'SELECT 1',
  'ALTER TABLE pacientes_ocupacionales ADD COLUMN anulado_at DATETIME NULL AFTER anulacion_motivo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'pacientes_ocupacionales'
      AND column_name = 'anulado_by'
  ),
  'SELECT 1',
  'ALTER TABLE pacientes_ocupacionales ADD COLUMN anulado_by BIGINT UNSIGNED NULL AFTER anulado_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;