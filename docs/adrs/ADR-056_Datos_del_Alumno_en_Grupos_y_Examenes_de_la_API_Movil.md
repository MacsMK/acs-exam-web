# ADR-056 — Datos del Alumno en los Listados de Grupos y Exámenes de la API Móvil

> **Estado:** Aceptado (fechas y duración del examen pendientes de `CAM-002`)  
> **Fecha:** 15 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-036` (salida de grupo y un intento por alumno), `ADR-037` (resultados del estudiante, sin reaplicaciones)  
> **Normativa de Referencia:** `MPW-V2` (§6.1, §6.3), `MPM-V3` (Tabla 12), `RF-GRU-010`, `RF-GRU-019`, `RF-EXA-026`, `StudentGroupAndExamListingTest.php`

---

## 1. Contexto y Problemática
En la revisión con el cliente del 9 de noviembre se observó que la app no muestra lo que el panel web del alumno ya muestra: en los grupos falta la materia y el docente, y en los exámenes falta de qué materia y docente son y en qué quedó el intento del alumno (cuántos lleva o por qué se canceló).

La app no puede mostrarlo porque la API no lo envía: `GET /api/v1/groups` devolvía solo `id`, `name`, `folio` y `students_count`, y `GET /api/v1/exams` solo `id` y `title`.

`RF-EXA-026` (Consulta de exámenes del estudiante) pide identificar la fecha de apertura, el límite para iniciar y el estado de la participación, y respetar la restricción de grupos cerrados de `RF-GRU-019`. La API listaba también los exámenes de grupos ya cerrados, a diferencia del panel web (`GroupEnrollmentService::listAvailableExamsForStudent()`).

---

## 2. Decisión de Arquitectura
1. **`GET /api/v1/groups` y `POST /api/v1/groups/join`** agregan, sin quitar nada:
   - `subject`: materia del grupo (`acs_groups.subject`, puede ser `null`).
   - `teacher_name`: nombre completo del docente del grupo.
   - `joined_at`: fecha de ingreso del alumno (`acs_group_student.joined_at`); `null` en la lista del docente.

   `join` responde con el grupo tal como aparece en el listado, ya con su fecha de ingreso.
2. **`GET /api/v1/exams` para el alumno** agrega, sin quitar nada:
   - `teacher_name`: nombre completo del docente del examen.
   - `group`: `id`, `name` y `subject` del grupo del alumno al que está asignado el examen (si el alumno ya tiene intento, el grupo del intento).
   - `questions_count`: número de preguntas del examen.
   - `attempts_allowed` y `attempts_used`: intentos permitidos (uno, por el índice único `(exam_id, student_id)` de `ADR-036`) y usados (0 o 1).
   - `attempt_status`: `null` si no ha iniciado; si no, `in_progress`, `submitted`, `expired` o `cancelled_by_incident`.
3. **Vencimiento informado:** un intento que sigue en `in_progress` pero cuyo plazo ya terminó se informa como `expired`, usando `AttemptService::isInProgress()`. En la base el estado no cambia: el servidor solo lo marca al intentar enviarlo (`ADR-036`, punto 3).
4. **Grupos cerrados (`RF-GRU-019`):** el alumno ya no ve exámenes que solo están asignados a grupos suyos cerrados. Es el mismo criterio que el panel web.
5. **Sin consultas por fila:** las relaciones (`teacher`, grupos del alumno, su intento) y el conteo de preguntas se cargan en la misma consulta del listado.
6. **El docente no cambia:** su listado de exámenes conserva `id` y `title`.

---

## 3. Consecuencias
- **Positivas:** la app puede mostrar materia, docente, número de preguntas e intentos sin endpoints nuevos; los campos son aditivos, así que la versión actual de la app sigue funcionando porque ignora lo que no conoce; no requiere migración.
- **Pendiente:** las fechas de apertura y cierre y la duración del examen se agregan cuando se resuelva `CAM-002` (fecha común o por grupo), para que la app reciba la del grupo del alumno, como pide `RF-EXA-026`.
- **Cambio de comportamiento:** un alumno deja de ver en la app los exámenes de un grupo cerrado, igual que en el panel web.
- **Impacto en documentación:** `MPM-V4` describirá los campos nuevos que consume la app (Tabla 12 y modelos) y se registra en `DE-06`; el acuerdo con backend se registra en `DE-03`.
