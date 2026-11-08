<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     TeacherGradingService.php
 * Ubicación:   app/Services/TeacherGradingService.php
 * Descripción: Consulta optimizada sin N+1 de calificaciones y estadísticas
 *              para docentes y alumnos (Módulo 8, MPW-V2 §6.1).
 * ============================================================================
 */

namespace App\Services;

use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TeacherGradingService
{
    public function __construct(private readonly StudentResultService $studentResults)
    {
    }

    /**
     * Devuelve el listado paginado de calificaciones sin N+1 y preservando el
     * contrato exacto esperado por la aplicación móvil.
     *
     * @param array<string, mixed> $query
     */
    public function paginateGradesForUser(
        User $user,
        int $perPage = 20,
        int $page = 1,
        string $path = '',
        array $query = [],
    ): LengthAwarePaginator {
        $rows = $user->role === 'teacher'
            ? $this->gradeRowsForUser($user)
            : $this->studentListRows($user);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $path, 'query' => $query],
        );
    }

    /**
     * Listado del alumno: calificaciones publicadas y, sin dato alguno de
     * calificación, los intentos con evaluación pendiente (RF-REP-002),
     * del más reciente al más antiguo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function studentListRows(User $student): Collection
    {
        $published = StudentAttempt::where('student_id', $student->id)
            ->inCurrentGroupsOfStudent($student->id)
            ->whereNotNull('final_score')
            ->with('exam')
            ->get()
            ->map(fn (StudentAttempt $attempt): array => [
                'submitted_at' => $attempt->submitted_at,
                'row' => $this->formatPayload(
                    (string) $attempt->id,
                    $attempt->exam->title,
                    (float) $attempt->final_score,
                    $attempt->exam->grading_scale,
                ),
            ]);

        return $published
            ->concat($this->studentResults->pendingRowsForStudent($student))
            ->sortByDesc(fn (array $entry) => $entry['submitted_at']?->getTimestamp() ?? 0)
            ->pluck('row')
            ->values();
    }

    /**
     * Obtiene las filas de calificación para alumno o docente en una sola
     * consulta agregada (sin N+1).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function gradeRowsForUser(User $user): Collection
    {
        if ($user->role === 'teacher') {
            return $this->teacherGradeRows($user);
        }

        return StudentAttempt::where('student_id', $user->id)
            ->inCurrentGroupsOfStudent($user->id)
            ->whereNotNull('final_score')
            ->with('exam')
            ->latest('submitted_at')
            ->get()
            ->map(fn (StudentAttempt $attempt) => $this->formatPayload(
                (string) $attempt->id,
                $attempt->exam->title,
                (float) $attempt->final_score,
                $attempt->exam->grading_scale,
            ));
    }

    /**
     * Calcula el promedio por examen publicado del docente usando agregación
     * SQL (withAvg + whereHas) para erradicar el N+1 sobre acs_student_attempts.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function teacherGradeRows(User $teacher): Collection
    {
        return Exam::where('teacher_id', $teacher->id)
            ->where('is_published', true)
            ->whereHas('studentAttempts', static fn ($q) => $q->whereNotNull('final_score'))
            ->withAvg(
                ['studentAttempts as average_final_score' => static fn ($q) => $q->whereNotNull('final_score')],
                'final_score',
            )
            ->get()
            ->map(fn (Exam $exam) => $this->formatPayload(
                (string) $exam->id,
                $exam->title,
                (float) ($exam->average_final_score ?? 0.0),
                $exam->grading_scale,
            ))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPayload(string $id, string $examName, float $score, string $gradingScale): array
    {
        $passingThreshold = $gradingScale === '0-100' ? 60.0 : 6.0;

        return [
            'id' => $id,
            'exam_name' => $examName,
            'score' => round($score, 2),
            'weight' => 100.0,
            'is_passing' => $score >= $passingThreshold,
            'status' => 'published',
        ];
    }
}
