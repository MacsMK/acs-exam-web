<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamMonitorController.php
 * Ubicación:      app/Http/Controllers/ExamMonitorController.php
 * Requerimiento:  RW-12, RW-16, RNF-SEG-004 (Monitoreo Docente WebSockets Reverb)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.2.0
 * Descripción:    Controlador web delgado para el tablero de monitoreo y
 *                 supervisión en vivo de exámenes activos con Laravel Reverb.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Services\AttemptService;
use Collator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controlador web para la supervisión docente reactiva de exámenes en tiempo real.
 *
 * @package App\Http\Controllers
 */
class ExamMonitorController extends Controller
{
    private const FILTER_STATUSES = ['all', 'in_progress', 'submitted', 'expired', 'cancelled_by_incident', 'not_started'];

    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
        private readonly AttemptService $attemptService,
    ) {
    }

    /**
     * Muestra el tablero de monitoreo en tiempo real del examen.
     */
    public function show(Request $request, Exam $exam): View|JsonResponse
    {
        $this->authorizeTeacher($exam);

        $loadedExam = $this->examRepository->getMonitorDashboardData($exam);
        $collator = class_exists(Collator::class) ? new Collator('es_MX') : null;

        $enrolledStudents = $loadedExam->groups
            ->flatMap(static fn ($group) => $group->students)
            ->unique('id')
            ->sortBy(static fn (User $student): string => $collator?->getSortKey($student->full_name)
                ?: Str::lower(Str::ascii($student->full_name)), SORT_STRING)
            ->values();

        // RF-EXA-052: con reaplicaciones, el monitoreo muestra el intento vigente.
        $attempts = $loadedExam->studentAttempts->toBase()->sortBy('attempt_number')
            ->keyBy('student_id')->only($enrolledStudents->pluck('id')->all());
        $sequenceIds = $attempts
            ->where('status', 'in_progress')
            ->whereNull('free_close_at')
            ->flatMap(static fn (StudentAttempt $attempt): array => $attempt->question_sequence_payload ?? [])
            ->unique()->values();
        $questionTimes = $sequenceIds->isEmpty() ? collect() : ExamQuestion::query()
            ->whereIn('id', $sequenceIds)->pluck('time_limit_seconds', 'id');
        $observedAt = now();

        $students = $enrolledStudents->map(function ($student) use ($attempts, $loadedExam, $observedAt, $questionTimes): array {
            $attempt = $attempts->get($student->id);
            $status = $attempt?->status ?? 'not_started';

            if ($status === 'in_progress') {
                $attempt->setRelation('exam', $loadedExam);
                $sequence = array_unique(array_map('intval', $attempt->question_sequence_payload ?? []));
                $totalQuestionSeconds = (int) $questionTimes->only($sequence)->sum();
                $deadline = $this->attemptService->attemptDeadline($attempt, $totalQuestionSeconds);

                if ($deadline !== null && $observedAt->gt($deadline)) {
                    $status = 'expired';
                }
            }

            return [
                'id' => (int) $student->id,
                'name' => $student->full_name,
                'email' => (string) $student->email,
                'status' => $status,
                'incidents' => (int) ($attempt?->incidents_count ?? 0),
                'score' => $attempt?->final_score !== null
                    ? number_format((float) $attempt->final_score, 2, ',', '')
                    : ($status === 'submitted' ? 'Pendiente' : '—'),
                'started_at' => $attempt?->started_at?->format('H:i:s') ?? '—',
                'submitted_at' => match ($status) {
                    'submitted' => $attempt?->submitted_at?->format('H:i:s') ?? '—',
                    'expired', 'cancelled_by_incident' => 'Sin envío formal',
                    default => '—',
                },
                'attempt_id' => $attempt === null ? null : (int) $attempt->id,
            ];
        });

        $monitorSnapshot = [
            'students' => $students->all(),
            'counts' => [
                'enrolled' => $students->count(),
                'inProgress' => $students->where('status', 'in_progress')->count(),
                'submitted' => $students->where('status', 'submitted')->count(),
                'incidents' => $students->sum('incidents'),
            ],
            'maxIncidents' => $loadedExam->max_allowed_incidents === null ? null : (int) $loadedExam->max_allowed_incidents,
            'observedAt' => $observedAt->toIso8601String(),
            'observedAtLabel' => $observedAt->format('H:i:s'),
        ];

        if ($request->boolean('snapshot')) {
            return response()->json($monitorSnapshot, headers: ['Cache-Control' => 'private, no-store']);
        }

        $search = $request->query('search');
        $status = $request->query('status');
        $monitorFilters = [
            'search' => is_string($search) ? trim($search) : '',
            'status' => in_array($status, self::FILTER_STATUSES, true) ? $status : 'all',
        ];
        $term = mb_strtolower($monitorFilters['search']);
        $visibleStudents = $students->filter(static function (array $student) use ($term, $monitorFilters): bool {
            $matchesSearch = str_contains(mb_strtolower($student['name']), $term)
                || str_contains(mb_strtolower($student['email']), $term);

            return $matchesSearch && ($monitorFilters['status'] === 'all' || $student['status'] === $monitorFilters['status']);
        })->values()->all();

        $broadcast = config('broadcasting.connections.' . config('broadcasting.default'), []);
        $reverbEnabled = ($broadcast['driver'] ?? null) === 'reverb';
        $publicEndpoint = $broadcast['public'] ?? $broadcast['options'] ?? [];
        $reverbConfig = [
            'enabled' => $reverbEnabled,
            'key' => $reverbEnabled ? (string) ($broadcast['key'] ?? '') : '',
            'host' => (string) ($publicEndpoint['host'] ?? $request->getHost()),
            'port' => (int) ($publicEndpoint['port'] ?? 8080),
            'useTls' => strtolower((string) ($publicEndpoint['scheme'] ?? 'http')) === 'https',
        ];

        return view('exams.monitor', [
            'exam' => $loadedExam,
            'monitorSnapshot' => $monitorSnapshot,
            'monitorFilters' => $monitorFilters,
            'visibleStudents' => $visibleStudents,
            'snapshotUrl' => route('exams.monitor', ['exam' => $exam, 'snapshot' => 1]),
            'reverbConfig' => $reverbConfig,
        ]);
    }

    /**
     * Valida la propiedad del examen frente a ataques IDOR (RNF-SEG-004).
     */
    private function authorizeTeacher(Exam $exam): void
    {
        abort_unless((int) $exam->teacher_id === (int) request()->user()->id, 403);
    }
}
