# Traspaso de la auditoría de cumplimiento web · Fase 2

## 1. Objetivo y alcance

Auditar la parte web de **acs-exam-web** para determinar, con evidencia verificable, en qué medida cumple cada requerimiento aplicable y cada regla aplicable de los cuatro manuales. La auditoría de calidad es de solo lectura. Conservar intactos el código, los requerimientos y los manuales. Guardar los archivos de análisis únicamente en `output/`.

**Repositorio** `/home/laambws/Escritorio/acs-exam-web`. **Rama registrada** `feature/cumplimiento-requerimientos`. **Commit al traspaso** `e0a89af1d774769e1d00deb5423329f632199944`. El corte documental corresponde al **23 de noviembre de 2026**. Al retomar, anotar rama, commit y cambios locales para que las citas de archivo y línea se puedan reproducir.

El alcance comprende el navegador de docente, alumno y superadministrador, incluido el navegador móvil donde se exige, y el código de servidor, las tareas y la persistencia que sostienen esas operaciones. El cliente nativo queda fuera. RF-GRU-008, RF-EXA-056, RNF-TEC-002 y RNF-SEG-011 se conservan en el inventario por trazabilidad. RF-EXA-053 sí exige navegador móvil. Para el envío de notificaciones desde el servidor, consultar DOC-010 antes de asignar alcance a RF-EXA-054/057.

## 2. Prompt original completo

Copia literal del primer mensaje del usuario. El bloque conserva sus saltos de línea, escapes, espacios y enlaces.

