# Salud Ocupacional - Ruta Oficial de Migraciones

## Objetivo
Evitar ambiguedades entre scripts antiguos (base fija) y scripts actuales (base activa).

## Ruta oficial (vigente)
### Produccion Hostinger con dos bases

Para las bases actuales de Hostinger no ejecutar la secuencia historica archivo por archivo. Usar los paquetes acumulativos:

1. En la base clinica: `20260725_0024_hostinger_produccion_clinica.sql`.
2. En la base ocupacional: `20260725_0023_hostinger_produccion_ocupacional.sql`.

Instrucciones, backups, postflight y smoke test:

- `docs/salud-ocupacional/GUIA_DESPLIEGUE_SQL_HOSTINGER_DOS_BD_20260725.md`

Los paquetes seleccionan las bases exactas de produccion y no deben mezclarse con los maestros Hostinger 0013, 0014 o 0015.

Si 0024 y 0023 ya fueron ejecutados, para habilitar el RNA opcional del medico aplicar solamente:

1. En la base clinica: `20260725_0025_medicos_rna_clinica.sql`.
2. En la base ocupacional: `20260725_0026_ocupacional_medico_rna_snapshot.sql`.

Los dos scripts son incrementales, admiten segunda ejecucion y no modifican los registros existentes.

Para habilitar los snapshots de clasificacion y orden de la hoja de ruta en una instalacion que ya tiene 0023-0026, ejecutar despues:

1. En la base ocupacional: `20260725_0027_ocupacional_hoja_ruta_snapshots.sql`.
2. En la misma base: `20260726_0028_ocupacional_triaje_catalogo.sql`.

Los scripts son incrementales e idempotentes. Las ordenes antiguas usan como fallback la clasificacion vigente del catalogo; las nuevas congelan grupo, subgrupo y posiciones al registrarse. La migracion 0028 instala el grupo `TRIAJE`, su subgrupo `Signos vitales` y el examen `TRI_0001`, pero su habilitacion y monto se configuran por empresa, protocolo y tipo de evaluacion desde la matriz.

### Desarrollo o instalacion historica incremental

1. Seleccionar previamente la base de datos destino en la herramienta/cliente SQL.
2. Ejecutar las migraciones del bloque `20260614_000x` en orden:
   - `20260614_0001_salud_ocupacional_base.sql`
   - `20260614_0002_salud_ocupacional_auditoria.sql`
   - `20260614_0003_salud_ocupacional_indices_listados.sql`
3. Si necesitas paridad de campos de Empresa con el sistema legacy, ejecutar:
   - `20260723_0004_salud_ocupacional_integridad_empresa.sql`
   - `20260723_0005_salud_ocupacional_empresa_campos_legacy.sql`
6. `20260724_0006_pacientes_biometria.sql`
7. `20260724_0007_pacientes_campos_legacy.sql`
8. `20260724_0008_ubigeo_lima.sql`
9. `20260724_0009_ubigeo_peru_completo.sql`

Esta secuencia historica no representa por si sola el esquema ocupacional completo actual. Para nuevos despliegues utilizar los paquetes acumulativos indicados arriba.

## Nota de biometria

- La migracion `20260724_0006_pacientes_biometria.sql` agrega en `pacientes` los campos `firma_digital`, `huella_digital` y `fotografia` para soporte de verificacion/captura biometrica en flujo moderno.
- La migracion `20260724_0007_pacientes_campos_legacy.sql` agrega campos de paridad legacy para ficha ocupacional (ocupacion, lugar/direccion ampliada, hijos, ubigeo, instruccion, estado civil, padres, acompanante, residencia y celular).
- La migracion `20260724_0008_ubigeo_lima.sql` crea catalogo ubigeo (`departamento`, `provincia`, `distrito`) y siembra Lima para llenado dinamico de combos.
- La migracion `20260724_0009_ubigeo_peru_completo.sql` carga el catalogo nacional completo (todos los departamentos, provincias y distritos del Peru).

Estos scripts usan `DATABASE()` y no fuerzan `USE ...`, por lo que operan sobre la base actualmente seleccionada.

## Script legado
- `001_salud_ocupacional.sql` se mantiene solo por compatibilidad historica.
- Ese script crea y usa una base fija (`2demayo_so`) y no debe mezclarse con la ruta oficial en despliegues nuevos.

## Regla de equipo
- En nuevos cambios de Salud Ocupacional, continuar con migraciones idempotentes y sin base hardcodeada.
