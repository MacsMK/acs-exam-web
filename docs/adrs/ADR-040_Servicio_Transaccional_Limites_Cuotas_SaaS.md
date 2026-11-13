# ADR-040 — Servicio Transaccional de Límites y Cuotas SaaS y Regla Freemium Acumulada (`RF-SUS-001..015`, `RF-SUS-010`, `RW-01..04`, `RNF-SEG-003`)

> **Estado:** Aceptado  
> **Fecha:** 08 de Noviembre de 2026  
> **Decisores:** `normative-compliance-auditor`, `architecture-quality-auditor`, `security-vulnerability-auditor`, `backend-laravel-specialist`, `qa-testing-engineer`  
> **Normativa de Referencia:** Manual `MPW-V2` (§3.8.1, §5.2.1, §6.6, §6.9, Tabla 8, Tabla 17), Manual `MC-V2`, `RF-SUS-001` a `RF-SUS-015`, `RF-SUS-010`, `RW-01..04`, `RNF-SEG-003`, `RNF-SEG-004`

---

## 1. Contexto y Problemática

1. **Ausencia de Validación Server-Side de Cuotas:** Los controladores de grupos, banco de reactivos y exámenes permitían la creación ilimitada de recursos sin verificar los topes contratados en `acs_subscription_packages`, violando los requerimientos `RF-SUS-007` y `RNF-SEG-003` (la comprobación debe residir en el servidor y no depender de la interfaz).
2. **Fraude de Cuota en Prueba Freemium (`RF-SUS-010`):** Los requerimientos comerciales establecen que un docente sin suscripción de pago tiene derecho a una prueba gratuita de **1 grupo escolar y 1 examen**. Sin una regla de cómputo acumulativo histórico, un usuario malintencionado podría crear un grupo, cerrarlo o borrarlo, y crear uno nuevo indefinidamente burlando el límite comercial.
3. **Manejo Descentralizado de Excepciones:** Se requería una excepción de dominio unificada que respondiera adecuadamente con `HTTP 403 Forbidden` en la API v1 (móvil Flutter) y con redirección `HTTP 422 / back()->withErrors()` en la interfaz web de Laravel Blade.

---

## 2. Decisión de Arquitectura

### 2.1. Creación de `SubscriptionLimitService` ([SubscriptionLimitService.php](file:///home/laambws/Escritorio/acs-exam-web/app/Services/SubscriptionLimitService.php))
Se implementó un servicio de dominio desacoplado que centraliza las reglas de negocio de las cotas SaaS:
- `ensureCanCreateGroup(User $teacher)`
- `ensureCanCreateQuestions(User $teacher, int $quantity = 1)`
- `ensureCanCreateExam(User $teacher)`
- `ensureCanPublishExam(User $teacher, Exam $exam)`
- `ensureCanEnrollStudent(Group $group)`
- `resolveActivePackage(User $teacher): ?SubscriptionPackage`

### 2.2. Implementación de la Regla de Oro Freemium Acumulada (`RF-SUS-010`)
Para usuarios sin suscripción activa o con estado de pago no completado:
- **Grupos:** Se evalúa el conteo histórico total mediante `Group::withoutGlobalScopes()->where('teacher_id', $teacher->id)->count()`. Si el conteo acumulativo es $\ge 1$, se bloquea la creación de cualquier grupo adicional, indicando que el beneficio de prueba no se restablece al cerrar o eliminar entidades.
- **Exámenes:** Se evalúa el conteo histórico total mediante `Exam::withoutGlobalScopes()->where('teacher_id', $teacher->id)->count()`. Si el conteo acumulativo es $\ge 1$, se bloquea la creación de nuevos borradores.
- **Banco de Reactivos:** Tope máximo de 50 reactivos en cuenta gratuita.
- **Alumnos por Grupo:** Capacidad máxima de 40 estudiantes por grupo escolar en prueba freemium.

