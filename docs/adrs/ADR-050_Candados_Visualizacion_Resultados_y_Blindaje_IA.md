# ADR-050 — Candados de Visualización de Resultados, Blindaje Anti-Fuga de IA y Revisión Post-Examen

**Estado:** Aceptado  
**Fecha:** 2026-11-09  
**Autores:** Equipo de Seguridad y Calidad (A-Code Solutions)  
**Requerimientos Relacionados:** P-136, P-171, P-172, P-173, RF-EVA-009, RF-REP-004, RF-REP-005, RF-REP-006, RW-27  

---

## Contexto

El requerimiento `RF-EVA-009` (P-136) y la especificación `RW-27` señalan explícitamente que el sistema debe impedir que el alumno consulte las puntuaciones y justificaciones emitidas por el servicio de evaluación asistida (IA) antes de que el docente las valide o recoteje.

En la vista web de resultado anterior (`result-summary.blade.php`), ante la presencia de `ai_evaluated_at`, se desplegaban inmediatamente insignias como «IA: Cumple objetivo» y la retroalimentación cualitativa generada por Gemini AI, generando confusiones y posibles discrepancias si el profesor ajustaba la calificación posteriormente.

Asimismo, los requerimientos `RF-REP-005` y `RF-REP-006` exigen que el alumno pueda consultar sus respuestas y las soluciones modelo únicamente cuando la calificación esté publicada y la ventana de aplicación del examen haya concluido formalmente.

## Decisión

1. **Blindaje de IA en `result-summary.blade.php`:** En reactivos abiertos y de programación, si el profesor aún no ha validado la nota (`teacher_recoteje_score === null`), se ocultan todos los datos de IA y se despliega la insignia institucional `<x-badge variant="warning">Pendiente de evaluación docente</x-badge>` con el texto *«Puntos: Pendiente de validación»*.
2. **Exhibición de Comentarios del Profesor (`P-173`):** Se exponen exclusivamente las notas capturadas por el docente (`teacher_recoteje_notes`) una vez recotejado el reactivo.
3. **Control de Acceso a Soluciones (`reviewIsAvailable`):** La ruta `intentos/{attempt}/revision` exige:
   - Que el intento esté entregado (`submitted`).
   - Que la calificación final esté publicada (`final_score !== null`).
   - Que la ventana del examen haya cerrado (`ends_at <= now()`).
   Cualquier intento previo es rechazado con HTTP 403 Forbidden.

## Consecuencias

- **Positivas:**
  - Se erradica la fuga de calificaciones provisionales de IA, protegiendo la autoridad pedagógica del docente.
  - Se previene la filtración de reactivos y soluciones entre estudiantes durante ventanas de examen activas.
- **Negativas:**
  - Los estudiantes deben esperar a que el docente concluya la etapa de recoteje para conocer su dictamen cualitativo definitivo.
