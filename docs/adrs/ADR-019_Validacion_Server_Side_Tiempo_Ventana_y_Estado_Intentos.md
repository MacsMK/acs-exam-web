# ADR-019 — Validación Server-Side de Tiempo, Ventana de Examen y Estado de Intentos

> **Estado:** Aceptado  
> **Fecha:** 27 de Octubre de 2026  
> **Decisores:** `security-vulnerability-auditor`, `backend-laravel-specialist`  
> **Normativa de Referencia:** Manual `MPW-V2` (§5.6.1), `RNF-CON-001`, `RF-EXA-032`, `RF-EXA-047`

---

## 1. Contexto y Problemática
El control del tiempo y estado del examen dependía principalmente del cronómetro del cliente (Alpine.js en Web y temporizador Dio en Flutter). Esto abría vulnerabilidades críticas:
1. **Evasión de ventana de disponibilidad:** Un alumno podía iniciar exámenes antes de `starts_at` o después de `ends_at` si conocía el ID del examen.
2. **Tiempo infinito por pregunta:** Un atacante podía alterar o detener el JavaScript del navegador y enviar respuestas mucho tiempo después del límite `time_limit_seconds`.
3. **Resurrección de intentos cancelados por trampa:** Si un alumno acumulaba incidentes en la app móvil y el intento pasaba a `status = 'cancelled_by_incident'`, continuar enviando respuestas hasta el final del examen ejecutaba `submitFinal()`, el cual sobrescribía el estado a `'submitted'` y otorgaba calificación final, burlando la sanción anti-trampa.
4. **Navegación regresiva en Web:** Un alumno podía alterar el campo oculto `exam_question_id` en HTML para sobrescribir preguntas contestadas previamente.

---

## 2. Decisión de Arquitectura
1. **Validación Server-Side de Ventana en `AttemptService::startOrResumeAttempt()`:**
   - Se valida contra el reloj del servidor:
     - Si `now() < starts_at`: se rechaza con excepción de dominio / `HTTP 403` (*"El examen aún no está disponible"*).
     - Si `now() > ends_at`: se rechaza con excepción de dominio / `HTTP 403` (*"La ventana del examen ha concluido"*).
2. **Validación de Estado Inmutable (`status === 'in_progress'`):**
   - Tanto en `submitAnswer()` como en `submitFinal()`, se valida estrictamente:
     ```php
     if ($attempt->status !== 'in_progress') {
         throw new \DomainException('Este intento ya no se encuentra activo.', 409);
     }
     ```
   - Si un intento fue marcado como `cancelled_by_incident` o `submitted`, cualquier envío posterior es rechazado con **`HTTP 409 Conflict`** y jamás se altera su estado terminal ni su puntaje.
3. **Validación de Secuencia Estricta (Anti-Navegación Regresiva):**
   - Se verifica que el `exam_question_id` enviado coincida exactamente con la pregunta activa en `question_sequence_payload[$current_question_index]`. Intentos de enviar reactivos fuera de orden son rechazados con `HTTP 422`.
4. **Tolerancia de Red en Tiempo Límite:**
   - Se calcula el límite de tiempo a partir de `started_at + duration_minutes + 60s` de tolerancia por latencia de red. Si el tiempo expira, el intento se finaliza automáticamente por el servidor.

---

## 3. Consecuencias
- **Positivas:** El servidor es la única autoridad de tiempo y estado; se mitigan fraudes por alteración de JavaScript del navegador; imposibilidad matemática de revivir intentos cancelados por incidentes anti-trampa.
