<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SubscriptionLimitService.php
 * Ubicación:      app/Services/SubscriptionLimitService.php
 * Requerimiento:  RF-SUS-001..015, RF-SUS-010, RW-01..04, RNF-SEG-003, RNF-SEG-004
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.1.0
 * Descripción:    Servicio de dominio encargado de auditar y hacer cumplir
 *                 las cotas de suscripción SaaS, bloqueo pesimista contra
 *                 concurrencia TOCTOU [ALTA-03] y la regla freemium.
 * ============================================================================
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\AiCreditUsage;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Question;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use Carbon\CarbonInterface;

class SubscriptionLimitService
{
    // Cotas Oficiales para la Prueba Gratuita Freemium (RF-SUS-008, RF-SUS-010, ADR-059)
    public const FREEMIUM_MAX_CUMULATIVE_GROUPS = 1;
    public const FREEMIUM_MAX_CUMULATIVE_EXAMS  = 1;
    public const FREEMIUM_MAX_QUESTIONS         = 50;
    public const FREEMIUM_MAX_STUDENTS_PER_GROUP = 40;
    public const FREEMIUM_MAX_AI_EVALUATIONS    = 25;

    public const FREEMIUM_LIMITS = [
        'max_groups' => self::FREEMIUM_MAX_CUMULATIVE_GROUPS,
        'max_exams' => self::FREEMIUM_MAX_CUMULATIVE_EXAMS,
        'max_questions' => self::FREEMIUM_MAX_QUESTIONS,
        'max_students' => self::FREEMIUM_MAX_STUDENTS_PER_GROUP,
        'max_ai_evaluations' => self::FREEMIUM_MAX_AI_EVALUATIONS,
    ];

    /**
     * Comprueba el límite de grupos activos antes de reabrir un grupo cerrado
     * (RF-GRU-014, RF-GRU-020, ACT-WEB-051). Los grupos cerrados no cuentan
     * como activos, por lo que reabrir uno vuelve a ocupar un lugar del límite.
     * En la prueba gratuita el límite es el mismo grupo único (RF-SUS-008).
     *
     * @param User $teacher Docente propietario del grupo.
     * @return void
     *
     * @throws SubscriptionLimitExceededException Si reabrir excede el límite.
     */
    public function ensureCanReopenGroup(User $teacher): void
    {
        $package = $this->resolveActivePackage($teacher);
        $maxGroups = $package === null ? self::FREEMIUM_MAX_CUMULATIVE_GROUPS : $package->max_groups;

        if ($maxGroups === null) {
            return; // Ilimitado
        }

        $activeGroups = Group::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->where('is_closed', false)
            ->count();

        if ($activeGroups >= $maxGroups) {
            throw new SubscriptionLimitExceededException(
                sprintf(
                    'No se puede reabrir el grupo: ya tienes %d de %d grupos activos permitidos. Cierra otro grupo o sube de nivel para reabrirlo.',
                    $activeGroups,
                    $maxGroups
                ),
                'groups',
                $activeGroups,
                (int) $maxGroups,
                $package?->tier ?? 'freemium'
            );
        }
    }

