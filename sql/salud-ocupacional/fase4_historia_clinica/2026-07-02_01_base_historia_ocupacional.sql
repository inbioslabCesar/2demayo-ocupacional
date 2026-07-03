-- Fase 4 Salud Ocupacional: base de historia ocupacional y resultados clinicos
-- Entorno: desarrollo (ejecutar primero aqui) y luego produccion por script
-- Script idempotente: usa CREATE TABLE IF NOT EXISTS

CREATE TABLE IF NOT EXISTS ocupacional_historia_ocupacional (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  empresa_id INT UNSIGNED NOT NULL,
  trabajador_id INT UNSIGNED NOT NULL,
  protocolo_id INT UNSIGNED NULL,
  tipo_evaluacion_id INT UNSIGNED NULL,
  motivo_evaluacion VARCHAR(180) NULL,
  puesto_actual VARCHAR(180) NULL,
  area_trabajo VARCHAR(180) NULL,
  tiempo_puesto_meses SMALLINT UNSIGNED NULL,
  antecedentes_laborales_json LONGTEXT NULL,
  antecedentes_patologicos_json LONGTEXT NULL,
  habitos_json LONGTEXT NULL,
  observaciones TEXT NULL,
  estado ENUM('activo','anulado','cerrado') NOT NULL DEFAULT 'activo',
  anulado_motivo VARCHAR(255) NULL,
  anulado_by INT NULL,
  anulado_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_historia_orden (orden_id),
  KEY idx_ocup_historia_empresa_fecha (empresa_id, created_at),
  KEY idx_ocup_historia_trabajador (trabajador_id),
  KEY idx_ocup_historia_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_resultados_clinicos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_detalle_id INT UNSIGNED NOT NULL,
  orden_id INT UNSIGNED NOT NULL,
  examen_id INT UNSIGNED NOT NULL,
  formato_codigo VARCHAR(40) NOT NULL,
  datos_json LONGTEXT NULL,
  estado ENUM('borrador','finalizado','anulado') NOT NULL DEFAULT 'borrador',
  ejecutado_by INT NULL,
  validado_by INT NULL,
  validado_at DATETIME NULL,
  observacion VARCHAR(255) NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_resultado_detalle_formato (orden_detalle_id, formato_codigo),
  KEY idx_ocup_resultado_orden (orden_id),
  KEY idx_ocup_resultado_examen (examen_id),
  KEY idx_ocup_resultado_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
