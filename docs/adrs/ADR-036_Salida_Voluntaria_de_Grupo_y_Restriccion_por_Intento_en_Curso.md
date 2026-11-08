# ADR-036 — Salida Voluntaria de Grupo y Restricción por Intento en Curso

> **Estado:** Aceptado (alcance de eliminación de registros pendiente de confirmar en `DE-01`)  
> **Fecha:** 8 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-013` (aislamiento multi-docente), `ADR-019` (estado e intentos), `ADR-028` (política de preservación de evidencia académica)  
> **Normativa de Referencia:** `MPW-V2` (§5.2, §6.3), `MPM-V3` (Tabla 12), `RF-GRU-012`, `RF-GRU-017`, `GroupLeaveTest.php`

---

## 1. Contexto y Problemática
`RF-GRU-012` (Salida voluntaria del estudiante) permite al alumno **abandonar un grupo**, previa confirmación explícita que le advierta que perderá su pertenencia y sus calificaciones y antecedentes académicos de ese grupo. `RF-GRU-017` (Restricción de salida durante un intento) **impide la salida mientras tenga un intento en curso asociado al grupo**; el rechazo no modifica su pertenencia, no elimina información y no finaliza el intento.

La API no tenía forma de salir de un grupo. Además, el `DE-01` de `RF-GRU-012` deja **pendientes el alcance exacto de eliminación de registros y las condiciones de reincorporación**.

---

## 2. Decisión de Arquitectura
1. **Nuevo endpoint:** `POST /api/v1/groups/{group}/leave` (`api.v1.groups.leave`), solo alumno (`403` para docentes) y solo si pertenece al grupo (`422` en caso contrario). La confirmación explícita y la advertencia las presenta la app antes de llamarlo.
2. **Regla de `RF-GRU-017` en `GroupEnrollmentService::leaveGroup()`:** si el alumno tiene un intento **en curso** del grupo, responde **409** con *"No puedes abandonar el grupo mientras tengas un examen en curso. Podrás hacerlo cuando ese intento haya finalizado."* y no modifica nada.
3. **Definición de "en curso" (`AttemptService::isInProgress()`):** estado `in_progress` **y** vencimiento aún no alcanzado (ventana del examen, con la tolerancia de 60 s de `ADR-019`). El estado `expired` solo se marca cuando alguien intenta enviar el intento, así que un intento abandonado a medias dejaría al alumno atrapado en el grupo para siempre si bastara con mirar el estado. La lógica de vencimiento se extrajo a un método privado compartido con la validación de envío, sin cambiar su comportamiento.
4. **Efecto de la salida:** se elimina la fila de `acs_group_student` y se registra `group.left` en `acs_audit_logs`. **No se borran** intentos, respuestas ni calificaciones: el alumno **pierde el acceso** porque sus consultas se limitan a los grupos a los que pertenece hoy (`StudentAttempt::scopeInCurrentGroupsOfStudent`, usado por `grades` y por el repositorio de intentos del alumno). Si vuelve a unirse, sus resultados reaparecen.
5. **Por qué no se borra la evidencia:** el `DE-01` deja sin definir el alcance de la eliminación y `ADR-028` fija la política de conservar la evidencia académica. Además, cada alumno tiene un solo intento por examen (índice único `(exam_id, student_id)` en `acs_student_attempts`): si la salida borrara los intentos, un alumno podría salir del grupo, volver a entrar con el folio y repetir un examen ya presentado.

---

## 3. Consecuencias
- **Positivas:** el alumno puede salir sin perder integridad de datos; el docente conserva la evidencia; la restricción de intento en curso no atrapa a nadie por intentos viejos; no requiere migración.
- **A confirmar:** si el cliente exige **eliminación física** de calificaciones y antecedentes del grupo, hay que decidirlo en `DE-01`/`DE-06`, junto con las condiciones de reincorporación (un alumno que se reincorpora hoy recupera sus resultados).
- **Impacto en documentación:** `MPM-V3` (Tabla 12) documenta el endpoint y el mensaje de advertencia que muestra la app; se registra en `DE-06`.
