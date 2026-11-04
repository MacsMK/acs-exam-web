# ADR-028: Preservación de Evidencia Académica y Borrado Lógico (SoftDeletes) en Entidades del Banco de Reactivos (`RF-BAN-011`)

> **Estado:** Aceptado  
> **Fecha:** 2026-11-04  
> **Decisores:** Antigravity (Coordinador General), `architecture-quality-auditor`, `normative-compliance-auditor`  
> **Consultados:** `backend-laravel-specialist`, `db-eloquent-architect`, `qa-testing-engineer`  
> **Informados:** Equipo 4 (A-Code Solutions), Dirección de Calidad  
> **Módulos Impactados:** `Módulo 5 (Banco de Preguntas)`, `database/migrations/2026_11_04_000001_add_soft_deletes_to_acs_questions_table.php`, `app/Models/Question.php`, `app/Repositories/Contracts/QuestionRepositoryInterface.php`, `app/Repositories/Eloquent/EloquentQuestionRepository.php`, `app/Services/QuestionBankService.php`, `app/Http/Controllers/QuestionController.php`  
> **Requerimientos Relacionados:** `RF-BAN-011`, `RF-EVA-009`, `MBD-V4` (§3.4, §4.2), `MPW-V2` (§2.4, §6.1, §6.7), `MC-V2` (§2.4.3).

---

## 1. Contexto y Problemática

En el modelo relacional de la plataforma SaaS evaluadora, la tabla `acs_exam_question` actúa como una entidad pivote enriquecida que vincula un reactivo del banco (`question_id`) con un examen y un apartado específico (`exam_id`, `exam_section_id`), asignándole tiempo límite, ponderación y orden de presentación. A su vez, dicha tabla se encuentra estrechamente enlazada con las respuestas enviadas por los alumnos (`acs_student_answers`).

Para proteger la integridad referencial y evitar la pérdida accidental de datos de auditoría, la clave foránea `question_id` en `acs_exam_question` fue definida originalmente con la restricción estándar:
```php
$table->foreignId('question_id')->constrained('acs_questions')->restrictOnDelete();
```

Sin embargo, esta restricción provocaba un conflicto crítico en el flujo de negocio del docente al intentar eliminar un reactivo (`RF-BAN-011`):
- Cuando un docente intentaba eliminar un reactivo de su banco personal que ya hubiese sido utilizado en algún examen previo (histórico o concluido), PostgreSQL disparaba una excepción de integridad referencial (`QueryException: Integrity constraint violation - 23503`), provocando un error **HTTP 500** en el panel web.
- El docente quedaba imposibilitado para depurar o limpiar su banco de reactivos obsoletos o desactualizados.

### Opciones Analizadas y Descartadas

1. **Adopción de Eliminación en Cascada (`cascadeOnDelete()`):**  
   *Descartada de inmediato.* Si la eliminación de un reactivo de la tabla raíz `acs_questions` disparara la eliminación en cascada en `acs_exam_question` y consecuentemente en `acs_student_answers`, se destruirían de forma irrecuperable los registros de evaluaciones presentadas por los alumnos, alterando promedios históricos y violando flagrantemente las normativas institucionales de preservación de evidencia académica auditable (`MC-V2`).
2. **Bloqueo Rígido con Error de Cliente (HTTP 400 / 409 Conflict):**  
   *Descartada.* Notificar al docente que *"no puede eliminar este reactivo porque ya fue utilizado en un examen"* degrada severamente la experiencia de usuario (UX). Un profesor no debe estar obligado a mantener en su catálogo activo de preguntas visibles reactivos que ya no desea impartir en ciclos escolares futuros.

---

## 2. Decisión de Diseño

Se resolvió implementar un esquema de **Borrado Lógico Institucional (`SoftDeletes`)** para la entidad raíz del banco de reactivos (`Question`), compuesto por los siguientes pilares de ingeniería:

### 2.1. Migración de Base de Datos
Se generó la migración `database/migrations/2026_11_04_000001_add_soft_deletes_to_acs_questions_table.php`, agregando una columna timestamp nullable indexada `deleted_at` a la tabla institucional `acs_questions`:
```php
Schema::table('acs_questions', function (Blueprint $table): void {
    $table->softDeletes();
});
```

### 2.2. Integración en el Modelo Eloquent (`Question.php`)
Se incorporó el trait nativo de Laravel `Illuminate\Database\Eloquent\SoftDeletes` en la clase `App\Models\Question`:
```php
class Question extends Model
{
    use SoftDeletes;
    ...
}
```
Esto asegura que, por defecto, cualquier consulta Eloquent ordinaria (`Question::query()`, `all()`, `find()`, `where()`) aplique de forma transparente la condición global `WHERE deleted_at IS NULL`.

### 2.3. Contrato e Implementación en la Capa de Repositorios
Se formalizó el método de borrado seguro en `QuestionRepositoryInterface` y su implementación en `EloquentQuestionRepository`:
```php
/**
 * Elimina lógicamente un reactivo (Soft Delete).
 *
 * @param Question $question Reactivo a eliminar.
 * @return bool
 */
public function destroySafely(Question $question): bool
{
    return (bool) $question->delete();
}
```

### 2.4. Delegación en Servicio y Skinny Controller
En `QuestionBankService::deleteQuestion()`, se valida la pertenencia multi-docente (`abort_unless($question->teacher_id === $teacher->id, 403)`) y se delega la ejecución al repositorio mediante `$this->questionRepository->destroySafely($question)`.  
En `QuestionController::destroy()`, el controlador delega directamente al servicio y emite una redirección limpia con el mensaje de confirmación institucional: *"Reactivo eliminado del banco exitosamente."*

---

## 3. Consecuencias y Beneficios

- **Preservación Total de Evidencia Académica:**  
  La clave foránea `question_id` en `acs_exam_question` permanece 100% íntegra y válida en el motor PostgreSQL. Los exámenes aplicados en el pasado, sus ponderaciones y las respuestas de los estudiantes en `acs_student_answers` no sufren alteración alguna, garantizando auditabilidad absoluta.
- **Ergonomía y Catálogo Limpio para el Docente:**  
  Al aplicarse el borrado lógico, el reactivo desaparece de inmediato de los listados (`index`), búsquedas y filtros del docente. Si el docente vuelve a consultar su banco o crear exámenes nuevos, el reactivo ya no figura como elegible.
- **Trazabilidad y Capacidad de Recuperación Forense:**  
  Los reactivos borrados lógicamente pueden ser auditados o restaurados en el futuro mediante consultas con `Question::withTrashed()` o `Question::onlyTrashed()`, sustentando labores de soporte e inspección forense curricular.
- **Certificación QA Automatizada:**  
  La prueba unitaria/funcional `test_soft_delete_preserves_fk_integrity_with_historic_exam` en `tests/Feature/QuestionBankManagementTest.php` certifica que:
  1. Al invocar `DELETE /questions/{id}` sobre un reactivo asignado a un examen histórico, la respuesta es una redirección HTTP 302 con mensaje de éxito.
  2. La base de datos registra el reactivo como soft-deleted (`$this->assertSoftDeleted('acs_questions', ...)`).
  3. `Question::find($id)` retorna `null`, mientras que `Question::withTrashed()->find($id)` conserva el registro íntegro.
  4. La tupla en `acs_exam_question` persiste intacta sin violaciones de integridad ni excepciones en PostgreSQL.
