<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionBankManagementTest.php
 * Ubicación:      tests/Feature/QuestionBankManagementTest.php
 * Requerimiento:  Fase 5 - Certificación QA del Banco de Reactivos (RF-BAN,
 *                 ADR-003 Copy-On-Write, Aislamiento IDOR, Filtros, Paginación,
 *                 Importador CSV y SoftDeletes).
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\CodeItem;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionBankManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Certifica que el docente puede listar y filtrar sus reactivos por tipo, materia,
     *    estado de paráfrasis, búsqueda predictiva y selector de paginación.
     */
    public function test_teacher_can_list_and_filter_questions(): void
    {
        $teacher = $this->createTeacher();

        // 1.1 Crear reactivos polimórficos variados
        $mc = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Álgebra Booleana y Compuertas Lógicas',
            'stem' => '¿Cuál es el postulado central del álgebra de Boole?',
            'subject_tag' => 'Matemáticas Discretas',
        ]);

        $matching = $this->createMatchingQuestion($teacher, [
            'title' => 'Relación de Conceptos POO',
            'stem' => 'Relacione los pilares de la programación orientada a objetos:',
            'subject_tag' => 'Programación Avanzada',
        ]);
        $matching->paraphrases()->create(['stem' => 'Paráfrasis de POO para mitigar copias']);

        $open = $this->createOpenQuestion($teacher, [
            'title' => 'Concepto y Propiedades de Recursión',
            'stem' => 'Explique qué es la recursión y su condición de parada.',
            'subject_tag' => 'Programación Avanzada',
        ]);
        $open->paraphrases()->create(['stem' => 'Paráfrasis de recursión para variantes']);

        $code = $this->createCodeQuestion($teacher, [
            'title' => 'Algoritmo de Partición Quicksort',
            'stem' => 'Implemente la función de partición del algoritmo quicksort en Python.',
            'subject_tag' => 'Estructuras de Datos',
        ]);

        // Crear reactivos adicionales para evaluar paginación (total = 11)
        for ($i = 1; $i <= 7; $i++) {
            $this->createMultipleChoiceQuestion($teacher, [
                'title' => "Reactivo Adicional {$i}",
                'stem' => "Enunciado del reactivo adicional {$i}",
                'subject_tag' => 'Física General',
            ]);
        }

        // 1.2 Filtro por tipo polimórfico (question_type=code)
        $codeFilterResponse = $this->actingAs($teacher)->get(route('questions.index', ['question_type' => 'code']));
        $codeFilterResponse->assertOk();
        $codeFilterResponse->assertViewHas('questions', function ($questions) use ($code): bool {
            return $questions->total() === 1
                && $questions->first()->id === $code->id
                && $questions->first()->question_type === 'code';
        });

        // 1.3 Filtro por materia / asignatura (subject_tag='Programación Avanzada')
        $subjectFilterResponse = $this->actingAs($teacher)->get(route('questions.index', ['subject_tag' => 'Programación Avanzada']));
        $subjectFilterResponse->assertOk();
        $subjectFilterResponse->assertViewHas('questions', function ($questions): bool {
            return $questions->total() === 2
                && $questions->every(fn (Question $q) => $q->subject_tag === 'Programación Avanzada');
        });

        // 1.4 Filtro por estado de paráfrasis: 'with' (con paráfrasis)
        $withParaphraseResponse = $this->actingAs($teacher)->get(route('questions.index', ['paraphrase_status' => 'with']));
        $withParaphraseResponse->assertOk();
        $withParaphraseResponse->assertViewHas('questions', function ($questions): bool {
            return $questions->total() === 2;
        });

        // 1.5 Filtro por estado de paráfrasis: 'without' (sin paráfrasis)
        $withoutParaphraseResponse = $this->actingAs($teacher)->get(route('questions.index', ['paraphrase_status' => 'without']));
        $withoutParaphraseResponse->assertOk();
        $withoutParaphraseResponse->assertViewHas('questions', function ($questions): bool {
            return $questions->total() === 9;
        });

        // 1.6 Búsqueda predictiva (search='Recursión')
        $searchResponse = $this->actingAs($teacher)->get(route('questions.index', ['search' => 'Recursión']));
        $searchResponse->assertOk();
        $searchResponse->assertViewHas('questions', function ($questions) use ($open): bool {
            return $questions->total() === 1
                && $questions->first()->id === $open->id
                && str_contains((string) $questions->first()->title, 'Recursión');
        });

        // 1.7 Selector de paginación (per_page=10 de 11 totales)
        $paginationResponse = $this->actingAs($teacher)->get(route('questions.index', ['per_page' => 10]));
        $paginationResponse->assertOk();
        $paginationResponse->assertViewHas('questions', function ($questions): bool {
            return $questions->perPage() === 10
                && $questions->count() === 10
                && $questions->total() === 11;
        });
    }

    /**
     * 2. Certifica el aislamiento multi-docente contra vulnerabilidades IDOR:
     *    Cualquier intento de un docente ajeno de consultar, editar, actualizar,
     *    eliminar, duplicar o alterar paráfrasis debe ser denegado con 403 Forbidden.
     */
    public function test_multi_teacher_isolation_idor_protection(): void
    {
        $teacherA = $this->createTeacher();
        $teacherB = $this->createTeacher();

        $questionA = $this->createMultipleChoiceQuestion($teacherA, [
            'title' => 'Reactivo Exclusivo de Docente A',
        ]);
        $paraphraseA = $questionA->paraphrases()->create([
            'stem' => 'Paráfrasis confidencial de Docente A',
        ]);

        // Docente B intenta acceder a show -> 403 Forbidden
        $this->actingAs($teacherB)
            ->get(route('questions.show', $questionA))
            ->assertForbidden();

        // Docente B intenta acceder a edit -> 403 Forbidden
        $this->actingAs($teacherB)
            ->get(route('questions.edit', $questionA))
            ->assertForbidden();

        // Docente B intenta actualizar el reactivo -> 403 Forbidden
        $this->actingAs($teacherB)
            ->put(route('questions.update', $questionA), [
                'title' => 'Intento de Alteración Ilícita',
                'stem' => 'Stem modificado por atacante',
                'question_type' => 'multiple_choice',
                'default_points' => 10,
                'default_time_seconds' => 60,
                'options' => ['Opción 1', 'Opción 2'],
                'correct_option_index' => 0,
            ])
            ->assertForbidden();

        // Docente B intenta eliminar el reactivo -> 403 Forbidden
        $this->actingAs($teacherB)
            ->delete(route('questions.destroy', $questionA))
            ->assertForbidden();

        // Docente B intenta duplicar el reactivo -> 403 Forbidden
        $this->actingAs($teacherB)
            ->post(route('questions.duplicate', $questionA))
            ->assertForbidden();

        // Docente B intenta agregar una paráfrasis al reactivo de Docente A -> 403 Forbidden
        $this->actingAs($teacherB)
            ->post(route('questions.paraphrase.store', $questionA), [
                'stem' => 'Paráfrasis inyectada por docente ajeno',
            ])
            ->assertForbidden();

        // Docente B intenta eliminar la paráfrasis de Docente A -> 403 Forbidden
        $this->actingAs($teacherB)
            ->delete(route('questions.paraphrase.destroy', ['question' => $questionA, 'paraphrase' => $paraphraseA]))
            ->assertForbidden();

        // Verificar que la base de datos no fue alterada
        $this->assertDatabaseHas('acs_questions', [
            'id' => $questionA->id,
            'title' => 'Reactivo Exclusivo de Docente A',
        ]);
        $this->assertDatabaseHas('acs_question_paraphrases', [
            'id' => $paraphraseA->id,
            'stem' => 'Paráfrasis confidencial de Docente A',
        ]);
    }

    /**
     * 3. Certifica que la vista de detalle GET /questions/{question} responda 200
     *    y renderice los elementos específicos de las 4 variantes polimórficas.
     */
    public function test_teacher_can_view_question_details_for_all_polymorphic_types(): void
    {
        $teacher = $this->createTeacher();

        $mcQuestion = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Detalle Opción Múltiple',
        ]);
        $matchingQuestion = $this->createMatchingQuestion($teacher, [
            'title' => 'Detalle Relación de Columnas',
        ]);
        $openQuestion = $this->createOpenQuestion($teacher, [
            'title' => 'Detalle Pregunta Abierta',
        ]);
        $codeQuestion = $this->createCodeQuestion($teacher, [
            'title' => 'Detalle Código Fuente',
        ]);

        // 3.1 Opción Múltiple
        $mcResponse = $this->actingAs($teacher)->get(route('questions.show', $mcQuestion));
        $mcResponse->assertOk();
        $mcResponse->assertSee('Detalle Opción Múltiple');
        $mcResponse->assertSee('Alternativas de Respuesta');
        $mcResponse->assertSee('Respuesta Correcta');

        // 3.2 Relación de Columnas
        $matchingResponse = $this->actingAs($teacher)->get(route('questions.show', $matchingQuestion));
        $matchingResponse->assertOk();
        $matchingResponse->assertSee('Detalle Relación de Columnas');
        $matchingResponse->assertSee('Correspondencia Biyectiva');
        $matchingResponse->assertSee('Columna A: Concepto / Premisa');

        // 3.3 Pregunta Abierta
        $openResponse = $this->actingAs($teacher)->get(route('questions.show', $openQuestion));
        $openResponse->assertOk();
        $openResponse->assertSee('Detalle Pregunta Abierta');
        $openResponse->assertSee('Respuesta Modelo Canónica');
        $openResponse->assertSee('Rúbrica de Evaluación');

        // 3.4 Código Fuente
        $codeResponse = $this->actingAs($teacher)->get(route('questions.show', $codeQuestion));
        $codeResponse->assertOk();
        $codeResponse->assertSee('Detalle Código Fuente');
        $codeResponse->assertSee('Configuración del Entorno de Código');
        $codeResponse->assertSee('Solución Canónica de Referencia');
        $codeResponse->assertSee('Instrucciones Técnicas');
    }

    /**
     * 4. Certifica la edición in-situ cuando un reactivo NO está vinculado a ningún examen:
     *    Se modifica directamente el registro existente conservando su ID original.
     */
    public function test_in_situ_update_when_question_has_no_exam_links(): void
    {
        $teacher = $this->createTeacher();

        $question = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Título In-Situ Original',
            'stem' => 'Enunciado in-situ original',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
        ]);

        $initialQuestionCount = Question::count();

        $updateResponse = $this->actingAs($teacher)->put(route('questions.update', $question), [
            'title' => 'Título In-Situ Actualizado',
            'stem' => 'Enunciado in-situ actualizado con nuevas premisas',
            'question_type' => 'multiple_choice',
            'subject_tag' => 'Bases de Datos Relacionales',
            'default_points' => 3.5,
            'default_time_seconds' => 90,
            'options' => ['Alternativa Alfa', 'Alternativa Beta', 'Alternativa Gamma'],
            'correct_option_index' => 1,
        ]);

        $updateResponse->assertRedirect(route('questions.show', $question));
        $updateResponse->assertSessionHas('status', 'Reactivo actualizado exitosamente.');

        // No deben haberse creado nuevas filas en la tabla raíz
        $this->assertSame($initialQuestionCount, Question::count());

        $question->refresh();
        $this->assertSame('Título In-Situ Actualizado', $question->title);
        $this->assertSame('Enunciado in-situ actualizado con nuevas premisas', $question->stem);
        $this->assertSame('Bases de Datos Relacionales', $question->subject_tag);
        $this->assertSame('3.50', number_format((float) $question->default_points, 2));
        $this->assertSame(90, $question->default_time_seconds);

        /** @var MultipleChoiceItem $variant */
        $variant = $question->questionable;
        $this->assertSame(1, $variant->correct_option_index);
        $this->assertCount(3, $variant->options_payload);
    }

    /**
     * 5. Certifica el patrón Copy-On-Write (ADR-003 / RF-BAN-010):
     *    Si el reactivo está vinculado a exámenes históricos o en curso, se preserva
     *    el original intacto, se crea una nueva versión con parent_question_id apuntando
     *    al original, se clona la variante polimórfica y se replican las paráfrasis anti-copia.
     */
    public function test_copy_on_write_versioning_when_question_is_linked_to_exam(): void
    {
        $teacher = $this->createTeacher();

        $originalQuestion = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Pregunta Histórica Inmutable',
            'stem' => 'Enunciado canónico vinculado a examen previo',
            'subject_tag' => 'Redes de Computadoras',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
        ]);

        // Registrar una paráfrasis anti-copia en la original
        $originalQuestion->paraphrases()->create([
            'stem' => 'Paráfrasis original vinculada para sorteo',
        ]);

        // Vincular el reactivo a un examen mediante ExamQuestion
        $this->linkQuestionToExam($teacher, $originalQuestion);

        $initialQuestionCount = Question::count();

        $updateResponse = $this->actingAs($teacher)->put(route('questions.update', $originalQuestion), [
            'title' => 'Pregunta Histórica Modificada Versión 2',
            'stem' => 'Enunciado actualizado para la versión 2',
            'question_type' => 'multiple_choice',
            'subject_tag' => 'Redes Avanzadas',
            'default_points' => 4.0,
            'default_time_seconds' => 120,
            'options' => ['Opción Nueva A', 'Opción Nueva B'],
            'correct_option_index' => 1,
        ]);

        // Debe haberse creado un nuevo registro en la tabla raíz
        $this->assertSame($initialQuestionCount + 1, Question::count());

        // La pregunta original debe permanecer INTACTA en base de datos
        $originalQuestion->refresh();
        $this->assertSame('Pregunta Histórica Inmutable', $originalQuestion->title);
        $this->assertSame('Enunciado canónico vinculado a examen previo', $originalQuestion->stem);
        $this->assertSame('2.00', number_format((float) $originalQuestion->default_points, 2));

        // Debe existir el nuevo reactivo versionado
        /** @var Question $newVersion */
        $newVersion = Question::query()
            ->where('parent_question_id', $originalQuestion->id)
            ->first();

        $this->assertNotNull($newVersion);
        $this->assertSame('Pregunta Histórica Modificada Versión 2', $newVersion->title);
        $this->assertSame('Enunciado actualizado para la versión 2', $newVersion->stem);
        $this->assertSame('Redes Avanzadas', $newVersion->subject_tag);
        $this->assertSame('4.00', number_format((float) $newVersion->default_points, 2));
        $this->assertSame(120, $newVersion->default_time_seconds);

        // La variante polimórfica debe ser una instancia independiente
        $this->assertNotEquals($originalQuestion->questionable_id, $newVersion->questionable_id);

        // Las paráfrasis anti-copia deben haber sido clonadas hacia la nueva versión
        $this->assertDatabaseHas('acs_question_paraphrases', [
            'question_id' => $newVersion->id,
            'stem' => 'Paráfrasis original vinculada para sorteo',
        ]);

        // Redirección hacia la vista de detalle de la nueva versión con mensaje explicativo
        $updateResponse->assertRedirect(route('questions.show', $newVersion));
        $updateResponse->assertSessionHas(
            'status',
            'Se generó una nueva versión (v2) del reactivo debido a que el original ya está vinculado a exámenes históricos.'
        );
    }

    /**
     * 6. Certifica que la duplicación atómica POST /questions/{question}/duplicate
     *    genera un nuevo reactivo con el sufijo '(Copia)' y una variante polimórfica replicada.
     */
    public function test_duplicate_question_creates_clone_with_copia_suffix(): void
    {
        $teacher = $this->createTeacher();

        $originalQuestion = $this->createMatchingQuestion($teacher, [
            'title' => 'Matrices y Determinantes',
            'stem' => 'Relacione las propiedades matriciales con sus definiciones:',
            'subject_tag' => 'Álgebra Lineal',
        ]);

        $duplicateResponse = $this->actingAs($teacher)->post(route('questions.duplicate', $originalQuestion));

        $duplicateResponse->assertRedirect(route('questions.index'));
        $duplicateResponse->assertSessionHas('status', "Reactivo duplicado exitosamente como 'Matrices y Determinantes (Copia)'.");

        /** @var Question $clone */
        $clone = Question::query()
            ->where('teacher_id', $teacher->id)
            ->where('title', 'Matrices y Determinantes (Copia)')
            ->first();

        $this->assertNotNull($clone);
        $this->assertNotEquals($originalQuestion->id, $clone->id);
        $this->assertNotEquals($originalQuestion->questionable_id, $clone->questionable_id);
        $this->assertSame($originalQuestion->stem, $clone->stem);
        $this->assertSame($originalQuestion->question_type, $clone->question_type);
        $this->assertSame($originalQuestion->subject_tag, $clone->subject_tag);

        // Verificar que la variante polimórfica replicada posea los pares correspondientes
        /** @var MatchingItem $originalItem */
        $originalItem = $originalQuestion->questionable;
        /** @var MatchingItem $clonedItem */
        $clonedItem = $clone->questionable;

        $this->assertSame($originalItem->pairs_payload, $clonedItem->pairs_payload);
    }

    /**
     * 7. Certifica ambas garantías de [ALTA-02] y la resolución 404 de [ALTA-01]:
     *    (a) Un reactivo vinculado a un examen publicado o con intentos bloquea DELETE con error 'question'
     *        y mantiene deleted_at === null.
     *    (b) Un reactivo vinculado únicamente a un examen borrador sin intentos permite SoftDelete,
     *        $examQuestion->fresh()->question sigue resolviendo vía withTrashed() sin lanzar null/500,
     *        y acceder por URL a show/edit del reactivo eliminado devuelve 404.
     */
    public function test_soft_delete_preserves_fk_integrity_with_historic_exam(): void
    {
        $teacher = $this->createTeacher();
        $student = User::create([
            'role' => 'student',
            'first_name' => 'Alumno',
            'last_name' => 'Histórico',
            'email' => uniqid('student-', true) . '@acode.test',
            'password' => 'Alumno123!',
            'is_active' => true,
        ]);

        // (a.1) Bloqueo ante examen publicado
        $publishedQuestion = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Reactivo En Examen Publicado',
        ]);
        $this->linkQuestionToExam($teacher, $publishedQuestion, true);

        $blockedPublishedResponse = $this->actingAs($teacher)
            ->from(route('questions.index'))
            ->delete(route('questions.destroy', $publishedQuestion));

        $blockedPublishedResponse->assertRedirect(route('questions.index'));
        $blockedPublishedResponse->assertSessionHasErrors('question');
        $this->assertNull($publishedQuestion->fresh()->deleted_at);
        $this->assertNotSoftDeleted('acs_questions', ['id' => $publishedQuestion->id]);

        // (a.2) Bloqueo ante examen no publicado pero con intentos de alumnos
        $attemptedQuestion = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Reactivo En Examen Con Intento',
        ]);
        $attemptedExamQuestion = $this->linkQuestionToExam($teacher, $attemptedQuestion, false);
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo Histórico',
            'access_folio' => 'HIST001',
            'is_closed' => false,
        ]);
        StudentAttempt::create([
            'exam_id' => $attemptedExamQuestion->exam_id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [$attemptedExamQuestion->id],
            'current_question_index' => 0,
            'started_at' => now(),
            'status' => 'submitted',
        ]);

        $blockedAttemptResponse = $this->actingAs($teacher)
            ->from(route('questions.index'))
            ->delete(route('questions.destroy', $attemptedQuestion));

        $blockedAttemptResponse->assertRedirect(route('questions.index'));
        $blockedAttemptResponse->assertSessionHasErrors('question');
        $this->assertNull($attemptedQuestion->fresh()->deleted_at);

        // (b) Examen borrador sin intentos permite SoftDelete preservando FK y withTrashed()
        $draftQuestion = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Reactivo En Borrador Sin Intentos',
        ]);
        $draftExamQuestion = $this->linkQuestionToExam($teacher, $draftQuestion, false);

        $deleteResponse = $this->actingAs($teacher)->delete(route('questions.destroy', $draftQuestion));

        $deleteResponse->assertRedirect(route('questions.index'));
        $deleteResponse->assertSessionHas('status', 'Reactivo eliminado del banco exitosamente.');

        // Debe haberse marcado como SoftDeleted en la base de datos
        $this->assertSoftDeleted('acs_questions', ['id' => $draftQuestion->id]);
        $this->assertTrue($draftQuestion->fresh()->trashed());

        // Ya no debe ser accesible en consultas ordinarias del banco de preguntas
        $this->assertNull(Question::query()->find($draftQuestion->id));
        $this->assertNotNull(Question::withTrashed()->find($draftQuestion->id));

        // La relación en acs_exam_question debe persistir intacta y resolver vía withTrashed()
        $this->assertDatabaseHas('acs_exam_question', [
            'question_id' => $draftQuestion->id,
        ]);
        $resolvedQuestion = $draftExamQuestion->fresh()->question;
        $this->assertNotNull($resolvedQuestion);
        $this->assertSame($draftQuestion->id, $resolvedQuestion->id);
        $this->assertTrue($resolvedQuestion->trashed());

        // [ALTA-01] Acceder por URL a un reactivo soft-deleted debe retornar 404 Not Found
        $this->actingAs($teacher)
            ->get(route('questions.show', $draftQuestion->id))
            ->assertNotFound();
        $this->actingAs($teacher)
            ->get(route('questions.edit', $draftQuestion->id))
            ->assertNotFound();
    }

    /**
     * 8. Certifica la creación de paráfrasis anti-copia (RF-BAN-010) y su posterior
     *    eliminación autorizada, validando la protección IDOR.
     */
    public function test_paraphrase_creation_and_deletion(): void
    {
        $teacher = $this->createTeacher();
        $foreignTeacher = $this->createTeacher();

        $question = $this->createMultipleChoiceQuestion($teacher, [
            'title' => 'Reactivo con Paráfrasis Anti-Copia',
        ]);

        // 8.1 Creación de paráfrasis
        $storeResponse = $this->actingAs($teacher)->post(route('questions.paraphrase.store', $question), [
            'stem' => 'Redacción alternativa generada para mitigar fraude en el aula',
        ]);

        $storeResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acs_question_paraphrases', [
            'question_id' => $question->id,
            'stem' => 'Redacción alternativa generada para mitigar fraude en el aula',
        ]);

        /** @var QuestionParaphrase $paraphrase */
        $paraphrase = $question->paraphrases()->first();
        $this->assertNotNull($paraphrase);

        // 8.2 Intento de eliminación por docente ajeno (IDOR) -> 403 Forbidden
        $idorDeleteResponse = $this->actingAs($foreignTeacher)->delete(
            route('questions.paraphrase.destroy', ['question' => $question, 'paraphrase' => $paraphrase])
        );
        $idorDeleteResponse->assertForbidden();
        $this->assertDatabaseHas('acs_question_paraphrases', ['id' => $paraphrase->id]);

        // 8.3 Eliminación exitosa por el docente autor
        $authorizedDeleteResponse = $this->actingAs($teacher)->delete(
            route('questions.paraphrase.destroy', ['question' => $question, 'paraphrase' => $paraphrase])
        );
        $authorizedDeleteResponse->assertRedirect(route('questions.show', $question));
        $authorizedDeleteResponse->assertSessionHas('status', 'Paráfrasis eliminada.');
        $this->assertDatabaseMissing('acs_question_paraphrases', ['id' => $paraphrase->id]);
    }

    /**
     * 9. Certifica la descarga de la plantilla institucional CSV y la importación
     *    masiva por lotes de reactivos para las 4 variantes polimórficas.
     */
    public function test_csv_template_download_and_batch_import(): void
    {
        $teacher = $this->createTeacher();

        // 9.1 Descarga de plantilla institucional CSV
        $templateResponse = $this->actingAs($teacher)->get(route('questions.template.csv'));
        $templateResponse->assertOk();
        $templateResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csvTemplate = $templateResponse->getContent();
        $this->assertNotEmpty($csvTemplate);
        $this->assertStringContainsString('tipo,titulo,enunciado,materia', $csvTemplate);

        // 9.2 Importación por lotes de las 4 variantes polimórficas vía archivo CSV
        $csvContent = implode("\n", [
            'tipo,titulo,enunciado,materia,puntos,tiempo_segundos,opciones_o_definiciones,respuesta_correcta_o_solucion,instrucciones_o_rubrica',
            'multiple_choice,CSV Reactivo MC,¿Cuál es el protocolo de transporte orientado a conexión?,Redes,2.50,60,TCP|UDP|IP|ICMP,0,Seleccione una sola opción.',
            'matching,CSV Reactivo Relacion,Relacione conceptos de redes con sus capas del modelo OSI,Redes,3.00,120,Router=Capa 3 Red|Switch=Capa 2 Enlace|HTTP=Capa 7 Aplicacion,,Relacione todos los pares.',
            'open,CSV Reactivo Abierto,Explique detalladamente la técnica de Three-Way Handshake en TCP,Redes,4.00,180,,El handshake establece la conexión TCP mediante el intercambio SYN, SYN-ACK y ACK.,Mencione SYN, SYN-ACK y ACK.',
            'code,CSV Reactivo Codigo,Función de cálculo de máscara de red en Python,Redes,5.00,240,python,"def calcular_mascara():\n    return 24",Implemente la función solicitada.',
        ]);

        $uploadedFile = UploadedFile::fake()->createWithContent('reactivos_importacion_lote.csv', $csvContent);

        $importResponse = $this->actingAs($teacher)->post(route('questions.import'), [
            'csv_file' => $uploadedFile,
        ]);

        $importResponse->assertRedirect(route('questions.index'));
        $importResponse->assertSessionHas('status', 'Se importaron 4 reactivos exitosamente.');

        // Verificar persistencia de cada variante importada en base de datos
        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'CSV Reactivo MC',
            'question_type' => 'multiple_choice',
            'subject_tag' => 'Redes',
        ]);

        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'CSV Reactivo Relacion',
            'question_type' => 'matching',
            'subject_tag' => 'Redes',
        ]);

        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'CSV Reactivo Abierto',
            'question_type' => 'open',
            'subject_tag' => 'Redes',
        ]);

        $this->assertDatabaseHas('acs_questions', [
            'teacher_id' => $teacher->id,
            'title' => 'CSV Reactivo Codigo',
            'question_type' => 'code',
            'subject_tag' => 'Redes',
        ]);
    }

    // =========================================================================
    // Métodos Auxiliares de Soporte para la Suite de Certificación
    // =========================================================================

    private function createTeacher(): User
    {
        return User::create([
            'role' => 'teacher',
            'first_name' => 'Profesor',
            'last_name' => 'Certificador',
            'email' => uniqid('teacher-', true) . '@acode.test',
            'password' => 'Docente123!',
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createMultipleChoiceQuestion(User $teacher, array $attributes = []): Question
    {
        $variant = MultipleChoiceItem::create([
            'options_payload' => [
                ['index' => 0, 'text' => 'Alternativa A'],
                ['index' => 1, 'text' => 'Alternativa B'],
                ['index' => 2, 'text' => 'Alternativa C'],
            ],
            'correct_option_index' => 0,
        ]);

        return Question::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Reactivo Opción Múltiple Canónico',
            'stem' => 'Seleccione la alternativa correcta.',
            'question_type' => 'multiple_choice',
            'subject_tag' => 'Informática',
            'default_points' => 2.0,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $variant->id,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createMatchingQuestion(User $teacher, array $attributes = []): Question
    {
        $variant = MatchingItem::create([
            'left_column_payload' => [
                ['id' => 'L1', 'text' => 'Concepto 1'],
                ['id' => 'L2', 'text' => 'Concepto 2'],
            ],
            'right_column_payload' => [
                ['id' => 'R1', 'text' => 'Definición 1'],
                ['id' => 'R2', 'text' => 'Definición 2'],
            ],
            'pairs_payload' => [
                ['left_id' => 'L1', 'right_id' => 'R1'],
                ['left_id' => 'L2', 'right_id' => 'R2'],
            ],
        ]);

        return Question::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Reactivo Relación de Columnas Canónico',
            'stem' => 'Relacione cada concepto con su definición adecuada.',
            'question_type' => 'matching',
            'subject_tag' => 'Informática',
            'default_points' => 3.0,
            'default_time_seconds' => 120,
            'questionable_type' => MatchingItem::class,
            'questionable_id' => $variant->id,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createOpenQuestion(User $teacher, array $attributes = []): Question
    {
        $variant = OpenItem::create([
            'reference_answer' => 'La respuesta modelo canónica esperada por la rúbrica.',
            'evaluation_rubric' => 'Explicación detallada con palabras clave y coherencia.',
        ]);

        return Question::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Reactivo Abierto Canónico',
            'stem' => 'Desarrolle su respuesta de manera concisa.',
            'question_type' => 'open',
            'subject_tag' => 'Informática',
            'default_points' => 4.0,
            'default_time_seconds' => 180,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $variant->id,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createCodeQuestion(User $teacher, array $attributes = []): Question
    {
        $variant = CodeItem::create([
            'programming_language' => 'python',
            'is_find_error' => false,
            'starter_code' => null,
            'reference_solution' => "def solution():\n    return True",
            'instructions' => 'Escriba la función requerida respetando la firma indicada.',
        ]);

        return Question::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Reactivo Código Fuente Canónico',
            'stem' => 'Escriba un fragmento de código que cumpla la especificación.',
            'question_type' => 'code',
            'subject_tag' => 'Programación',
            'default_points' => 5.0,
            'default_time_seconds' => 240,
            'questionable_type' => CodeItem::class,
            'questionable_id' => $variant->id,
        ], $attributes));
    }

    private function linkQuestionToExam(User $teacher, Question $question, bool $isPublished = true): ExamQuestion
    {
        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen con Reactivo Vinculado',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => $isPublished,
        ]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Apartado Principal',
            'question_type' => $question->question_type,
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        return ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => $question->default_time_seconds,
            'points_weight' => $question->default_points,
            'sort_order' => 0,
        ]);
    }
}
