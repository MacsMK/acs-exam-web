# Auditoría de cumplimiento web · Fase 1

**Corte documental del 23 de noviembre de 2026.** Se leyeron las 265 páginas de los cuatro manuales V3 y la única hoja de la lista maestra guardada en disco. El inventario contiene **238 propuestas con códigos distintos** y **298 entradas de manual**. Se registraron **35 discrepancias o decisiones documentales**. La revisión de implementación queda pendiente del punto de control solicitado después de esta entrega.

## Inventarios

| Documento de trabajo | Cobertura y uso |
|---|---|
| [Requerimientos](requerimientos.md) | 212 RF y 26 RNF. Incluye por fila el código previsto, referencia preliminar si existe, módulo o categoría, nombre y descripción literales, las tres marcas web de origen y las observaciones que constan en Excel. |
| [Manual de Calidad](calidad.md) | 49 entradas. Identidad, especificaciones documentales, nomenclatura, formatos, versiones y conservación de evidencia. MC-V3, pp. 1–35. |
| [Manual de Desarrollo](desarrollo.md) | 62 entradas. Roles, Kanban, DE-01 a DE-09, prioridades, validación, trazabilidad, revisión, cambios, firmas y entregas. MD-V3, pp. 1–63. |
| [Manual de Programación Web](programacion_web.md) | 114 entradas. Entorno, arquitectura, carpetas, nomenclatura, documentación de código, validaciones, temporizadores, datos y servicios, textos, pantallas y diseño. MPW-V3, pp. 1–123. |
| [Manual de Base de Datos](base_de_datos.md) | 73 entradas. PostgreSQL, objetos, relaciones, transacciones y cambios, diccionario, roles, respaldos, recuperación y BD-01 a BD-03. MBD-V3, pp. 1–44. |
| [Catálogos del MPW](catalogos_mpw.md) | Consulta por fila de las tablas 7–20. Incluye cuotas, fórmulas, verbos y confirmaciones, mensajes oficiales y prohibidos, tokens, tipografía, dimensiones, iconos y licencias. MPW-V3, pp. 61–122. |
| [Decisiones y discrepancias](decisiones_documentales.md) | Registros DOC-001 a DOC-035, ordenados por severidad, con sustento, evidencia, esperado, encontrado y acción sugerida. Al final se indican parámetros y evidencia pendientes. |

Las entradas de manual agrupan condiciones de un mismo criterio y distinguen obligaciones, opciones, recomendaciones, ejemplos, reglas condicionales y evidencias de proceso. Sus IDs son localizadores de esta auditoría. Para aplicar una condición se conserva su sección y página de origen. Las tablas e ilustraciones se cotejaron cuando aportaban valores, campos o instrucciones.

## Lectura de la lista maestra

| Clasificación | Módulos o categorías · número de códigos |
|---|---|
| RF · 212 | AUT 14, GRU 21, SUS 15, BAN 15, EXA 57, EVA 13, IAR 14, CAL 28, REP 17 y ADM 18. |
| RNF · 26 | TEC 4, SEG 11, CON 4, PRI 2, USA 2, ACC 2 y REC 1. |

La fila 3 califica el conjunto como **propuestas activas con códigos previstos**. Las 238 tienen código en B. La referencia preliminar de A está vacía en 12. La prioridad y el estado formal por requerimiento se documentan en DE-01 conforme a MD-V3 §§4.5–4.8, pp. 27–29. La leyenda del Excel define las marcas de E, F, G y H como revisión de implementación. Las tres columnas de interés web son E, F y H. [Lista maestra, A3:I6 y A7:I244](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>).

| Rol web | ✓ en la fuente | ✗ en la fuente | N/A en la fuente | Celda vacía |
|---|---:|---:|---:|---:|
| Docente | 74 | 4 | 43 | 117 |
| Alumno | 45 | 2 | 48 | 143 |
| Superadmin | 8 | 0 | 113 | 117 |

En 117 filas están vacías **las tres** columnas web. Seis códigos tienen ✗ en algún rol web. Para docente son RF-EXA-012 (modalidad), RF-EXA-014 (tiempo total), RF-EXA-016 (programación por grupo) y RF-EXA-017 (apertura y límite de inicio). Para alumno son RF-EXA-033 (navegación libre) y RF-EXA-043 (vencimiento del tiempo total). En esos seis casos la leyenda del libro agrupa ausencia e implementación incompleta bajo la misma marca. La decisión entre «Parcial» y «No cumple» requiere evidencia independiente. [Lista maestra, A5, E77:I82, F98:I98 y F108:I108](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>).

## Alcance web y criterios que afectan el dictamen

