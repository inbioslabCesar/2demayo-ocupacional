-- Fase 2 Salud Ocupacional: Protocolos por empresa y matriz de montos por tipo de evaluacion
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

CREATE TABLE IF NOT EXISTS ocupacional_tipos_evaluacion (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_tipos_eval_codigo (codigo),
  KEY idx_ocup_tipos_eval_estado_orden (estado, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ocupacional_tipos_evaluacion (codigo, nombre, orden, estado)
VALUES
  ('PRE', 'PRE OCUPACIONAL', 1, 'activo'),
  ('PER', 'PERIODICO', 2, 'activo'),
  ('POST', 'POST OCUPACIONAL', 3, 'activo')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  orden = VALUES(orden),
  estado = VALUES(estado);

CREATE TABLE IF NOT EXISTS ocupacional_protocolos_empresa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id INT UNSIGNED NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_proto_empresa_desc (empresa_id, descripcion),
  KEY idx_ocup_proto_empresa_estado (empresa_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_protocolo_detalle (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocolo_id INT UNSIGNED NOT NULL,
  catalogo_id INT UNSIGNED NOT NULL,
  tipo_evaluacion_id INT UNSIGNED NOT NULL,
  monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_proto_detalle (protocolo_id, catalogo_id, tipo_evaluacion_id),
  KEY idx_ocup_proto_detalle_proto (protocolo_id),
  KEY idx_ocup_proto_detalle_catalogo (catalogo_id),
  KEY idx_ocup_proto_detalle_tipo (tipo_evaluacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
