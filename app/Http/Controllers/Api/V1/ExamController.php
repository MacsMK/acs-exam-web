<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamController.php
 * Ubicación:   app/Http/Controllers/Api/V1/ExamController.php
 * Descripción: api.v1.exams.index y api.v1.exams.progress (MPM-V3 Tabla
 *              12). La aplicación del examen en sí (take/answers/
 *              incidents) vive en AttemptController — este controlador
 *              solo lista y reporta avance, nunca cambia estado de
 *              intento.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * api.v1.exams.index. Docente: todos sus exámenes (armados o no).
     * Alumno: publicados y asignados a alguno de sus grupos activos.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $exams = $user->role === 'teacher'
            ? Exam::where('teacher_id', $user->id)->latest()->get()
            : Exam::where('is_published', true)
                ->whereHas('groups', fn ($group) => $group->active()
                    ->whereHas('students', fn ($student) => $student->whereKey($user->id)))
                ->get();

        return ApiResponse::success(
            $exams->map(fn (Exam $exam) => ['id' => (string) $exam->id, 'title' => $exam->title])->all(),
        );
    }

    /**
     * api.v1.exams.progress — solo el docente dueño del examen. `total_count`
     * suma los alumnos de todos los grupos asignados (un alumno inscrito en
     * más de un grupo asignado al mismo examen se contaría dos veces;
     * simplificación aceptada para esta entrega, ver
     * docs/04_DECISIONES_ADR.md). `answered_count` son los intentos que ya
     * llegaron a un estado terminal (enviado, expirado o cancelado por
     * incidente) — no los que siguen en curso.
     */
    public function progress(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($exam->teacher_id === $request->user()->id, 403);

        $totalCount = (int) $exam->groups()->withCount('students')->get()->sum('students_count');
        $answeredCount = $exam->studentAttempts()
            ->current()
            ->whereIn('status', ['submitted', 'expired', 'cancelled_by_incident'])
            ->count();

        return ApiResponse::success(['answered_count' => $answeredCount, 'total_count' => $totalCount]);
    }
}
