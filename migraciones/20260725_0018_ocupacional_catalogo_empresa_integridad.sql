-- Integridad del catalogo de examenes por empresa.
-- Alinea empresa_id con su tabla padre y agrega claves foraneas sin borrar datos.

SET @db_name := DATABASE();

SET @empresa_id_type := (
  SELECT COLUMN_TYPE
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'ocupacional_catalogo_empresas'
    AND column_name = 'empresa_id'
  LIMIT 1
);
SET @sql := IF(
  @empresa_id_type <> 'bigint unsigned',
  'ALTER TABLE ocupacional_catalogo_empresas MODIFY COLUMN empresa_id BIGINT UNSIGNED NOT NULL',
  'SELECT "skip ocupacional_catalogo_empresas.empresa_id"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @orphan_empresas := (
  SELECT COUNT(*)
  FROM ocupacional_catalogo_empresas c
  LEFT JOIN empresas_ocupacionales e ON e.id = c.empresa_id
  WHERE e.id IS NULL
);
SET @fk_empresa_exists := (
  SELECT COUNT(*)
  FROM information_schema.referential_constraints
  WHERE BINARY constraint_schema = BINARY @db_name
    AND BINARY constraint_name = BINARY 'fk_cat_empresa'
);
SET @sql := IF(
  @fk_empresa_exists = 0,
  'ALTER TABLE ocupacional_catalogo_empresas ADD CONSTRAINT fk_cat_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "skip fk_cat_empresa"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @orphan_examenes := (
  SELECT COUNT(*)
  FROM ocupacional_catalogo_empresas c
  LEFT JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
  WHERE e.id IS NULL
);
SET @fk_examen_exists := (
  SELECT COUNT(*)
  FROM information_schema.referential_constraints
  WHERE BINARY constraint_schema = BINARY @db_name
    AND BINARY constraint_name = BINARY 'fk_cat_examen'
);
SET @sql := IF(
  @fk_examen_exists = 0,
  'ALTER TABLE ocupacional_catalogo_empresas ADD CONSTRAINT fk_cat_examen FOREIGN KEY (examen_id) REFERENCES ocupacional_examenes_generales(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT "skip fk_cat_examen"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT
  @orphan_empresas AS catalogos_empresa_huerfana,
  @orphan_examenes AS catalogos_examen_huerfano;
