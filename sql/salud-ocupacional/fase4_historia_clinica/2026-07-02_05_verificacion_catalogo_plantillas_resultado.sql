-- Verificacion post despliegue: catalogo de plantillas clinicas ocupacionales

SELECT
  TABLE_NAME,
  ENGINE,
  TABLE_COLLATION
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'ocupacional_plantillas_resultado';

SHOW INDEX FROM ocupacional_plantillas_resultado;

SELECT
  COUNT(*) AS total_plantillas,
  SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS activas,
  SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) AS inactivas
FROM ocupacional_plantillas_resultado;
