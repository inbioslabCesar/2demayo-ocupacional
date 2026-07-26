-- ============================================================================
-- HOSTINGER PRODUCCION - CATALOGO BASE DE TRIAJE OCUPACIONAL
-- Destino esperado: u330560936_so2demayo
-- Ejecutar despues de 20260725_0027_ocupacional_hoja_ruta_snapshots.sql.
-- No vincula el examen a empresas o protocolos ni modifica montos existentes.
-- ============================================================================

SET NAMES utf8mb4;
USE `u330560936_so2demayo`;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada, 'OK' AS preflight_base;

INSERT INTO ocupacional_grupos_examenes
  (nombre, parent_id, orden, estado, created_by, updated_by)
VALUES
  ('TRIAJE', 0, 9, 'activo', NULL, NULL)
ON DUPLICATE KEY UPDATE
  orden = VALUES(orden),
  estado = 'activo',
  updated_at = CURRENT_TIMESTAMP;

SET @triaje_grupo_id := (
  SELECT id
  FROM ocupacional_grupos_examenes
  WHERE parent_id = 0
    AND UPPER(TRIM(nombre)) = 'TRIAJE'
  LIMIT 1
);

INSERT INTO ocupacional_grupos_examenes
  (nombre, parent_id, orden, estado, created_by, updated_by)
VALUES
  ('Signos vitales', @triaje_grupo_id, 1, 'activo', NULL, NULL)
ON DUPLICATE KEY UPDATE
  orden = VALUES(orden),
  estado = 'activo',
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO ocupacional_examenes_generales
  (codigo, descripcion, grupo, subgrupo, valores_normales, precio, posicion, estado, created_by, updated_by)
VALUES
  ('TRI_0001', 'Triaje', 'TRIAJE', 'Signos vitales', 'Registro de signos vitales ocupacionales', 0.00, 1, 'activo', NULL, NULL)
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
WHERE g.id = @triaje_grupo_id
  OR (g.parent_id = @triaje_grupo_id AND UPPER(TRIM(g.nombre)) = 'SIGNOS VITALES')
ORDER BY g.parent_id, g.orden, g.id;

SELECT id, codigo, descripcion, grupo, subgrupo, precio, posicion, estado
FROM ocupacional_examenes_generales
WHERE codigo = 'TRI_0001';

SELECT 'FIN CATALOGO BASE TRIAJE' AS resultado, DATABASE() AS base_aplicada;