<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradeController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GradeController.php
 * Descripción: api.v1.grades.index y api.v1.grades.statistics (MPM-V4
 *              Tabla 12, MPW-V2 §6.1). Delega las consultas agregadas sin
 *              N+1 a TeacherGradingService.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TeacherGradingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly TeacherGradingService $gradingService)
    {
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
}
