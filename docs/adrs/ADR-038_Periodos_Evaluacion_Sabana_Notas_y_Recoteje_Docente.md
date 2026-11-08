# ADR-038 — Periodos de Evaluación, Sábana de Notas Web y Recoteje Docente de IA (`RF-CAL-001..027`, `RF-EVA-009`, `RF-IAR-002`)

> **Estado:** Aceptado  
> **Fecha:** 08 de Noviembre de 2026  
> **Decisores:** `normative-compliance-auditor`, `architecture-quality-auditor`, `backend-laravel-specialist`, `frontend-ui-specialist`  
> **Normativa de Referencia:** Manual `MPW-V2` (§5.2.1, §6.1, §7, §8, Tabla 17, Tabla 20), Manual `MC-V2`, `RF-CAL-001` a `RF-CAL-027`, `RF-EVA-009`, `RF-IAR-002`, `RW-11`, `RW-28..31`

---

## 1. Contexto y Problemática

1. **Gestión Desconectada de Calificaciones:** Aunque existía la estructura de datos `acs_evaluation_periods` y `acs_student_period_grades` en el esquema relacional, no se disponía de controladores ni vistas web para que el docente definiera sus parciales por grupo, configurara ponderaciones porcentuales y capturara rubros manuales (tareas, proyectos, otros) en una sábana de notas consolidada (`RF-CAL-001..005`, `RF-CAL-009`, `RW-28..31`).
2. **Validación Normativa de Ponderaciones:** El requerimiento funcional `RF-CAL-005` exige taxativamente que la suma de ponderaciones de los rubros asignados a un periodo sume exactamente 100% antes de utilizar la fórmula de cálculo.
3. **Fugas de Puntuaciones Provisionales de IA y Falta de Recoteje:** Los requerimientos `RF-IAR-002`, `RF-EVA-009` y `RW-11` exigen que toda respuesta abierta o de programación cuente con una interfaz donde el docente pueda revisar la sugerencia de la IA, modificarla o confirmarla, impidiendo que el alumno visualice estimaciones provisionales sin recoteje definitivo.
4. **Vulnerabilidad de Aislamiento IDOR:** La manipulación de parámetros de ruta (`group_id`, `period_id`, `attempt_id`) requería validación estricta de propiedad docente en el servidor para evitar que un docente acceda a la información académica de otro (`RNF-SEG-004`).

---

## 2. Decisión de Arquitectura

### 2.1. Gestión de Periodos y Validación de Ponderaciones al 100%
- Se implementó [`EvaluationPeriodStoreRequest`](file:///home/laambws/Escritorio/acs-exam-web/app/Http/Requests/EvaluationPeriodStoreRequest.php) implementando el método `withValidator` para certificar en backend que:
  $$\text{exams\_weight} + \text{homework\_weight} + \text{project\_weight} + \text{other\_weight} = 100.0\%$$
- En la vista Blade [`evaluation-periods/index.blade.php`](file:///home/laambws/Escritorio/acs-exam-web/resources/views/evaluation-periods/index.blade.php), se integró un componente reactivo Alpine.js que suma dinámicamente los porcentajes en tiempo real, bloqueando el botón de envío y mostrando alertas visuales si difiere del 100%.

### 2.2. Motor de Cálculo Seguro sin `eval()` ([GradeCalculatorService.php](file:///home/laambws/Escritorio/acs-exam-web/app/Services/GradeCalculatorService.php))
- La fórmula del periodo se almacena de forma estructurada (ej. `exams*0.6+homework*0.2+project*0.1+other*0.1`).
- La evaluación se efectúa estrictamente mediante operaciones de precisión arbitraria `bcmath` (`bcmul`, `bcadd`), erradicando cualquier riesgo de inyección o ejecución arbitraria de código PHP (`eval()`).

### 2.3. Sábana de Notas Web y Captura Masiva Transaccional
- Se implementó [`EvaluationPeriodController`](file:///home/laambws/Escritorio/acs-exam-web/app/Http/Controllers/EvaluationPeriodController.php) con:
  - `gradebook(Group $group, EvaluationPeriod $period)`: Carga optimizada sin N+1 de los estudiantes del grupo, sus calificaciones guardadas y el promedio de intentos reales del alumno en los exámenes del grupo.
  - `saveGradebook(GradebookSaveRequest $request, Group $group, EvaluationPeriod $period)`: Operación envuelta en `DB::transaction()` que actualiza o crea los registros en `acs_student_period_grades` y recalcula la calificación final `final_grade`.
- Representación de ausencia de calificación como «Pendiente» sin sustituir por cero (`RF-CAL-021`).

### 2.4. Módulo de Recoteje Docente de IA ([TeacherReviewController.php](file:///home/laambws/Escritorio/acs-exam-web/app/Http/Controllers/TeacherReviewController.php))
- Interfaz institucional para que el docente audite intentos pendientes (`status = 'submitted'`, `teacher_recoteje_score = null`).
- Permite registrar puntuación definitiva y retroalimentación docente, recalculando `final_score` del intento una vez completada la revisión (`RF-EVA-011`).

---

## 3. Consecuencias

### Positivas
- Cumplimiento pleno de `RF-CAL-001..005`, `RF-CAL-009`, `RF-CAL-012`, `RF-CAL-017`, `RF-CAL-021`, `RF-CAL-027`, `RF-EVA-009`, `RF-IAR-002`.
- Interfaz fluida y responsive construida con el UI Kit A-Code (Tailwind + Alpine.js).
- Cobertura de pruebas unitarias y de integración al 100% en [`EvaluationPeriodGradebookTest`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/EvaluationPeriodGradebookTest.php) y [`TeacherReviewRecotejeTest`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/Exam/TeacherReviewRecotejeTest.php).

### Neutras / A Considerar
- Las sábanas de notas requieren exportación a formato ofimático Excel para descarga local del profesor, abordado en `ADR-036`.
