# Analisis de Continuidad: Flujo Completo de Salud Ocupacional

Fecha de corte: 2026-07-25

Repositorio: `2demayo-ocupacional`

Objetivo: conservar el estado funcional, las brechas detectadas y el plan de implementacion para que el trabajo pueda retomarse en otra sesion sin depender del historial del chat.

## 1. Resumen ejecutivo

El nucleo clinico ocupacional ya permite operar el flujo principal y emitir certificados:

1. Configurar empresa, catalogos y protocolo.
2. Registrar o vincular al trabajador con el paciente clinico.
3. Crear una orden con los examenes aplicables.
4. Registrar y finalizar resultados clinicos.
5. Gestionar observaciones e interconsultas.
6. Registrar aptitud final y medico responsable.
7. Cerrar la orden mediante el guardado de aptitud.
8. Generar el certificado ocupacional en PDF.

La emision de certificados esta operativa. Las brechas descritas en este documento no impiden emitirlos actualmente; corresponden al cierre administrativo, documental, de permisos, escalabilidad por areas y robustez de produccion.

Validacion funcional usada durante QA:

- Orden `OO000010`.
- Estado final: `cerrada`.
- Resultados finalizados: `2/2`.
- Aptitud: `APTO`.
- Certificado emitido y auditado como evento.
- Paciente visible en tabla y detalle: `SANDRA MIQUEAS ZAPATA`, documento `00127069`.

## 2. Arquitectura relevante

### 2.1 Frontend

- Pagina central del flujo: `src/pages/ocupacional/OrdenesOcupacionalesPage.jsx`.
- Cliente API: `src/api/ocupacionalApi.js`.
- Paginas de configuracion ocupacional: `src/pages/ocupacional/`.
- Generacion de PDF: navegador, mediante `jspdf` y `jspdf-autotable`.

### 2.2 Backend

- Ordenes y ciclo de vida: `api_ocupacional_ordenes.php`.
- Resultados y plantillas: `api_ocupacional_resultados.php`.
- Historia ocupacional: `api_ocupacional_historia.php`.
- Consolidado clinico: `api_ocupacional_clinica.php`.
- Interconsultas y levantamientos: `api_ocupacional_interconsultas.php`.
- Empresas: `api_ocupacional_empresas.php`.
- Trabajadores: `api_ocupacional_trabajadores.php`.
- Protocolos: `api_ocupacional_protocolos.php`.

La identidad del paciente reside en la base clinica principal (`pacientes`). La relacion laboral y ocupacional reside en `pacientes_ocupacionales`, vinculada mediante `external_patient_id`.

### 2.3 Datos principales

- `pacientes`: identidad clinica maestra.
- `pacientes_ocupacionales`: relacion laboral, empresa, puesto y documento.
- `ocupacional_ordenes`: cabecera de orden y aptitud final.
- `ocupacional_orden_detalle`: examenes solicitados y estado de ejecucion.
- `ocupacional_resultados_clinicos`: resultados, estado y datos JSON estructurados.
- `ocupacional_historia_ocupacional`: historia por orden.
- `ocupacional_interconsultas`: observaciones, respuestas y levantamientos.
- `ocupacional_orden_eventos`: bitacora y auditoria operativa.

## 3. Estado funcional implementado

### 3.1 Empresas, areas, puestos y catalogos

Estado: implementado.

Existe CRUD de empresas, catalogos laborales por empresa, catalogo de examenes y validaciones de integridad. Los protocolos pueden asociar examenes, precios y condiciones de aplicacion.

Archivos principales:

- `src/pages/ocupacional/EmpresasOcupacionalesPage.jsx`
- `src/pages/ocupacional/CatalogoEmpresaExamenesPage.jsx`
- `src/pages/ocupacional/ProtocolosOcupacionalesPage.jsx`
- `api_ocupacional_empresas.php`
- `api_ocupacional_catalogo.php`
- `api_ocupacional_catalogos_laborales.php`
- `api_ocupacional_protocolos.php`

### 3.2 Trabajador y filiacion clinica

Estado: implementado.

El trabajador ocupacional se vincula con el paciente clinico mediante `external_patient_id`. Se valida la identidad y se conserva un unico maestro para nombre, apellidos, sexo, fecha de nacimiento, historia clinica y biometria.

