-- Fase 3 Salud Ocupacional: Ordenes ocupacionales y detalle de examenes
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

CREATE TABLE IF NOT EXISTS ocupacional_ordenes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NULL,
  empresa_id INT UNSIGNED NOT NULL,
  trabajador_id INT UNSIGNED NOT NULL,
  protocolo_id INT UNSIGNED NOT NULL,
  tipo_evaluacion_id INT UNSIGNED NOT NULL,
  fecha_orden DATE NOT NULL,
  estado ENUM('emitida','anulada') NOT NULL DEFAULT 'emitida',
  monto_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  observacion VARCHAR(255) NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_orden_codigo (codigo),
  KEY idx_ocup_orden_empresa_fecha (empresa_id, fecha_orden),
  KEY idx_ocup_orden_trabajador (trabajador_id),
  KEY idx_ocup_orden_protocolo_tipo (protocolo_id, tipo_evaluacion_id),
  KEY idx_ocup_orden_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_orden_detalle (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  catalogo_id INT UNSIGNED NOT NULL,
  examen_id INT UNSIGNED NOT NULL,
  examen_codigo VARCHAR(40) NOT NULL,
  examen_descripcion VARCHAR(200) NOT NULL,
  monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_orden_detalle_orden (orden_id),
  KEY idx_ocup_orden_detalle_catalogo (catalogo_id),
  KEY idx_ocup_orden_detalle_examen (examen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
