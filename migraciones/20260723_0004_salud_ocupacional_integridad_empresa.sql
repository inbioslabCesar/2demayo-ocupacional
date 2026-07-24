-- Salud Ocupacional - Integridad de Empresa
-- Fecha: 2026-07-23
-- Objetivo:
-- 1) Unificar tipos de columnas relacionadas con empresa en tablas fase 2/3.
-- 2) Asegurar indices de soporte para joins/listados.
-- 3) Reportar orfandad antes de crear FKs.
-- 4) Crear FKs idempotentes en tablas clave de empresa.
--
-- Ejecutar en la base de salud ocupacional (ejemplo: 2demayo_so).

SET @schema = DATABASE();

-- ---------------------------------------------------------------------
-- 0) Guardas de existencia de tablas requeridas
-- ---------------------------------------------------------------------
SET @missing_tables = (
  SELECT COUNT(*)
  FROM (
    SELECT 'empresas_ocupacionales' AS t
    UNION ALL SELECT 'pacientes_ocupacionales'
    UNION ALL SELECT 'ocupacional_protocolos_empresa'
    UNION ALL SELECT 'ocupacional_catalogo_empresas'
    UNION ALL SELECT 'ocupacional_ordenes'
  ) x
  LEFT JOIN information_schema.tables it
    ON it.table_schema = @schema
   AND it.table_name = x.t
  WHERE it.table_name IS NULL
);

SELECT
  CASE
    WHEN @missing_tables = 0 THEN 'OK tablas requeridas presentes'
    ELSE CONCAT('ERROR faltan tablas requeridas: ', @missing_tables)
  END AS estado_precheck_tablas;

