<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptController.php
 * Ubicación:   app/Http/Controllers/Api/V1/AttemptController.php
 * Descripción: Aplicación contrarreloj del examen desde la app móvil
 *              (Módulo 7). Envoltura JSON delgada sobre AttemptService
 *              (lógica compartida con el panel web, app/Services).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    public function start(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptService->start($exam, $request->user());

        return response()->json(['attempt' => $attempt]);
    }

    public function currentQuestion(StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        $current = $this->attemptService->currentQuestion($attempt);

        return response()->json($current ?? ['finished' => true]);
    }

    public function submitAnswer(Request $request, StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        $data = $request->validate([
            'exam_question_id' => ['required', 'integer', 'exists:acs_exam_question,id'],
            'answer_payload' => ['required'],
        ]);

        $this->attemptService->submitAnswer($attempt, (int) $data['exam_question_id'], $data['answer_payload']);

        return response()->json(['message' => 'Respuesta registrada.']);
    }

    public function submitFinal(StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        $attempt = $this->attemptService->submitFinal($attempt);

        return response()->json(['attempt' => $attempt, 'result_visibility_mode' => $attempt->exam->result_visibility_mode]);
    }

    private function authorizeOwnership(StudentAttempt $attempt): void
    {
        abort_unless($attempt->student_id === request()->user()->id, 403);
    }
}
