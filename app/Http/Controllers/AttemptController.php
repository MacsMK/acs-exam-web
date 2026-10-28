<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptController.php
 * Ubicación:   app/Http/Controllers/AttemptController.php
 * Descripción: Versión web (sesión) de la aplicación de examen (MPW-V2 §5.6,
 *              §6.1, §6.3): delega a AttemptService y bloquea envíos fuera
 *              de secuencia o en intentos inactivos.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\AttemptAnswerWebRequest;
use App\Http\Requests\AttemptTakeRequest;
use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Services\AttemptService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    public function start(AttemptTakeRequest $request, Exam $exam): RedirectResponse
    {
        try {
            $attempt = $this->attemptService->start($exam, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['exam' => $e->getMessage()]);
        }

        return redirect()->route('attempts.show', $attempt);
    }

    public function show(StudentAttempt $attempt): View|RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('attempts.result', $attempt);
        }

        $attempt->loadMissing('exam');
        $current = $this->attemptService->currentUnansweredQuestion($attempt);

        if ($current === null) {
            $attempt = $this->attemptService->submitFinal($attempt);

            return redirect()->route('attempts.result', $attempt);
        }

        return view('exams.take-exam', ['attempt' => $attempt, 'current' => $current]);
    }

    public function answer(AttemptAnswerWebRequest $request, StudentAttempt $attempt): RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('attempts.result', $attempt)
                ->withErrors(['attempt' => 'El intento ya no se encuentra activo.']);
        }

        $data = $request->validated();
        $current = $this->attemptService->currentUnansweredQuestion($attempt);

        abort_if(
            $current === null || (int) $current['exam_question_id'] !== (int) $data['exam_question_id'],
            409,
            'La pregunta enviada no corresponde al reactivo activo en secuencia.',
        );

        $answerPayload = match (true) {
            $request->has('selected_option_index') => ['selected_option_index' => (int) $request->input('selected_option_index')],
            $request->has('pairs') => $request->input('pairs'),
            $request->has('given_answer') => $request->input('given_answer'),
            default => (string) ($request->input('answer_text') ?? ''),
        };

        try {
            $this->attemptService->submitAnswer($attempt, (int) $data['exam_question_id'], $answerPayload);
        } catch (DomainException $e) {
            return redirect()->route('attempts.result', $attempt)->withErrors(['attempt' => $e->getMessage()]);
        }

        return redirect()->route('attempts.show', $attempt);
    }

    public function finish(StudentAttempt $attempt): RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('attempts.result', $attempt);
        }

        try {
            $attempt = $this->attemptService->submitFinal($attempt);
        } catch (DomainException $e) {
            return redirect()->route('attempts.result', $attempt)->withErrors(['attempt' => $e->getMessage()]);
        }

        return redirect()->route('attempts.result', $attempt);
    }

    public function result(StudentAttempt $attempt): View
    {
        $this->authorizeOwnership($attempt);

        return view('exams.result-summary', ['attempt' => $attempt->load('exam', 'answers.examQuestion.question')]);
    }

    private function authorizeOwnership(StudentAttempt $attempt): void
    {
        abort_unless($attempt->student_id === request()->user()->id, 403);
    }
}