    /**
     * Valida si el docente tiene cupo para crear un nuevo grupo escolar.
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanCreateGroup(User $teacher): void
    {
        $package = $this->resolveActivePackage($teacher);

        // Caso 1: Nivel Freemium (RF-SUS-010: Conteo Histórico Acumulado e Irreversible)
        if ($package === null) {
            $cumulativeGroups = $this->countHistoricalTrialGroups($teacher);

            if ($cumulativeGroups >= self::FREEMIUM_MAX_CUMULATIVE_GROUPS) {
                throw new SubscriptionLimitExceededException(
                    sprintf(
                        'El beneficio de prueba gratuita permite crear un máximo histórico de %d grupo. Eliminar o cerrar el grupo no restablece el beneficio. Contrata un plan para continuar.',
                        self::FREEMIUM_MAX_CUMULATIVE_GROUPS
                    ),
                    'groups',
                    $cumulativeGroups,
                    self::FREEMIUM_MAX_CUMULATIVE_GROUPS,
                    'freemium'
                );
            }

            return;
        }

        // Caso 2: Plan de Pago (Basic / Advanced / Pro)
        if ($package->max_groups === null) {
            return; // Ilimitado (Nivel Institucional / Pro)
        }

        $activeGroups = Group::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->where('is_closed', false)
            ->count();

        if ($activeGroups >= $package->max_groups) {
            throw new SubscriptionLimitExceededException(
                sprintf(
                    'Has alcanzado el límite de %d grupos activos de tu paquete %s. Cierra grupos anteriores o sube de nivel para crear más.',
                    $package->max_groups,
                    $package->name
                ),
                'groups',
                $activeGroups,
                $package->max_groups,
                $package->tier
            );
        }
    }

    public function countHistoricalTrialGroups(User $teacher): int
    {
        $count = Group::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->count();

        return $teacher->trial_group_used_at !== null || $teacher->legacy_group_record
            ? max(1, $count)
            : $count;
    }

    /**
     * Valida si el docente puede dar de alta N reactivos en su banco de preguntas.
     * Si ya existe una transacción abierta, aplica bloqueo pesimista [ALTA-03].
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanCreateQuestions(User $teacher, int $quantity = 1): void
    {
        if ($quantity <= 0) {
            return;
        }

        if (DB::transactionLevel() > 0) {
            $this->ensureCanCreateQuestionsLocked($teacher, $quantity);

            return;
        }

        $this->evaluateQuestionQuota($teacher, $quantity);
    }

    /**
     * Valida la cuota de reactivos adquiriendo bloqueo pesimista FOR UPDATE sobre
     * el registro del docente para prevenir condiciones de carrera TOCTOU [ALTA-03].
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanCreateQuestionsLocked(User $teacher, int $quantity = 1): void
    {
        if ($quantity <= 0) {
            return;
        }

        $lockedTeacher = User::query()
            ->with('subscription.subscriptionPackage')
            ->whereKey($teacher->id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->evaluateQuestionQuota($lockedTeacher, $quantity);
    }

    /**
     * Consulta la capacidad del paquete y la disponibilidad de una política de cierre.
     *
     * @param User $teacher Cuenta docente titular.
     * @return bool Disponibilidad de la modalidad libre para el docente.
     */
    public function allowsFreeNavigation(User $teacher): bool
    {
        $tiers = config('exams.free_navigation_tiers', []);
        if ($tiers === [] || ! in_array(config('exams.free_submission_grace_seconds'), [0, 60], true)) {
            return false;
        }
        try {
            $tier = $this->resolveActivePackage($teacher)?->tier ?? 'freemium';
        } catch (SubscriptionLimitExceededException) {
            return false;
        }

        return in_array($tier, $tiers, true);
    }

    /**
     * Retorna el resumen de cuota de reactivos para KPIs del banco e importación masiva.
     *
     * @return array{used: int, limit: ?int, remaining: ?int, is_unlimited: bool, tier: string, package_name: string}
     */
    public function getQuestionQuotaSummary(User $teacher): array
    {
        try {
            $package = $this->resolveActivePackage($teacher);
        } catch (SubscriptionLimitExceededException) {
            $used = Question::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)
                ->count();

            return [
                'used' => $used,
                'limit' => 0,
                'remaining' => 0,
                'is_unlimited' => false,
                'tier' => 'suspended',
                'package_name' => 'Suscripción Suspendida',
            ];
        }

        if ($package === null) {
            $used = Question::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)
                ->count();

