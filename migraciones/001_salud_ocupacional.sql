-- ============================================================
-- MIGRACIÓN 001 — Salud Ocupacional
-- Base de datos: 2demayo_so (aislada de la BD clínica principal)
-- Ejecutar en phpMyAdmin o HeidiSQL antes de iniciar el módulo.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `2demayo_so`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `2demayo_so`;

-- ------------------------------------------------------------
-- Tabla: empresas_ocupacionales
-- Almacena los clientes corporativos del programa de SO.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empresas_ocupacionales` (
  `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `ruc`             VARCHAR(11)       NOT NULL,
  `razon_social`    VARCHAR(255)      NOT NULL,
  `nombre_comercial`VARCHAR(255)      DEFAULT NULL,
  `actividad`       VARCHAR(255)      NOT NULL,
  `direccion`       VARCHAR(500)      NOT NULL,
  `departamento`    VARCHAR(100)      DEFAULT NULL,
  `provincia`       VARCHAR(100)      DEFAULT NULL,
  `distrito`        VARCHAR(100)      DEFAULT NULL,
  `telefono`        VARCHAR(20)       DEFAULT NULL,
  `contacto`        VARCHAR(255)      DEFAULT NULL,
  `correo`          VARCHAR(255)      DEFAULT NULL,
  `observacion`     TEXT              DEFAULT NULL,
  `estado`          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE  KEY `uq_ruc` (`ruc`),
  KEY     `idx_estado` (`estado`),
  KEY     `idx_razon_social` (`razon_social`(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: pacientes_ocupacionales
-- Almacena el contexto LABORAL del trabajador.
-- La identidad civil (nombre, fecha nac.) vive en el sistema
-- clínico principal y se consulta por external_patient_id.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pacientes_ocupacionales` (
  `id`                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `empresa_id`          INT UNSIGNED      NOT NULL,
  `external_patient_id` INT UNSIGNED      NOT NULL  COMMENT 'ID del paciente en el sistema clínico principal',
  -- Cache local de identidad (readonly — no se edita aquí)
  `documento_tipo`      VARCHAR(20)       DEFAULT NULL  COMMENT 'DNI, Pasaporte, CE, etc.',
  `documento_numero`    VARCHAR(15)       DEFAULT NULL  COMMENT 'Alfanumérico, 6-15 caracteres',
  -- Contexto laboral (propiedad exclusiva de este sistema)
  `puesto_trabajo`      VARCHAR(255)      NOT NULL,
  `area_riesgo`         ENUM('bajo','medio','alto') DEFAULT NULL,
  `tipo_contrato`       ENUM('indefinido','plazo_fijo','temporal') DEFAULT NULL,
  `fecha_ingreso`       DATE              NOT NULL  COMMENT 'Obligatorio — base de cálculo de exposición laboral',
  `estado_laboral`      ENUM('activo','retirado','suspendido') NOT NULL DEFAULT 'activo',
  `created_at`          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  -- Un trabajador no puede estar dos veces activo en la misma empresa
  UNIQUE  KEY `uq_empresa_paciente` (`empresa_id`, `external_patient_id`),
  KEY     `idx_external_patient` (`external_patient_id`),
  KEY     `idx_documento` (`documento_tipo`, `documento_numero`),
  KEY     `idx_empresa_estado` (`empresa_id`, `estado_laboral`),

  CONSTRAINT `fk_pacoc_empresa`
    FOREIGN KEY (`empresa_id`)
    REFERENCES `empresas_ocupacionales` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
