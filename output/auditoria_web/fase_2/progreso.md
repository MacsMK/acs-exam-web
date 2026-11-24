# Progreso de la Fase 2

Commit `e0a89af1d774769e1d00deb5423329f632199944`. Cinco huellas coincidentes con la Fase 1. Método, severidad y límites de ejecución aprobados en la sección 10 del traspaso.

| Orden | Bloque | Estado |
|---:|---|---|
| 1 | [AUT](AUT.md) | Cerrado |
| 2 | [SUS](SUS.md) | Cerrado |
| 3 | [GRU](GRU.md) | Cerrado |
| 4 | [BAN](BAN.md) | Cerrado |
| 5 | [EXA_1_armado_y_programacion](EXA_1_armado_y_programacion.md) | Cerrado |
| 6 | [EXA_2_aplicacion](EXA_2_aplicacion.md) | Cerrado |
| 7 | [EXA_3_reaplicacion_y_cierre](EXA_3_reaplicacion_y_cierre.md) | Cerrado |
| 8 | [EVA](EVA.md) | Cerrado |
| 9 | [IAR](IAR.md) | Cerrado |
| 10 | [CAL](CAL.md) | Cerrado |
| 11 | [REP](REP.md) | Cerrado |
| 12 | [ADM](ADM.md) | Cerrado |
| 13 | [RNF](RNF.md) | Cerrado |
| 14 | [manuales_transversales](manuales_transversales.md) | Cerrado |

14/14 bloques cerrados. 238/238 códigos incorporados. Cumple: 50 · Parcial: 127 · Sin dictamen: 27 · No aplica a web: 4 · No cumple: 30.

Hallazgos incorporados por severidad. crítica 19, alta 61, media 74, baja 21. Cada DOC y cada hallazgo web se cuenta una sola vez.

**Bloqueos de dictamen.** Los DOC se conservan en la columna de seguimiento. La operación de correo, cobros, tareas, respaldos y registros de proceso requiere evidencia del entorno o del expediente autorizado. Los 14 bloques de revisión estática están cerrados.

**Siguiente acción.** Consultar las decisiones y evidencias pendientes que enumera el consolidado.

## Reconciliación y pendientes

Universo de 238 códigos. Hay **232 aplicables a web**, 4 exclusivos del cliente nativo y 2 con alcance abierto por DOC-010. Entre los aplicables hay 50 Cumple, 127 Parcial, 30 No cumple y 25 sin dictamen. El conjunto contiene 27 filas con estado reservado.

Cumplimiento estricto acreditado al corte: **50/232 = 21,55 %**. Cobertura de dictamen sobre el universo confirmado: **207/232 = 89,22 %**. El denominador conserva los aplicables pendientes y excluye los dos de alcance abierto. Ambos cocientes son provisionales hasta resolver los pendientes. Cobertura de revisión documental de este inventario: 238/238 códigos y 298/298 reglas examinadas.

El [registro de manuales](manuales_transversales.md) contiene 49 MC, 62 MD, 114 MPW, 73 MBD y el cotejo de 111 filas de las tablas 7–20. El [consolidado](informe_cumplimiento.md) conserva 175 IDs únicos.

**Decisiones y evidencia.** Hay 39 asuntos documentales abiertos y uno condicionado a redistribución. Afectan, entre otros, al cierre y los relojes del examen, redondeo y publicación de notas, confirmación de pagos, alcance del envío de avisos, capacidades por nivel y criterios para navegador y teclado. Las filas sin dictamen identifican la evidencia que falta. Se requieren, según la fila, registros de correo y colas, acuerdos DE y BD firmados, configuración efectiva de permisos, constancias de respaldo y restauración y observación de navegadores compatibles.

## Comprobación final del repositorio

2026-11-23T13:17:32-06:00 · Ciudad de México. El commit coincide con el corte inicial. Las cinco huellas SHA-256 volvieron a coincidir. La comparación de la hoja original, B7:B244, dio 238 pares distintos de fila y código, idénticos a la matriz. Se comprobó el destino de los 272 enlaces a hallazgos y la existencia de las 236 rutas de evidencia con sus líneas.

