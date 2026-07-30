-- ============================================================================
-- CATALOGO BASE DE PSICOLOGIA OCUPACIONAL
-- Ejecutar despues de 20260726_0028_ocupacional_triaje_catalogo.sql.
-- No vincula el examen a empresas o protocolos ni modifica ordenes existentes.
-- ============================================================================

SET NAMES utf8mb4;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada, 'OK' AS preflight_base;

INSERT INTO ocupacional_grupos_examenes
  (nombre, parent_id, orden, estado, created_by, updated_by)
VALUES
  ('PSICOLOGIA', 0, 5, 'activo', NULL, NULL)
ON DUPLICATE KEY UPDATE
  orden = VALUES(orden),
  estado = 'activo',
  updated_at = CURRENT_TIMESTAMP;

SET @psicologia_grupo_id := (
  SELECT id
  FROM ocupacional_grupos_examenes
  WHERE parent_id = 0
    AND UPPER(TRIM(nombre)) = 'PSICOLOGIA'
  LIMIT 1
);

INSERT INTO ocupacional_grupos_examenes
  (nombre, parent_id, orden, estado, created_by, updated_by)
VALUES
  ('Evaluacion psicologica ocupacional', @psicologia_grupo_id, 1, 'activo', NULL, NULL)
ON DUPLICATE KEY UPDATE
  orden = VALUES(orden),
  estado = 'activo',
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO ocupacional_examenes_generales
  (codigo, descripcion, grupo, subgrupo, valores_normales, precio, posicion, estado, created_by, updated_by)
VALUES
  (
    'PSI_0001',
    'Evaluacion psicologica ocupacional',
    'PSICOLOGIA',
    'Evaluacion psicologica ocupacional',
    'Informe psicologico ocupacional estructurado',
    0.00,
    1,
    'activo',
    NULL,
    NULL
  )
ON DUPLICATE KEY UPDATE
  descripcion = VALUES(descripcion),
  grupo = VALUES(grupo),
  subgrupo = VALUES(subgrupo),
  valores_normales = VALUES(valores_normales),
  posicion = VALUES(posicion),
  estado = 'activo',
  updated_at = CURRENT_TIMESTAMP;

SELECT g.id, g.nombre, g.parent_id, g.orden, g.estado,
       p.nombre AS grupo_padre
FROM ocupacional_grupos_examenes g
LEFT JOIN ocupacional_grupos_examenes p ON p.id = g.parent_id
WHERE g.id = @psicologia_grupo_id
   OR (
     g.parent_id = @psicologia_grupo_id
     AND UPPER(TRIM(g.nombre)) = 'EVALUACION PSICOLOGICA OCUPACIONAL'
   )
ORDER BY g.parent_id, g.orden, g.id;

SELECT id, codigo, descripcion, grupo, subgrupo, precio, posicion, estado
FROM ocupacional_examenes_generales
WHERE codigo = 'PSI_0001';

SELECT 'FIN CATALOGO BASE PSICOLOGIA' AS resultado, @db_name AS base_aplicada;
