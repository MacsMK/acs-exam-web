<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AdminTeacherController.php
 * Ubicación:      app/Http/Controllers/AdminTeacherController.php
 * Requerimiento:  RW-05, RW-06, RF-ADM-001..018 (Portal Superadministrador)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Panel administrativo para consultar docentes registrados,
 *                 suspender/activar cuentas y asignar paquetes de suscripción.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminTeacherController extends Controller
{
    /**
     * Lista todos los docentes registrados y su estatus de suscripción.
     */
    public function index(Request $request): View
    {
        $query = User::where('role', 'teacher')
            ->withCount(['examsCreated', 'teacherSubscriptions'])
            ->with(['teacherSubscriptions' => function ($q): void {
                $q->latest('id')->with('package');
            }])
            ->latest();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->input('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $teachers = $query->paginate(15);
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('monthly_price')->get();

        return view('admin.teachers.index', [
            'teachers' => $teachers,
            'packages' => $packages,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Alterna la suspensión o activación de la cuenta del docente (RW-05).
     */
    public function toggleStatus(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->role === 'teacher', 400);

        $oldStatus = $teacher->is_active;
        $teacher->is_active = !$oldStatus;
        $teacher->save();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $teacher->is_active ? 'teacher.reactivated' : 'teacher.suspended',
            'auditable_type' => User::class,
            'auditable_id' => $teacher->id,
            'old_values' => ['is_active' => $oldStatus],
            'new_values' => ['is_active' => $teacher->is_active],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        $statusMessage = $teacher->is_active
            ? "La cuenta del docente {$teacher->full_name} ha sido reactivada exitosamente."
            : "La cuenta del docente {$teacher->full_name} ha sido suspendida exitosamente.";

        return back()->with('status', $statusMessage);
    }

    /**
     * Asigna o actualiza manualmente el paquete de suscripción del docente (RW-05).
     */
    public function assignPackage(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->role === 'teacher', 400);

        $validated = $request->validate([
            'subscription_package_id' => ['required', 'exists:acs_subscription_packages,id'],
            'payment_status' => ['required', 'in:paid,pending,overdue,suspended'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $sub = $teacher->teacherSubscriptions()->latest('id')->first();

        if ($sub) {
            $sub->update([
                'subscription_package_id' => (int) $validated['subscription_package_id'],
                'payment_status' => $validated['payment_status'],
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'suspended_at' => $validated['payment_status'] === 'suspended' ? now() : null,
                'notes' => $validated['notes'] ?? 'Asignación administrativa por superadministrador',
            ]);
        } else {
            $sub = TeacherSubscription::create([
                'teacher_id' => $teacher->id,
                'subscription_package_id' => (int) $validated['subscription_package_id'],
                'payment_status' => $validated['payment_status'],
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'suspended_at' => $validated['payment_status'] === 'suspended' ? now() : null,
                'notes' => $validated['notes'] ?? 'Asignación administrativa por superadministrador',
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'teacher.subscription_assigned',
            'auditable_type' => TeacherSubscription::class,
            'auditable_id' => $sub->id,
            'new_values' => [
                'target_teacher_id' => $teacher->id,
                'package_id' => $validated['subscription_package_id'],
                'payment_status' => $validated['payment_status'],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return back()->with('status', "Paquete de suscripción actualizado exitosamente para {$teacher->full_name}.");
    }
}
