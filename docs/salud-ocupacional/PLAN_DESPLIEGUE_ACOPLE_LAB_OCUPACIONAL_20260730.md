# Plan de Despliegue: Acople Laboratorio Moderno -> Salud Ocupacional

Fecha: 2026-07-30

## 1. Objetivo

Integrar el catalogo moderno de laboratorio en el flujo ocupacional para que:

- Examenes ocupacionales puedan importarse desde laboratorio con metadata clinica estructurada.
- Ordenes ocupacionales congelen un snapshot del examen para trazabilidad historica.
- Formatos ocupacionales de laboratorio se generen de forma dinamica desde snapshot (no solo texto libre).

## 2. Cambios implementados en codigo

- API ocupacional de examenes: importacion desde laboratorio moderno y listado de catalogo laboratorio.
- API ocupacional de ordenes: copia del snapshot de laboratorio al detalle de orden.
- API ocupacional de resultados: template lab dinamico desde snapshot.
- UI de examenes ocupacionales: bloque "Importar desde laboratorio moderno".
- Migracion SQL de ocupacional:
  - `migraciones/20260730_0040_ocupacional_acople_laboratorio_moderno.sql`

## 3. Bases de datos en produccion (contexto real)

Segun operacion actual:

- Base Clinica (catalogo laboratorio): usuario `u330560936_user2DeMayo`
- Base Salud Ocupacional (modulo ocupacional): usuario `u330560936_userSO2DM`

Notas:

- La migracion nueva se ejecuta SOLO en la base ocupacional.
- El catalogo laboratorio se consume en runtime via API sobre la base clinica; no requiere ALTER en clinica para este release.

## 4. Orden recomendado (muy importante)

1. Backup completo de ambas bases.
2. Validar acceso de app a ambas conexiones (clinica y ocupacional).
3. Subir codigo (sin ejecutar migracion aun).
4. Ejecutar migracion en base ocupacional.
5. Limpiar cache/opcache si aplica.
6. Pruebas funcionales guiadas.
7. Habilitacion operativa.

## 5. Checklist detallado por etapa

### Etapa A: Pre-release (sin tocar produccion)

- Confirmar que build local y endpoints no tienen errores de sintaxis.
- Confirmar que usuarios/roles de ocupacional pueden abrir Examenes Generales.

### Etapa B: Backup

- Exportar dump de base clinica.
- Exportar dump de base ocupacional.
- Guardar checksum y timestamp de ambos dumps.

### Etapa C: Deploy de codigo

- Publicar archivos modificados.
- No ejecutar aun la migracion mientras no termine la copia.

### Etapa D: Migracion (al final)

Ejecutar en base ocupacional:

- `migraciones/20260730_0040_ocupacional_acople_laboratorio_moderno.sql`

Validar columnas creadas:

- `ocupacional_examenes_generales.origen_datos`
- `ocupacional_examenes_generales.laboratorio_examen_id`
- `ocupacional_examenes_generales.laboratorio_version_id`
- `ocupacional_examenes_generales.laboratorio_snapshot_json`
- `ocupacional_orden_detalle.examen_snapshot_json`

### Etapa E: Smoke tests post-migracion

1. Ir a Examenes Generales ocupacional.
2. Buscar un examen en bloque "Importar desde laboratorio moderno".
3. Importar examen.
4. Verificar que aparece en listado ocupacional.
5. Activarlo en Catalogo por Empresa.
6. Asignar monto en Protocolo.
7. Crear Orden ocupacional.
8. Abrir formato del examen importado y verificar parametros dinamicos en bloque laboratorio.

## 6. Rollback

Si falla antes de migracion:

- Revertir codigo desplegado.

Si falla despues de migracion:

- Revertir codigo y restaurar dump de base ocupacional tomado en backup.

## 7. Riesgos conocidos

- Si hay datos historicos antiguos sin snapshot, el sistema usa fallback legacy (texto libre) para mantener compatibilidad.
- Si una instancia no ejecuta la migracion, la importacion desde laboratorio devolvera error controlado indicando columnas faltantes.

## 8. Criterio de cierre

Se considera exitoso cuando:

- Se importa al menos 1 examen desde laboratorio moderno.
- Se genera 1 orden ocupacional con ese examen.
- El formato ocupacional muestra parametros dinamicos desde snapshot.
- No hay errores 500 en endpoints ocupacionales relacionados.
