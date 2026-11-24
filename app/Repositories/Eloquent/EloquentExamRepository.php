<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EloquentExamRepository.php
 * Ubicación:      app/Repositories/Eloquent/EloquentExamRepository.php
 * Requerimiento:  RF-EXA (Armado y Aplicación de Exámenes, MPW-V3 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio de exámenes con
 *                 Eager Loading (with, withCount, withSum), paginación en SQL,
 *                 KPIs agregados, ciclo de vida transaccional y recoteje.
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Scopes\TenantTeacherScope;
use App\Models\StudentAttempt;
use App\Models\Subject;
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
     * Columnas editables en metadatos de examen.
     *
     * @var list<string>
     */
    private const UPDATABLE_EXAM_FIELDS = [
        'title',
        'description',
        'grading_scale',
        'randomize_question_order',
        'result_visibility_mode',
        'require_webcam',
        'max_allowed_incidents',
        'starts_at',
        'ends_at',
    ];

    /**
     * Columnas editables en metadatos de apartado.
     *
     * @var list<string>
     */
    private const UPDATABLE_SECTION_FIELDS = [
        'title',
        'subject_tag',
        'subject_id',
        'requested_quantity',
        'sort_order',
    ];

    /**
     * Obtiene la lista paginada de exámenes creados por un docente con filtros,
     * conteos y suma de ponderaciones precargados en SQL.
     *
     * @param int                  $teacherId Identificador del docente propietario.
     * @param int                  $perPage   Registros por página en SQL.
     * @param array<string, mixed> $filters   Filtros opcionales (search, status, group_id).
     * @return LengthAwarePaginator<Exam>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Exam::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->forTeacherFilter($teacherId, $filters)
            ->with(['groups'])
            ->withCount([
                'sections',
                'examQuestions',
                'studentAttempts',
                'studentAttempts as pending_recoteje_count' => static function ($query): void {
                    $query->where('status', 'submitted')
                        ->where('pending_ai_count', '>', 0);
                },
            ])
            ->withSum('examQuestions as total_points_weight', 'points_weight')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->forTeacher($teacherId)
            ->with(['groups'])
            ->withCount(['sections', 'examQuestions', 'studentAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Calcula los indicadores agregados en SQL para el tablero de exámenes del docente.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return array{
     *     total: int,
     *     drafts: int,
     *     scheduled: int,
     *     published_active: int,
     *     closed: int,
     *     pending_recoteje_exams: int
     * }
     */
    public function getTeacherExamStats(int $teacherId): array
    {
        $now = now();

        $stats = Exam::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->selectRaw(
                'COUNT(*) as total, '
                . 'COALESCE(SUM(CASE WHEN NOT is_published THEN 1 ELSE 0 END), 0) as drafts, '
                . 'COALESCE(SUM(CASE WHEN is_published AND ends_at <= ? THEN 1 ELSE 0 END), 0) as closed, '
                . 'COALESCE(SUM(CASE WHEN is_published AND starts_at > ? AND ends_at > ? THEN 1 ELSE 0 END), 0) as scheduled, '
                . 'COALESCE(SUM(CASE WHEN is_published AND starts_at <= ? AND ends_at > ? THEN 1 ELSE 0 END), 0) as published_active',
                [$now, $now, $now, $now, $now]
            )
            ->first();

        $pendingRecotejeExams = (int) Exam::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->whereHas('studentAttempts', static function (Builder $query): void {
                $query->where('status', 'submitted')
                    ->where('pending_ai_count', '>', 0);
            })
            ->count();

        return [
            'total' => (int) ($stats?->total ?? 0),
            'drafts' => (int) ($stats?->drafts ?? 0),
            'scheduled' => (int) ($stats?->scheduled ?? 0),
            'published_active' => (int) ($stats?->published_active ?? 0),
            'closed' => (int) ($stats?->closed ?? 0),
            'pending_recoteje_exams' => $pendingRecotejeExams,
        ];
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
     * Busca un examen por su identificador precargando apartados, reactivos y variantes (MPW-V3 §6.7).
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
     * Busca un examen propiedad del docente precargando grupos, apartados ordenados,
     * reactivos con materia/paráfrasis, conteos y suma de ponderaciones sin N+1.
     *
     * @param int $examId    Identificador del examen.
     * @param int $teacherId Identificador del docente propietario.
     * @return Exam|null
     */
    public function findForTeacherWithDetails(int $examId, int $teacherId): ?Exam
    {
        return Exam::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->whereKey($examId)
            ->with([
                'groups' => static function ($query): void {
                    $query->orderBy('name');
                },
                'sections' => static function ($query): void {
                    $query->orderBy('sort_order')->orderBy('id');
                },
                'sections.subject',
                'sections.examQuestions' => static function ($query): void {
                    $query->orderBy('sort_order')->orderBy('id');
                },
                'sections.examQuestions.question.subject',
                'sections.examQuestions.question.paraphrases',
                'sections.examQuestions.question.questionable',
                'examQuestions' => static function ($query): void {
                    $query->orderBy('sort_order')->orderBy('id');
                },
                'examQuestions.examSection',
                'examQuestions.question.subject',
                'examQuestions.question.paraphrases',
                'examQuestions.question.questionable',
            ])
            ->withCount(['sections', 'examQuestions', 'studentAttempts'])
            ->withSum('examQuestions as total_points_weight', 'points_weight')
            ->first();
    }

    /**
     * Obtiene los exámenes publicados y vigentes dentro de su ventana horaria para un grupo (MPW-V3 §6.7).
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
                $ownedGroupIds = $this->filterOwnedGroupIds($teacherId, $groupIds);
                $exam->groups()->sync($ownedGroupIds);
            }

            return $exam->load(['groups']);
        });
    }

    /**
     * Actualiza transaccionalmente los metadatos de un examen y sincroniza sus grupos.
     *
     * @param Exam                 $exam     Examen a actualizar.
     * @param array<string, mixed> $data     Atributos validados a persistir.
     * @param array<int, int>      $groupIds Identificadores de grupos asignados.
     * @return Exam
     */
    public function updateExam(Exam $exam, array $data, array $groupIds): Exam
    {
        return DB::transaction(function () use ($exam, $data, $groupIds): Exam {
            $attributes = array_intersect_key($data, array_flip(self::UPDATABLE_EXAM_FIELDS));

            if ($attributes !== []) {
                $exam->update($attributes);
            }

            $ownedGroupIds = $this->filterOwnedGroupIds((int) $exam->teacher_id, $groupIds);
            $exam->groups()->sync($ownedGroupIds);

            return $exam->fresh(['groups']);
        });
    }

    /**
     * Elimina de forma transaccional un examen en borrador junto con sus pivotes,
     * reactivos asignados y apartados.
     *
     * @param Exam $exam Examen en borrador a eliminar.
     * @return bool
     */
    public function deleteDraftExam(Exam $exam): bool
    {
        return DB::transaction(static function () use ($exam): bool {
            $exam->groups()->detach();
            $exam->examQuestions()->delete();
            $exam->sections()->delete();

            return (bool) $exam->delete();
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
        $maxSort = $exam->sections()->max('sort_order');

        return ExamSection::create([
            'exam_id' => $exam->id,
            'title' => (string) $data['title'],
            'question_type' => (string) $data['question_type'],
            'subject_tag' => $data['subject_tag'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'requested_quantity' => (int) $data['requested_quantity'],
            'sort_order' => $maxSort !== null ? ((int) $maxSort) + 1 : 0,
        ]);
    }

    /**
     * Actualiza los metadatos de un apartado existente del examen.
     *
     * @param ExamSection          $section Apartado a modificar.
     * @param array<string, mixed> $data    Atributos validados del apartado.
     * @return ExamSection
     */
    public function updateSection(ExamSection $section, array $data): ExamSection
    {
        $attributes = array_intersect_key($data, array_flip(self::UPDATABLE_SECTION_FIELDS));

        if ($attributes !== []) {
            $section->update($attributes);
        }

        return $section->fresh(['subject']);
    }

    /**
     * Elimina transaccionalmente un apartado y sus reactivos asignados, reindexando
     * el campo sort_order de los apartados restantes del examen.
     *
     * @param ExamSection $section Apartado a eliminar.
     * @return void
     */
    public function deleteSection(ExamSection $section): void
    {
        DB::transaction(static function () use ($section): void {
            $examId = (int) $section->exam_id;

            $section->examQuestions()->delete();
            $section->delete();

            $remainingSections = ExamSection::query()
                ->where('exam_id', $examId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($remainingSections->values() as $index => $remaining) {
                if ((int) $remaining->sort_order !== $index) {
                    $remaining->update(['sort_order' => $index]);
                }
            }
        });
    }

    /**
     * Remueve un reactivo asignado de su apartado dentro de una transacción y
     * reindexa el campo sort_order de los reactivos restantes en esa sección.
     *
     * @param ExamQuestion $examQuestion Vínculo reactivo-examen a eliminar.
     * @return void
     */
    public function removeExamQuestion(ExamQuestion $examQuestion): void
    {
        DB::transaction(static function () use ($examQuestion): void {
            $sectionId = (int) $examQuestion->exam_section_id;

            $examQuestion->delete();

            $remainingQuestions = ExamQuestion::query()
                ->where('exam_section_id', $sectionId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($remainingQuestions->values() as $index => $remaining) {
                if ((int) $remaining->sort_order !== $index) {
                    $remaining->update(['sort_order' => $index]);
                }
            }
        });
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
     * Revierte la publicación de un examen regresándolo a estado borrador.
     *
     * @param Exam $exam Instancia del examen a despublicar.
     * @return Exam
     */
    public function unpublish(Exam $exam): Exam
    {
        $exam->update(['is_published' => false]);

        return $exam->fresh();
    }

    /**
     * Cierra anticipadamente la ventana de aplicación de un examen publicado.
     *
     * @param Exam $exam Instancia del examen a cerrar.
     * @return Exam
     */
    public function closeEarly(Exam $exam): Exam
    {
        $exam->update(['ends_at' => now()]);

        return $exam->fresh();
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
     * Carga el grafo completo requerido para el tablero de monitoreo en vivo sin N+1.
     *
     * @param Exam $exam Examen supervisado.
     * @return Exam
     */
    public function getMonitorDashboardData(Exam $exam): Exam
    {
        return $exam->load([
            'groups.students' => static function ($query): void {
                $query->orderBy('first_name')->orderBy('last_name');
            },
            'studentAttempts' => static function ($query): void {
                $query->orderBy('status')
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id');
            },
            'studentAttempts.student',
            'studentAttempts.group',
        ]);
    }

    /**
     * Pagina en SQL los intentos de un examen para revisión y recoteje docente.
     *
     * @param Exam                 $exam    Examen evaluado.
     * @param array<string, mixed> $filters Filtros opcionales (search, status).
     * @param int                  $perPage Registros por página en SQL.
     * @return LengthAwarePaginator<StudentAttempt>
     */
    public function paginateAttemptsForReview(Exam $exam, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $exam->studentAttempts()
            ->with(['student', 'group', 'answers.examQuestion.question']);

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $driver = $query->getConnection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
            $pattern = '%' . $search . '%';
            $normalized = Subject::normalizeName($search);

            $query->whereHas('student', static function (Builder $studentQuery) use (
                $driver,
                $likeOperator,
                $pattern,
                $normalized
            ): void {
                $studentQuery->where('first_name', $likeOperator, $pattern)
                    ->orWhere('last_name', $likeOperator, $pattern)
                    ->orWhere('email', $likeOperator, $pattern);

                if ($normalized !== '') {
                    $normalizedPattern = '%' . $normalized . '%';
                    $studentQuery->orWhereRaw(
                        self::foldUserFieldSqlExpression('first_name', $driver) . ' LIKE ?',
                        [$normalizedPattern]
                    )->orWhereRaw(
                        self::foldUserFieldSqlExpression('last_name', $driver) . ' LIKE ?',
                        [$normalizedPattern]
                    );
                }
            });
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : 'all';
        if ($status !== '' && $status !== 'all') {
            match ($status) {
                'pending_recoteje' => $query
                    ->where('status', 'submitted')
                    ->where('pending_ai_count', '>', 0),
                'graded' => $query
                    ->where('status', 'submitted')
                    ->where('pending_ai_count', 0),
                'cancelled' => $query->where('status', 'cancelled_by_incident'),
                default => null,
            };
        }

        return $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Busca un intento específico acotado al examen indicado con todas las relaciones
     * requeridas para la vista de recoteje docente sin lazy loading.
     *
     * @param Exam $exam      Examen propietario del intento.
     * @param int  $attemptId Identificador del intento de alumno.
     * @return StudentAttempt|null
     */
    public function findAttemptForReviewWithAnswers(Exam $exam, int $attemptId): ?StudentAttempt
    {
        return $exam->studentAttempts()
            ->whereKey($attemptId)
            ->with([
                'student',
                'group',
                'answers' => static function ($query): void {
                    $query->orderBy('id');
                },
                'answers.examQuestion.question.questionable',
                'answers.examQuestion.question.paraphrases',
                'answers.examQuestion.examSection',
            ])
            ->first();
    }

    /**
     * Registra de forma transaccional un intento de examen enviado por un alumno (MPW-V3 §6.7).
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

    /**
     * Filtra los identificadores de grupos verificando propiedad docente (anti-IDOR).
     *
     * @param int             $teacherId Identificador del docente propietario.
     * @param array<int, int> $groupIds  Identificadores candidatos de grupos.
     * @return array<int, int>
     */
    private function filterOwnedGroupIds(int $teacherId, array $groupIds): array
    {
        $cleanIds = array_values(array_unique(array_filter(
            array_map('intval', $groupIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($cleanIds === []) {
            return [];
        }

        return Group::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->whereIn('id', $cleanIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Construye una expresión SQL portable para filtrar nombres de estudiantes
     * de forma insensible a mayúsculas y diacríticos en PostgreSQL y SQLite/MySQL.
     *
     * @param string $column Nombre de columna en acs_users.
     * @param string $driver Driver de base de datos activo.
     * @return string
     */
    private static function foldUserFieldSqlExpression(string $column, string $driver): string
    {
        if ($driver === 'pgsql') {
            return "translate(lower(COALESCE({$column}, '')), 'áéíóúàèìòùäëïöüâêîôûãõñçÁÉÍÓÚÀÈÌÒÙÄËÏÖÜÂÊÎÔÛÃÕÑÇ', 'aeiouaeiouaeiouaeiouaoncaeiouaeiouaeiouaeiouaonc')";
        }

        $expr = "LOWER(COALESCE({$column}, ''))";
        $replacements = [
            'Á' => 'a',
            'á' => 'a',
            'É' => 'e',
            'é' => 'e',
            'Í' => 'i',
            'í' => 'i',
            'Ó' => 'o',
            'ó' => 'o',
            'Ú' => 'u',
            'ú' => 'u',
            'Ü' => 'u',
            'ü' => 'u',
            'Ñ' => 'n',
            'ñ' => 'n',
        ];

        foreach ($replacements as $from => $to) {
            $expr = "REPLACE({$expr}, '{$from}', '{$to}')";
        }

        return $expr;
    }
}
