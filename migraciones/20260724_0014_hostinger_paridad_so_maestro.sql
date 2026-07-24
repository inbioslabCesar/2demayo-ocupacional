-- =====================================================
-- MAESTRO HOSTINGER: PARIDAD DB SO (SALUD OCUPACIONAL)
-- Generado: 2026-07-24
-- Base: seleccionar previamente la BD destino en phpMyAdmin
-- =====================================================
-- USAR SOLO EN LA DB ocupacional (ej: u330560936_so2demayo).
-- Incluye integridad empresa, campos legacy empresa, grupos/subgrupos y header legacy ordenes.
-- NO incluye pacientes/medicos/ubigeo del sistema clinico.

SET @db_name := DATABASE();


-- =====================================================
-- BEGIN: migraciones/20260723_0004_salud_ocupacional_integridad_empresa.sql
-- =====================================================
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

-- =====================================================
-- END: migraciones/20260723_0004_salud_ocupacional_integridad_empresa.sql
-- =====================================================

-- =====================================================
-- BEGIN: migraciones/20260723_0005_salud_ocupacional_empresa_campos_legacy.sql
-- =====================================================
-- Add legacy-aligned fields to empresas_ocupacionales (idempotent)
-- Run this after 20260723_0004 on the selected occupational database.

SET @db_name = DATABASE();

-- nombre_comercial
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.columns
      WHERE table_schema = @db_name
        AND table_name = 'empresas_ocupacionales'
        AND column_name = 'nombre_comercial'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN nombre_comercial VARCHAR(200) NULL AFTER razon_social'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- actividad
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'actividad'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN actividad VARCHAR(200) NULL AFTER nombre_comercial'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ubicacion
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'departamento'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN departamento VARCHAR(120) NULL AFTER direccion'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'provincia'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN provincia VARCHAR(120) NULL AFTER departamento'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'distrito'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN distrito VARCHAR(120) NULL AFTER provincia'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- telefonos y contactos
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'telefono_1'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN telefono_1 VARCHAR(30) NULL AFTER telefono'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'telefono_2'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN telefono_2 VARCHAR(30) NULL AFTER telefono_1'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'contacto_1'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN contacto_1 VARCHAR(160) NULL AFTER telefono_2'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'contacto_2'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN contacto_2 VARCHAR(160) NULL AFTER contacto_1'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- correos
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'correo_1'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN correo_1 VARCHAR(120) NULL AFTER correo'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'correo_2'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN correo_2 VARCHAR(120) NULL AFTER correo_1'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- credenciales
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'rrhh_usuario'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN rrhh_usuario VARCHAR(80) NULL AFTER correo_2'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'rrhh_password'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN rrhh_password VARCHAR(120) NULL AFTER rrhh_usuario'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'doctor_usuario'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN doctor_usuario VARCHAR(80) NULL AFTER rrhh_password'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'doctor_password'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN doctor_password VARCHAR(120) NULL AFTER doctor_usuario'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- formatos y observacion
SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'formato_principal'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN formato_principal VARCHAR(40) NULL AFTER doctor_password'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'formato_certificado'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN formato_certificado VARCHAR(40) NULL AFTER formato_principal'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_add = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db_name AND table_name = 'empresas_ocupacionales' AND column_name = 'observacion'
    ),
    'SELECT 1',
    'ALTER TABLE empresas_ocupacionales ADD COLUMN observacion TEXT NULL AFTER formato_certificado'
  )
);
PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill canonical columns from the new primary fields
UPDATE empresas_ocupacionales
SET
  telefono = COALESCE(NULLIF(telefono, ''), NULLIF(telefono_1, ''), telefono),
  correo = COALESCE(NULLIF(correo, ''), NULLIF(correo_1, ''), correo),
  telefono_1 = COALESCE(NULLIF(telefono_1, ''), NULLIF(telefono, ''), telefono_1),
  correo_1 = COALESCE(NULLIF(correo_1, ''), NULLIF(correo, ''), correo_1);

