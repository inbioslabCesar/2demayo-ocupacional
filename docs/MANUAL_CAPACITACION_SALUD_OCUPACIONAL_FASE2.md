# Manual de Capacitacion - Salud Ocupacional (Fase 1 + Fase 2)

Fecha: 2026-06-15
Version: 2.0
Dirigido a: Personal administrativo, recepcion, coordinacion ocupacional y jefaturas

---

## 1. Objetivo del manual

Este manual explica, de forma operativa y paso a paso, todo el flujo implementado del modulo Salud Ocupacional desde Fase 1 hasta Fase 2, para capacitar al personal que usara el sistema en produccion.

Con este material el equipo aprendera a:

1. Configurar la base operativa de empresas y trabajadores (Fase 1).
2. Configurar examenes, catalogo por empresa y protocolos (Fase 2).
3. Definir montos y condiciones ocupacionales.
4. Ejecutar el ciclo completo: alta de trabajador y baja laboral.

---

## 2. Alcance por fases

## Fase 1 (base operativa)

Alcance implementado:

1. Empresas ocupacionales (registro, listado, inactivacion logica).
2. Trabajadores ocupacionales (verificacion de identidad, registro y baja logica).
3. Listados con filtros, busqueda, paginacion y ordenamiento server-side.
4. Permisos de acceso y rutas protegidas por rol/permisos.
5. Auditoria minima (`created_by`, `updated_by`).

Resultado operativo de Fase 1:

- El sistema puede administrar empresas y trabajadores con control de estado (`activo`/`retirado`) y trazabilidad basica.

## Fase 2 (expansion funcional)

Alcance implementado:

1. Examenes generales ocupacionales (alta, edicion, inactivacion, listado).
2. Catalogo de examenes por empresa (toggle ON/OFF).
3. Protocolos por empresa y tipo de evaluacion (PRE, PER, POST).
4. Matriz de montos por examen con totalizadores.
5. Condiciones por examen en protocolo (crear, editar, eliminar).

Resultado operativo de Fase 2:

- El sistema permite parametrizar la oferta ocupacional por empresa y controlar reglas/montos para ejecucion operativa.

---

## 3. Requisitos previos para operar

Antes de iniciar capacitacion u operacion diaria:

1. Usuario con permisos del modulo Salud Ocupacional.
2. Sesion iniciada correctamente.
3. Navegador en el sistema nuevo.
4. Datos minimos:
   - Al menos una empresa ocupacional.
   - Al menos un examen general.

---

## 4. Rutas del modulo

Menu lateral -> Salud Ocupacional ->

1. Empresas
2. Trabajadores
3. Examenes Generales
4. Catalogo de Examenes
5. Protocolos

Orden recomendado de trabajo (Fase 1 + Fase 2):

1. Empresas
2. Examenes Generales
3. Catalogo de Examenes
4. Protocolos y Matriz
5. Condiciones
6. Trabajadores

---

## 5. Flujo completo de capacitacion (punta a punta)

## Paso 1 (Fase 1): Registrar empresa ocupacional

Pantalla: Salud Ocupacional -> Empresas

Procedimiento:

1. Registrar RUC, razon social y campos obligatorios.
2. Guardar.
3. Confirmar mensaje de exito.
4. Validar presencia en listado.

Buenas practicas:

- Normalizar razon social en mayusculas.
- Evitar duplicados de RUC.

## Paso 2 (Fase 2): Registrar examen general

Pantalla: Salud Ocupacional -> Examenes Generales

Procedimiento:

1. Crear codigo interno (ejemplo: EXA001).
2. Ingresar nombre y descripcion.
3. Guardar.
4. Validar que quede en estado activo.

Buenas practicas:

- Definir nomenclatura estable para codigos.
- Evitar examenes equivalentes con nombres distintos.

## Paso 3 (Fase 2): Activar examen en catalogo por empresa

Pantalla: Salud Ocupacional -> Catalogo de Examenes

Procedimiento:

1. Seleccionar empresa.
2. Buscar examen.
3. Cambiar OFF -> ON.
4. Confirmar estado activo.

Interpretacion:

- ON: examen habilitado para configuracion y uso en protocolos.
- OFF: examen no habilitado para esa empresa.

## Paso 4 (Fase 2): Crear protocolo ocupacional

Pantalla: Salud Ocupacional -> Protocolos

Procedimiento:

1. Seleccionar empresa.
2. Elegir tipo PRE/PER/POST.
3. Escribir nombre del protocolo.
4. Guardar.
5. Seleccionar protocolo para editar matriz.

## Paso 5 (Fase 2): Configurar montos en matriz

