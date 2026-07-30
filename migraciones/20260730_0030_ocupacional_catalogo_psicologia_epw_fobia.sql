-- ============================================================================
-- PSICOLOGIA: ALTA DE EXAMENES EPW_0001 Y FOBIA + PROPAGACION A CATALOGO/PROTOCOLOS
-- Idempotente: no duplica examenes, catalogos ni detalles de protocolo.
-- Requiere que exista PSI_0001 como referencia en catalogo/protocolos.
-- ============================================================================

SET NAMES utf8mb4;
SET @db_name := DATABASE();

SELECT DATABASE() AS base_seleccionada, 'OK' AS preflight_base;

-- 1) Maestro de examenes
INSERT INTO ocupacional_examenes_generales
  (codigo, descripcion, grupo, subgrupo, valores_normales, precio, posicion, estado, created_by, updated_by)
VALUES
  (
    'EPW_0001',
    'TEST DE EPWORTH',
    'PSICOLOGIA',
    'Evaluacion psicologica ocupacional',
    'Test de somnolencia de Epworth (8 items)',
    0.00,
    2,
    'activo',
    NULL,
    NULL
  ),
  (
    'FOBIA',
    'TEST DE FOBIAS Y ESTRES',
    'PSICOLOGIA',
    'Evaluacion psicologica ocupacional',
    'Test de fobias y estres (22 items)',
    0.00,
    3,
    'activo',
    NULL,
    NULL
  )
ON DUPLICATE KEY UPDATE
  descripcion = VALUES(descripcion),
  grupo = VALUES(grupo),
  subgrupo = VALUES(subgrupo),
  valores_normales = VALUES(valores_normales),
  posicion = VALUES(posicion),
  estado = 'activo',
  updated_at = CURRENT_TIMESTAMP;

SET @psi_exam_id := (
  SELECT id FROM ocupacional_examenes_generales WHERE UPPER(codigo) = 'PSI_0001' LIMIT 1
);
SET @epw_exam_id := (
  SELECT id FROM ocupacional_examenes_generales WHERE UPPER(codigo) = 'EPW_0001' LIMIT 1
);
SET @fobia_exam_id := (
  SELECT id FROM ocupacional_examenes_generales WHERE UPPER(codigo) = 'FOBIA' LIMIT 1
);

-- 2) Catalogo por empresa: clonar presencia de PSI_0001 para EPW_0001 y FOBIA
INSERT INTO ocupacional_catalogo_empresas
  (empresa_id, examen_id, estado, created_by, updated_by)
SELECT
  cpsi.empresa_id,
  @epw_exam_id,
  'activo',
  cpsi.created_by,
  cpsi.updated_by
FROM ocupacional_catalogo_empresas cpsi
INNER JOIN ocupacional_examenes_generales epsi
  ON epsi.id = cpsi.examen_id
 AND UPPER(epsi.codigo) = 'PSI_0001'
LEFT JOIN ocupacional_catalogo_empresas cepw
  ON cepw.empresa_id = cpsi.empresa_id
 AND cepw.examen_id = @epw_exam_id
WHERE @epw_exam_id IS NOT NULL
  AND cepw.id IS NULL;

INSERT INTO ocupacional_catalogo_empresas
  (empresa_id, examen_id, estado, created_by, updated_by)
SELECT
  cpsi.empresa_id,
  @fobia_exam_id,
  'activo',
  cpsi.created_by,
  cpsi.updated_by
FROM ocupacional_catalogo_empresas cpsi
INNER JOIN ocupacional_examenes_generales epsi
  ON epsi.id = cpsi.examen_id
 AND UPPER(epsi.codigo) = 'PSI_0001'
LEFT JOIN ocupacional_catalogo_empresas cfob
  ON cfob.empresa_id = cpsi.empresa_id
 AND cfob.examen_id = @fobia_exam_id
WHERE @fobia_exam_id IS NOT NULL
  AND cfob.id IS NULL;

-- 3) Protocolo detalle: clonar monto/tipo de PSI_0001 hacia EPW_0001 y FOBIA
INSERT INTO ocupacional_protocolo_detalle
  (protocolo_id, catalogo_id, tipo_evaluacion_id, monto, created_by, updated_by)
SELECT
  pd.protocolo_id,
  cepw.id AS catalogo_id,
  pd.tipo_evaluacion_id,
  pd.monto,
  pd.created_by,
  pd.updated_by
FROM ocupacional_protocolo_detalle pd
INNER JOIN ocupacional_catalogo_empresas cpsi
  ON cpsi.id = pd.catalogo_id
INNER JOIN ocupacional_examenes_generales epsi
  ON epsi.id = cpsi.examen_id
 AND UPPER(epsi.codigo) = 'PSI_0001'
INNER JOIN ocupacional_catalogo_empresas cepw
  ON cepw.empresa_id = cpsi.empresa_id
 AND cepw.examen_id = @epw_exam_id
LEFT JOIN ocupacional_protocolo_detalle pde
  ON pde.protocolo_id = pd.protocolo_id
 AND pde.catalogo_id = cepw.id
 AND pde.tipo_evaluacion_id = pd.tipo_evaluacion_id
WHERE @epw_exam_id IS NOT NULL
  AND pde.id IS NULL;

INSERT INTO ocupacional_protocolo_detalle
  (protocolo_id, catalogo_id, tipo_evaluacion_id, monto, created_by, updated_by)
