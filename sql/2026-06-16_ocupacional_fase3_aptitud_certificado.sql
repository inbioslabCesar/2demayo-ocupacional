-- Fase 3 Salud Ocupacional: aptitud final y certificado
-- Aplicar sobre la base ocupacional (por defecto: 2demayo_so)

ALTER TABLE ocupacional_ordenes
  ADD COLUMN aptitud_final ENUM('APTO','APTO_CON_RESTRICCIONES','NO_APTO') NULL AFTER observacion,
  ADD COLUMN restriccion_final VARCHAR(255) NULL AFTER aptitud_final,
  ADD COLUMN recomendacion_final VARCHAR(255) NULL AFTER restriccion_final,
  ADD COLUMN medico_responsable VARCHAR(150) NULL AFTER recomendacion_final;
