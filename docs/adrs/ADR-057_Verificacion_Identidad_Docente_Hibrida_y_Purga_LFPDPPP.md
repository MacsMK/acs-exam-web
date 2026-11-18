# ADR-057 — Verificación Híbrida de Identidad Docente, Códigos Institucionales y Purga Automática LFPDPPP

- **Fecha:** 18 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen C` (Auditoría de Seguridad/Privacidad + Extensión Normativa a Manuales Oficiales `.docx`)
- **Afecta a:** `database/migrations/2026_11_17_000001_create_teacher_verification_tables.php`, `database/seeders/UserSeeder.php`, `app/Models/User.php`, `app/Models/TeacherVerification.php`, `app/Models/TeacherInvitationCode.php`, `app/Contracts/ProfessionalLicenseVerifierContract.php`, `app/Services/Verification/ManualProfessionalLicenseVerifier.php`, `app/Services/TeacherVerificationService.php`, `app/Services/AuthService.php`, `app/Http/Middleware/EnsureTeacherIsVerified.php`, `app/Http/Requests/RegisterTeacherRequest.php`, `app/Http/Requests/ResubmitTeacherVerificationRequest.php`, `app/Http/Requests/RedeemInvitationCodeRequest.php`, `app/Http/Requests/RejectTeacherVerificationRequest.php`, `app/Http/Requests/StoreInvitationCodeRequest.php`, `app/Http/Requests/SendInvitationCodeEmailRequest.php`, `app/Http/Requests/AssignTeacherPackageRequest.php`, `app/Http/Requests/UpdateTeacherProfileRequest.php`, `app/Http/Controllers/Auth/RegisterController.php`, `app/Http/Controllers/TeacherVerificationController.php`, `app/Http/Controllers/AdminTeacherController.php`, `app/Http/Controllers/AdminInvitationCodeController.php`, `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/TeacherProfileController.php`, `app/Http/Controllers/Api/V1/AuthController.php`, `config/services.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`, `routes/api.php`, `routes/channels.php`, `.env.example`, `resources/views/auth/register-teacher.blade.php`, `resources/views/teacher/verification/status.blade.php`, `resources/views/teacher/profile/index.blade.php`, `resources/views/admin/teachers/index.blade.php`, `resources/views/admin/invitations/index.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`

---

## 1. Contexto y Problema

Durante la consolidación de la seguridad del alta de cuentas docentes (`RF-AUT-001`, `RW-03`, `RW-05`) se identificaron cuatro riesgos estructurales críticos que impedían mantener un registro abierto sin acreditación:

1. **Suplantación de rol docente y abuso de cuotas SaaS:** Cualquier persona (incluyendo alumnos) podía crear una cuenta con rol `teacher` únicamente ingresando nombre, correo personal, teléfono y contraseña, obteniendo acceso inmediato al banco de preguntas, creación de grupos, evaluación asistida por IA y panel operativo docente.
2. **Limitación técnica de la API SEP y riesgo de suplantación por cédula pública:** El Registro Nacional de Profesionistas de la SEP (`cedulaprofesional.sep.gob.mx`) no ofrece una API REST pública gratuita estable sin retos anti-bot/CAPTCHA ni garantiza por sí solo identidad docente activa. Además, las cédulas profesionales en México son datos públicos consultables por nombre: aceptar una cédula válida como prueba única de auto-aprobación permitiría que un tercero copiara la cédula de un profesor real para suplantarlo.
3. **Necesidad de adopción institucional sin fricción:** Instituciones educativas con convenio SaaS requieren incorporar rápidamente a su planta docente sin que el Superadministrador revise manualmente docenas de credenciales en el arranque de semestre.
4. **Minimización de datos personales sensibles (LFPDPPP / `RNF-SEG-007`):** Recabar comprobantes documentales (credenciales institucionales, constancias laborales u horarios oficiales) conlleva responsabilidad legal sobre datos personales; conservarlos indefinidamente en disco público o tras concluir la validación viola el principio de proporcionalidad y conservación limitada de la LFPDPPP.

---

## 2. Decisión de Arquitectura

Se adopta un modelo de **Verificación Híbrida de Identidad Docente en Doble Carril** gobernado transaccionalmente por `TeacherVerificationService` y reforzado con compuerta de acceso `EnsureTeacherIsVerified`:

1. **Doble Carril de Acreditación en Registro con Divulgación Progresiva / Ley de Hick (`RF-AUT-001`, `MPW-V2 §7.8.2`):**
   - **Asistente Web en 2 pasos (`/register/docente`):** Para minimizar la carga cognitiva (Ley de Hick), el Paso 1 solicita únicamente los datos básicos de acceso + Aviso de Privacidad (botón `Continuar`), y el Paso 2 solicita exclusivamente la acreditación docente (botones `Volver` y `Enviar solicitud`).
   - **Carril A — Código de Invitación Institucional (Aprobación Expedita y Distribución):** El Superadministrador emite códigos únicos de alta entropía (`PREFIX-XXXX-XXXX`, generados con `random_int` sobre alfabeto no ambiguo `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` y cota acotada de `30` intentos conforme a `MPW-V2 §5.2.1`) con límite de usos (`max_uses`) y vigencia opcional (`expires_at`). Desde `/admin/invitaciones` puede copiar el código, copiar un **enlace directo de registro prellenado** (`/register/docente?codigo=XXXX`) o enviarlo por correo electrónico (`admin.invitations.send-email`). Al registrarse o canjear posteriormente un código válido, el sistema aplica orden determinista de bloqueos pesimistas (`User` → `TeacherVerification` → `TeacherInvitationCode` con `lockForUpdate()`), revalida estado no aprobado y vigencia/cupo (`isUsable()`), incrementa `uses_count` atómicamente y marca `acs_users.teacher_verification_status = 'approved'` y `acs_teacher_verifications.status = 'approved'` con método `invitation_code`.
   - **Carril B — Revisión Documental Privada (Default sin código):** Cuando el aspirante no cuenta con código institucional, proporciona en el Paso 2 únicamente `institution_name` y su comprobante (`PDF`, `JPG`, `JPEG` o `PNG` `<= 5 MB`), manteniendo `professional_license` (7 a 8 dígitos) bajo divulgación progresiva opcional y eliminando del formulario inicial los selectores de `educational_level` y `credential_type` (conservados como `nullable` en esquema/API para compatibilidad). La cuenta inicia en `teacher_verification_status = 'pending'`, el archivo valida estrictamente su MIME real vía `finfo` (`$document->getMimeType()`, sin fallback a cabeceras o extensiones de cliente) y se almacena exclusivamente en el disco privado `local` (`storage/app/private/teacher-verifications/{user_id}/{uuid}.{ext}`) con limpieza automática ante cualquier fallo transaccional.

2. **Indicador Informativo de Dominio Institucional y Contrato Enchufable SEP:**
   - `TeacherVerificationService::isInstitutionalEmail()` evalúa sufijos educativos/gubernamentales (`.edu.mx`, `.edu`, `.gob.mx`, `.ipn.mx`, `.unam.mx`, `.tecnm.mx`, `.tec.mx`, entre otros) únicamente como señal informativa (`is_institutional_email`), nunca como bypass de verificación.
   - Se define `App\Contracts\ProfessionalLicenseVerifierContract` (enlazado en `AppServiceProvider` mediante `config('services.sep_cedula.driver', 'manual')` y parametrizado en `.env.example` con `SEP_CEDULA_*`) con implementación por defecto `ManualProfessionalLicenseVerifier`. En `resubmit()`, la invocación al verificador SEP se ejecuta **fuera** de `DB::transaction()` para no retener bloqueos SQL ante futuro I/O HTTP externo.

3. **Dictamen Estricto, Streaming Privado Endurecido y Purga Total LFPDPPP:**
   - Todos los métodos administrativos del servicio verifican en profundidad `$admin->role === 'super_admin'` (`403`) y `approve()`/`reject()` bloquean primero `User` y `TeacherVerification` exigiendo estrictamente `status === 'pending'` (impidiendo dictaminar solicitudes ya resueltas o colisiones concurrentes).
   - `TeacherVerificationService::resolveAdminDocumentStream()` y `AdminTeacherController::document()` sirven comprobantes únicamente a `super_admin` validando prefijo estricto `teacher-verifications/{$teacher->id}/`, ausencia de `..`/`\`/`\0`, existencia en disco privado y MIME **real en disco** (`Storage::disk('local')->mimeType($path)`) contra la allowlist (`application/pdf`, `image/jpeg`, `image/png`) con rechazo HTTP `415` ante cualquier discrepancia (nunca se hace fallback al MIME guardado en BD ni al nombre original del cliente). Cada visualización registra `AuditLog` (`teacher.verification_document_viewed`) y responde con `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Cache-Control: no-store, no-cache, must-revalidate, private` y `Content-Security-Policy: default-src 'none'; frame-ancestors 'self'; sandbox`.
   - Al **aprobar**, **rechazar** o **canjear un código institucional** posteriormente, el sistema limpia `document_path`, `document_original_name` y `document_mime_type` a `null`, registra `document_purged_at = now()` y verifica explícitamente el borrado físico (`$deleted = $disk->delete($path)` y `! $disk->exists($path)`), emitiendo `Log::error(...)` ante cualquier fallo de disco.

4. **Compuerta de Acceso Web, API v1 y WebSockets Reverb (`EnsureTeacherIsVerified`):**
   - El alias `teacher.verified` protege todo el grupo operativo docente en `routes/web.php` y los grupos de negocio (`groups/*`, `exams/*`, `grades/*`) en `routes/api.php`.
   - En `/api/v1/*`, el middleware es transparente para alumnos y docentes aprobados, mantiene libres `auth/me`, `auth/logout`, `privacy-notice*` y `devices`, y responde HTTP `403` uniforme vía `ApiResponse::error` diferenciando el mensaje según `pending` vs `rejected` junto con `errors.verification_status`.
   - En `routes/channels.php`, el canal privado `exam.{examId}` exige `$user->role === 'teacher' && $user->is_active && $user->isVerifiedTeacher()`.

5. **Eliminación de Oráculo, Skinny Controllers y Erradicación de Validaciones Inline:**
   - En `RegisterTeacherRequest::withValidator()`, la verificación de `invitation_code` se omite si existen otros errores en el formulario (`$validator->errors()->isNotEmpty()`), cerrando el oráculo de consulta de códigos sin efectos secundarios.
   - Se incorporan `AssignTeacherPackageRequest` y `UpdateTeacherProfileRequest` eliminando el `$request->validate()` inline en `AdminTeacherController` y `TeacherProfileController`, y delegando consultas complejas/transacciones a `TeacherVerificationService`.

---

## 3. Matriz de Seguridad (OWASP Top 10, `RNF-SEG-007/008` y LFPDPPP)

| Riesgo / Amenaza | Clasificación Normativa | Control Técnico Implementado | Evidencia en Código |
| :--- | :--- | :--- | :--- |
| Escalación de privilegios por Mass Assignment (`teacher_verification_status`) | OWASP A01 / `RNF-SEG-008` | Columna excluida de `User::$fillable`; valor inicial en memoria vía `$attributes` y mutación exclusiva con `forceFill()` en `TeacherVerificationService`. | `app/Models/User.php`, `app/Services/TeacherVerificationService.php` |
| IDOR, Path Traversal y acceso cruzado a comprobantes privados | OWASP A01 / LFPDPPP | Disco privado `local` (`storage/app/private`), prefijo estricto `teacher-verifications/{$teacher->id}/`, rechazo de `..`/`\`/`\0`, doble verificación `super_admin` (ruta + servicio) y auditoría `teacher.verification_document_viewed`. | `app/Services/TeacherVerificationService.php`, `app/Http/Controllers/AdminTeacherController.php` |
| XSS almacenado, MIME spoofing o Clickjacking al visualizar comprobantes | OWASP A03 / A05 | MIME real verificado en carga (`getMimeType()` sin fallback) y al servir desde disco (`Storage::mimeType()` -> `415` si no cumple allowlist); nombre seguro derivado del MIME; `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` y `CSP: default-src 'none'; frame-ancestors 'self'; sandbox`. | `app/Services/TeacherVerificationService.php`, `app/Http/Controllers/AdminTeacherController.php` |
| Condición de carrera (TOCTOU) en cupo de códigos y dictamen concurrente | OWASP A04 / `RNF-SEG-008` | Orden determinista de locks `User` → `TeacherVerification` → `TeacherInvitationCode` (`lockForUpdate()`); `approve()`/`reject()` exigen estrictamente `status === 'pending'`; `resubmit()` ejecuta `licenseVerifier` fuera de `DB::transaction()`. | `app/Services/TeacherVerificationService.php` |
| Enumeración / oráculo de códigos institucionales y fuerza bruta | OWASP A07 / `RNF-SEG-008` | Entropía criptográfica con `random_int` (40 bits), `withValidator()` aborta si existen errores previos en el formulario, error genérico uniforme y `throttle:10,1` en registro, reenvío y canje. | `app/Http/Requests/RegisterTeacherRequest.php`, `routes/web.php`, `routes/api.php` |
| Retención indebida de documentos o metadatos personales | LFPDPPP / `RNF-SEG-007` | Purga física verificada (`delete()` + `!exists()` con `Log::error` ante falla), nulificación de `document_path`, `document_original_name` y `document_mime_type` con marca `document_purged_at`, y `Cache-Control: no-store`. | `app/Services/TeacherVerificationService.php`, `app/Services/AuthService.php` |
| Bypass de acreditación en API v1 o WebSockets Reverb | OWASP A01 / `RNF-SEG-004` | Middleware `EnsureTeacherIsVerified` en `groups/*`, `exams/*`, `grades/*` (`403` diferenciado `pending`/`rejected`) y validación `is_active && isVerifiedTeacher()` en `exam.{examId}`. | `app/Http/Middleware/EnsureTeacherIsVerified.php`, `routes/api.php`, `routes/channels.php` |
| Fuga de PII o falta de trazabilidad en bitácoras | OWASP A09 / LFPDPPP | Registro inmutable en `acs_audit_logs` para envío, canje, reenvío, visualización documental, aprobación, rechazo y gestión de códigos, sin almacenar nunca contenido del archivo. | `app/Services/TeacherVerificationService.php` |

---

## 4. Guía Exacta de Actualización para Manuales Oficiales (`.docx`) — Dictamen C

Al tratarse de una extensión arquitectónica de seguridad y privacidad posterior a la emisión base de los manuales V2/V4, corresponde emitir **Dictamen C** y sincronizar explícitamente las siguientes secciones y tablas en los documentos oficiales ubicados en `07_Manuales_Oficiales_V2/`:

### 4.1 Mapa de Secciones y Tablas por Documento Oficial

| Manual / Artefacto Oficial | Sección / Tabla Exacta a Actualizar | Cambio Normativo Requerido |
| :--- | :--- | :--- |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`§8.10.6` Pantalla de Registro de Docentes** | Incorporar el asistente de 2 pasos (Ley de Hick: Paso 1 datos básicos con `Continuar`, Paso 2 acreditación mínima con `Volver`/`Enviar solicitud`), el enlace directo `?codigo=`, `institution_name`, `verification_document` (`PDF/JPG/PNG <= 5 MB`), `professional_license` opcional bajo divulgación progresiva, el aviso LFPDPPP de purga automática y la pantalla post-login `docente/verificacion` (`teacher.verification.status`) con reenvío tras rechazo y canje posterior de código. |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`Tabla 7` Catálogo de Form Requests** | Actualizar `RegisterTeacherRequest` y añadir los 7 Form Requests nuevos/homologados: `ResubmitTeacherVerificationRequest`, `RedeemInvitationCodeRequest`, `RejectTeacherVerificationRequest`, `StoreInvitationCodeRequest`, `SendInvitationCodeEmailRequest`, `AssignTeacherPackageRequest` y `UpdateTeacherProfileRequest`. |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`Tabla 17` / `Tabla 18` Mensajes de Validación** | Copiar los mensajes exactos de la **Subsección 4.3** (sin punto final) para todos los campos de acreditación docente, códigos institucionales, asignación de paquete y perfil. |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`§6.3` / `§6.6` Almacenamiento Privado, Rutas y Purga LFPDPPP** | Incorporar la **Subsección 4.2** (catálogo de rutas Web/API v1), el uso exclusivo del disco `local` (`storage/app/private/teacher-verifications/{user_id}/{uuid}.{ext}`), el streaming endurecido (`415` ante MIME no permitido, `CSP frame-ancestors 'self'; sandbox`, `X-Frame-Options: SAMEORIGIN`, `nosniff`, `no-store`), el contrato `ProfessionalLicenseVerifierContract` (`SEP_CEDULA_*` en `.env.example`), `EnsureTeacherIsVerified`, canal Reverb `exam.{examId}` y la purga física y de metadatos LFPDPPP. |
| **`Manual de Programacion Laravel (MPW-V2).docx`** | **`§7.8.2` / `Tabla 20` Catálogo de Verbos y `§8.7` SweetAlert2** | Dejar constancia de que la nueva UI reutiliza verbos ya oficiales de `Tabla 20` (`Aceptar solicitud`, `Enviar rechazo`, `Crear código`, `Abrir revisión`, `Enviar solicitud`, `Continuar`, `Cerrar`) y proponer la incorporación formal únicamente de **`Revocar`** y **`Reactivar`** (junto con los verbos administrativos preexistentes **`Asignar plan`** y **`Suspender`**) para el portal Superadministrador con confirmación SweetAlert2. |
| **`Manual de Calidad (MC-V2).docx`** | **Control de Cambios / Versiones y Evidencias LFPDPPP** | Registrar la nueva revisión menor por `ADR-057`, documentar el procedimiento de purga física + nulificación de metadatos LFPDPPP con auditoría `acs_audit_logs` (`document_purged`, `teacher.verification_document_viewed`) y actualizar la matriz de auditoría de seguridad. |
| **`Manual de Base de Datos (MBD-V4).docx`** | **Tabla 20 (`acs_users`) y Diccionario de Datos (Nuevas Tablas)** | Documentar la columna `teacher_verification_status VARCHAR(20) DEFAULT 'approved' INDEX` en `acs_users` y agregar diccionario, índices, FKs y diagrama ER de `acs_teacher_verifications` y `acs_teacher_invitation_codes` (migración `2026_11_17_000001`). |
| **`DE-01` / Matriz de 226 Requerimientos (`.xlsx`)** | **`RF-AUT-001`, `RW-03`, `RW-05`, `RNF-SEG-004`, `RNF-SEG-007`, `RNF-SEG-008`** | Ampliar los criterios de aceptación de `RF-AUT-001` y `RW-03` para exigir acreditación híbrida docente, reflejar dictamen y códigos institucionales en `RW-05`, y enlazar la purga documental y bloqueo WebSocket con `RNF-SEG-004/007/008`. |

### 4.2 Catálogo de Rutas Web y API v1 (`ADR-057`) — Listo para `MPW-V2`

| Superficie | Método | URI Exacta | Nombre de Ruta | Middlewares y Restricciones |
| :--- | :--- | :--- | :--- | :--- |
| Web (Invitado) | `GET` | `/register/docente` | `auth.register.teacher.show` | `web`, `guest` |
| Web (Invitado) | `POST` | `/register/docente` | `auth.register.teacher.store` | `web`, `guest`, `throttle:10,1` |
| Web (Docente) | `GET` | `/docente/verificacion` | `teacher.verification.status` | `web`, `auth`, `role:teacher` |
| Web (Docente) | `POST` | `/docente/verificacion/reenviar` | `teacher.verification.resubmit` | `web`, `auth`, `role:teacher`, `throttle:10,1` |
| Web (Docente) | `POST` | `/docente/verificacion/canjear-codigo` | `teacher.verification.redeem-code` | `web`, `auth`, `role:teacher`, `throttle:10,1` |
| Web (Docente Operativo) | `*` | `/groups*`, `/questions*`, `/exams*`, `/docente/revision`, `/docente/calificaciones`, `/docente/suscripcion`, `/docente/creditos-ia`, `/perfil` | `groups.*`, `questions.*`, `exams.*`, `teacher.*` | `web`, `auth`, `role:teacher`, `teacher.verified` |
| Web (Super Admin) | `GET` | `/admin/docentes/{teacher}/verificacion/documento` | `admin.teachers.verification.document` | `web`, `auth`, `role:super_admin`, `whereNumber('teacher')` |
| Web (Super Admin) | `POST` | `/admin/docentes/{teacher}/verificacion/aprobar` | `admin.teachers.verification.approve` | `web`, `auth`, `role:super_admin`, `whereNumber('teacher')` |
| Web (Super Admin) | `POST` | `/admin/docentes/{teacher}/verificacion/rechazar` | `admin.teachers.verification.reject` | `web`, `auth`, `role:super_admin`, `whereNumber('teacher')` |
| Web (Super Admin) | `GET` | `/admin/invitaciones` | `admin.invitations.index` | `web`, `auth`, `role:super_admin` |
| Web (Super Admin) | `POST` | `/admin/invitaciones` | `admin.invitations.store` | `web`, `auth`, `role:super_admin` |
| Web (Super Admin) | `POST` | `/admin/invitaciones/{invitationCode}/toggle` | `admin.invitations.toggle` | `web`, `auth`, `role:super_admin`, `whereNumber('invitationCode')` |
| Web (Super Admin) | `POST` | `/admin/invitaciones/{invitationCode}/enviar` | `admin.invitations.send-email` | `web`, `auth`, `role:super_admin`, `whereNumber('invitationCode')`, `throttle:20,1` |
| API v1 (Pública) | `POST` | `/api/v1/auth/register/teacher` | `api.v1.auth.register.teacher` | `api`, `throttle:10,1` |
| API v1 (Protegida) | `*` | `/api/v1/groups*`, `/api/v1/exams*`, `/api/v1/grades*` | `api.v1.groups.*`, `api.v1.exams.*`, `api.v1.grades.*` | `api`, `auth:sanctum`, `teacher.verified` |
| WebSockets Reverb | `AUTH` | `private-exam.{examId}` | `channels.php` | `role === 'teacher' && is_active && isVerifiedTeacher()` + propiedad del examen |

### 4.3 Catálogo de Mensajes Normativos (`Tabla 17`) Agregados — Listo para `MPW-V2`

| Form Request | Campo y Regla | Mensaje Exacto en Español (Sin Punto Final) |
| :--- | :--- | :--- |
| `RegisterTeacherRequest` / `RedeemInvitationCodeRequest` | `invitation_code.required` | `El campo código de invitación es obligatorio` |
| `RegisterTeacherRequest` / `RedeemInvitationCodeRequest` | `invitation_code.string` | `El código de invitación no tiene un formato válido` |
| `RegisterTeacherRequest` / `RedeemInvitationCodeRequest` | `invitation_code.max` | `El código de invitación no debe exceder los 32 caracteres` |
| `RegisterTeacherRequest` / `TeacherVerificationService` | `invitation_code` (inválido/expirado/agotado) | `El código de invitación no es válido, expiró o alcanzó su límite de usos` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` / `StoreInvitationCodeRequest` | `institution_name.required` / `required_without` | `El campo institución educativa es obligatorio` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` / `StoreInvitationCodeRequest` | `institution_name.max` | `El campo institución educativa no debe exceder los 150 caracteres` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `educational_level.in` | `La opción seleccionada en nivel educativo no es válida` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `professional_license.regex` | `La cédula profesional no tiene un formato válido` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `credential_type.required` / `required_without` | `El campo tipo de comprobante es obligatorio` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `credential_type.in` | `La opción seleccionada en tipo de comprobante no es válida` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `verification_document.required` / `required_without` | `El campo comprobante de acreditación docente es obligatorio` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `verification_document.uploaded` | `No se pudo cargar el comprobante de acreditación docente, verifica que no supere 5 MB` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `verification_document.file` / `mimes` / `mimetypes` | `El comprobante de acreditación docente no tiene un formato válido` |
| `RegisterTeacherRequest` / `ResubmitTeacherVerificationRequest` | `verification_document.max` | `El tamaño del comprobante de acreditación docente debe ser menor o igual que 5 MB` |
| `RejectTeacherVerificationRequest` | `rejection_reason.required` | `El campo motivo de rechazo es obligatorio` |
| `RejectTeacherVerificationRequest` | `rejection_reason.min` | `El motivo de rechazo debe tener al menos 10 caracteres` |
| `RejectTeacherVerificationRequest` | `rejection_reason.max` | `El motivo de rechazo no debe exceder los 500 caracteres` |
| `StoreInvitationCodeRequest` | `max_uses.required` | `El campo límite de usos es obligatorio` |
| `StoreInvitationCodeRequest` | `max_uses.min` | `El límite de usos debe ser mayor que 0` |
| `StoreInvitationCodeRequest` | `max_uses.max` | `El límite de usos debe ser menor o igual que 500` |
| `StoreInvitationCodeRequest` | `expires_at.after` | `La fecha de expiración debe ser posterior a la fecha actual` |
| `StoreInvitationCodeRequest` | `custom_prefix.regex` | `El prefijo personalizado no tiene un formato válido` |
| `AssignTeacherPackageRequest` | `subscription_package_id.required` / `exists` | `El campo paquete de suscripción es obligatorio` / `La opción seleccionada en paquete de suscripción no es válida` |
| `AssignTeacherPackageRequest` | `payment_status.required` / `in` | `El campo estado de pago es obligatorio` / `La opción seleccionada en estado de pago no es válida` |
| `UpdateTeacherProfileRequest` | `current_password.required_with` / `current_password` | `El campo contraseña actual es obligatorio` / `La contraseña actual no es correcta` |

---

## 5. Consecuencias

- **Seguridad reforzada sin fricción institucional:** Se cierra el vector de suplantación docente, colisiones TOCTOU, MIME spoofing y abuso de recursos SaaS/WebSockets manteniendo onboarding instantáneo mediante códigos institucionales y revisión auditable para docentes independientes.
- **Cumplimiento demostrable ante LFPDPPP:** Ningún comprobante ni sus metadatos de archivo (`document_path`, `document_original_name`, `document_mime_type`) permanecen almacenados una vez emitido el dictamen o canjeado un código; la tabla `acs_teacher_verifications` conserva únicamente la huella auditable (`document_purged_at`, `reviewed_by`, `reviewed_at`).
- **Cero regresiones para alumnos y API móvil v1:** `registerStudent` no sufre alteraciones, los docentes sembrados/existentes conservan `teacher_verification_status = 'approved'` por defecto en BD y en `User::$attributes`, y la API móvil recibe `verification_status` en el alta docente (`201`) junto con mensajes `403` diferenciados (`pending` vs `rejected`) vía `ApiResponse`.
- **Extensibilidad sin retrabajo:** Conectar en el futuro un proveedor automatizado de consulta SEP consiste únicamente en implementar `ProfessionalLicenseVerifierContract` y configurar `SEP_CEDULA_*` en `.env`, sin modificar controladores ni retener bloqueos de base de datos durante llamadas HTTP externas.
