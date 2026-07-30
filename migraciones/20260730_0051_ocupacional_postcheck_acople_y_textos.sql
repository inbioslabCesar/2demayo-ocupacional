-- 20260730_0051_ocupacional_postcheck_acople_y_textos.sql
-- Verificacion posterior a 0040 + 0050

SELECT DATABASE() AS db_actual;

SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'origen_datos';
SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'laboratorio_examen_id';
SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'laboratorio_version_id';
SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'laboratorio_snapshot_json';

SELECT codigo, descripcion, grupo, subgrupo
FROM ocupacional_examenes_generales
WHERE codigo IN ('LAB_0001','LAB_0002')
ORDER BY codigo;

SELECT
  SUM(CASE WHEN HEX(descripcion) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_descripcion,
  SUM(CASE WHEN HEX(grupo) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_grupo,
  SUM(CASE WHEN HEX(subgrupo) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_subgrupo,
  SUM(CASE WHEN HEX(valores_normales) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_valores_normales
FROM ocupacional_examenes_generales;
