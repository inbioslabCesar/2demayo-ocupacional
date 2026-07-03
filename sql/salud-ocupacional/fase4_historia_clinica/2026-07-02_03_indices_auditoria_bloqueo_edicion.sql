-- Fase 4 Salud Ocupacional: auditoria y bloqueo de edicion por estado de orden
-- Requisitos previos:
--   01_base_historia_ocupacional.sql
--   02_relaciones_indices_historia_ocupacional.sql
-- Objetivo:
--   1) Crear tabla de auditoria clinica ocupacional
--   2) Registrar eventos de insercion/actualizacion en historia y resultados
--   3) Bloquear insercion/edicion cuando la orden este cerrada o anulada

CREATE TABLE IF NOT EXISTS ocupacional_historia_auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entidad ENUM('historia_ocupacional','resultado_clinico') NOT NULL,
  registro_id INT UNSIGNED NOT NULL,
  orden_id INT UNSIGNED NOT NULL,
  accion ENUM('insert','update','annul','close') NOT NULL,
  payload_json JSON NULL,
  actor_id INT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ocup_hist_audit_entidad_registro (entidad, registro_id),
  KEY idx_ocup_hist_audit_orden_fecha (orden_id, created_at),
  KEY idx_ocup_hist_audit_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS tr_ocup_historia_bi_guard;
DELIMITER $$
CREATE TRIGGER tr_ocup_historia_bi_guard
BEFORE INSERT ON ocupacional_historia_ocupacional
FOR EACH ROW
BEGIN
  DECLARE v_estado_orden VARCHAR(20) DEFAULT NULL;

  SELECT estado INTO v_estado_orden
  FROM ocupacional_ordenes
  WHERE id = NEW.orden_id
  LIMIT 1;

  IF v_estado_orden IN ('cerrada', 'anulada') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede registrar historia: la orden esta cerrada o anulada';
  END IF;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_historia_bu_guard;
DELIMITER $$
CREATE TRIGGER tr_ocup_historia_bu_guard
BEFORE UPDATE ON ocupacional_historia_ocupacional
FOR EACH ROW
BEGIN
  DECLARE v_estado_orden VARCHAR(20) DEFAULT NULL;

  SELECT estado INTO v_estado_orden
  FROM ocupacional_ordenes
  WHERE id = OLD.orden_id
  LIMIT 1;

  IF v_estado_orden IN ('cerrada', 'anulada') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede editar historia: la orden esta cerrada o anulada';
  END IF;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_resultado_bi_guard;
DELIMITER $$
CREATE TRIGGER tr_ocup_resultado_bi_guard
BEFORE INSERT ON ocupacional_resultados_clinicos
FOR EACH ROW
BEGIN
  DECLARE v_estado_orden VARCHAR(20) DEFAULT NULL;

  SELECT estado INTO v_estado_orden
  FROM ocupacional_ordenes
  WHERE id = NEW.orden_id
  LIMIT 1;

  IF v_estado_orden IN ('cerrada', 'anulada') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede registrar resultado: la orden esta cerrada o anulada';
  END IF;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_resultado_bu_guard;
DELIMITER $$
CREATE TRIGGER tr_ocup_resultado_bu_guard
BEFORE UPDATE ON ocupacional_resultados_clinicos
FOR EACH ROW
BEGIN
  DECLARE v_estado_orden VARCHAR(20) DEFAULT NULL;

  SELECT estado INTO v_estado_orden
  FROM ocupacional_ordenes
  WHERE id = OLD.orden_id
  LIMIT 1;

  IF v_estado_orden IN ('cerrada', 'anulada') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede editar resultado: la orden esta cerrada o anulada';
  END IF;
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_historia_ai_audit;
DELIMITER $$
CREATE TRIGGER tr_ocup_historia_ai_audit
AFTER INSERT ON ocupacional_historia_ocupacional
FOR EACH ROW
BEGIN
  INSERT INTO ocupacional_historia_auditoria (
    entidad,
    registro_id,
    orden_id,
    accion,
    payload_json,
    actor_id
  )
  VALUES (
    'historia_ocupacional',
    NEW.id,
    NEW.orden_id,
    'insert',
    JSON_OBJECT(
      'estado', NEW.estado,
      'puesto_actual', NEW.puesto_actual,
      'area_trabajo', NEW.area_trabajo
    ),
    NEW.created_by
  );
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_historia_au_audit;
DELIMITER $$
CREATE TRIGGER tr_ocup_historia_au_audit
AFTER UPDATE ON ocupacional_historia_ocupacional
FOR EACH ROW
BEGIN
  INSERT INTO ocupacional_historia_auditoria (
    entidad,
    registro_id,
    orden_id,
    accion,
    payload_json,
    actor_id
  )
  VALUES (
    'historia_ocupacional',
    NEW.id,
    NEW.orden_id,
    CASE
      WHEN NEW.estado = 'anulado' AND OLD.estado <> 'anulado' THEN 'annul'
      WHEN NEW.estado = 'cerrado' AND OLD.estado <> 'cerrado' THEN 'close'
      ELSE 'update'
    END,
    JSON_OBJECT(
      'estado_old', OLD.estado,
      'estado_new', NEW.estado,
      'updated_by', NEW.updated_by
    ),
    NEW.updated_by
  );
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_resultado_ai_audit;
DELIMITER $$
CREATE TRIGGER tr_ocup_resultado_ai_audit
AFTER INSERT ON ocupacional_resultados_clinicos
FOR EACH ROW
BEGIN
  INSERT INTO ocupacional_historia_auditoria (
    entidad,
    registro_id,
    orden_id,
    accion,
    payload_json,
    actor_id
  )
  VALUES (
    'resultado_clinico',
    NEW.id,
    NEW.orden_id,
    'insert',
    JSON_OBJECT(
      'formato_codigo', NEW.formato_codigo,
      'estado', NEW.estado
    ),
    NEW.created_by
  );
END;
$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_ocup_resultado_au_audit;
DELIMITER $$
CREATE TRIGGER tr_ocup_resultado_au_audit
AFTER UPDATE ON ocupacional_resultados_clinicos
FOR EACH ROW
BEGIN
  INSERT INTO ocupacional_historia_auditoria (
    entidad,
    registro_id,
    orden_id,
    accion,
    payload_json,
    actor_id
  )
  VALUES (
    'resultado_clinico',
    NEW.id,
    NEW.orden_id,
    CASE
      WHEN NEW.estado = 'anulado' AND OLD.estado <> 'anulado' THEN 'annul'
      ELSE 'update'
    END,
    JSON_OBJECT(
      'estado_old', OLD.estado,
      'estado_new', NEW.estado,
      'formato_codigo', NEW.formato_codigo,
      'updated_by', NEW.updated_by
    ),
    NEW.updated_by
  );
END;
$$
DELIMITER ;
