<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AdminOperationsController.php
 * Ubicación:      app/Http/Controllers/AdminOperationsController.php
 * Requerimiento:  RF-ADM-002, RF-ADM-010, RF-ADM-011, RF-ADM-012
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Presenta estados y movimientos operativos de cuentas docentes y estudiantiles.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\AiCreditUsage;
use App\Models\Exam;
use App\Models\Group;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\View\View;

/** Consultas operativas de RF-ADM-002 y RF-ADM-010 a 012. */
class AdminOperationsController extends Controller
{
    /**
     * Pagina las cuentas estudiantiles con sus datos básicos y su estado.
     *
     * @return View Vista que contiene los datos autorizados.
     */
    public function students(): View
    {
        $students = User::where('role', 'student')->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(30, ['id', 'first_name', 'last_name', 'email', 'is_active']);

        return view('admin.operations.students', compact('students'));
    }

    /**
     * Consulta los movimientos y estados administrativos de una cuenta docente.
     *
     * @param User $teacher Titular de la cuenta docente.
     * @param SubscriptionLimitService $limits Servicio de cuotas de suscripción.
     * @return View Vista que contiene los datos autorizados.
     */
    public function teacher(User $teacher, SubscriptionLimitService $limits): View
    {
        abort_unless($teacher->role === 'teacher', 404);

        $groups = Group::withTrashed()->where('teacher_id', $teacher->id)->orderByDesc('id')
            ->paginate(20, ['id', 'is_closed', 'deleted_at'], 'grupos')->withQueryString();
        $exams = Exam::where('teacher_id', $teacher->id)->with('groups:id')
            ->orderByDesc('id')->paginate(20, ['id', 'is_published', 'starts_at', 'ends_at'], 'examenes')->withQueryString();
        $movements = AiCreditUsage::where('teacher_id', $teacher->id)->where('status', AiCreditUsage::STATUS_CONSUMED)
            ->orderByDesc('id')->paginate(20, ['id', 'created_at', 'credits_consumed'], 'movimientos')->withQueryString();
        $renewals = PaymentTransaction::where('teacher_id', $teacher->id)->where('concept', PaymentTransaction::CONCEPT_RENEWAL)
            ->orderByDesc('id')->paginate(20, ['id', 'created_at', 'status'], 'renovaciones')->withQueryString();

        return view('admin.operations.teacher', [
            'teacher' => $teacher, 'groups' => $groups, 'exams' => $exams,
            'movements' => $movements, 'renewals' => $renewals,
            'credits' => $limits->getAiCreditQuotaSummary($teacher),
        ]);
    }
}
