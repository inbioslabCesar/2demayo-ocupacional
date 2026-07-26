-- Reconciliacion de estados de ordenes ocupacionales abiertas.
-- Una orden solo queda completada cuando cada examen tiene resultado finalizado.
-- Las ordenes cerradas y anuladas se preservan para revision historica controlada.

DROP TEMPORARY TABLE IF EXISTS tmp_ocupacional_estado_clinico;

CREATE TEMPORARY TABLE tmp_ocupacional_estado_clinico AS
SELECT
  o.id AS orden_id,
  CASE
    WHEN COUNT(d.id) > 0
      AND SUM(
        CASE
          WHEN d.estado_ejecucion = 'realizado'
            AND EXISTS (
              SELECT 1
              FROM ocupacional_resultados_clinicos rc
              WHERE rc.orden_detalle_id = d.id
                AND rc.estado = 'finalizado'
            )
          THEN 1 ELSE 0
        END
      ) = COUNT(d.id)
      THEN 'completada'
    WHEN SUM(CASE WHEN d.estado_ejecucion <> 'pendiente' THEN 1 ELSE 0 END) > 0
      THEN 'en_proceso'
    ELSE 'emitida'
  END AS estado_calculado
FROM ocupacional_ordenes o
LEFT JOIN ocupacional_orden_detalle d ON d.orden_id = o.id
WHERE o.estado NOT IN ('cerrada', 'anulada')
GROUP BY o.id;

UPDATE ocupacional_ordenes o
INNER JOIN tmp_ocupacional_estado_clinico t ON t.orden_id = o.id
SET
  o.estado = t.estado_calculado,
  o.updated_at = CURRENT_TIMESTAMP
WHERE BINARY o.estado <> BINARY t.estado_calculado;

SET @ordenes_reconciliadas := ROW_COUNT();

DROP TEMPORARY TABLE IF EXISTS tmp_ocupacional_estado_clinico;

SELECT @ordenes_reconciliadas AS ordenes_reconciliadas;