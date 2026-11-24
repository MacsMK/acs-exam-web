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

En Sail, Laravel publica por la red interna de Docker en `reverb:8080`. El navegador recibe el host, puerto y esquema públicos de `REVERB_HOST`, `REVERB_PORT` y `REVERB_SCHEME`. Los despliegues pueden configurar la publicación por separado con `REVERB_PUBLISH_HOST`, `REVERB_PUBLISH_PORT` y `REVERB_PUBLISH_SCHEME`.

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

### 2.4. Tablero web reactivo

- Blade sirve el listado inicial, los totales y los filtros. Alpine mantiene la búsqueda por nombre, correo y estado sin recargar la vista.
- El indicador muestra «En vivo» después de confirmar la suscripción y recuperar el estado vigente desde el servidor. Durante una interrupción conserva los últimos datos y muestra la hora de actualización. Las consultas periódicas también permiten reflejar expiraciones.
- Las tarjetas muestran totales del examen. La tabla identifica el intento vigente de cada estudiante y muestra su estado, incidentes reportados, horas y calificación con la escala del examen. La posición de navegación puede cambiar en modo libre y queda fuera de la supervisión.
- Alcanzar el límite configurado genera una advertencia. La cancelación confirmada queda en la fila y se anuncia con un aviso descartable en línea. El docente puede seguir supervisando sin perder el foco.

---

## 3. Consecuencias

### Positivas
- Supervisión proactiva en vivo para docentes sin necesidad de recargar la página web.
- Seguridad garantizada: protección IDOR en el canal de WebSockets respaldada por pruebas automatizadas con generación de firma criptográfica Reverb.
- Detección inmediata y bloqueo de conductas deshonestas durante los exámenes.
- Suite completa de pruebas automatizadas en [`ExamMonitoringReverbTest.php`](file:///home/laambws/Escritorio/acs-exam-web/tests/Feature/Realtime/ExamMonitoringReverbTest.php).