- **Navegador.** Se incluyen docente, alumno y superadministrador, así como servicios de aplicación y persistencia que sostienen sus operaciones web. RF-EXA-053 menciona expresamente navegador de computadora y navegador móvil. RF-GRU-008, RF-EXA-056, RNF-TEC-002 y RNF-SEG-011 prescriben comportamiento o tecnología del cliente nativo. Se conservaron en el inventario para mantener las 238 filas identificables. [Lista maestra, D28, D121, D124, D220 y D241](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>). MPW-V3 §1.1, p. 15 y MBD-V3 §1.2, p. 7.
- **Notificaciones.** RF-EXA-054/057 atribuyen al servidor el disparo del aviso y a la app la recepción. Las tres marcas web dicen N/A. DOC-010 mantiene abierta la atribución para que una pantalla móvil o una misma operación no se cuenten por duplicado. [Lista maestra, D122:I122 y D125:I125](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>). MPW-V3 §§1.1, 2.5.1 y 2.6, pp. 15, 30–31.
- **Documentación e interfaz.** El MC exige Arial 12 pt y tablas documentales blancas. El MPW fija Inter para la interfaz y exige encabezados de tabla azul marino con texto blanco. La finalidad del elemento determina qué regla se contrasta. Para los elementos de marca, ambos manuales sí pueden concurrir. MC-V3 §§1.2, 1.4, 2.4, 3.6 y 3.11, pp. 6–10, 13, 15–17. MPW-V3 §§8.1.2, 8.2 y 8.4, pp. 97–99, 106.
- **Proceso y operación.** Para juzgar aprobaciones y prioridades hacen falta registros DE-01 y evidencias de validación. Para constatar respaldos, roles efectivos de BD o restauraciones se necesitan registros del entorno. El MBD sitúa los respaldos fuera del repositorio. La ausencia de esos elementos entre las cinco fuentes deja su verificación pendiente. MD-V3 §§4.5–4.8, pp. 27–29 y §§11.1.1–11.1.3, pp. 43–46. MBD-V3 §§7.1–7.3 y 8.1–8.4, pp. 29–33.
- **Procedencia de la funcionalidad.** Un control puede proceder directamente del MPW aun cuando falte un RF específico. La acreditación docente por invitación o comprobante y la importación del banco son ejemplos para contrastar en el mapa inverso de Fase 2. El MD admite actividades técnicas sin RF directo. MPW-V3 §6.3.2, pp. 61–63 y §8.10.6, p. 114. MD-V3 §5.1, pp. 29–30 y §11.8.3, p. 60.

## Decisiones documentales con mayor efecto

| Tema | Por qué cambia la aceptación | Evidencia |
|---|---|---|
| Fin del acceso y del intento | Un estudiante que ya inició puede seguir según RF-EXA-050. El MPW valida una ventana global y las notas del propio Excel dan dos tratamientos al cierre. | DOC-001. D82/I82, D115/I115. MPW-V3 §5.6.1, p. 59. |
| Calificaciones | Para 0–100 se piden enteros. El ejemplo técnico redondea a dos decimales y, en el cálculo ponderado, trata un componente ausente como cero. Esto cambia la nota o el estado que se publica. | DOC-002, DOC-003, DOC-007 y DOC-008. D76, D161:D164, D173, D180:D186. MPW-V3 §§6.3.2 y 6.12, pp. 62–63, 76–77. |
| Confirmación de cobros | La contratación, la renovación y los estados dependen de confirmar la operación. Hay que fijar qué evidencia acredita el proveedor y qué acredita la simulación de demostración. | DOC-004. D37:D40, D50, D244 e I37:I40/I50. MPW-V3 §6.3.2, tabla 7, p. 63. |
| Acceso y cámara | La lista exige segundo factor en cada inicio. El MPW usa una condición al describir esa pantalla. La ilustración 20 exige cámara activa y el texto aplaza la biometría. | DOC-005 y DOC-006. D9:D11. MPW-V3 §§7.10, 8.6, 8.9.3 y 8.10.5, pp. 92, 108, 111–114. |

El registro de la categoría 6 contiene **8 asuntos de severidad alta, 19 media y 8 baja**. Son 35 en total. Los nombres MPW-D01 a MPW-D15 del anexo técnico explican pasajes del manual y se solapan con el registro. Las anotaciones de los anexos y los parámetros pendientes no se suman de nuevo. El porcentaje de cumplimiento de implementación, el conteo de hallazgos en las ocho categorías, los diez puntos críticos del sistema y el plan de corrección corresponden a la Fase 2, después de contrastar las fuentes con el sistema.

## Identidad de las fuentes y punto de control

| Fuente suministrada | Extensión revisada | SHA-256 del archivo guardado |
|---|---|---|
| [Lista maestra](</home/laambws/Descargas/manuales/acode-solutions/09_Nuevos_Requerimientos/Lista_maestra_requerimientos.xlsx>) | Una hoja, «Lista maestra», 238 filas de datos, 7–244 | `45711e75f798b4f88dd78cf0d0eda3e33ee8a9215e80e65d4cf6e7c4cf36f219` |
| [Manual de Calidad](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Calidad (MC-V3).pdf>) | 35 páginas | `0860ff19ccf01ed17c5309a395f81f1ebf989ea4e1a3f04908944e15eee61184` |
| [Manual de Desarrollo](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Desarrollo (MD-V3).pdf>) | 63 páginas | `b5b65b1de51e3d524794702c2f62f3b1daffe7df45a65adced6dabfbc12e7adf` |
| [Manual de Programación Web](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Programación Web (MPW-V3).pdf>) | 123 páginas | `24c8a50c2afa862630cd8e1126f4855c1b1bb6a2d597597b9a2b943bd0524fd5` |
| [Manual de Base de Datos](</home/laambws/Descargas/manuales/acode-solutions/08_Manuales_Oficiales_V3/Manual de Base de Datos (MBD-V3).pdf>) | 44 páginas | `3f0ee89f23d34f2c0f29784d14c8ccb851533a063a5a8d64b6955cb720948783` |

Rama visible al inicio: `feature/cumplimiento-requerimientos`, commit `e0a89af`. Se confirmó acceso a la raíz del proyecto y a las carpetas de fuentes. El corte de la lista es el archivo guardado, con bloqueo de LibreOffice presente al abrirse la auditoría. La inspección de esta fase se limitó a fuentes y estructura de primer nivel. El único material nuevo en el proyecto es esta entrega de análisis, dentro de `output/auditoria_web/fase_1/`.
