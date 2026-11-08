# ADR-031 — Registro Web con Aviso de Privacidad Versionado, Validaciones Unificadas y Sesión 2FA Pendiente

> **Estado:** Aceptado  
> **Fecha:** 4 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-010` (2FA sin identificador en el payload de la API), `ADR-011` (aviso de privacidad en la API), `ADR-017` (FormRequests), `ADR-022` (UI Kit Blade)  
> **Normativa de Referencia:** `MPW-V2` (§7.3, §8.5, §8.6.1), `RF-AUT-001`, `RF-AUT-002`, `RF-AUT-003`, `RF-AUT-005`, `RF-AUT-011`, `WebRegistrationPrivacyTest.php`, `FrontendViewRenderingTest.php`

---

## 1. Contexto y Problemática
Dos líneas de trabajo del equipo tocaron las mismas pantallas de autenticación web (registro de alumno, registro de docente, verificación 2FA y layout `guest`):
1. **Refactor de arquitectura y UI Kit** (rama `refactor-arquitectura-v2`, `ADR-022`): vistas reconstruidas con componentes `x-form.*`, `x-modal` y Alpine.js, con modal de aviso de privacidad de lectura obligatoria.
2. **Correcciones por requerimiento** (PR #7, `fix/registro-aut`): validaciones y mensajes por campo (`RF-AUT-001`, `RF-AUT-002`, `RF-AUT-003`, `RF-AUT-005`), aceptación del aviso de privacidad **versionado en base de datos** y protección de la pantalla 2FA.

Al unirlas no bastaba elegir un lado: el refactor tenía el diseño institucional pero un aviso estático (sin versión ni registro de qué texto se aceptó), y las correcciones tenían la lógica correcta sobre vistas HTML planas que no usan el UI Kit.

---

## 2. Decisión de Arquitectura
1. **Aviso de privacidad vigente y versionado (`RF-AUT-011`).** `AuthService::currentPrivacyNotice()` devuelve el aviso publicado más reciente (`published_at <= now()`). Las vistas de registro envían su `id` en un campo oculto `privacy_notice_id` y muestran su **versión y texto** dentro del recuadro de lectura obligatoria. Si no hay aviso vigente, no se envía el campo y el registro se rechaza.
2. **Aceptación trazable.** `AuthService::registerWeb()` compara el `id` recibido con el aviso vigente; si difiere (se publicó otra versión mientras el usuario leía, o el formulario fue manipulado) lanza un error de validación sobre `accepts_privacy_notice`. Si coincide, en **una sola transacción** crea la cuenta, registra la aceptación (`acs_privacy_acceptances`: usuario, aviso, fecha/hora e IP) y emite el primer código 2FA.
3. **Validaciones únicas, mismas frases en servidor y cliente.** Las reglas y mensajes viven en los `FormRequest` (`RegisterStudentRequest`, `RegisterTeacherRequest`, `LoginRequest`, `VerifyTwoFactorRequest`): nombre y apellidos solo letras (máx. 100), correo único (máx. 255), teléfono de 10 dígitos (**obligatorio para docente, opcional para alumno**), contraseña de mínimo 8 con confirmación, código 2FA de 6 dígitos. Las vistas replican `maxlength`, `pattern`, filtrado de caracteres y los mismos textos de error bajo cada campo, para que lo que ve el usuario sea lo que el servidor valida.
4. **2FA ligado al navegador que pasó la contraseña.** Al iniciar sesión o registrarse se guarda `pending_2fa_user_id` en la sesión. `LoginController::showTwoFactorForm()` y `verifyTwoFactor()` responden **403** si el usuario de la URL/formulario no coincide, y la clave se elimina al verificar el código. Evita abrir la pantalla 2FA (y ver o probar códigos) de otra cuenta cambiando el id de la URL.
5. **Diseño.** Las vistas conservan el UI Kit (`ADR-022`): `x-form.group`, `x-form.input`, `x-modal` con scroll obligatorio. El layout `guest` conserva el pie institucional y su `@stack('scripts')`, desde donde cada vista carga su validación.
6. **Código 2FA en pantalla solo en modo demo.** El banner "Modo demo" de `verify-2fa` aparece únicamente cuando `MAIL_MAILER` es `log` o `array` (el correo no sale de verdad); con un correo real el código llega solo por correo, igual que `demo_code` en la API. De lo contrario, mostrarlo en la misma pantalla anularía el segundo factor (`RF-AUT-005`).
7. **Mensaje de contraseñas.** El aviso reactivo de Alpine usa la misma frase que el servidor: *"Los campos contraseña y confirmar contraseña deben coincidir"*.

---

## 3. Consecuencias
- **Positivas:** queda constancia de **qué versión** del aviso aceptó cada usuario y desde qué IP; no hay validaciones duplicadas con textos distintos; la pantalla 2FA ya no se puede abrir con un id ajeno; se conserva el diseño institucional.
- **Dependencia operativa:** si no existe ningún aviso publicado, el registro web falla con el mensaje *"El aviso de privacidad cambió o no está disponible"*. `PrivacySeeder` (incluido en `DatabaseSeeder`) crea la versión `1.0` de demostración; en un entorno nuevo debe ejecutarse la siembra.
- **Pendiente de definición del cliente:** el `DE-01` indica que las condiciones de aceptación de términos y privacidad siguen pendientes (`RF-AUT-011`). El texto de términos del servicio mostrado junto al aviso es provisional.
- **A vigilar:** `RF-AUT-003` y `RF-AUT-005` piden 2FA solo para docente y superadministrador; hoy el sistema lo exige también al alumno. Se deja registrado para decisión del equipo (no se cambió aquí).
- **Impacto en documentación:** `MPW-V2 §7.3` y `§8.6.1` deben mencionar el campo `privacy_notice_id` y la clave de sesión `pending_2fa_user_id`; se registra en `DE-06`.