Pantalla: Protocolos -> Matriz PRE/PER/POST

Procedimiento:

1. Ubicar examen en tabla.
2. Ingresar monto en PRE, PER y/o POST.
3. Confirmar guardado.
4. Revisar fila TOTAL.

Control de calidad:

- Todo cambio de monto debe reflejarse en totalizadores.

## Paso 6 (Fase 2): Configurar condiciones por examen

Pantalla: Protocolos -> Panel de Condiciones

Procedimiento:

1. Seleccionar examen.
2. Registrar condicion (puesto, sexo, edad minima, edad maxima).
3. Guardar.
4. Editar si aplica.
5. Eliminar si ya no aplica.

Ejemplo:

- Puesto: Operario QA
- Sexo: Masculino
- Edad: 18 a 65

## Paso 7 (Fase 1): Verificar identidad del trabajador

Pantalla: Salud Ocupacional -> Trabajadores

Procedimiento:

1. Elegir tipo de documento (DNI, Pasaporte o CE).
2. Ingresar numero de documento.
3. Click en Verificar.
4. Validar autocompletado de identidad.

Regla:

- Si no hay verificacion, no continuar con registro ocupacional.

## Paso 8 (Fase 1): Registrar trabajador ocupacional

Pantalla: Salud Ocupacional -> Trabajadores

Procedimiento:

1. Seleccionar empresa.
2. Ingresar puesto.
3. Seleccionar area de riesgo.
4. Seleccionar tipo de contrato.
5. Definir fecha de ingreso.
6. Guardar.
7. Confirmar mensaje exitoso.
8. Verificar en listado como `activo`.

## Paso 9 (Fase 1): Dar de baja trabajador

Pantalla: Salud Ocupacional -> Trabajadores (listado)

Procedimiento:

1. Ubicar trabajador en estado `activo`.
2. Click en Dar baja.
3. Confirmar dialogo.
4. Verificar cambio a `retirado`.
5. Verificar que acciones queden segun regla vigente.

---

## 6. Checklist de capacitacion (Fase 1 + Fase 2)

Validacion completa:

1. Empresa registrada.
2. Empresa visible en listado.
3. Examen general registrado.
4. Examen activo en catalogo para la empresa.
5. Protocolo creado.
6. Monto guardado en matriz.
7. Totalizador correcto.
8. Condicion creada.
9. Condicion editada.
10. Condicion eliminada.
11. Identidad de trabajador verificada.
12. Trabajador registrado como activo.
13. Trabajador dado de baja (retirado).

Si los 13 puntos estan conformes, la capacitacion del flujo completo queda aprobada.

---

## 7. Errores comunes y acciones de soporte

1. No permite registrar trabajador.
- Verificar identidad primero.
- Completar empresa, puesto y fecha de ingreso.

2. Examen no aparece en protocolos.
- Revisar catalogo: debe estar ON para esa empresa.

3. Montos no actualizan total.
- Confirmar guardado del campo.
- Recargar modulo si hay latencia visual.

4. Botones deshabilitados.
- Revisar permisos del usuario.
- Validar secuencia (primero verificacion de identidad).

5. No encuentro registros.
- Revisar filtros de empresa, estado, busqueda y ordenamiento.

---

## 8. Plan sugerido de entrenamiento

1. Sesion 1 (45 min): Fase 1
- Empresas.
- Trabajadores (verificar, registrar, baja).

2. Sesion 2 (45 min): Fase 2
- Examenes.
- Catalogo por empresa.
- Protocolos, matriz y condiciones.

3. Sesion 3 (30 min): simulacion integral
- Caso completo de punta a punta.
- Revision del checklist de 13 puntos.

---

## 9. Guion practico para evaluacion final

Caso demo sugerido:

1. Crear empresa `EMPRESA DEMO CAPACITACION SAC`.
2. Crear examen `EXA_CAP_001`.
3. Activar examen en catalogo de esa empresa.
4. Crear protocolo `PROTOCOLO CAP PRE`.
5. Asignar monto PRE de `120.50`.
6. Crear condicion (Operario, Masculino, 18-65).
7. Verificar DNI de prueba.
8. Registrar trabajador.
9. Confirmar estado `activo`.
10. Ejecutar baja y confirmar `retirado`.

---

## 10. Exportacion del manual

1. Para Word:
- Abrir este archivo `.md` y copiar/pegar en Word.

2. Para PDF:
- Usar el archivo HTML del mismo manual.
- Abrir en navegador.
- Presionar `Ctrl+P`.
- Seleccionar `Guardar como PDF`.

---

Fin del documento.
