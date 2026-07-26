-- ============================================================================
-- HOSTINGER PRODUCCION - BASE OCUPACIONAL
-- Destino esperado: u330560936_so2demayo
-- Compatible con instalacion nueva o parcial. Selecciona la BD exacta.
-- No elimina tablas, no trunca datos y no contiene FKs hacia la BD clinica.
-- ============================================================================

SET NAMES utf8mb4;
USE `u330560936_so2demayo`;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada,
        'OK' AS preflight_base;

CREATE TABLE IF NOT EXISTS empresas_ocupacionales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ruc CHAR(11) NOT NULL,
  razon_social VARCHAR(200) NOT NULL,
  nombre_comercial VARCHAR(200) NULL,
  actividad VARCHAR(200) NULL,
  direccion VARCHAR(255) NULL,
  departamento VARCHAR(120) NULL,
  provincia VARCHAR(120) NULL,
  distrito VARCHAR(120) NULL,
  telefono VARCHAR(30) NULL,
  telefono_1 VARCHAR(30) NULL,
  telefono_2 VARCHAR(30) NULL,
  contacto_1 VARCHAR(160) NULL,
  contacto_2 VARCHAR(160) NULL,
  correo VARCHAR(120) NULL,
  correo_1 VARCHAR(120) NULL,
  correo_2 VARCHAR(120) NULL,
  rrhh_usuario VARCHAR(80) NULL,
  rrhh_password VARCHAR(120) NULL,
  doctor_usuario VARCHAR(80) NULL,
  doctor_password VARCHAR(120) NULL,
  formato_principal VARCHAR(40) NULL,
  formato_certificado VARCHAR(40) NULL,
  observacion TEXT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_empresas_ocupacionales_ruc (ruc),
  KEY idx_empresas_ocupacionales_estado (estado),
  KEY idx_empresas_ocupacionales_razon_social (razon_social),
  KEY idx_emp_estado_created (estado, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacientes_ocupacionales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  external_patient_id BIGINT UNSIGNED NOT NULL,
  documento_tipo VARCHAR(20) NOT NULL DEFAULT 'DNI',
  documento_numero VARCHAR(15) NOT NULL,
  puesto_trabajo VARCHAR(180) NOT NULL,
  area_riesgo VARCHAR(120) NULL,
  tipo_contrato VARCHAR(60) NULL,
  estado_laboral ENUM('activo','retirado','anulado') NOT NULL DEFAULT 'activo',
  anulacion_motivo VARCHAR(255) NULL,
  anulado_at DATETIME NULL,
  anulado_by BIGINT UNSIGNED NULL,
  fecha_ingreso DATE NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_empresa_external_patient (empresa_id, external_patient_id),
  KEY idx_pacientes_ocupacionales_external (external_patient_id),
  KEY idx_pacientes_ocupacionales_documento (documento_numero),
  KEY idx_pacientes_ocupacionales_doc_tipo_num (documento_tipo, documento_numero),
  KEY idx_pacientes_ocupacionales_empresa_estado (empresa_id, estado_laboral),
  KEY idx_pac_estado_fecha (estado_laboral, fecha_ingreso),
  KEY idx_pac_empresa_fecha (empresa_id, fecha_ingreso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_examenes_generales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  descripcion VARCHAR(160) NOT NULL,
  grupo VARCHAR(100) NOT NULL DEFAULT '',
  subgrupo VARCHAR(100) NULL,
  valores_normales TEXT NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  posicion INT NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_examenes_codigo (codigo),
  KEY idx_ocup_examenes_estado (estado),
  KEY idx_ocup_examenes_descripcion (descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_catalogo_empresas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  examen_id INT UNSIGNED NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_catalogo_empresa_examen (empresa_id, examen_id),
  KEY idx_ocup_catalogo_empresa (empresa_id),
  KEY idx_ocup_catalogo_examen (examen_id),
  KEY idx_ocup_catalogo_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO ocupacional_tipos_evaluacion (codigo, nombre, orden, estado) VALUES
  ('PRE', 'PRE OCUPACIONAL', 1, 'activo'),
  ('PER', 'PERIODICO', 2, 'activo'),
  ('POST', 'POST OCUPACIONAL', 3, 'activo'),
  ('EXAMCOMP', 'EXAMENES COMPLEMENTARIOS', 4, 'activo')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), orden = VALUES(orden);

CREATE TABLE IF NOT EXISTS ocupacional_protocolos_empresa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
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

CREATE TABLE IF NOT EXISTS ocupacional_protocolo_condiciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocolo_id INT UNSIGNED NOT NULL,
  catalogo_id INT UNSIGNED NOT NULL,
  puesto_trabajo VARCHAR(120) NULL,
  sexo ENUM('M','F') NULL,
  edad_min TINYINT UNSIGNED NULL,
  edad_max TINYINT UNSIGNED NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_cond_proto_catalogo (protocolo_id, catalogo_id),
  KEY idx_ocup_cond_puesto (puesto_trabajo),
  KEY idx_ocup_cond_sexo (sexo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_ordenes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NULL,
  empresa_id BIGINT UNSIGNED NOT NULL,
  subcontrata_empresa_id BIGINT UNSIGNED NULL,
  facturar_empresa_id BIGINT UNSIGNED NULL,
  trabajador_id BIGINT UNSIGNED NOT NULL,
  gestante TINYINT(1) NOT NULL DEFAULT 0,
  protocolo_id INT UNSIGNED NOT NULL,
  tipo_evaluacion_id INT UNSIGNED NOT NULL,
  firma_doctor VARCHAR(80) NULL,
  modo VARCHAR(30) NULL,
  fecha_orden DATE NOT NULL,
  estado ENUM('emitida','en_proceso','completada','cerrada','anulada') NOT NULL DEFAULT 'emitida',
  monto_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  observacion VARCHAR(255) NULL,
  documento VARCHAR(50) NULL,
  indica_dr VARCHAR(255) NULL,
  aptitud_final ENUM('APTO','APTO_CON_RESTRICCIONES','NO_APTO') NULL,
  restriccion_final VARCHAR(255) NULL,
  recomendacion_final VARCHAR(255) NULL,
  medico_responsable VARCHAR(150) NULL,
  medico_responsable_id INT NULL,
  medico_nombre_snapshot VARCHAR(220) NULL,
  medico_especialidad_snapshot VARCHAR(150) NULL,
  medico_cmp_snapshot VARCHAR(30) NULL,
  medico_rne_snapshot VARCHAR(30) NULL,
  medico_rna_snapshot VARCHAR(30) NULL,
  medico_firma_snapshot LONGTEXT NULL,
  aptitud_registrada_by INT NULL,
  aptitud_registrada_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_orden_codigo (codigo),
  KEY idx_ocup_orden_empresa_fecha (empresa_id, fecha_orden),
  KEY idx_ocup_orden_trabajador (trabajador_id),
  KEY idx_ocup_orden_protocolo_tipo (protocolo_id, tipo_evaluacion_id),
  KEY idx_ocup_orden_estado (estado),
  KEY idx_ocup_orden_subcontrata (subcontrata_empresa_id),
  KEY idx_ocup_orden_facturar (facturar_empresa_id),
  KEY idx_ocup_orden_medico_responsable (medico_responsable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_orden_detalle (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  catalogo_id INT UNSIGNED NOT NULL,
  examen_id INT UNSIGNED NOT NULL,
  examen_codigo VARCHAR(40) NOT NULL,
  examen_descripcion VARCHAR(200) NOT NULL,
  monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  estado_ejecucion ENUM('pendiente','en_proceso','realizado','observado') NOT NULL DEFAULT 'pendiente',
  observacion_ejecucion VARCHAR(255) NULL,
  fecha_ejecucion DATETIME NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_orden_detalle_orden (orden_id),
  KEY idx_ocup_orden_detalle_catalogo (catalogo_id),
  KEY idx_ocup_orden_detalle_examen (examen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_orden_eventos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  tipo_evento VARCHAR(40) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  payload_json LONGTEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_orden_evt_orden (orden_id),
  KEY idx_ocup_orden_evt_tipo (tipo_evento),
  KEY idx_ocup_orden_evt_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_historia_ocupacional (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  empresa_id BIGINT UNSIGNED NOT NULL,
  trabajador_id BIGINT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_ocup_resultado_detalle_formato (orden_detalle_id, formato_codigo),
  KEY idx_ocup_resultado_orden (orden_id),
  KEY idx_ocup_resultado_examen (examen_id),
  KEY idx_ocup_resultado_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_historia_auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entidad ENUM('historia_ocupacional','resultado_clinico') NOT NULL,
  registro_id INT UNSIGNED NOT NULL,
  orden_id INT UNSIGNED NOT NULL,
  accion ENUM('insert','update','annul','close') NOT NULL,
  payload_json LONGTEXT NULL,
  actor_id INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_hist_audit_entidad_registro (entidad, registro_id),
  KEY idx_ocup_hist_audit_orden_fecha (orden_id, created_at),
  KEY idx_ocup_hist_audit_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_plantillas_resultado (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(60) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  template_code VARCHAR(50) NOT NULL,
  examen_codigo VARCHAR(60) NULL,
  formato_codigo VARCHAR(40) NULL,
  datos_json LONGTEXT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_plantilla_codigo (codigo),
  KEY idx_ocup_plantilla_template (template_code),
  KEY idx_ocup_plantilla_examen (examen_codigo),
  KEY idx_ocup_plantilla_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_grupos_examenes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  parent_id INT UNSIGNED NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_grupos_parent_nombre (parent_id, nombre),
  KEY idx_ocup_grupos_parent_estado (parent_id, estado),
  KEY idx_ocup_grupos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_catalogos_laborales_empresa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('area','puesto') NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ocup_catalogo_laboral_empresa_tipo_nombre (empresa_id, tipo, nombre),
  KEY idx_ocup_catalogo_laboral_empresa_tipo_estado (empresa_id, tipo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ocupacional_interconsultas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  orden_detalle_id INT UNSIGNED NOT NULL,
  especialidad VARCHAR(120) NOT NULL,
  motivo TEXT NOT NULL,
  diagnostico_cie10 VARCHAR(20) NULL,
  diagnostico_descripcion VARCHAR(255) NULL,
  observaciones TEXT NULL,
  estado ENUM('solicitada','respondida','levantada','anulada') NOT NULL DEFAULT 'solicitada',
  especialista_nombre VARCHAR(180) NULL,
  respuesta TEXT NULL,
  respuesta_documento VARCHAR(255) NULL,
  respuesta_at DATETIME NULL,
  respondida_by INT NULL,
  levantamiento TEXT NULL,
  recomendacion TEXT NULL,
  resultado_levantamiento ENUM('FAVORABLE','NO_FAVORABLE') NULL,
  medico_levantamiento_id INT NULL,
  medico_levantamiento_nombre_snapshot VARCHAR(220) NULL,
  medico_levantamiento_cmp_snapshot VARCHAR(30) NULL,
  levantamiento_at DATETIME NULL,
  levantada_by INT NULL,
  anulacion_motivo VARCHAR(255) NULL,
  anulada_at DATETIME NULL,
  anulada_by INT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_inter_orden (orden_id),
  KEY idx_ocup_inter_detalle_estado (orden_detalle_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actualizacion idempotente de instalaciones parciales.
ALTER TABLE pacientes_ocupacionales
  MODIFY COLUMN estado_laboral ENUM('activo','retirado','anulado') NOT NULL DEFAULT 'activo';

ALTER TABLE ocupacional_ordenes
  MODIFY COLUMN estado ENUM('emitida','en_proceso','completada','cerrada','anulada') NOT NULL DEFAULT 'emitida';

ALTER TABLE ocupacional_protocolos_empresa MODIFY COLUMN empresa_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE ocupacional_catalogo_empresas MODIFY COLUMN empresa_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE ocupacional_ordenes MODIFY COLUMN empresa_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE ocupacional_ordenes MODIFY COLUMN trabajador_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE ocupacional_historia_ocupacional MODIFY COLUMN empresa_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE ocupacional_historia_ocupacional MODIFY COLUMN trabajador_id BIGINT UNSIGNED NOT NULL;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes_ocupacionales' AND column_name='anulacion_motivo')=0,
  'ALTER TABLE pacientes_ocupacionales ADD COLUMN anulacion_motivo VARCHAR(255) NULL AFTER estado_laboral','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes_ocupacionales' AND column_name='anulado_at')=0,
  'ALTER TABLE pacientes_ocupacionales ADD COLUMN anulado_at DATETIME NULL AFTER anulacion_motivo','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='pacientes_ocupacionales' AND column_name='anulado_by')=0,
  'ALTER TABLE pacientes_ocupacionales ADD COLUMN anulado_by BIGINT UNSIGNED NULL AFTER anulado_at','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='estado_ejecucion')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN estado_ejecucion ENUM(''pendiente'',''en_proceso'',''realizado'',''observado'') NOT NULL DEFAULT ''pendiente'' AFTER monto','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='observacion_ejecucion')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN observacion_ejecucion VARCHAR(255) NULL AFTER estado_ejecucion','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='fecha_ejecucion')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN fecha_ejecucion DATETIME NULL AFTER observacion_ejecucion','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='updated_by')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN updated_by INT NULL AFTER fecha_ejecucion','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='updated_at')=0,
  'ALTER TABLE ocupacional_orden_detalle ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by','SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Columnas de orden agregadas despues de las fases base.
SET @cols := 'subcontrata_empresa_id,facturar_empresa_id,gestante,firma_doctor,modo,documento,indica_dr,aptitud_final,restriccion_final,recomendacion_final,medico_responsable,medico_responsable_id,medico_nombre_snapshot,medico_especialidad_snapshot,medico_cmp_snapshot,medico_rne_snapshot,medico_rna_snapshot,medico_firma_snapshot,aptitud_registrada_by,aptitud_registrada_at';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='subcontrata_empresa_id')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN subcontrata_empresa_id BIGINT UNSIGNED NULL AFTER empresa_id','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='facturar_empresa_id')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN facturar_empresa_id BIGINT UNSIGNED NULL AFTER subcontrata_empresa_id','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='gestante')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN gestante TINYINT(1) NOT NULL DEFAULT 0 AFTER trabajador_id','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='firma_doctor')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN firma_doctor VARCHAR(80) NULL AFTER tipo_evaluacion_id','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='modo')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN modo VARCHAR(30) NULL AFTER firma_doctor','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='documento')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN documento VARCHAR(50) NULL AFTER observacion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='indica_dr')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN indica_dr VARCHAR(255) NULL AFTER documento','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='aptitud_final')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN aptitud_final ENUM(''APTO'',''APTO_CON_RESTRICCIONES'',''NO_APTO'') NULL AFTER indica_dr','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='restriccion_final')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN restriccion_final VARCHAR(255) NULL AFTER aptitud_final','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='recomendacion_final')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN recomendacion_final VARCHAR(255) NULL AFTER restriccion_final','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_responsable')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_responsable VARCHAR(150) NULL AFTER recomendacion_final','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_responsable_id')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_responsable_id INT NULL AFTER medico_responsable','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_nombre_snapshot')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_nombre_snapshot VARCHAR(220) NULL AFTER medico_responsable_id','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_especialidad_snapshot')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_especialidad_snapshot VARCHAR(150) NULL AFTER medico_nombre_snapshot','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_cmp_snapshot')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_cmp_snapshot VARCHAR(30) NULL AFTER medico_especialidad_snapshot','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_rne_snapshot')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_rne_snapshot VARCHAR(30) NULL AFTER medico_cmp_snapshot','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_rna_snapshot')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_rna_snapshot VARCHAR(30) NULL AFTER medico_rne_snapshot','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='medico_firma_snapshot')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN medico_firma_snapshot LONGTEXT NULL AFTER medico_rna_snapshot','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='aptitud_registrada_by')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN aptitud_registrada_by INT NULL AFTER medico_firma_snapshot','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='aptitud_registrada_at')=0,'ALTER TABLE ocupacional_ordenes ADD COLUMN aptitud_registrada_at DATETIME NULL AFTER aptitud_registrada_by','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Campos legacy de empresa para instalaciones que ya tenian la tabla base.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='nombre_comercial')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN nombre_comercial VARCHAR(200) NULL AFTER razon_social','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='actividad')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN actividad VARCHAR(200) NULL AFTER nombre_comercial','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='departamento')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN departamento VARCHAR(120) NULL AFTER direccion','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='provincia')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN provincia VARCHAR(120) NULL AFTER departamento','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='distrito')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN distrito VARCHAR(120) NULL AFTER provincia','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='telefono_1')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN telefono_1 VARCHAR(30) NULL AFTER telefono','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='telefono_2')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN telefono_2 VARCHAR(30) NULL AFTER telefono_1','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='contacto_1')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN contacto_1 VARCHAR(160) NULL AFTER telefono_2','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='contacto_2')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN contacto_2 VARCHAR(160) NULL AFTER contacto_1','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='correo_1')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN correo_1 VARCHAR(120) NULL AFTER correo','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='correo_2')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN correo_2 VARCHAR(120) NULL AFTER correo_1','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='rrhh_usuario')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN rrhh_usuario VARCHAR(80) NULL AFTER correo_2','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='rrhh_password')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN rrhh_password VARCHAR(120) NULL AFTER rrhh_usuario','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='doctor_usuario')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN doctor_usuario VARCHAR(80) NULL AFTER rrhh_password','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='doctor_password')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN doctor_password VARCHAR(120) NULL AFTER doctor_usuario','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='formato_principal')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN formato_principal VARCHAR(40) NULL AFTER doctor_password','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='formato_certificado')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN formato_certificado VARCHAR(40) NULL AFTER formato_principal','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='empresas_ocupacionales' AND column_name='observacion')=0,'ALTER TABLE empresas_ocupacionales ADD COLUMN observacion TEXT NULL AFTER formato_certificado','SELECT 1'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE empresas_ocupacionales SET
  telefono_1 = COALESCE(NULLIF(telefono_1,''), NULLIF(telefono,'')),
  correo_1 = COALESCE(NULLIF(correo_1,''), NULLIF(correo,''));

