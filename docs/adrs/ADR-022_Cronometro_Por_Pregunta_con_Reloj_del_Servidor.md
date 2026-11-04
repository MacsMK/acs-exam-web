# ADR-022 — Cronómetro por Pregunta con Reloj del Servidor y Respuestas Extemporáneas

> **Estado:** Aceptado  
> **Fecha:** 3 de Noviembre de 2026  
> **Decisores:** Equipo 4 (A-Code Solutions)  
> **Complementa:** `ADR-019` (validación server-side de tiempo y ventana)  
> **Normativa de Referencia:** `MPW-V2` (§5.6.1), `MPM-V4` (§5.4.1, §5.4.2, §7.8), `RF-EXA-032`, `RF-EXA-042`, `ExamQuestionTimingTest.php`

---

## 1. Contexto y Problemática
La auditoría de la API contra el manual móvil encontró que el servidor **no era la autoridad del tiempo por pregunta**, aunque `MPW-V2 §5.6.1` y `MPM-V4 §5.4.1` lo exigen ("el servidor entrega el tiempo restante de cada pregunta, calcula el tiempo transcurrido con su propio reloj y rechaza o marca como extemporánea toda respuesta que exceda el límite más tres segundos"):
1. **`remaining_seconds` era siempre el límite completo de la pregunta** (`time_limit_seconds`), sin restar el tiempo ya consumido. Cerrar y reabrir la app, o volver a llamar a `POST /exams/take`, devolvía el tiempo completo otra vez.
2. **No existía validación por pregunta.** `ADR-019` solo valida la duración total del intento y la ventana del examen (con margen de 60 s); una respuesta podía llegar minutos después del límite de su pregunta y calificarse con normalidad.
3. **El comentario del código citaba `MPW-V2 §5.6.1` para una tolerancia de 60 s**, mientras el manual fija **3 s por pregunta**. Son dos reglas distintas que estaban confundidas.

---

## 2. Decisión de Arquitectura
1. **Momento de entrega de la pregunta activa, sin columnas nuevas** (`AttemptService::questionServedAt()`): es el `answered_at` de la última respuesta del intento (cada respuesta devuelve la siguiente pregunta) o, para la primera pregunta, `started_at`. No se modificó el esquema de base de datos.
2. **Tiempo restante real** (`AttemptService::remainingSeconds()`): `límite − transcurrido` con el reloj del servidor, acotado entre `0` y el límite. `GET`/`POST` de la API y la vista web muestran este valor en `remaining_seconds`.
3. **Respuesta extemporánea** (`AttemptService::isAnswerLate()`): si `transcurrido > límite + 3 s` (`AttemptService::QUESTION_TIME_TOLERANCE_SECONDS`), la respuesta **se conserva** para la revisión docente pero se califica con **cero puntos**, sin invocar al evaluador de IA, y el intento **avanza igual** a la siguiente pregunta (no se puede volver atrás, RW-20).
4. **Contrato de la API (`POST /api/v1/exams/{exam}/answers`):** la respuesta exitosa agrega el campo booleano `late_answer`. La app móvil muestra el mensaje oficial *"El tiempo asignado a este reactivo ha concluido. Tu avance fue guardado."* (`MPM-V4` Tabla 20) y continúa. En el panel web el mismo mensaje se muestra como aviso de estado.
5. **Resincronización al volver a primer plano (`MPM-V4 §5.4.1`):** `POST /api/v1/exams/take` ya reanuda el intento (`firstOrCreate`); con este ADR devuelve el tiempo restante real, por lo que la app puede resincronizar **sin endpoint nuevo**.
6. **Distinción de tolerancias:** 3 s aplican a **cada pregunta**; los 60 s de `ADR-019` aplican a la **duración total del intento y a la ventana `ends_at`**.

---

## 3. Consecuencias
- **Positivas:** el servidor es de verdad la autoridad del tiempo por pregunta; reabrir la app o recargar la página no devuelve el tiempo completo; el comportamiento coincide con ambos manuales; no requiere migración.
- **Negativas / a vigilar:** una conexión muy lenta puede marcar como extemporánea una respuesta enviada a tiempo; por eso la tolerancia es configurable en una sola constante. El primer cálculo depende de `started_at` del intento.
- **Impacto en documentación:** `MPW-V2 §5.6.1` debe citar el campo `late_answer` y la derivación del momento de entrega; `MPM-V4 §6.5` (Tabla 12) debe documentar `late_answer`; se registra en `DE-06`.
