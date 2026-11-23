<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamTeacherWorkflowTest.php
 * Ubicación:      tests/Feature/Exam/ExamTeacherWorkflowTest.php
 * Requerimiento:  RF-EXA, RW-11 a RW-27, RNF-SEG-004, ADR-027, ADR-043,
 *                 ADR-050, ADR-056 (Flujo Completo Docente de Exámenes)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Suite integral de certificación del flujo docente de
 *                 exámenes: tablero CRUD con KPIs y filtros, armado dual con
 *                 exclusión Copy-On-Write, RW-14, orden entre apartados,
 *                 ponderación base 100.00 decimal y conversión a 0-10,
 *                 checklist 5/5 de publicación, canal WebSocket, recoteje y
 *                 blindaje estricto de result_visibility_mode.
 * ============================================================================
 */

namespace Tests\Feature\Exam;

use App\Contracts\PushNotifierContract;
use App\Models\CodeItem;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\Subject;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\ExamAssemblyService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

class ExamTeacherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->app->instance(PushNotifierContract::class, new FakePushNotifier());
    }

    // ------------------------------------------------------------------
    // a) Ciclo de vida CRUD y tablero exams.index
    // ------------------------------------------------------------------

    public function test_teacher_dashboard_filters_and_kpis_enforce_isolation_and_counts(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $otherTeacher = $this->createTeacherWithProSubscription();

        $groupA = $this->createGroup($teacher, 'Grupo Alfa', 'ALFA01');
        $groupB = $this->createGroup($teacher, 'Grupo Beta', 'BETA01');
        $otherGroup = $this->createGroup($otherTeacher, 'Grupo Ajeno', 'AJEN01');

        $draftExam = $this->createExamRecord($teacher, [
            'title' => 'Parcial Cálculo Borrador',
            'description' => 'Temas de derivadas',
            'is_published' => false,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ], [$groupA->id]);

        $scheduledExam = $this->createExamRecord($teacher, [
            'title' => 'Examen Programado Redes',
            'is_published' => true,
            'starts_at' => now()->addHours(4),
            'ends_at' => now()->addDays(2),
        ], [$groupB->id]);

        $activeExam = $this->createExamRecord($teacher, [
            'title' => 'Examen Activo Álgebra',
            'is_published' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(3),
        ], [$groupA->id]);

        $closedExam = $this->createExamRecord($teacher, [
            'title' => 'Examen Cerrado Historia',
            'is_published' => true,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
        ], [$groupB->id]);

        $student = User::factory()->student()->create();
        $groupB->students()->attach($student->id, ['joined_at' => now()]);
        StudentAttempt::create([
            'exam_id' => $closedExam->id,
            'group_id' => $groupB->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(90),
            'pending_ai_count' => 1,
            'final_score' => null,
            'question_sequence_payload' => [],
        ]);

        // Examen de otro docente jamás debe contaminar KPIs ni resultados.
        $this->createExamRecord($otherTeacher, [
            'title' => 'Examen Secreto Otro Docente',
            'is_published' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ], [$otherGroup->id]);

        $response = $this->actingAs($teacher)->get(route('exams.index'));
        $response->assertOk();
        $response->assertViewHas('stats', [
            'total' => 4,
            'drafts' => 1,
            'scheduled' => 1,
            'published_active' => 1,
            'closed' => 1,
            'pending_recoteje_exams' => 1,
        ]);
        $response->assertSee($draftExam->title);
        $response->assertSee($activeExam->title);
        $response->assertDontSee('Examen Secreto Otro Docente');

        // Filtro por search (sin acentos y case-insensitive).
        $searchRes = $this->actingAs($teacher)->get(route('exams.index', ['search' => 'algebra']));
        $searchRes->assertOk();
        $searchRes->assertSee($activeExam->title);
        $searchRes->assertDontSee($draftExam->title);

        // Filtros por status.
        $this->actingAs($teacher)->get(route('exams.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee($draftExam->title)
            ->assertDontSee($activeExam->title);

        $this->actingAs($teacher)->get(route('exams.index', ['status' => 'scheduled']))
            ->assertOk()
            ->assertSee($scheduledExam->title)
            ->assertDontSee($activeExam->title);

        $this->actingAs($teacher)->get(route('exams.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee($activeExam->title)
            ->assertDontSee($closedExam->title);

        $this->actingAs($teacher)->get(route('exams.index', ['status' => 'closed']))
            ->assertOk()
            ->assertSee($closedExam->title)
            ->assertDontSee($activeExam->title);

        $this->actingAs($teacher)->get(route('exams.index', ['status' => 'pending_review']))
            ->assertOk()
            ->assertSee($closedExam->title)
            ->assertDontSee($draftExam->title);

        // Filtro por group_id.
        $this->actingAs($teacher)->get(route('exams.index', ['group_id' => $groupA->id]))
            ->assertOk()
            ->assertSee($draftExam->title)
            ->assertSee($activeExam->title)
            ->assertDontSee($scheduledExam->title);
    }

    public function test_exam_crud_lifecycle_edit_destroy_unpublish_close_and_release_results(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $foreignTeacher = $this->createTeacherWithProSubscription();
        $openGroup = $this->createGroup($teacher, 'Grupo Activo', 'OPEN01');
        $closedGroup = $this->createGroup($teacher, 'Grupo Cerrado', 'CLOS01', true);
        $student = User::factory()->student()->create();
        $openGroup->students()->attach($student->id, ['joined_at' => now()]);

        // 1. Alta vía exams.store con group_ids, escala y visibilidad.
        $storeResponse = $this->actingAs($teacher)->post(route('exams.store'), [
            'title' => 'Examen Inicial POO',
            'description' => 'Evaluación de objetos',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'hidden',
            'max_allowed_incidents' => 2,
            'starts_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(5)->format('Y-m-d H:i:s'),
            'group_ids' => [$openGroup->id],
        ]);

        $exam = Exam::where('title', 'Examen Inicial POO')->firstOrFail();
        $storeResponse->assertRedirect(route('exams.assemble', $exam));
        $this->assertFalse($exam->is_published);
        $this->assertFalse($exam->randomize_question_order);
        $this->assertSame('hidden', $exam->result_visibility_mode);
        $this->assertSame([$openGroup->id], $exam->groups()->pluck('acs_groups.id')->all());

        // Rechaza grupo cerrado en creación.
        $this->actingAs($teacher)->postJson(route('exams.store'), [
            'title' => 'Examen Grupo Cerrado',
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'max_allowed_incidents' => 1,
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
            'group_ids' => [$closedGroup->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['group_ids.0']);

        // 2. Edición (exams.edit / exams.update) + aislamiento IDOR.
        $this->actingAs($teacher)->get(route('exams.edit', $exam))->assertOk()->assertSee('Examen Inicial POO');
        $this->actingAs($foreignTeacher)->get(route('exams.edit', $exam))->assertForbidden();

        $this->actingAs($foreignTeacher)->putJson(route('exams.update', $exam), [
            'title' => 'Secuestrado',
            'grading_scale' => '0-100',
            'result_visibility_mode' => 'final_score',
            'max_allowed_incidents' => 1,
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(3)->toDateTimeString(),
        ])->assertForbidden();

        $this->actingAs($teacher)->put(route('exams.update', $exam), [
            'title' => 'Examen Actualizado POO',
            'description' => 'Actualizado',
            'grading_scale' => '0-100',
            'randomize_question_order' => true,
            'result_visibility_mode' => 'hidden',
            'max_allowed_incidents' => 4,
            'starts_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(6)->format('Y-m-d H:i:s'),
            'group_ids' => [$openGroup->id],
        ])->assertRedirect(route('exams.assemble', $exam));

        $exam->refresh();
        $this->assertSame('Examen Actualizado POO', $exam->title);
        $this->assertSame('0-100', $exam->grading_scale);
        $this->assertTrue($exam->randomize_question_order);

        // Prepara apartado con peso 100 para publicar.
        $mc = $this->createMultipleChoiceQuestion($teacher, 'Pregunta 1');
        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Apartado Único',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $mc->id,
            'time_limit_seconds' => 60,
            'points_weight' => 100.0,
            'sort_order' => 0,
        ]);

        // Publicar examen.
        $this->actingAs($teacher)->post(route('exams.publish', $exam))->assertRedirect(route('exams.index'));
        $this->assertTrue($exam->fresh()->is_published);

        // No se puede eliminar publicado -> 422.
        $this->actingAs($teacher)->deleteJson(route('exams.destroy', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exam']);

        // Despublicar con 0 intentos -> éxito.
        $this->actingAs($teacher)->post(route('exams.unpublish', $exam))->assertRedirect(route('exams.index'));
        $this->assertFalse($exam->fresh()->is_published);

        // Volver a publicar, ahora cerrar anticipadamente y liberar resultados ocultos.
        $exam->update(['starts_at' => now()->subMinutes(15)]);
        $this->actingAs($teacher)->post(route('exams.publish', $exam))->assertRedirect(route('exams.index'));

        $this->actingAs($teacher)->patch(route('exams.results.release', $exam))->assertRedirect();
        $this->assertSame('final_score', $exam->fresh()->result_visibility_mode);

        $this->actingAs($teacher)->post(route('exams.close', $exam))->assertRedirect(route('exams.index'));
        $this->assertTrue($exam->fresh()->isWindowClosed());

        // Con intento registrado: bloquea cambio de escala, despublicación y eliminación.
        StudentAttempt::create([
            'exam_id' => $exam->id,
            'group_id' => $openGroup->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now()->subMinute(),
            'question_sequence_payload' => [],
        ]);

        $this->actingAs($teacher)->putJson(route('exams.update', $exam), [
            'title' => 'Cambio escala bloqueado',
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'max_allowed_incidents' => 4,
            'starts_at' => $exam->starts_at->toDateTimeString(),
            'ends_at' => now()->addHours(3)->toDateTimeString(),
            'group_ids' => [$openGroup->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['grading_scale']);

        $this->actingAs($teacher)->postJson(route('exams.unpublish', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exam']);

        $exam->update(['is_published' => false]);
        $this->actingAs($teacher)->deleteJson(route('exams.destroy', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exam']);

        // Eliminar un borrador limpio sin intentos sí funciona y limpia relaciones.
        $deletableDraft = $this->createExamRecord($teacher, ['title' => 'Borrador Eliminable'], [$openGroup->id]);
        $deletableSection = ExamSection::create([
            'exam_id' => $deletableDraft->id,
            'title' => 'Sección temporal',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        ExamQuestion::create([
            'exam_id' => $deletableDraft->id,
            'exam_section_id' => $deletableSection->id,
            'question_id' => $mc->id,
            'time_limit_seconds' => 60,
            'points_weight' => 100.0,
            'sort_order' => 0,
        ]);

        $this->actingAs($foreignTeacher)->deleteJson(route('exams.destroy', $deletableDraft))->assertForbidden();

        $this->actingAs($teacher)->delete(route('exams.destroy', $deletableDraft))
            ->assertRedirect(route('exams.index'));

        $this->assertDatabaseMissing('acs_exams', ['id' => $deletableDraft->id]);
        $this->assertDatabaseMissing('acs_exam_sections', ['id' => $deletableSection->id]);
        $this->assertDatabaseMissing('acs_exam_group', ['exam_id' => $deletableDraft->id]);
    }

    // ------------------------------------------------------------------
    // b) Armado Dual e Inventario (exams.assemble)
    // ------------------------------------------------------------------

    public function test_dual_assembly_inventory_cow_exclusion_proposals_manual_sections_and_rw14(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $group = $this->createGroup($teacher, 'Grupo Armado', 'ARMA01');
        $subjectMath = Subject::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matemáticas',
            'normalized_name' => 'matematicas',
        ]);

        $rootMc = $this->createMultipleChoiceQuestion($teacher, 'MC Raíz', $subjectMath);
        // Hija Copy-On-Write de $rootMc: comparte linaje y NO debe sumarse como candidata independiente una vez usada la raíz.
        $cowChildMc = $this->createMultipleChoiceQuestion($teacher, 'MC Hija COW', $subjectMath, $rootMc->id);
        $independentMc1 = $this->createMultipleChoiceQuestion($teacher, 'MC Independiente 1', $subjectMath);
        $independentMc2 = $this->createMultipleChoiceQuestion($teacher, 'MC Independiente 2', $subjectMath);
        $openQ = $this->createOpenQuestion($teacher, 'Abierta 1', $subjectMath);

        $exam = $this->createExamRecord($teacher, [
            'title' => 'Examen Armado Dual',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(4),
        ], [$group->id]);

        // 1. Inventario inicial: 4 MC (raíz + hija + 2 independientes) y 1 Open.
        $assembleInitial = $this->actingAs($teacher)->get(route('exams.assemble', $exam));
        $assembleInitial->assertOk();
        $assembleInitial->assertViewHas('bankInventory', function (array $inv): bool {
            return $inv['total'] === 5
                && $inv['by_type']['multiple_choice'] === 4
                && $inv['by_type']['open'] === 1;
        });

        // 2. Generar propuesta válida (2 MC) y verificar preservación en sesión si la siguiente propuesta falla.
        $this->actingAs($teacher)->post(route('exams.proposals.generate', $exam), [
            'title' => 'Apartado Sorteado',
            'question_type' => 'multiple_choice',
            'subject_id' => $subjectMath->id,
            'requested_quantity' => 2,
        ])->assertRedirect(route('exams.assemble', $exam));

        $sessionKey = "exam_section_proposal_{$exam->id}";
        $originalProposal = session($sessionKey);
        $this->assertIsArray($originalProposal);
        $this->assertCount(2, $originalProposal['question_ids']);

        // Solicitar 99 reactivos falla validación, pero NO borra la propuesta previa en sesión.
        $this->actingAs($teacher)->from(route('exams.assemble', $exam))
            ->post(route('exams.proposals.generate', $exam), [
                'title' => 'Propuesta Fallida',
                'question_type' => 'multiple_choice',
                'subject_id' => $subjectMath->id,
                'requested_quantity' => 99,
            ])
            ->assertRedirect(route('exams.assemble', $exam))
            ->assertSessionHasErrors(['requested_quantity']);

        $this->assertSame($originalProposal, session($sessionKey));

        // 3. Agregar manualmente $rootMc en un nuevo apartado y verificar exclusión de linaje COW.
        $this->actingAs($teacher)->post(route('exams.questions.manual.store', $exam), [
            'new_section_title' => 'Apartado Manual MC',
            'question_ids' => [$rootMc->id],
        ])->assertRedirect(route('exams.assemble', $exam));

        $manualSection = ExamSection::where('exam_id', $exam->id)->where('title', 'Apartado Manual MC')->firstOrFail();

        // Inventario ahora excluye tanto $rootMc como su hija $cowChildMc: solo quedan 2 MC independientes + 1 Open = 3.
        $this->actingAs($teacher)->get(route('exams.assemble', $exam))
            ->assertOk()
            ->assertViewHas('bankInventory', function (array $inv): bool {
                return $inv['total'] === 3
                    && $inv['by_type']['multiple_choice'] === 2
                    && $inv['by_type']['open'] === 1;
            });

        // Intentar agregar manualmente la hija COW debe ser rechazado con 422.
        $this->actingAs($teacher)->postJson(route('exams.questions.manual.store', $exam), [
            'exam_section_id' => $manualSection->id,
            'question_ids' => [$cowChildMc->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['question_ids']);

        // Intentar agregar una pregunta de tipo distinto (open) al apartado MC -> 422.
        $this->actingAs($teacher)->postJson(route('exams.questions.manual.store', $exam), [
            'exam_section_id' => $manualSection->id,
            'question_ids' => [$openQ->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['question_ids']);

        // Agregar $independentMc1 al apartado manual existente -> sort_order consecutivo.
        $this->actingAs($teacher)->post(route('exams.questions.manual.store', $exam), [
            'exam_section_id' => $manualSection->id,
            'question_ids' => [$independentMc1->id],
        ])->assertRedirect(route('exams.assemble', $exam));

        $this->assertSame(2, $manualSection->fresh()->requested_quantity);
        $this->assertSame([0, 1], $manualSection->examQuestions()->orderBy('sort_order')->pluck('sort_order')->all());

        // 4. RW-14 Re-sorteo puntual preservando exactamente points_weight y time_limit_seconds.
        $targetEq = $manualSection->examQuestions()->where('question_id', $independentMc1->id)->firstOrFail();
        $targetEq->update([
            'points_weight' => 42.50,
            'time_limit_seconds' => 185,
        ]);

        $this->actingAs($teacher)->post(route('exams.questions.reshuffle', [$exam, $targetEq]))
            ->assertRedirect(route('exams.assemble', $exam));

        $targetEq->refresh();
        $this->assertSame($independentMc2->id, (int) $targetEq->question_id);
        $this->assertEquals(42.50, (float) $targetEq->points_weight);
        $this->assertSame(185, (int) $targetEq->time_limit_seconds);

        // 5. Editar y eliminar apartado + aceptar propuesta tras eliminaciones (MAX(sort_order)+1).
        // Crear una segunda sección abierta manualmente para probar actualización, eliminación y reindexado.
        $this->actingAs($teacher)->post(route('exams.questions.manual.store', $exam), [
            'new_section_title' => 'Apartado Abierto Temporal',
            'question_ids' => [$openQ->id],
        ])->assertRedirect(route('exams.assemble', $exam));

        $openSection = ExamSection::where('exam_id', $exam->id)->where('title', 'Apartado Abierto Temporal')->firstOrFail();

        $this->actingAs($teacher)->put(route('exams.sections.update', [$exam, $openSection]), [
            'title' => 'Apartado Abierto Renombrado',
            'subject_tag' => 'Matemáticas',
        ])->assertRedirect(route('exams.assemble', $exam));

        $this->assertSame('Apartado Abierto Renombrado', $openSection->fresh()->title);

        // Eliminar el primer apartado ($manualSection) -> $openSection se reindexa a sort_order = 0.
        $this->actingAs($teacher)->delete(route('exams.sections.destroy', [$exam, $manualSection]))
            ->assertRedirect(route('exams.assemble', $exam));

        $this->assertDatabaseMissing('acs_exam_sections', ['id' => $manualSection->id]);
        $this->assertSame(0, (int) $openSection->fresh()->sort_order);

        // Simular hueco en sort_order de sección (ej. sort_order = 2) y aceptar propuesta -> MAX(sort_order)+1 = 3.
        $openSection->update(['sort_order' => 2]);
        $subjectPhysics = Subject::create([
            'teacher_id' => $teacher->id,
            'name' => 'Física',
            'normalized_name' => 'fisica',
        ]);
        $this->createMultipleChoiceQuestion($teacher, 'Física MC 1', $subjectPhysics);
        $this->createMultipleChoiceQuestion($teacher, 'Física MC 2', $subjectPhysics);

        $this->actingAs($teacher)->post(route('exams.proposals.generate', $exam), [
            'title' => 'Propuesta Tras Eliminación',
            'question_type' => 'multiple_choice',
            'subject_id' => $subjectPhysics->id,
            'requested_quantity' => 2,
        ])->assertRedirect(route('exams.assemble', $exam));

        $this->actingAs($teacher)->post(route('exams.proposals.accept', $exam))
            ->assertRedirect(route('exams.assemble', $exam));

        $acceptedSection = ExamSection::where('exam_id', $exam->id)
            ->where('title', 'Propuesta Tras Eliminación')
            ->firstOrFail();

        $this->assertSame(3, (int) $acceptedSection->sort_order);
        $this->assertSame([0, 1], $acceptedSection->examQuestions()->orderBy('sort_order')->pluck('sort_order')->all());

        // 6. Candado ensureStructureMutable: bloquea si está publicado con ventana iniciada o si tiene intentos.
        $exam->update([
            'is_published' => true,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHours(2),
        ]);
        $lockedEq = $acceptedSection->examQuestions()->firstOrFail();

        $this->actingAs($teacher)->postJson(route('exams.proposals.generate', $exam), [
            'title' => 'Bloqueado',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['exam']);

        $this->actingAs($teacher)->putJson(route('exams.sections.update', [$exam, $acceptedSection]), [
            'title' => 'No mutable',
        ])->assertStatus(422)->assertJsonValidationErrors(['exam']);

        $this->actingAs($teacher)->deleteJson(route('exams.sections.destroy', [$exam, $acceptedSection]))
            ->assertStatus(422)->assertJsonValidationErrors(['exam']);

        $this->actingAs($teacher)->deleteJson(route('exams.questions.destroy', [$exam, $lockedEq]))
            ->assertStatus(422)->assertJsonValidationErrors(['exam']);

        $this->actingAs($teacher)->postJson(route('exams.questions.reshuffle', [$exam, $lockedEq]))
            ->assertStatus(422)->assertJsonValidationErrors(['exam']);

        // También bloquea si no está publicado pero ya tiene intentos registrados.
        $student = User::factory()->student()->create();
        $group->students()->attach($student->id, ['joined_at' => now()]);
        $exam->update(['is_published' => false]);
        StudentAttempt::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'question_sequence_payload' => [],
        ]);

        $this->actingAs($teacher)->postJson(route('exams.questions.manual.store', $exam), [
            'exam_section_id' => $acceptedSection->id,
            'question_ids' => [$rootMc->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['exam']);
    }

    // ------------------------------------------------------------------
    // c) Secuencia ordenada entre apartados
    // ------------------------------------------------------------------

    public function test_build_question_sequence_orders_by_section_sort_order_before_question_sort_order(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $exam = $this->createExamRecord($teacher, [
            'title' => 'Examen Secuencia Secciones',
            'randomize_question_order' => false,
        ]);

        $sectionA = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección A',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 2,
            'sort_order' => 0,
        ]);
        $sectionB = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección B',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 2,
            'sort_order' => 1,
        ]);

        $q1 = $this->createMultipleChoiceQuestion($teacher, 'Q1');
        $q2 = $this->createMultipleChoiceQuestion($teacher, 'Q2');
        $q3 = $this->createMultipleChoiceQuestion($teacher, 'Q3');
        $q4 = $this->createMultipleChoiceQuestion($teacher, 'Q4');

        // Insertar intercalados (B primero, A después) para probar que el orden de secciones prevalece sobre id y question.sort_order.
        $b1 = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $sectionB->id,
            'question_id' => $q3->id,
            'time_limit_seconds' => 60,
            'points_weight' => 25,
            'sort_order' => 1,
        ]);
        $a1 = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $sectionA->id,
            'question_id' => $q2->id,
            'time_limit_seconds' => 60,
            'points_weight' => 25,
            'sort_order' => 1,
        ]);
        $b0 = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $sectionB->id,
            'question_id' => $q4->id,
            'time_limit_seconds' => 60,
            'points_weight' => 25,
            'sort_order' => 0,
        ]);
        $a0 = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $sectionA->id,
            'question_id' => $q1->id,
            'time_limit_seconds' => 60,
            'points_weight' => 25,
            'sort_order' => 0,
        ]);

        /** @var ExamAssemblyService $assemblyService */
        $assemblyService = $this->app->make(ExamAssemblyService::class);
        $sequence = $assemblyService->buildQuestionSequence($exam);

        $this->assertSame([$a0->id, $a1->id, $b0->id, $b1->id], $sequence);

        // RF-EXA-015: en orden aleatorio cada apartado conserva su lugar y solo se mezclan sus preguntas
        $exam->update(['randomize_question_order' => true]);
        for ($run = 0; $run < 20; $run++) {
            $random = $assemblyService->buildQuestionSequence($exam->fresh());
            $this->assertEqualsCanonicalizing([$a0->id, $a1->id], array_slice($random, 0, 2));
            $this->assertEqualsCanonicalizing([$b0->id, $b1->id], array_slice($random, 2, 2));
        }
    }

    // ------------------------------------------------------------------
    // d) Ponderación base 100.00 decimal y conversión automática a 0-10
    // ------------------------------------------------------------------

    public function test_decimal_weights_100_persist_and_normalize_scores_for_0_10_and_0_100_scales(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $group = $this->createGroup($teacher, 'Grupo Pesos', 'PESO01');
        $studentPass10 = User::factory()->student()->create();
        $studentFail10 = User::factory()->student()->create();
        $studentPass100 = User::factory()->student()->create();
        $group->students()->attach([$studentPass10->id, $studentFail10->id, $studentPass100->id], ['joined_at' => now()]);

        $exam = $this->createExamRecord($teacher, [
            'title' => 'Examen Ponderación Decimal',
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(4),
        ], [$group->id]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Única',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 3,
            'sort_order' => 0,
        ]);

        $q1 = $this->createMultipleChoiceQuestion($teacher, 'P1');
        $q2 = $this->createMultipleChoiceQuestion($teacher, 'P2');
        $q3 = $this->createMultipleChoiceQuestion($teacher, 'P3');

        $eq1 = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $section->id, 'question_id' => $q1->id, 'time_limit_seconds' => 60, 'points_weight' => 30, 'sort_order' => 0]);
        $eq2 = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $section->id, 'question_id' => $q2->id, 'time_limit_seconds' => 60, 'points_weight' => 30, 'sort_order' => 1]);
        $eq3 = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $section->id, 'question_id' => $q3->id, 'time_limit_seconds' => 60, 'points_weight' => 40, 'sort_order' => 2]);

        // Rechazar suma != 100.00.
        $this->actingAs($teacher)->putJson(route('exams.configuration.update', $exam), [
            'title' => $exam->title,
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'max_allowed_incidents' => 3,
            'randomize_question_order' => false,
            'group_ids' => [$group->id],
            'questions' => [
                $eq1->id => ['points_weight' => 33.33, 'time_limit_seconds' => 60],
                $eq2->id => ['points_weight' => 33.33, 'time_limit_seconds' => 60],
                $eq3->id => ['points_weight' => 33.30, 'time_limit_seconds' => 60],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['questions']);

        // Guardar 33.33 + 33.33 + 33.34 = 100.00.
        $this->actingAs($teacher)->put(route('exams.configuration.update', $exam), [
            'title' => $exam->title,
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'max_allowed_incidents' => 3,
            'randomize_question_order' => false,
            'group_ids' => [$group->id],
            'questions' => [
                $eq1->id => ['points_weight' => '33.33', 'time_limit_seconds' => 90],
                $eq2->id => ['points_weight' => '33.33', 'time_limit_seconds' => 90],
                $eq3->id => ['points_weight' => '33.34', 'time_limit_seconds' => 90],
            ],
        ])->assertRedirect(route('exams.review', $exam));

        $this->assertEquals(33.33, (float) $eq1->fresh()->points_weight);
        $this->assertEquals(33.33, (float) $eq2->fresh()->points_weight);
        $this->assertEquals(33.34, (float) $eq3->fresh()->points_weight);

        // Probar conversión 85.00/100 -> 8.50 (is_passing: true) y 55.00/100 -> 5.50 (is_passing: false) en escala 0-10.
        $examScale10 = $this->createPublishedBase100Exam($teacher, $group, '0-10', 85.0, 15.0);
        [$eqObj10, $eqOpen10] = $examScale10->examQuestions()->orderBy('sort_order')->get()->all();

        /** @var AttemptService $attemptService */
        $attemptService = $this->app->make(AttemptService::class);

        // Intento A (submitFinal directo): 85.00 puntos objetivos + 0.00 abierta sin AI pendiente? Para submitFinal directo usamos examen puramente objetivo 85 + 15.
        $examObjOnly10 = $this->createPublishedObjectiveExam($teacher, $group, '0-10', 85.0, 15.0);
        [$obj85, $obj15] = $examObjOnly10->examQuestions()->orderBy('sort_order')->get()->all();

        $attPass = $attemptService->start($examObjOnly10, $studentPass10);
        $attemptService->submitAnswer($attPass, $obj85->id, ['selected_option_index' => 1]); // 85.00
        $attemptService->submitAnswer($attPass, $obj15->id, ['selected_option_index' => 0]); // 0.00
        $submittedPass = $attemptService->submitFinal($attPass);

        $this->assertEquals(8.50, (float) $submittedPass->final_score);

        // Intento B: 55.00 / 100 -> 5.50 en submitFinal (examen con pesos 55 + 45).
        $examObj55 = $this->createPublishedObjectiveExam($teacher, $group, '0-10', 55.0, 45.0);
        [$o55, $o45] = $examObj55->examQuestions()->orderBy('sort_order')->get()->all();

        $attFail = $attemptService->start($examObj55, $studentFail10);
        $attemptService->submitAnswer($attFail, $o55->id, ['selected_option_index' => 1]); // 55.00
        $attemptService->submitAnswer($attFail, $o45->id, ['selected_option_index' => 0]); // 0.00
        $submittedFail = $attemptService->submitFinal($attFail);

        $this->assertEquals(5.50, (float) $submittedFail->final_score);

        // Verificar is_passing en API /api/v1/grades/{attempt}.
        $this->app['auth']->forgetGuards();
        $tokenPass = $studentPass10->createToken('test-pass')->plainTextToken;
        $this->withToken($tokenPass)->getJson("/api/v1/grades/{$submittedPass->id}")
            ->assertOk()
            ->assertJsonPath('data.score', 8.5)
            ->assertJsonPath('data.is_passing', true);

        $this->app['auth']->forgetGuards();
        $tokenFail = $studentFail10->createToken('test-fail')->plainTextToken;
        $this->withToken($tokenFail)->getJson("/api/v1/grades/{$submittedFail->id}")
            ->assertOk()
            ->assertJsonPath('data.score', 5.5)
            ->assertJsonPath('data.is_passing', false);

        // Probar normalización tras recoteje (85.00 -> 8.50 y 55.00 -> 5.50).
        $recotejePass = $this->createSubmittedAttemptForRecoteje($examScale10, $group, $studentPass10, $eqObj10, $eqOpen10, 70.0);
        $this->actingAs($teacher)->post(route('exams.revision.update', [$examScale10, $recotejePass['attempt']]), [
            'reviews' => [['answer_id' => $recotejePass['open_answer']->id, 'score' => 15.0]],
        ])->assertRedirect();
        $this->assertEquals(8.50, (float) $recotejePass['attempt']->fresh()->final_score);

        $recotejeFail = $this->createSubmittedAttemptForRecoteje($examScale10, $group, $studentFail10, $eqObj10, $eqOpen10, 50.0);
        $this->actingAs($teacher)->post(route('exams.revision.update', [$examScale10, $recotejeFail['attempt']]), [
            'reviews' => [['answer_id' => $recotejeFail['open_answer']->id, 'score' => 5.0]],
        ])->assertRedirect();
        $this->assertEquals(5.50, (float) $recotejeFail['attempt']->fresh()->final_score);

        // Escala 0-100 mantiene sin conversión (85.00 -> 85.00).
        $examObj100 = $this->createPublishedObjectiveExam($teacher, $group, '0-100', 85.0, 15.0);
        [$eq100a, $eq100b] = $examObj100->examQuestions()->orderBy('sort_order')->get()->all();
        $att100 = $attemptService->start($examObj100, $studentPass100);
        $attemptService->submitAnswer($att100, $eq100a->id, ['selected_option_index' => 1]);
        $attemptService->submitAnswer($att100, $eq100b->id, ['selected_option_index' => 0]);
        $submitted100 = $attemptService->submitFinal($att100);

        $this->assertEquals(85.00, (float) $submitted100->final_score);
        $this->app['auth']->forgetGuards();
        $token100 = $studentPass100->createToken('test-100')->plainTextToken;
        $res100 = $this->withToken($token100)->getJson("/api/v1/grades/{$submitted100->id}")
            ->assertOk()
            ->assertJsonPath('data.is_passing', true);
        $this->assertEquals(85.0, (float) $res100->json('data.score'));
    }

    // ------------------------------------------------------------------
    // e) Checklist y candados de publicación (exams.review y exams.publish)
    // ------------------------------------------------------------------

    public function test_publish_checklist_locks_and_publishes_only_when_all_five_checks_pass(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $openGroup = $this->createGroup($teacher, 'Grupo Abierto', 'PUBO01');
        $closedGroup = $this->createGroup($teacher, 'Grupo Cerrado', 'PUBC01', true);
        $q = $this->createMultipleChoiceQuestion($teacher, 'Q Publish');

        $exam = $this->createExamRecord($teacher, [
            'title' => 'Examen Checklist 5/5',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(5), // ventana vencida inicialmente
        ], [$closedGroup->id]);

        // 1. Falla por falta de secciones, peso != 100, sin grupo abierto, ventana vencida.
        $this->actingAs($teacher)->get(route('exams.review', $exam))
            ->assertOk()
            ->assertViewHas('publishChecklist', fn (array $c): bool => $c['can_publish'] === false && $c['passed_count'] === 0);

        $this->actingAs($teacher)->postJson(route('exams.publish', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publish']);

        // Agregar sección 1 con peso 100, pero dejar otra sección vacía.
        $s1 = ExamSection::create(['exam_id' => $exam->id, 'title' => 'S1', 'question_type' => 'multiple_choice', 'requested_quantity' => 1, 'sort_order' => 0]);
        $sEmpty = ExamSection::create(['exam_id' => $exam->id, 'title' => 'S Vacía', 'question_type' => 'multiple_choice', 'requested_quantity' => 0, 'sort_order' => 1]);
        $eq = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $s1->id, 'question_id' => $q->id, 'time_limit_seconds' => 60, 'points_weight' => 90.0, 'sort_order' => 0]);

        // Falla por sección vacía, peso 90, sin grupo abierto y ventana expirada.
        $this->actingAs($teacher)->postJson(route('exams.publish', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publish']);

        // Corregir sección vacía y peso 100, pero sigue sin grupo abierto y con ventana expirada.
        $sEmpty->delete();
        $eq->update(['points_weight' => 100.0]);

        $this->actingAs($teacher)->postJson(route('exams.publish', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publish']);

        // Corregir ventana futura, sigue sin grupo abierto (solo tiene cerrado).
        $exam->update(['ends_at' => now()->addHours(2)]);
        $this->actingAs($teacher)->postJson(route('exams.publish', $exam))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publish']);

        // Asignar grupo abierto -> cumple 5/5 y publica redirigiendo a exams.index.
        $exam->groups()->attach($openGroup->id);

        $this->actingAs($teacher)->get(route('exams.review', $exam))
            ->assertOk()
            ->assertViewHas('publishChecklist', fn (array $c): bool => $c['can_publish'] === true && $c['passed_count'] === 5);

        $this->actingAs($teacher)->post(route('exams.publish', $exam))
            ->assertRedirect(route('exams.index'));

        $this->assertTrue($exam->fresh()->is_published);
    }

    // ------------------------------------------------------------------
    // f) Seguridad WebSocket, Recoteje y Visibilidad
    // ------------------------------------------------------------------

    public function test_broadcast_channel_rejects_malformed_exam_id_cleanly_without_type_error(): void
    {
        $teacher = $this->createTeacherWithProSubscription();

        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        foreach (['abc', '1;DROP', '0', '-5', '1.5'] as $malformedId) {
            $request = Request::create('/broadcasting/auth', 'POST', [
                'channel_name' => "private-exam.{$malformedId}",
                'socket_id' => '1234.5678',
            ]);
            $request->setUserResolver(static fn () => $teacher);

            $denied = false;
            try {
                Broadcast::auth($request);
            } catch (AccessDeniedHttpException) {
                $denied = true;
            }

            $this->assertTrue($denied, "El canal private-exam.{$malformedId} debe denegarse limpiamente sin TypeError.");
        }

        config(['broadcasting.default' => 'null']);
    }

    public function test_recoteje_security_partial_recalculation_and_safe_rendering_of_array_payloads(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $group = $this->createGroup($teacher, 'Grupo Recoteje', 'RECO01');
        $student = User::factory()->student()->create();
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = $this->createExamRecord($teacher, [
            'title' => 'Examen Recoteje Completo',
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'is_published' => true,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHour(),
        ], [$group->id]);

        $sMc = ExamSection::create(['exam_id' => $exam->id, 'title' => 'MC', 'question_type' => 'multiple_choice', 'requested_quantity' => 1, 'sort_order' => 0]);
        $sMatch = ExamSection::create(['exam_id' => $exam->id, 'title' => 'Match', 'question_type' => 'matching', 'requested_quantity' => 1, 'sort_order' => 1]);
        $sOpen = ExamSection::create(['exam_id' => $exam->id, 'title' => 'Open', 'question_type' => 'open', 'requested_quantity' => 1, 'sort_order' => 2]);
        $sCode = ExamSection::create(['exam_id' => $exam->id, 'title' => 'Code', 'question_type' => 'code', 'requested_quantity' => 1, 'sort_order' => 3]);

        $qMc = $this->createMultipleChoiceQuestion($teacher, 'Obj MC');
        $qMatch = $this->createMatchingQuestion($teacher, 'Obj Match');
        $qOpen = $this->createOpenQuestion($teacher, 'Qual Open');
        $qCode = $this->createCodeQuestion($teacher, 'Qual Code');

        $eqMc = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $sMc->id, 'question_id' => $qMc->id, 'time_limit_seconds' => 60, 'points_weight' => 25.0, 'sort_order' => 0]);
        $eqMatch = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $sMatch->id, 'question_id' => $qMatch->id, 'time_limit_seconds' => 60, 'points_weight' => 25.0, 'sort_order' => 0]);
        $eqOpen = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $sOpen->id, 'question_id' => $qOpen->id, 'time_limit_seconds' => 120, 'points_weight' => 25.0, 'sort_order' => 0]);
        $eqCode = ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $sCode->id, 'question_id' => $qCode->id, 'time_limit_seconds' => 120, 'points_weight' => 25.0, 'sort_order' => 0]);

        $attempt = StudentAttempt::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(5),
            'objective_score' => 50.0,
            'ai_estimated_score' => 20.0,
            'final_score' => null,
            'pending_ai_count' => 2,
            'question_sequence_payload' => [$eqMc->id, $eqMatch->id, $eqOpen->id, $eqCode->id],
        ]);

        $ansMc = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $eqMc->id,
            'answer_payload' => ['selected_option_index' => 1],
            'is_correct' => true,
            'points_awarded' => 25.0,
            'answered_at' => now()->subMinutes(10),
        ]);
        $ansMatch = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $eqMatch->id,
            'answer_payload' => [['left_id' => 'L1', 'right_id' => 'R1'], ['left_id' => 'L2', 'right_id' => 'R2']],
            'is_correct' => true,
            'points_awarded' => 25.0,
            'answered_at' => now()->subMinutes(9),
        ]);
        $ansOpen = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $eqOpen->id,
            // Arreglo sin clave 'text'/'code' para validar que no ocurra Array to string conversion.
            'answer_payload' => ['raw' => 'Respuesta compleja en arreglo'],
            'points_awarded' => 10.0,
            'ai_similarity_score' => 40.0,
            'ai_meets_goal' => false,
            'ai_feedback' => 'IA feedback interno',
            'ai_evaluated_at' => now()->subMinutes(5),
            'answered_at' => now()->subMinutes(8),
        ]);
        $ansCode = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $eqCode->id,
            'answer_payload' => ['source' => 'echo "ok";'],
            'points_awarded' => 10.0,
            'ai_similarity_score' => 40.0,
            'ai_meets_goal' => false,
            'ai_feedback' => 'IA code feedback interno',
            'ai_evaluated_at' => now()->subMinutes(5),
            'answered_at' => now()->subMinutes(7),
        ]);

        // 1. Renderizado seguro en exams.revision.show sin "Array to string conversion".
        $showRes = $this->actingAs($teacher)->get(route('exams.revision.show', [$exam, $attempt]));
        $showRes->assertOk();
        $showRes->assertSee('Izq 1');
        $showRes->assertSee('Der 1');
        $showRes->assertSee('Respuesta compleja en arreglo');
        $showRes->assertSeeText('source');
        $showRes->assertSeeText('echo');

        // 2. Rechazo de edición sobre respuestas objetivas (multiple_choice y matching).
        $this->actingAs($teacher)->postJson(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $ansMc->id, 'score' => 20.0]],
        ])->assertStatus(422)->assertJsonValidationErrors(['reviews.0.answer_id']);

        $this->actingAs($teacher)->postJson(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $ansMatch->id, 'score' => 10.0]],
        ])->assertStatus(422)->assertJsonValidationErrors(['reviews.0.answer_id']);

        // 3. Rechazo si score > points_weight.
        $this->actingAs($teacher)->postJson(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $ansOpen->id, 'score' => 26.0]],
        ])->assertStatus(422)->assertJsonValidationErrors(['reviews.0.score']);

        // 4. Recoteje parcial: actualizar solo open -> pending_ai_count pasa a 1 y final_score sigue null.
        $this->actingAs($teacher)->post(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $ansOpen->id, 'score' => 20.0, 'notes' => 'Bien argumentado']],
        ])->assertRedirect(route('exams.revision.show', [$exam, $attempt]));

        $attempt->refresh();
        $this->assertSame(1, (int) $attempt->pending_ai_count);
        $this->assertNull($attempt->final_score);

        // 5. Completar recoteje con code -> pending_ai_count = 0 y final_score = (25+25+20+15)/10 = 8.50.
        $this->actingAs($teacher)->post(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $ansCode->id, 'score' => 15.0, 'notes' => 'Código correcto']],
        ])->assertRedirect(route('exams.revision.show', [$exam, $attempt]));

        $attempt->refresh();
        $this->assertSame(0, (int) $attempt->pending_ai_count);
        $this->assertEquals(8.50, (float) $attempt->final_score);

        // 6. Rechazar recoteje sobre intentos in_progress o cancelled_by_incident.
        foreach (['in_progress', 'cancelled_by_incident'] as $blockedStatus) {
            $studentOther = User::factory()->student()->create();
            $group->students()->attach($studentOther->id, ['joined_at' => now()]);
            $blockedAttempt = StudentAttempt::create([
                'exam_id' => $exam->id,
                'group_id' => $group->id,
                'student_id' => $studentOther->id,
                'status' => $blockedStatus,
                'started_at' => now()->subMinutes(10),
                'submitted_at' => $blockedStatus === 'cancelled_by_incident' ? now() : null,
                'final_score' => $blockedStatus === 'cancelled_by_incident' ? 0.0 : null,
                'question_sequence_payload' => [$eqOpen->id],
            ]);
            $blockedAnswer = StudentAnswer::create([
                'student_attempt_id' => $blockedAttempt->id,
                'exam_question_id' => $eqOpen->id,
                'answer_payload' => ['text' => 'Intento bloqueado'],
                'points_awarded' => 10.0,
                'answered_at' => now(),
            ]);

            $this->actingAs($teacher)->postJson(route('exams.revision.update', [$exam, $blockedAttempt]), [
                'reviews' => [['answer_id' => $blockedAnswer->id, 'score' => 15.0]],
            ])->assertStatus(422)->assertJsonValidationErrors(['attempt']);
        }
    }

    public function test_four_result_visibility_modes_enforce_strict_hiding_and_adr050_ai_shield(): void
    {
        $teacher = $this->createTeacherWithProSubscription();
        $group = $this->createGroup($teacher, 'Grupo Visibilidad', 'VISI01');

        /** @var AttemptService $attemptService */
        $attemptService = $this->app->make(AttemptService::class);

        // 1. Modo 'hidden' con final_score = 0.0: ocultamiento estricto en Web y API (/api/v1/grades y /api/v1/grades/{attempt}).
        $studentHidden = User::factory()->student()->create();
        $group->students()->attach($studentHidden->id, ['joined_at' => now()]);
        $hiddenExam = $this->createPublishedObjectiveExam($teacher, $group, '0-10', 100.0, 0.0, 'hidden');
        $hiddenEq = $hiddenExam->examQuestions()->orderBy('sort_order')->firstOrFail();

        $hiddenAttempt = $attemptService->start($hiddenExam, $studentHidden);
        $attemptService->submitAnswer($hiddenAttempt, $hiddenEq->id, ['selected_option_index' => 0]); // 0.0 puntos
        $hiddenSubmitted = $attemptService->submitFinal($hiddenAttempt);

        $this->assertSame(0.0, (float) $hiddenSubmitted->final_score);

        // Web result-summary oculta estrictamente.
        $this->actingAs($studentHidden)->get(route('attempts.result', $hiddenSubmitted))
            ->assertOk()
            ->assertSee('Resultado reservado temporalmente')
            ->assertDontSee('Calificación Definitiva')
            ->assertDontSee('0.00');

        $this->actingAs($studentHidden)->get(route('attempts.review', $hiddenSubmitted))
            ->assertForbidden();

        // API /api/v1/grades/{attempt} y /api/v1/grades no deben exponer calificación ni status='published'.
        $this->app['auth']->forgetGuards();
        $hiddenToken = $studentHidden->createToken('hidden-token')->plainTextToken;
        $this->withToken($hiddenToken)->getJson("/api/v1/grades/{$hiddenSubmitted->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.score');

        $gradesListResponse = $this->withToken($hiddenToken)->getJson('/api/v1/grades')->assertOk();
        $listedHiddenRow = collect($gradesListResponse->json('data.data'))->firstWhere('id', (string) $hiddenSubmitted->id);
        if ($listedHiddenRow !== null) {
            $this->assertSame('pending', $listedHiddenRow['status']);
            $this->assertNull($listedHiddenRow['score']);
            $this->assertNull($listedHiddenRow['is_passing']);
        } else {
            $this->fail('El intento oculto no fue filtrado ni marcado como pendiente en GET /api/v1/grades.');
        }

        $this->withToken($hiddenToken)->getJson('/api/v1/grades/statistics')
            ->assertOk()
            ->assertJsonCount(0, 'data.grades');

        $this->actingAs($studentHidden)->get(route('student.grades.index'))
            ->assertOk()
            ->assertDontSee($hiddenExam->title);

        $this->actingAs($studentHidden)->get(route('student.grades.show', $group))
            ->assertOk()
            ->assertDontSee($hiddenExam->title);

        // 2. Modo 'objective_only_pending': mantiene final_score = null antes de recoteje y no fuga ai_feedback.
        $studentObjOnly = User::factory()->student()->create();
        $group->students()->attach($studentObjOnly->id, ['joined_at' => now()]);
        $objPendingExam = $this->createPublishedBase100Exam($teacher, $group, '0-10', 50.0, 50.0, 'objective_only_pending');
        [$eqObj, $eqOpen] = $objPendingExam->examQuestions()->orderBy('sort_order')->get()->all();

        $attemptObjPending = $attemptService->start($objPendingExam, $studentObjOnly);
        $attemptService->submitAnswer($attemptObjPending, $eqObj->id, ['selected_option_index' => 1]);
        $attemptService->submitAnswer($attemptObjPending, $eqOpen->id, 'Mi respuesta de prueba');
        $submittedObjPending = $attemptService->submitFinal($attemptObjPending);

        // Inyectar ai_feedback para certificar blindaje ADR-050.
        StudentAnswer::where('student_attempt_id', $submittedObjPending->id)
            ->where('exam_question_id', $eqOpen->id)
            ->update(['ai_feedback' => 'SECRETO-AI-FEEDBACK-OBJ']);

        $this->assertNull($submittedObjPending->final_score);
        $this->assertSame(1, (int) $submittedObjPending->pending_ai_count);

        $this->actingAs($studentObjOnly)->get(route('attempts.result', $submittedObjPending))
            ->assertOk()
            ->assertSee('Puntaje Objetivo Registrado')
            ->assertSee('50.00')
            ->assertDontSee('SECRETO-AI-FEEDBACK-OBJ');

        // 3. Modo 'estimated_score': mantiene final_score = null antes de recoteje y muestra preliminar sin fugar ai_feedback.
        $studentEstimated = User::factory()->student()->create();
        $group->students()->attach($studentEstimated->id, ['joined_at' => now()]);
        $estimatedExam = $this->createPublishedBase100Exam($teacher, $group, '0-10', 40.0, 60.0, 'estimated_score');
        [$eqEstObj, $eqEstOpen] = $estimatedExam->examQuestions()->orderBy('sort_order')->get()->all();

        $attemptEst = $attemptService->start($estimatedExam, $studentEstimated);
        $attemptService->submitAnswer($attemptEst, $eqEstObj->id, ['selected_option_index' => 1]);
        $attemptService->submitAnswer($attemptEst, $eqEstOpen->id, 'Respuesta abierta con estimación IA');
        $submittedEst = $attemptService->submitFinal($attemptEst);

        StudentAnswer::where('student_attempt_id', $submittedEst->id)
            ->where('exam_question_id', $eqEstOpen->id)
            ->update([
                'ai_feedback' => 'SECRETO-AI-FEEDBACK-EST',
                'points_awarded' => 30.0,
            ]);
        $submittedEst->update(['ai_estimated_score' => 30.0]);

        $this->assertNull($submittedEst->fresh()->final_score);
        $this->assertSame(1, (int) $submittedEst->fresh()->pending_ai_count);

        $this->actingAs($studentEstimated)->get(route('attempts.result', $submittedEst->fresh()))
            ->assertOk()
            ->assertSee('Calificación preliminar estimada')
            ->assertSee('7.00')
            ->assertDontSee('SECRETO-AI-FEEDBACK-EST');

        $this->app['auth']->forgetGuards();
        $estToken = $studentEstimated->createToken('est-token')->plainTextToken;
        $estApiRes = $this->withToken($estToken)->getJson("/api/v1/grades/{$submittedEst->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
        $this->assertStringNotContainsString('SECRETO-AI-FEEDBACK-EST', $estApiRes->getContent());

        // 4. Modo 'final_score': muestra calificación definitiva y nunca fuga ai_feedback.
        $studentFinal = User::factory()->student()->create();
        $group->students()->attach($studentFinal->id, ['joined_at' => now()]);
        $finalExam = $this->createPublishedObjectiveExam($teacher, $group, '0-10', 90.0, 10.0, 'final_score');
        [$eqFinA, $eqFinB] = $finalExam->examQuestions()->orderBy('sort_order')->get()->all();

        $attemptFinal = $attemptService->start($finalExam, $studentFinal);
        $attemptService->submitAnswer($attemptFinal, $eqFinA->id, ['selected_option_index' => 1]);
        $attemptService->submitAnswer($attemptFinal, $eqFinB->id, ['selected_option_index' => 0]);
        $submittedFinal = $attemptService->submitFinal($attemptFinal);

        $this->assertEquals(9.00, (float) $submittedFinal->final_score);
        $this->actingAs($studentFinal)->get(route('attempts.result', $submittedFinal))
            ->assertOk()
            ->assertSee('Calificación Definitiva')
            ->assertSee('9.00');
    }

    // ------------------------------------------------------------------
    // Helpers de soporte para fixtures del módulo
    // ------------------------------------------------------------------

    private function createTeacherWithProSubscription(): User
    {
        $this->seed(SubscriptionPackageSeeder::class);
        $teacher = User::factory()->teacher()->create();
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();

        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => $proPackage->id,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        return $teacher;
    }

    private function createGroup(User $teacher, string $name, string $folio, bool $isClosed = false): Group
    {
        return Group::create([
            'teacher_id' => $teacher->id,
            'name' => $name,
            'subject' => 'Ingeniería',
            'access_folio' => $folio,
            'is_closed' => $isClosed,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<int, int>      $groupIds
     */
    private function createExamRecord(User $teacher, array $overrides = [], array $groupIds = []): Exam
    {
        $exam = Exam::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Base',
            'description' => 'Descripción base',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => false,
        ], $overrides));

        if ($groupIds !== []) {
            $exam->groups()->attach($groupIds);
        }

        return $exam;
    }

    private function createMultipleChoiceQuestion(
        User $teacher,
        string $title,
        ?Subject $subject = null,
        ?int $parentQuestionId = null
    ): Question {
        $item = MultipleChoiceItem::create([
            'options_payload' => [
                ['index' => 0, 'text' => 'Opción incorrecta'],
                ['index' => 1, 'text' => 'Opción correcta'],
            ],
            'correct_option_index' => 1,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject?->id,
            'subject_tag' => $subject?->name ?? 'General',
            'title' => $title,
            'stem' => "Enunciado de {$title}",
            'question_type' => 'multiple_choice',
            'default_points' => 10.0,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
            'parent_question_id' => $parentQuestionId,
        ]);
    }

    private function createMatchingQuestion(User $teacher, string $title): Question
    {
        $item = MatchingItem::create([
            'left_column_payload' => [
                ['id' => 'L1', 'text' => 'Izq 1'],
                ['id' => 'L2', 'text' => 'Izq 2'],
            ],
            'right_column_payload' => [
                ['id' => 'R1', 'text' => 'Der 1'],
                ['id' => 'R2', 'text' => 'Der 2'],
            ],
            'pairs_payload' => [
                ['left_id' => 'L1', 'right_id' => 'R1'],
                ['left_id' => 'L2', 'right_id' => 'R2'],
            ],
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'General',
            'title' => $title,
            'stem' => "Relaciona {$title}",
            'question_type' => 'matching',
            'default_points' => 10.0,
            'default_time_seconds' => 90,
            'questionable_type' => MatchingItem::class,
            'questionable_id' => $item->id,
        ]);
    }

    private function createOpenQuestion(User $teacher, string $title, ?Subject $subject = null): Question
    {
        $item = OpenItem::create([
            'reference_answer' => 'Respuesta modelo esperada.',
            'evaluation_rubric' => 'Criterio clave.',
            'allow_file_upload' => false,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject?->id,
            'subject_tag' => $subject?->name ?? 'General',
            'title' => $title,
            'stem' => "Explica {$title}",
            'question_type' => 'open',
            'default_points' => 10.0,
            'default_time_seconds' => 120,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);
    }

    private function createCodeQuestion(User $teacher, string $title): Question
    {
        $item = CodeItem::create([
            'programming_language' => 'php',
            'is_find_error' => false,
            'starter_code' => '<?php',
            'reference_solution' => '<?php echo "ok";',
            'instructions' => 'Imprime ok.',
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'Programación',
            'title' => $title,
            'stem' => "Programa {$title}",
            'question_type' => 'code',
            'default_points' => 10.0,
            'default_time_seconds' => 180,
            'questionable_type' => CodeItem::class,
            'questionable_id' => $item->id,
        ]);
    }

    private function createPublishedObjectiveExam(
        User $teacher,
        Group $group,
        string $scale,
        float $weightA,
        float $weightB,
        string $visibilityMode = 'final_score'
    ): Exam {
        $exam = $this->createExamRecord($teacher, [
            'title' => "Objetivo {$scale} {$visibilityMode}",
            'grading_scale' => $scale,
            'result_visibility_mode' => $visibilityMode,
            'is_published' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ], [$group->id]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Objetiva',
            'question_type' => 'multiple_choice',
            'requested_quantity' => $weightB > 0 ? 2 : 1,
            'sort_order' => 0,
        ]);

        $qA = $this->createMultipleChoiceQuestion($teacher, 'Obj A');
        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $qA->id,
            'time_limit_seconds' => 60,
            'points_weight' => $weightA,
            'sort_order' => 0,
        ]);

        if ($weightB > 0) {
            $qB = $this->createMultipleChoiceQuestion($teacher, 'Obj B');
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $section->id,
                'question_id' => $qB->id,
                'time_limit_seconds' => 60,
                'points_weight' => $weightB,
                'sort_order' => 1,
            ]);
        }

        return $exam;
    }

    private function createPublishedBase100Exam(
        User $teacher,
        Group $group,
        string $scale,
        float $objWeight,
        float $openWeight,
        string $visibilityMode = 'final_score'
    ): Exam {
        $exam = $this->createExamRecord($teacher, [
            'title' => "Mixto {$scale} {$visibilityMode}",
            'grading_scale' => $scale,
            'result_visibility_mode' => $visibilityMode,
            'is_published' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ], [$group->id]);

        $s1 = ExamSection::create(['exam_id' => $exam->id, 'title' => 'MC', 'question_type' => 'multiple_choice', 'requested_quantity' => 1, 'sort_order' => 0]);
        $s2 = ExamSection::create(['exam_id' => $exam->id, 'title' => 'Abierta', 'question_type' => 'open', 'requested_quantity' => 1, 'sort_order' => 1]);

        $qMc = $this->createMultipleChoiceQuestion($teacher, 'Mixto MC');
        $qOpen = $this->createOpenQuestion($teacher, 'Mixto Open');

        ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $s1->id, 'question_id' => $qMc->id, 'time_limit_seconds' => 60, 'points_weight' => $objWeight, 'sort_order' => 0]);
        ExamQuestion::create(['exam_id' => $exam->id, 'exam_section_id' => $s2->id, 'question_id' => $qOpen->id, 'time_limit_seconds' => 120, 'points_weight' => $openWeight, 'sort_order' => 0]);

        return $exam;
    }

    /**
     * @return array{attempt: StudentAttempt, open_answer: StudentAnswer}
     */
    private function createSubmittedAttemptForRecoteje(
        Exam $exam,
        Group $group,
        User $student,
        ExamQuestion $eqObj,
        ExamQuestion $eqOpen,
        float $earnedObjectivePoints
    ): array {
        $attempt = StudentAttempt::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(15),
            'submitted_at' => now()->subMinutes(2),
            'objective_score' => $earnedObjectivePoints,
            'ai_estimated_score' => 0.0,
            'final_score' => null,
            'pending_ai_count' => 1,
            'question_sequence_payload' => [$eqObj->id, $eqOpen->id],
        ]);

        StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $eqObj->id,
            'answer_payload' => ['selected_option_index' => 1],
            'is_correct' => true,
            'points_awarded' => $earnedObjectivePoints,
            'answered_at' => now()->subMinutes(5),
        ]);

        $openAnswer = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $eqOpen->id,
            'answer_payload' => ['text' => 'Respuesta de prueba'],
            'points_awarded' => 0.0,
            'answered_at' => now()->subMinutes(4),
        ]);

        return [
            'attempt' => $attempt,
            'open_answer' => $openAnswer,
        ];
    }
}