```text
On branch feature/cumplimiento-requerimientos
Your branch is up to date with 'origin/feature/cumplimiento-requerimientos'.

Untracked files:
  (use "git add <file>..." to include in what will be committed)
	output/

nothing added to commit but untracked files present (use "git add" to track)
```

La enumeración desglosada con `git status --porcelain=v1 -z --untracked-files=all` ubica los 28 archivos listados en `output/`. Incluye los ocho anexos de Fase 1 y el informe PDF histórico que ya se encontraban allí. En Fase 2 hay 19 archivos, incluido el traspaso con su nueva sección 10. No se detectaron cambios fuera de `output/`.

Las dos dependencias citadas desde `vendor/` coinciden con las referencias de origen de `composer.lock`: Laravel Framework v13.38.0, `c42f9abd4a3d2f9a3cd6be109255ddce8092ceb8`, y PhpSpreadsheet 5.11.0, `3aa1f0d0f3c658b803c18050ac49244d07d96907`. El contraste fue de lectura estática.

## Verificación de continuación · 2026-11-23T18:54:02-06:00

Los 14 bloques ya estaban cerrados. Módulos en curso o pendientes de revisión estática del corte original: **0**. Se conservan los dictámenes y las citas del commit `e0a89af1d774769e1d00deb5423329f632199944`. La sección 10 del traspaso ya constaba una sola vez. Las cinco huellas coinciden con las del traspaso. La salida completa de Git y las huellas de esta continuación se añadieron al [estado inicial](00_estado_inicial.md).

**Excepción al cierre original de Git.** Al empezar esta continuación ya había **106 archivos fuera de `output/`** con cambios locales (72 modificados y 34 nuevos sin seguimiento). Corresponden a `.env.example`, `README.md`, `app/`, `config/`, `database/`, `resources/`, `routes/` y `tests/`. De los modificados, 47 se citan directamente en la matriz. Para reproducir las citas debe consultarse el contenido del commit auditado. El cumplimiento del árbol local actual queda sin verificar. Dentro de `output/` también estaban presentes dos archivos en `auditoria_web/correcciones/` ajenos a los 28 del cierre anterior.

**Reconciliación del corte auditado.** Coinciden los 238 pares de código y fila de B7:B244 con la matriz. De los 232 aplicables a web, 50 cumplen, 127 cumplen parcialmente, 30 incumplen y 25 tienen dictamen pendiente. Los otros seis corresponden a cuatro exclusivos de la app y dos con alcance abierto por DOC-010. Se verificó el destino de los 272 enlaces de la matriz al consolidado. Coinciden las 298 entradas únicas de manual (49 MC, 62 MD, 114 MPW y 73 MBD) y las 111 filas de las tablas 7–20. Los 175 hallazgos únicos conservan su categoría y severidad en el consolidado: **19 críticos, 61 altos, 74 medios y 21 bajos**.

**Pendientes de decisión y evidencia.** Siguen abiertos 39 asuntos documentales. DOC-035 depende de la redistribución de recursos y DOC-033 corresponde a la discrepancia de índice ya localizada. Como siguiente acción del expediente, registrar la resolución de los DOC abiertos e incorporar a las filas señaladas la evidencia de operación y de proceso. Las citas y los resultados anteriores siguen referidos al corte inicial.

## Revalidación solicitada sobre los cambios locales · 2026-11-23T19:02:51-06:00

Se contrastaron los 134 hallazgos WEB con la copia local actual, incluidos los archivos sin seguimiento. Hay **23 corregidos, 24 parcialmente corregidos y 87 que persisten**. De los 19 críticos originales, 16 tienen corrección completa en código y 3 tienen corrección parcial. Las cifras de la matriz de 238 requerimientos siguen identificando el corte original. La revalidación por hallazgo tiene su propio informe, con el detalle y las limitaciones de producción. [Vigencia de los hallazgos y evidencias por ID](revalidacion_hallazgos.md)
