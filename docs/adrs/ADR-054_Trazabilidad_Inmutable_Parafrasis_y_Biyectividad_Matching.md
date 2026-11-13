# ADR-054 — Trazabilidad Inmutable de Paráfrasis e Integridad Biyectiva en Relación de Columnas

- **Fecha:** 12 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen C1/C2` (Auditoría de Seguridad OWASP y Normativa `MPW-V2`)
- **Afecta a:** `app/Services/QuestionBankService.php`, `app/Services/AttemptService.php`, `app/Services/StudentResultService.php`, `app/Http/Requests/QuestionParaphraseStoreRequest.php`, `app/Http/Requests/AttemptAnswerWebRequest.php`, `app/Http/Requests/AttemptAnswerApiRequest.php`, `app/Http/Controllers/QuestionController.php`

---

## 1. Contexto y Problema

La auditoría técnica del motor de evaluación detectó cuatro riesgos de integridad académica y seguridad:
1. **`[CRÍTICA-02]` Identificadores Predecibles en Relación de Columnas:** `QuestionBankService::buildMatchingPayload()` generaba IDs correlacionables en los pares `left` / `right`, filtrando indicios de la respuesta correcta si se inspeccionaba el payload servido. Además faltaba verificación de cardinalidad idéntica entre `concepts` y `definitions`.
2. **`[CRÍTICA-03]` Falta de Biyectividad en Respuestas `matching`:** Ni `AttemptAnswerWebRequest` / `AttemptAnswerApiRequest` ni `AttemptService::gradeMatching()` impedían enviar valores `right_id` repetidos o superar el número de conceptos/definiciones válidos.
3. **`[ALTA-09]` / `[MEDIA-05]` Mutabilidad Post-Inicio del Enunciado Servido:** Si un docente añadía o eliminaba paráfrasis después de iniciarse un intento, `visibleStem()` recalculaba el índice sobre el total vivo de paráfrasis, alterando retrospectivamente la redacción mostrada durante el examen o en la revisión post-examen.
4. **`[MEDIA-02]` Paráfrasis Duplicadas o Sin Límite/Pertenencia:** Faltaba tope de paráfrasis por reactivo, validación normalizada contra el `stem` original y verificación explícita de pertenencia reactivo↔paráfrasis en eliminación.

---

## 2. Decisión de Arquitectura

1. **IDs Opaque Aleatorios y Cardinalidad Exacta en `matching` (`[CRÍTICA-02]`):**
   - `QuestionBankService::buildMatchingPayload()` valida `count($concepts) === count($definitions)` (lanzando `InvalidArgumentException` si difieren) y genera identificadores criptográficos independientes `'L_' . bin2hex(random_bytes(5))` y `'R_' . bin2hex(random_bytes(5))`, preservando la estructura `['id' => string, 'text' => string]` y `pairs_payload: [['left_id' => ..., 'right_id' => ...]]` exigida por web y móvil.

2. **Biyectividad Estricta en Validación y Calificación (`[CRÍTICA-03]`):**
   - `AttemptAnswerWebRequest` y `AttemptAnswerApiRequest` rechazan `left_id` duplicados, `right_id` no vacíos repetidos y exceso de pares respecto a la columna derecha (resolviendo el `ExamQuestion` dentro del examen en API para preservar el flujo `409 Conflict` ante reactivo distinto al activo).
   - `AttemptService::gradeMatching()` valida biyectividad estricta: si la cantidad de pares excede el total esperado o existen `left_id` duplicados, `right_id` repetidos o identificadores inexistentes en los catálogos del reactivo, califica con `0.0` sin otorgar puntaje parcial fraudulento.

3. **Snapshot Inmutable de Paráfrasis por Intento y Respuesta (`[ALTA-09]`, `[MEDIA-05]`):**
   - Al generar el intento en `AttemptService`, se construye `StudentAttempt::$paraphrase_snapshot_payload` asignando determinísticamente (`crc32("$studentId:$examQuestionId") % count($variants)`) entre `[null, ...paraphrases]`, congelando `paraphrase_id` y `stem`.
   - `AttemptService::resolvedStemData()` prioriza dicho snapshot sobre la relación viva y `submitAnswer()` persiste `question_paraphrase_id` y `served_stem_snapshot` en `acs_student_answers`.
   - `StudentResultService::review()` carga eager `'question.paraphrases'` y muestra `$answer?->served_stem_snapshot ?? $this->attemptService->visibleStem($examQuestion, $attempt)` para garantizar fidelidad histórica exacta.

4. **Gobernanza del Ciclo de Vida de Paráfrasis (`[MEDIA-02]`):**
   - `QuestionParaphraseStoreRequest` y `QuestionBankService::createParaphrase()` aplican limpieza Unicode, verifican tope `MAX_PARAPHRASES_PER_QUESTION = 10` e impiden registrar redacciones equivalentes (minúsculas y espacios colapsados) al `stem` raíz o a paráfrasis ya existentes.
   - `QuestionBankService::deleteParaphrase()` resuelve el registro vía `findParaphraseForQuestion($question, $paraphraseId)` para prevenir IDOR transversal entre reactivos (`DomainException` / HTTP 404).

---

## 3. Consecuencias y Beneficios

- **Integridad Psicométrica y Anti-Trampa:** El payload servido al estudiante no revela correspondencias y la calificación proporcional `RF-EVA-002` es matemáticamente inviolable.
- **Evidencia Auditora Inmutable:** Lo que el estudiante leyó al responder queda congelado en `StudentAttempt` y `StudentAnswer`, protegiendo revisiones y apelaciones futuras.
