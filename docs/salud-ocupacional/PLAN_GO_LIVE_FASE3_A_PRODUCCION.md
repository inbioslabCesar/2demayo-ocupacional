# Plan De Ejecucion Semanal - Go Live Salud Ocupacional

## Objetivo
Dejar el modulo de Salud Ocupacional listo para uso operativo diario en clinica, con control medico final, trazabilidad y salida estable a produccion.

## Alcance actual
- Fase 1: implementada.
- Fase 2: implementada.
- Fase 3: implementada en base operativa (ordenes, ejecucion por examen, cierre formal, anulacion con motivo, auditoria y reportes por orden/globales).

## Criterio de cierre total
Se considera culminado cuando:
1. Usuarios por rol pueden operar sin bloqueos en ambiente de preproduccion.
2. Existen actas de QA/UAT firmadas.
3. Se ejecuta despliegue con checklist y monitoreo post salida 7 dias.

## Semana 1 - Cierre Clinico Operativo
### Entregables
1. Flujo de aptitud ocupacional (apto/no apto/restricciones/recomendaciones) enlazado a orden cerrada.
2. Generacion de certificado de aptitud desde estado cerrada.
3. Plantilla de salida clinica por tipo de evaluacion (PRE/PER/POST).
4. Bloqueo de certificados si la orden no esta cerrada.

### Criterios de aceptacion
1. Cada orden cerrada tiene aptitud final y medico responsable.
2. Certificado PDF muestra datos de orden, paciente, empresa, protocolo, aptitud y firma.
3. No se permite emitir certificado en estados emitida/en_proceso/completada/anulada.

### Riesgos
1. Diferencias de formato del certificado entre sedes.
2. Firma medica no configurada en algunos usuarios.

## Semana 2 - Operacion Por Rol Y Permisos
### Entregables
1. Matriz de permisos finos en base de datos por perfil:
- ver_ordenes_ocupacional
- registrar_ordenes_ocupacional
- ejecutar_ordenes_ocupacional
- cerrar_ordenes_ocupacional
- anular_ordenes_ocupacional
- ver_auditoria_ordenes_ocupacional
2. Scripts de carga inicial de permisos por rol (administrador, recepcion, medico ocupacional, auditor).
3. Bandeja por rol (filtros y vistas guardadas).

### Criterios de aceptacion
1. Recepcion no puede cerrar ni anular.
2. Medico ocupacional puede ejecutar y cerrar.
3. Auditor puede ver bitacora y reportes sin editar.
4. Administrador conserva control total.

### Riesgos
1. Perfiles historicos con permisos mezclados.
2. Operadores usando cuentas compartidas.

## Semana 3 - QA, UAT Y Datos
### Entregables
1. Suite de pruebas E2E ocupacional:
- crear orden
- ejecutar detalle
- completar
- cerrar
- anular con motivo
- filtrar auditoria
- exportar PDF/Excel
2. Pruebas por dispositivo:
- desktop
- movil
3. Migracion de datos minimos para continuidad (catalogos y parametros criticos).
4. Manual corto por rol para operacion diaria.

### Criterios de aceptacion
1. Tasa de exito >= 95% en casos E2E.
2. Cero errores bloqueantes en UAT.
3. Reportes y certificados coherentes con legacy.

### Riesgos
1. Datos legacy inconsistentes.
2. Casos no previstos de empresas con protocolos incompletos.

## Semana 4 - Despliegue Controlado
### Entregables
1. Checklist de despliegue (backup, migraciones, permisos, smoke test).
2. Plan de rollback documentado.
3. Monitoreo post salida 7 dias:
- errores API
- tiempos de respuesta
- volumen de ordenes
- incidencias de usuarios
4. Acta de salida a produccion.

### Criterios de aceptacion
1. Sin incidentes criticos P1 en 7 dias.
2. Sin perdida de datos de ordenes.
3. Operacion continua sin volver al flujo legacy.

## Arquitectura faltante para cierre productivo
## 1. Dominio Clinico De Aptitud
- Servicio de evaluacion final medica conectado a orden cerrada.
- Registro de medico responsable y firma.

## 2. Dominio Documental
- Servicio de generacion y versionado de certificados.
- Trazabilidad de reimpresiones y anulaciones documentales.

## 3. Dominio De Seguridad Y Gobierno
- RBAC persistente en base de datos con semilla por perfil.
- Auditoria transversal de acciones sensibles.

## 4. Dominio De Observabilidad
- Logs estructurados por endpoint ocupacional.
- Alertas basicas de fallas (errores 5xx y latencia).

## 5. Dominio Analitico
- KPIs consolidados por empresa, tipo de evaluacion y estado.
- Reportes operativos con filtros por fecha y usuario.

## Plan de arranque inmediato (48 horas)
1. Activar permisos por rol en preproduccion.
2. Capacitar 1 hora a recepcion y medico ocupacional.
3. Ejecutar 10 casos reales supervisados.
4. Habilitar go-live controlado con mesa de soporte.

## Definicion final de listo para operar
1. Clinica puede registrar ordenes nuevas sin usar legacy.
2. Medico puede cerrar ordenes y emitir documento final.
3. Auditor puede reconstruir historico de acciones por orden.
4. Jefatura puede ver KPIs globales diarios.
