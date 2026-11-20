# ADR-061 — Datos del Alumno en los Listados de Grupos, Exámenes y Calificaciones de la API Móvil

> **Estado:** Aceptado  
> **Fecha:** 15 de Noviembre de 2026 (actualizado el 20 de Noviembre de 2026: fechas, grupo en calificaciones y filtro por grupo)  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-036` (salida de grupo y un intento por alumno), `ADR-037` (resultados del estudiante, sin reaplicaciones)  
> **Normativa de Referencia:** `MPW-V2` (§6.1, §6.3), `MPM-V3` (Tabla 12, §6.9.1), `RF-GRU-010`, `RF-GRU-019`, `RF-GRU-021`, `RF-EXA-026`, `CAM-002`, `StudentGroupAndExamListingTest.php`

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
   - `starts_at` y `ends_at`: fecha y hora de apertura y fecha y hora límite del examen. Hoy el servidor solo maneja la programación común del examen; cuando exista la programación por grupo (`CAM-002`, `ACT-WEB-027`), estos campos entregarán la del grupo del alumno sin cambiar su nombre.
   - `questions_count`: número de preguntas del examen.
   - `attempts_allowed` y `attempts_used`: intentos permitidos (uno, por el índice único `(exam_id, student_id)` de `ADR-036`) y usados (0 o 1).
   - `attempt_status`: `null` si no ha iniciado; si no, `in_progress`, `submitted`, `expired` o `cancelled_by_incident`.
3. **Vencimiento informado:** un intento que sigue en `in_progress` pero cuyo plazo ya terminó se informa como `expired`, usando `AttemptService::isInProgress()`. En la base el estado no cambia: el servidor solo lo marca al intentar enviarlo (`ADR-036`, punto 3).
4. **Grupos cerrados (`RF-GRU-019`):** el alumno ya no ve exámenes que solo están asignados a grupos suyos cerrados. Es el mismo criterio que el panel web.
5. **Sin consultas por fila:** las relaciones (`teacher`, grupos del alumno, su intento) y el conteo de preguntas se cargan en la misma consulta del listado.
6. **El docente no cambia:** su listado de exámenes conserva `id` y `title`, y su listado de calificaciones conserva sus filas por examen.
7. **`GET /api/v1/grades` para el alumno:** cada fila, publicada o pendiente de evaluación, agrega `group` (`id`, `name` y `subject`) con el grupo en el que presentó el intento.
8. **Filtro por grupo:** `GET /api/v1/exams` y `GET /api/v1/grades` aceptan `?group={id}` para el alumno (`StudentGroupFilterRequest`). Solo se listan los exámenes asignados a ese grupo o los intentos presentados en él; un grupo ajeno o cerrado devuelve una lista vacía y un valor no numérico responde 422. Lo usa el detalle del grupo de la app (`student.groups.show`, `MPM-V3` §6.9.1), con dos peticiones paralelas y sin una consulta por examen.

---

## 3. Consecuencias
- **Positivas:** la app puede mostrar materia, docente, fechas, número de preguntas e intentos, y armar el detalle del grupo, sin endpoints nuevos; los campos son aditivos, así que la versión actual de la app sigue funcionando porque ignora lo que no conoce; no requiere migración.
- **Pendiente:** la programación por grupo (`CAM-002`, `ACT-WEB-027`) requiere el cambio de base de datos que defina el Manual de Base de Datos; al implementarse, `starts_at` y `ends_at` tomarán la del grupo del alumno.
- **Cambio de comportamiento:** un alumno deja de ver en la app los exámenes de un grupo cerrado, igual que en el panel web.
- **Impacto en documentación:** `MPM-V3` describe los campos nuevos que consume la app (Tabla 12, §6.4.1 y §6.9.1) y se registra en `DE-06` (`CAM-007`); el acuerdo con backend se registra en `DE-03` (`ACU-004`).