### 2.3. Matriz Normativa de Cuotas para Planes de Pago (`Tabla 8`)
Cuando el docente cuenta con una suscripción activa (`payment_status === 'paid'` y no vencida):
- **Básico ($299/mes):** Máximo 3 grupos activos (`is_closed = false`), 5,000 preguntas en banco, 5 exámenes publicados simultáneos y 40 alumnos por grupo.
- **Avanzado ($599/mes):** Máximo 10 grupos activos, 10,000 preguntas en banco, 25 exámenes publicados simultáneos y 120 alumnos por grupo.
- **Institucional / Pro ($999/mes):** Grupos ilimitados (`null`), preguntas ilimitadas (`null`), exámenes publicados ilimitados (`null`) y 500 alumnos por grupo.
- **Suscripción Suspendida:** Si `payment_status === 'suspended'`, se bloquea toda operación transaccional hasta que se regularice el pago.

### 2.4. Excepción de Dominio: `SubscriptionLimitExceededException`
Se creó [`App\Exceptions\SubscriptionLimitExceededException`](file:///home/laambws/Escritorio/acs-exam-web/app/Exceptions/SubscriptionLimitExceededException.php) que implementa `HttpExceptionInterface`:
- **Peticiones API (JSON):** Devuelve respuesta tipada `ApiResponse::error($message, 403, [...])` con metadatos del tipo de cuota y uso actual para que la app móvil Flutter pueda sugerir la actualización de plan.
- **Peticiones Web:** Redirige `back()->withInput()->withErrors(['subscription' => $message])`.

### 2.5. Puntos de Intercepción en la Capa de Servicios
El servicio se inyecta en:
1. [`GroupEnrollmentService::createGroup`](file:///home/laambws/Escritorio/acs-exam-web/app/Services/GroupEnrollmentService.php)
2. [`GroupEnrollmentService::joinByFolio`](file:///home/laambws/Escritorio/acs-exam-web/app/Services/GroupEnrollmentService.php)
3. [`QuestionBankService::createQuestion`](file:///home/laambws/Escritorio/acs-exam-web/app/Services/QuestionBankService.php)
4. [`QuestionBankService::importBatchFromCsv`](file:///home/laambws/Escritorio/acs-exam-web/app/Services/QuestionBankService.php)
5. [`ExamController::store`](file:///home/laambws/Escritorio/acs-exam-web/app/Http/Controllers/ExamController.php)
6. [`ExamController::publish`](file:///home/laambws/Escritorio/acs-exam-web/app/Http/Controllers/ExamController.php)

---

## 3. Consecuencias

### Positivas
- Cumplimiento pleno de `RF-SUS-001..015`, `RF-SUS-010`, `RW-01..04`, `RNF-SEG-003` y `RNF-SEG-004`.
- Protección infranqueable contra abusos del nivel freemium.
- Cobertura completa de pruebas unitarias y de integración en [`SubscriptionLimitServiceTest`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/SubscriptionLimitServiceTest.php).

---

## 4. Actualización de Alineación (Anillo 2 — Bloqueo Pesimista TOCTOU y Cuotas en Duplicación/Lote)
- **Eliminación de condición de carrera TOCTOU (`[ALTA-03]`):** `SubscriptionLimitService::ensureCanCreateQuestionsLocked()` adquiere bloqueo `lockForUpdate()` sobre la fila `User` del docente dentro de la transacción de base de datos antes de contabilizar y validar la cuota disponible.
- **Regla diferenciada Freemium vs Planes de Pago:** En plan Freemium (`$package === null`, tope 50) se mantiene el conteo histórico (`withoutGlobalScopes()`) incluyendo borrados lógicos para evitar el reinicio de cuota; en planes de pago (`$package !== null`) se contabilizan únicamente reactivos activos (`whereNull('deleted_at')`).
- **Nuevos puntos de intercepción y resumen KPI:** Se aplicó verificación de cuota en `QuestionBankService::duplicateQuestion()`, `createQuestionBatch()` y `commitBatchImport()`, y se expuso `getQuestionQuotaSummary(User $teacher)` para los indicadores de capacidad en el Banco de Reactivos.
