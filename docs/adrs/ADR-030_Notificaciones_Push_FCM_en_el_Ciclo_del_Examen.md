# ADR-030 — Notificaciones Push (FCM) en el Ciclo de Vida del Examen

> **Estado:** Aceptado  
> **Fecha:** 3 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Normativa de Referencia:** `MPM-V4` (§6.8, Tabla 14), `MPW-V2` (§1.3.4), `RF-EXA-054` del `DE-01` (notificación push a los estudiantes del grupo al programar o publicar un examen), Listado de Requerimientos (docente: "ya comenzó / ya terminó"), `FcmPushNotifierTest.php`, `ExamPushNotificationsTest.php`

---

## 1. Contexto y Problemática
La app móvil ya registra su token FCM (`POST /api/v1/devices`, tabla `acs_user_devices`) y sabe abrir la pantalla correcta al tocar una notificación (`PushRouter`, Tabla 14), pero **el backend nunca enviaba ninguna**: no existía código de envío, y los cuatro tipos de la Tabla 14 (`exam.assigned`, `exam.opened`, `exam.started`, `exam.finished`) quedaban sin emisor. Los requisitos de notificaciones para docente y alumno no se cumplían de extremo a extremo.

---

## 2. Decisión de Arquitectura
1. **Contrato aislado (`PushNotifierContract`)**, igual que `AiEvaluatorContract`: `FcmPushNotifier` (envío real) o `NullPushNotifier` (solo bitácora). Se elige por `FCM_ENABLED`; por defecto **apagado**, de modo que demo, desarrollo y CI nunca contactan a Firebase.
2. **FCM HTTP v1 sin dependencias nuevas.** El token OAuth2 se obtiene firmando un JWT RS256 con la cuenta de servicio (`openssl_sign`) y se guarda en caché 55 minutos. No se modifica `composer.json` ni `composer.lock`.
3. **La cuenta de servicio nunca se versiona.** Su ruta llega por `FCM_CREDENTIALS_PATH` (por defecto `storage/app/private/`, ya ignorada por git, más reglas explícitas en `.gitignore`). Nunca se escribe su contenido en bitácoras.
4. **Contrato del mensaje (Tabla 14):** bloque `data` con `type`, `exam_id` y `group_id`, siempre como texto (`PushMessage::data()`), más `notification` con título y cuerpo en español.
5. **Quién recibe qué y cuándo** (`ExamNotificationService`):

| Tipo | Destinatario | Momento |
| --- | --- | --- |
| `exam.assigned` | Alumnos de los grupos del examen | Al publicar, si la ventana aún no abre |
| `exam.opened` | Alumnos de los grupos del examen | Al publicar con la ventana ya abierta, o cuando llega `starts_at` (comando programado) |
| `exam.started` | Docente del examen | Cuando el primer alumno inicia el examen |
| `exam.finished` | Docente del examen | Cuando vence `ends_at` (comando programado) o cuando todos los alumnos inscritos ya cerraron su intento |

6. **Una sola vez por examen, sin columnas nuevas:** antes de enviar, cada aviso se reserva con un registro en `acs_audit_logs` (`push.exam.*`) bajo bloqueo de la fila del examen, por lo que dos alumnos simultáneos no duplican el aviso y queda rastro auditable.
7. **Un fallo nunca rompe el examen:** el envío se ejecuta con captura de excepciones; un error de red o de Firebase se registra y el flujo continúa. Los tokens que FCM marca como no registrados se eliminan de `acs_user_devices`.
8. **Programación:** el comando `exams:push-due` corre cada minuto (`routes/console.php`) y solo considera ventanas de las últimas 24 horas, para no avisar de exámenes antiguos. En producción requiere la entrada de cron estándar `* * * * * php artisan schedule:run`.

---

## 3. Consecuencias
- **Positivas:** el requisito de notificaciones queda cubierto de extremo a extremo; el comportamiento sin llave es idéntico al anterior; sin migraciones ni dependencias nuevas; pruebas sin salir a internet.
- **A confirmar con el equipo (DE-03):** los momentos exactos de `exam.started` ("primer alumno que inicia") y `exam.finished` ("vence la ventana o todos terminaron") son la interpretación vigente de la Tabla 14; el `group_id` de los avisos al docente es el del alumno que inició (`exam.started`) y el primer grupo del examen (`exam.finished`).
- **A vigilar:** el aviso `exam.opened` depende de que el programador de tareas esté activo; sin él, solo se envía al publicar. `RF-EXA-054` cubre únicamente el aviso `exam.assigned` (al programar o publicar); los avisos `exam.opened`, `exam.started` y `exam.finished` **no tienen código en el `DE-01`** y se solicitó su registro al responsable del formato (no se inventó ninguno).
- **Impacto en documentación:** `MPW-V2` debe incorporar la sección de notificaciones (contrato, variables `FCM_*`, comando programado y tabla de eventos); `MPM-V4 §6.8` ya describe el lado cliente. Se registra en `DE-06`.
