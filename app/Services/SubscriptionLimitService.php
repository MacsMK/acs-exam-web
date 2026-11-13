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
use App\Models\Exam;
use App\Models\Group;
use App\Models\Question;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;

class SubscriptionLimitService
{
    // Cotas Oficiales para la Prueba Gratuita Freemium (RF-SUS-008, RF-SUS-010)
    public const FREEMIUM_MAX_CUMULATIVE_GROUPS = 1;
    public const FREEMIUM_MAX_CUMULATIVE_EXAMS  = 1;
    public const FREEMIUM_MAX_QUESTIONS         = 50;
    public const FREEMIUM_MAX_STUDENTS_PER_GROUP = 40;

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
            $cumulativeGroups = Group::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)
                ->count();

            if ($cumulativeGroups >= self::FREEMIUM_MAX_CUMULATIVE_GROUPS) {
                throw new SubscriptionLimitExceededException(
                    sprintf(
                        'El beneficio de prueba gratuita permite crear un máximo histórico de %d grupo (RF-SUS-010). Eliminar o cerrar el grupo no restablece el beneficio. Contrata un plan para continuar.',
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
            $cumulativeExams = Exam::withoutGlobalScopes()
                ->where('teacher_id', $teacher->id)
                ->count();

            if ($cumulativeExams >= self::FREEMIUM_MAX_CUMULATIVE_EXAMS) {
                throw new SubscriptionLimitExceededException(
                    sprintf(
                        'El beneficio de prueba gratuita permite crear un máximo histórico de %d examen (RF-SUS-010). Contrata un paquete docente para diseñar más evaluaciones.',
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
