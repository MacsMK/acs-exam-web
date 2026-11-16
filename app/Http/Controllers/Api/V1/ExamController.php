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
 *              intento. Para el alumno, cada examen incluye el docente, su
 *              grupo con la materia, el número de preguntas y el estado de
 *              su intento (ADR-056).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Services\AttemptService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * Intentos que puede usar un alumno por examen: uno, garantizado por el
     * índice único (exam_id, student_id) de acs_student_attempts (ADR-036,
     * ADR-037: sin reaplicaciones).
     */
    private const ATTEMPTS_PER_STUDENT = 1;

    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    /**
     * api.v1.exams.index. Docente: todos sus exámenes (armados o no).
     * Alumno (RF-EXA-026): solo publicados y asignados a algún grupo suyo que
     * siga abierto (RF-GRU-019, mismo criterio que el panel web), con los
     * datos de la tarjeta del alumno (ADR-056).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'teacher') {
            $exams = Exam::where('teacher_id', $user->id)->latest()->get();

            return ApiResponse::success(
                $exams->map(fn (Exam $exam) => ['id' => (string) $exam->id, 'title' => $exam->title])->all(),
            );
        }

        $openGroupsOfStudent = fn ($query) => $query
            ->where('acs_groups.is_closed', false)
            ->whereHas('students', fn ($students) => $students->where('acs_users.id', $user->id));

        $exams = Exam::where('is_published', true)
            ->whereHas('groups', $openGroupsOfStudent)
            ->with([
                'teacher',
                'groups' => $openGroupsOfStudent,
                'studentAttempts' => fn ($query) => $query->where('student_id', $user->id),
            ])
            ->withCount('examQuestions')
            ->get();

        return ApiResponse::success(
            $exams->map(fn (Exam $exam) => $this->studentPayload($exam))->all(),
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
            ->whereIn('status', ['submitted', 'expired', 'cancelled_by_incident'])
            ->count();

        return ApiResponse::success(['answered_count' => $answeredCount, 'total_count' => $totalCount]);
    }

    /**
     * Examen visto por el alumno (ADR-056). Requiere `teacher`, `groups`
     * (solo los del alumno), `studentAttempts` (solo el suyo) y el conteo
     * `exam_questions_count` ya cargados. Las fechas y la duración se
     * agregan cuando se resuelva la fecha por grupo (CAM-002).
     *
     * @return array<string, mixed>
     */
    private function studentPayload(Exam $exam): array
    {
        $attempt = $exam->studentAttempts->first();

        $group = $attempt !== null
            ? ($exam->groups->firstWhere('id', $attempt->group_id) ?? $exam->groups->first())
            : $exam->groups->first();

        return [
            'id' => (string) $exam->id,
            'title' => $exam->title,
            'teacher_name' => $exam->teacher?->full_name,
            'group' => $group === null ? null : [
                'id' => (string) $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
            ],
            'questions_count' => (int) $exam->exam_questions_count,
            'attempts_allowed' => self::ATTEMPTS_PER_STUDENT,
            'attempts_used' => $attempt === null ? 0 : 1,
            'attempt_status' => $this->attemptStatus($attempt, $exam),
        ];
    }

    /**
     * Estado del intento del alumno, o null si no lo ha iniciado. Un intento
     * en curso cuyo plazo ya venció se informa como `expired`, aunque en la
     * base siga en `in_progress`: el servidor solo lo marca al intentar
     * enviarlo (ADR-036, punto 3).
     */
    private function attemptStatus(?StudentAttempt $attempt, Exam $exam): ?string
    {
        if ($attempt === null) {
            return null;
        }

        if ($attempt->status !== 'in_progress') {
            return $attempt->status;
        }

        $attempt->setRelation('exam', $exam);

        return $this->attemptService->isInProgress($attempt) ? 'in_progress' : 'expired';
    }
}
