# Runbook Producción: Acople Lab + Normalización Textos

Fecha: 2026-07-30

## Objetivo

Aplicar en producción los cambios de Salud Ocupacional para:

1. Acoplar catálogo moderno de laboratorio al maestro ocupacional.
2. Normalizar caracteres especiales históricos (mojibake) en exámenes ocupacionales.

## Bases involucradas

1. Base clínica (solo lectura para este despliegue): usuario `u330560936_user2DeMayo`
2. Base ocupacional (sí requiere migración): usuario `u330560936_userSO2DM`

## Archivos a ejecutar en producción (orden exacto)

1. migraciones/20260730_0040_ocupacional_acople_laboratorio_moderno.sql
2. migraciones/20260730_0050_ocupacional_normalizar_textos_examenes.sql

## Ventana recomendada

1. Ventana de bajo tráfico (10-20 min).
2. Sin usuarios importando exámenes durante la ejecución.

## Paso 1: Backup obligatorio

1. Backup completo de base ocupacional.
2. Backup completo de base clínica.
3. Guardar nombre de archivo y timestamp del backup.

## Paso 2: Publicar código

Publicar primero backend/frontend ya actualizado y luego ejecutar SQL.

Archivos críticos incluidos en release:

1. api_ocupacional_examenes.php
2. src/pages/ocupacional/FormExamenOcupacional.jsx
3. src/api/ocupacionalApi.js

## Paso 3: Ejecutar SQL en base ocupacional

Ejecutar en este orden:

1. 20260730_0040_ocupacional_acople_laboratorio_moderno.sql
2. 20260730_0050_ocupacional_normalizar_textos_examenes.sql

## Paso 4: Verificación SQL post-migración

Ejecutar este bloque en base ocupacional:

```sql
SELECT DATABASE() AS db_actual;

SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'origen_datos';
SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'laboratorio_examen_id';
SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'laboratorio_version_id';
SHOW COLUMNS FROM ocupacional_examenes_generales LIKE 'laboratorio_snapshot_json';

SELECT codigo, descripcion, grupo, subgrupo
FROM ocupacional_examenes_generales
WHERE codigo IN ('LAB_0001','LAB_0002')
ORDER BY codigo;

SELECT
  SUM(CASE WHEN HEX(descripcion) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_descripcion,
  SUM(CASE WHEN HEX(grupo) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_grupo,
  SUM(CASE WHEN HEX(subgrupo) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_subgrupo,
  SUM(CASE WHEN HEX(valores_normales) LIKE '%C383C283%' THEN 1 ELSE 0 END) AS bad_valores_normales
FROM ocupacional_examenes_generales;
```

Esperado:

1. Las 4 columnas nuevas existen.
2. `LAB_0001` muestra `Hematología` (sin mojibake).
3. Contadores `bad_*` en 0.

## Paso 5: Smoke test funcional

1. Ir a Salud Ocupacional > Exámenes Generales.
2. Buscar `hemograma completo` en bloque de importación.
3. Confirmar autoselección cuando hay 1 coincidencia.
4. Importar y validar mensaje de éxito.
5. Revisar que la fila nueva en listado no muestre caracteres corruptos.

## Paso 6: Validación de errores

1. Revisar Network de `api_ocupacional_examenes.php`: debe responder JSON válido.
2. No deben aparecer fatales tipo `Incorrect string value`.

## Rollback

Si hay incidencia:

1. Restaurar backup de base ocupacional.
2. Revertir release de código a versión previa estable.

## Notas

1. La migración `0050` es segura para ejecución repetida (reaplicable, no destructiva).
2. La base clínica no requiere alter para este despliegue.
