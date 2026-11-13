# ADR-027: Banco de Reactivos — Patrón Copy-On-Write para Exámenes Históricos, Vistas Canónicas, Filtros Multidimensionales SQL e Importador Masivo CSV

> **Estado:** Aceptado  
> **Fecha:** 2026-11-04  
> **Decisores:** Antigravity (Coordinador General), `architecture-quality-auditor`, `normative-compliance-auditor`  
> **Consultados:** `backend-laravel-specialist`, `db-eloquent-architect`, `frontend-ui-specialist`, `qa-testing-engineer`  
> **Informados:** Equipo 4 (A-Code Solutions), Dirección de Calidad  
> **Módulos Impactados:** `Módulo 5 (Banco de Preguntas)`, `app/Services/QuestionBankService.php`, `app/Http/Controllers/QuestionController.php`, `app/Http/Requests/QuestionUpdateRequest.php`, `app/Http/Requests/QuestionImportRequest.php`, `resources/views/questions/show.blade.php`, `resources/views/questions/edit.blade.php`, `resources/views/questions/index.blade.php`, `routes/web.php`  
> **Requerimientos Relacionados:** `RF-BAN-001` a `RF-BAN-014`, `RF-BAN-007`, `RF-BAN-010`, `ADR-003`, `MPW-V2` (§2.4, §4.1.2, §6.1, §6.3, §6.4.1, §6.7, Tabla 7, Tabla 10, Tabla 17), `MC-V2` (§2.4.3).

---

## 1. Contexto y Problemática

El Banco de Preguntas de la plataforma evaluadora SaaS requería resolver integralmente su ciclo de vida CRUD y elevar la ergonomía de la experiencia docente:

1. **Preservación de Evidencia e Inmutabilidad Histórica (ADR-003 / RF-BAN-010):**  
   Cuando un docente diseña un reactivo y lo incorpora a uno o varios exámenes ya publicados, armados o contestados (`acs_exam_question`), una modificación directa *in-situ* de las opciones, rúbricas o solución alteraría retrospectivamente los reactivos de exámenes concluidos o en curso. Esto invalidaría las calificaciones previas, los desgloses de respuestas de los estudiantes (`acs_student_answers`) y las auditorías académicas normativas.
2. **Deficiencias de Visualización y Navegación Detallada (RF-BAN-007):**  
   Los docentes carecían de una vista canónica de detalle (`questions/show.blade.php`) que permitiera auditar en profundidad la configuración de las 4 variantes polimórficas (`multiple_choice`, `matching`, `open`, `code`) y gestionar su catálogo de paráfrasis anti-copia sin necesidad de entrar a la interfaz de edición.
3. **Incertidumbre en la Pantalla de Edición (`questions/edit.blade.php`):**  
   Si el docente decidía editar un reactivo, el sistema no le advertía de forma preventiva si dicho reactivo estaba vinculado a exámenes históricos, creando confusión sobre si la modificación afectaría pruebas previas o generaría una nueva versión.
4. **Consultas Ineficientes y Falta de Filtrado Multidimensional:**  
   El listado de reactivos no contaba con capacidades de filtrado granular a nivel motor SQL. Se requería combinar en una sola consulta paginada: búsqueda textual por término (`search`), filtrado por variante polimórfica (`question_type`), materia registrada (`subject_tag`), existencia de variantes de redacción (`paraphrase_status`), ordenamiento dinámico (`sort_by`) y tamaño de página sanitizado (`per_page` de 10, 20 o 50 registros).
5. **Carga Operativa en la Captura Masiva:**  
   Para profesores con bancos extensos preexistentes, la captura unitaria representaba una fricción inaceptable. Se requerían mecanismos ágiles de productividad: duplicación atómica en un solo clic y un flujo de importación masiva mediante archivo CSV estandarizado con codificación universal.

---

## 2. Decisión de Diseño

Para solventar estos desafíos de forma armónica y bajo el estándar normativo `MPW-V2`, se establecieron las siguientes decisiones arquitectónicas:

