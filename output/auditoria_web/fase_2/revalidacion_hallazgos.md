# Vigencia de los hallazgos de Fase 2 después de los cambios locales

**23 de noviembre de 2026.** De los 134 hallazgos WEB del corte original, **23 están corregidos en el árbol local, 24 están parcialmente corregidos y 87 persisten**. Quedan **111** con alguna condición abierta. Los 41 asuntos DOC se registran aparte. [Hallazgos originales](informe_cumplimiento.md)

## Estado por severidad original

| Severidad original | Corregidos | Parcialmente corregidos | Persisten | Total revisado |
|---|---:|---:|---:|---:|
| Crítica | 16 | 3 | 0 | 19 |
| Alta | 7 | 16 | 26 | 49 |
| Media | 0 | 4 | 50 | 54 |
| Baja | 0 | 1 | 11 | 12 |
| **Total WEB** | **23** | **24** | **87** | **134** |

«Corregido» acredita que el código local resuelve la condición descrita en la ficha original. «Parcialmente corregido» identifica un cambio que resuelve una parte y precisa el caso que continúa abierto. «Persiste» indica que la diferencia original sigue presente. La tabla conserva la severidad original para permitir la comparación. La recepción efectiva del correo, la aplicación de las migraciones y el funcionamiento del sistema desplegado requieren evidencia del entorno.

### Los tres hallazgos críticos parcialmente corregidos

- **WEB-EXA-010 · Entrega de la respuesta activa.** La entrega ya incorpora y califica el borrador que guarda el sistema actual. Cuando lo único disponible es un borrador anterior en caché, se recupera su contenido, pero falta la hora que usa el calificador. En ese caso una respuesta objetiva puede quedar sin puntuar. Se desconoce si quedan borradores de ese tipo en la instalación. [Evidencia de exámenes](revalidacion_academica.md)
- **WEB-GRU-002 · Consulta de notas después de la baja o del cierre.** El detalle del intento y el listado de notas ya exigen matrícula vigente y grupo abierto. Los avisos de corrección guardados siguen mostrando la nota numérica al estudiante, porque su consulta filtra solo por alumno. [Evidencia de grupos](revalidacion_gestion.md)
- **WEB-RNF-003 · Datos personales en la evaluación con IA.** Se filtran la respuesta, la referencia y el contexto antes de enviarlos. El filtro cubre identificadores conocidos y patrones concretos. Un dato personal escrito en prosa, como el domicilio particular del estudiante, todavía puede llegar al proveedor. El análisis establece qué permite el código. Un envío real no está acreditado. [Evidencia de privacidad](revalidacion_ia_y_rnf.md)

## Evidencia de los 134 hallazgos

Cada enlace abre las fichas correspondientes con el ID original, el estado actual y las líneas del código local que lo sustentan.

| Bloque | Corregidos | Parcialmente corregidos | Persisten | Revisados |
|---|---:|---:|---:|---:|
| [Acceso, suscripciones, grupos, banco y administración](revalidacion_gestion.md) | 9 | 7 | 21 | 37 |
| [Exámenes, evaluación, calificaciones y reportes](revalidacion_academica.md) | 12 | 12 | 25 | 49 |
| [Evaluación asistida por IA y requisitos no funcionales](revalidacion_ia_y_rnf.md) | 1 | 1 | 8 | 10 |
| [Manuales y catálogos](revalidacion_manuales.md) | 1 | 4 | 33 | 38 |

## Asuntos documentales y alcance

Las huellas SHA-256 de la lista maestra y los cuatro manuales coinciden con las originales. Entre los 41 asuntos DOC siguen registrados 39 que requieren decisión. DOC-035 queda condicionado a la redistribución de recursos y DOC-033 documenta la discrepancia de índice ya localizada. El expediente de correcciones mantiene pendientes, entre otras, las decisiones de habilitación del examen libre, redondeo de notas y condiciones de venta de créditos. [Registro DOC](manuales_transversales.md#registro-doc-y-efecto-sobre-el-dictamen) · [Decisiones pendientes del expediente de correcciones](../correcciones/registro_30.md#decisiones-que-requieren-aprobación)

La unidad de este recuento es el **hallazgo**. El expediente de correcciones siguió 30 **requerimientos** antes clasificados «No cumple». Allí constan 16 verificados, 4 condicionados, 6 parciales y 4 bloqueados. Un requerimiento puede estar vinculado a varios hallazgos. Por eso el porcentaje de cumplimiento de los 238 requerimientos de la matriz original necesitaría una nueva revisión por código. [Seguimiento de los 30 requerimientos](../correcciones/registro_30.md) · [Matriz del corte original](matriz_trazabilidad.md)

## Identificación del árbol revisado

Rama `feature/cumplimiento-requerimientos`, sobre el commit `e0a89af1d774769e1d00deb5423329f632199944`, con 72 archivos modificados y 34 nuevos fuera de `output/`. Las referencias de los cuatro anexos corresponden a esta copia con cambios. La revisión fue estática. El reporte JUnit anterior se conservó como antecedente del otro expediente y tuvo la huella consignada allí. En esta revisión no se ejecutaron la aplicación, pruebas, migraciones ni servidores. [Estado registrado](00_estado_inicial.md) · [Antecedente de pruebas](../correcciones/registro_30.md#pruebas-y-trazabilidad)

La huella conjunta de los 479 archivos del proyecto, versionados o nuevos sin ignorar y fuera de `output/`, fue `5400883c459861b121a236e1a11233963d3f7a3f9f120e1c79adc75257e5a2fc` al inicio del cotejo y al verificar los resultados. Para reproducirla, se ordenan por bytes las rutas relativas obtenidas con `git ls-files --cached --others --exclude-standard -z`, excluyendo `output/`. Se calcula SHA-256 sobre la concatenación, por archivo, de su ruta, un byte nulo, los 32 bytes de su SHA-256 y otro byte nulo.
