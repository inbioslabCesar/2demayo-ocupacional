# Manual Checklist - Flujo Completo Salud Ocupacional

Fecha: 2026-07-24
Sistema: CLINICA 2 DE MAYO
Objetivo: Ejecutar el flujo completo desde registro de empresa hasta emision de certificado ocupacional.

## 1) Preparacion

- [ ] Iniciar sesion con usuario con permisos de Salud Ocupacional.
- [ ] Verificar que los catalogos carguen sin error.
- [ ] Confirmar que exista al menos un medico con firma configurada en CRUD Medicos.
- [ ] Confirmar logo y datos de clinica en Configuracion.

Criterio de salida:
- [ ] Se visualizan modulos: Empresas, Trabajadores, Catalogo, Protocolos, Ordenes.

## 2) Registro de Empresa

Ruta: Salud Ocupacional -> Empresas

- [ ] Click en Nueva empresa.
- [ ] Completar RUC (11 digitos).
- [ ] Completar razon social.
- [ ] Completar direccion y datos de contacto.
- [ ] Guardar.
- [ ] Verificar que aparezca en listado con estado activo.

Criterio de salida:
- [ ] Empresa creada y seleccionable en modulos siguientes.

## 3) Registro de Trabajador

Ruta: Salud Ocupacional -> Trabajadores

- [ ] Click en Nuevo trabajador.
- [ ] Seleccionar empresa creada.
- [ ] Ingresar documento (DNI u otro), puesto de trabajo y fecha ingreso.
- [ ] Verificar vinculacion con paciente clinico (si aplica).
- [ ] Guardar.
- [ ] Verificar estado laboral = activo.

Criterio de salida:
- [ ] Trabajador aparece en listado y es elegible para orden.

## 4) Catalogo de Examenes por Empresa

Ruta: Salud Ocupacional -> Catalogo por Empresa

- [ ] Seleccionar empresa.
- [ ] Agregar examenes requeridos al catalogo.
- [ ] Definir montos por examen.
- [ ] Verificar estado activo.

Criterio de salida:
- [ ] Examenes disponibles para crear protocolo.

## 5) Protocolo

Ruta: Salud Ocupacional -> Protocolos

- [ ] Crear nuevo protocolo para la empresa.
- [ ] Agregar detalle de examenes desde catalogo.
- [ ] Definir tipo de evaluacion (Pre, Periodico, Post).
- [ ] Configurar condiciones (puesto, sexo, edad) si corresponde.
- [ ] Guardar y activar protocolo.

Criterio de salida:
- [ ] Protocolo visible y seleccionable en Ordenes.

## 6) Generacion de Orden Ocupacional

Ruta: Salud Ocupacional -> Ordenes

- [ ] Seleccionar empresa.
- [ ] Seleccionar trabajador.
- [ ] Seleccionar protocolo.
- [ ] Seleccionar tipo de evaluacion.
- [ ] Seleccionar medico desde combo (fuente: CRUD Medicos).
- [ ] Click en Previsualizar examenes.
- [ ] Verificar items aplicables y total.
- [ ] Click en Registrar orden.

Criterio de salida:
- [ ] Orden generada con codigo OO y estado emitida.

## 7) Ejecucion de Examenes

Ruta: Ordenes -> boton Detalle

- [ ] Abrir detalle de la orden.
- [ ] Actualizar estado de cada examen a realizado (u observado).
- [ ] Guardar cada item.
- [ ] Verificar avance 100 por ciento.

Criterio de salida:
- [ ] Estado de orden cambia a completada.

## 8) Cierre de Orden

Ruta: Ordenes (grilla)

- [ ] Click en Cerrar para la orden completada.
- [ ] Confirmar dialogo de cierre formal.

Criterio de salida:
- [ ] Estado de orden = cerrada.

## 9) Aptitud Final

Ruta: Ordenes -> Detalle -> Aptitud final y certificado

- [ ] Seleccionar Aptitud final (APTO, APTO_CON_RESTRICCIONES, NO_APTO).
- [ ] Completar medico responsable.
- [ ] Completar restricciones y recomendaciones.
- [ ] Guardar aptitud.

Criterio de salida:
- [ ] Mensaje de aptitud guardada y datos persistidos.

## 10) Emision de Certificado

Ruta: Ordenes -> Detalle o grilla

- [ ] Click en Emitir certificado.
- [ ] Verificar que genera PDF de certificado (no hoja de ruta).
- [ ] Verificar contenido minimo:
  - [ ] Nombre del paciente.
  - [ ] HC.
  - [ ] Documento.
  - [ ] Empresa, puesto, protocolo, tipo evaluacion.
  - [ ] Aptitud final, restricciones, recomendaciones.
  - [ ] Firma medico.
  - [ ] Nombre medico, especialidad, CMP y RNE.
- [ ] Verificar badge de emision en grilla: "Emit.".
- [ ] Verificar evento certificado_emitido en bitacora.

Criterio de salida:
- [ ] Certificado emitido y auditado.

## 11) Impresion y Archivo

- [ ] Guardar PDF con nombre estandar: certificado_aptitud_OOxxxxxx.pdf
- [ ] Imprimir copia fisica (si aplica).
- [ ] Adjuntar PDF a expediente digital del trabajador.

## Checklist Rapido de Auditoria

- [ ] Orden en estado cerrada.
- [ ] Aptitud final registrada.
- [ ] Certificado emitido.
- [ ] Firma del medico visible.
- [ ] Bitacora con certificado_emitido.
- [ ] PDF legible y completo.

## Problemas comunes y solucion corta

1. Boton Certificado deshabilitado.
- Causa: Orden no cerrada o aptitud faltante.
- Solucion: Completar ejecucion, cerrar orden y guardar aptitud.

2. Sale Hoja de ruta en vez de certificado.
- Causa: Se uso boton Hoja ruta.
- Solucion: Usar boton Certificado.

3. No aparece firma.
- Causa: Medico sin firma en CRUD o resolucion de medico incorrecta.
- Solucion: Verificar firma en CRUD Medicos y reemitir.

4. No sale nombre paciente o HC.
- Causa: Falta de vinculacion a paciente clinico.
- Solucion: Revisar external_patient_id del trabajador y datos clinicos.

---

Responsable de verificacion:
Fecha de ejecucion:
Observaciones:
