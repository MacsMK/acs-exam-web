# ADR-056 — Rediseño del Flujo End-to-End de Exámenes Docentes, Asignación Dual y Conversión Base-100 a Escala `0-10`

- **Fecha:** 18 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen B` (Auditoría Anillo 1 `MSA-V1` + Decisiones Funcionales `A1–A6`)
- **Afecta a:** `routes/web.php`, `routes/channels.php`, `app/Models/Exam.php`, `app/Repositories/**`, `app/Http/Requests/Exam*.php`, `app/Http/Requests/TeacherReviewStoreRequest.php`, `app/Http/Controllers/ExamController.php`, `app/Http/Controllers/ExamMonitorController.php`, `app/Http/Controllers/TeacherReviewController.php`, `app/Services/GradeCalculatorService.php`, `app/Services/ExamAssemblyService.php`, `app/Services/AttemptService.php`, `app/Services/StudentResultService.php`, `resources/views/exams/**`, `resources/views/layouts/app.blade.php`

---

## 1. Contexto y Problema

La auditoría integral del módulo de exámenes docentes identificó cuellos de botella estructurales y divergencias entre etapas:
1. **Publicación bloqueada y sin checklist transaccional:** `ExamController::publish()` empleaba `abort_if` sin bloqueo pesimista (`lockForUpdate`), sin validar la suma `100.00`, la presencia de al menos un grupo abierto ni la vigencia futura de `ends_at`, mientras la vista de revisión carecía de formulario real de publicación.
2. **Armado a ciegas y colisiones Copy-On-Write:** No existía inventario en vivo por tipo y materia ni selección manual desde el banco; `generateSectionProposal()` descartaba propuestas previas válidas antes de verificar disponibilidad; `reshuffleQuestion()` (`RW-14`) sobrescribía `points_weight` y `time_limit_seconds`; `acceptProposal()` no expandía linajes COW y usaba `count()` para `sort_order`; y `buildQuestionSequence()` no ordenaba primero por `acs_exam_sections.sort_order`.
3. **Contradicción matemática `0-10` vs base `100.00`:** El configurador exigía sumar `100.00` puntos (con fallo en `step="1"` en `0-100` que bloqueaba decimales como `33.33`), mientras `AttemptService::submitFinal()` persistía puntajes brutos `0..100` en `final_score` y `StudentResultService` evaluaba aprobación con `>= 6.0`.
4. **Visibilidad de resultados y recoteje:** Los modos `'objective_only_pending'` y `'estimated_score'` debían retener `final_score = null` mientras existieran reactivos cualitativos (`open`/`code`) sin recoteje docente, `'hidden'` debía ocultar estrictamente calificación y revisión, y `TeacherReviewController::update()` debía impedir alterar reactivos objetivos y recalcular `pending_ai_count` real.
5. **Tokens CSS en runtime CDN y proctoring pasivo:** El layout maestro mantenía Tailwind CDN (`docs/03 §7`) sin registrar variantes `-dark` (`primary-dark`, `accent-dark`, `danger-dark`, `tech-dark`, `navy`, `warning-dark`), y el monitoreo requería validación estricta de canal `exam.{examId}` (`ctype_digit`) y protocolo agnóstico `ws://`/`wss://`.

---

## 2. Decisión de Arquitectura (`Dictamen B`)

1. **Conversión Automática Base-100 a Escala `0-10` (`GradeCalculatorService::normalizeScoreForExam`):**
   - La configuración del examen siempre pondera reactivos en base `100.00` con precisión decimal `0.01` (`min:0.01|max:100`, suma exacta `100.00 ± 0.01`).
   - Al consolidar `final_score` (tanto en `AttemptService::submitFinal()` como en `TeacherReviewController::update()`), si `$exam->grading_scale === '0-10'` **y** la suma total de pesos del examen es `100.00 ± 0.05`, se normaliza mediante `round($rawPointsEarned / 10.0, 2)`. En escala `0-100` o en *fixtures* sintéticos históricos con sumas distintas de `100.00`, se conserva `round($rawPointsEarned, 2)` para preservar compatibilidad 1:1 con contratos `/api/v1/*` y suites existentes.

2. **Modo Dual de Armado (`Armar`) e Inmutabilidad de Pesos en `RW-14`:**
   - Se habilitan de forma simultánea el **sorteo inteligente con inventario real disponible** (`QuestionRepositoryInterface::getAvailableInventoryForTeacher`) y la **selección manual paginada** (`getAvailableCandidatesPaginated` + `ExamAssemblyService::addManualQuestions`), ambos con exclusión de linajes Copy-On-Write (`ADR-027`).
   - Se incorporan rutas dedicadas para editar/eliminar apartados (`exams.sections.update`, `exams.sections.destroy`), eliminar reactivos (`exams.questions.destroy`) y agregar reactivos manuales (`exams.questions.manual.store`), todas protegidas con `->whereNumber(...)` y `ExamAssemblyService::ensureStructureMutable()`.
   - `reshuffleQuestion()` (`RW-14`) adquiere `lockForUpdate()` y sustituye únicamente `question_id`, preservando `points_weight` y `time_limit_seconds`.
   - `buildQuestionSequence()` con `randomize_question_order = false` ordena primero por `acs_exam_sections.sort_order ASC` y después por `acs_exam_question.sort_order ASC`.

3. **Activación Integral de los 4 Modos de `result_visibility_mode` (`ADR-050`):**
   - `'final_score'`: publica `final_score` inmediatamente cuando no queden evaluaciones pendientes de IA.
   - `'estimated_score'` y `'objective_only_pending'`: retienen `final_score = null` y contabilizan en `pending_ai_count` cualquier respuesta `open` o `code` con `teacher_recoteje_score === null` hasta que el docente concluya el recoteje, manteniendo blindado `ai_feedback` (`ADR-050`).
   - `'hidden'`: `StudentResultService::isPending()` devuelve `true` y `reviewIsAvailable()` devuelve `false` de manera estricta hasta que el docente libere los resultados (`PATCH exams/{exam}/visibilidad-resultados` → `exams.results.release`).

4. **Checklist Pre-Publicación 5/5, Ciclo de Vida y Cuotas SaaS:**
   - `ExamAssemblyService::evaluatePublishReadiness()` verifica: (1) `>= 1` apartado, (2) ningún apartado vacío, (3) suma `100.00 ± 0.01`, (4) `>= 1` grupo abierto (`is_closed = false`), y (5) `ends_at > now()`.
   - `publishExam()` opera de forma idempotente bajo `Exam::lockForUpdate()` y `User::lockForUpdate()`, valida cuota SaaS (`SubscriptionLimitService::ensureCanPublishExam`), lanza `ValidationException` si falla el checklist y despacha notificaciones push exclusivamente en `DB::afterCommit()`.
   - Se formalizan despublicación segura con 0 intentos (`exams.unpublish`), cierre anticipado (`exams.close`) y borrado de borradores sin intentos (`exams.destroy`).

5. **Sincronización de Tokens Tailwind CDN (`A5`) y Proctoring Pasivo (`ADR-043`):**
   - Se mantiene el stack sin compilación Node/Vite (`docs/03 §7`) sincronizando las variantes `-dark` de la paleta `acode-*` en `layouts/app.blade.php` y `app.css`.
   - Se conserva el proctoring pasivo sin biometría (`require_webcam = false` por defecto, `ADR-043`) y se blinda la autorización WebSocket en `routes/channels.php` validando `ctype_digit((string) $examId)`.

---

## 3. Consecuencias y Guía de Actualización Documental (`Dictamen C`)

- **Cero regresiones API v1:** `MobileContractTest`, `StudentResultsTest` y el cliente móvil `acs-exam-mobile` mantienen intactas sus estructuras JSON y escalas esperadas.
- **Actualización pendiente en Manuales Oficiales `.docx` (`07_Manuales_Oficiales_V2/`):**
  1. Documentar el registro sincronizado de variantes `-dark` en la configuración CDN de `acode-*` (`MC-V2`).
  2. Incorporar el flujo de armado dual (sorteo con conteo real + selección manual) y edición/eliminación de apartados (`MPW-V2`).
  3. Especificar la regla matemática de ponderación base `100.00` y su normalización automática `/ 10.0` hacia escala `0-10`.
  4. Documentar las acciones de ciclo de vida post-publicación (despublicar con 0 intentos, cerrar anticipadamente y liberar resultados ocultos).
