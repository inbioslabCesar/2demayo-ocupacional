-- Fase 4 Salud Ocupacional: catalogo de plantillas de resultado clinico
-- Objetivo:
--   1) Crear tabla versionada para plantillas reutilizables por examen/formato
--   2) Dejar indices y restricciones para busqueda y unicidad por codigo
-- Nota:
--   La API ya puede autogenerar esta tabla en caliente, pero este script
--   formaliza el despliegue para ambientes controlados (staging/produccion).

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
