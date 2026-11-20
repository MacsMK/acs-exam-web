# ADR-059 — Evaluación Asistida por IA Bajo Demanda, Colas de Trabajo, Créditos SaaS y Google Gemini 2.5 Flash

- **Fecha:** 19 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen C` (Evolución de `ADR-002`, `ADR-018`, `ADR-038`, `ADR-041` y `ADR-050` + Extensión Normativa a Manuales Oficiales `.docx`)
- **Afecta a:** `config/services.php`, `.env.example`, `app/Contracts/AiEvaluatorContract.php`, `app/Services/MockAiEvaluator.php`, `app/Services/Ai/GeminiAiEvaluator.php`, `app/Providers/AppServiceProvider.php`, `app/Services/SubscriptionLimitService.php`, `app/Jobs/EvaluateStudentAnswerJob.php`, `app/Services/AiEvaluationService.php`, `app/Services/AttemptService.php`, `app/Services/StudentResultService.php`, `app/Http/Requests/RequestAiEvaluationRequest.php`, `app/Http/Controllers/TeacherReviewController.php`, `app/Http/Controllers/TeacherAiCreditsController.php`, `app/Http/Controllers/AttemptController.php`, `routes/web.php`

---

## 1. Contexto y Problema

Tras introducir la integración inicial de Google Gemini 1.5 Flash (`ADR-041`) y la omisión de IA en respuestas vacías (`ADR-018`), el análisis normativo integral de los requerimientos `RF-IAR-001..014`, `RF-EVA-004..013`, `RNF-PRI-001` y `RNF-CON-002` reveló cinco brechas críticas en la arquitectura de evaluación cualitativa:

1. **Disparo síncrono automático al entregar respuestas (`RF-IAR-001`):** `AttemptService::gradeAnswer()` invocaba al evaluador de IA en el hilo HTTP del estudiante al responder cada pregunta abierta o de código, consumiendo cuota del docente sin autorización explícita, elevando la latencia de aplicación del examen y rompiendo la regla de evaluación bajo demanda controlada por el docente.
2. **Ausencia de contabilidad real de créditos IA (`RF-IAR-010`, `RF-IAR-011`, `RF-IAR-014`):** No existía una bitácora auditable de consumo (`acs_ai_credit_usages`), ni cupos diferenciados por paquete (`Freemium = 25` histórico, `Básico = 200`, `Avanzado = 600`, `Pro = ilimitado`), ni garantía de no descontar créditos ante respuestas degradadas a fallback local (`RF-IAR-011`), ni preferencia persistente para omitir la advertencia de reevaluación (`skip_ai_reevaluation_warning`).
3. **Resiliencia insuficiente y modelo desactualizado (`RNF-CON-002`):** `GeminiAiEvaluator` apuntaba por defecto a `gemini-1.5-flash`, no inyectaba el contexto pedagógico completo del reactivo (rúbrica, lenguaje de programación, código base, instrucciones) y carecía de un patrón *Circuit Breaker* en caché para evitar cascadas de timeouts ante caídas de la API externa.
4. **Falta de trazabilidad inmutable en correcciones docentes (`RF-EVA-013`):** El recoteje docente en `TeacherReviewController::update()` no diferenciaba ni auditaba en `acs_audit_logs` las revisiones iniciales (`teacher.grade_reviewed`) frente a correcciones posteriores (`teacher.grade_corrected`).
5. **Inconsistencia de escala preliminar en `estimated_score` (`ADR-056`):** La vista web `exams/result-summary.blade.php` sumaba `objective_score + ai_estimated_score` en bruto (base 100) sin normalizar a escala `0-10` mediante `GradeCalculatorService::normalizeScoreForExam()`.

---

## 2. Decisión de Arquitectura

Se adopta la **Evaluación Asistida por IA Bajo Demanda Desacoplada por Colas**, respaldada por **Google Gemini 2.5 Flash**, **Circuit Breaker en caché**, **contabilidad transaccional de créditos** y **auditoría normativa completa**:

1. **Evaluación Bajo Demanda (`RF-IAR-001` Opción A):**
   - `AttemptService::gradeAnswer()` deja de invocar a `AiEvaluatorContract::evaluate()` para reactivos `'open'` y `'code'`. En su lugar, `registerQualitativeAnswer()`:
     - Si la respuesta está en blanco (`RF-EVA-008`), persiste `0.0`, `is_correct = false`, `ai_similarity_score = 0.0`, `ai_meets_goal = false`, `ai_evaluated_at = now()`, `ai_is_fallback = false`, `ai_model_used = 'none'` sin consumir IA.
     - Si tiene contenido, la registra con campos de IA en `null` y `points_awarded = 0.0`, contabilizándola como pendiente (`pending_ai_count`) hasta la revisión/recoteje del docente.
   - El docente dispara explícitamente la evaluación desde `/exams/{exam}/revision/{attempt}` de forma individual (`POST .../respuestas/{answer}/evaluar-ia`) o en lote (`POST .../evaluar-ia-lote`).

2. **Contrato Enriquecido, Gemini 2.5 Flash y Circuit Breaker (`RNF-PRI-001`, `RNF-CON-002`):**
   - `AiEvaluatorContract::evaluate(string $studentAnswer, string $referenceAnswer, array $context = []): array` admite contexto estrictamente pedagógico y retorna metadatos `is_fallback` y `model`.
   - `config/services.php` y `.env.example` adoptan `'gemini-2.5-flash'` como modelo predeterminado y agregan `circuit_failure_threshold` (`3`) y `circuit_cooldown_seconds` (`60`).
   - `GeminiAiEvaluator` resuelve `model` y `timeout` dinámicamente desde configuración cuando son `null`, aplica una **lista blanca estricta** (`question_type`, `stem`, `evaluation_rubric`, `programming_language`, `instructions`, `is_find_error`, `starter_code`, `points_weight`) garantizando cero PII (`RNF-PRI-001` LFPDPPP) y opera un *Circuit Breaker* en caché (`ai:gemini:consecutive_failures`, `ai:gemini:circuit_open_until`) que degrada transparentemente a `MockAiEvaluator` (`is_fallback = true`, `model = 'mock-local'`, prefijo `[Degradación por Contingencia] `).

3. **Despacho por Colas y Transaccionalidad Sin Retención de Locks (`MPW-V2 §6.10`):**
   - `App\Jobs\EvaluateStudentAnswerJob` (`ShouldQueue`, `$tries = 2`, `$backoff = [5, 15]`, `$timeout = 30`) delega en `AiEvaluationService::executeEvaluation()`.
   - `AiEvaluationService::executeEvaluation()` ejecuta la llamada HTTP externa **fuera** de `DB::transaction()`. Posteriormente abre transacción con `User::lockForUpdate()` y `StudentAnswer::lockForUpdate()`:
     - Calcula el puntaje sugerido proporcional `round((similarity_score / 100) * points_weight, 2)` y actualiza `points_awarded` únicamente si `teacher_recoteje_score === null`.
     - Recalcula `ai_estimated_score` en `acs_student_attempts` sin alterar `final_score` ni `pending_ai_count` (`RF-EVA-009`).
     - **Regla `RF-IAR-011`:** Solo si `! $isFallback` inserta registro en `acs_ai_credit_usages` (`status = 'consumed'`) y en `acs_audit_logs` (`ai.answer_evaluated` / `ai.answer_reevaluated`). Si hubo fallback local, no se descuenta ningún crédito y el controlador advierte al docente.

4. **Control de Cuotas de Créditos IA (`SubscriptionLimitService`) y Monitor Skinny:**
   - Se incorpora `FREEMIUM_MAX_AI_EVALUATIONS = 25` (histórico acumulado), ciclos mensuales para `Básico (200)` y `Avanzado (600)`, e ilimitado (`null`) para `Pro`.
   - `SubscriptionLimitService::getAiCreditQuotaSummary()` y `ensureCanConsumeAiCredits()` (con `lockForUpdate()` cuando hay transacción activa) bloquean excesos lanzando `SubscriptionLimitExceededException`.
   - `TeacherAiCreditsController` se refactoriza como Skinny Controller delegando íntegramente a `AiEvaluationService::getTeacherAiCreditsOverview()`.

5. **Auditoría de Recoteje (`RF-EVA-013`) y Normalización Preliminar (`StudentResultService`):**
   - `TeacherReviewController::update()` registra en `acs_audit_logs` cada recoteje inicial (`teacher.grade_reviewed`) o corrección posterior (`teacher.grade_corrected`) con `old_values`, `new_values`, motivo, autor, IP y fecha.
   - `StudentResultService::buildWebResultSummary()` centraliza el cálculo y normalización de `preliminaryScore` mediante `GradeCalculatorService::normalizeScoreForExam()` para la vista web `exams/result-summary.blade.php`.

---

## 3. Matriz de Seguridad y Cumplimiento Normativo

| Riesgo / Amenaza | Clasificación | Control Implementado | Evidencia |
| :--- | :--- | :--- | :--- |
| Fuga de datos personales (PII) hacia proveedor de IA | LFPDPPP / `RNF-PRI-001` | Whitelist estricta `ALLOWED_CONTEXT_KEYS` en `GeminiAiEvaluator`; jamás se envía nombre, correo, matrícula ni `student_id`. | `GeminiAiEvaluator.php`, `AiEvaluationService.php` |
| IDOR multi-docente al solicitar evaluación IA de intentos ajenos | OWASP A01 / `RNF-SEG-004` | `RequestAiEvaluationRequest::authorize()` + `abort_unless($exam->teacher_id === $teacher->id, 403)` y validación de pertenencia `exam -> attempt -> answer`. | `RequestAiEvaluationRequest.php`, `AiEvaluationService.php` |
| Carrera TOCTOU y saldo negativo de créditos IA | OWASP A04 / `RNF-SEG-003` | `User::lockForUpdate()` + revalidación `ensureCanConsumeAiCredits()` dentro de `DB::transaction()` antes de insertar en `acs_ai_credit_usages`. | `SubscriptionLimitService.php`, `AiEvaluationService.php` |
| Cobro indebido de créditos cuando falla la API externa | `RF-IAR-011` / `RNF-CON-002` | Bandera `ai_is_fallback`: ante error o circuito abierto no se crea fila en `acs_ai_credit_usages` ni se descuenta saldo. | `GeminiAiEvaluator.php`, `AiEvaluationService.php` |
| Abuso de cuota por peticiones repetidas a endpoints de IA | OWASP A04 / A07 | `throttle:30,1` en evaluación individual y `throttle:10,1` en lote, con restricciones `whereNumber()`. | `routes/web.php` |
| Manipulación no autorizada de `skip_ai_reevaluation_warning` | OWASP A01 / Mass Assignment | Columna fuera de `User::$fillable`; mutación exclusiva con `forceFill()` bajo autorización del docente dueño. | `User.php`, `AiEvaluationService.php` |
| Exposición de `ai_feedback` preliminar al estudiante | `RF-EVA-009` / `ADR-050` | `executeEvaluation()` jamás modifica `final_score` ni `pending_ai_count`; `StudentResultService` omite `ai_feedback`. | `AiEvaluationService.php`, `StudentResultService.php` |

---

## 4. Guía Exacta de Actualización para Manuales Oficiales (`.docx`) — Dictamen C

Corresponde emitir **Dictamen C** y actualizar los documentos normativos en `07_Manuales_Oficiales_V2/`:

### 4.1 Mapa de Secciones y Tablas por Documento Oficial

| Manual / Artefacto Oficial | Sección / Tabla Exacta | Actualización Requerida |
| :--- | :--- | :--- |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`§6.10` Integración de IA y Colas (`Jobs`)** | Actualizar modelo por defecto a `gemini-2.5-flash`, documentar *Circuit Breaker* en caché (`GEMINI_CIRCUIT_FAILURE_THRESHOLD`, `GEMINI_CIRCUIT_COOLDOWN_SECONDS`), `EvaluateStudentAnswerJob`, `AiEvaluationService` y el cambio a disparo bajo demanda (`RF-IAR-001`). |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`Tabla 7` Form Requests, `Tabla 17` Mensajes y Catálogo de Rutas Web** | Registrar `RequestAiEvaluationRequest` con sus mensajes exactos (Subsección 4.3) y las dos rutas `POST` de evaluación IA (Subsección 4.2). |
| **`Manual de Calidad (MC-V2).docx`** | **Control de Versiones, Matriz de Riesgos y Resiliencia** | Incorporar `ADR-059`, política de cero descuento en degradación (`RF-IAR-011`), trazabilidad de recotejes/correcciones (`RF-EVA-013`) y aislamiento PII LFPDPPP. |
| **`Manual de Base de Datos (MBD-V4).docx`** | **Diccionario de Datos y Esquema de Colas/Créditos** | Documentar tablas `jobs`, `failed_jobs` y `acs_ai_credit_usages`, columna `acs_users.skip_ai_reevaluation_warning`, columnas `acs_student_answers.ai_is_fallback` y `ai_model_used`, y cupos `ai_requests_limit` (`200`, `600`, `NULL`). |
| **`DE-01` / `DE-06` y Matriz de 226 Requerimientos (`.xlsx`)** | **`RF-IAR-001..014`, `RF-EVA-004..013`, `RNF-PRI-001`, `RNF-CON-002`** | Registrar el cambio de arquitectura en `DE-06` (folio `ADR-059`) y vincular los RF/RNF de evaluación IA bajo demanda, colas, créditos y auditoría de recoteje. |

### 4.2 Catálogo Exacto de Rutas Web (`ADR-059`) — Listo para `MPW-V2`

| Método | URI Exacta | Nombre de Ruta | Controlador y Método | Middlewares y Restricciones |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `/exams/{exam}/revision/{attempt}/respuestas/{answer}/evaluar-ia` | `exams.revision.evaluate-ai` | `TeacherReviewController@evaluateWithAi` | `web`, `auth`, `role:teacher`, `teacher.verified`, `teacher.plan_onboarded`, `whereNumber(['exam', 'attempt', 'answer'])`, `throttle:30,1` |
| `POST` | `/exams/{exam}/revision/{attempt}/evaluar-ia-lote` | `exams.revision.evaluate-ai-batch` | `TeacherReviewController@evaluateAttemptWithAi` | `web`, `auth`, `role:teacher`, `teacher.verified`, `teacher.plan_onboarded`, `whereNumber(['exam', 'attempt'])`, `throttle:10,1` |

### 4.3 Catálogo Exacto de Mensajes Normativos (`Tabla 17`) — `RequestAiEvaluationRequest`

| Campo y Regla | Mensaje Exacto en Español (Sin Punto Final) |
| :--- | :--- |
| `is_reevaluation.boolean` | `El indicador de reevaluación debe ser verdadero o falso` |
| `dismiss_warning.boolean` | `El indicador para omitir la advertencia debe ser verdadero o falso` |

---

## 5. Consecuencias

- **Control total de costos y créditos por el docente:** Ningún examen dispara consumo de IA al responder; el docente decide qué reactivos o intentos evaluar y conoce su saldo en tiempo real.
- **Portabilidad entre entornos:** Funciona de forma inmediata con `QUEUE_CONNECTION=sync` en desarrollo/demo y escala sin cambios de código a `QUEUE_CONNECTION=database` con `php artisan queue:work` en producción.
- **Cero afectación a contratos `/api/v1/*`:** Los endpoints móviles mantienen intactas sus envolturas JSON y códigos de estado; las respuestas abiertas/código se guardan con menor latencia al no esperar llamadas externas durante el examen.
- **Trazabilidad normativa integral:** Cada consumo real queda en `acs_ai_credit_usages` + `acs_audit_logs`, cada recoteje/corrección en `acs_audit_logs`, y las fallas externas degradan de forma segura sin perjudicar al docente.