Archivos principales:

- `src/pages/ocupacional/FormTrabajador.jsx`
- `api_ocupacional_consultar_identidad.php`
- `api_ocupacional_trabajadores.php`
- `api_pacientes_biometria.php`

### 3.3 Creacion de orden

Estado: implementado.

La pantalla permite seleccionar empresa, trabajador, protocolo, tipo de evaluacion, fecha, medico, modo, gestante, subcontrata y empresa a facturar. Antes de registrar se previsualizan los examenes aplicables y el total.

El backend valida empresa, trabajador, protocolo y catalogo; resuelve las reglas de aplicabilidad y crea cabecera y detalles.

### 3.4 Tabla de ordenes

Estado: implementado con mejora de identidad.

La tabla muestra codigo, fecha, estado, avance, empresa, paciente, documento, puesto, protocolo, tipo, monto y acciones. El nombre completo se resuelve desde `pacientes` por lote, evitando consultas N+1.

Pendiente asociado: el buscador todavia filtra principalmente por codigo, documento o protocolo. Debe ampliarse para buscar tambien por nombre y apellido.

### 3.5 Ejecucion y resultados clinicos

Estado: implementado para el conjunto actual de formatos.

Formatos estructurados confirmados:

- Triaje clinico, con calculo de IMC.
- Evaluacion medica ocupacional.
- Audiometria basica.
- Laboratorio basico y parametros contextuales.
- Oftalmologia basica.
- Psicologia basica.
- EKG basico.
- Formato general para examenes sin plantilla especializada.

Los borradores pueden quedar incompletos. La finalizacion exige validaciones propias del tipo de examen. Un examen solo cuenta como completado cuando su ejecucion esta en `realizado` y su resultado esta `finalizado`.

### 3.6 Historia ocupacional

Estado: implementado y simplificado para uso medico.

Los campos `antecedentes_laborales_json`, `antecedentes_patologicos_json` y `habitos_json` se conservan en backend, pero la interfaz ya no muestra JSON ni corchetes. Se presentan listas clinicas con estados vacios, agregar, editar y eliminar.

El bloque muestra en modo de solo lectura:

- Nombres y apellidos del paciente.
- Documento.
- Historia clinica.
- Edad.
- Sexo.

La identidad no se duplica dentro de la historia; siempre proviene del paciente maestro.

### 3.7 Observaciones e interconsultas

Estado: implementado.

Un resultado observado puede originar interconsulta. Existen acciones para solicitar, responder, adjuntar PDF, levantar observacion con medico responsable y anular. Las interconsultas abiertas bloquean el cierre clinico.

### 3.8 Aptitud y cierre

Estado: implementado.

La aptitud puede registrarse cuando la orden esta `completada` o `cerrada`. Al guardar aptitud sobre una orden `completada`, el backend valida todas las puertas clinicas y la cierra atomicamente.

Puertas de cierre confirmadas:

- La orden debe tener al menos un examen.
- Todos los resultados deben estar finalizados.
- No puede haber examenes observados.
- No puede haber interconsultas abiertas.
- Deben seleccionarse aptitud y medico responsable.

Estados de aptitud disponibles:

- `APTO`
- `APTO_CON_RESTRICCIONES`
- `NO_APTO`

El backend conserva snapshots de nombre, especialidad, CMP, RNE y firma del medico responsable.

### 3.9 Certificado y PDFs

Estado: emision operativa.

El certificado solo se habilita cuando la orden esta cerrada y tiene aptitud. Su formato sigue la grilla ocupacional requerida por la clinica e incluye:

- Logo ocupacional configurable.
- Apellidos y nombres.
- Tipo de evaluacion.
- Documento, edad y sexo.
- Puesto, HC y fecha.
- Empresa y protocolo.
- Aptitud y restricciones.
- Recomendaciones.
- Bloque de firma, CMP y RNE.

Tambien existen:

- Hoja de ruta.
- PDF individual de resultado clinico.
- PDF del consolidado clinico.
- Reporte global PDF y Excel.
- Auditoria de emision de certificado y PDF individual.

Limitacion actual: los archivos se generan en el navegador. El backend registra el evento, pero no conserva el binario exacto emitido.

