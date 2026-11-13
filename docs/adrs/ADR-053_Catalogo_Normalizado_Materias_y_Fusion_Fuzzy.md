# ADR-053 — Catálogo Normalizado de Materias, Sugerencias Similares y Fusión Transaccional

- **Fecha:** 12 de noviembre de 2026
- **Estado:** Aceptado
- **Autores:** Equipo 4 (A-Code Solutions)
- **Dictamen Normativo:** `Dictamen B4` (Auditoría Normativa y Anillo 2 Banco de Reactivos)
- **Afecta a:** `app/Models/Subject.php`, `app/Repositories/Eloquent/EloquentQuestionRepository.php`, `app/Services/QuestionBankService.php`, `app/Services/ExamAssemblyService.php`, `app/Http/Requests/SubjectMergeRequest.php`, `app/Http/Requests/SubjectSuggestRequest.php`, `app/Http/Controllers/QuestionController.php`, `routes/web.php`, `MPW-V2 §6.1`

---

## 1. Contexto y Problema

El Banco de Reactivos almacenaba la materia únicamente como texto libre en `acs_questions.subject_tag`, lo que provocaba fragmentación de catálogo por diferencias tipográficas (`"Programación Web"`, `"Programacion web"`, `"programación  web"`). Esta dispersión afectaba:
1. El filtrado multidimensional en el Banco de Reactivos y la consistencia visual de etiquetas.
2. El armado por sección en `ExamAssemblyService` (`subject_tag` en `acs_exam_sections` con cota incongruente de `max:255` frente a `max:100`).
3. La agregación de métricas KPI de materias del docente sin consultas N+1.

---

## 2. Decisión de Arquitectura

1. **Entidad Normalizada `Subject` (`acs_subjects`) y Vínculo Dual Compatible:**
   - Se introduce `App\Models\Subject` (Paso 1) con `Subject::cleanDisplayName()` y `Subject::normalizeName()` (remoción de acentos/diacríticos, colapso de espacios y minúsculas), preservando `subject_id` nullable junto con `subject_tag` canonizado tanto en `acs_questions` como en `acs_exam_sections`.
   - `QuestionRepositoryInterface` expone `resolveOrCreateSubject()`, `getSubjectsWithStatsForTeacher()`, `findSimilarSubjects()`, `mergeTeacherSubjects()` y `getBankSummaryMetrics()`.

2. **Resolución Automática en Flujos Transaccionales:**
   - `QuestionBankService` resuelve la materia en `createQuestion()`, `updateQuestion()`, `createQuestionBatch()` y `commitBatchImport()`, asignando simultáneamente `subject_id` y `subject_tag` canonizado.
   - `ExamAssemblyService` persiste `subject_id` y `subject_tag` en apartados y filtra candidatos aleatorios de sección considerando ambos identificadores; `ExamSectionStoreRequest` homologa la longitud máxima de `subject_tag` a `max:100`.

3. **Detección Fuzzy y Fusión Segura de Duplicados:**
   - **Sugerencias JSON (`GET /questions/subjects/suggestions`):** Validado por `SubjectSuggestRequest`, retorna `['suggestions' => [['id' => int, 'name' => string], ...]]` mediante `QuestionBankService::suggestSubjectsForTeacher()`.
   - **Previsualización de Importación:** `QuestionBankService::previewBatchImport()` reporta `subject_warnings` cuando detecta nombres en el archivo similares a materias ya existentes del docente.
   - **Fusión Transaccional (`POST /questions/subjects/merge`):** Validado por `SubjectMergeRequest` (`Rule::exists('acs_subjects', 'id')->where('teacher_id', ...)` para `source_subject_ids` y limpieza Unicode de `target_name`), consolida los reactivos y apartados hacia el nombre canónico mediante `mergeSubjects()`.

4. **Panel de Métricas KPI sin N+1 (`getSubjectsDashboard`):**
   - `QuestionController::index()` expone `subjectsStats`, `summaryMetrics` y `quotaSummary` alimentados desde consultas agregadas en repositorio y servicio SaaS, sin consultas por fila ni lazy loading (`PreventLazyLoading`).

---

## 3. Consecuencias y Beneficios

- **Consistencia de Catálogo:** Erradica duplicados tipográficos por acentos, mayúsculas o espacios Unicode invisibles manteniendo retrocompatibilidad con filtros `subject_tag` y clientes API v1.
- **Trazabilidad entre Banco y Exámenes:** Permite armar apartados aleatorios filtrados por materia normalizada sin romper exámenes históricos.
- **Seguridad Multi-Docente:** Tanto las sugerencias como la fusión de materias están estrictamente acotadas al `teacher_id` autenticado.
