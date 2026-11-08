# ADR-035 — Reenvío del Código de Autenticación en la API Móvil

> **Estado:** Aceptado  
> **Fecha:** 8 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-006` (2FA por correo), `ADR-010` (flujo 2FA en API v1 y colisión de OTP)  
> **Normativa de Referencia:** `MPW-V2` (§5.1), `MPM-V3` (§6.6.1, Tabla 12), `RF-AUT-006`, `RF-AUT-005`, `TwoFactorResendTest.php`

---

## 1. Contexto y Problemática
`RF-AUT-006` (Reenvío de código de autenticación) exige que el sistema permita **solicitar un nuevo código después de transcurridos 60 segundos desde el último envío**, y que **la emisión del nuevo código invalide el anterior**. Cada código nuevo conserva la vigencia (10 minutos) y el uso único de `RF-AUT-005`, y solicitar el reenvío **no completa la autenticación**.

La API solo emitía el código al iniciar sesión o registrarse. Si el correo tardaba o se perdía, el usuario tenía que volver a escribir sus credenciales. Además, `POST /api/v1/auth/two-factor` localiza al usuario **solo por el código** (`ADR-010`), por lo que la app móvil no conserva ningún identificador del usuario en ese paso.

---

## 2. Decisión de Arquitectura
1. **Nuevo endpoint público:** `POST /api/v1/auth/two-factor/resend` (`api.v1.auth.two-factor.resend`), con `{ email }`. Pertenece al mismo flujo previo a la sesión que `login` y `two-factor`, por lo que no usa `auth:sanctum`, y tiene el límite de intentos `throttle:10,1`.
2. **Regla de negocio en `AuthService::resendTwoFactorCode()`:**
   - Solo procede si la cuenta está activa, es docente o alumno y **tiene un código pendiente** (`two_factor_code` no nulo: se borra al verificarse).
   - El momento del último envío **se deduce de la vigencia** (`two_factor_expires_at − 10 minutos`), sin columnas nuevas ni migración. Si no pasaron `TWO_FACTOR_RESEND_SECONDS` (60), responde **429** con el mensaje *"Espera N segundos para solicitar un nuevo código."*.
   - Si procede, reutiliza `issueTwoFactorCode()`: genera un código único entre los vigentes (`ADR-010`), **sobrescribe el anterior** (queda invalidado) y reinicia la vigencia y la espera.
3. **Respuesta uniforme:** si el correo no existe o no hay verificación pendiente, la API responde igualmente `200` con el mismo mensaje y sin enviar nada, para no revelar qué cuentas existen. En entornos donde el correo no sale (`log`/`array`) se incluye `demo_code`, igual que en `login`.
4. **Constantes únicas** `TWO_FACTOR_TTL_MINUTES` y `TWO_FACTOR_RESEND_SECONDS` en `AuthService`: el texto del correo y el cálculo de la espera usan los mismos valores.
5. **Alcance por roles:** el reenvío aplica a los mismos roles que el login actual (docente y alumno). La resolución de `M-13` (alcance del segundo factor) puede restringirlo después sin cambiar el contrato.

---

## 3. Consecuencias
- **Positivas:** el usuario recupera un código perdido sin reescribir su contraseña; el servidor es la autoridad de la espera de 60 s (la app solo la refleja); el código anterior deja de funcionar; no requiere migración.
- **A vigilar:** quien consulte el endpoint con el correo de una cuenta que inició sesión hace menos de un minuto recibe `429` y, por tanto, deduce que existe una verificación pendiente; el límite por IP lo mitiga y la exposición es mínima. La búsqueda del usuario por código (`ADR-010`) no cambia.
- **Impacto en documentación:** `MPM-V3` (Tabla 12) documenta el endpoint; se registra en `DE-06`.
