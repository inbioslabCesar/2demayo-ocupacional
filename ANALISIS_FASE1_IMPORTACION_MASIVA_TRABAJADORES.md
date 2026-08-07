# Fase 1 - Importacion Masiva de Trabajadores (Solo Maestro)

## Objetivo
Implementar registro masivo trabajador-empresa en modo maestro, separado del flujo transaccional de ordenes/certificados.

## Alcance de Fase 1
Incluye:
- lectura de Excel XLSX
- validacion por fila
- resolucion/creacion de paciente clinico por DNI
- vinculacion ocupacional en pacientes_ocupacionales
- reporte CSV de errores
- dry-run sin persistencia

Excluye:
- aptitud
- restricciones
- fechas de vigencia de certificado
- creacion de ordenes medicas u ordenes ocupacionales
- resultados de examenes

## Implementacion aplicada
- Script nuevo: scripts/import_trabajadores_ocupacionales_excel.php
- Modo de ejecucion: CLI
- Motor: lectura XLSX nativa con ZipArchive + transacciones en BD clinica y BD ocupacional

## Reglas clave de negocio
1. La empresa objetivo se define por empresa_id o empresa_ruc.
2. El DNI es obligatorio y debe tener 8 digitos.
3. Si no existe paciente clinico, se crea en pacientes (upsert-paciente=1 por defecto).
4. Si ya existe paciente:
- por defecto no se modifica ficha core (sync-core=0)
- opcionalmente puede sincronizar nombre/apellido/sexo/fecha_nacimiento (sync-core=1)
5. En pacientes_ocupacionales:
- si no existe relacion empresa+paciente: inserta
- si existe y estado activo: actualiza solo si update-activo=1
- si existe y estado retirado/anulado: reactiva
6. Si hay errores:
- dry-run=1: siempre rollback
- dry-run=0: rollback total cuando existe al menos 1 error

## Campos reconocidos en cabecera
Alias aceptados por el importador:
- dni: dni, documento, numero_documento, nro_documento
- tipo documento: tipo_documento, tipo_doc
- nombres: nombres, nombre
- apellidos: apellidos, apellido
- nombre completo: apellidos_nombres, apellidos_y_nombres, nombre_completo
- sexo: genero, sexo
- fecha nacimiento: fecha_nacimiento, fec_nac, fecha_de_nacimiento
- puesto: cargo_categoria, cargo, puesto_trabajo, puesto, ocupacion
- area: area, area_riesgo
- fecha ingreso: fecha_ingreso, fecha_ingreso_empresa
- tipo contrato: tipo_contrato

## Comandos de uso
### 1) Simulacion segura (recomendado primero)
php scripts/import_trabajadores_ocupacionales_excel.php --file="C:/ruta/lote.xlsx" --empresa-id=12 --dry-run=1

### 2) Simulacion por RUC de empresa
php scripts/import_trabajadores_ocupacionales_excel.php --file="C:/ruta/lote.xlsx" --empresa-ruc=20123456789 --dry-run=1

### 3) Ejecucion real
php scripts/import_trabajadores_ocupacionales_excel.php --file="C:/ruta/lote.xlsx" --empresa-id=12 --dry-run=0

### 4) Ejecucion real con sincronizacion de datos core
php scripts/import_trabajadores_ocupacionales_excel.php --file="C:/ruta/lote.xlsx" --empresa-id=12 --dry-run=0 --sync-core=1

## Parametros opcionales utiles
- --sheet=1
- --header-row=N
- --default-fecha-ingreso=YYYY-MM-DD
- --default-puesto="OPERARIO"
- --default-area="PRODUCCION"
- --default-sexo=M
- --upsert-paciente=0|1
- --update-activo=0|1
- --error-file="C:/ruta/reporte_errores.csv"

## Salida esperada
El script imprime:
- conteo de pacientes insertados/actualizados
- conteo de trabajadores insertados/actualizados/reactivados
- filas vacias
- total de errores
- ruta del CSV de errores cuando aplique
- estado final: SIMULADO, ROLLBACK o OK

## Estrategia de despliegue recomendada
1. Ejecutar dry-run con muestra pequena.
2. Corregir errores de estructura y datos.
3. Repetir dry-run hasta errores criticos en cero.
4. Ejecutar dry-run sobre lote completo.
5. Ejecutar dry-run=0 en ventana controlada.
6. Validar conteos en pacientes y pacientes_ocupacionales.

## Separacion con Fase 2
Fase 2 procesara otro Excel orientado a transacciones (ordenes/certificado) con campos como aptitud, restricciones y vigencias.
No se mezcla en este script para evitar acoplamiento entre alta maestra y procesos asistenciales.
