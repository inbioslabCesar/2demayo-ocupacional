# Salud Ocupacional - Ruta Oficial de Migraciones

## Objetivo
Evitar ambiguedades entre scripts antiguos (base fija) y scripts actuales (base activa).

## Ruta oficial (vigente)
1. Seleccionar previamente la base de datos destino en la herramienta/cliente SQL.
2. Ejecutar las migraciones del bloque `20260614_000x` en orden:
   - `20260614_0001_salud_ocupacional_base.sql`
   - `20260614_0002_salud_ocupacional_auditoria.sql`
   - `20260614_0003_salud_ocupacional_indices_listados.sql`
3. Verificar que no existan errores y que las tablas/indices se hayan creado o ajustado.

Estos scripts usan `DATABASE()` y no fuerzan `USE ...`, por lo que operan sobre la base actualmente seleccionada.

## Script legado
- `001_salud_ocupacional.sql` se mantiene solo por compatibilidad historica.
- Ese script crea y usa una base fija (`2demayo_so`) y no debe mezclarse con la ruta oficial en despliegues nuevos.

## Regla de equipo
- En nuevos cambios de Salud Ocupacional, continuar con migraciones idempotentes y sin base hardcodeada.