SELECT
  column_name,
  data_type,
  is_nullable
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'empresas_ocupacionales'
  AND column_name IN (
    'ruc', 'razon_social', 'nombre_comercial', 'actividad', 'direccion',
    'departamento', 'provincia', 'distrito',
    'telefono', 'telefono_1', 'telefono_2',
    'contacto_1', 'contacto_2',
    'correo', 'correo_1', 'correo_2',
    'rrhh_usuario', 'rrhh_password', 'doctor_usuario', 'doctor_password',
    'formato_principal', 'formato_certificado', 'observacion'
  )
ORDER BY ordinal_position;

-- =====================================================
-- END: migraciones/20260723_0005_salud_ocupacional_empresa_campos_legacy.sql
-- =====================================================

-- =====================================================
-- BEGIN: migraciones/20260724_0011_ocupacional_maestro_grupos_subgrupos.sql
-- =====================================================
-- Maestro formal de grupos/subgrupos para examenes ocupacionales
-- Compatible con datos legacy de ocupacional_examenes_generales

CREATE TABLE IF NOT EXISTS ocupacional_grupos_examenes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  parent_id INT UNSIGNED NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_grupos_parent_nombre (parent_id, nombre),
  KEY idx_ocup_grupos_parent_estado (parent_id, estado),
  KEY idx_ocup_grupos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Sembrar grupos (nivel raiz)
INSERT INTO ocupacional_grupos_examenes (nombre, parent_id, estado)
SELECT t.grupo, 0, 'activo'
FROM (
  SELECT DISTINCT TRIM(grupo) AS grupo
  FROM ocupacional_examenes_generales
  WHERE grupo IS NOT NULL AND TRIM(grupo) <> ''
) t
LEFT JOIN ocupacional_grupos_examenes g
  ON g.parent_id = 0 AND UPPER(g.nombre) = UPPER(t.grupo)
WHERE g.id IS NULL;

-- 2) Sembrar subgrupos por grupo
INSERT INTO ocupacional_grupos_examenes (nombre, parent_id, estado)
SELECT x.subgrupo, g.id, 'activo'
FROM (
  SELECT DISTINCT TRIM(grupo) AS grupo, TRIM(subgrupo) AS subgrupo
  FROM ocupacional_examenes_generales
  WHERE grupo IS NOT NULL AND TRIM(grupo) <> ''
    AND subgrupo IS NOT NULL AND TRIM(subgrupo) <> ''
) x
INNER JOIN ocupacional_grupos_examenes g
  ON g.parent_id = 0 AND UPPER(g.nombre) = UPPER(x.grupo)
LEFT JOIN ocupacional_grupos_examenes s
  ON s.parent_id = g.id AND UPPER(s.nombre) = UPPER(x.subgrupo)
WHERE s.id IS NULL;

-- =====================================================
-- END: migraciones/20260724_0011_ocupacional_maestro_grupos_subgrupos.sql
-- =====================================================

-- =====================================================
-- BEGIN: sql/2026-07-24_ocupacional_fase3_ordenes_campos_legacy_header_idempotente.sql
-- =====================================================
-- Salud Ocupacional Fase 3: campos de cabecera legacy en ocupacional_ordenes
-- Objetivo: paridad funcional con ordenocupacional legacy (subcontrata, facturar, firma, modo, gestante, documento, indica_dr)
-- Seguro para ejecutar multiples veces.

SET @schema_name := DATABASE();

SET @exists_subcontrata := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'subcontrata_empresa_id'
);
SET @sql_subcontrata := IF(
  @exists_subcontrata = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN subcontrata_empresa_id INT UNSIGNED NULL AFTER empresa_id',
  'SELECT "subcontrata_empresa_id exists"'
);
PREPARE stmt_subcontrata FROM @sql_subcontrata;
EXECUTE stmt_subcontrata;
DEALLOCATE PREPARE stmt_subcontrata;

SET @exists_facturar := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'facturar_empresa_id'
);
SET @sql_facturar := IF(
  @exists_facturar = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN facturar_empresa_id INT UNSIGNED NULL AFTER subcontrata_empresa_id',
  'SELECT "facturar_empresa_id exists"'
);
PREPARE stmt_facturar FROM @sql_facturar;
EXECUTE stmt_facturar;
DEALLOCATE PREPARE stmt_facturar;

