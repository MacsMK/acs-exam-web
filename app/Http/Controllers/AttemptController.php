<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptController.php
 * Ubicación:   app/Http/Controllers/AttemptController.php
 * Descripción: Versión web (sesión) de la aplicación de examen, para que
 *              el docente/profesor pueda probar el lado alumno dando clic
 *              en el navegador sin necesitar la app móvil ni Postman.
 *              Misma lógica que la API (app/Services/AttemptService.php).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Services\AttemptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    public function start(Request $request, Exam $exam): RedirectResponse
    {
        $attempt = $this->attemptService->start($exam, $request->user());

        return redirect()->route('attempts.show', $attempt);
    }

    public function show(StudentAttempt $attempt): View|RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('attempts.result', $attempt);
        }

        $current = $this->attemptService->currentQuestion($attempt);

        if ($current === null) {
            $attempt = $this->attemptService->submitFinal($attempt);

            return redirect()->route('attempts.result', $attempt);
        }

        return view('exams.take-exam', ['attempt' => $attempt, 'current' => $current]);
    }

    public function answer(Request $request, StudentAttempt $attempt): RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        $data = $request->validate([
            'exam_question_id' => ['required', 'integer', 'exists:acs_exam_question,id'],
        ]);

        $answerPayload = match (true) {
            $request->has('selected_option_index') => ['selected_option_index' => (int) $request->input('selected_option_index')],
            $request->has('pairs') => $request->input('pairs'),
            default => $request->input('answer_text', ''),
        };

        $this->attemptService->submitAnswer($attempt, (int) $data['exam_question_id'], $answerPayload);

        return redirect()->route('attempts.show', $attempt);
    }

    public function finish(StudentAttempt $attempt): RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        $attempt = $this->attemptService->submitFinal($attempt);

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
