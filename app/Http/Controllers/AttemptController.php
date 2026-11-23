<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptController.php
 * Ubicación:   app/Http/Controllers/AttemptController.php
 * Descripción: Versión web (sesión) de la aplicación de examen (MPW-V3 §5.6,
 *              §6.1, §6.3): delega a AttemptService y bloquea envíos fuera
 *              de secuencia o en intentos inactivos.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\AttemptAnswerWebRequest;
use App\Http\Requests\AttemptIncidentStoreRequest;
use App\Http\Requests\AttemptTakeRequest;
use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Services\AttemptService;
use App\Services\StudentResultService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AttemptController extends Controller
{
    private const OTHER_SESSION_MESSAGE = 'Este intento está abierto en otra sesión o dispositivo. Ciérralo ahí o espera un minuto e inténtalo de nuevo.';

    public function __construct(
        private readonly AttemptService $attemptService,
        private readonly StudentResultService $studentResultService,
    ) {
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

        if (! $this->attemptService->sessionAvailable($attempt, $this->sessionHolder())) {
            return redirect()->route('student.exams.index')->withErrors(['attempt' => self::OTHER_SESSION_MESSAGE]);
        }

        $attempt->loadMissing('exam');
        $current = $this->attemptService->currentUnansweredQuestion($attempt);

        if ($current === null) {
            $attempt = $this->attemptService->submitFinal($attempt);

            return redirect()->route('attempts.result', $attempt);
        }

        return view('exams.take-exam', [
            'attempt' => $attempt,
            'current' => $current,
            // Borrador guardado de la pregunta activa (RF-EXA-040, RF-EXA-048)
            'draft' => $this->attemptService->draftFor($attempt, (int) $current['exam_question_id']),
        ]);
    }

    public function answer(AttemptAnswerWebRequest $request, StudentAttempt $attempt): RedirectResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('attempts.result', $attempt)
                ->withErrors(['attempt' => 'El intento ya no se encuentra activo.']);
        }

        if (! $this->attemptService->sessionAvailable($attempt, $this->sessionHolder())) {
            return redirect()->route('student.exams.index')->withErrors(['attempt' => self::OTHER_SESSION_MESSAGE]);
        }

        $data = $request->validated();
        $current = $this->attemptService->currentUnansweredQuestion($attempt);

        abort_if(
            $current === null || (int) $current['exam_question_id'] !== (int) $data['exam_question_id'],
            409,
            'La pregunta enviada no corresponde al reactivo activo en secuencia.',
        );

        $answerPayload = $this->answerPayloadFrom($request);

        try {
            $isLate = $this->attemptService->submitAnswer($attempt, (int) $data['exam_question_id'], $answerPayload);
        } catch (DomainException $e) {
            return redirect()->route('attempts.result', $attempt)->withErrors(['attempt' => $e->getMessage()]);
        }

        $next = redirect()->route('attempts.show', $attempt);

        // Mensaje oficial "Tiempo agotado" (MPW-V3 §7.8) cuando la respuesta llegó fuera de tiempo.
        return $isLate
            ? $next->with('status', 'El tiempo asignado a este reactivo ha concluido. Tu avance fue guardado.')
            : $next;
    }

    /**
     * Registra un incidente anti-trampa detectado en el navegador web durante
     * el intento activo y emite el evento en vivo por Reverb (MPW-V3 §7.7, ADR-042).
     */
    public function incident(AttemptIncidentStoreRequest $request, StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'incidents_count' => (int) $attempt->incidents_count,
                'status' => $attempt->status,
                'cancelled' => $attempt->status === 'cancelled_by_incident',
                'redirect_url' => route('attempts.result', $attempt),
            ]);
        }

        $updated = $this->attemptService->reportIncident(
            $attempt,
            (string) $request->validated('incident_type'),
            (string) ($request->validated('description') ?? 'Cambio de pestaña o pérdida de foco detectada en navegador web.'),
        );

        return response()->json([
            'incidents_count' => (int) $updated->incidents_count,
            'status' => $updated->status,
            'cancelled' => $updated->status === 'cancelled_by_incident',
            'redirect_url' => route('attempts.result', $updated),
        ]);
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

        $attempt->load('exam', 'answers.examQuestion.question', 'group.teacher');

        return view('exams.result-summary', [
            'attempt' => $attempt,
            'reviewAvailable' => $this->studentResultService->reviewIsAvailable($attempt),
            'summary' => $this->studentResultService->buildWebResultSummary($attempt),
        ]);
    }

    /**
     * Consulta autorizada de respuestas y soluciones modelo (RF-REP-005 / P-172).
     */
    public function review(StudentAttempt $attempt): View
    {
        $this->authorizeOwnership($attempt);

        $reviewData = $this->studentResultService->review($attempt);

        return view('student.exams.review', [
            'attempt' => $attempt->loadMissing('exam', 'group.teacher'),
            'reviewData' => $reviewData,
        ]);
    }

    /**
     * Guarda el borrador de la pregunta activa sin calificarla (RF-EXA-040). La
     * vista solo marca la respuesta como guardada al recibir esta confirmación
     * (RF-EXA-041).
     */
    public function draft(AttemptAnswerWebRequest $request, StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        if (! $this->attemptService->sessionAvailable($attempt, $this->sessionHolder())) {
            return response()->json(['saved' => false, 'message' => self::OTHER_SESSION_MESSAGE], 409);
        }

        try {
            $this->attemptService->saveDraft(
                $attempt,
                (int) $request->validated('exam_question_id'),
                $this->answerPayloadFrom($request),
            );
        } catch (DomainException $e) {
            return response()->json(['saved' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Normaliza la respuesta del formulario web según el tipo de pregunta.
     */
    private function answerPayloadFrom(AttemptAnswerWebRequest $request): mixed
    {
        return match (true) {
            $request->has('selected_option_index') => ['selected_option_index' => (int) $request->input('selected_option_index')],
            $request->has('pairs') => $request->input('pairs'),
            $request->has('given_answer') => $request->input('given_answer'),
            default => (string) ($request->input('answer_text') ?? ''),
        };
    }

    /**
     * Señal de presencia de la pestaña que presenta el intento (RF-EXA-049).
     */
    public function heartbeat(StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json(['active' => false, 'redirect_url' => route('attempts.result', $attempt)], 409);
        }

        if (! $this->attemptService->acquireSession($attempt, $this->sessionHolder())) {
            return response()->json([
                'active' => false,
                'message' => self::OTHER_SESSION_MESSAGE,
                'redirect_url' => route('student.exams.index'),
            ], 409);
        }

        return response()->json(['active' => true]);
    }

    private function sessionHolder(): string
    {
        return 'web:' . request()->session()->getId();
    }

    private function authorizeOwnership(StudentAttempt $attempt): void
    {
        abort_unless($attempt->student_id === request()->user()->id, 403);
    }
}
