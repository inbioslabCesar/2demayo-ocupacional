-- Fase 3 Salud Ocupacional: cierre formal y auditoria de ordenes
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

ALTER TABLE ocupacional_ordenes
  MODIFY COLUMN estado ENUM('emitida','en_proceso','completada','cerrada','anulada') NOT NULL DEFAULT 'emitida';

CREATE TABLE IF NOT EXISTS ocupacional_orden_eventos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  tipo_evento VARCHAR(40) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  payload_json JSON NULL,
  created_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_orden_evt_orden (orden_id),
  KEY idx_ocup_orden_evt_tipo (tipo_evento),
  KEY idx_ocup_orden_evt_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