INSERT IGNORE INTO ocupacional_grupos_examenes (nombre, parent_id, estado)
SELECT DISTINCT TRIM(grupo), 0, 'activo' FROM ocupacional_examenes_generales
WHERE grupo IS NOT NULL AND TRIM(grupo) <> '';

INSERT IGNORE INTO ocupacional_grupos_examenes (nombre, parent_id, estado)
SELECT DISTINCT TRIM(e.subgrupo), g.id, 'activo'
FROM ocupacional_examenes_generales e
INNER JOIN ocupacional_grupos_examenes g
  ON g.parent_id=0 AND UPPER(g.nombre)=UPPER(TRIM(e.grupo))
WHERE e.subgrupo IS NOT NULL AND TRIM(e.subgrupo) <> '';

INSERT IGNORE INTO ocupacional_catalogos_laborales_empresa (empresa_id, tipo, nombre, estado)
SELECT DISTINCT empresa_id, 'puesto', TRIM(puesto_trabajo), 'activo'
FROM pacientes_ocupacionales WHERE TRIM(COALESCE(puesto_trabajo,'')) <> '';

INSERT IGNORE INTO ocupacional_catalogos_laborales_empresa (empresa_id, tipo, nombre, estado)
SELECT DISTINCT empresa_id, 'area', TRIM(area_riesgo), 'activo'
FROM pacientes_ocupacionales
WHERE TRIM(COALESCE(area_riesgo,'')) <> ''
  AND LOWER(TRIM(area_riesgo)) NOT IN ('bajo','medio','alto');

