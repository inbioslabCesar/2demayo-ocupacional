# ANALISIS TECNICO - PANEL MEDICO OCUPACIONAL (SOLO LECTURA)

## 1. Objetivo
Habilitar una vista dedicada para el rol medico donde pueda ver y completar evaluaciones de Salud Ocupacional asociadas directamente a su usuario (medico responsable de la orden), manteniendo aislamiento estricto por ownership y sin romper el flujo clinico actual de "Mis Consultas".

## 2. Estado Actual (verificado en codigo)

### 2.1 Panel medico clinico
- La vista medico usa "Mis Consultas" y ya filtra por medico autenticado en backend.
- Frontend principal:
  - src/pages/MedicoConsultasPage.jsx
  - src/components/medico/MedicoConsultas.jsx
- Backend principal:
  - api_consultas.php
- Regla de aislamiento actual (clinico):
  - Si la sesion es medico, se fuerza medico_id de sesion y no permite consultar otro medico.

### 2.2 Flujo ocupacional
- Las ordenes ocupacionales guardan medico_responsable_id y medico_nombre_snapshot.
- Backend principal:
  - api_ocupacional_ordenes.php
  - api_ocupacional_resultados.php
- Actualmente las rutas ocupacionales en frontend estan protegidas para administrador/recepcionista, no para rol medico.
- No existe hoy una vista dedicada "Mis Evaluaciones Ocupacionales" dentro del panel medico.

## 3. Logica Propuesta
Crear una vista medica dedicada a evaluaciones ocupacionales con comportamiento análogo a "Mis Consultas", pero sobre ordenes/detalles ocupacionales.

Regla funcional:
- Un medico solo ve evaluaciones ocupacionales donde:
  - ocupacional_ordenes.medico_responsable_id == medico_id de sesion.
- Un medico solo puede editar/finalizar resultados clinicos de examenes de sus ordenes.

## 4. Impacto por Capa

### 4.1 Seguridad y autorizacion (impacto alto)
- Es el punto critico.
- No debe depender solo de filtros de frontend.
- Deben agregarse validaciones de ownership en backend ocupacional:
  - Listado
  - Detalle de orden
  - Obtencion de resultado clinico por detalle
  - Guardado/finalizado de resultado

Riesgo si no se hace:
- Exposicion de evaluaciones de otros medicos por URL o llamadas directas.

### 4.2 Backend API (impacto medio-alto)
- Reutilizable con extensiones minimas:
  - api_ocupacional_ordenes.php (listar_ordenes, detalle_orden)
  - api_ocupacional_resultados.php (obtener, guardar)
- Se recomienda estrategia "fail closed":
  - Si no se puede resolver ownership, responder 403.

### 4.3 Frontend rutas y UX (impacto medio)
- Agregar ruta medica dedicada:
  - /mis-evaluaciones-ocupacionales
- Integrar acceso en sidebar medico.
- Mantener separado de /mis-consultas para no mezclar dominios clinico/ocupacional.

### 4.4 Operacion medica (impacto positivo alto)
- Menor friccion operativa.
- El medico completa PSI/EPW/FOBIA y otros examenes ocupacionales desde su panel.
- Mejor continuidad y trazabilidad del acto medico.

## 5. Alcance Tecnico Recomendado (faseado)

### Fase 1 - Seguridad backend (obligatoria antes de UI)
1. Resolver medico de sesion en APIs ocupacionales.
2. En modo sesion medico:
   - aplicar filtro por medico_responsable_id en listados.
   - validar ownership por orden/detalle en endpoints de lectura/escritura.
3. Responder 403 cuando no corresponda ownership.

### Fase 2 - Vista dedicada medica ocupacional
1. Nueva pagina React para listado de evaluaciones ocupacionales del medico.
2. Filtros minimos:
   - estado (emitida, en_proceso, completada)
   - fecha_desde / fecha_hasta
   - busqueda por codigo orden, documento, trabajador
3. Accion "Abrir examen" hacia formato clinico ocupacional.

### Fase 3 - Integracion de navegacion
1. En sidebar medico, nuevo item "Mis evaluaciones ocupacionales".
2. Mantener "Mis consultas" sin regresiones.
3. Ajustar home por rol medico (si se define) o mantener home actual y solo agregar acceso.

## 6. Endpoints y Rutas con Impacto Directo

### Backend (ocupacional)
- api_ocupacional_ordenes.php
  - accion=listar_ordenes
  - accion=detalle_orden
- api_ocupacional_resultados.php
  - GET obtener por orden_detalle_id
  - POST guardar resultado

### Frontend
- src/App.jsx
  - definicion de ruta para rol medico
- src/components/medico/SidebarMedico.jsx
  - enlace a nueva vista
- Nueva pagina propuesta:
  - src/pages/ocupacional/MisEvaluacionesOcupacionalesPage.jsx (nombre sugerido)

## 7. Checklist de Seguridad (gate de aprobacion)
- [ ] En sesion medico, listado ocupacional solo devuelve ordenes de su medico_responsable_id.
- [ ] En sesion medico, detalle de orden valida ownership antes de responder.
- [ ] En sesion medico, lectura de resultado por detalle valida ownership.
- [ ] En sesion medico, guardado/finalizado valida ownership.
- [ ] Acceso directo por URL a orden ajena retorna 403 (no 200, no 404 ambiguo).
- [ ] Auditoria registra actor medico en cambios de resultado.
- [ ] Pruebas con medico A intentando ver/editar orden de medico B.

## 8. Riesgos y Mitigaciones

### Riesgo 1: fuga de datos por omision de filtro backend
- Mitigacion: validacion ownership en cada endpoint critico y pruebas negativas.

### Riesgo 2: regresion en flujo administrativo ocupacional
- Mitigacion: separar ruta/vista medica sin cambiar comportamiento admin-recepcion.

### Riesgo 3: conflicto de permisos existentes
- Mitigacion: definir regla explicita para rol medico (permiso ocupacional requerido o bypass controlado por rol + ownership).

## 9. Criterios de Aceptacion
1. Medico autenticado solo visualiza sus evaluaciones ocupacionales.
2. Medico puede abrir y completar examenes de sus ordenes desde su panel.
3. Medico no puede consultar ni editar evaluaciones de otros medicos.
4. Flujo clinico "Mis Consultas" permanece estable.
5. Validaciones de seguridad pasan pruebas negativas.

## 10. Recomendacion Final
La iniciativa es tecnicamente correcta y de alto valor operativo. Se recomienda aprobar implementacion con enfoque "backend security first" y luego habilitar la vista dedicada en el panel medico.
