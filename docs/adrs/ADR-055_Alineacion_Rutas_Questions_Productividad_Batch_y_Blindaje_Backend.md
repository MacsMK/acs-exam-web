# ADR-055 — Alineación de Rutas `questions/*`, Productividad Batch/XLSX y Blindaje Backend

- **Fecha:** 12 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen C3/C4` (Auditoría Normativa, Arquitectura y Seguridad)
- **Afecta a:** `routes/web.php`, `app/Http/Controllers/QuestionController.php`, `app/Http/Controllers/ExamController.php`, `app/Http/Requests/Concerns/NormalizesQuestionInputs.php`, `app/Http/Requests/Question*.php`, `app/Http/Requests/ExamConfigurationUpdateRequest.php`, `app/Services/QuestionBankService.php`, `app/Services/SubscriptionLimitService.php`, `app/Services/ExamAssemblyService.php`

---

## 1. Contexto y Problema

La consolidación integral del Banco de Reactivos y Configuración de Exámenes requería incorporar flujos productivos docentes (`save_and_new`, paráfrasis iniciales en alta, captura rápida en lote, plantillas Excel `.xlsx` y previsualización *dry-run* de importación) mientras se cerraban vulnerabilidades detectadas en auditoría:
- **`[CRÍTICA-01]` IDOR en Configuración de Examen:** `ExamConfigurationUpdateRequest` validaba `exists:acs_groups,id` sin filtrar por `teacher_id`, permitiendo vincular grupos ajenos mediante manipulación del payload.
- **`[ALTA-03]` Condición de Carrera TOCTOU en Cuotas:** `SubscriptionLimitService` verificaba la cuota disponible sin bloqueo pesimista transaccional sobre el docente.
- **`[ALTA-08]` / `[MEDIA-02]` Integridad Copy-On-Write y Borrado:** `updateQuestion()` requería bloqueo pesimista antes de decidir edición *in-situ* vs Copy-On-Write, y `deleteQuestion()` necesitaba bloquear el borrado cuando existiesen vínculos a exámenes activos o publicados (`hasActiveOrPublishedExamLinks()`).
- **`[MEDIA-01]` Mutabilidad de `question_type`:** En edición era posible alterar el tipo polimórfico por manipulación de campos.
- **`CWE-1236` y Coerciones Silenciosas:** Faltaba neutralización contra inyección de fórmulas CSV/XLSX en plantillas exportadas y validación estricta sin coerciones implícitas al importar filas.
- **Brecha `MPW-V2 Tabla 17`:** Mensajes en español asociados a `.required_if` nunca disparaban bajo reglas `exclude_unless`.

---

## 2. Decisión de Arquitectura

1. **Rutas de Productividad Antes del Recurso `questions` (`routes/web.php`):**
   - Se registran explícitamente antes de `Route::resource('questions', QuestionController::class)`:
     - `GET questions/template/csv` (`questions.template.csv`) y `GET questions/template/xlsx` (`questions.template.xlsx`).
     - `POST questions/import/preview` (`questions.import.preview`) y `POST questions/import` (`questions.import`).
     - `GET questions/batch/create` (`questions.batch.create`) y `POST questions/batch` (`questions.batch.store`).
     - `POST questions/subjects/merge` (`questions.subjects.merge`) y `GET questions/subjects/suggestions` (`questions.subjects.suggestions`).
     - `POST questions/{question}/duplicate` (`questions.duplicate`), `POST questions/{question}/parafrasis` (`questions.paraphrase.store`) y `DELETE questions/{question}/parafrasis/{paraphrase}` (`questions.paraphrase.destroy` con `whereNumber('paraphrase')`).

2. **Normalización y Validación Polimórfica Unificada (`NormalizesQuestionInputs`):**
   - Limpieza de espacios Unicode invisibles (`\u{00A0}`, `\u{200B}`, etc.), `trim()` / `Subject::cleanDisplayName()` y reindexado (`array_values`) en `prepareForValidation()`.
   - Homologación estricta de reglas (`stem` `3..5000`, `default_points` `decimal:0,2|min:0.1|max:100`, `default_time_seconds` `5..3600`, `options` `list|min:2|max:6|distinct`, `concepts`/`definitions` `list|min:2|max:8|distinct` + paridad exacta, `starter_code` obligatorio cuando `is_find_error=true`, `instructions` `nullable|string|max:5000`).
   - Reemplazo completo de llaves `.required_if` por `.required` en `messages()` (`MPW-V2 Tabla 17`).
   - `QuestionUpdateRequest` fuerza `'question_type' => $question->question_type` en `prepareForValidation()` + `Rule::in([$question->question_type])` (`[MEDIA-01]`).
   - Nuevos FormRequests: `QuestionBatchStoreRequest` (`shared_subject_tag` + 1..30 `items`) y actualización de `QuestionImportRequest` (`csv_file` o alias `import_file`, `mimes:csv,txt,xlsx`, `max:5120`).

3. **Productividad Docente y Seguridad en Importación/Exportación (`QuestionBankService`):**
   - **Alta Productiva:** `createQuestion()` acepta `initial_paraphrases` (máximo 3, no repetidas ni iguales al `stem`) y `QuestionController::store()` redirige a `questions.create` conservando contexto cuando `save_and_new` es verdadero.
   - **Captura Rápida en Lote:** `createQuestionBatch()` crea hasta 30 reactivos atómicamente bajo bloqueo de cuota SaaS.
   - **Importación en Dos Fases (`CSV`, `TXT`, `XLSX`):** Lectura `.xlsx` con `PhpSpreadsheet` en modo `setReadDataOnly(true)`, límite `MAX_IMPORT_ROWS = 250`, 9 columnas canónicas más 2 opcionales, cero coerciones silenciosas ante datos inválidos, previsualización JSON (`previewBatchImport`) con detección de cuota y materias similares, confirmación atómica (`commitBatchImport`) y sanitización `CWE-1236` (`sanitizeSpreadsheetCellForExport`).

4. **Blindaje de Cuotas, IDOR y COW:**
   - `SubscriptionLimitService::ensureCanCreateQuestionsLocked()` aplica `User::whereKey(...)->lockForUpdate()` (`[ALTA-03]`) diferenciando Freemium histórico (`<= 50`) vs activos en planes de pago, y expone `getQuestionQuotaSummary()`.
   - `ExamConfigurationUpdateRequest` y `ExamController` verifican que todos los `group_ids` pertenezcan al docente autenticado antes de ejecutar `$exam->groups()->sync()` (`[CRÍTICA-01]`).
   - `QuestionBankService::updateQuestion()` adquiere `lockForUpdate()` antes del flujo COW (`[ALTA-08]`) y `deleteQuestion()` rechaza con `DomainException` cuando `hasActiveOrPublishedExamLinks()` es verdadero (`[MEDIA-02]`).
   - `ExamAssemblyService::acceptProposal()` verifica propiedad y `question_type` del docente (`[MEDIA-02]`), removiendo consultas Eloquent directas de `ExamController::assemble()`.

---

## 3. Consecuencias y Beneficios

- **Seguridad End-to-End:** Eliminación de vectores IDOR en grupos/paráfrasis/propuestas, carreras TOCTOU en cuotas SaaS, mutaciones ilícitas de tipo e inyecciones CSV/XLSX.
- **Cero Regresiones Contractuales:** Preservación íntegra de API v1 (`/api/v1/*`) para `acs-exam-mobile` y refactorización limpia de controladores hacia Skinny Controllers.