-- ---------------------------------------------------------------------
-- 1) Unificar tipos a BIGINT UNSIGNED en columnas empresa/trabajador
-- ---------------------------------------------------------------------
SET @type_proto_empresa = (
  SELECT COLUMN_TYPE
  FROM information_schema.columns
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_protocolos_empresa'
    AND column_name = 'empresa_id'
  LIMIT 1
);
SET @sql = IF(
  @type_proto_empresa = 'bigint unsigned',
  'SELECT "skip ocupacional_protocolos_empresa.empresa_id ya bigint unsigned" AS info',
  'ALTER TABLE ocupacional_protocolos_empresa MODIFY empresa_id BIGINT UNSIGNED NOT NULL'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @type_cat_empresa = (
  SELECT COLUMN_TYPE
  FROM information_schema.columns
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_catalogo_empresas'
    AND column_name = 'empresa_id'
  LIMIT 1
);
SET @sql = IF(
  @type_cat_empresa = 'bigint unsigned',
  'SELECT "skip ocupacional_catalogo_empresas.empresa_id ya bigint unsigned" AS info',
  'ALTER TABLE ocupacional_catalogo_empresas MODIFY empresa_id BIGINT UNSIGNED NOT NULL'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @type_ord_empresa = (
  SELECT COLUMN_TYPE
  FROM information_schema.columns
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'empresa_id'
  LIMIT 1
);
SET @sql = IF(
  @type_ord_empresa = 'bigint unsigned',
  'SELECT "skip ocupacional_ordenes.empresa_id ya bigint unsigned" AS info',
  'ALTER TABLE ocupacional_ordenes MODIFY empresa_id BIGINT UNSIGNED NOT NULL'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @type_ord_trab = (
  SELECT COLUMN_TYPE
  FROM information_schema.columns
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'trabajador_id'
  LIMIT 1
);
SET @sql = IF(
  @type_ord_trab = 'bigint unsigned',
  'SELECT "skip ocupacional_ordenes.trabajador_id ya bigint unsigned" AS info',
  'ALTER TABLE ocupacional_ordenes MODIFY trabajador_id BIGINT UNSIGNED NOT NULL'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2) Indices idempotentes para joins/listados
-- ---------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_protocolos_empresa'
    AND index_name = 'idx_proto_empresa'
);
SET @sql = IF(
  @idx_exists = 0,
  'ALTER TABLE ocupacional_protocolos_empresa ADD INDEX idx_proto_empresa (empresa_id)',
  'SELECT "skip idx_proto_empresa" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_catalogo_empresas'
    AND index_name = 'idx_cat_empresa'
);
SET @sql = IF(
  @idx_exists = 0,
  'ALTER TABLE ocupacional_catalogo_empresas ADD INDEX idx_cat_empresa (empresa_id)',
  'SELECT "skip idx_cat_empresa" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_ordenes'
    AND index_name = 'idx_ord_empresa'
);
SET @sql = IF(
  @idx_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD INDEX idx_ord_empresa (empresa_id)',
  'SELECT "skip idx_ord_empresa" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @schema
    AND table_name = 'ocupacional_ordenes'
    AND index_name = 'idx_ord_trabajador'
);
SET @sql = IF(
  @idx_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD INDEX idx_ord_trabajador (trabajador_id)',
  'SELECT "skip idx_ord_trabajador" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 3) Auditoria de orfandad (debe dar 0 para avanzar a FKs)
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS huerfanos_proto_empresa
FROM ocupacional_protocolos_empresa p
LEFT JOIN empresas_ocupacionales e ON e.id = p.empresa_id
WHERE e.id IS NULL;

SELECT COUNT(*) AS huerfanos_cat_empresa
FROM ocupacional_catalogo_empresas c
LEFT JOIN empresas_ocupacionales e ON e.id = c.empresa_id
WHERE e.id IS NULL;

SELECT COUNT(*) AS huerfanos_ord_empresa
FROM ocupacional_ordenes o
LEFT JOIN empresas_ocupacionales e ON e.id = o.empresa_id
WHERE e.id IS NULL;

SELECT COUNT(*) AS huerfanos_ord_trabajador
FROM ocupacional_ordenes o
LEFT JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
WHERE t.id IS NULL;

-- ---------------------------------------------------------------------
-- 4) FKs idempotentes por relacion (no por nombre)
-- ---------------------------------------------------------------------
-- FK: ocupacional_protocolos_empresa.empresa_id -> empresas_ocupacionales.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.key_column_usage k
  WHERE k.table_schema = @schema
    AND k.table_name = 'ocupacional_protocolos_empresa'
    AND k.column_name = 'empresa_id'
    AND k.referenced_table_name = 'empresas_ocupacionales'
    AND k.referenced_column_name = 'id'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_protocolos_empresa ADD CONSTRAINT fk_proto_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "skip fk proto_empresa relation already exists" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- FK: ocupacional_catalogo_empresas.empresa_id -> empresas_ocupacionales.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.key_column_usage k
  WHERE k.table_schema = @schema
    AND k.table_name = 'ocupacional_catalogo_empresas'
    AND k.column_name = 'empresa_id'
    AND k.referenced_table_name = 'empresas_ocupacionales'
    AND k.referenced_column_name = 'id'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_catalogo_empresas ADD CONSTRAINT fk_cat_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "skip fk cat_empresa relation already exists" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- FK: ocupacional_ordenes.empresa_id -> empresas_ocupacionales.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.key_column_usage k
  WHERE k.table_schema = @schema
    AND k.table_name = 'ocupacional_ordenes'
    AND k.column_name = 'empresa_id'
    AND k.referenced_table_name = 'empresas_ocupacionales'
    AND k.referenced_column_name = 'id'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD CONSTRAINT fk_ord_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "skip fk ord_empresa relation already exists" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- FK: ocupacional_ordenes.trabajador_id -> pacientes_ocupacionales.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.key_column_usage k
  WHERE k.table_schema = @schema
    AND k.table_name = 'ocupacional_ordenes'
    AND k.column_name = 'trabajador_id'
    AND k.referenced_table_name = 'pacientes_ocupacionales'
    AND k.referenced_column_name = 'id'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE ocupacional_ordenes ADD CONSTRAINT fk_ord_trabajador FOREIGN KEY (trabajador_id) REFERENCES pacientes_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "skip fk ord_trabajador relation already exists" AS info'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 5) Reporte final de estructura esperada
-- ---------------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @schema
  AND (
    (TABLE_NAME = 'ocupacional_protocolos_empresa' AND COLUMN_NAME = 'empresa_id') OR
    (TABLE_NAME = 'ocupacional_catalogo_empresas' AND COLUMN_NAME = 'empresa_id') OR
    (TABLE_NAME = 'ocupacional_ordenes' AND COLUMN_NAME IN ('empresa_id', 'trabajador_id'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @schema
  AND (
    (TABLE_NAME = 'ocupacional_protocolos_empresa' AND COLUMN_NAME = 'empresa_id') OR
    (TABLE_NAME = 'ocupacional_catalogo_empresas' AND COLUMN_NAME = 'empresa_id') OR
    (TABLE_NAME = 'ocupacional_ordenes' AND COLUMN_NAME IN ('empresa_id', 'trabajador_id'))
  )
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