## 4. Brechas pendientes priorizadas

## 4.1 Prioridad critica para produccion controlada

### A. Matriz real de permisos ocupacionales

Problema:

- La operacion esta concentrada en una pantalla.
- Frontend y backend no expresan de forma completamente coherente los perfiles clinicos.
- Algunas funciones backend admiten permisos de compatibilidad demasiado amplios.

Objetivo:

- Separar permisos de ver, registrar, ejecutar, finalizar, observar, responder interconsulta, levantar, guardar aptitud, cerrar, anular, emitir y reimprimir.
- Definir perfiles de recepcion, enfermeria, laboratorio, audiometria, psicologia, medico ocupacional y administrador.
- Eliminar fallbacks de permisos que otorguen acceso clinico mediante permisos de trabajadores.

Criterios de aceptacion:

- Cada perfil solo ve y ejecuta sus acciones autorizadas.
- La API bloquea acciones aun cuando se intente invocarlas fuera de la UI.
- Existe una prueba de permisos por rol para cada accion critica.

### B. Consolidado clinico con resultados completos

Problema:

`api_ocupacional_clinica.php` informa avance y existencia de resultados, pero no consolida el contenido clinico detallado de todos los examenes.

Objetivo:

- Devolver resultados finalizados con plantilla, datos, conclusion, observacion, medico y fecha.
- Mostrar esa informacion en la vista consolidada.
- Incluirla en el PDF clinico consolidado.

Criterios de aceptacion:

- El medico ocupacional puede revisar toda la evidencia sin abrir cada examen.
- El consolidado respeta snapshots y no toma nombres actuales de catalogos cuando existe evidencia historica.
- Los resultados observados y levantamientos quedan claramente identificados.

### C. Repositorio documental inmutable

Problema:

Los documentos se descargan localmente y solo queda un evento de emision. Una reimpresion futura puede usar configuracion, logo o firma diferentes.

Objetivo:

- Guardar certificado, PDFs individuales y consolidado en el servidor.
- Registrar tipo, version, ruta, hash, fecha, usuario, medico y orden.
- Reimprimir el archivo historico, no regenerarlo silenciosamente.

Criterios de aceptacion:

- Cada emision produce una version inmutable.
- Se puede descargar exactamente el documento entregado.
- Una nueva emision no reemplaza ni borra versiones anteriores.

## 4.2 Prioridad alta

### D. Busqueda por paciente

La tabla ya muestra el nombre, pero la busqueda debe aceptar nombre y apellido ademas de codigo, documento y protocolo.

Criterio de aceptacion:

- Buscar una parte del nombre o apellido devuelve las ordenes del paciente sin afectar paginacion ni filtros.

### E. Bandejas por area clinica

Problema:

Todas las areas operan desde el modal central de ordenes.

Objetivo:

- Bandejas de pendientes, en proceso, observados y finalizados por area.
- Filtros por fecha, empresa y responsable.
- Apertura directa del formato correspondiente.

Criterios de aceptacion:

- Cada area identifica su cola de trabajo sin recorrer todas las ordenes.
- Los estados se sincronizan con la orden central.

### F. Formatos clinicos especializados faltantes

Problema:

Los examenes no reconocidos usan `general_basico`, lo cual no sustituye formularios clinicos completos.

Accion:

Inventariar el catalogo activo y priorizar, segun uso real:

- Espirometria.
- Radiografia.
- Odontologia.
- Evaluacion osteomuscular.
- Neurologia.
- Ecografia u otros examenes del catalogo local.

Criterios de aceptacion:

- Cada examen prioritario tiene campos, validaciones, conclusion y PDF propios.
- El formato general queda solo como fallback controlado.

### G. Correccion y reapertura controlada

Problema:

No existe un flujo formal para corregir una orden cerrada o sustituir un documento emitido.

Objetivo:

- Reapertura con permiso especial, motivo obligatorio y evento de auditoria.
- Invalidacion logica de documentos anteriores, nunca eliminacion fisica.
- Nueva version al volver a cerrar y emitir.

Criterios de aceptacion:

- Toda correccion post cierre es trazable.
- Se conoce que version es vigente y cuales fueron sustituidas.

## 4.3 Prioridad administrativa

### H. Caja, cobro y facturacion

