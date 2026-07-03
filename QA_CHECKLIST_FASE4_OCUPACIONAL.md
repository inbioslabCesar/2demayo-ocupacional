# QA Checklist Fase 4 - Salud Ocupacional

Objetivo: validar en menos de 10 minutos el flujo operativo de historia clinica ocupacional consolidada y formatos por examen con plantillas.

Ambiente recomendado:
- URL: staging o entorno local estable
- Perfil: administrador o usuario con permisos de salud ocupacional
- Datos: empresa/trabajador/protocolo con al menos 1 examen aplicable

## Caso 1 - Registrar orden y abrir detalle
Tiempo estimado: 2 min

Pasos:
1. Ir a Salud Ocupacional > Ordenes.
2. Seleccionar empresa, trabajador, protocolo y tipo.
3. Clic en Previsualizar examenes.
4. Clic en Registrar orden.
5. En la tabla, abrir Detalle de la orden recien creada.

Resultado esperado:
- Se muestra mensaje de orden registrada.
- La orden aparece en tabla con estado emitida.
- El modal de ejecucion abre y muestra items del detalle.

## Caso 2 - Formato clinico con plantilla sugerida
Tiempo estimado: 2 min

Pasos:
1. En el detalle, clic en Formato del examen.
2. Verificar carga inicial del JSON.
3. Clic en Cargar plantilla sugerida.
4. Confirmar que el JSON se carga/reemplaza sin error.

Resultado esperado:
- Se abre modal Formato clinico por examen.
- El JSON no queda vacio en primera carga.
- No aparece error de runtime ni error de API.

## Caso 3 - Guardar plantilla de catalogo y aplicar
Tiempo estimado: 2 min

Pasos:
1. En el modal de formato, escribir Nombre de plantilla (ej: qa_rt_plantilla_1).
2. Clic en Guardar como plantilla.
3. Verificar que la nueva plantilla aparece en el selector.
4. Seleccionar la plantilla guardada.
5. Clic en Aplicar seleccionada.

Resultado esperado:
- Mensaje de confirmacion de plantilla guardada.
- Selector muestra la plantilla nueva.
- Aplicar seleccionada reemplaza el JSON sin errores.

## Caso 4 - Guardar formato finalizado y sincronizacion de estado
Tiempo estimado: 2 min

Pasos:
1. En modal de formato, elegir estado finalizado.
2. Clic en Guardar formato.
3. Volver al detalle de la orden.
4. Confirmar estado del item y avance de la orden.

Resultado esperado:
- El detalle pasa a estado realizado.
- El avance cambia (ejemplo 1/1 si es un solo examen).
- El estado de orden se sincroniza (en_proceso o completada segun corresponda).
- Consolidado clinico refleja los nuevos contadores.

## Caso 5 - Eliminar plantilla de catalogo
Tiempo estimado: 2 min

Pasos:
1. En modal de formato, seleccionar una plantilla de catalogo (id > 0).
2. Clic en Eliminar plantilla seleccionada.
3. Confirmar en el dialogo.
4. Revisar nuevamente el selector.

Resultado esperado:
- Mensaje de plantilla eliminada.
- La plantilla eliminada ya no aparece.
- Permanece la Plantilla sugerida del sistema.

## Criterios de aprobacion
- No hay errores de consola bloqueantes.
- No hay errores 4xx/5xx en llamadas del flujo validado.
- Todas las acciones CRUD de plantilla (crear/aplicar/eliminar) funcionan.
- Guardar formato actualiza estado de detalle y orden correctamente.

## Evidencias minimas sugeridas
- Captura de orden emitida recien creada.
- Captura del modal de formato con plantilla aplicada.
- Captura del mensaje de plantilla guardada.
- Captura del detalle con item realizado y avance actualizado.
- Captura del selector tras eliminar plantilla.

## Incidencia conocida ya corregida
- Guardar como plantilla antes usaba prompt y podia fallar en algunos entornos de automatizacion.
- Ahora se usa campo inline Nombre de plantilla en el modal.
