# Estado de Continuidad Salud Ocupacional

Fecha: 2026-07-02
Proyecto: 2demayo-ocupacional

Estado de continuidad actualizado:

- Rama de trabajo creada: `feature/salud-ocupacional-fase4`
- Base de arranque usada: `main`

## 1. Punto exacto donde quedó la implementación

Estado verificado en git:

- `main` apunta al commit `f7fa121` con mensaje `completando la fase 3`.
- `feature/salud-ocupacional-fase3` apunta al mismo commit `f7fa121`.
- `feature/salud-ocupacional-fase2` quedó un commit atrás en `e9f7d57` con mensaje `fase 2 implementada con exito`.

Conclusión:

- La base real para continuar ya no es `feature/salud-ocupacional-fase2`.
- La base real para continuar es `main`, porque `main` ya contiene el cierre técnico que se dejó en `feature/salud-ocupacional-fase3`.
- Mantener la rama `feature/salud-ocupacional-fase3` solo como referencia histórica de cierre de esa etapa.

## 2. Estado funcional actual de Fase 3

Fase 3 quedó implementada en desarrollo con estos componentes:

- Órdenes ocupacionales.
- Previsualización de exámenes aplicables por protocolo.
- Registro de orden.
- Ejecución por detalle de examen.
- Cambio de estado automático a `en_proceso` y `completada`.
- Cierre formal de orden.
- Anulación con motivo.
- Bitácora / auditoría por orden.
- Reporte global PDF / Excel.
- Aptitud final y certificado.

Archivos principales relacionados:

- `api_ocupacional_ordenes.php`
- `src/pages/ocupacional/OrdenesOcupacionalesPage.jsx`
- `sql/2026-06-15_ocupacional_fase3_ordenes.sql`
- `sql/2026-06-16_ocupacional_fase3_ejecucion_ordenes.sql`
- `sql/2026-06-16_ocupacional_fase3_cierre_auditoria.sql`
- `sql/2026-06-16_ocupacional_fase3_aptitud_certificado.sql`

## 3. Qué falta para dar Fase 3 por cerrada operativamente

Aunque Fase 3 quedó construida, todavía quedaron pendientes de cierre operativo:

- Cargar y validar permisos finos por rol en base de datos.
- Reflejar esos permisos finos también en el frontend administrativo/recepción.
- QA/UAT formal del flujo completo.
- Manual operativo específico de Fase 3.
- Validación productiva de certificados y bitácora.

Esto significa:

- Fase 3 está cerrada como base técnica.
- Fase 3 no está completamente cerrada como entrega operativa/productiva.

## 4. Recomendación de rama para continuar

Recomendación principal:

- Crear una nueva rama desde `main` para continuar la mejora.

Nombre recomendado:

- `feature/salud-ocupacional-fase4`

Alternativa si se quiere un nombre más descriptivo:

- `feature/salud-ocupacional-fase4-historia-clinica`

No recomendado:

- Continuar directamente en `feature/salud-ocupacional-fase3`, porque ya quedó equivalente a `main` y es mejor conservarla como hito histórico.

Regla práctica a partir de ahora:

- `main`: base estable actual.
- `feature/salud-ocupacional-fase3`: referencia histórica de cierre técnico de Fase 3.
- `feature/salud-ocupacional-fase4`: rama de trabajo para la siguiente implementación.

Estado actual:

- La rama `feature/salud-ocupacional-fase4` ya fue creada para continuar la implementación.

## 5. Alcance sugerido de Fase 4

Fase 4 debería enfocarse en contrastar y migrar el bloque clínico ocupacional que existe en `clinicaocupacional` y aún no existe en forma equivalente en `2demayo-ocupacional`.

Inicio sugerido de Fase 4:

- Historia ocupacional.
- Historia clínica ocupacional PDF / vista clínica.
- Formatos clínicos por examen ocupacional.
- Flujo complementario RRHH / evaluación / certificados si aplica al nuevo modelo.

Nota de implementación ya acordada:

- Cuando se construyan impresiones/PDF de formatos ocupacionales, deben seguir el estilo profesional que ya usa `2demayo-ocupacional` en resultados de laboratorio.
- Referencia principal: `descargar_resultados_laboratorio.php`.
- Esto implica separar hoja de solicitud de informe profesional final, usar encabezado institucional real, bloque clínico estructurado, firma profesional, colegiatura y presentación apta para archivo clínico.

## 6. Convención para nuevas tablas y scripts SQL

Regla acordada para siguientes mejoras:

- Toda nueva tabla o cambio SQL debe quedar dentro de la carpeta `sql`.
- Debe crearse una subcarpeta específica para la mejora o fase nueva.

Ejemplo recomendado para Fase 4:

- `sql/salud-ocupacional/fase4_historia_clinica/`

Estado actual:

- La carpeta base recomendada para scripts SQL de esta mejora queda reservada en `sql/salud-ocupacional/fase4_historia_clinica/`.

Ejemplos de nombres de archivos:

- `sql/salud-ocupacional/fase4_historia_clinica/2026-07-02_01_base_historia_ocupacional.sql`
- `sql/salud-ocupacional/fase4_historia_clinica/2026-07-02_02_relacion_orden_formato.sql`
- `sql/salud-ocupacional/fase4_historia_clinica/2026-07-02_03_datos_clinicos_iniciales.sql`

Convención de contenido:

- Un archivo por cambio lógico relevante.
- Encabezado con objetivo de la mejora.
- Si la mejora depende de scripts anteriores, numerarlos de forma secuencial.

## 7. Decisión recomendada para continuar

Decisión recomendada de trabajo:

1. Mantener `main` como base estable.
2. Crear `feature/salud-ocupacional-fase4` desde `main`.
3. Antes de tocar código, convertir este documento en checklist vivo de continuidad.
4. Toda tabla nueva de la mejora debe vivir bajo una subcarpeta específica dentro de `sql`.

## 8. Resumen corto

Si se pierde nuevamente el chat, recordar esto:

- La implementación quedó realmente en `main` = `feature/salud-ocupacional-fase3`.
- Fase 2 quedó atrás.
- La siguiente implementación debe salir desde una rama nueva de Fase 4.
- La siguiente capa pendiente es el bloque clínico ocupacional heredado de `clinicaocupacional`.
- Los nuevos scripts SQL deben guardarse dentro de `sql/` en una carpeta propia de la mejora.

## 9. Documento complementario para arrancar Fase 4

Antes de tocar código, usar como hoja de ruta técnica:

- `docs/salud-ocupacional/FASE4_CHECKLIST_TECNICO.md`

Ese documento contiene:

- brecha exacta contra `clinicaocupacional`
- orden recomendado de implementación
- checklist técnico por bloques
- convención de carpeta SQL para la mejora