-- FKs internas. Si existen huerfanos se informa y se omite la FK afectada.
SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='pacientes_ocupacionales' AND column_name='empresa_id' AND referenced_table_name='empresas_ocupacionales');
SET @orph := (SELECT COUNT(*) FROM pacientes_ocupacionales p LEFT JOIN empresas_ocupacionales e ON e.id=p.empresa_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE pacientes_ocupacionales ADD CONSTRAINT fk_pacientes_ocupacionales_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk pacientes_empresa: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_protocolos_empresa' AND column_name='empresa_id' AND referenced_table_name='empresas_ocupacionales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_protocolos_empresa p LEFT JOIN empresas_ocupacionales e ON e.id=p.empresa_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_protocolos_empresa ADD CONSTRAINT fk_proto_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk protocolos_empresa: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_catalogo_empresas' AND column_name='empresa_id' AND referenced_table_name='empresas_ocupacionales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_catalogo_empresas c LEFT JOIN empresas_ocupacionales e ON e.id=c.empresa_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_catalogo_empresas ADD CONSTRAINT fk_cat_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk catalogo_empresa: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_catalogo_empresas' AND column_name='examen_id' AND referenced_table_name='ocupacional_examenes_generales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_catalogo_empresas c LEFT JOIN ocupacional_examenes_generales e ON e.id=c.examen_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_catalogo_empresas ADD CONSTRAINT fk_cat_examen FOREIGN KEY (examen_id) REFERENCES ocupacional_examenes_generales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk catalogo_examen: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='empresa_id' AND referenced_table_name='empresas_ocupacionales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_ordenes o LEFT JOIN empresas_ocupacionales e ON e.id=o.empresa_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_ordenes ADD CONSTRAINT fk_ord_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk orden_empresa: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_ordenes' AND column_name='trabajador_id' AND referenced_table_name='pacientes_ocupacionales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_ordenes o LEFT JOIN pacientes_ocupacionales p ON p.id=o.trabajador_id WHERE p.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_ordenes ADD CONSTRAINT fk_ord_trabajador FOREIGN KEY (trabajador_id) REFERENCES pacientes_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk orden_trabajador: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_orden_detalle' AND column_name='orden_id' AND referenced_table_name='ocupacional_ordenes');
SET @orph := (SELECT COUNT(*) FROM ocupacional_orden_detalle d LEFT JOIN ocupacional_ordenes o ON o.id=d.orden_id WHERE o.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_orden_detalle ADD CONSTRAINT fk_ocup_detalle_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk detalle_orden: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_historia_ocupacional' AND column_name='orden_id' AND referenced_table_name='ocupacional_ordenes');
SET @orph := (SELECT COUNT(*) FROM ocupacional_historia_ocupacional h LEFT JOIN ocupacional_ordenes o ON o.id=h.orden_id WHERE o.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_historia_ocupacional ADD CONSTRAINT fk_ocup_historia_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk historia_orden: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_resultados_clinicos' AND column_name='orden_detalle_id' AND referenced_table_name='ocupacional_orden_detalle');
SET @orph := (SELECT COUNT(*) FROM ocupacional_resultados_clinicos r LEFT JOIN ocupacional_orden_detalle d ON d.id=r.orden_detalle_id WHERE d.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_resultados_clinicos ADD CONSTRAINT fk_ocup_resultado_detalle FOREIGN KEY (orden_detalle_id) REFERENCES ocupacional_orden_detalle(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk resultado_detalle: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_resultados_clinicos' AND column_name='orden_id' AND referenced_table_name='ocupacional_ordenes');
SET @orph := (SELECT COUNT(*) FROM ocupacional_resultados_clinicos r LEFT JOIN ocupacional_ordenes o ON o.id=r.orden_id WHERE o.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_resultados_clinicos ADD CONSTRAINT fk_ocup_resultado_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk resultado_orden: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_resultados_clinicos' AND column_name='examen_id' AND referenced_table_name='ocupacional_examenes_generales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_resultados_clinicos r LEFT JOIN ocupacional_examenes_generales e ON e.id=r.examen_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_resultados_clinicos ADD CONSTRAINT fk_ocup_resultado_examen FOREIGN KEY (examen_id) REFERENCES ocupacional_examenes_generales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk resultado_examen: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_orden_eventos' AND column_name='orden_id' AND referenced_table_name='ocupacional_ordenes');
SET @orph := (SELECT COUNT(*) FROM ocupacional_orden_eventos e LEFT JOIN ocupacional_ordenes o ON o.id=e.orden_id WHERE o.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_orden_eventos ADD CONSTRAINT fk_ocup_evento_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk evento_orden: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_interconsultas' AND column_name='orden_id' AND referenced_table_name='ocupacional_ordenes');
SET @orph := (SELECT COUNT(*) FROM ocupacional_interconsultas i LEFT JOIN ocupacional_ordenes o ON o.id=i.orden_id WHERE o.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_interconsultas ADD CONSTRAINT fk_ocup_inter_orden FOREIGN KEY (orden_id) REFERENCES ocupacional_ordenes(id) ON UPDATE CASCADE ON DELETE CASCADE','SELECT "skip fk interconsulta_orden: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_interconsultas' AND column_name='orden_detalle_id' AND referenced_table_name='ocupacional_orden_detalle');
SET @orph := (SELECT COUNT(*) FROM ocupacional_interconsultas i LEFT JOIN ocupacional_orden_detalle d ON d.id=i.orden_detalle_id WHERE d.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_interconsultas ADD CONSTRAINT fk_ocup_inter_detalle FOREIGN KEY (orden_detalle_id) REFERENCES ocupacional_orden_detalle(id) ON UPDATE CASCADE ON DELETE CASCADE','SELECT "skip fk interconsulta_detalle: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @rel := (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema=@db_name AND table_name='ocupacional_catalogos_laborales_empresa' AND column_name='empresa_id' AND referenced_table_name='empresas_ocupacionales');
SET @orph := (SELECT COUNT(*) FROM ocupacional_catalogos_laborales_empresa c LEFT JOIN empresas_ocupacionales e ON e.id=c.empresa_id WHERE e.id IS NULL);
SET @sql := IF(@rel=0 AND @orph=0,'ALTER TABLE ocupacional_catalogos_laborales_empresa ADD CONSTRAINT fk_ocup_catalogo_laboral_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_ocupacionales(id) ON UPDATE CASCADE ON DELETE RESTRICT','SELECT "skip fk catalogo_laboral_empresa: existente o con huerfanos"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @unique_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db_name AND table_name='ocupacional_resultados_clinicos' AND index_name='uq_ocup_resultado_detalle_formato');
SET @duplicates := (SELECT COUNT(*) FROM (SELECT orden_detalle_id, formato_codigo FROM ocupacional_resultados_clinicos GROUP BY orden_detalle_id, formato_codigo HAVING COUNT(*)>1) d);
SET @sql := IF(@unique_exists=0 AND @duplicates=0,'ALTER TABLE ocupacional_resultados_clinicos ADD UNIQUE KEY uq_ocup_resultado_detalle_formato (orden_detalle_id, formato_codigo)','SELECT "skip unique resultado: existente o hay duplicados"'); PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Postflight: todos los valores deben ser 1 salvo conteos de datos.
SELECT 'tablas_ocupacionales' AS check_name, COUNT(*) AS total
FROM information_schema.tables
WHERE table_schema=@db_name
  AND table_name IN (
    'empresas_ocupacionales','pacientes_ocupacionales','ocupacional_examenes_generales',
    'ocupacional_catalogo_empresas','ocupacional_tipos_evaluacion','ocupacional_protocolos_empresa',
    'ocupacional_protocolo_detalle','ocupacional_protocolo_condiciones','ocupacional_ordenes',
    'ocupacional_orden_detalle','ocupacional_orden_eventos','ocupacional_historia_ocupacional',
    'ocupacional_resultados_clinicos','ocupacional_historia_auditoria',
    'ocupacional_plantillas_resultado','ocupacional_grupos_examenes',
    'ocupacional_catalogos_laborales_empresa','ocupacional_interconsultas'
  );

SELECT 'columnas_orden_criticas' AS check_name, COUNT(*) AS total
FROM information_schema.columns
WHERE table_schema=@db_name AND table_name='ocupacional_ordenes'
  AND column_name IN (
    'aptitud_final','medico_responsable_id','medico_nombre_snapshot','medico_cmp_snapshot',
    'medico_rne_snapshot','medico_rna_snapshot','medico_firma_snapshot','aptitud_registrada_by','aptitud_registrada_at'
  );

SELECT 'relaciones_internas' AS check_name, COUNT(*) AS total
FROM information_schema.key_column_usage
WHERE table_schema=@db_name AND referenced_table_name IS NOT NULL
  AND (table_name LIKE 'ocupacional%' OR table_name='pacientes_ocupacionales');

SELECT @duplicates AS resultados_duplicados_por_formato;

SELECT 'FIN OCUPACIONAL' AS resultado, DATABASE() AS base_aplicada;
