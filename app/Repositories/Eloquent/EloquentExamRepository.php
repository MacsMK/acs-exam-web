<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EloquentExamRepository.php
 * Ubicación:      app/Repositories/Eloquent/EloquentExamRepository.php
 * Requerimiento:  RF-EXA (Armado y Aplicación de Exámenes, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio de exámenes con
 *                 Eager Loading (with, withCount), paginación en SQL y
 *                 aislamiento multi-docente (scopeForTeacher).
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Repositories\Contracts\ExamRepositoryInterface;

/**
 * Repositorio Eloquent para la entidad Exam y sus apartados.
 *
 * @package App\Repositories\Eloquent
 */
class EloquentExamRepository implements ExamRepositoryInterface
{
    /**
     * Estados terminales de un intento para el cómputo de avance docente.
     */
    private const TERMINAL_ATTEMPT_STATUSES = [
        'submitted',
        'expired',
        'cancelled_by_incident',
    ];

    /**
     * Obtiene la lista paginada de exámenes creados por un docente con conteos precargados.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Exam>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 15): LengthAwarePaginator
    {
        return Exam::query()
            ->forTeacher($teacherId)
            ->with(['groups'])
            ->withCount(['sections', 'examQuestions', 'studentAttempts'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Obtiene la colección de exámenes creados por un docente con relaciones y conteos en SQL.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Exam>
     */
    public function getForTeacher(int $teacherId): Collection
    {
        return Exam::query()
            ->forTeacher($teacherId)
            ->with(['groups'])
            ->withCount(['sections', 'examQuestions', 'studentAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Obtiene los exámenes publicados asignados a los grupos donde está inscrito el alumno.
     *
     * @param int $studentId Identificador del alumno.
     * @return Collection<int, Exam>
     */
    public function getPublishedForStudent(int $studentId): Collection
    {
        return Exam::query()
            ->published()
            ->whereHas('groups.students', fn (Builder $query) => $query->where('acs_users.id', $studentId))
            ->with(['sections', 'groups'])
            ->withCount(['examQuestions'])
            ->latest()
            ->get();
    }

    /**
     * Busca un examen por su identificador precargando apartados, reactivos y variantes (MPW-V2 §6.7).
     *
     * @param int $examId Identificador del examen.
     * @return Exam|null
     */
    public function findByIdWithQuestions(int $examId): ?Exam
    {
        return Exam::query()
            ->with(['sections.examQuestions.question.questionable', 'groups'])
            ->withCount(['sections', 'examQuestions', 'studentAttempts'])
            ->find($examId);
    }

    /**
     * Obtiene los exámenes publicados y vigentes dentro de su ventana horaria para un grupo (MPW-V2 §6.7).
     *
     * @param int $groupId Identificador del grupo.
     * @return Collection<int, Exam>
     */
    public function getActiveExamsForGroup(int $groupId): Collection
    {
        return Exam::query()
            ->active()
            ->whereHas('groups', fn (Builder $query) => $query->where('acs_groups.id', $groupId))
            ->with(['sections'])
            ->withCount(['examQuestions'])
            ->get();
    }

    /**
     * Crea un examen para un docente y sincroniza atómicamente los grupos asignados.
     *
     * @param int                  $teacherId Identificador del docente propietario.
     * @param array<string, mixed> $data      Atributos validados del examen.
     * @param array<int, int>      $groupIds  Identificadores de los grupos asignados.
     * @return Exam
     */
    public function createForTeacher(int $teacherId, array $data, array $groupIds = []): Exam
    {
        return DB::transaction(function () use ($teacherId, $data, $groupIds): Exam {
            $exam = Exam::create([
                'teacher_id' => $teacherId,
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'grading_scale' => (string) $data['grading_scale'],
                'randomize_question_order' => (bool) ($data['randomize_question_order'] ?? true),
                'result_visibility_mode' => (string) $data['result_visibility_mode'],
                'require_webcam' => (bool) ($data['require_webcam'] ?? false),
                'max_allowed_incidents' => (int) $data['max_allowed_incidents'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'share_token' => (string) ($data['share_token'] ?? Str::random(32)),
                'is_published' => false,
            ]);

            if ($groupIds !== []) {
                $exam->groups()->sync($groupIds);
            }

            return $exam->load(['groups']);
        });
    }

    /**
     * Crea un apartado dentro del examen calculando su orden de aparición.
     *
     * @param Exam                 $exam Examen al que pertenece el apartado.
     * @param array<string, mixed> $data Datos validados del apartado.
     * @return ExamSection
     */
    public function createSection(Exam $exam, array $data): ExamSection
    {
        return ExamSection::create([
            'exam_id' => $exam->id,
            'title' => (string) $data['title'],
            'question_type' => (string) $data['question_type'],
            'requested_quantity' => (int) $data['requested_quantity'],
            'sort_order' => $exam->sections()->count(),
        ]);
    }

    /**
     * Marca un examen como publicado.
     *
     * @param Exam $exam Instancia del examen a publicar.
     * @return Exam
     */
    public function publish(Exam $exam): Exam
    {
        $exam->update(['is_published' => true]);

        return $exam;
    }

    /**
     * Calcula en SQL las métricas de avance (intentos terminales vs total de alumnos asignados).
     *
     * @param Exam $exam Examen del cual se consulta el progreso.
     * @return array{answered_count: int, total_count: int}
     */
    public function getProgressStats(Exam $exam): array
    {
        $totalCount = (int) $exam->groups()->withCount('students')->get()->sum('students_count');
        $answeredCount = $exam->studentAttempts()
            ->whereIn('status', self::TERMINAL_ATTEMPT_STATUSES)
            ->count();

        return [
            'answered_count' => $answeredCount,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Registra de forma transaccional un intento de examen enviado por un alumno (MPW-V2 §6.7).
     *
     * @param int                  $examId    Identificador del examen.
     * @param int                  $studentId Identificador del alumno.
     * @param array<string, mixed> $answers   Respuestas asociadas al intento.
     * @return Exam
     */
    public function saveExamAttempt(int $examId, int $studentId, array $answers): Exam
    {
        return DB::transaction(function () use ($examId, $studentId, $answers): Exam {
            $exam = Exam::query()->with(['groups'])->findOrFail($examId);
            $groupId = (int) ($exam->groups->first()?->id ?? $answers['group_id'] ?? 0);

            $exam->studentAttempts()->updateOrCreate(
                ['student_id' => $studentId],
                [
                    'group_id' => $groupId,
                    'question_sequence_payload' => $answers['question_sequence_payload'] ?? [],
                    'current_question_index' => (int) ($answers['current_question_index'] ?? 0),
                    'started_at' => $answers['started_at'] ?? now(),
                    'submitted_at' => now(),
                    'status' => 'submitted',
                ]
            );

            return $exam;
        });
    }
}