### 2.1. Patrón Copy-On-Write (ADR-003 / RF-BAN-010) en `QuestionBankService`
Se implementó en `QuestionBankService::updateQuestion()` la lógica de bifurcación transparente:
- **Verificación de Enlace:** Se comprueba si el reactivo está asociado a algún examen mediante `examQuestions()->exists()`.
- **Edición In-Situ (`$isLinked === false`):** Si el reactivo no tiene vinculaciones a exámenes, se actualizan directamente en `DB::transaction()` los campos base (`title`, `stem`, `subject_tag`, `default_points`, `default_time_seconds`) y la entidad polimórfica hija correspondiente (`options_payload`, `pairs_payload`, `reference_answer`, etc.).
- **Bifurcación Copy-On-Write (`$isLinked === true`):** Si el reactivo forma parte de uno o más exámenes, se preserva el reactivo original intacto. En una transacción atómica:
  1. Se instancia una nueva variante polimórfica con los datos modificados.
  2. Se crea un nuevo registro en `acs_questions` asignando `'parent_question_id' => $question->id`, estableciendo el árbol genealógico del reactivo.
  3. Se replican automáticamente todas las paráfrasis anti-copia asociadas al reactivo padre hacia la nueva versión hija.
  4. El controlador redirige al detalle del nuevo reactivo emitiendo una notificación flash explicativa: *"Se generó una nueva versión (v2) del reactivo debido a que el original ya está vinculado a exámenes históricos."*

### 2.2. Vista Canónica de Detalle Polimórfico (`questions/show.blade.php`, `RF-BAN-007`)
Se implementó una vista enriquecida que presenta:
- Encabezado con insignias semánticas del tipo de reactivo, ponderación nominal, tiempo límite y badge del estado de inmutabilidad (reactivo vinculado a exámenes vs. libre de edición *in-situ*).
- Paneles dedicados según la variante polimórfica:
  - **Opción Múltiple:** Listado de alternativas de respuesta con resaltado visual institucional de la opción correcta.
  - **Relación de Columnas:** Visualización de correspondencia biyectiva entre premisas (Columna A) y definiciones asociadas (Columna B).
  - **Pregunta Abierta:** Presentación de la respuesta modelo canónica y desglose de la rúbrica de evaluación para cotejo con IA.
  - **Código Fuente:** Configuración del lenguaje, indicador de modalidad (*Desarrollo* vs *Auditoría/Debugging* con código starter), solución canónica y guía de instrucciones.
- Panel interactivo de paráfrasis anti-copia con formulario de registro ágil y eliminación autorizada.
- Barra de acciones con botones de editar, duplicar, eliminar y volver al listado según los 18 verbos oficiales (`MPW-V2 Tabla 10`).

### 2.3. Pantalla de Edición con Advertencia Preventiva (`questions/edit.blade.php`)
Se estructuró la vista de edición incorporando:
- **Banner de Alerta Contextual:** Si `$isLinkedToExams` es verdadero, la interfaz notifica al docente antes de enviar el formulario que cualquier cambio guardado originará automáticamente una nueva versión desacoplada (Copy-On-Write), garantizando absoluta transparencia.
- Rehidratación íntegra de los campos polimórficos de la variante activa, respetando el asistente guiado y previniendo colisiones de validación con inputs inactivos deshabilitados.

### 2.4. Filtros Multidimensionales y Paginación Sanitizada en SQL
En `EloquentQuestionRepository` y `QuestionBankService`:
- Se encapsularon los scopes `search()` (búsqueda insensible a mayúsculas con operador `ILIKE` en PostgreSQL sobre título, redacción y materia) y `filterBy()` (filtrado por `question_type`, `subject_tag`, `paraphrase_status` y ordenamiento por `points_asc`, `points_desc`, `title_asc`, `title_desc` o `recent`).
- En `QuestionController::index()`, se sanitizó el selector de elementos por página mediante `in_array($perPage, [10, 20, 50], true) ? $perPage : 20`.
- Se aplicó Eager Loading estricto `with(['questionable', 'paraphrases'])` y `withCount(['paraphrases', 'childQuestions'])`, erradicando por completo el problema N+1.

