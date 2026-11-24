<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\StudentAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class StudentExamAvailability
{
    public function __construct(private readonly AttemptService $attempts)
    {
    }

    public function forStudent(User $student): Collection
    {
        return $this->describe($this->assignedExams($student)->get())
            ->sortBy([
                ['priority', 'asc'],
                ['dateOrder', 'asc'],
                ['id', 'desc'],
            ])->values();
    }

    public function forExam(Exam $exam, User $student): ?array
    {
        return $this->describe($this->assignedExams($student)->whereKey($exam->id)->get())->first();
    }

    /**
     * Carga los tiempos de las secuencias fijadas al inicio en una sola consulta.
     *
     * @param Collection<int, StudentAttempt> $attempts
     * @return Collection<int, ?CarbonInterface> Plazo indexado por ID del intento.
     */
    public function deadlinesFor(Collection $attempts): Collection
    {
        $inProgress = $attempts->where('status', 'in_progress');
        $sequential = $inProgress->filter(static fn (StudentAttempt $attempt): bool => $attempt->free_close_at === null);
        $questionIds = $sequential
            ->flatMap(static fn (StudentAttempt $attempt): array => $attempt->question_sequence_payload ?? [])
            ->unique()
            ->values();
        $secondsByQuestion = $questionIds->isEmpty()
            ? collect()
            : ExamQuestion::query()->whereKey($questionIds->all())->pluck('time_limit_seconds', 'id');

        (new EloquentCollection($sequential->all()))->loadMissing('exam');

        return $inProgress->mapWithKeys(function (StudentAttempt $attempt) use ($secondsByQuestion): array {
            $totalSeconds = $secondsByQuestion->only($attempt->question_sequence_payload ?? [])->sum();

            return [$attempt->id => $this->attempts->attemptDeadline($attempt, (int) $totalSeconds)];
        });
    }

    private function assignedExams(User $student): Builder
    {
        $activeMembership = static fn ($groups) => $groups->active()
            ->whereHas('students', static fn (Builder $students) => $students->whereKey($student->id));

        return Exam::query()
            ->published()
            ->whereHas('groups', $activeMembership)
            ->with([
                'groups' => static fn ($groups) => $activeMembership($groups)->orderBy('acs_groups.name'),
                'teacher',
                'studentAttempts' => static fn ($attempts) => $attempts->current()
                    ->where('student_id', $student->id)
                    ->with(['group' => $activeMembership]),
            ])
            ->withCount('examQuestions')
            ->withSum('examQuestions', 'time_limit_seconds');
    }

    private function describe(Collection $exams): Collection
    {
        $latestAttempts = $exams->flatMap(static fn (Exam $exam) => $exam->studentAttempts);
        $examsById = $exams->keyBy('id');
        foreach ($latestAttempts as $attempt) {
            $attempt->setRelation('exam', $examsById->get($attempt->exam_id));
        }

        $deadlines = $this->deadlinesFor($latestAttempts);
        $now = now();

        return $exams->map(function (Exam $exam) use ($deadlines, $now): array {
            $attempt = $exam->studentAttempts->first();
            $deadline = $attempt ? $deadlines->get($attempt->id) : null;

            return $this->describeExam($exam, $attempt, $deadline, $now);
        });
    }

    private function describeExam(Exam $exam, ?StudentAttempt $attempt, ?CarbonInterface $deadline, CarbonInterface $now): array
    {
        $window = $this->attempts->examWindowState($exam, $now);
        $state = $window;
        $action = null;
        $reapplicationNumber = null;

        if ($attempt?->status === 'in_progress' && $attempt->group !== null) {
            $due = $this->attempts->hasReachedDeadline($attempt, $deadline, $now);
            $state = $due
                ? ($attempt->free_close_at !== null ? Exam::STATUS_CLOSED : 'expired')
                : 'in_progress';
            $action = $due ? 'result' : 'resume';
        } elseif ($attempt?->status === 'in_progress') {
            $state = Exam::STATUS_CLOSED;
        } elseif ($attempt === null || $attempt->reapplication_authorized_at !== null && $window !== Exam::STATUS_CLOSED) {
            if ($window === Exam::STATUS_ACTIVE) {
                $action = 'start';
            }
            if ($attempt !== null) {
                $reapplicationNumber = $attempt->attempt_number + 1;
            }
        } else {
            $state = match ($attempt->status) {
                'expired', 'cancelled_by_incident' => $attempt->status,
                default => 'submitted',
            };
            if ($attempt->group !== null) {
                $action = 'result';
            }
        }

        [$statusLabel, $statusVariant] = match ($state) {
            'in_progress' => ['En progreso', 'warning'],
            Exam::STATUS_ACTIVE => ['Activo', 'accent'],
            Exam::STATUS_SCHEDULED => ['Programado', 'tech'],
            'submitted' => ['Finalizado', 'neutral'],
            'expired' => ['Expirado', 'neutral'],
            'cancelled_by_incident' => ['Cancelado', 'danger'],
            default => ['Cerrado', 'neutral'],
        };
        [$href, $actionLabel, $actionVariant] = match ($action) {
            'resume' => [route('attempts.show', $attempt), 'Continuar', 'primary'],
            'start' => [route('student.exams.instructions', $exam), 'Abrir examen', 'primary'],
            'result' => [route('attempts.result', $attempt), 'Ver detalles', 'secondary'],
            default => [null, null, null],
        };
        $editEndsAt = $attempt?->free_edit_until;
        $attemptHasGrace = $attempt?->free_close_at !== null && $editEndsAt?->lt($attempt->free_close_at);

        return [
            'id' => $exam->id,
            'exam' => $exam,
            'groupNames' => in_array($action, ['resume', 'result'], true)
                ? $attempt->group->name
                : $exam->groups->pluck('name')->implode(', '),
            'state' => $state,
            'section' => in_array($state, ['in_progress', Exam::STATUS_ACTIVE, Exam::STATUS_SCHEDULED], true)
                ? $state
                : Exam::STATUS_CLOSED,
            'action' => $action,
            'href' => $href,
            'actionLabel' => $actionLabel,
            'actionVariant' => $actionVariant,
            'statusLabel' => $statusLabel,
            'statusVariant' => $statusVariant,
            'durationMinutes' => $exam->maxDurationMinutes((int) $exam->exam_questions_sum_time_limit_seconds),
            'startsAt' => $exam->starts_at,
            'endsAt' => $exam->ends_at,
            'attemptDeadline' => $deadline,
            'attemptEndsAt' => $editEndsAt ?? $deadline,
            'attemptHasGrace' => $attemptHasGrace,
            'reapplicationNumber' => $reapplicationNumber,
            'priority' => match ($state) {
                'in_progress' => 0,
                Exam::STATUS_ACTIVE => 1,
                Exam::STATUS_SCHEDULED => 2,
                default => 3,
            },
            'dateOrder' => match ($state) {
                'in_progress' => $deadline?->timestamp ?? PHP_INT_MAX,
                Exam::STATUS_ACTIVE => $exam->ends_at?->timestamp ?? PHP_INT_MAX,
                Exam::STATUS_SCHEDULED => $exam->starts_at?->timestamp ?? PHP_INT_MAX,
                default => -($attempt?->submitted_at?->timestamp ?? $exam->ends_at?->timestamp ?? 0),
            },
        ];
    }
}
