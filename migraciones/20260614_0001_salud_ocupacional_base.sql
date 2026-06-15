-- Base schema for isolated Salud Ocupacional database.
CREATE DATABASE IF NOT EXISTS `2demayo_so`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `2demayo_so`;

CREATE TABLE IF NOT EXISTS `empresas_ocupacionales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruc` CHAR(11) NOT NULL,
  `razon_social` VARCHAR(200) NOT NULL,
  `direccion` VARCHAR(255) DEFAULT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `correo` VARCHAR(120) DEFAULT NULL,
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_empresas_ocupacionales_ruc` (`ruc`),
  KEY `idx_empresas_ocupacionales_estado` (`estado`),
  KEY `idx_empresas_ocupacionales_razon_social` (`razon_social`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pacientes_ocupacionales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` BIGINT UNSIGNED NOT NULL,
  `external_patient_id` BIGINT UNSIGNED NOT NULL,
  `documento_tipo` VARCHAR(20) NOT NULL DEFAULT 'DNI',
  `documento_numero` VARCHAR(15) NOT NULL,
  `puesto_trabajo` VARCHAR(180) NOT NULL,
  `area_riesgo` VARCHAR(120) DEFAULT NULL,
  `tipo_contrato` VARCHAR(60) DEFAULT NULL,
  `estado_laboral` ENUM('activo', 'retirado') NOT NULL DEFAULT 'activo',
  `fecha_ingreso` DATE NOT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_empresa_external_patient` (`empresa_id`, `external_patient_id`),
  KEY `idx_pacientes_ocupacionales_external` (`external_patient_id`),
  KEY `idx_pacientes_ocupacionales_documento` (`documento_numero`),
  KEY `idx_pacientes_ocupacionales_doc_tipo_num` (`documento_tipo`, `documento_numero`),
  KEY `idx_pacientes_ocupacionales_empresa_estado` (`empresa_id`, `estado_laboral`),
  CONSTRAINT `fk_pacientes_ocupacionales_empresa`
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas_ocupacionales`(`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
