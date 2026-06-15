USE `2demayo_so`;

SET @exists_created_by_emp = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = '2demayo_so'
    AND table_name = 'empresas_ocupacionales'
    AND column_name = 'created_by'
);
SET @sql_created_by_emp = IF(
  @exists_created_by_emp = 0,
  'ALTER TABLE `empresas_ocupacionales` ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `estado`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_created_by_emp;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_updated_by_emp = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = '2demayo_so'
    AND table_name = 'empresas_ocupacionales'
    AND column_name = 'updated_by'
);
SET @sql_updated_by_emp = IF(
  @exists_updated_by_emp = 0,
  'ALTER TABLE `empresas_ocupacionales` ADD COLUMN `updated_by` BIGINT UNSIGNED NULL AFTER `created_by`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_updated_by_emp;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_created_by_pac = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = '2demayo_so'
    AND table_name = 'pacientes_ocupacionales'
    AND column_name = 'created_by'
);
SET @sql_created_by_pac = IF(
  @exists_created_by_pac = 0,
  'ALTER TABLE `pacientes_ocupacionales` ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `fecha_ingreso`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_created_by_pac;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_updated_by_pac = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = '2demayo_so'
    AND table_name = 'pacientes_ocupacionales'
    AND column_name = 'updated_by'
);
SET @sql_updated_by_pac = IF(
  @exists_updated_by_pac = 0,
  'ALTER TABLE `pacientes_ocupacionales` ADD COLUMN `updated_by` BIGINT UNSIGNED NULL AFTER `created_by`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_updated_by_pac;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
