# Salud Ocupacional - Ruta Oficial de Migraciones

## Objetivo
Evitar ambiguedades entre scripts antiguos (base fija) y scripts actuales (base activa).

## Ruta oficial (vigente)
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