````text
\# Contexto\
Estoy en la rama de cumplimiento de requerimientos. El objetivo es auditar la\
parte WEB de mi sistema (ignora cualquier otro componente) para verificar que\
cumple al 100% con mis requerimientos y con mis manuales, de cara a una auditoría\
de calidad.\
\
\# Fuentes de verdad (SOLO LECTURA)\
\- Lista_maestra_requerimientos.xlsx (versión actual) [Lista_maestra_requerimientos.xlsx](/home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx) \
\- Manual de Calidad (MC-V3).pdf [Manual de Calidad (MC-V3).pdf](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Calidad (MC-V3).pdf>) \
\- Manual de Desarrollo (MD-V3).pdf [Manual de Desarrollo (MD-V3).pdf](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Desarrollo (MD-V3).pdf>) \
\- Manual de Programación Web (MPW-V3).pdf [Manual de Programación Web (MPW-V3).pdf](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Programación Web (MPW-V3).pdf>) \
\- Manual de Base de Datos (MBD-V3).pdf [Manual de Base de Datos (MBD-V3).pdf](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Base de Datos (MBD-V3).pdf>) \
\
Reglas estrictas:\
\- NO modifiques, reformules ni "corrijas" los manuales ni los requerimientos.\
&#x20; Si algo en ellos parece incorrecto o contradictorio, repórtalo, no lo cambies.\
\- NO modifiques código en esta fase. Esta tarea es solo de análisis.\
\- No inventes reglas: todo hallazgo debe apoyarse en un requerimiento o en una\
&#x20; sección concreta de un manual. Si no encuentras sustento, dilo.\
\- Si hay ambigüedad o conflicto entre manual y requerimiento, no asumas:\
&#x20; márcalo como "Requiere decisión" y explícame el conflicto.\
\
\# Fase 1: Extracción de reglas\
Lee completos los 4 manuales y la lista maestra. Genera un inventario de:\
\- Requerimientos (ID, descripción, módulo, prioridad/estado si existe).\
\- Reglas de los manuales que apliquen a la web: colores, tipografías, textos y\
&#x20; mensajes, nomenclatura, estructura de carpetas, estándares de código,\
&#x20; convenciones de base de datos, validaciones, seguridad, documentación, etc.\
&#x20; Cita siempre manual y sección/página.\
\
\# Fase 2: Revisión del sistema\
Revisa el código y la estructura de la web y evalúa cada requerimiento y cada\
regla de los manuales. Busca específicamente:\
1\. Requerimientos incumplidos o parcialmente cumplidos.\
2\. Requerimientos sin implementación (faltantes).\
3\. Módulos, pantallas u opciones que deberían existir y no existen.\
4\. Incumplimientos de los manuales (colores, textos, formas de desarrollar,\
&#x20;  nomenclatura, estructura, consultas y base de datos).\
5\. Inconsistencias internas del sistema (mismo elemento con estilos o textos\
&#x20;  distintos, flujos incompletos, código duplicado o contradictorio).\
6\. Inconsistencias entre documentos (manual vs. manual, manual vs. requerimiento).\
7\. Funcionalidad en el sistema que no tiene requerimiento asociado.\
8\. Errores técnicos evidentes (bugs, validaciones ausentes, rutas rotas).\
\
\# Formato de cada hallazgo\
\- ID del hallazgo\
\- Categoría (de las 8 anteriores)\
\- Severidad: Crítica / Alta / Media / Baja\
\- Requerimiento o sección del manual afectado\
\- Evidencia: archivo y línea, o descripción del faltante\
\- Qué se esperaba vs. qué se encontró\
\- Acción sugerida (sin ejecutarla)\
\
\# Entregables\
1\. Matriz de trazabilidad: requerimiento -> módulo/archivo -> estado\
&#x20;  (Cumple / Parcial / No cumple / No aplica a web).\
2\. Lista de hallazgos en el formato anterior, en un archivo (por ejemplo\
&#x20;  informe_cumplimiento.md), ordenada por severidad.\
3\. Resumen ejecutivo final: % de cumplimiento, conteo de hallazgos por categoría\
&#x20;  y severidad, los 10 puntos más críticos, y un plan de corrección priorizado.\
\
Antes de empezar, confirma qué archivos y carpetas puedes ver y dime si algo de\
lo anterior no está disponible. Avanza fase por fase y muéstrame el resultado de\
la Fase 1 antes de continuar con la Fase 2.
````

La instrucción posterior del usuario difiere la elaboración del resumen ejecutivo hasta que lo pida expresamente. El nuevo chat puede avanzar módulo por módulo y actualizar la matriz durante ese intervalo.

## 3. Fuentes de verdad de solo lectura

| Fuente | Ruta absoluta | SHA-256 registrado en Fase 1 |
|---|---|---|
| Lista maestra | `/home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx` | `45711e75f798b4f88dd78cf0d0eda3e33ee8a9215e80e65d4cf6e7c4cf36f219` |
| MC-V3 · Calidad | `/home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Calidad (MC-V3).pdf` | `0860ff19ccf01ed17c5309a395f81f1ebf989ea4e1a3f04908944e15eee61184` |
| MD-V3 · Desarrollo | `/home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Desarrollo (MD-V3).pdf` | `b5b65b1de51e3d524794702c2f62f3b1daffe7df45a65adced6dabfbc12e7adf` |
| MPW-V3 · Programación Web | `/home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Programación Web (MPW-V3).pdf` | `24c8a50c2afa862630cd8e1126f4855c1b1bb6a2d597597b9a2b943bd0524fd5` |
| MBD-V3 · Base de Datos | `/home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Base de Datos (MBD-V3).pdf` | `3f0ee89f23d34f2c0f29784d14c8ccb851533a063a5a8d64b6955cb720948783` |

Comparar estas huellas si se recibe una versión posterior. Si cambia una fuente, identificar las filas o secciones afectadas y conservar la versión de referencia junto a las conclusiones que se hayan obtenido. El código, las pruebas y los registros del proyecto sirven como evidencia de ejecución o de proceso. Las reglas de aceptación proceden de las cinco fuentes.

## 4. Mapa de archivos de la Fase 1

| Ruta absoluta | Uso en la Fase 2 |
|---|---|
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/fase_1.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/fase_1.md>) | Punto de entrada. Contiene el corte, recuentos por módulo y rol, alcance y huellas de las fuentes. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/requerimientos.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/requerimientos.md>) | Inventario de las 238 filas. Recupera el código, la referencia preliminar, el nombre y la descripción literales, las marcas web por rol y 122 observaciones originales. El número de fila remite al libro. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/calidad.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/calidad.md>) | 49 entradas MC sobre identidad, presentación de documentos, nomenclatura, formatos, control de versiones y evidencias. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/desarrollo.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/desarrollo.md>) | 62 entradas MD sobre actividades, Kanban, trazabilidad, firmas, riesgos, cambios y formatos DE-01 a DE-09. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/programacion_web.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/programacion_web.md>) | 114 entradas MPW sobre arquitectura, carpetas, código, validaciones, servicios, pantallas, textos y diseño. MPW-D01 a MPW-D15 amplían los asuntos documentales y se solapan con el registro DOC. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/base_de_datos.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/base_de_datos.md>) | 73 entradas MBD sobre PostgreSQL, nomenclatura, integridad, cambios, roles, respaldos y formatos BD-01 a BD-03. Usar los folios físicos verificados porque el índice del PDF contiene desfases. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/catalogos_mpw.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/catalogos_mpw.md>) | Tablas 7–20 del MPW para comprobar cuotas, fórmulas, mensajes aprobados y prohibidos, colores, tipografía, controles e iconos. Cada entrada identifica su página y conserva el contexto de los ejemplos. |
| [/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/decisiones_documentales.md](</home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_1/decisiones_documentales.md>) | Registro DOC-001 a DOC-035 de categoría 6 con severidad, evidencia, discrepancia y acción sugerida. Incluye parámetros y registros que aún faltan para dictaminar. |

