<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GroupRetentionService.php
 * Ubicación:      app/Services/GroupRetentionService.php
 * Requerimiento:  RF-GRU-015 (Eliminación automática de grupos, CAM-009),
 *                 RF-SUS-010, RNF-PRI-002
 * Actividad:      ACT-WEB-022
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Vigencia de los grupos cerrados: avisa al docente 30 días
 *                 antes y elimina los grupos cerrados hace 12 meses que no
 *                 se reabrieron. Borra su información académica (intentos,
 *                 respuestas y sus historiales, periodos, calificaciones e
 *                 incorporaciones) y conserva el registro del grupo con
 *                 borrado lógico para no restablecer la prueba gratuita.
 * ============================================================================
 */

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Aplica la vigencia de 12 meses de los grupos cerrados (RF-GRU-015, CAM-009).
 */
class GroupRetentionService
{
    /**
     * Meses que se conserva un grupo cerrado antes de eliminarse.
     */
    public const RETENTION_MONTHS = 12;

    /**
     * Días de anticipación del aviso al docente.
     */
    public const WARNING_DAYS = 30;

    public const ACTION_WARNING = 'group.deletion_warning_sent';

    public const ACTION_DELETED = 'group.auto_deleted';

    /**
     * Fecha en que se eliminará un grupo cerrado; null si está activo o no tiene fecha de cierre.
     */
    public function deletionDateFor(Group $group): ?CarbonInterface
    {
        if (! $group->is_closed || $group->closed_at === null) {
            return null;
        }

        return Carbon::parse($group->closed_at)->addMonths(self::RETENTION_MONTHS);
    }

    /**
     * Avisa por correo a los docentes cuyos grupos se eliminarán en los próximos 30 días.
     * Cada cierre recibe un solo aviso (se registra en la bitácora).
     *
     * @return int Avisos enviados.
     */
    public function sendWarnings(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $limit = $now->copy()->subMonths(self::RETENTION_MONTHS)->addDays(self::WARNING_DAYS);
        $sent = 0;

        $this->closedGroupsQuery()
            ->where('closed_at', '<=', $limit)
            ->where('closed_at', '>', $now->copy()->subMonths(self::RETENTION_MONTHS))
            ->with('teacher')
            ->each(function (Group $group) use (&$sent): void {
                if ($this->warningAlreadySent($group)) {
                    return;
                }

                // Si el correo falla no se registra el aviso; se reintenta en la siguiente ejecución (RNF-CON-004)
                if (! $this->mailWarning($group)) {
                    return;
                }

                AuditLog::create([
                    'user_id' => null,
                    'action' => self::ACTION_WARNING,
                    'auditable_type' => Group::class,
                    'auditable_id' => $group->id,
                    'new_values' => [
                        'closed_at' => Carbon::parse($group->closed_at)->toIso8601String(),
                        'deletion_date' => $this->deletionDateFor($group)?->toDateString(),
                    ],
                ]);

                $sent++;
            });

        return $sent;
    }

    /**
     * Elimina los grupos cerrados hace 12 meses o más que no se reabrieron.
     *
     * @return int Grupos eliminados.
     */
    public function purgeExpired(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $deleted = 0;

        $this->closedGroupsQuery()
            ->where('closed_at', '<=', $now->copy()->subMonths(self::RETENTION_MONTHS))
            ->each(function (Group $group) use (&$deleted): void {
                $this->purge($group);
                $deleted++;
            });

        return $deleted;
    }

    /**
     * Borra la información académica del grupo y aplica el borrado lógico al registro.
     */
    public function purge(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $attemptIds = StudentAttempt::where('group_id', $group->id)->pluck('id');
            $answerIds = StudentAnswer::whereIn('student_attempt_id', $attemptIds)->pluck('id');

            $studentsCount = $group->students()->count();

            // Historiales individuales de calificación de esas respuestas (RF-EVA-013, RNF-PRI-002)
            AuditLog::where('auditable_type', StudentAnswer::class)
                ->whereIn('auditable_id', $answerIds)
                ->delete();

            // La llave foránea de intentos es restrictiva; sus respuestas se eliminan en cascada
            StudentAttempt::whereIn('id', $attemptIds)->delete();

            // Periodos (con sus calificaciones en cascada), incorporaciones y asignación de exámenes
            $group->evaluationPeriods()->delete();
            $group->students()->detach();
            $group->exams()->detach();

            $group->delete();

            AuditLog::create([
                'user_id' => null,
                'action' => self::ACTION_DELETED,
                'auditable_type' => Group::class,
                'auditable_id' => $group->id,
                'new_values' => [
                    'closed_at' => Carbon::parse($group->closed_at)->toIso8601String(),
                    'students_removed' => $studentsCount,
                    'attempts_deleted' => $attemptIds->count(),
                ],
            ]);
        });
    }

    /**
     * @return Builder<Group>
     */
    private function closedGroupsQuery(): Builder
    {
        return Group::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_closed', true)
            ->whereNotNull('closed_at');
    }

    private function warningAlreadySent(Group $group): bool
    {
        return AuditLog::where('action', self::ACTION_WARNING)
            ->where('auditable_type', Group::class)
            ->where('auditable_id', $group->id)
            ->where('created_at', '>=', $group->closed_at)
            ->exists();
    }

    private function mailWarning(Group $group): bool
    {
        $teacher = $group->teacher;

        if ($teacher === null) {
            return false;
        }

        $date = $this->deletionDateFor($group)?->format('d/m/Y');
        $body = "Hola, {$teacher->first_name}:\n\n"
            ."El grupo «{$group->name}» está cerrado desde el "
            .Carbon::parse($group->closed_at)->format('d/m/Y')
            ." y se eliminará automáticamente el {$date}.\n\n"
            ."Antes de esa fecha puedes exportar sus calificaciones o reabrir el grupo para conservarlo:\n"
            .route('groups.show', $group)."\n\n"
            ."Al eliminarse se borrarán las incorporaciones de estudiantes, los periodos, las calificaciones y los intentos de ese grupo. "
            ."Tus exámenes y tu banco de preguntas se conservan.\n";

        try {
            Mail::raw($body, static function ($message) use ($teacher, $group): void {
                $message->to($teacher->email)->subject("Aviso: el grupo «{$group->name}» se eliminará en 30 días");
            });
        } catch (Throwable $e) {
            Log::warning('No fue posible enviar el aviso de eliminación de grupo.', [
                'group_id' => $group->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
