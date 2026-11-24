<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherReviewRecotejeTest.php
 * Ubicación:      tests/Feature/Exam/TeacherReviewRecotejeTest.php
 * Requerimiento:  RF-IAR-002, RF-EVA-009, RW-11 (Recoteje Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Pruebas automatizadas de la interfaz y persistencia del recoteje
 *                 docente sobre respuestas evaluadas por IA.
 * ============================================================================
 */

namespace Tests\Feature\Exam;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherReviewRecotejeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_review_and_recotejar_ai_answer(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $foreignTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo A',
            'subject' => 'Historia',
            'access_folio' => 'FOLIOREV1',
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen con Pregunta Abierta',
            'share_token' => 'REVTOKEN1',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección Abierta',
            'question_type' => 'open',
            'requested_quantity' => 1,
            'sort_order' => 1,
        ]);

        $openItem = OpenItem::create([
            'reference_answer' => 'La Revolución Mexicana inició en 1910 con Madero.',
            'evaluation_rubric' => 'Mencionar año 1910 y personaje Francisco I. Madero.',
            'allow_file_upload' => false,
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'subject_tag' => 'Historia',
            'title' => 'Inicio de la Revolución',
            'stem' => '¿Cuándo y con quién inició la Revolución Mexicana?',
            'question_type' => 'open',
            'default_points' => 10.0,
            'default_time_seconds' => 120,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $openItem->id,
        ]);

        $examQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'order_index' => 1,
            'points_weight' => 10.0,
            'time_limit_seconds' => 120,
        ]);

        $attempt = StudentAttempt::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'current_question_index' => 1,
            'objective_score' => 0.0,
            'ai_estimated_score' => 6.0,
            'final_score' => null,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
            'question_sequence_payload' => [$examQuestion->id],
        ]);

        $studentAnswer = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $examQuestion->id,
            'answer_payload' => ['text' => 'Inició en 1910 con Francisco Madero para derrocar a Díaz.'],
            'is_correct' => true,
            'points_awarded' => 6.0,
            'ai_similarity_score' => 60.0,
            'ai_meets_goal' => true,
            'ai_feedback' => 'Menciona el año y el personaje, pero no detalla el contexto completo.',
            'ai_evaluated_at' => now(),
            'teacher_recoteje_score' => null,
            'teacher_recoteje_notes' => null,
            'answered_at' => now(),
        ]);

        // 1. Docente ajeno intenta acceder -> 403 Forbidden
        $this->actingAs($foreignTeacher)
            ->get(route('exams.revision.index', $exam))
            ->assertForbidden();

        $this->actingAs($foreignTeacher)
            ->get(route('exams.revision.show', [$exam, $attempt]))
            ->assertForbidden();

        // 2. Docente autor consulta la lista de intentos
        $this->actingAs($teacher)
            ->get(route('exams.revision.index', $exam))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('Pendiente de revisión');

        // 3. Docente autor consulta la vista de detalle
        $this->actingAs($teacher)
            ->get(route('exams.revision.show', [$exam, $attempt]))
            ->assertOk()
            ->assertSee('Inicio de la Revolución')
            ->assertSee('Inició en 1910 con Francisco Madero')
            ->assertSee('Evaluación Asistida por IA');

        // 4. Docente autor guarda el recoteje aumentando la nota de 6.0 a 9.5
        $response = $this->actingAs($teacher)->post(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [
                [
                    'answer_id' => $studentAnswer->id,
                    'score' => 9.5,
                    'notes' => 'Respuesta excelente y concisa.',
                ],
            ],
        ]);

        $response->assertRedirect(route('exams.revision.show', [$exam, $attempt]));
        $response->assertSessionHas('status');

        $studentAnswer->refresh();
        $this->assertEquals(9.5, (float) $studentAnswer->teacher_recoteje_score);
        $this->assertEquals(9.5, (float) $studentAnswer->points_awarded);
        $this->assertSame('Respuesta excelente y concisa.', $studentAnswer->teacher_recoteje_notes);

        $attempt->refresh();
        $this->assertEquals(9.5, (float) $attempt->final_score);
        // Una corrección posterior a la publicación se avisa únicamente al titular y con el nuevo valor.
        $exam->update(['result_visibility_mode' => 'final_score']);
        $this->actingAs($teacher)->post(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $studentAnswer->id, 'score' => 8.5, 'notes' => 'Revisión sintética']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($student)->get(route('student.grades.index'))->assertOk()
            ->assertSee('Nueva calificación · 8.50')->assertDontSee('Revisión sintética');
        $exam->update(['result_visibility_mode' => 'hidden']);
        $this->actingAs($teacher)->post(route('exams.revision.update', [$exam, $attempt]), [
            'reviews' => [['answer_id' => $studentAnswer->id, 'score' => 7.5, 'notes' => 'Cambio oculto']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($student)->get(route('student.grades.index'))->assertDontSee('Nueva calificación · 7.50');

        // Los registros históricos en blanco conservan cero, aunque carezcan del marcador de IA.
        foreach (['none', null] as $model) {
            $studentAnswer->update(['answer_payload' => ['text' => " \n \u{00A0}"], 'ai_model_used' => $model,
                'points_awarded' => 0, 'teacher_recoteje_score' => null]);
            $attempt->update(['final_score' => 0]);
            $this->actingAs($teacher)->get(route('exams.revision.show', [$exam, $attempt]))->assertOk()
                ->assertDontSee('reviews[0][score]', false);
            $this->post(route('exams.revision.update', [$exam, $attempt]), [
                'reviews' => [['answer_id' => $studentAnswer->id, 'score' => 5]],
            ])->assertSessionHasErrors(['reviews' => 'La respuesta en blanco conserva cero puntos']);
            $this->assertEquals(0, $studentAnswer->fresh()->points_awarded);
            $this->assertEquals(0, $attempt->fresh()->final_score);
        }
    }
}