El inventario comprende 212 RF, 26 RNF y 298 entradas de manual en 265 páginas. Varios controles agrupan condiciones de una misma sección. Para emitir un dictamen, comprobar las condiciones aplicables de cada entrada. Las preferencias, ejemplos y cifras ilustrativas conservan el carácter que les da el original.

## 5. Decisiones que cambian las pruebas de aceptación

| ID y tema | Fuentes y discrepancia | Tratamiento en la Fase 2 |
|---|---|---|
| DOC-001 · Cierre del examen | RF-EXA-017 y RF-EXA-050, hoja «Lista maestra», D82/I82 y D115/I115. I82 indica que el cierre termina el examen. D115 e I115 permiten continuar a quien ya había iniciado. MPW-V3 §5.6.1, p. 59, y §7.10, p. 92, describen la ventana global y tolerancias de 60 s globales y 3 s por reactivo. | Rastrear la autorización de inicio y el vencimiento del intento por separado. Contrastar un intento nuevo al alcanzarse el límite con otro iniciado antes y contestado después. Registrar qué reloj, condición y tolerancia se aplican. Marcar **Requiere decisión** en el criterio de cierre afectado. |
| DOC-002 · Calificación 0–100 | RF-EXA-011 y RF-CAL-010/011, D76 y D162:D163, exigen la escala 0–100 sin decimales y contemplan la conversión de escalas. MPW-V3 §6.12, tabla 9, pp. 76–77, redondea a dos decimales también en el ejemplo de 0–100. | Seguir una puntuación fraccionaria, por ejemplo 87.25, a través del cálculo, almacenamiento, visualización y exportación. Distinguir la escala de examen de la de grupo. Consignar dónde se conserva o pierde la fracción y mantener **Requiere decisión** sobre el redondeo definitivo. |
| DOC-006 · Cámara durante el examen | MPW-V3 §8.6, ilustración 20, p. 108, pide mantener la cámara encendida. Los §§7.10 y 8.9.3, pp. 92 y 111–112, posponen la biometría, describen supervisión pasiva y reservan el campo `require_webcam` con valor predeterminado falso. | Identificar por separado el texto previo al examen, la configuración y cualquier uso de la cámara por el navegador. Documentar si divergen. Mantener **Requiere decisión** en el criterio de aceptación del aviso y del comportamiento en conflicto. |

El registro completo contiene **35 asuntos** de categoría 6, con **8 altos, 19 medios y 8 bajos**. Los tres anteriores están incluidos en esos totales. Hay 33 entradas con la etiqueta exacta «Requiere decisión», DOC-035 queda condicionada a la redistribución de recursos y DOC-033 ya se clasificó como «Discrepancia localizada» por el índice del MBD. Conservar cada ID y su carácter. Vincularlo desde los módulos afectados, contrastar lo que sí se puede observar y dejar abierta únicamente la conclusión que depende de la decisión. Un mismo DOC se cuenta una vez aunque afecte varios módulos. Además de estos tres, conviene consultar DOC-003 al distinguir pendiente de cero, DOC-004 al auditar pagos y DOC-008 al auditar la visibilidad de resultados.

