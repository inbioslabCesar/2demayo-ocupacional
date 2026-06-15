USE `2demayo_so`;

SET @exists_emp_estado_created = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = '2demayo_so'
    AND table_name = 'empresas_ocupacionales'
    AND index_name = 'idx_emp_estado_created'
);
SET @sql_emp_estado_created = IF(
  @exists_emp_estado_created = 0,
  'ALTER TABLE `empresas_ocupacionales` ADD INDEX `idx_emp_estado_created` (`estado`, `created_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_emp_estado_created;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_pac_estado_fecha = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = '2demayo_so'
    AND table_name = 'pacientes_ocupacionales'
    AND index_name = 'idx_pac_estado_fecha'
);
SET @sql_pac_estado_fecha = IF(
  @exists_pac_estado_fecha = 0,
  'ALTER TABLE `pacientes_ocupacionales` ADD INDEX `idx_pac_estado_fecha` (`estado_laboral`, `fecha_ingreso`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pac_estado_fecha;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_pac_empresa_fecha = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = '2demayo_so'
    AND table_name = 'pacientes_ocupacionales'
    AND index_name = 'idx_pac_empresa_fecha'
);
SET @sql_pac_empresa_fecha = IF(
  @exists_pac_empresa_fecha = 0,
  'ALTER TABLE `pacientes_ocupacionales` ADD INDEX `idx_pac_empresa_fecha` (`empresa_id`, `fecha_ingreso`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pac_empresa_fecha;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
