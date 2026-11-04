<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamNotificationService.php
 * Ubicación:      app/Services/ExamNotificationService.php
 * Requerimiento:  Notificaciones push (MPM-V4 §6.8, Tabla 14); código de
 *                 DE-01 pendiente de registro
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Decide a quién y cuándo avisar sobre el ciclo de vida de un
 *                 examen (Tabla 14): exam.assigned y exam.opened a los
 *                 alumnos de los grupos del examen; exam.started y
 *                 exam.finished al docente. Cada aviso se envía una sola vez
 *                 por examen: se reserva con un registro en acs_audit_logs
 *                 (sin columnas nuevas) antes de enviar. Un fallo al notificar
 *                 se registra y nunca interrumpe el flujo del examen.
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\PushNotifierContract;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Support\PushMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExamNotificationService
{
    public const ACTION_ASSIGNED = 'push.exam.assigned';

    public const ACTION_OPENED = 'push.exam.opened';

    public const ACTION_STARTED = 'push.exam.started';

    public const ACTION_FINISHED = 'push.exam.finished';

    public function __construct(private readonly PushNotifierContract $notifier)
    {
    }

    /**
     * Un examen se publicó: si su ventana ya está abierta se avisa que está
     * disponible; si no, que fue asignado y se abrirá más adelante.
     */
    public function examPublished(Exam $exam): void
    {
        $this->safely(function () use ($exam): void {
            if ($this->windowIsOpen($exam)) {
                $this->claim(self::ACTION_ASSIGNED, $exam);
                $this->examOpened($exam);

                return;
            }

            $this->examAssigned($exam);
        });
    }

    public function examAssigned(Exam $exam): void
    {
        $this->safely(function () use ($exam): void {
            if (! $this->claim(self::ACTION_ASSIGNED, $exam)) {
                return;
            }

            $this->notifyStudents(
                $exam,
                PushMessage::EXAM_ASSIGNED,
                'Nuevo examen asignado',
                sprintf('Se asignó el examen «%s». Revisa tu lista de exámenes.', $exam->title),
            );
        });
    }

    public function examOpened(Exam $exam): void
    {
        $this->safely(function () use ($exam): void {
            if (! $this->claim(self::ACTION_OPENED, $exam)) {
                return;
            }

            $this->notifyStudents(
                $exam,
                PushMessage::EXAM_OPENED,
                'Examen disponible',
                sprintf('El examen «%s» ya está disponible.', $exam->title),
            );
        });
    }

    /**
     * El primer alumno inició el examen: se avisa una sola vez al docente.
     */
    public function examStarted(Exam $exam, StudentAttempt $attempt): void
    {
        $this->safely(function () use ($exam, $attempt): void {
            if (! $this->claim(self::ACTION_STARTED, $exam)) {
                return;
            }

            $this->notifier->sendToUsers([(int) $exam->teacher_id], new PushMessage(
                PushMessage::EXAM_STARTED,
                'Examen en curso',
                sprintf('El examen «%s» ya comenzó.', $exam->title),
                (int) $exam->id,
                (int) $attempt->group_id,
            ));
        });
    }

    public function examFinished(Exam $exam): void
    {
        $this->safely(function () use ($exam): void {
            if (! $this->claim(self::ACTION_FINISHED, $exam)) {
                return;
            }

            $groupId = (int) DB::table('acs_exam_group')->where('exam_id', $exam->id)->min('group_id');

            $this->notifier->sendToUsers([(int) $exam->teacher_id], new PushMessage(
                PushMessage::EXAM_FINISHED,
                'Examen concluido',
                sprintf('El examen «%s» ya terminó. Revisa el avance y las calificaciones.', $exam->title),
                (int) $exam->id,
                $groupId,
            ));
        });
    }

    /**
     * Un intento se cerró: si ya no queda ningún alumno inscrito por
     * presentar, el examen terminó antes de que venza su ventana.
     */
    public function attemptClosed(StudentAttempt $attempt): void
    {
        $this->safely(function () use ($attempt): void {
            $exam = Exam::query()->find($attempt->exam_id);

            if ($exam === null || $this->enrolledStudentIds($exam) === []) {
                return;
            }

            $pending = array_diff($this->enrolledStudentIds($exam), $this->closedStudentIds($exam));

            if ($pending === []) {
                $this->examFinished($exam);
            }
        });
    }

    /**
     * Revisión periódica (comando exams:push-due): avisa de las ventanas que
     * abrieron o vencieron en las últimas 24 horas y aún no se notificaron.
     *
     * @return array{opened: int, finished: int}
     */
    public function dispatchDue(): array
    {
        $now = now();
        $since = $now->copy()->subDay();
        $counts = ['opened' => 0, 'finished' => 0];

        $toOpen = Exam::query()
            ->published()
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$since, $now])
            ->where(static fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->get();

        foreach ($toOpen as $exam) {
            if (! $this->alreadyClaimed(self::ACTION_OPENED, $exam)) {
                $this->examOpened($exam);
                $counts['opened']++;
            }
        }

        $toFinish = Exam::query()
            ->published()
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$since, $now])
            ->get();

        foreach ($toFinish as $exam) {
            if (! $this->alreadyClaimed(self::ACTION_FINISHED, $exam)) {
                $this->examFinished($exam);
                $counts['finished']++;
            }
        }

        return $counts;
    }

    private function windowIsOpen(Exam $exam): bool
    {
        $now = now();

        return ($exam->starts_at === null || $exam->starts_at->lte($now))
            && ($exam->ends_at === null || $exam->ends_at->gt($now));
    }

    private function notifyStudents(Exam $exam, string $type, string $title, string $body): void
    {
        $rows = DB::table('acs_exam_group as eg')
            ->join('acs_group_student as gs', 'gs.group_id', '=', 'eg.group_id')
            ->where('eg.exam_id', $exam->id)
            ->get(['eg.group_id', 'gs.student_id']);

        foreach ($rows->groupBy('group_id') as $groupId => $members) {
            $this->notifier->sendToUsers(
                $members->pluck('student_id')->map(static fn ($id): int => (int) $id)->all(),
                new PushMessage($type, $title, $body, (int) $exam->id, (int) $groupId),
            );
        }
    }

    /**
     * @return list<int>
     */
    private function enrolledStudentIds(Exam $exam): array
    {
        return DB::table('acs_exam_group as eg')
            ->join('acs_group_student as gs', 'gs.group_id', '=', 'eg.group_id')
            ->where('eg.exam_id', $exam->id)
            ->distinct()
            ->pluck('gs.student_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function closedStudentIds(Exam $exam): array
    {
        return StudentAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', '!=', 'in_progress')
            ->pluck('student_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function alreadyClaimed(string $action, Exam $exam): bool
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('auditable_type', Exam::class)
            ->where('auditable_id', $exam->id)
            ->exists();
    }

    /**
     * Reserva el aviso para este examen. Devuelve false si ya se había
     * enviado. El bloqueo de la fila del examen evita duplicados cuando dos
     * alumnos disparan el mismo evento al mismo tiempo.
     */
    private function claim(string $action, Exam $exam): bool
    {
        return DB::transaction(function () use ($action, $exam): bool {
            Exam::query()->whereKey($exam->id)->lockForUpdate()->first();

            if ($this->alreadyClaimed($action, $exam)) {
                return false;
            }

            AuditLog::query()->create([
                'action' => $action,
                'auditable_type' => Exam::class,
                'auditable_id' => $exam->id,
            ]);

            return true;
        });
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::error('No se pudo procesar una notificación push.', ['reason' => $e->getMessage()]);
        }
    }
}