Estado actual:

`facturar_empresa_id` identifica a quien facturar, pero no existe integracion ocupacional completa con caja, cobro, credito empresarial o comprobante.

Decisiones de negocio necesarias:

- Determinar si la orden puede ejecutarse antes del pago.
- Diferenciar contado, credito empresa, convenio y cortesia.
- Definir si una orden genera cotizacion, cuenta por cobrar o comprobante directo.

Criterios de aceptacion:

- Cada orden tiene estado financiero visible.
- Se puede rastrear cobro o cuenta empresarial hasta la orden.
- No se duplica el monto en caja ni facturacion.

Esta brecha no bloquea la emision clinica actual si la clinica administra el cobro por fuera del modulo, pero si impide considerar integrado el circuito administrativo.

### I. Entrega a paciente o empresa

Estado actual:

Las empresas conservan contactos y correos, pero no existe registro formal de entrega documental.

Objetivo:

- Registrar destinatario, medio, fecha, usuario y documentos entregados.
- Soportar entrega fisica, descarga, correo u otro canal aprobado.
- Conservar acuse o evidencia cuando corresponda.

Criterios de aceptacion:

- Se puede responder quien recibio cada certificado y cuando.
- La entrega referencia una version documental concreta.

### J. Reportes gerenciales

Ampliar reportes con:

- Ordenes y trabajadores por empresa.
- Pendientes por area.
- Tiempo de atencion por etapa.
- Observados e interconsultas.
- Distribucion de aptitudes.
- Produccion por profesional.
- Estado de entrega.
- Estado financiero.

## 4.4 Prioridad de calidad y despliegue

### K. Pruebas automatizadas de extremo a extremo

Cobertura minima:

1. Crear trabajador y orden.
2. Resolver protocolo y examenes.
3. Guardar borrador y finalizar resultados.
4. Observar un examen.
5. Crear, responder y levantar interconsulta.
6. Verificar bloqueo antes del levantamiento.
7. Guardar aptitud y cerrar.
8. Emitir y versionar certificado.
9. Verificar permisos y auditoria.
10. Verificar escritorio y movil.

### L. Ruta oficial de migraciones

Problema confirmado:

`migraciones/README_SALUD_OCUPACIONAL.md` no enumera todas las migraciones recientes que exige el codigo actual, entre ellas integridad de catalogo, bloqueo clinico, snapshots medicos, interconsultas y logo ocupacional.

Objetivo:

- Actualizar el README con la secuencia completa.
- Agregar verificacion post migracion.
- Evitar creacion de tablas en runtime cuando deba existir migracion explicita.

Migraciones recientes relevantes:

- `migraciones/20260725_0018_ocupacional_catalogo_empresa_integridad.sql`
- `migraciones/20260725_0019_ocupacional_ordenes_bloqueo_clinico.sql`
- `migraciones/20260725_0020_ocupacional_medico_responsable_snapshot.sql`
- `migraciones/20260725_0021_ocupacional_interconsultas_levantamientos.sql`
- `migraciones/20260725_0022_configuracion_logo_ocupacional.sql`

## 5. Riesgos concretos conocidos

1. Permisos frontend y backend pueden divergir si no se define una matriz unica.
2. Un evento de emision no sustituye al almacenamiento del documento legal emitido.
3. Regenerar un PDF historico puede introducir logo, firma o configuracion actual en lugar de la original.
4. El consolidado actual puede dar sensacion de integridad sin mostrar el contenido de cada resultado.
5. El formato general puede ocultar que un examen requiere estructura y validaciones especializadas.
6. Una instalacion nueva puede quedar incompleta si se sigue el README de migraciones actual.
7. Sin pruebas de extremo a extremo, cambios en cierre, observaciones o permisos pueden reabrir bypasses clinicos.
8. Sin entrega auditada, no se puede demostrar formalmente que la empresa o el paciente recibio el documento.

## 6. Plan recomendado de implementacion

### Fase A: cierre clinico y de acceso

Alcance:

- Busqueda por nombre de paciente.
- Matriz de permisos por rol y accion.
- Consolidado con resultados clinicos completos.

Salida esperada:

- Operacion clinica segura y revisable antes de certificar.

### Fase B: trazabilidad documental

