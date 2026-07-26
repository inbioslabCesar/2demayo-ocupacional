# Mapa Funcional Objetivo

## Objetivo
Definir un flujo operativo para que el sistema moderno conserve su interfaz actual, pero funcione con la logica de configuracion maestra por empresa del sistema antiguo (areas, puestos y protocolos).

## Problema actual observado
1. En Empresas existen acciones llamadas Area y Puesto, pero redirigen a Trabajadores.
2. En Trabajadores, el puesto se registra como texto manual por trabajador.
3. Esto mezcla dos niveles distintos:
- Nivel maestro (catalogos por empresa).
- Nivel operativo (registro individual del trabajador).

## Criterio de diseno recomendado
Separar claramente configuracion maestra de operacion diaria.

Regla:
1. Primero se configura la empresa (catalogos).
2. Luego se registran trabajadores usando esos catalogos.
3. Finalmente se ejecutan protocolos y ordenes.

## Flujo objetivo (to-be)

### Paso 1: Empresas (hub maestro)
Desde la grilla de empresas, las acciones deben comportarse asi:
1. Area: abre gestion de areas de esa empresa.
2. Puesto: abre gestion de puestos de esa empresa.
3. Protocolo: abre gestion de protocolos de esa empresa.
4. Editar/Inactivar: mantenimiento de empresa.

Resultado esperado:
- El usuario entiende que todo lo estructural de la empresa se prepara aqui.

### Paso 2: Gestion de Areas por empresa
Pantalla/modal de Areas por empresa con:
1. Crear area.
2. Editar area.
3. Inactivar o eliminar area (segun politica de datos).
4. Listado paginado y buscador.

Reglas:
1. No duplicados por empresa.
2. Nombre normalizado (sin espacios dobles y comparacion case-insensitive).

### Paso 3: Gestion de Puestos por empresa
Pantalla/modal de Puestos por empresa con:
1. Crear puesto.
2. Editar puesto.
3. Inactivar o eliminar puesto.
4. Listado paginado y buscador.

Reglas:
1. No duplicados por empresa.
2. Si un puesto ya esta usado por trabajadores, no eliminar fisico.
3. En caso de uso, permitir solo inactivar.

### Paso 4: Gestion de Protocolos por empresa
Pantalla de Protocolos filtrada por empresa:
1. Crear protocolo.
2. Configurar matriz por tipo de evaluacion.
3. Configurar condiciones por puesto/sexo/edad.
4. Inactivar protocolo.

Reglas:
1. Solo protocolos activos para ordenes nuevas.
2. Trazabilidad de cambios de monto y condiciones.

### Paso 5: Registro de Trabajadores
En Trabajadores, el formulario debe consumir catalogos de la empresa:
1. Empresa: selector obligatorio.
2. Area: selector desde catalogo de la empresa.
3. Puesto: selector desde catalogo de la empresa.
4. Permitir modo excepcional de texto libre solo bajo bandera controlada.

Reglas:
1. Por defecto no texto libre para puesto.
2. Si se usa texto libre excepcional, registrar bandera de auditoria.

### Paso 6: Ordenes Ocupacionales
Al crear orden:
1. Seleccionar empresa.
2. Seleccionar trabajador.
3. Mostrar puesto/area estandarizados.
4. Seleccionar protocolo y tipo de evaluacion.
5. Previsualizar y registrar.

Resultado:
- Menos ambiguedad en filtros, reportes y condiciones por puesto.

## Comparacion de eficiencia (resumen)

### Sistema antiguo (modelo maestro por empresa)
Ventajas:
1. Alta coherencia de datos.
2. Menos variaciones de texto en puesto/area.
3. Mejor base para protocolos condicionados.

Desventajas:
1. Requiere configuracion inicial antes de registrar trabajadores.

### Sistema moderno actual (captura manual en trabajador)
Ventajas:
1. Registro rapido para casos individuales.
2. Menor friccion inicial en ambientes pequenos.

Desventajas:
1. Mayor dispersion de nombres de puesto.
2. Menor intuicion en accion Area/Puesto desde Empresas.
3. Coste operativo acumulado por correcciones y limpieza de datos.

## Recomendacion final
Para una operacion ocupacional estable y escalable:
1. Adoptar flujo maestro por empresa como comportamiento principal.
2. Mantener la UI moderna, pero con navegacion y semantica equivalentes al antiguo.
3. Dejar captura manual de puesto solo como excepcion controlada, no como camino por defecto.

## KPI para validar que el flujo mejoro
1. Porcentaje de trabajadores con puesto desde catalogo maestro.
2. Cantidad de variantes textuales del mismo puesto por empresa.
3. Tiempo promedio para registrar trabajador.
4. Tasa de correcciones posteriores en puesto/area.
5. Incidencias de protocolos que no aplican por inconsistencia de puesto.

## Plan de adopcion funcional por fases
1. Fase A: Redefinir semantica de acciones Area/Puesto en Empresas.
2. Fase B: Crear mantenimiento maestro de Areas y Puestos por empresa.
3. Fase C: Conectar FormTrabajador a catalogos maestros.
4. Fase D: Dejar modo texto libre solo para casos excepcionales auditables.
5. Fase E: Medir KPI y ajustar.

## Conclusiones operativas
1. El modelo mas intuitivo para usuario administrativo es el maestro por empresa.
2. El modelo mas eficiente a mediano plazo tambien es el maestro por empresa.
3. El modelo moderno actual puede conservarse visualmente, pero debe ajustar la logica de navegacion y captura para alinear semantica y operacion.
