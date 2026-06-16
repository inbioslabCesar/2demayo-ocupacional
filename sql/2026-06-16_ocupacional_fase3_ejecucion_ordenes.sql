-- Fase 3 Salud Ocupacional: ejecucion de examenes por orden
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

ALTER TABLE ocupacional_ordenes
  MODIFY COLUMN estado ENUM('emitida','en_proceso','completada','anulada') NOT NULL DEFAULT 'emitida';

ALTER TABLE ocupacional_orden_detalle
  ADD COLUMN estado_ejecucion ENUM('pendiente','en_proceso','realizado','observado') NOT NULL DEFAULT 'pendiente' AFTER monto,
  ADD COLUMN observacion_ejecucion VARCHAR(255) NULL AFTER estado_ejecucion,
  ADD COLUMN fecha_ejecucion DATETIME NULL AFTER observacion_ejecucion,
  ADD COLUMN updated_by INT NULL AFTER fecha_ejecucion,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by;

UPDATE ocupacional_orden_detalle
SET estado_ejecucion = 'pendiente'
WHERE estado_ejecucion IS NULL;