## 6. Lista maestra y propuesta de cálculo pendiente de aprobación del usuario

Se usó **la versión guardada en disco** del libro, que estaba abierto en LibreOffice. La hoja «Lista maestra» llama a sus 238 filas «propuestas activas» con «códigos previstos» (A3). La leyenda de revisión previa está en A5. E corresponde a docente web, F a alumno web y H a superadministrador web. G pertenece a la app. En **117 filas están vacías las tres columnas web**. Las **seis filas con ✗** son RF-EXA-012, 014, 016 y 017 para docente (E77, E79, E81, E82) y RF-EXA-033 y 043 para alumno (F98, F108). La marca admite tanto ausencia como implementación incompleta.

**Propuesta pendiente de aprobación.** La fórmula y el tratamiento de pendientes siguientes son una propuesta de medición. La revisión de módulos y el registro de evidencia pueden comenzar mientras el usuario decide.

1. Mantener una fila por código previsto. Auditar desde la descripción, por cada rol y condición aplicables, incluso cuando E/F/H estén vacías. Para las seis ✗, identificar qué existe, qué falta y si el flujo llega a su resultado. Tratar las ✓ y N/A del libro como antecedentes sujetos al mismo contraste.
2. Proponer un porcentaje estricto de cumplimiento de **requerimientos**: `100 × códigos web con estado Cumple / total de códigos aplicables a web`. Contar cada código una vez. «Parcial» y «No cumple» permanecen en el denominador y aportan cero al numerador. Excluir solo los «No aplica a web» justificados. Registrar la cobertura de reglas de manual por separado.
3. Si falta evidencia o un DOC impide decidir, dejar el estado sin emitir y registrar el motivo en la columna de seguimiento. Un código de aplicabilidad web confirmada sigue formando parte del universo aunque su dictamen esté pendiente. Cuando la propia aplicabilidad esté en discusión, contabilizarlo aparte hasta resolverla.
4. Durante la auditoría, mostrar los conteos de códigos dictaminados, pendientes y de alcance abierto. Con el universo web ya delimitado se puede indicar la cobertura de revisión: `100 × (Cumple + Parcial + No cumple) / total aplicable`. Reservar el porcentaje definitivo para cuando se apruebe el método y se cierren los dictámenes. Ninguna marca del Excel genera por sí sola crédito, incumplimiento ni exclusión.

## 7. Reglas de trabajo y formato de evidencia

- Trabajar módulo por módulo. Contrastar cada función y cada regla con un RF/RNF o con manual, sección y página. Si falta sustento, declarar «Sin sustento localizado» y documentar qué se consultó. Una función con sustento en un manual puede carecer de RF directo. MD-V3 §5.1, pp. 29–30, también contempla actividades técnicas.
- Conservar las ocho categorías numeradas del prompt original. Para la categoría 7, registrar la función observada, la búsqueda en el catálogo y cualquier respaldo del manual. Para la categoría 8, describir el comportamiento técnico y el criterio afectado. Una sospecha sin evidencia queda en seguimiento.
- Formato de cada hallazgo: **ID estable, categoría 1–8, severidad Crítica/Alta/Media/Baja, requerimiento o sección afectada, evidencia con archivo y línea, esperado, encontrado y acción sugerida sin ejecutarla**. Para un faltante, describir la entrada esperada y los lugares revisados; citar las líneas de los puntos de entrada vecinos si existen. Si la evidencia es documental, usar celdas o páginas. En cada archivo ordenar los hallazgos por severidad.
- Usar exclusivamente **Cumple / Parcial / No cumple / No aplica a web** como estados emitidos de la matriz. «Cumple» requiere evidencia de todas las condiciones web aplicables. «Parcial» identifica una parte implementada y otra incumplida. «No cumple» identifica ausencia o incumplimiento demostrado. «No aplica a web» exige justificación de alcance. Mantener «Requiere decisión» y «Evidencia pendiente» en una columna separada. La falta de acceso a un registro operativo deja su comprobación pendiente.
- Conservar por separado la evidencia de código, las pruebas existentes que se hayan leído, las ejecuciones efectivamente observadas y la evidencia de proceso. Una mención a un test en el Excel no acredita un resultado en el corte actual. Registrar la ruta y línea sobre el commit auditado, indicando cambios locales si los hay. Toda escritura de resultados se limita a `output/`.
- **No generar el resumen ejecutivo, el top 10 ni el plan ejecutivo de corrección hasta que el usuario lo pida.** Sí registrar de inmediato la acción sugerida de cada hallazgo. La entrega original también pide un listado consolidado ordenado por severidad.