            return [
                'used' => $used,
                'limit' => self::FREEMIUM_MAX_QUESTIONS,
                'remaining' => max(0, self::FREEMIUM_MAX_QUESTIONS - $used),
                'is_unlimited' => false,
                'tier' => 'freemium',
                'package_name' => 'Prueba Gratuita (Freemium)',
            ];
        }

        $usedActive = Question::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->whereNull('deleted_at')
            ->count();

        $limit = $package->max_questions;

        return [
            'used' => $usedActive,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $usedActive),
            'is_unlimited' => $limit === null,
            'tier' => (string) $package->tier,
            'package_name' => (string) $package->name,
        ];
    }

    /**
     * Valida si el docente puede crear un borrador de examen.
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanCreateExam(User $teacher): void
    {
        $package = $this->resolveActivePackage($teacher);

        // Caso Freemium: Conteo histórico acumulado (RF-SUS-010)
        if ($package === null) {
            $cumulativeExams = $this->countHistoricalTrialExams($teacher);

            if ($cumulativeExams >= self::FREEMIUM_MAX_CUMULATIVE_EXAMS) {
                throw new SubscriptionLimitExceededException(
                    sprintf(
                        'El beneficio de prueba gratuita permite crear un máximo histórico de %d examen. Contrata un paquete docente para diseñar más evaluaciones.',
                        self::FREEMIUM_MAX_CUMULATIVE_EXAMS
                    ),
                    'exams',
                    $cumulativeExams,
                    self::FREEMIUM_MAX_CUMULATIVE_EXAMS,
                    'freemium'
                );
            }
        }
    }

    public function countHistoricalTrialExams(User $teacher): int
    {
        $count = Exam::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->count();

        return $teacher->trial_exam_used_at !== null || $teacher->legacy_exam_record
            ? max(1, $count)
            : $count;
    }

    /**
     * Valida si un examen puede ser publicado sin superar el límite de exámenes activos simultáneos.
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanPublishExam(User $teacher, Exam $exam): void
    {
        if ($exam->is_published) {
            return;
        }

        $package = $this->resolveActivePackage($teacher);

        // En Freemium, no puede haber más de 1 examen publicado simultáneamente
        if ($package === null) {
            $publishedExams = Exam::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)
                ->where('is_published', true)
                ->count();

            if ($publishedExams >= self::FREEMIUM_MAX_CUMULATIVE_EXAMS) {
                throw new SubscriptionLimitExceededException(
                    'No puedes tener más de 1 examen publicado simultáneamente en el plan de prueba gratuito.',
                    'exams',
                    $publishedExams,
                    1,
                    'freemium'
                );
            }

            return;
        }

        // Plan Pro: Ilimitado
        if ($package->max_active_exams === null) {
            return;
        }

        $publishedExams = Exam::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->where('is_published', true)
            ->count();

        if ($publishedExams >= $package->max_active_exams) {
            throw new SubscriptionLimitExceededException(
                sprintf(
                    'Has alcanzado el límite de %d exámenes activos simultáneos de tu paquete %s. Cierra o despublica exámenes inactivos para continuar.',
                    $package->max_active_exams,
                    $package->name
                ),
                'exams',
                $publishedExams,
                $package->max_active_exams,
                $package->tier
            );
        }
    }

    /**
     * Valida si un estudiante puede unirse al grupo sin superar la capacidad del paquete del docente.
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanEnrollStudent(Group $group): void
    {
        $group->loadMissing('teacher');
        $teacher = $group->teacher;
        $package = $this->resolveActivePackage($teacher);
        $currentStudents = $group->students()->count();

        $maxStudents = $package?->max_concurrent_students ?? self::FREEMIUM_MAX_STUDENTS_PER_GROUP;

        if ($currentStudents >= $maxStudents) {
            throw new SubscriptionLimitExceededException(
                sprintf('Este grupo escolar ha alcanzado su capacidad máxima permitida de %d alumnos.', $maxStudents),
                'students',
                $currentStudents,
                $maxStudents,
                $package?->tier ?? 'freemium'
            );
        }
    }

    /**
     * Calcula el resumen vigente de créditos de evaluación con IA para el docente
     * (Freemium histórico acumulado, planes de pago por ciclo vigente o ilimitado Pro).
     *
     * @param User $teacher Cuenta docente titular.
     * @return array{
     *     used: int,
     *     limit: ?int,
     *     remaining: ?int,
     *     is_unlimited: bool,
     *     tier: string,
     *     package_name: string,
     *     percent: int,
     *     variant: string,
     *     is_suspended: bool,
     *     cycle_label: string,
     *     cycle_starts_at: ?CarbonInterface,
     *     cycle_ends_at: ?CarbonInterface,
     *     next_reset_at: ?CarbonInterface
     * }
     */
    public function getAiCreditQuotaSummary(User $teacher): array
    {
        $consumedCredits = AiCreditUsage::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', AiCreditUsage::STATUS_CONSUMED);

        try {
            $package = $this->resolveActivePackage($teacher);
        } catch (SubscriptionLimitExceededException) {
            return [
                'used' => (int) $consumedCredits->sum('credits_consumed'),
                'limit' => 0,
                'remaining' => 0,
                'is_unlimited' => false,
                'tier' => 'suspended',
                'package_name' => 'Suscripción Suspendida',
                'percent' => 100,
                'variant' => 'danger',
                'is_suspended' => true,
                'cycle_label' => 'Suscripción suspendida',
                'cycle_starts_at' => null,
                'cycle_ends_at' => null,
                'next_reset_at' => null,
            ];
        }

        if ($package === null) {
            $used = (int) $consumedCredits->sum('credits_consumed');

            $limit = self::FREEMIUM_MAX_AI_EVALUATIONS;
            $remaining = max(0, $limit - $used);
            $percent = min(100, (int) round(($used / max(1, $limit)) * 100));
            $variant = $percent >= 75 ? 'warning' : 'tech';

            return [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'is_unlimited' => false,
                'tier' => 'freemium',
                'package_name' => 'Prueba Gratuita (Freemium)',
                'percent' => $percent,
                'variant' => $variant,
                'is_suspended' => false,
                'cycle_label' => 'Histórico acumulado de la prueba gratuita',
                'cycle_starts_at' => null,
                'cycle_ends_at' => null,
                'next_reset_at' => null,
            ];
        }

        $subscription = $teacher->subscription;
        $now = now();
        $anchor = $subscription?->starts_at ?? $now->copy()->startOfMonth();
        $limit = $package->ai_requests_limit;

        if ($limit === null) {
            return [
                'used' => (int) $consumedCredits->where('created_at', '>=', $anchor)->sum('credits_consumed'),
                'limit' => null,
                'remaining' => null,
                'is_unlimited' => true,
                'tier' => (string) $package->tier,
                'package_name' => (string) $package->name,
                'percent' => 0,
                'variant' => 'accent',
                'is_suspended' => false,
                'cycle_label' => 'Consumo registrado desde '.$anchor->format('d/m/Y'),
                'cycle_starts_at' => null,
                'cycle_ends_at' => null,
                'next_reset_at' => null,
            ];
        }

        $elapsedMonths = max(0, ($now->year - $anchor->year) * 12 + $now->month - $anchor->month);
        $cycleStart = $anchor->copy()->addMonthsNoOverflow($elapsedMonths);

        // Cada límite se calcula desde el día de activación, incluso tras un mes más corto.
        if ($elapsedMonths > 0 && $now->lt($cycleStart)) {
            $elapsedMonths--;
            $cycleStart = $anchor->copy()->addMonthsNoOverflow($elapsedMonths);
        }

        $cycleEnd = $anchor->copy()->addMonthsNoOverflow($elapsedMonths + 1);
        $used = (int) $consumedCredits
            ->where('created_at', '>=', $cycleStart)
            ->where('created_at', '<', $cycleEnd)
            ->sum('credits_consumed');

        $hasNextCycle = $subscription?->expires_at === null
            || $subscription->expires_at->gt($cycleEnd)
            || ($subscription->renewsAutomatically() && ! $subscription->isInRenewalGracePeriod());
        $remaining = max(0, $limit - $used);
        $percent = min(100, (int) round(($used / max(1, $limit)) * 100));
        $variant = $percent >= 75 ? 'warning' : 'tech';

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'is_unlimited' => false,
            'tier' => (string) $package->tier,
            'package_name' => (string) $package->name,
            'percent' => $percent,
            'variant' => $variant,
            'is_suspended' => false,
            'cycle_label' => 'Ciclo mensual vigente desde '.$cycleStart->format('d/m/Y'),
            'cycle_starts_at' => $cycleStart,
            'cycle_ends_at' => $cycleEnd,
            'next_reset_at' => $hasNextCycle ? $cycleEnd : null,
        ];
    }

    /**
     * Valida si el docente dispone de créditos suficientes para ejecutar N
     * evaluaciones con IA, aplicando bloqueo pesimista si hay transacción abierta.
     *
     * @param User $teacher Cuenta docente solicitante.
     * @param int $creditsNeeded Cantidad de créditos requeridos.
     * @return void
     *
     * @throws SubscriptionLimitExceededException
     */
    public function ensureCanConsumeAiCredits(User $teacher, int $creditsNeeded = 1): void
    {
        if ($creditsNeeded <= 0) {
            return;
        }

        $subjectTeacher = $teacher;

        if (DB::transactionLevel() > 0) {
            $subjectTeacher = User::query()
                ->with('subscription.subscriptionPackage')
                ->whereKey($teacher->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $summary = $this->getAiCreditQuotaSummary($subjectTeacher);

        if ($summary['is_suspended']) {
            throw new SubscriptionLimitExceededException(
                'Tu suscripción docente se encuentra temporalmente suspendida por falta de pago.',
                'ai_credits',
                $summary['used'],
                0,
                'suspended'
            );
        }

        if (! $summary['is_unlimited'] && ($summary['remaining'] ?? 0) < $creditsNeeded) {
            $message = $summary['tier'] === 'freemium'
                ? sprintf(
                    'Has agotado tu cupo de %d evaluaciones con IA de la Prueba Gratuita (disponibles: %d, solicitadas: %d). Contrata un plan para continuar.',
                    $summary['limit'] ?? self::FREEMIUM_MAX_AI_EVALUATIONS,
                    $summary['remaining'] ?? 0,
                    $creditsNeeded
                )
                : sprintf(
                    'No cuentas con créditos de IA suficientes en tu paquete %s (disponibles: %d de %d, solicitados: %d). %s',
                    $summary['package_name'],
                    $summary['remaining'] ?? 0,
                    $summary['limit'] ?? 0,
                    $creditsNeeded,
                    $summary['next_reset_at'] === null
                        ? 'Consulta las opciones de tu plan en Suscripción.'
                        : 'El próximo ciclo está previsto para el '.$summary['next_reset_at']->format('d/m/Y').'.'
                );

            throw new SubscriptionLimitExceededException(
                $message,
                'ai_credits',
                $summary['used'],
                $summary['limit'],
                $summary['tier']
            );
        }
    }

    /**
     * Resuelve el paquete de suscripción vigente y pagado del docente.
     */
    public function resolveActivePackage(User $teacher): ?SubscriptionPackage
    {
        $teacher->loadMissing('subscription.subscriptionPackage');
        $subscription = $teacher->subscription;

        if (! $subscription instanceof TeacherSubscription) {
            return null; // Freemium
        }

        if ($subscription->payment_status === 'suspended') {
            throw new SubscriptionLimitExceededException(
                'Tu suscripción docente se encuentra temporalmente suspendida por falta de pago.',
                'payment',
                0,
                0,
                'suspended'
            );
        }

        // Cobro de renovación rechazado: el plan sigue vigente durante los reintentos (RF-SUS-005)
        if ($subscription->isInRenewalGracePeriod()) {
            return $subscription->subscriptionPackage;
        }

        if ($subscription->payment_status !== 'paid') {
            return null;
        }

        if ($subscription->expires_at !== null && $subscription->expires_at->isPast()) {
            return null; // Vencida -> degrada a freemium
        }

        return $subscription->subscriptionPackage;
    }

    /**
     * Evalúa el consumo histórico (Freemium) o activo (Plan de Pago) contra la cota vigente.
     *
     * @throws SubscriptionLimitExceededException
     */
    private function evaluateQuestionQuota(User $teacher, int $quantity): void
    {
        $package = $this->resolveActivePackage($teacher);

        // Caso 1: Nivel Freemium (conteo histórico acumulado para evitar reinicio borrando y recreando)
        if ($package === null) {
            $currentQuestions = Question::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)
                ->count();

            if (($currentQuestions + $quantity) > self::FREEMIUM_MAX_QUESTIONS) {
                throw new SubscriptionLimitExceededException(
                    sprintf(
                        'No es posible registrar %d reactivo(s). Tu cuenta gratuita tiene registrados %d/%d reactivos permitidos.',
                        $quantity,
                        $currentQuestions,
                        self::FREEMIUM_MAX_QUESTIONS
                    ),
                    'questions',
                    $currentQuestions,
                    self::FREEMIUM_MAX_QUESTIONS,
                    'freemium'
                );
            }

            return;
        }

        // Caso 2: Plan de Pago (solo cuenta reactivos activos en el banco)
        if ($package->max_questions === null) {
            return; // Ilimitado
        }

        $activeQuestions = Question::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->whereNull('deleted_at')
            ->count();

        if (($activeQuestions + $quantity) > $package->max_questions) {
            throw new SubscriptionLimitExceededException(
                sprintf(
                    'Tu paquete %s permite un máximo de %d reactivos activos en el banco. Uso actual: %d. Excederías el límite por %d reactivo(s).',
                    $package->name,
                    $package->max_questions,
                    $activeQuestions,
                    ($activeQuestions + $quantity) - $package->max_questions
                ),
                'questions',
                $activeQuestions,
                $package->max_questions,
                $package->tier
            );
        }
    }
}