SET @exists_firma := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'firma_doctor'
);
SET @sql_firma := IF(
  @exists_firma = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN firma_doctor VARCHAR(80) NULL AFTER tipo_evaluacion_id',
  'SELECT "firma_doctor exists"'
);
PREPARE stmt_firma FROM @sql_firma;
EXECUTE stmt_firma;
DEALLOCATE PREPARE stmt_firma;

SET @exists_modo := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'modo'
);
SET @sql_modo := IF(
  @exists_modo = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN modo VARCHAR(30) NULL AFTER firma_doctor',
  'SELECT "modo exists"'
);
PREPARE stmt_modo FROM @sql_modo;
EXECUTE stmt_modo;
DEALLOCATE PREPARE stmt_modo;

SET @exists_gestante := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'gestante'
);
SET @sql_gestante := IF(
  @exists_gestante = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN gestante TINYINT(1) NOT NULL DEFAULT 0 AFTER trabajador_id',
  'SELECT "gestante exists"'
);
PREPARE stmt_gestante FROM @sql_gestante;
EXECUTE stmt_gestante;
DEALLOCATE PREPARE stmt_gestante;

SET @exists_documento := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'documento'
);
SET @sql_documento := IF(
  @exists_documento = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN documento VARCHAR(50) NULL AFTER observacion',
  'SELECT "documento exists"'
);
PREPARE stmt_documento FROM @sql_documento;
EXECUTE stmt_documento;
DEALLOCATE PREPARE stmt_documento;

SET @exists_indica := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND column_name = 'indica_dr'
);
SET @sql_indica := IF(
  @exists_indica = 0,
  'ALTER TABLE ocupacional_ordenes ADD COLUMN indica_dr VARCHAR(255) NULL AFTER documento',
  'SELECT "indica_dr exists"'
);
PREPARE stmt_indica FROM @sql_indica;
EXECUTE stmt_indica;
DEALLOCATE PREPARE stmt_indica;

SET @idx_subcontrata := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND index_name = 'idx_ocup_orden_subcontrata'
);
SET @sql_idx_subcontrata := IF(
  @idx_subcontrata = 0,
  'ALTER TABLE ocupacional_ordenes ADD KEY idx_ocup_orden_subcontrata (subcontrata_empresa_id)',
  'SELECT "idx_ocup_orden_subcontrata exists"'
);
PREPARE stmt_idx_subcontrata FROM @sql_idx_subcontrata;
EXECUTE stmt_idx_subcontrata;
DEALLOCATE PREPARE stmt_idx_subcontrata;

SET @idx_facturar := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = @schema_name
    AND table_name = 'ocupacional_ordenes'
    AND index_name = 'idx_ocup_orden_facturar'
);
SET @sql_idx_facturar := IF(
  @idx_facturar = 0,
  'ALTER TABLE ocupacional_ordenes ADD KEY idx_ocup_orden_facturar (facturar_empresa_id)',
  'SELECT "idx_ocup_orden_facturar exists"'
);
PREPARE stmt_idx_facturar FROM @sql_idx_facturar;
EXECUTE stmt_idx_facturar;
DEALLOCATE PREPARE stmt_idx_facturar;

-- =====================================================
-- END: sql/2026-07-24_ocupacional_fase3_ordenes_campos_legacy_header_idempotente.sql
-- =====================================================

-- VALIDACIONES DB SO
SELECT 'tabla_empresas_ocupacionales' AS check_name, COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='empresas_ocupacionales';
SELECT 'tabla_ocupacional_ordenes' AS check_name, COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='ocupacional_ordenes';
SELECT 'tabla_ocupacional_grupos_examenes' AS check_name, COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='ocupacional_grupos_examenes';
SELECT 'grupos_subgrupos_total' AS check_name, COUNT(*) AS total FROM ocupacional_grupos_examenes;
