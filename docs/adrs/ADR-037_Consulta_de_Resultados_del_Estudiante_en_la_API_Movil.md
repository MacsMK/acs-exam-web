# ADR-037 — Consulta de Resultados del Estudiante en la API Móvil

> **Estado:** Aceptado (RF-REP-005 con puntos pendientes de validar en `DE-01`)  
> **Fecha:** 8 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-003` (Copy-On-Write de reactivos), `ADR-019` (estado e intentos), `ADR-036` (resultados del grupo tras la salida)  
> **Normativa de Referencia:** `MPW-V2` (§6.1), `MPM-V3` (Tabla 12), `RF-REP-002`, `RF-REP-004`, `RF-REP-005`, `RF-REP-006`, `StudentResultsTest.php`

---

## 1. Contexto y Problemática
La API solo exponía `GET /api/v1/grades` (una línea por examen con calificación) y `GET /api/v1/grades/statistics`. Los requerimientos `RF-REP-002`, `004`, `005` y `006` piden que el estudiante consulte: el estado «Pendiente de evaluación» sin calificaciones parciales ni estimaciones de IA; los puntos por pregunta y por apartado; sus respuestas y las soluciones de referencia solo cuando la calificación esté publicada **y** haya terminado el plazo de acceso al examen; y los comentarios del docente sin las justificaciones provisionales de la IA. Además, `RF-REP-004` y `RF-REP-006` aclaran que esas consultas **no habilitan** la visualización de respuestas o soluciones.

---

## 2. Decisión de Arquitectura
1. **Estado publicado o pendiente.** Un intento está **publicado** cuando tiene `final_score`, y **pendiente** cuando está `submitted` sin `final_score` (quedan respuestas por evaluar, `RF-EVA-010`). Un intento cancelado por incidentes no es un resultado.
2. **`GET /api/v1/grades` (existente, ampliado).** Cada fila lleva `status` (`published` | `pending`). Las filas pendientes llevan `score` e `is_passing` nulos: no se envía ninguna calificación parcial. `grades/statistics` solo cuenta calificaciones publicadas.
3. **`GET /api/v1/grades/{attempt}` (`api.v1.grades.show`, nuevo).** Solo el estudiante dueño, en un grupo al que pertenece hoy (`404` en otro caso, `403` para docentes). Pendiente → solo `status` y el mensaje `Pendiente de evaluación`. Publicado → `score`, escala, `sections[]` con puntos obtenidos y posibles por apartado (suma de sus preguntas, sin ponderación adicional) y `questions[]` con los puntos por pregunta (la pregunta sin responder cuenta cero), `teacher_comments[]` y `review_available`.
4. **`GET /api/v1/grades/{attempt}/review` (`api.v1.grades.review`, nuevo).** Enunciado visto por el alumno, su respuesta y la solución de referencia por pregunta. Responde **`403`** con el mensaje *«Podrás consultar tus respuestas y las soluciones cuando tu calificación esté publicada y haya terminado el plazo de acceso al examen.»* si falta cualquiera de las dos condiciones: calificación publicada y `ends_at` del examen vencido **más los 60 s de tolerancia** con los que el servidor aún acepta envíos (`ADR-019`). Con eso, ningún alumno puede seguir examinándose cuando las soluciones se publican.
5. **Comentarios del docente.** Se devuelven solo los textos de `teacher_recoteje_notes` (opcionales; su ausencia no impide consultar la calificación). `ai_feedback`, `ai_similarity_score` y demás datos de la IA nunca se serializan.
6. **Soluciones fieles al examen.** Las soluciones salen de la pregunta vinculada al examen. Un reactivo ya usado en un examen no se edita en sitio: la edición crea una copia (`ADR-003`), de modo que la solución mostrada es la que tenía el examen aunque el banco cambie.
7. **Capa de servicio.** `StudentResultService` concentra las reglas; `GradeController` solo delega. `AttemptService::visibleStem()` reproduce el enunciado (paráfrasis incluida) que vio el alumno.

---

## 3. Consecuencias
- **Positivas:** el candado de `RF-REP-005` lo aplica el servidor, no la app; sin migraciones; el contrato de `grades` solo crece (`status`).
- **Pendiente en `DE-01` (`RF-REP-005`):** la protección de soluciones con intentos en curso tras el cierre o con reaplicaciones. La web no tiene reaplicaciones y ningún intento puede continuar pasado el cierre más la tolerancia, por lo que hoy no se presenta; debe resolverse antes de validar el requerimiento.
- **Límite de `RF-REP-006`:** la web todavía no tiene un flujo en el que el docente escriba comentarios (solo existe la columna `teacher_recoteje_notes`); mientras no se capture, la lista llega vacía.
- **No cubiertos por esta decisión:** `RF-REP-008` (calificación final) depende de `RF-CAL-020/021`, cuyo cálculo y captura aún no existen en la web (las tablas de periodos no se alimentan); `RF-REP-017` (aviso de corrección) depende de que exista una corrección de calificaciones publicadas, que la web aún no ofrece.
