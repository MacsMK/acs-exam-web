<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradeController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GradeController.php
 * Descripción: api.v1.grades.index y api.v1.grades.statistics (MPM-V4
 *              Tabla 12). Para el alumno: sus propios intentos ya
 *              calificados (final_score no nulo). Para el docente: el
 *              promedio del grupo por examen propio — no existe todavía
 *              un concepto real de "ponderación entre exámenes" conectado
 *              a UI (GradeCalculatorService sí calcula la fórmula de
 *              periodo, acs_evaluation_periods.formula, pero sin endpoint
 *              propio): por eso `weight` se deja fijo en 100.0 para
 *              ambos roles, documentado como decisión explícita (ver
 *              docs/04_DECISIONES_ADR.md), no como un dato real del
 *              servidor.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GradeController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $rows = $this->gradeRows($request->user());

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ApiResponse::success($paginator);
    }

    public function statistics(Request $request): JsonResponse
    {
        return ApiResponse::success(['grades' => $this->gradeRows($request->user())->values()->all()]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function gradeRows(User $user): Collection
    {
        if ($user->role === 'teacher') {
            return $this->teacherGradeRows($user);
        }

        return StudentAttempt::where('student_id', $user->id)
            ->whereNotNull('final_score')
            ->with('exam')
            ->latest('submitted_at')
            ->get()
            ->map(fn (StudentAttempt $attempt) => $this->payload(
                (string) $attempt->id,
                $attempt->exam->title,
                (float) $attempt->final_score,
                $attempt->exam->grading_scale,
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function teacherGradeRows(User $teacher): Collection
    {
        return Exam::where('teacher_id', $teacher->id)
            ->where('is_published', true)
            ->get()
            ->map(function (Exam $exam) {
                $scores = StudentAttempt::where('exam_id', $exam->id)->whereNotNull('final_score')->pluck('final_score');

                if ($scores->isEmpty()) {
                    return null;
                }

                return $this->payload((string) $exam->id, $exam->title, (float) $scores->avg(), $exam->grading_scale);
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $id, string $examName, float $score, string $gradingScale): array
    {
        $passingThreshold = $gradingScale === '0-100' ? 60.0 : 6.0;

        return [
            'id' => $id,
            'exam_name' => $examName,
            'score' => round($score, 2),
            'weight' => 100.0,
            'is_passing' => $score >= $passingThreshold,
        ];
    }
}
