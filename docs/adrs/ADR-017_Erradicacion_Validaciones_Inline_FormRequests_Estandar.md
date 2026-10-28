# ADR-017 — Erradicación de Validaciones Inline y Adopción Estricta de Form Requests

> **Estado:** Aceptado  
> **Fecha:** 27 de Octubre de 2026  
> **Decisores:** `backend-laravel-specialist`, `architecture-quality-auditor`  
> **Normativa de Referencia:** Manual `MPW-V2` (§2.1, §6.3, §8.11 Tabla 17)

---

## 1. Contexto y Problemática
En el código original existían **11 llamadas directas `$request->validate([...])`** dentro del cuerpo de los controladores Web y de API v1 (`QuestionController::store`, `ExamController::storeSection`, `AttemptController::answer`, `StudentExamController::join`, `Api\V1\GroupController::join`, etc.).

Esta práctica violaba directamente la regla de oro de `MPW-V2 §6.3` (*"Queda terminantemente prohibido validar entradas HTTP dentro de los métodos del controlador"*), provocaba controladores inflados (*Fat Controllers*), impedía reutilizar validaciones y generaba mensajes de error en inglés o genéricos fuera del estándar de calidad de A-Code Solutions. Además, `ExamStoreRequest` contenía una vulnerabilidad IDOR al no validar la pertenencia docente de los `group_ids.*`.

---

## 2. Decisión de Arquitectura
1. **Erradicación del 100% de `$request->validate()` en Controladores:**
   - Se crearon 10 nuevos Form Requests en `app/Http/Requests/`:
     - `QuestionStoreRequest` (con reglas condicionales según el tipo de reactivo: opción múltiple, relación de columnas, abierta o código).
     - `QuestionParaphraseStoreRequest`
     - `ExamSectionStoreRequest`
     - `GroupJoinRequest`
     - `GroupVerifyFolioRequest` (con soporte dual de `folio` y `qr_payload`).
     - `AttemptTakeRequest`
     - `AttemptAnswerWebRequest`
     - `AttemptAnswerApiRequest` (con soporte de respuestas vacías `nullable` para auto-envío por cronómetro).
     - `AttemptIncidentStoreRequest`
     - `DeviceRegisterRequest`
2. **Estandarización de Mensajes en Español (Tabla 17):**
   - Todos los Form Requests (los 10 nuevos y los 6 existentes) implementan el método `messages(): array` aplicando las 7 plantillas canónicas de `MPW-V2 §8.11`:
     - `'required' => 'El campo :attribute es obligatorio'` (sin punto final).
     - `'string' => 'El campo :attribute debe ser una cadena de texto'`.
     - `'min' => 'El campo :attribute debe contener al menos :min caracteres'`.
     - `'max' => 'El campo :attribute no debe exceder :max caracteres'`.
     - `'exists' => 'El :attribute seleccionado no es válido o no existe'`.
3. **Cierre de Brecha IDOR en Creación de Exámenes:**
   - En `ExamStoreRequest`, la regla para `group_ids.*` se aseguró contra el docente en sesión:
     ```php
     'group_ids.*' => [
         'integer',
         Rule::exists('acs_groups', 'id')->where('teacher_id', $this->user()?->id),
     ],
     ```
     impidiendo que un docente inyecte exámenes en grupos de otro docente.

---

## 3. Consecuencias
- **Positivas:** Controladores limpios (< 80 líneas) que solo reciben datos pre-validados y autorizados; mensajes de error 100% homogéneos y en español; mitigación integral de ataques IDOR en asignación de grupos; cumplimiento pleno de `MPW-V2 §6.3`.
