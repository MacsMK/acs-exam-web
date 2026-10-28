# ADR-018 — Calificación Proporcional en Relación de Columnas (`RF-EVA-002`) y Omisión de IA en Respuestas Vacías (`RF-EVA-008`)

> **Estado:** Aceptado  
> **Fecha:** 27 de Octubre de 2026  
> **Decisores:** `normative-compliance-auditor`, `backend-laravel-specialist`  
> **Requerimientos Asociados:** `RF-EVA-002` (Propuesta P-114), `RF-EVA-008` (Propuesta P-126), `RF-IAR-001` (Propuesta P-118), `RF-EVA-009` (Propuesta P-136)

---

## 1. Contexto y Problemática
Durante la auditoría del motor de calificaciones en `app/Services/AttemptService.php`, se identificaron dos discrepancias críticas frente al catálogo de requerimientos del cliente:
1. **Calificación todo-o-nada en relación de columnas:** `gradeMatching()` comparaba el conjunto completo de pares esperados contra los entregados mediante `===`. Si un reactivo tenía 4 pares y el alumno acertaba 3, recibía `0.0` puntos. Esto violaba directamente el requerimiento `RF-EVA-002`, el cual exige otorgar una puntuación proporcional a la cantidad de correspondencias correctas.
2. **Consumo innecesario de IA en reactivos en blanco:** Cuando un estudiante dejaba un reactivo abierto o de código vacío (o se agotaba el cronómetro sin respuesta), `submitAnswer()` invocaba al evaluador de IA (`AiEvaluatorContract`), consumiendo procesamiento y cuotas de IA inútilmente, además de asignar de forma síncrona un puntaje provisional que se exponía como definitivo antes del recotejo docente.

---

## 2. Decisión de Arquitectura
1. **Algoritmo Proporcional en `AttemptService::gradeMatching()`:**
   - Se calcula el número de pares individuales correctos:
     $$\text{correctPairs} = \sum [(\text{par}_\text{esperado} \in \text{pares}_\text{alumno})]$$
   - El puntaje asignado se calcula proporcionalmente con redondeo a 2 decimales:
     $$\text{pointsAwarded} = \text{round}\left( \frac{\text{correctPairs}}{\text{totalPairs}} \times \text{pointsWeight},\; 2 \right)$$
   - La bandera `is_correct` solo es `true` si $\text{correctPairs} === \text{totalPairs}$.
2. **Cortocircuito Preventivo para Respuestas Abiertas/Código en Blanco:**
   - En `AttemptService::gradeWithAi()`, antes de invocar la interfaz de IA, se valida si la respuesta entregada carece de contenido:
     ```php
     if (trim((string) ($studentAnswer ?? '')) === '') {
         return [
             'is_correct' => false,
             'points_awarded' => 0.0,
             'ai_meets_goal' => false,
             'ai_feedback' => 'Reactivo sin respuesta; no se invoca evaluación asistida.',
         ];
     }
     ```
   - Esto asigna `0.0` puntos inmediatamente con cero llamadas a la API de IA.
3. **Retención de Calificación Asistida (`RF-EVA-009`):**
   - Las sugerencias de IA en reactivos cualitativos se registran con `teacher_recoteje_score = null`, manteniendo en la interfaz estudiantil el estado *"Pendiente de evaluación docente"* hasta que el profesor confirme o ajuste la nota en la plataforma web.

---

## 3. Consecuencias
- **Positivas:** Calificación justa y pedagógica en relación de columnas; ahorro total de costos/créditos de IA en reactivos vacíos; cumplimiento riguroso de `RF-EVA-002` y `RF-EVA-008`.
- **Certificación:** Validadas ambas reglas mediante pruebas Feature automatizadas en `BackendArchitectureAndSecurityTest.php`.
