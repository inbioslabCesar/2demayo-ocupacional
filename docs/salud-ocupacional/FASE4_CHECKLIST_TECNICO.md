# Fase 4 - Checklist Tecnico De Implementacion

Fecha: 2026-07-02
Proyecto destino: 2demayo-ocupacional
Proyecto fuente de contraste: clinicaocupacional
Base recomendada de trabajo: `main`
Rama recomendada: `feature/salud-ocupacional-fase4`

Estado actual:

- Rama de trabajo ya creada: `feature/salud-ocupacional-fase4`
- Carpeta base SQL ya reservada: `sql/salud-ocupacional/fase4_historia_clinica/`

## 1. Objetivo de Fase 4

Completar en `2demayo-ocupacional` la capa clinica ocupacional que todavia existe solo en `clinicaocupacional`, manteniendo como base la Fase 3 ya construida en ordenes ocupacionales.

La Fase 4 no debe rehacer Fase 3.

La Fase 4 debe apoyarse en:

- empresas
- trabajadores
- examenes generales
- catalogo por empresa
- protocolos
- ordenes ocupacionales
- ejecucion por detalle
- cierre y aptitud final

## 2. Punto de partida confirmado

En `2demayo-ocupacional` ya existe:

- modulo administrativo ocupacional completo hasta ordenes
- backend de ordenes con auditoria, cierre y certificado
- frontend de ordenes con detalle, ejecucion y reportes

En `clinicaocupacional` existe adicionalmente:

- historia ocupacional editable por orden
- historia clinica ocupacional PDF consolidada
- formatos clinicos ocupacionales por examen
- capa RRHH de evaluacion/certificados
- base ocupacional analitica y consolidada

## 3. Brecha funcional exacta a cubrir

### 3.1 Bloque Historia Ocupacional

Existe en legacy:

- pantalla de historia ocupacional por orden
- guardado de registros historicos laborales
- consulta y anulado logico de historia ocupacional

Referencia legacy:

- `application/controllers/Ordenocupacional.php` metodo `historiaocupacional`
- `application/controllers/Ordenocupacional.php` metodo `guardarHistoriaOcupacional`
- `application/controllers/Ordenocupacional.php` metodo `consultarHistoriaOcupacional`
- `application/controllers/Ordenocupacional.php` metodo `anularHistoriaOcupacional`
- `application/views/historiaocupacional.php`

Estado en 2demayo:

- no existe pantalla equivalente dentro de `src/pages/ocupacional`
- no existe API ocupacional equivalente visible
- no existe tabla SQL visible para historia ocupacional en modulo nuevo

### 3.2 Bloque Historia Clinica Ocupacional Consolidada

Existe en legacy:

- armado de historia clinica ocupacional completa por orden
- consolidacion de multiples resultados y formatos
- salida PDF/impresion clinica

Referencia legacy:

- `application/controllers/Rrhhevaluacion.php` flujo `historia($id)`
- `application/views/historia/pdfhistoriaclinica.php`

Estado en 2demayo:

- no existe vista clinica ocupacional consolidada equivalente
- no existe PDF clinico ocupacional equivalente
- la orden nueva solo muestra detalle administrativo de examenes

### 3.3 Bloque Formatos Clinicos Por Examen

Existe en legacy:

- triaje
- evaluacion medica
- antecedentes
- altura
- laboratorio
- audiometria
- oftalmologia
- psicologia
- ekg
- ecografia
- osteomuscular
- musculo-esqueletica
- epworth
- fobia
- odontologia
- neurologico / ten_* / geo_0001
- examen fisico
- diagnosticos

Referencias legacy detectadas:

- `application/views/historia/ev_0001.php`
- `application/views/historia/lab_0001.php`
- `application/views/historia/aud_0001.php`
- `application/views/historia/oft_0001.php`
- `application/views/historia/psi_0001.php`
- `application/views/historia/ekg_0001.php`
- `application/views/historia/eco_0001.php`
- `application/views/historia/ost_0001.php`
- `application/views/historia/mus_0001.php`
- `application/views/historia/epw_0001.php`
- `application/views/historia/fobia.php`
- `application/views/historia/odo_0001.php`
- `application/views/historia/rayosx.php`
- `application/views/historia/evaluacion_medica/antecedentes.php`
- `application/views/historia/evaluacion_medica/altura.php`
- `application/views/historia/evaluacion_medica/ev_0001.php`
- `application/views/historia/evaluacion_medica/geo_0001.php`
- `application/views/historia/evaluacion_medica/ten_0001.php`

