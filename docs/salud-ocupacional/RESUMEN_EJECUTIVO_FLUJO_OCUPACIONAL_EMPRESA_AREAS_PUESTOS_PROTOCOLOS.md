# Resumen Ejecutivo (1 pagina)

## Tema
Alineacion del flujo ocupacional moderno con la logica operativa del sistema antiguo para mejorar intuicion, eficiencia y calidad de datos.

## Decision solicitada
Aprobar la evolucion funcional para que la gestion de Areas, Puestos y Protocolos vuelva a ser maestra por empresa, manteniendo la interfaz moderna.

## Situacion actual
1. En Empresas existen acciones Area y Puesto, pero redirigen a Trabajadores.
2. En Trabajadores, el Puesto se registra como texto manual por persona.
3. Esto mezcla configuracion maestra con operacion diaria, generando:
- Variaciones de nombres (inconsistencias).
- Menor intuicion para usuarios administrativos.
- Mayor esfuerzo de correccion posterior.

## Hallazgo clave
El modelo antiguo es mas intuitivo para administracion y mas eficiente a mediano plazo, porque primero configura catalogos por empresa y luego los reutiliza en toda la operacion.

## Propuesta (to-be)
Mantener look and feel moderno, pero ajustar comportamiento funcional:
1. Desde Empresas:
- Area -> Gestion maestra de Areas de la empresa.
- Puesto -> Gestion maestra de Puestos de la empresa.
- Protocolo -> Gestion de protocolos por empresa.
2. En Trabajadores:
- Empresa, Area y Puesto tomados desde catalogos maestros.
- Texto libre de Puesto solo como excepcion controlada y auditable.
3. En Ordenes:
- Consumo de datos estandarizados (empresa/area/puesto/protocolo).

## Beneficios esperados
1. Mayor intuicion de uso:
- Menos pasos ambiguos.
- Menor curva de aprendizaje.
2. Mayor eficiencia operativa:
- Menos digitacion repetitiva.
- Menos retrabajo por correcciones.
3. Mejor calidad de datos:
- Estandarizacion de Areas y Puestos.
- Mejor filtrado, reportes y reglas de protocolo.
4. Menor riesgo funcional:
- Menos errores por variacion de texto en condiciones por puesto.

## Coste / impacto
1. Impacto de cambio: medio.
2. Riesgo tecnico: bajo a medio (se reutiliza arquitectura actual).
3. Impacto de capacitacion: bajo (la UX moderna se mantiene).

## Prioridad recomendada
Alta.
Motivo: el problema afecta operaciones diarias, coherencia de datos y aplicacion de protocolos.

## Plan sugerido (4 a 6 semanas)
1. Semana 1:
- Definicion funcional final y criterios de aceptacion.
2. Semana 2-3:
- Implementar gestion maestra de Areas y Puestos por empresa.
3. Semana 3-4:
- Conectar Trabajadores a catalogos maestros.
4. Semana 5:
- Ajustes de reportes, validaciones y permisos.
5. Semana 6:
- Capacitacion, despliegue controlado y seguimiento de KPI.

## KPI de exito
1. % de trabajadores con puesto desde catalogo maestro.
2. Numero de variantes textuales de un mismo puesto por empresa.
3. Tiempo promedio de registro de trabajador.
4. Tasa de correcciones de puesto/area post-registro.
5. Incidencias de protocolo por inconsistencia de puesto.

## Recomendacion final
Aprobar la alineacion al modelo maestro por empresa, conservando la interfaz moderna.
Es la opcion con mejor balance entre adopcion del usuario, eficiencia operativa y sostenibilidad de datos.
