# ADR-034 — Aviso de Privacidad Público para el Registro desde la App Móvil

> **Estado:** Aceptado  
> **Fecha:** 8 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-011` (aviso de privacidad en la API), `ADR-031` (aviso versionado en el registro web)  
> **Normativa de Referencia:** `MPM-V4` (§6.6.3, Tabla 12 y Tabla 17), `RF-AUT-002`, `RF-AUT-011`, `PublicPrivacyNoticeTest.php`

---

## 1. Contexto y Problemática
`RF-AUT-011` exige que el usuario pueda **consultar el aviso de privacidad y aceptarlo de forma explícita antes de completar el registro**, "tanto en la aplicación web como en la aplicación móvil". `RF-AUT-002` (registro de estudiante) está validado en el `DE-01` del alcance móvil.

La API tenía el registro (`POST /api/v1/auth/register/student`, que ya exige `accepts_privacy_notice` y guarda la aceptación) pero **no había forma de leer el aviso sin sesión**: `GET /api/v1/privacy-notice` está protegido por `auth:sanctum` y calcula `accepted` con el usuario autenticado. Una pantalla de registro en la app solo podía pedir una casilla de aceptación **sin mostrar el texto**, lo que incumple el requerimiento.

---

## 2. Decisión de Arquitectura
1. **Nueva ruta pública de solo lectura:** `GET /api/v1/privacy-notice/current` (`api.v1.privacy.current`), sin `auth:sanctum`. Devuelve `{ version, title, content }` del aviso vigente (el mismo que ya consultan `show` y `accept`). No incluye `accepted`, porque sin usuario no existe una aceptación que informar.
2. **Sin cambios al registro.** `POST /api/v1/auth/register/student` sigue recibiendo `accepts_privacy_notice` y registrando la aceptación del aviso vigente en la misma transacción que crea la cuenta.
3. **Las rutas con sesión no cambian.** `GET /api/v1/privacy-notice` y `POST /api/v1/privacy-notice/accept` siguen exigiendo token; la prueba `test_authenticated_notice_route_still_requires_a_token` lo verifica.

---

## 3. Consecuencias
- **Positivas:** la app móvil puede mostrar el texto legal antes del registro y cumplir `RF-AUT-011` sin duplicar el aviso en el cliente; ningún contrato existente cambia; el aviso sigue siendo texto único del servidor.
- **Seguridad:** el aviso de privacidad es información pública por naturaleza; la ruta no expone datos de usuarios ni de aceptaciones.
- **A vigilar:** el registro de API registra el aviso vigente al momento de enviar el formulario y no el `id` mostrado (a diferencia del registro web, `ADR-031`). Si se publica una versión nueva mientras el alumno lee, quedará aceptada la nueva; no hay riesgo legal adicional porque la app muestra el aviso completo de la nueva versión al iniciar sesión por primera vez cuando no consta su aceptación.
- **Impacto en documentación:** `MPW-V2` y `docs/03_BACKEND_Y_FRONTEND.md` deben listar la ruta pública; `MPM-V4` Tabla 12 debe documentarla y la Tabla 17 debe incluir la pantalla de registro; se registra en `DE-06`.
