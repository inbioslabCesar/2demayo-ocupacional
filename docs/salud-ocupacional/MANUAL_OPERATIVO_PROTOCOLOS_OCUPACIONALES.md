# Manual Operativo Corto: Protocolos Ocupacionales

## Objetivo
Este manual explica como usar Protocolos de Salud Ocupacional en el orden correcto para evitar errores de configuracion y cobros inesperados.

## Idea clave en 30 segundos
Un protocolo define que examenes se cobran y con que monto para cada tipo de evaluacion (por ejemplo PRE, PER, POST), y ademas puede limitar examenes por condiciones (puesto, sexo, edad).

## Flujo recomendado (orden de trabajo)
1. Confirmar Tipos de Evaluacion activos.
2. Seleccionar empresa.
3. Crear o elegir protocolo.
4. Ajustar matriz de montos por tipo de evaluacion.
5. Configurar condiciones por examen (si aplica).
6. Previsualizar antes de usar en Ordenes.

## Paso 1: Tipos de Evaluacion
- Ir a Salud Ocupacional > Tipos Evaluacion.
- Verificar que esten activos los tipos que la operacion necesita.
- Entender impacto: cada tipo activo aparece como columna en Protocolos.
- Recomendacion: no inactivar tipos en uso sin revisar antes con el equipo.

## Paso 2: Empresa y Protocolo
- Ir a Salud Ocupacional > Protocolos.
- Elegir empresa.
- Elegir protocolo existente o crear uno nuevo.
- Si se crea nuevo, mantener activada la opcion de crear con precios base para iniciar mas rapido.

## Paso 3: Matriz de montos (la parte principal)
En cada celda (Examen x Tipo):
- Si escribes un monto: personalizas ese examen para ese tipo.
- Si dejas vacio y sales del campo: el examen queda excluido para ese tipo.
- Si usas Restablecer: vuelve al precio base del catalogo general.

Regla practica:
- Personaliza solo donde de verdad cambia el precio.
- Usa Restablecer para limpiar ajustes temporales.

## Paso 4: Condiciones por examen
Las condiciones definen cuando un examen aplica a un trabajador.

Criterios disponibles:
- Puesto de trabajo
- Sexo
- Rango de edad

Comportamiento:
- Sin condiciones: el examen aplica siempre.
- Con condiciones: debe cumplir al menos una condicion registrada.

Sugerencia operativa:
- Empezar con reglas simples.
- Evitar cargar demasiadas condiciones al inicio.

## Paso 5: Herramientas para trabajar mas rapido
### Copiar configuracion entre protocolos
- Sirve para clonar montos y/o condiciones desde un protocolo origen.
- Usar Previsualizar primero.
- Aplicar solo cuando el resumen sea el esperado.

### Plantillas referenciales
- Cargan reglas base editables.
- Ajustar filtro y criterios antes de aplicar.

### Automatizacion masiva
- Aplica una condicion a muchos examenes por texto de filtro.
- Requiere filtro de examen y al menos un criterio (puesto, sexo o edad).
- Siempre previsualizar antes de aplicar en produccion.

## Escenarios reales (como usarlo en el dia a dia)
### Escenario A: Ingreso de personal nuevo
1. Seleccionar empresa.
2. Elegir protocolo de ingreso (o crear uno nuevo).
3. Revisar columna de tipo correspondiente (por ejemplo PRE).
4. Confirmar examenes obligatorios y montos.
5. Previsualizar orden con un trabajador de prueba.

### Escenario B: Evaluacion periodica
1. Clonar desde protocolo base para no empezar de cero.
2. Ajustar solo diferencias en la columna periodica.
3. Revisar condiciones por puesto expuesto.
4. Previsualizar y validar total esperado.

### Escenario C: Cese o retiro
1. Seleccionar protocolo de salida.
2. Verificar examenes que no deben aplicar y excluirlos dejando celda vacia.
3. Restablecer celdas que deban volver al precio base.
4. Previsualizar antes de registrar orden final.

## Checklist rapido antes de cerrar cambios
- Empresa correcta seleccionada.
- Protocolo correcto seleccionado.
- Tipos activos correctos.
- No quedaron exclusiones accidentales.
- Previsualizacion de orden revisada con total coherente.

## Errores frecuentes y como evitarlos
- Error: editar en empresa equivocada.
  Prevencion: verificar encabezado de empresa y protocolo antes de tocar montos.

- Error: excluir examen sin querer (celda vacia).
  Prevencion: usar Restablecer cuando el objetivo era volver al precio base.

- Error: aplicar masivo sin revisar alcance.
  Prevencion: ejecutar previsualizacion y leer resumen de coincidencias.

- Error: confusion por columnas PRE/PER/POST.
  Prevencion: recordar que las columnas vienen de Tipos Evaluacion activos.

## Regla de oro operativa
Primero previsualizar, luego aplicar.

Si una configuracion impacta muchos examenes, nunca aplicar en directo sin previsualizacion previa.