Estado en 2demayo:

- no hay modulo de captura clinica ocupacional por formato
- no hay tabla de resultados ocupacionales por examen visible en el repo nuevo
- no hay acople entre `ocupacional_orden_detalle` y formularios clinicos de ejecucion real

### 3.4 Bloque RRHH Ocupacional

Existe en legacy:

- bandeja RRHH evaluacion
- bandeja RRHH certificado
- acceso a historia clinica ocupacional y documentos asociados

Referencias legacy:

- `application/controllers/Rrhhevaluacion.php`
- `application/controllers/Rrhhcertificado.php`

Estado en 2demayo:

- no existe modulo equivalente dentro del arbol ocupacional nuevo
- el certificado actual de orden es operativo, pero no reemplaza aun toda la capa RRHH legacy

### 3.5 Bloque Base Ocupacional Analitica

Existe en legacy:

- export consolidado de datos clinicos ocupacionales
- estadistica descriptiva por empresa, evaluacion y resultado

Referencias legacy:

- `application/controllers/Baseocupacional.php`
- `application/views/baseocupacional.php`

Estado en 2demayo:

- solo existe reporte de ordenes
- no existe consolidado clinico ocupacional equivalente

## 4. Checklist tecnico de Fase 4

### 4.1 Definicion funcional inicial

- [ ] Confirmar con negocio si Fase 4 replicara el flujo legacy completo o una version reducida adaptada al nuevo modelo.
- [ ] Confirmar si todos los formatos del legacy siguen vigentes o si algunos se retiran.
- [ ] Confirmar si el cierre medico final seguira en la orden nueva o migrara a una historia clinica ocupacional superior.
- [ ] Confirmar si RRHH tendra modulo propio o solo vistas filtradas del mismo modulo ocupacional.

### 4.2 Diseño de datos

- [ ] Definir carpeta SQL de la mejora dentro de `sql/`.
- [ ] Diseñar tabla base de historia ocupacional vinculada a `ocupacional_ordenes`.
- [ ] Diseñar tabla o esquema de resultados/formularios clinicos por examen.
- [ ] Definir si cada formato tendra tabla propia o si se usara un modelo mixto con JSON controlado.
- [ ] Definir trazabilidad: `created_by`, `updated_by`, `created_at`, `updated_at`, estado logico y control de cierre.
- [ ] Definir si la historia ocupacional permite multiples registros historicos por orden como en legacy.

### 4.3 Historia ocupacional

- [ ] Crear endpoints para listar, guardar, consultar y anular historia ocupacional.
- [ ] Crear pantalla ocupacional equivalente para gestionar historia laboral del trabajador dentro de la orden.
- [ ] Vincular historia ocupacional a la orden seleccionada.
- [ ] Definir bloqueo de edicion cuando la orden este cerrada, salvo permisos especiales.

### 4.4 Formatos clinicos por examen

- [ ] Definir catálogo de formatos activos a migrar desde legacy.
- [ ] Mapear qué formatos dependen del examen general, del protocolo o del tipo de evaluacion.
- [ ] Crear estrategia de apertura de formato desde cada detalle de `ocupacional_orden_detalle`.
- [ ] Resolver persistencia de resultados clinicos por examen.
- [ ] Definir estado real de ejecucion: un examen no debe marcarse `realizado` sin respaldo clinico si el formato lo requiere.
- [ ] Crear criterio de obligatoriedad por formato.

### 4.5 Historia clinica ocupacional consolidada

- [ ] Crear endpoint para consolidar la historia clinica ocupacional completa por orden.
- [ ] Crear vista de lectura clinica consolidada.
- [ ] Crear salida PDF equivalente o superior al legacy.
- [ ] Definir qué datos saldran desde tablas clinicas nuevas y cuáles seguiran viniendo de la orden.

#### Criterio de impresion profesional para Fase 4

Tomar como referencia directa la logica ya existente en `2demayo-ocupacional` para resultados profesionales de laboratorio, especialmente:

- `descargar_resultados_laboratorio.php`
- `src/components/print/ImpresionAnalisisLaboratorio.jsx`

