# Guia de despliegue SQL en Hostinger: dos bases

Fecha: 2026-07-25

## 1. Bases de produccion

- Base ocupacional: `u330560936_so2demayo`.
- Base clinica: `u330560936_bd2DeMayo`.

El sistema usa ambas conexiones. La identidad de pacientes, medicos y configuracion de la clinica permanece en la base clinica. Empresas, trabajadores ocupacionales, protocolos, ordenes, resultados e interconsultas permanecen en la base ocupacional.

## 2. Archivos autorizados para este despliegue

Ejecutar solo estos dos paquetes, en este orden:

1. `migraciones/20260725_0024_hostinger_produccion_clinica.sql`.
2. `migraciones/20260725_0023_hostinger_produccion_ocupacional.sql`.

Ambos archivos seleccionan su base exacta mediante `USE`, son reejecutables y no contienen `DROP TABLE`, `TRUNCATE TABLE` ni `DELETE`.

## 3. Copias de seguridad obligatorias

Antes de importar:

1. Exportar `u330560936_bd2DeMayo` completa en formato SQL.
2. Exportar `u330560936_so2demayo` completa en formato SQL.
3. Marcar en phpMyAdmin las opciones de estructura y datos.
4. Conservar los dos archivos fuera del hosting hasta terminar el smoke test.

No continuar si una exportacion falla o queda vacia.

## 4. Ejecucion en phpMyAdmin

### 4.1 Base clinica

1. Abrir phpMyAdmin desde Hostinger.
2. Seleccionar cualquier base accesible. El propio archivo cambiara a la base clinica exacta.
3. Abrir `Importar`.
4. Importar `20260725_0024_hostinger_produccion_clinica.sql`.
5. Confirmar que la salida termine con `FIN CLINICA`.

Resultados esperados:

- `pacientes_campos_ocupacionales = 9`.
- `medicos_campos_ocupacionales = 5`.
- `logo_ocupacional = 1`.

Este paquete no recarga ni modifica el catalogo de ubigeo.

### 4.2 Base ocupacional

1. Abrir `Importar` en phpMyAdmin.
2. Importar `20260725_0023_hostinger_produccion_ocupacional.sql`.
3. Confirmar que la salida termine con `FIN OCUPACIONAL`.

Resultados esperados:

- `tablas_ocupacionales = 18`.
- `columnas_orden_criticas = 8`.
- `resultados_duplicados_por_formato = 0`.
- `relaciones_internas` debe ser mayor que cero. En instalacion limpia validada fue `15`.

Los mensajes `skip` en una segunda ejecucion son normales: indican que una columna, indice o relacion ya existia.

Si el resultado de duplicados es mayor que cero, no eliminar filas automaticamente. Detener el despliegue y revisar `ocupacional_resultados_clinicos` antes de crear la unicidad.

## 5. Scripts que no deben mezclarse

No ejecutar junto con los paquetes anteriores:

- `migraciones/001_salud_ocupacional.sql`: crea y selecciona una base legacy fija.
- `migraciones/hostinger_so2demayo_completo.sql`: solo cubre dos tablas y esta incompleto para el codigo actual.
- `migraciones/20260724_0013_hostinger_paridad_total_maestro.sql`: combina responsabilidades de ambas bases.
- `migraciones/20260724_0014_hostinger_paridad_so_maestro.sql`: fue reemplazado por el paquete 0023.
- `migraciones/20260724_0015_hostinger_paridad_bd2demayo_maestro.sql`: contiene recarga destructiva de ubigeo mediante `TRUNCATE`.
- Scripts individuales 0001 a 0022: ya estan consolidados en 0023/0024 para este despliegue. No repetirlos despues.

## 6. Configuracion de conexiones

Antes del smoke test, confirmar sin publicar contrasenas:

- `DB_NAME` apunta a `u330560936_bd2DeMayo`.
- `DB_OCUP_NAME` apunta a `u330560936_so2demayo`.
- El usuario clinico tiene acceso a la base clinica.
- El usuario ocupacional tiene acceso a la base ocupacional.
- Ambas conexiones usan `utf8mb4`.

Si `DB_OCUP_NAME` no esta definido, `db_ocupacional.php` puede terminar usando `DB_NAME` en produccion. No desplegar con esa configuracion incompleta.

Seguridad: las credenciales de produccion no deben permanecer versionadas en archivos del repositorio. Rotarlas en Hostinger y moverlas a variables de entorno o configuracion externa despues de confirmar el despliegue.

## 7. Smoke test posterior

Ejecutar en este orden:

1. Iniciar sesion con un usuario autorizado.
2. Abrir Salud Ocupacional > Empresas.
3. Confirmar que cargan empresas, areas y puestos.
4. Abrir Trabajadores y verificar nombre, documento y empresa.
5. Abrir Protocolos y confirmar catalogo y montos.
6. Abrir Ordenes y confirmar que la tabla muestra paciente y DNI.
7. Abrir una orden existente y revisar historia, resultados e interconsultas.
8. En una orden de prueba, guardar un resultado como borrador y finalizarlo.
9. Confirmar que una observacion o interconsulta abierta bloquea el cierre.
10. Guardar aptitud con medico responsable.
11. Confirmar que la orden queda cerrada y se habilita Certificado.
12. Generar el certificado y verificar logo, paciente, aptitud, CMP, RNE y firma.

No usar una orden real cerrada para probar escrituras. Crear una orden controlada o usar una orden de QA.

## 8. Recuperacion ante fallo

Las sentencias DDL de MySQL hacen commit implicito; no existe rollback transaccional completo para una importacion de esquema.

Si falla la importacion:

1. Guardar el mensaje exacto y la sentencia reportada por phpMyAdmin.
2. No seguir importando otros scripts historicos.
3. Si el fallo ocurrio antes de operar el sistema, restaurar la copia de seguridad de la base afectada.
4. Si solo se agregaron columnas y no hubo error funcional, analizar el punto exacto antes de decidir restauracion.
5. Repetir el paquete solo despues de corregir la causa. Los paquetes son idempotentes.

## 9. Validacion local realizada

Los paquetes fueron probados con MySQL 8.4 en los siguientes escenarios:

- Instalacion ocupacional limpia.
- Segunda ejecucion sobre la misma base.
- Actualizacion desde la base ocupacional inicial de junio de 2026.
- Base clinica minima equivalente al dump de Hostinger.
- Segunda ejecucion del paquete clinico.

Resultados finales de la prueba ocupacional:

- 18 tablas.
- 9 columnas criticas de aptitud/snapshot.
- 15 relaciones internas.
- 0 resultados duplicados por detalle/formato.

## 10. Actualizacion incremental RNA del medico

Cuando los paquetes 0024 y 0023 ya fueron aplicados, no es necesario repetirlos para agregar el tercer registro profesional del medico.

Ejecutar en este orden:

1. `migraciones/20260725_0025_medicos_rna_clinica.sql` en la base clinica.
2. `migraciones/20260725_0026_ocupacional_medico_rna_snapshot.sql` en la base ocupacional.

Postflight esperado en ambos casos: `total = 1`. Los medicos y ordenes existentes mantienen RNA vacio; las nuevas aptitudes congelan el RNA disponible en el snapshot de la orden.
