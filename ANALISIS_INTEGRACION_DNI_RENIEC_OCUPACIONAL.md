# Analisis de Integracion DNI para Registro de Trabajadores Ocupacionales

## Estado
- Decision: dejar en standby.
- Objetivo: conservar el analisis para implementarlo en este u otro chat sin perder contexto.
- Alcance de este documento: analisis funcional, tecnico, riesgos, alternativas de proveedor y plan de implementacion.

## Objetivo funcional de la mejora
Cuando el usuario ingrese un DNI en Salud Ocupacional > Trabajadores y presione Verificar:
1. Buscar primero en base local (pacientes del sistema clinico).
2. Si no existe en base local y el tipo de documento es DNI, consultar un proveedor de datos de identidad.
3. Si hay respuesta, autocompletar nombres y apellidos para facilitar el alta del paciente.
4. Mantener flujo actual de registro manual cuando no exista respuesta externa.

## Flujo actual identificado
### Frontend
- Pantalla principal de captura: src/pages/ocupacional/FormTrabajador.jsx
- Boton Verificar ejecuta onVerify y llama verificarIdentidadClinica.
- Si responde Paciente no encontrado, se habilita el bloque para crear paciente manualmente.

### Cliente API Frontend
- Funciones de API ocupacional: src/api/ocupacionalApi.js
- verificarIdentidadClinica consume api_ocupacional_consultar_identidad.php

### Backend
- Endpoint de verificacion actual: api_ocupacional_consultar_identidad.php
- Comportamiento actual:
  - Solo metodo GET.
  - Requiere permiso registrar_trabajadores_ocupacional.
  - Valida formato de documento.
  - Busca solo en tabla pacientes por DNI.
  - Si no encuentra: 404 Paciente no encontrado.

## Hallazgos sobre RENIEC y fuentes de datos
### Via oficial
- La via oficial para interoperabilidad estatal es PIDE (Gobierno Digital).
- Requiere proceso formal: solicitud, formatos, acuerdos de consumo y pasos administrativos.
- No es un servicio abierto inmediato tipo token publico para cualquier sistema privado.

### Via operativa inmediata (terceros)
- Se revisaron proveedores conocidos del mercado peruano que exponen consulta DNI por API.
- Modelo habitual: token y consulta desde backend.
- Algunos ofrecen plan de entrada o prueba, pero no se debe asumir gratuidad permanente ni SLA estatal.

### Conclusiones de disponibilidad
- No se confirmo una API oficial RENIEC publica y gratuita para integracion directa en este contexto.
- Implementacion rapida: proveedor tercero.
- Implementacion robusta y formal a largo plazo: PIDE.

## Decision tecnica recomendada
Aplicar estrategia hibrida por capas:
1. Capa 1: busqueda local (fuente principal).
2. Capa 2: fallback externo (solo si local no existe y documento tipo DNI).
3. Capa 3: registro manual (si proveedor externo falla o no tiene datos).

Beneficios:
- Menor dependencia externa para casos ya registrados.
- Mejor UX para altas nuevas.
- Control de costos y resiliencia.

## Requisitos no funcionales obligatorios
1. Seguridad
- Nunca exponer token de proveedor en frontend.
- Consumir proveedor solo desde backend.
- Guardar secretos en variables de entorno.

2. Privacidad y cumplimiento
- Auditar quien consulta, cuando y con que resultado.
- Publicar/actualizar politicas de tratamiento de datos personales.
- Limitar uso de datos a finalidad de atencion y registro.

3. Disponibilidad
- Timeout corto por consulta externa (ejemplo: 3 a 5 segundos).
- Manejo de caidas del proveedor con fallback manual.
- Capa de cache temporal por DNI para reducir latencia y costo.

4. Control de abuso
- Rate limit por usuario/IP/sesion.
- Mensajes de error no verbosos para evitar enumeracion masiva.

## Propuesta de arquitectura de implementacion
### A. Endpoint actual como orquestador
Mantener api_ocupacional_consultar_identidad.php como punto unico para frontend.

### B. Adaptador de proveedor
Crear una capa interna que permita cambiar proveedor sin tocar el frontend ni la logica principal.

### C. Respuesta unificada
Respuesta del endpoint debe conservar formato actual y agregar metadatos de origen:
- source: local | reniec_proxy
- confidence: high | medium | low (opcional)
- external_lookup: true/false

## Contrato funcional sugerido (sin implementar aun)
### Request
- documento_tipo
- documento_numero

### Response exitosa
- id (si existe en local)
- nombre
- apellidos
- sexo (si disponible)
- fecha_nacimiento (si disponible)
- documento_tipo
- documento_numero
- source

### Response no encontrada
- success false
- error Paciente no encontrado
- sugerencia de continuar con registro manual

## Plan de implementacion propuesto por fases
### Fase 1: Integracion minima segura
- Backend: fallback externo en api_ocupacional_consultar_identidad.php.
- Frontend: reutilizar flujo actual de Verificar y mostrar datos sugeridos cuando source sea reniec_proxy.
- Sin cambios agresivos de UX.

### Fase 2: Robustez operativa
- Agregar tabla de auditoria de consultas DNI.
- Agregar cache de respuestas exitosas y negativas con TTL.
- Agregar rate limiting.

### Fase 3: Calidad de datos y gobierno
- Reglas de normalizacion de nombres y apellidos.
- Indicador visual de dato sugerido por fuente externa.
- Flujos de conciliacion entre dato externo y dato final guardado.

## Criterios de aceptacion para implementar despues
1. Si DNI existe en local, retorno inmediato sin llamar proveedor externo.
2. Si DNI no existe y proveedor responde, se autocompletan nombres/apellidos.
3. Si proveedor no responde o falla, el usuario puede continuar con alta manual sin bloqueo.
4. No hay secretos en frontend ni en repositorio.
5. Todas las consultas externas quedan auditadas.

## Riesgos identificados
1. Dependencia de proveedor tercero
- Riesgo de caidas, cambios de contrato o limites de cuota.

2. Costo variable
- Aumento de consultas puede elevar costo mensual.

3. Riesgo legal y de privacidad
- Uso de datos personales debe documentarse y controlarse.

4. Calidad inconsistente
- Distintas fuentes pueden devolver formatos diferentes de nombres.

## Recomendacion final de arranque
- Para avanzar rapido: integrar proveedor tercero en backend con fallback manual.
- Para sostenibilidad: iniciar en paralelo gestion de acceso via PIDE.

## Lista de tareas para retomar en otro chat
1. Definir proveedor inicial y politica de costos.
2. Definir variables de entorno y politica de secretos.
3. Diseñar adaptador de proveedor en backend.
4. Actualizar endpoint de verificacion de identidad con fallback.
5. Ajustar interfaz para mostrar origen de datos.
6. Agregar auditoria y cache.
7. Probar casos feliz, no encontrado, timeout y error proveedor.
8. Documentar operacion y contingencia.

## Nota de trazabilidad
Este documento fue generado antes de implementar cambios de codigo para la integracion RENIEC/DNI, por solicitud explicita de mantener la logica en standby.