## 8. Plan de la Fase 2

Directorio de trabajo: `/home/laambws/Escritorio/acs-exam-web/output/auditoria_web/fase_2/`. Guardar cada revisión en `output/auditoria_web/fase_2/<modulo>.md` y mantener **`output/auditoria_web/fase_2/matriz_trazabilidad.md`** como matriz acumulada. Los nombres de archivo y el orden de esta tabla son una propuesta operativa.

| Orden y archivo propuesto | Qué leer | Qué verificar |
|---|---|---|
| 1 · `AUT.md` | RF-AUT (14); rutas públicas, registro, sesiones, segundo factor, perfil, avisos y archivos privados. | Los tres roles, caducidad y reenvío, límite de intentos, consentimiento, foto y cierre de sesión. Relacionar DOC-005. |
| 2 · `SUS.md` | RF-SUS (15); contratación, pagos, paquetes, límites, tareas de renovación y consumo histórico. | Prueba gratuita, transiciones tras confirmación, cambio de nivel, cancelación, conservación de beneficios consumidos. Relacionar DOC-004 y DOC-020. |
| 3 · `GRU.md` | RF-GRU (21); gestión docente, consulta del alumno, pertenencia, QR y tareas de retención. | Propiedad, límites del paquete, duplicados, retiro/salida, intentos en curso, cierre, reapertura y purga con aviso. |
| 4 · `BAN.md` | RF-BAN (15); catálogo privado, tipos de pregunta, validación, adjuntos y versiones. | Clasificación, búsqueda, acceso a archivos, referencias obligatorias y conservación de copias ya usadas. |
| 5 · `EXA.md` | RF-EXA (57); rutas y vistas de armado, programación, aplicación, temporizadores, guardado, entrega y reaplicación. | Seguir ambos roles, ramificar por modalidad, grupo y paquete. Cubrir el límite de inicio, tiempo individual, reconexión, bloqueos y navegador móvil. Usar DOC-001/006/009/010 y las seis ✗ como puntos de partida. |
| 6 · `EVA.md` | RF-EVA (13); calificación automática, revisión docente, puntuaciones, retroalimentación e historial. | Reglas por tipo, pendientes, respuestas en blanco, acceso del alumno y correcciones auditables. |
| 7 · `IAR.md` | RF-IAR (14); solicitud docente, datos enviados, proveedor, contingencia, colas, créditos y reintentos. | Sugerencia y justificación, confirmación docente, privacidad y consumo solo en las condiciones previstas. |
| 8 · `CAL.md` | RF-CAL (28); periodos, rubros, ponderaciones, captura manual, conversión y recálculo. | Sumas de 100 %, dependencias, pendiente frente a cero, correcciones y calificación final. Seguir DOC-002/003/007. |
| 9 · `REP.md` | RF-REP (17); publicación, políticas de acceso, estadísticas, avisos y exportación Excel. | Rol y momento de publicación, soluciones restringidas, escala, pendientes y contenido por periodo o conjunto de periodos. Relacionar DOC-008. |
| 10 · `ADM.md` | RF-ADM (18); rutas de administración, cuentas, operaciones comerciales, documentos legales y bitácoras. | Alcance del superadministrador, confirmaciones, evidencia de pago, prohibición de acceso al contenido académico y auditoría de cambios. |
| 11 · `RNF.md` | 26 RNF en TEC, SEG, CON, PRI, USA, ACC y REC. Cruzar ejemplos y evidencia recogida en los diez módulos. | Tecnología, permisos efectivos, integridad, privacidad, acceso por teclado, uso en navegador y recuperación. Separar requisitos del cliente nativo y evidencias operativas pendientes. |
| 12 · `manuales_transversales.md` | Las 298 entradas MC, MD, MPW y MBD, los catálogos y el registro DOC. | Asignar a cada regla su módulo o alcance transversal, evidencia, carácter y resultado. Completar pantalla → función → RF/manual; revisar diseño, mensajes, estructura, persistencia y registros de calidad. |