Alcance:

- Repositorio de documentos.
- Versiones, hash y reimpresion historica.
- Reapertura y sustitucion controlada.

Salida esperada:

- Evidencia legal y documental reproducible.

### Fase C: operacion por areas

Alcance:

- Bandejas clinicas.
- Asignacion de responsables.
- Formatos especializados prioritarios.

Salida esperada:

- Escalabilidad operativa sin depender de una unica pantalla.

### Fase D: administracion y entrega

Alcance:

- Caja, cobro, credito y facturacion.
- Entrega a paciente o empresa.
- Reportes gerenciales.

Salida esperada:

- Circuito clinico, financiero y documental unido.

### Fase E: go-live reproducible

Alcance:

- Migraciones oficiales completas.
- Pruebas automatizadas.
- Smoke post despliegue.
- Manual operativo por perfil.

Salida esperada:

- Instalacion y validacion repetibles en produccion.

## 7. Orden de prioridad recomendado

1. Busqueda por nombre y matriz de permisos.
2. Consolidado clinico con resultados completos.
3. Persistencia y versionado de documentos.
4. Reapertura controlada.
5. Bandejas por area.
6. Formatos especializados restantes.
7. Integracion financiera.
8. Entrega documentaria.
9. Reportes, pruebas y runbook de produccion.

## 8. Validaciones realizadas al corte

- `php -l api_ocupacional_ordenes.php`: correcto.
- `npm run build:sistema`: correcto.
- Diagnosticos del editor en archivos modificados: sin errores.
- Emision de certificado validada en navegador.
- Guardado de aptitud cierra la orden y habilita certificado.
- Auditoria de eventos de aptitud, cierre y certificado confirmada.
- Nombre del paciente visible en tabla de ordenes.
- Cabecera de paciente visible en Historia ocupacional.
- Formularios de historia sin JSON visible.
- Vista movil comprobada sin desbordamiento incoherente en los bloques modificados.

## 9. Restricciones y decisiones que deben preservarse

- No duplicar identidad editable del paciente en la historia ocupacional.
- El backend debe seguir siendo la autoridad de cierre y permisos.
- Un resultado observado no cuenta como completado.
- Una interconsulta abierta bloquea el cierre.
- La aptitud debe conservar snapshots del medico.
- Los documentos historicos no deben regenerarse como si fueran el original una vez implementado el repositorio.
- Mantener paridad funcional y visual entre escritorio y movil.
- Las nuevas migraciones deben ubicarse en `migraciones/`, ser idempotentes y no fijar una base mediante `USE`.
- No alterar trabajos de contratos, farmacia o historia clinica general fuera del alcance ocupacional.

## 10. Prompt de continuidad para otra sesion

Usar este texto como punto de partida:

> Continua el cierre del modulo de Salud Ocupacional del repositorio `2demayo-ocupacional`. Lee primero `docs/salud-ocupacional/ANALISIS_CONTINUIDAD_FLUJO_COMPLETO_20260725.md`. El flujo clinico y la emision de certificados ya funcionan. No reconstruyas lo ya implementado. Verifica el estado actual del codigo y comienza por la primera brecha pendiente de la Fase A: busqueda por nombre, permisos por rol o consolidado con resultados completos, segun lo que siga sin implementar. Conserva las puertas clinicas backend, snapshots medicos, auditoria y paridad movil/escritorio. Toda migracion debe ir en `migraciones/` y aplicarse en desarrollo. Ejecuta validacion focalizada despues de cada primer cambio.

## 11. Definicion de flujo completo terminado

El modulo puede considerarse integralmente terminado cuando:

1. Cada perfil opera solo su area y acciones autorizadas.
2. La orden se puede localizar por codigo, DNI y nombre del paciente.
3. Todos los examenes prioritarios tienen captura clinica adecuada.
4. El medico revisa un consolidado con resultados completos.
5. Aptitud y cierre conservan todas las puertas clinicas.
6. Cada documento emitido queda almacenado y versionado.
7. Las correcciones posteriores al cierre son controladas y auditadas.
8. El estado financiero esta vinculado a la orden.
9. La entrega al paciente o empresa queda registrada.
10. El flujo completo tiene pruebas automatizadas y una ruta de despliegue reproducible.
