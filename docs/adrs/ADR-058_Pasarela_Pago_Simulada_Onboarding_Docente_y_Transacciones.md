# ADR-058 — Pasarela de Pago Simulada Dual, Onboarding Post-Verificación Docente y Transacciones SaaS

- **Fecha:** 18 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen C` (Evolución de `ADR-045` y `ADR-057` + Extensión Normativa a Manuales Oficiales `.docx`)
- **Afecta a:** `database/migrations/2026_11_18_000001_create_payment_transactions_and_onboarding_column.php`, `app/Models/PaymentTransaction.php`, `app/Models/TeacherVerification.php`, `app/Models/User.php`, `app/Contracts/PaymentGatewayContract.php`, `app/Services/Payment/SimulatedPaymentGateway.php`, `app/Services/TeacherSubscriptionService.php`, `app/Services/TeacherVerificationService.php`, `app/Http/Middleware/EnsureTeacherPlanOnboarded.php`, `app/Http/Requests/ProcessSubscriptionCheckoutRequest.php`, `app/Http/Controllers/TeacherSubscriptionController.php`, `app/Http/Controllers/TeacherVerificationController.php`, `app/Http/Controllers/DashboardController.php`, `app/Providers/AppServiceProvider.php`, `config/services.php`, `bootstrap/app.php`, `routes/web.php`, `.env.example`

---

## 1. Contexto y Problema

Tras establecer en `ADR-045` el autoservicio docente con contacto comercial por WhatsApp y en `ADR-057` la verificación híbrida de identidad docente (`EnsureTeacherIsVerified`), persistían tres brechas funcionales y de experiencia de usuario en el ciclo comercial SaaS (`RF-SUS-008`, `RF-SUS-013`, `RF-SUS-015`, `RF-ADM-005`, `RF-ADM-006`, `RF-ADM-014`):

1. **Ausencia de onboarding post-verificación:** Un docente recién aprobado mediante código institucional o dictamen administrativo ingresaba directamente al dashboard vacío sin elegir explícitamente entre iniciar la Prueba Gratuita (Freemium, `RF-SUS-008`) o contratar un paquete de pago (`Basic`, `Advanced`, `Pro`).
2. **Fricción por activación exclusivamente manual:** Para demostraciones académicas y pruebas integrales de autoservicio (`RF-SUS-013`), depender únicamente de WhatsApp y asignación manual en `/admin/docentes` impedía probar la activación instantánea de planes, renovación por tarjeta o SPEI, manejo de pagos rechazados y emisión de comprobantes.
3. **Falta de trazabilidad financiera auditable:** No existía un registro histórico de intentos de cobro aprobados o fallidos (`acs_payment_transactions`) con folio único, método de pago e instrumento enmascarado sin exposición de datos sensibles de tarjeta (PAN completo ni CVV).

---

## 2. Decisión de Arquitectura

Se adopta una **Pasarela de Pago Simulada Dual Desacoplada** y un **Flujo de Onboarding Post-Verificación** gobernado por `TeacherSubscriptionService` y la compuerta `EnsureTeacherPlanOnboarded`:

1. **Contrato Enchufable de Pasarela (`PaymentGatewayContract` + `SimulatedPaymentGateway`):**
   - Se define `App\Contracts\PaymentGatewayContract::process(SubscriptionPackage $package, User $teacher, array $paymentData): array`, enlazado en `AppServiceProvider::register()` a partir de `config('services.payment_gateway.driver', 'simulated')` (`PAYMENT_GATEWAY_DRIVER=simulated` en `.env.example`).
   - `SimulatedPaymentGateway` soporta dos métodos sin cobro real ni dependencias externas:
     - **Tarjeta (`card`):** Limpia separadores, extrae únicamente `card_last4`, detecta la franquicia (`visa`, `mastercard`, `amex`, `card`), valida dígito verificador mediante **algoritmo de Luhn** y aplica reglas deterministas de demostración (`last4 === '0002'` o `cvv === '000'` → `insufficient_funds`; `last4 === '0009'` → `card_declined`; Luhn inválido → `invalid_card`; otros válidos → `approved: true` con código `SIM-XXXXXX`). **Jamás** persiste ni retorna en `metadata` el PAN completo ni el CVV.
     - **SPEI instantáneo (`spei`):** Valida banco emisor y titular contra la CLABE destino institucional `646180110400000018` (`SIMULATED_CLABE`). Si el nombre del titular contiene `RECHAZO`, retorna `approved: false` (`spei_rejected`); en caso contrario aprueba de inmediato generando `spei_tracking_key` (`SPEI-YYYYMMDD-XXXXXXXX`).
   - El contacto comercial por WhatsApp (`ADR-045`) se conserva como canal asistido complementario.

2. **Onboarding Post-Verificación con Prueba Gratuita (`RF-SUS-008`):**
   - La columna `plan_onboarding_completed_at` en `acs_teacher_verifications` (excluida de `$fillable` y mutada únicamente con `forceFill()`) controla si el docente verificado ya eligió plan o prueba gratuita.
   - `EnsureTeacherPlanOnboarded` (alias `teacher.plan_onboarded` en `bootstrap/app.php`) protege las rutas operativas web docentes (`groups.*`, `questions.*`, `exams.*`, `teacher.reviews.*`, `teacher.gradebook.*`, `teacher.ai-credits.*`, `teacher.profile.*`), manteniendo accesibles `teacher.subscription.*`, `teacher.verification.*`, `auth.logout` y toda la superficie `/api/v1/*`.
   - `DashboardController::__invoke()` y `TeacherVerificationController::redeemCode()` redirigen automáticamente al docente verificado con `needsPlanOnboarding() === true` hacia `route('teacher.subscription.index', ['onboarding' => 1])`.
   - `TeacherSubscriptionService::activateFreeTrialOnboarding()` completa el onboarding bajo `DB::transaction()` con `lockForUpdate()` sobre `User` y `TeacherVerification` y registra `AuditLog` (`subscription.freemium_selected`).

3. **Persistencia Transaccional de Pagos Aprobados y Fallidos (`acs_payment_transactions`):**
   - `TeacherSubscriptionService::processInstantCheckout()` invoca `PaymentGatewayContract::process()` **fuera** de `DB::transaction()` para evitar retener bloqueos SQL ante futuro I/O HTTP externo.
   - Dentro de `DB::transaction()`, bloquea `User::lockForUpdate()`, genera el folio único `ACS-PAY-YYYYMMDD-XXXXXX` con `random_int` y cota máxima de `20` reintentos (`MAX_REFERENCE_ATTEMPTS`, `MPW-V2 §5.2.1`), y persiste siempre la fila en `acs_payment_transactions`:
     - Si es **aprobado (`paid`)**: actualiza o crea la fila en `acs_teacher_subscriptions` (`payment_status = 'paid'`, `starts_at = now()`, `expires_at = now()->addMonth()`, `suspended_at = null`), registra `PaymentTransaction` (`STATUS_PAID`), marca `plan_onboarding_completed_at` si seguía `null`, registra `AuditLog` (`subscription.payment_succeeded`) y envía recibo por correo vía `Mail::raw()` fuera de la transacción.
     - Si es **rechazado (`failed`)**: persiste `PaymentTransaction` (`STATUS_FAILED`, `failure_code`, `failure_message`) y `AuditLog` (`subscription.payment_failed`) sin lanzar excepción que revierta el historial (`RF-SUS-015` / `RF-ADM-007`), permitiendo al controlador informar el error en pantalla sin conservar datos sensibles en `withInput()`.
   - En `TeacherVerificationService::getAdminTeachersIndexData()`, se precarga `'latestPaymentTransaction.package'` para evitar `LazyLoadingViolationException` en `/admin/docentes` (`RF-ADM-005/006/014`).

4. **Validación Estricta en Form Request (`ProcessSubscriptionCheckoutRequest`):**
   - Cero `$request->validate()` inline en `TeacherSubscriptionController` (`MPW-V2 §6.3`).
   - `prepareForValidation()` normaliza separadores de tarjeta y espacios; `withValidator()` valida Luhn, vigencia mensual `MM/AA` no vencida y longitud de CVV diferenciada (`4` dígitos para Amex `34`/`37`, `3` dígitos para el resto); `messages()` adopta íntegramente `MPW-V2 Tabla 17` en español sin punto final.

---

## 3. Matriz de Seguridad y Cumplimiento Normativo

| Riesgo / Amenaza | Clasificación | Control Implementado | Evidencia |
| :--- | :--- | :--- | :--- |
| Exposición o almacenamiento de PAN completo o CVV | PCI-DSS / LFPDPPP | Solo se persisten `card_brand`, `card_last4` y `card_holder_name`; `withInput($request->except(['card_number', 'card_cvv', 'card_expiry']))`; cero PAN/CVV en `metadata` y `AuditLog`. | `SimulatedPaymentGateway.php`, `TeacherSubscriptionService.php`, `TeacherSubscriptionController.php` |
| Manipulación de estado de onboarding por Mass Assignment | OWASP A01 / `RNF-SEG-008` | `plan_onboarding_completed_at` fuera de `TeacherVerification::$fillable`; mutación exclusiva con `forceFill()` dentro de `DB::transaction()`. | `TeacherVerification.php`, `TeacherSubscriptionService.php` |
| Bypass de acreditación o acceso de no docentes al checkout | OWASP A01 | Rutas protegidas con `['role:teacher', 'teacher.verified']`, `authorize()` en Form Request y `abort_unless($teacher->role === 'teacher' && $teacher->isVerifiedTeacher(), 403)` en servicio. | `routes/web.php`, `ProcessSubscriptionCheckoutRequest.php`, `TeacherSubscriptionService.php` |
| Fuerza bruta / abuso en endpoint de cobro | OWASP A04 / A07 | `throttle:15,1` en `POST /docente/suscripcion/checkout/{package}` y folio único acotado (`MAX_REFERENCE_ATTEMPTS = 20`). | `routes/web.php`, `TeacherSubscriptionService.php` |
| Regresión de Lazy Loading (N+1) en panel SuperAdmin | `MPW-V2 §5.1` | Eager loading `'latestPaymentTransaction.package'` en `getAdminTeachersIndexData()`. | `TeacherVerificationService.php` |

---

## 4. Guía Exacta de Actualización para Manuales Oficiales (`.docx`) — Dictamen C

Corresponde emitir **Dictamen C** y sincronizar los artefactos en `07_Manuales_Oficiales_V2/`:

### 4.1 Mapa de Secciones y Tablas por Documento Oficial

| Manual / Artefacto Oficial | Sección / Tabla Exacta | Actualización Requerida |
| :--- | :--- | :--- |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`§6.3` / `§6.6` Contratos, Servicios y Middlewares** | Documentar `PaymentGatewayContract` (`PAYMENT_GATEWAY_DRIVER`), `SimulatedPaymentGateway`, `TeacherSubscriptionService`, el middleware `EnsureTeacherPlanOnboarded` (`teacher.plan_onboarded`) y la **Subsección 4.2** de rutas Web. |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`Tabla 7` Catálogo de Form Requests y `Tabla 17` Mensajes** | Incorporar `ProcessSubscriptionCheckoutRequest` en `Tabla 7` y agregar los mensajes exactos de la **Subsección 4.3** en `Tabla 17` (en español sin punto final). |
| **`Manual de Calidad (MC-V2).docx`** | **Control de Versiones y Matriz de Seguridad** | Registrar `ADR-058`, documentar la política de no persistencia de PAN/CVV, validación Luhn dual (cliente/servidor) y trazabilidad en `acs_payment_transactions` + `acs_audit_logs`. |
| **`Manual de Base de Datos (MBD-V4).docx`** | **Diccionario de Datos y Diagrama ER** | Incorporar la columna `plan_onboarding_completed_at TIMESTAMP NULL` en `acs_teacher_verifications` y documentar la tabla `acs_payment_transactions` (migración `2026_11_18_000001`). |
| **`DE-01` / Matriz de 226 Requerimientos (`.xlsx`)** | **`RF-SUS-008`, `RF-SUS-013`, `RF-SUS-015`, `RF-ADM-005/006/014`** | Vincular `RF-SUS-008` al onboarding post-verificación, `RF-SUS-013/015` al checkout dual simulado con historial de intentos, y `RF-ADM-005/006/014` al seguimiento de folios desde `/admin/docentes`. |

### 4.2 Catálogo Exacto de Rutas Web (`ADR-058`) — Listo para `MPW-V2`

| Método | URI Exacta | Nombre de Ruta | Controlador y Método | Middlewares y Restricciones |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/docente/suscripcion` | `teacher.subscription.index` | `TeacherSubscriptionController@index` | `web`, `auth`, `role:teacher`, `teacher.verified` |
| `GET` | `/docente/suscripcion/checkout/{package}` | `teacher.subscription.checkout` | `TeacherSubscriptionController@checkout` | `web`, `auth`, `role:teacher`, `teacher.verified`, `whereNumber('package')` |
| `POST` | `/docente/suscripcion/checkout/{package}` | `teacher.subscription.pay` | `TeacherSubscriptionController@processPayment` | `web`, `auth`, `role:teacher`, `teacher.verified`, `whereNumber('package')`, `throttle:15,1` |
| `POST` | `/docente/suscripcion/prueba-gratuita` | `teacher.subscription.free-trial` | `TeacherSubscriptionController@startFreeTrial` | `web`, `auth`, `role:teacher`, `teacher.verified` |
| `*` | `/groups*`, `/questions*`, `/exams*`, `/docente/revision`, `/docente/calificaciones`, `/docente/creditos-ia`, `/perfil` | `groups.*`, `questions.*`, `exams.*`, `teacher.*` | Varios controladores operativos docentes | `web`, `auth`, `role:teacher`, `teacher.verified`, **`teacher.plan_onboarded`** |

### 4.3 Catálogo Exacto de Mensajes Normativos (`Tabla 17`) — `ProcessSubscriptionCheckoutRequest`

| Campo y Regla | Mensaje Exacto en Español (Sin Punto Final) |
| :--- | :--- |
| `payment_method.required` | `El campo método de pago es obligatorio` |
| `payment_method.string` | `El método de pago no tiene un formato válido` |
| `payment_method.in` | `La opción seleccionada en método de pago no es válida` |
| `card_holder_name.required_if` | `El campo nombre del titular es obligatorio` |
| `card_holder_name.string` | `El nombre del titular no tiene un formato válido` |
| `card_holder_name.min` | `El nombre del titular debe tener al menos 3 caracteres` |
| `card_holder_name.max` | `El nombre del titular no debe exceder los 120 caracteres` |
| `card_number.required_if` | `El campo número de tarjeta es obligatorio` |
| `card_number.string` | `El número de tarjeta no tiene un formato válido` |
| `card_number.regex` | `El número de tarjeta debe contener entre 13 y 19 dígitos` |
| `card_number` (`withValidator` Luhn) | `El número de tarjeta no es válido según la verificación bancaria` |
| `card_expiry.required_if` | `El campo fecha de vencimiento es obligatorio` |
| `card_expiry.string` | `La fecha de vencimiento no tiene un formato válido` |
| `card_expiry.regex` | `La fecha de vencimiento debe tener el formato MM/AA` |
| `card_expiry` (`withValidator` expirada) | `La tarjeta ingresada se encuentra vencida` |
| `card_cvv.required_if` | `El campo código de seguridad (CVV) es obligatorio` |
| `card_cvv.string` | `El código de seguridad (CVV) no tiene un formato válido` |
| `card_cvv.regex` | `El código de seguridad (CVV) debe contener 3 o 4 dígitos` |
| `card_cvv` (`withValidator` Amex 4 dígitos) | `El código de seguridad (CVV) debe contener 4 dígitos para tarjetas American Express` |
| `card_cvv` (`withValidator` estándar 3 dígitos) | `El código de seguridad (CVV) debe contener 3 dígitos` |
| `spei_sender_bank.required_if` | `El campo banco emisor es obligatorio` |
| `spei_sender_bank.string` | `El banco emisor no tiene un formato válido` |
| `spei_sender_bank.max` | `El banco emisor no debe exceder los 80 caracteres` |
| `spei_account_holder.required_if` | `El campo titular de la cuenta emisora es obligatorio` |
| `spei_account_holder.string` | `El titular de la cuenta emisora no tiene un formato válido` |
| `spei_account_holder.min` | `El titular de la cuenta emisora debe tener al menos 3 caracteres` |
| `spei_account_holder.max` | `El titular de la cuenta emisora no debe exceder los 120 caracteres` |

---

## 5. Consecuencias

- **Autoservicio completo de principio a fin:** Un docente verificado transita de forma guiada desde su acreditación hacia la selección de Prueba Gratuita o pago instantáneo con activación inmediata de su paquete en `acs_teacher_subscriptions`.
- **Trazabilidad financiera sin riesgos de cumplimiento:** Tanto los cobros exitosos (`paid`) como los rechazos simulados (`failed`) quedan registrados con folio único en `acs_payment_transactions` y bitácora `acs_audit_logs` sin persistir nunca PAN completo ni CVV.
- **Cero impacto en API móvil v1 ni en cuentas existentes:** La compuerta `EnsureTeacherPlanOnboarded` omite rutas `api/*` y JSON, y las cuentas aprobadas previas conservan `plan_onboarding_completed_at` poblado desde la migración.
- **Sustitución directa de proveedor real:** Migrar en producción a Stripe, Conekta o Openpay requiere únicamente crear una nueva implementación de `PaymentGatewayContract` y cambiar `PAYMENT_GATEWAY_DRIVER` en `.env`.