**Procedimiento repetible por módulo**

1. Seleccionar en el inventario los códigos del módulo, las filas del Excel, sus roles y los DOC relacionados. Recuperar de las fuentes los criterios de aceptación y de los anexos las reglas de manual que correspondan. Conservar la distinción entre descripción, observación previa y ejemplo.
2. Identificar las entradas reales del módulo en `routes/` y seguir, según proceda, middleware, autorización, controlador, validación, servicio, consulta/modelo, migración, vista o tarea programada. Consultar recursos públicos y tests vinculados. Estas carpetas orientan la búsqueda; las rutas concretas se consignan cuando se comprueban.
3. Seguir al menos el recorrido necesario para comprobar cada condición documentada, incluida la respuesta a cancelación, límite, error o cambio de rol cuando la fuente lo exija. Mapear también las opciones visibles o activas hacia su respaldo documental. Revisar artefactos operativos cuando sean pertinentes y accesibles.
4. Escribir el informe del módulo con códigos revisados, entradas de manual, evidencias y asuntos pendientes. Usar un ID estable por hallazgo (por ejemplo `WEB-EXA-001`) y vincular todos los códigos afectados. Conservar los `DOC-###` existentes para los conflictos de Fase 1.
5. Actualizar la matriz tras cada módulo. Columnas sugeridas: **código previsto, fila Excel, módulo, roles y alcance web, archivo:línea, referencia de manual, estado, hallazgo/DOC y evidencia o decisión pendiente**. Al cierre, reconciliar las 238 filas sin duplicar códigos, comprobar la cobertura de las 298 entradas de manual y consolidar la lista de hallazgos por severidad en `informe_cumplimiento.md` dentro de Fase 2.

## 9. Estado actual

**Completado.** Accesibilidad de las cinco fuentes y de la raíz del proyecto confirmada. Fase 1 entregada con los ocho archivos de la sección 4. Se registraron las huellas, los inventarios y los 35 asuntos documentales. Este archivo es el punto de entrada al nuevo chat.

**Pendiente.** Iniciar la revisión de implementación por AUT y abrir la matriz acumulada. Todavía no se ha dictaminado ningún módulo contra el código en esta auditoría. Quedan pendientes la aprobación del método de cálculo y las decisiones del registro que afecten a cada criterio. Los informes de módulo, matriz y consolidado se producirán durante la Fase 2. El resumen ejecutivo queda reservado para la petición expresa del usuario.

## 10. Decisiones aprobadas para la Fase 2

1. Se aprueba el método de la sección 6. Calcular el porcentaje estricto sobre requerimientos web y presentar la cobertura de revisión por separado.
2. Aplicar la siguiente rúbrica. **Crítica** si compromete seguridad, privacidad, integridad del examen o de las calificaciones, o puede causar pérdida de datos. **Alta** si un requerimiento funcional se incumple o un rol queda con un flujo principal incompleto. **Media** si hay cumplimiento parcial o una desviación del manual con efecto visible en mensajes, validaciones o estructura. **Baja** si la inconsistencia es cosmética o documental y carece de efecto en el comportamiento.
3. Se autoriza únicamente el análisis estático. La comprobación que requiera ejecutar pruebas, el sistema, migraciones o servidores se registra como «Evidencia pendiente».
4. EXA se revisa en tres tandas con archivos independientes para armado y programación, aplicación y reaplicación y cierre.
