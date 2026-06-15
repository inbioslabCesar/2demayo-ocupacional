-- ============================================================
-- MIGRACION COMPLETA SALUD OCUPACIONAL - HOSTINGER
-- BD destino: u330560936_so2demayo
-- Ejecutar en phpMyAdmin con esa BD seleccionada
-- ============================================================

-- ---- TABLAS BASE ----

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
  KEY `idx_pac_estado_fecha` (`estado_laboral`, `fecha_ingreso`),
  KEY `idx_pac_empresa_fecha` (`empresa_id`, `fecha_ingreso`),
  CONSTRAINT `fk_pacientes_ocupacionales_empresa`
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas_ocupacionales`(`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- INDICE ADICIONAL EMPRESAS ----

SET @exists_emp_estado_created = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = 'u330560936_so2demayo'
    AND table_name = 'empresas_ocupacionales'
    AND index_name = 'idx_emp_estado_created'
);
SET @sql_emp_estado_created = IF(
  @exists_emp_estado_created = 0,
  'ALTER TABLE `empresas_ocupacionales` ADD INDEX `idx_emp_estado_created` (`estado`, `created_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_emp_estado_created;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
