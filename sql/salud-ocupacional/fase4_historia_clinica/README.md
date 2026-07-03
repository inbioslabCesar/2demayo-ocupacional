# Fase 4 - Historia Clinica Ocupacional

Fecha de inicio: 2026-07-02
Rama de trabajo: `feature/salud-ocupacional-fase4`

## Objetivo de esta carpeta

Guardar aqui todos los scripts SQL relacionados con la mejora de Fase 4 para Salud Ocupacional.

## Regla de uso

- Crear un archivo por cambio lógico importante.
- Mantener numeración secuencial cuando haya dependencia entre scripts.
- No mezclar cambios de otras mejoras dentro de esta carpeta.

## Convención sugerida

- `YYYY-MM-DD_01_historia_ocupacional_base.sql`
- `YYYY-MM-DD_02_resultados_clinicos_por_examen.sql`
- `YYYY-MM-DD_03_indices_y_auditoria.sql`
- `YYYY-MM-DD_04_semillas_iniciales.sql`

## Script inicial creado

- `2026-07-02_01_base_historia_ocupacional.sql`
- `2026-07-02_02_relaciones_indices_historia_ocupacional.sql`
- `2026-07-02_03_indices_auditoria_bloqueo_edicion.sql`
- `2026-07-02_04_catalogo_plantillas_resultado.sql`
- `2026-07-02_05_verificacion_catalogo_plantillas_resultado.sql`

Este script crea la base inicial de Fase 4:

- `ocupacional_historia_ocupacional`
- `ocupacional_resultados_clinicos`

El script `02` agrega:

- llave unica por `orden_detalle_id + formato_codigo`
- llaves foraneas con `ocupacional_ordenes`
- llaves foraneas con `ocupacional_orden_detalle`
- llave foranea con `ocupacional_examenes_generales`

El script `03` agrega:

- tabla `ocupacional_historia_auditoria`
- triggers de auditoria para historia y resultados
- bloqueo de insercion/edicion cuando la orden este `cerrada` o `anulada`

El script `04` agrega:

- tabla `ocupacional_plantillas_resultado`
- indices para busqueda por `template_code`, `examen_codigo`, `activo`
- unicidad por `codigo`

El script `05` verifica:

- existencia de tabla y collation
- indices creados
- conteo de plantillas activas/inactivas

## Ejecución

Desarrollo (aplicado):

- Base: `2demayo_so`
- Comando: `mysql -u root 2demayo_so -e "SOURCE sql/salud-ocupacional/fase4_historia_clinica/2026-07-02_01_base_historia_ocupacional.sql;"`

Producción (Hostinger):

- Ejecutar el mismo archivo `.sql` sobre la base productiva ocupacional.
- Recomendado: correr primero en una copia/staging y luego en producción.

Orden recomendado de ejecucion:

1. `2026-07-02_01_base_historia_ocupacional.sql`
2. `2026-07-02_02_relaciones_indices_historia_ocupacional.sql`
3. `2026-07-02_03_indices_auditoria_bloqueo_edicion.sql`
4. `2026-07-02_04_catalogo_plantillas_resultado.sql`
5. `2026-07-02_05_verificacion_catalogo_plantillas_resultado.sql`

## Alcance esperado

Esta carpeta debe contener los scripts necesarios para soportar:

- historia ocupacional
- resultados clínicos por examen ocupacional
- relaciones con ordenes ocupacionales
- auditoría y trazabilidad de los nuevos datos clínicos