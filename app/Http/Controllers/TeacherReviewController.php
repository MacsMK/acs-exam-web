<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherReviewController.php
 * Ubicación:      app/Http/Controllers/TeacherReviewController.php
 * Requerimiento:  RF-IAR-002, RF-EVA-009, RW-11 (Recoteje Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Controlador de revisión docente para recotejar y modificar
 *                 calificaciones provisionales sugeridas por la IA.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\TeacherReviewStoreRequest;
use App\Models\Exam;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherReviewController extends Controller
{
    /**
     * Panel concentrador de revisiones de exámenes para el docente.
     */
    public function globalIndex(Request $request): View
    {
        $teacher = $request->user();

        $exams = Exam::where('teacher_id', $teacher->id)
            ->withCount([
                'studentAttempts',
                'studentAttempts as pending_reviews_count' => function ($query): void {
                    $query->where('pending_ai_count', '>', 0);
                },
            ])
            ->latest()
            ->paginate(12);

        return view('teacher.reviews.index', compact('exams'));
    }

    /**
     * Lista de intentos de examen presentados por los alumnos para revisión.
     */
    public function index(Request $request, Exam $exam): View
    {
        abort_unless($exam->teacher_id === $request->user()->id, 403);

        $attempts = $exam->studentAttempts()
            ->with(['student', 'answers.examQuestion.question'])
            ->latest('submitted_at')
            ->paginate(15);

        return view('exams.review-index', compact('exam', 'attempts'));
    }

    /**
     * Vista de detalle del intento de un alumno con desglose de IA y formulario de recoteje.
     */
    public function show(Request $request, Exam $exam, StudentAttempt $attempt): View
    {
        abort_unless($exam->teacher_id === $request->user()->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        $attempt->load([
            'student',
            'answers.examQuestion.question.questionable',
        ]);

        return view('exams.review-attempt', compact('exam', 'attempt'));
    }

    /**
     * Persiste el recoteje docente y actualiza la calificación final definitiva del alumno.
     */
    public function update(TeacherReviewStoreRequest $request, Exam $exam, StudentAttempt $attempt): RedirectResponse
    {
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        $reviews = $request->validated('reviews');

        DB::transaction(function () use ($attempt, $reviews): void {
            $answerIds = array_column($reviews, 'answer_id');
            $answers = $attempt->answers()
                ->with('examQuestion')
                ->whereIn('id', $answerIds)
                ->get()
                ->keyBy('id');

            foreach ($reviews as $review) {
                /** @var StudentAnswer|null $answer */
                $answer = $answers->get($review['answer_id']);

                if ($answer === null) {
                    abort(404, 'La respuesta seleccionada no pertenece al intento evaluado.');
                }

                $maxPoints = (float) $answer->examQuestion->points_weight;
                $assignedScore = (float) $review['score'];

                if ($assignedScore > $maxPoints) {
                    throw ValidationException::withMessages([
                        'reviews' => ["El puntaje asignado ({$assignedScore}) no puede superar el valor máximo del reactivo ({$maxPoints})."],
                    ]);
                }

                $answer->update([
                    'teacher_recoteje_score' => $assignedScore,
                    'teacher_recoteje_notes' => $review['notes'] ?? null,
                    'points_awarded' => $assignedScore,
                    'is_correct' => $assignedScore > 0,
                ]);
            }

            // Recalcula la calificación final definitiva sumando todos los reactivos
            $newFinalScore = (float) $attempt->answers()->sum('points_awarded');

            $attempt->update([
                'final_score' => $newFinalScore,
                'pending_ai_count' => 0,
            ]);
        });

        return redirect()
            ->route('exams.revision.show', [$exam, $attempt])
            ->with('status', 'Recoteje docente guardado exitosamente. La calificación final ha sido actualizada.');
    }
}