SELECT
  pd.protocolo_id,
  cfob.id AS catalogo_id,
  pd.tipo_evaluacion_id,
  pd.monto,
  pd.created_by,
  pd.updated_by
FROM ocupacional_protocolo_detalle pd
INNER JOIN ocupacional_catalogo_empresas cpsi
  ON cpsi.id = pd.catalogo_id
INNER JOIN ocupacional_examenes_generales epsi
  ON epsi.id = cpsi.examen_id
 AND UPPER(epsi.codigo) = 'PSI_0001'
INNER JOIN ocupacional_catalogo_empresas cfob
  ON cfob.empresa_id = cpsi.empresa_id
 AND cfob.examen_id = @fobia_exam_id
LEFT JOIN ocupacional_protocolo_detalle pdf
  ON pdf.protocolo_id = pd.protocolo_id
 AND pdf.catalogo_id = cfob.id
 AND pdf.tipo_evaluacion_id = pd.tipo_evaluacion_id
WHERE @fobia_exam_id IS NOT NULL
  AND pdf.id IS NULL;

-- 4) Condiciones de protocolo: clonar condiciones existentes de PSI_0001
INSERT INTO ocupacional_protocolo_condiciones
  (protocolo_id, catalogo_id, puesto_trabajo, sexo, edad_min, edad_max, created_by, updated_by)
SELECT
  pc.protocolo_id,
  cepw.id AS catalogo_id,
  pc.puesto_trabajo,
  pc.sexo,
  pc.edad_min,
  pc.edad_max,
  pc.created_by,
  pc.updated_by
FROM ocupacional_protocolo_condiciones pc
INNER JOIN ocupacional_catalogo_empresas cpsi
  ON cpsi.id = pc.catalogo_id
INNER JOIN ocupacional_examenes_generales epsi
  ON epsi.id = cpsi.examen_id
 AND UPPER(epsi.codigo) = 'PSI_0001'
INNER JOIN ocupacional_catalogo_empresas cepw
  ON cepw.empresa_id = cpsi.empresa_id
 AND cepw.examen_id = @epw_exam_id
LEFT JOIN ocupacional_protocolo_condiciones pce
  ON pce.protocolo_id = pc.protocolo_id
 AND pce.catalogo_id = cepw.id
 AND (pce.puesto_trabajo <=> pc.puesto_trabajo)
 AND (pce.sexo <=> pc.sexo)
 AND (pce.edad_min <=> pc.edad_min)
 AND (pce.edad_max <=> pc.edad_max)
WHERE @epw_exam_id IS NOT NULL
  AND pce.id IS NULL;

INSERT INTO ocupacional_protocolo_condiciones
  (protocolo_id, catalogo_id, puesto_trabajo, sexo, edad_min, edad_max, created_by, updated_by)
SELECT
  pc.protocolo_id,
  cfob.id AS catalogo_id,
  pc.puesto_trabajo,
  pc.sexo,
  pc.edad_min,
  pc.edad_max,
  pc.created_by,
  pc.updated_by
FROM ocupacional_protocolo_condiciones pc
INNER JOIN ocupacional_catalogo_empresas cpsi
  ON cpsi.id = pc.catalogo_id
INNER JOIN ocupacional_examenes_generales epsi
  ON epsi.id = cpsi.examen_id
 AND UPPER(epsi.codigo) = 'PSI_0001'
INNER JOIN ocupacional_catalogo_empresas cfob
  ON cfob.empresa_id = cpsi.empresa_id
 AND cfob.examen_id = @fobia_exam_id
LEFT JOIN ocupacional_protocolo_condiciones pcf
  ON pcf.protocolo_id = pc.protocolo_id
 AND pcf.catalogo_id = cfob.id
 AND (pcf.puesto_trabajo <=> pc.puesto_trabajo)
 AND (pcf.sexo <=> pc.sexo)
 AND (pcf.edad_min <=> pc.edad_min)
 AND (pcf.edad_max <=> pc.edad_max)
WHERE @fobia_exam_id IS NOT NULL
  AND pcf.id IS NULL;

-- 5) Verificaciones
SELECT id, codigo, descripcion, grupo, subgrupo, posicion, estado
FROM ocupacional_examenes_generales
WHERE UPPER(codigo) IN ('PSI_0001', 'EPW_0001', 'FOBIA')
ORDER BY FIELD(UPPER(codigo), 'PSI_0001', 'EPW_0001', 'FOBIA');

SELECT
  e.codigo,
  COUNT(*) AS empresas_en_catalogo
FROM ocupacional_catalogo_empresas c
INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
WHERE UPPER(e.codigo) IN ('PSI_0001', 'EPW_0001', 'FOBIA')
GROUP BY e.codigo
ORDER BY FIELD(UPPER(e.codigo), 'PSI_0001', 'EPW_0001', 'FOBIA');

SELECT
  e.codigo,
  COUNT(*) AS filas_protocolo_detalle
FROM ocupacional_protocolo_detalle pd
INNER JOIN ocupacional_catalogo_empresas c ON c.id = pd.catalogo_id
INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
WHERE UPPER(e.codigo) IN ('PSI_0001', 'EPW_0001', 'FOBIA')
GROUP BY e.codigo
ORDER BY FIELD(UPPER(e.codigo), 'PSI_0001', 'EPW_0001', 'FOBIA');

SELECT 'FIN ALTA EPW/FOBIA EN CATALOGO Y PROTOCOLOS' AS resultado, @db_name AS base_aplicada;
