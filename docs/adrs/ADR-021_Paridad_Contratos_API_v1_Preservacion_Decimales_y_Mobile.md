# ADR-021 — Paridad de Contratos API v1: Preservación de Decimales JSON y Cliente Flutter

> **Estado:** Aceptado  
> **Fecha:** 27 de Octubre de 2026  
> **Decisores:** `api-mobile-contract-verifier`, `backend-laravel-specialist`  
> **Normativa de Referencia:** `MPW-V2` (§6.5), `MPM-V1`, `RF-API-*`, `MobileContractTest.php`

---

## 1. Contexto y Problemática
La auditoría cruzada entre los 20 endpoints de `/api/v1/*` y el cliente Flutter (`acs-exam-mobile`) identificó riesgos de excepciones en tiempo de ejecución en Dart:
1. **Serialización truncada de decimales en JSON:** `json_encode()` en PHP, por omisión, serializa números de punto flotante exactos como enteros (por ejemplo, `40.0` y `100.0` se convierten en `"score": 40` y `"weight": 100`). En el cliente Dart, si un modelo espera `double`, la decodificación estricta lanza:
   `TypeError: type 'int' is not a subtype of type 'double'`.
2. **Fallo de validación en respuestas vacías por cronómetro:** Cuando el temporizador móvil agota el tiempo y envía un reactivo abierto o de código en blanco (`text: ""`), el middleware de Laravel `ConvertEmptyStringsToNull` convierte la cadena vacía a `null`. Al carecer de la regla `'nullable'`, el backend rechazaba la petición con `422 Unprocessable Entity`, bloqueando el avance a la siguiente pregunta.
3. **Discrepancia en verificación de folios de grupo:** La documentación especificaba soporte para `folio` o `qr_payload`, pero el controlador API solo admitía `qr_payload`, provocando fallos si Flutter enviaba `folio`.

---

## 2. Decisión de Arquitectura
1. **Preservación Obligatoria de Decimales en `App\Support\ApiResponse`:**
   - Se configuró la bandera `JSON_PRESERVE_ZERO_FRACTION` en las respuestas JSON:
     ```php
     return response()->json(
         $payload,
         $code,
         ['Content-Type' => 'application/json; charset=utf-8'],
         JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE
     );
     ```
   - Esto asegura que `40.0` se serialice invariablemente como `40.0` en el JSON, blindando los modelos Dart contra discrepancias de tipo `num`/`double`.
2. **Soporte de Respuestas `nullable` en `AttemptAnswerApiRequest`:**
   - Se definieron las reglas:
     ```php
     'text' => ['sometimes', 'nullable', 'string'],
     'code' => ['sometimes', 'nullable', 'string'],
     'pairs' => ['sometimes', 'nullable', 'array'],
     'selected_option_ids' => ['sometimes', 'nullable', 'array'],
     ```
   - En `Api\V1\AttemptController::normalizeAnswerPayload()`, si `text` o `code` es `null`, se normaliza a cadena vacía `""`, la cual es procesada limpiamente por `AttemptService` con puntaje 0 sin llamadas a IA.
3. **Soporte Dual en `GroupVerifyFolioRequest` y `Api\V1\GroupController::verify()`:**
   - El validador y el controlador aceptan indistintamente `folio` o `qr_payload` (`$folio = $data['folio'] ?? $data['qr_payload'] ?? null`).
4. **Respuesta Terminal con HTTP 409:**
   - Si un intento ya no está `in_progress` (por ejemplo, `cancelled_by_incident` o `submitted`), el controlador responde con `ApiResponse::error('Este intento ya no se encuentra activo.', 409)` en lugar de arrojar errores no controlados.

---

## 3. Consecuencias
- **Positivas:** Paridad 1:1 total entre el backend Laravel y el cliente Flutter; prevención de cierres inesperados de la app móvil en Android e iOS; compatibilidad certificada con la suite de pruebas `MobileContractTest.php`.
