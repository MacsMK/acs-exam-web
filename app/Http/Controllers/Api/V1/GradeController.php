<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradeController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GradeController.php
 * Descripción: api.v1.grades.index, .statistics, .show y .review (MPM-V3
 *              Tabla 12, MPW-V2 §6.1). Delega las consultas agregadas sin
 *              N+1 a TeacherGradingService y las consultas del estudiante
 *              sobre su propio resultado a StudentResultService (ADR-037).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentResultService;
use App\Services\TeacherGradingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly TeacherGradingService $gradingService,
        private readonly StudentResultService $studentResults,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $this->gradingService->paginateGradesForUser(
            $request->user(),
            self::PER_PAGE,
            $page,
            $request->url(),
            $request->query(),
        );

        return ApiResponse::success($paginator);
    }

    public function statistics(Request $request): JsonResponse
    {
        $rows = $this->gradingService->gradeRowsForUser($request->user());

        return ApiResponse::success(['grades' => $rows->values()->all()]);
    }

    /**
     * Detalle de un resultado propio: pendiente de evaluación, o puntos por
     * apartado y pregunta con los comentarios del docente (ADR-037).
     */
    public function show(Request $request, string $attempt): JsonResponse
    {
        $result = $this->studentResults->ownedAttemptOrFail($this->student($request), (int) $attempt);

        return ApiResponse::success($this->studentResults->detail($result));
    }

    /**
     * Respuestas del estudiante y soluciones de referencia; 403 hasta que la
     * calificación esté publicada y el acceso al examen haya cerrado.
     */
    public function review(Request $request, string $attempt): JsonResponse
    {
        $result = $this->studentResults->ownedAttemptOrFail($this->student($request), (int) $attempt);

        return ApiResponse::success($this->studentResults->review($result));
    }

    private function student(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->role === 'student', 403, 'Esta consulta es exclusiva del estudiante.');

        return $user;
    }
}
