-- Fase 4 Salud Ocupacional: relaciones e indices base
-- Requisito previo: 2026-07-02_01_base_historia_ocupacional.sql
-- Script idempotente para MySQL 8+

SET @schema_name := DATABASE();

-- 1) Indice unico para evitar duplicados por detalle y formato clinico
SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE BINARY table_schema = BINARY DATABASE()
  AND BINARY table_name = 'ocupacional_resultados_clinicos'
  AND BINARY index_name = 'uq_ocup_resultado_detalle_formato';

SET @sql_stmt := IF(
  @idx_exists = 0,
  'ALTER TABLE ocupacional_resultados_clinicos ADD UNIQUE KEY uq_ocup_resultado_detalle_formato (orden_detalle_id, formato_codigo)',
  'SELECT "SKIP uq_ocup_resultado_detalle_formato"'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) FK historia -> orden
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.referential_constraints
WHERE BINARY constraint_schema = BINARY DATABASE()
  AND BINARY constraint_name = 'fk_ocup_historia_orden';

SET @sql_stmt := IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_historia_ocupacional ADD CONSTRAINT fk_ocup_historia_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "SKIP fk_ocup_historia_orden"'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) FK resultados -> orden_detalle
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.referential_constraints
WHERE BINARY constraint_schema = BINARY DATABASE()
  AND BINARY constraint_name = 'fk_ocup_resultado_detalle';

SET @sql_stmt := IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_resultados_clinicos ADD CONSTRAINT fk_ocup_resultado_detalle FOREIGN KEY (orden_detalle_id) REFERENCES ocupacional_orden_detalle(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "SKIP fk_ocup_resultado_detalle"'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) FK resultados -> orden
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.referential_constraints
WHERE BINARY constraint_schema = BINARY DATABASE()
  AND BINARY constraint_name = 'fk_ocup_resultado_orden';

SET @sql_stmt := IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_resultados_clinicos ADD CONSTRAINT fk_ocup_resultado_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "SKIP fk_ocup_resultado_orden"'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) FK resultados -> examen general
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.referential_constraints
WHERE BINARY constraint_schema = BINARY DATABASE()
  AND BINARY constraint_name = 'fk_ocup_resultado_examen';

SET @sql_stmt := IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_resultados_clinicos ADD CONSTRAINT fk_ocup_resultado_examen FOREIGN KEY (examen_id) REFERENCES ocupacional_examenes_generales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "SKIP fk_ocup_resultado_examen"'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