Decisiones aterrizadas para formatos ocupacionales:

- Separar `solicitud/orden` de `resultado/informe profesional`.
- La hoja de solicitud puede ser compacta, pero el formato final imprimible debe ser clínico-profesional.
- El PDF profesional debe incluir encabezado institucional real con logo configurable, datos completos del paciente y contexto del examen/orden.
- Debe mostrar bloques tabulares claros, tipografía estable para impresión, control de saltos de página y estructura consistente por sección.
- Debe incluir firma profesional real, cargo y colegiatura cuando aplique, igual que el flujo de laboratorio actual.
- Debe incorporar observaciones, referencias o conclusiones clínicas de manera visible, no como texto suelto al final.
- Cuando un formato use rangos, normalidad, restricciones o criterios de aptitud, deben verse como “valores de referencia” clínicos comparables al estilo laboratorio.
- Debe poder distinguir visualmente datos fuera de criterio o hallazgos relevantes, similar al marcado de fuera de rango en laboratorio.
- El objetivo no es un PDF administrativo simple, sino un documento profesional apto para archivo clínico, validación médica y entrega externa.

### 4.6 RRHH y certificados complementarios

- [ ] Evaluar si el certificado actual de Fase 3 cubre totalmente la necesidad RRHH.
- [ ] Si no la cubre, diseñar bandeja RRHH evaluacion.
- [ ] Diseñar bandeja RRHH certificado.
- [ ] Definir perfiles y permisos especificos para RRHH.

### 4.7 Permisos y seguridad

- [ ] Cerrar primero la deuda de permisos finos de Fase 3.
- [ ] Agregar permisos nuevos de Fase 4 si habrá captura clínica, lectura clínica, liberación o reimpresión.
- [ ] Reflejar permisos tanto en backend como en frontend.

### 4.8 Reportes y analítica

- [ ] Definir si la base ocupacional analítica entra dentro de Fase 4 o Fase 5.
- [ ] Si entra en Fase 4, crear una versión mínima del consolidado clínico por empresa y rango de fechas.
- [ ] Alinear nombres de campos y salidas con reportes legacy para facilitar validación.

### 4.9 QA funcional

- [ ] Caso completo: crear orden -> ejecutar formato clínico -> cerrar -> emitir documento.
- [ ] Caso de anulación antes de cierre.
- [ ] Caso de intento de edición posterior al cierre.
- [ ] Caso de trabajador con protocolos sin formatos completos.
- [ ] Caso de reimpresión documental.

## 5. Orden sugerido de implementación

Orden recomendado para construir Fase 4:

1. Definición funcional exacta de formatos vigentes.
2. Diseño SQL en carpeta nueva bajo `sql/`.
3. Historia ocupacional.
4. Estructura de resultados clínicos por examen.
5. UI de captura clínica por detalle de orden.
6. Historia clínica ocupacional consolidada.
7. PDF clínico ocupacional.
8. RRHH complementario.
9. Reportes analíticos.
10. QA/UAT.

## 6. Carpeta SQL recomendada para esta mejora

Cuando empiece la implementación, usar una carpeta de este tipo:

- `sql/salud-ocupacional/fase4_historia_clinica/`

Estructura sugerida:

- `2026-07-02_01_historia_ocupacional_base.sql`
- `2026-07-02_02_resultados_clinicos_por_examen.sql`
- `2026-07-02_03_indices_y_auditoria.sql`
- `2026-07-02_04_semillas_o_catalogos_iniciales.sql`

## 7. Criterio de salida de Fase 4

Fase 4 puede considerarse cerrada solo cuando:

- exista captura clínica ocupacional real por orden
- exista historia ocupacional editable y consultable
- exista historia clínica ocupacional consolidada
- exista PDF clínico ocupacional usable
- los permisos estén correctamente cerrados
- el flujo ya no dependa del legacy para la parte clínica ocupacional definida

## 8. Resumen ejecutivo

La siguiente implementación no debe empezar “desde cero”.

Debe empezar sobre la orden ocupacional moderna ya construida en Fase 3 y completar la capa clínica que todavía vive en `clinicaocupacional`.

Si se pierde el chat, este checklist debe tomarse como la hoja de ruta técnica inicial para construir Fase 4.