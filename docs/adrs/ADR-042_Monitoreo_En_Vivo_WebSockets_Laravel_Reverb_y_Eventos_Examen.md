# ADR-042 — Monitoreo Docente en Vivo con WebSockets (Laravel Reverb), Canales Privados y Detección de Incidentes Anti-Trampa (`RW-12`, `RW-16`, `RNF-CON-001`, `RNF-CON-002`, `RNF-SEG-004`)

> **Estado:** Aceptado  
> **Fecha:** 08 de Noviembre de 2026  
> **Decisores:** `normative-compliance-auditor`, `architecture-quality-auditor`, `security-vulnerability-auditor`, `backend-laravel-specialist`, `frontend-ui-specialist`, `qa-testing-engineer`  
> **Normativa de Referencia:** Manual `MPW-V2` (§3.8.1, §5.6, §7.2, Tabla 8, Tabla 17), Manual `MC-V2`, `RW-12`, `RW-16`, `RNF-CON-001` (Concurrencia), `RNF-CON-002` (Tiempo Real), `RNF-SEG-004` (Aislamiento y Protección IDOR)

---

## 1. Contexto y Problemática

1. **Supervisión Docente Pasiva:** Anteriormente, el docente debía recargar manualmente las páginas de resultados para conocer el estatus de avance de sus alumnos durante una aplicación activa de examen, careciendo de visibilidad inmediata ante irregularidades académicas.
2. **Aislamiento Multi-Docente y Prevención de Fugas de Información (`RNF-SEG-004`):** El monitoreo en vivo expone eventos en tiempo real con nombres de estudiantes, estados de examen y alertas anti-trampa. Un canal público o deficientemente autenticado permitiría que otros estudiantes o docentes ajenos espíen la actividad del grupo.
3. **Control Severo Anti-Fraude en Tiempo Real:** El sistema anti-trampa reporta eventos como cambio de pestaña en navegador (`tab_switch`), salida de la app móvil (`app_left`), pérdida de foco de ventana (`focus_lost`) e intentos de captura de pantalla (`screenshot_attempt`). El backend debe aplicar las cotas configuradas en `max_allowed_incidents` y notificar inmediatamente al panel del docente cuando un intento deba ser cancelado.

---

## 2. Decisión de Arquitectura

### 2.1. Arquitectura de WebSockets con Laravel Reverb
Se aprovechó el contenedor oficial de Laravel Reverb corriendo en el puerto 8080 del entorno Sail / Docker Compose (`compose.yaml`). Se definieron los siguientes eventos que implementan la interfaz `ShouldBroadcastNow`:
1. [`App\Events\ExamAttemptStarted`](file:///home/laambws/Escritorio/acs-exam-web/app/Events/ExamAttemptStarted.php): Emitido al momento en que un estudiante inicia o reanuda un intento, transmitiendo ID de intento, alumno, nombre completo y hora.
2. [`App\Events\ExamAttemptIncidentReported`](file:///home/laambws/Escritorio/acs-exam-web/app/Events/ExamAttemptIncidentReported.php): Emitido ante cualquier irregularidad detectada por el cliente web o móvil, indicando el tipo de falta, contador acumulado y bandera `is_cancelled`.
3. [`App\Events\ExamAttemptSubmitted`](file:///home/laambws/Escritorio/acs-exam-web/app/Events/ExamAttemptSubmitted.php): Emitido al momento en que un alumno entrega el examen o se agota su tiempo, reportando calificación preliminar y estatus de entrega.

### 2.2. Blindaje de Canales Privados (`routes/channels.php`)
Los eventos se transmiten únicamente sobre el canal privado `private-exam.{examId}`:
```php
Broadcast::channel('exam.{examId}', function (User $user, int $examId): bool {
    if ($user->role !== 'teacher') {
        return false;
    }

    $exam = Exam::withoutGlobalScopes()->find($examId);

    return $exam !== null && (int) $exam->teacher_id === (int) $user->id;
});
```
- Se restringe el canal exclusivamente a usuarios con rol `teacher`.
- Se valida que el docente autenticado sea el titular propietario del examen (`exam->teacher_id === user->id`), impidiendo cualquier acceso IDOR.
- Cualquier solicitud no autorizada de alumnos o docentes externos es rechazada con `HTTP 403 Forbidden`.

### 2.3. Lógica Transaccional de Cancelación por Incidentes en `AttemptService`
En [`App\Services\AttemptService::reportIncident`](file:///home/laambws/Escritorio/acs-exam-web/app/Services/AttemptService.php):
- Se incrementa el contador `incidents_count` de forma atómica.
- Si el total supera el umbral del examen (`incidents_count > exam->max_allowed_incidents`), el estatus del intento cambia de inmediato a `'cancelled_by_incident'`, se sella la fecha de entrega y se fija la calificación final en `0.00`.
- El evento emitido viaja con `is_cancelled = true`, permitiendo que el panel del docente marque al estudiante en rojo intenso y emita una alerta visual inmediata.

### 2.4. Tablero Web Reactivo ([monitor.blade.php](file:///home/laambws/Escritorio/acs-exam-web/resources/views/exams/monitor.blade.php))
Construido con componentes atómicos del UI Kit A-Code, Alpine.js y cliente WebSocket nativo:
- **Indicador de Conectividad:** Muestra en tiempo real el estado del WebSocket (Reverb conectado / Reconectando).
- **Tarjetas Resumen:** Alumnos convocados, en curso, entregados e incidentes anti-fraude acumulados.
- **Feed de Alertas Dinámicas:** Banner interactivo que despliega en vivo los incidentes conforme ocurren, con opción de descarte.
- **Tabla de Estudiantes en Vivo:** Lista ordenada alfabéticamente que actualiza en tiempo real el avance (`Pregunta X / N`), contador de incidentes con código de colores (verde, ámbar, rojo) y estado final.

---

## 3. Consecuencias

### Positivas
- Supervisión proactiva en vivo para docentes sin necesidad de recargar la página web.
- Seguridad garantizada: protección IDOR en el canal de WebSockets respaldada por pruebas automatizadas con generación de firma criptográfica Reverb.
- Detección inmediata y bloqueo de conductas deshonestas durante los exámenes.
- Suite completa de pruebas automatizadas en [`ExamMonitoringReverbTest.php`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/Realtime/ExamMonitoringReverbTest.php).
