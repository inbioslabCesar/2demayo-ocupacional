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