### 2.5. Herramientas de Productividad Docente (Duplicación y CSV Batch)
- **Duplicación en 1 Clic (`POST /questions/{question}/duplicate`):** Clona de forma transaccional el reactivo raíz y su variante polimórfica, agregando el sufijo `"(Copia)"` al título y redirigiendo al listado general.
- **Descarga de Plantilla CSV (`GET /questions/template/csv`):** Emisión con BOM UTF-8 `\xEF\xBB\xBF` para apertura limpia en Microsoft Excel sin problemas de encoding, incluyendo cabeceras y 4 filas representativas completas.
- **Importador Masivo por Lotes (`POST /questions/import`):** Valida el archivo mediante `QuestionImportRequest` (`mimes:csv,txt`, máx 5 MB), procesa las filas mediante flujos seguros de memoria (`fgetcsv`), valida tipos y persiste cada reactivo atómicamente retornando un resumen detallado de reactivos importados y filas omitidas.

---

## 3. Consecuencias y Beneficios

- **Auditoría e Integridad Académica Inviolable:** Las evaluaciones históricas y las respuestas de estudiantes permanecen intactas frente a ediciones docentes.
- **Ergonomía Superior de Usuario:** El docente cuenta con visibilidad total sobre sus preguntas, claridad sobre el comportamiento Copy-On-Write y opciones rápidas para clonar e importar reactivos.
- **Arquitectura Limpia y Desacoplada:** Cero lógica de negocio en controladores (Skinny Controllers), validaciones tipadas en `FormRequests` con mensajes canónicos en español y consultas delegadas en repositorios con carga ansiosa.
- **Certificación de Calidad Automatizada:**
  La implementación está 100% blindada por la suite de pruebas `tests/Feature/QuestionBankManagementTest.php` (**9 pruebas automatizadas, 103 aserciones**), certificando:
  1. `test_teacher_can_list_and_filter_questions`: Filtrado multidimensional y paginación 10/20/50.
  2. `test_multi_teacher_isolation_idor_protection`: Blindaje IDOR multi-docente con HTTP 403.
  3. `test_teacher_can_view_question_details_for_all_polymorphic_types`: Renderizado canónico de las 4 variantes en show.
  4. `test_in_situ_update_when_question_has_no_exam_links`: Edición *in-situ* sin enlaces a exámenes.
  5. `test_copy_on_write_versioning_when_question_is_linked_to_exam`: Copy-On-Write, generación de reactivo hijo y clonación de paráfrasis.
  6. `test_duplicate_question_creates_clone_with_copia_suffix`: Duplicación atómica con sufijo.
  7. `test_soft_delete_preserves_fk_integrity_with_historic_exam`: Integridad referencial con borrado lógico.
  8. `test_paraphrase_creation_and_deletion`: Ciclo de vida y protección de paráfrasis anti-copia.
  9. `test_csv_template_download_and_batch_import`: Descarga de plantilla con BOM UTF-8 e importación masiva de las 4 variantes.

---

## 4. Actualización de Alineación (Anillo 2 — Materias Normalizadas, Bloqueo COW, XLSX y Seguridad Exportación)
- **Protección Copy-On-Write y borrado seguro (`[ALTA-08]`, `[MEDIA-02]`):** `QuestionBankService::updateQuestion()` ejecuta bloqueo transaccional pesimista (`Question::whereKey()->lockForUpdate()`) y verifica vínculos mediante `QuestionRepositoryInterface::hasExamLinks()`. `deleteQuestion()` impide eliminar reactivos enlazados a exámenes activos o publicados (`hasActiveOrPublishedExamLinks()`), emitiendo `DomainException`.
- **Resolución de materias y métricas:** Al crear, editar o importar se resuelve `subject_id` y `subject_tag` canónico a través de `Subject::cleanDisplayName()` / `resolveOrCreateSubject()`, habilitando además captura rápida en lote (`QuestionBatchStoreRequest`, hasta 30 reactivos).
- **Importación CSV/TXT/XLSX en dos fases y exportación segura (`CWE-1236`):** Además de la plantilla CSV con BOM UTF-8 y saneamiento anti-inyección de fórmulas (`sanitizeSpreadsheetCellForExport()`), se añade soporte `.xlsx` (`PhpSpreadsheet` con `setReadDataOnly(true)`), tope estricto de 250 filas de datos, flujo de previsualización (`previewBatchImport`) con detección de materias similares y confirmación atómica (`commitBatchImport`). Véanse los detalles normativos completos en [ADR-053](ADR-053_Catalogo_Normalizado_Materias_y_Fusion_Fuzzy.md) y [ADR-055](ADR-055_Alineacion_Rutas_Questions_Productividad_Batch_y_Blindaje_Backend.md).
