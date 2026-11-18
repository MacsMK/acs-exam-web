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
 * Versión:        1.1.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Controlador delgado de revisión docente para recotejar y
 *                 ajustar calificaciones provisionales emitidas por la IA,
 *                 recalculando pending_ai_count y normalizando escala final.
 * ============================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\TeacherReviewStoreRequest;
use App\Models\Exam;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Services\GradeCalculatorService;

class TeacherReviewController extends Controller
{
    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
        private readonly GradeCalculatorService $gradeCalculator,
    ) {
    }

    /**
     * Panel concentrador de revisiones de exámenes para el docente.
     */
    public function globalIndex(Request $request): View
    {
        $teacherId = (int) $request->user()->id;
        $filters = $request->only(['search', 'status']);

        $exams = $this->examRepository->paginateForTeacher($teacherId, 12, $filters);
        $exams->getCollection()->each(static function (Exam $exam): void {
            $exam->setAttribute('pending_reviews_count', (int) ($exam->pending_recoteje_count ?? 0));
        });

        $stats = $this->examRepository->getTeacherExamStats($teacherId);

        return view('teacher.reviews.index', [
            'exams' => $exams,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * Lista de intentos de examen presentados por los alumnos para revisión.
     */
    public function index(Request $request, Exam $exam): View
    {
        abort_unless((int) $exam->teacher_id === (int) $request->user()->id, 403);

        $filters = $request->only(['search', 'status']);
        $attempts = $this->examRepository->paginateAttemptsForReview($exam, $filters, 15);

        return view('exams.review-index', [
            'exam' => $exam,
            'attempts' => $attempts,
            'filters' => $filters,
        ]);
    }

    /**
     * Vista de detalle del intento de un alumno con desglose de IA y formulario de recoteje.
     */
    public function show(Request $request, Exam $exam, StudentAttempt $attempt): View
    {
        abort_unless((int) $exam->teacher_id === (int) $request->user()->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        $loadedAttempt = $this->examRepository->findAttemptForReviewWithAnswers($exam, (int) $attempt->id);
        abort_if($loadedAttempt === null, 404);

        return view('exams.review-attempt', [
            'exam' => $exam,
            'attempt' => $loadedAttempt,
        ]);
    }

    /**
     * Persiste el recoteje docente sobre reactivos cualitativos, recalcula el
     * conteo real de pendientes y normaliza la calificación final definitiva.
     */
    public function update(TeacherReviewStoreRequest $request, Exam $exam, StudentAttempt $attempt): RedirectResponse
    {
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        $reviews = $request->validated('reviews');

        DB::transaction(function () use ($exam, $attempt, $reviews): void {
            $lockedAttempt = StudentAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'attempt' => ['Solo se pueden recotejar intentos en estado entregado'],
                ]);
            }

            $answerIds = array_column($reviews, 'answer_id');
            $answers = $lockedAttempt->answers()
                ->with('examQuestion.question')
                ->whereIn('id', $answerIds)
                ->get()
                ->keyBy('id');

            foreach ($reviews as $review) {
                /** @var StudentAnswer|null $answer */
                $answer = $answers->get((int) $review['answer_id']);

                if ($answer === null) {
                    abort(404, 'La respuesta seleccionada no pertenece al intento evaluado.');
                }

                $questionType = (string) ($answer->examQuestion?->question?->question_type ?? '');
                if (! in_array($questionType, ['open', 'code'], true)) {
                    throw ValidationException::withMessages([
                        'reviews' => ['Solo puedes recotejar reactivos abiertos o de programación'],
                    ]);
                }

                $maxPoints = (float) ($answer->examQuestion?->points_weight ?? 0);
                $assignedScore = (float) $review['score'];

                if ($assignedScore < 0 || $assignedScore > $maxPoints) {
                    throw ValidationException::withMessages([
                        'reviews' => ["El puntaje asignado ({$assignedScore}) debe estar entre 0 y {$maxPoints}"],
                    ]);
                }

                $answer->update([
                    'teacher_recoteje_score' => $assignedScore,
                    'teacher_recoteje_notes' => $review['notes'] ?? null,
                    'points_awarded' => $assignedScore,
                    'is_correct' => $assignedScore > 0,
                ]);
            }

            $pendingAiCount = $lockedAttempt->answers()
                ->whereNull('teacher_recoteje_score')
                ->whereHas('examQuestion.question', static function ($query): void {
                    $query->whereIn('question_type', ['open', 'code']);
                })
                ->count();

            $rawTotal = (float) $lockedAttempt->answers()->sum('points_awarded');
            $totalExamWeight = (float) $exam->examQuestions()->sum('points_weight');
            $finalScore = $pendingAiCount === 0
                ? $this->gradeCalculator->normalizeScoreForExam($rawTotal, $exam, $totalExamWeight)
                : null;

            $lockedAttempt->update([
                'final_score' => $finalScore,
                'pending_ai_count' => $pendingAiCount,
            ]);
        });

        return redirect()
            ->route('exams.revision.show', [$exam, $attempt])
            ->with('status', 'Recoteje docente guardado exitosamente. La calificación final ha sido actualizada.');
    }
}
