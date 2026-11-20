# ADR-060 — Capa Bloqueante de Selección de Planes sobre el Dashboard Docente y Normalización de Cuotas Ilimitadas

- **Fecha:** 19 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen C` (Evolución de `ADR-045` y `ADR-058` + Extensión Normativa a Manuales Oficiales `.docx`)
- **Afecta a:** `app/Services/TeacherSubscriptionService.php`, `app/Services/TeacherVerificationService.php`, `app/Http/Controllers/DashboardController.php`, `app/Http/Middleware/EnsureTeacherPlanOnboarded.php`, `app/Http/Controllers/TeacherVerificationController.php`, `app/Http/Controllers/TeacherSubscriptionController.php`, `app/Http/Requests/ProcessSubscriptionCheckoutRequest.php`

---

## 1. Contexto y Problema

Tras implementar en `ADR-058` el onboarding post-verificación docente redirigiendo a `/docente/suscripcion?onboarding=1`, la revisión funcional y normativa de los flujos de contratación (`RF-SUS-001..015`, `RF-SUS-008`, `RF-SUS-013`) identificó cuatro oportunidades de mejora y correcciones obligatorias:

1. **Experiencia desarticulada al ingresar por primera vez:** Redirigir fuera del Dashboard hacia la vista completa de suscripción impedía presentar al docente recién verificado una bienvenida contextual sobre su panel operativo con una capa modal bloqueante de 2 pasos (selección de paquete/prueba gratuita y checkout integrado sin abandonar el Dashboard).
2. **Defecto de cálculo `/ 1` en cuotas ilimitadas del plan Pro:** En `teacher/subscription/index.blade.php`, cuando un paquete institucional (`Pro`) definía `max_groups`, `max_questions`, `max_active_exams` o `ai_requests_limit` en `null`, los cálculos inline en Blade degradaban el denominador a `1` mostrando fracciones erróneas (`/ 1`) en lugar de `Ilimitado`, incumpliendo además la regla de cero lógica de negocio en Blade (`MPW-V2 §5.2.2`).
3. **Brecha de mensaje normativo en `spei_sender_bank.in`:** `ProcessSubscriptionCheckoutRequest::messages()` carecía de la clave explícita `spei_sender_bank.in` en español conforme a `MPW-V2 Tabla 17`, y ante errores de validación desde el overlay necesitaba preservar el paquete elegido y regresar al Dashboard sin perder contexto ni flashear datos sensibles de tarjeta.
4. **Desincronización cuando el Superadministrador asigna un plan pagado:** `TeacherVerificationService::assignSubscriptionPackage()` activaba una suscripción `paid` sin marcar `plan_onboarding_completed_at` en `acs_teacher_verifications`, dejando pendiente el timestamp de onboarding en base de datos.

---

## 2. Decisión de Arquitectura

Se evoluciona `ADR-058` adoptando la **Capa Bloqueante de Selección de Planes en 2 Pasos sobre el Dashboard Docente** (Opción A aprobada) junto con la **normalización server-side de cuotas**:

1. **Overlay Bloqueante Montado sobre el Dashboard (`DashboardController` + `TeacherSubscriptionService`):**
   - `DashboardController::__invoke()` deja de redirigir a `/docente/suscripcion?onboarding=1` cuando `$user->needsPlanOnboarding()` es verdadero. En su lugar, renderiza `dashboard.teacher` con métricas operativas en `0`, colecciones vacías, `'showPlanOnboardingOverlay' => true` y `'onboardingOverlay' => $this->teacherSubscriptionService->getOnboardingOverlayData($user)`.
   - `TeacherSubscriptionService::getOnboardingOverlayData()` entrega en un único arreglo estructurado los paquetes activos ordenados por precio mensual (`packages`), las cotas vigentes de la prueba gratuita (`freemiumLimits`, incluyendo `max_ai_evaluations = 25`), los datos institucionales SPEI (`speiClabe`, `speiBeneficiary`), `whatsappNumber` y `teacher`.
   - `TeacherVerificationController::redeemCode()` redirige directamente a `route('dashboard')` para presentar inmediatamente el overlay de selección tras canjear un código institucional.

2. **Blindaje Server-Side del Middleware `EnsureTeacherPlanOnboarded`:**
   - Cualquier intento de acceso directo a módulos operativos (`groups.*`, `questions.*`, `exams.*`, `teacher.reviews.*`, `teacher.gradebook.*`, `teacher.ai-credits.*`, `teacher.profile.*`) mientras `needsPlanOnboarding() === true` redirige a `route('dashboard')` con mensaje `warning` exigiendo seleccionar y confirmar un paquete o la prueba gratuita.
   - Permanecen libres `dashboard`, `teacher.subscription.*`, `teacher.verification.*`, `privacy.show.public`, `auth.logout` y toda la superficie `/api/v1/*` / JSON.

3. **Checkout Dual Integrado desde el Overlay (`ProcessSubscriptionCheckoutRequest` + `TeacherSubscriptionController`):**
   - `ProcessSubscriptionCheckoutRequest` agrega `'onboarding_overlay' => ['nullable', 'boolean']` y el mensaje normativo `'spei_sender_bank.in' => 'La opción seleccionada en banco emisor no es válida'` (`MPW-V2 Tabla 17`).
   - Al fallar validación desde el overlay (`onboarding_overlay = 1`), `failedValidation()` flashea `onboarding_selected_package_id` en sesión y lanza `ValidationException` redirigiendo a `route('dashboard')`, preservando la exclusión de `card_number`, `card_cvv` y `card_expiry` garantizada por `dontFlash` en `bootstrap/app.php`.
   - `TeacherSubscriptionController::processPayment()` evalúa `$wasOnboarding` y `$fromOverlay` antes del cobro:
     - Si el pago es **aprobado (`STATUS_PAID`)** y provenía de onboarding u overlay, redirige a `route('dashboard')` desbloqueando el panel de inmediato; en renovaciones regulares redirige a `route('teacher.subscription.index')`.
     - Si el pago es **rechazado (`STATUS_FAILED`)** desde el overlay, redirige a `route('dashboard')` con `onboarding_selected_package_id`, input no sensible y error en `payment_gateway` para reintentar en el Paso 2 del overlay.

4. **Normalización Server-Side de Cuotas y Cierre de Onboarding por SuperAdmin:**
   - `TeacherSubscriptionService::getSubscriptionOverview()` precalcula `'quotaMetrics'` (`groups`, `questions`, `exams`, `aiCredits`) y `'aiCreditSummary'`, tratando explícitamente `null` como `is_unlimited => true`, `limit_label => 'Ilimitado'`, `percent => 0` y `variant => 'accent'`, eliminando el bug `/ 1` del plan **Pro** y erradicando cálculos `@php` en Blade.
   - `TeacherVerificationService::assignSubscriptionPackage()` marca automáticamente `plan_onboarding_completed_at = now()` bajo `lockForUpdate()` cuando el Superadministrador asigna un plan con `payment_status === 'paid'`.

---

## 3. Matriz de Seguridad y Cumplimiento Normativo

| Riesgo / Amenaza | Clasificación | Control Implementado | Evidencia |
| :--- | :--- | :--- | :--- |
| Bypass del overlay ocultando el modal en DevTools | OWASP A01 / `RNF-SEG-004` | El Dashboard solo envía métricas en `0` y colecciones vacías mientras `needsPlanOnboarding()` sea verdadero; `EnsureTeacherPlanOnboarded` bloquea server-side todas las rutas operativas. | `DashboardController.php`, `EnsureTeacherPlanOnboarded.php` |
| Fuga de PAN/CVV/vigencia en sesión tras error de validación en overlay | PCI-DSS / LFPDPPP | `bootstrap/app.php` mantiene `dontFlash(['card_number', 'card_cvv', 'card_expiry'])` y `TeacherSubscriptionController` aplica `$request->except([...])` en rechazos de pasarela. | `bootstrap/app.php`, `ProcessSubscriptionCheckoutRequest.php`, `TeacherSubscriptionController.php` |
| Manipulación de `plan_onboarding_completed_at` por Mass Assignment | OWASP A01 / `RNF-SEG-008` | Columna excluida de `$fillable`; actualizada únicamente vía `forceFill()` dentro de `DB::transaction()` con `lockForUpdate()`. | `TeacherVerification.php`, `TeacherSubscriptionService.php`, `TeacherVerificationService.php` |
| Exposición de mensajes de validación en inglés o con puntuación no estándar | `MPW-V2 Tabla 17` | Agregado mensaje exacto en español sin punto final para `spei_sender_bank.in` y `onboarding_overlay.boolean`. | `ProcessSubscriptionCheckoutRequest.php` |
| Regresión de rutas públicas o API móvil v1 | Paridad API v1 / `RF-PRI-001` | `EnsureTeacherPlanOnboarded` excluye `api/*`, JSON, `dashboard`, `teacher.subscription.*`, `teacher.verification.*`, `privacy.show.public` y `auth.logout`. | `EnsureTeacherPlanOnboarded.php` |

---

## 4. Guía Exacta de Actualización para Manuales Oficiales (`.docx`) — Dictamen C

Corresponde emitir **Dictamen C** y actualizar los documentos normativos en `07_Manuales_Oficiales_V2/`:

### 4.1 Mapa de Secciones y Tablas por Documento Oficial

| Manual / Artefacto Oficial | Sección / Tabla Exacta | Actualización Requerida |
| :--- | :--- | :--- |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`§6.6` Middlewares y `§7.9` Panel Docente (Dashboard)** | Actualizar el comportamiento de `EnsureTeacherPlanOnboarded` (redirección a `route('dashboard')` con overlay bloqueante en 2 pasos) y documentar `getOnboardingOverlayData()` y `quotaMetrics` en `TeacherSubscriptionService`. |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`Tabla 17` Mensajes Canónicos de Validación** | Añadir `spei_sender_bank.in` (`La opción seleccionada en banco emisor no es válida`) y `onboarding_overlay.boolean` en `ProcessSubscriptionCheckoutRequest`. |
| **`Manual de Calidad (MC-V2).docx`** | **Control de Versiones y Pruebas de Regresión SaaS** | Registrar `ADR-060`, certificar la erradicación del defecto `/ 1` en cuotas ilimitadas del paquete **Pro** y documentar el blindaje server-side del overlay en Dashboard. |
| **`DE-06_Registro_de_cambios.docx`** | **Bitácora de Cambios Arquitectónicos** | Asentar el cambio de UX/seguridad de `ADR-058` → `ADR-060` (overlay bloqueante de selección de plan sobre el Dashboard y sincronización de `plan_onboarding_completed_at` en asignación administrativa). |

### 4.2 Catálogo Exacto de Mensajes Normativos Adicionales (`Tabla 17`) — `ProcessSubscriptionCheckoutRequest`

| Campo y Regla | Mensaje Exacto en Español (Sin Punto Final) |
| :--- | :--- |
| `spei_sender_bank.in` | `La opción seleccionada en banco emisor no es válida` |
| `onboarding_overlay.boolean` | `El indicador del asistente de suscripción debe ser verdadero o falso` |

---

## 5. Consecuencias

- **Activación inmediata sin fricción:** El docente verificado llega a su Dashboard, elige Prueba Gratuita o paga un plan en el overlay de 2 pasos y queda operando en su panel sin saltos innecesarios entre páginas.
- **Integridad visual y matemática de cuotas:** `quotaMetrics` entrega valores exactos para `Freemium`, `Básico`, `Avanzado`, `Pro` (`Ilimitado`) y cuentas suspendidas, eliminando lógica `@php` en Blade.
- **Consistencia administrativa:** La asignación directa de un plan pagado desde `/admin/docentes` cierra automáticamente el estado de onboarding del docente.
