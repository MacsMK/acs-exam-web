{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: admin/teachers/index.blade.php (MPW-V3 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, §8.7, Tabla 10) --}}
@extends('layouts.app')

@section('title', 'Administración de Docentes y Paquetes')

@section('content')
<div
    class="space-y-6"
    x-data="acodeAdminTeachersPanel()"
    @open-teacher-review.window="openReview($event.detail)"
    @keydown.escape.window="if (reviewModalOpen) closeReview()"
>
    {{-- Encabezado principal --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">Superadministración de Cuentas Docentes</h1>
            <p class="text-sm text-slate-500 mt-1">
                Panel central para dictaminar acreditaciones docentes, verificar pagos, asignar paquetes SaaS y administrar el acceso de cuentas.
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <x-button href="{{ route('admin.invitations.index') }}" variant="secondary" size="sm">
                <x-icon name="key" class="w-4 h-4" />
                Abrir códigos de invitación
            </x-button>
        </div>
    </div>

    {{-- Barra de pestañas de filtro rápido por acreditación docente --}}
    <div class="flex flex-wrap items-center gap-2" role="navigation" aria-label="Filtro rápido por acreditación docente">
        <a
            href="{{ route('admin.teachers.index', array_filter([
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
            ])) }}"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition border {{ empty($filters['verification'])
                ? 'bg-acode-primary text-white border-acode-primary shadow-sm'
                : 'bg-white text-slate-600 border-slate-200 hover:border-acode-tech hover:text-acode-primary' }}"
        >
            <span>Todos</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ empty($filters['verification']) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">
                {{ $verificationCounts['all'] ?? $teachers->total() }}
            </span>
        </a>

        <a
            href="{{ route('admin.teachers.index', array_filter([
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'verification' => 'pending',
            ])) }}"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition border {{ ($filters['verification'] ?? '') === 'pending'
                ? 'bg-acode-primary text-white border-acode-primary shadow-sm'
                : 'bg-white text-slate-600 border-slate-200 hover:border-acode-warning hover:text-acode-primary' }}"
        >
            <span>Pendientes</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ ($pendingVerificationsCount ?? ($verificationCounts['pending'] ?? 0)) > 0
                ? 'bg-acode-warning text-acode-primary'
                : (($filters['verification'] ?? '') === 'pending' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700') }}">
                {{ $pendingVerificationsCount ?? ($verificationCounts['pending'] ?? 0) }}
            </span>
        </a>

        <a
            href="{{ route('admin.teachers.index', array_filter([
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'verification' => 'approved',
            ])) }}"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition border {{ ($filters['verification'] ?? '') === 'approved'
                ? 'bg-acode-primary text-white border-acode-primary shadow-sm'
                : 'bg-white text-slate-600 border-slate-200 hover:border-acode-accent hover:text-acode-primary' }}"
        >
            <span>Verificados</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ ($filters['verification'] ?? '') === 'approved' ? 'bg-white/20 text-white' : 'bg-acode-accent/15 text-acode-primary' }}">
                {{ $verificationCounts['approved'] ?? 0 }}
            </span>
        </a>

        <a
            href="{{ route('admin.teachers.index', array_filter([
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'verification' => 'rejected',
            ])) }}"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition border {{ ($filters['verification'] ?? '') === 'rejected'
                ? 'bg-acode-primary text-white border-acode-primary shadow-sm'
                : 'bg-white text-slate-600 border-slate-200 hover:border-acode-danger hover:text-acode-primary' }}"
        >
            <span>Rechazados</span>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ ($filters['verification'] ?? '') === 'rejected' ? 'bg-white/20 text-white' : 'bg-acode-danger/10 text-acode-danger' }}">
                {{ $verificationCounts['rejected'] ?? 0 }}
            </span>
        </a>
    </div>

    {{-- Filtros de búsqueda --}}
    <x-search-filter
        :action="route('admin.teachers.index')"
        search-label="Buscar docentes"
        :search-value="$filters['search'] ?? ''"
        placeholder="Buscar por nombre, correo o institución…"
        :clear-url="route('admin.teachers.index')"
        :has-active-filters="! empty($filters['search']) || ! empty($filters['status']) || ! empty($filters['verification'])"
    >
        <x-slot:parameters>
            <input type="hidden" name="verification" value="{{ $filters['verification'] ?? '' }}">
        </x-slot:parameters>

        <x-slot:filters>
            <x-form.group name="status" label="Estado" class="sm:w-48">
                <x-form.select name="status" size="sm">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Solo Activos</option>
                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Solo Suspendidos</option>
                </x-form.select>
            </x-form.group>
        </x-slot:filters>
    </x-search-filter>

    {{-- Tabla de Docentes --}}
    <x-card padding="p-0" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-acode-primary text-white text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-4">Docente</th>
                        <th class="px-5 py-4">Contacto</th>
                        <th class="px-5 py-4">Acreditación</th>
                        <th class="px-5 py-4">Estado Cuenta</th>
                        <th class="px-5 py-4">Suscripción Actual</th>
                        <th class="px-5 py-4 text-right">Acciones de Gestión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($teachers as $teacher)
                        <tr class="hover:bg-acode-surface/40 transition">
                            {{-- Docente e institución --}}
                            <td class="px-5 py-4 align-top">
                                <div class="font-bold text-acode-primary">{{ $teacher->full_name }}</div>
                                <div class="text-xs text-slate-600 mt-0.5">
                                    {{ $teacher->teacherVerification?->institution_name ?: 'Sin institución registrada' }}
                                </div>
                                @if ($teacher->teacherVerification?->professional_license)
                                    <div class="text-[11px] font-mono text-acode-tech mt-0.5">
                                        Cédula SEP: {{ $teacher->teacherVerification->professional_license }}
                                    </div>
                                @endif
                                <div class="text-[11px] text-slate-400 mt-1">
                                    ID: {{ $teacher->id }} · Exámenes: {{ $teacher->exams_created_count }}
                                </div>
                            </td>

                            {{-- Contacto --}}
                            <td class="px-5 py-4 align-top">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-xs text-slate-700 font-medium break-all">{{ $teacher->email }}</span>
                                    @if ($teacher->teacherVerification?->is_institutional_email)
                                        <x-badge variant="info">Institucional</x-badge>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-1">{{ $teacher->phone ?? 'Sin teléfono' }}</div>
                            </td>

                            {{-- Acreditación docente --}}
                            <td class="px-5 py-4 align-top">
                                <div class="space-y-1">
                                    @if ($teacher->isPendingTeacherVerification())
                                        <x-badge variant="warning">Pendiente</x-badge>
                                    @elseif ($teacher->isRejectedTeacherVerification())
                                        <x-badge variant="danger">Rechazado</x-badge>
                                    @else
                                        <x-badge variant="success">Verificado</x-badge>
                                    @endif

                                    @if ($teacher->teacherVerification)
                                        <div class="text-[11px] text-slate-500">
                                            {{ $teacher->teacherVerification->verificationMethodLabel() }}
                                        </div>
                                        @if ($teacher->teacherVerification->invitationCode)
                                            <div class="text-[11px] font-mono text-acode-tech">
                                                {{ $teacher->teacherVerification->invitationCode->code }}
                                            </div>
                                        @endif
                                        @if ($teacher->teacherVerification->document_purged_at)
                                            <div class="text-[11px] text-slate-400">
                                                Comprobante purgado
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </td>

                            {{-- Estado de cuenta --}}
                            <td class="px-5 py-4 align-top">
                                @if ($teacher->is_active)
                                    <x-badge variant="success">Activo</x-badge>
                                @else
                                    <x-badge variant="danger">Suspendido</x-badge>
                                @endif
                            </td>

                            {{-- Suscripción actual --}}
                            <td class="px-5 py-4 align-top">
                                <a class="block text-acode-tech underline" href="{{ route('admin.operations.teacher', $teacher) }}">Consultar operaciones</a>
                                @if ($teacher->teacherSubscriptions->first()?->package)
                                    <div class="text-xs font-bold text-acode-primary">
                                        {{ $teacher->teacherSubscriptions->first()->package->name }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        Pago:
                                        <span class="{{ match ($teacher->teacherSubscriptions->first()->payment_status) {
                                            'paid' => 'text-acode-accent-dark font-semibold',
                                            'pending' => 'text-acode-warning-dark font-semibold',
                                            'overdue', 'suspended' => 'text-acode-danger font-semibold',
                                            default => 'text-slate-600 font-semibold',
                                        } }}">
                                            {{ match ($teacher->teacherSubscriptions->first()->payment_status) {
                                                'paid' => 'Pagado',
                                                'pending' => 'Pendiente',
                                                'overdue' => 'Vencido',
                                                'suspended' => 'Suspendido',
                                                default => ucfirst((string) $teacher->teacherSubscriptions->first()->payment_status),
                                            } }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400 italic">Prueba Gratuita</span>
                                @endif

                                @if ($teacher->latestPaymentTransaction)
                                    <div class="mt-1 text-[11px] {{ $teacher->latestPaymentTransaction->status === 'failed' ? 'text-acode-danger' : 'text-slate-500' }}">
                                        <span class="font-mono font-medium">{{ $teacher->latestPaymentTransaction->reference_code }}</span>
                                        · {{ $teacher->latestPaymentTransaction->maskedPaymentSummary() }}
                                    </div>
                                @endif
                            </td>

                            {{-- Acciones de Gestión --}}
                            <td class="px-5 py-4 text-right align-top">
                                <div class="relative flex flex-wrap items-center justify-end gap-2" x-data="{ openAssign: false }">
                                    @if ($teacher->isPendingTeacherVerification() || ($teacher->isRejectedTeacherVerification() && $teacher->teacherVerification?->rejection_reason))
                                        <x-button
                                            :variant="$teacher->isPendingTeacherVerification() ? 'primary' : 'neutral'"
                                            size="sm"
                                            type="button"
                                            data-review="{{ json_encode([
                                                'id' => $teacher->id,
                                                'fullName' => $teacher->full_name,
                                                'email' => $teacher->email,
                                                'phone' => $teacher->phone ?? 'Sin teléfono',
                                                'status' => $teacher->teacher_verification_status ?? ($teacher->isPendingTeacherVerification() ? 'pending' : 'rejected'),
                                                'isInstitutionalEmail' => (bool) ($teacher->teacherVerification?->is_institutional_email ?? false),
                                                'institutionName' => $teacher->teacherVerification?->institution_name ?: 'Sin institución registrada',
                                                'educationalLevelLabel' => $teacher->teacherVerification?->educationalLevelLabel() ?? 'No especificado',
                                                'credentialTypeLabel' => $teacher->teacherVerification?->credentialTypeLabel() ?? 'No especificado',
                                                'verificationMethodLabel' => $teacher->teacherVerification?->verificationMethodLabel() ?? 'Revisión documental',
                                                'professionalLicense' => $teacher->teacherVerification?->professional_license ?? '',
                                                'submittedAt' => $teacher->teacherVerification?->submitted_at?->format('d/m/Y H:i') ?? ($teacher->isPendingTeacherVerification() ? 'Reciente' : ''),
                                                'reviewedAt' => $teacher->teacherVerification?->reviewed_at?->format('d/m/Y H:i') ?? '',
                                                'reviewerName' => $teacher->teacherVerification?->reviewer?->full_name ?? '',
                                                'resubmissionCount' => (int) ($teacher->teacherVerification?->resubmission_count ?? 0),
                                                'rejectionReason' => $teacher->teacherVerification?->rejection_reason ?? '',
                                                'hasDocument' => (bool) ($teacher->teacherVerification?->hasDocument() ?? false),
                                                'documentMimeType' => $teacher->teacherVerification?->document_mime_type ?? '',
                                                'documentOriginalName' => $teacher->teacherVerification?->document_original_name ?? '',
                                                'documentPurgedAt' => $teacher->teacherVerification?->document_purged_at?->format('d/m/Y H:i') ?? '',
                                                'documentUrl' => route('admin.teachers.verification.document', $teacher),
                                                'approveUrl' => route('admin.teachers.verification.approve', $teacher),
                                                'rejectUrl' => route('admin.teachers.verification.reject', $teacher),
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}"
                                            @click="openReviewFromElement($el)"
                                        >
                                            @if ($teacher->isPendingTeacherVerification())
                                                <x-icon name="view" class="w-3.5 h-3.5" />
                                                Abrir revisión
                                            @else
                                                Abrir detalle
                                            @endif
                                        </x-button>
                                    @endif

                                    {{-- Desplegable inline para asignar paquete --}}
                                    <x-button variant="secondary" size="sm" type="button" @click="openAssign = !openAssign">
                                        Asignar plan
                                    </x-button>

                                    {{-- Botón de suspender o reactivar cuenta con confirmación SweetAlert2 --}}
                                    <form
                                        method="POST"
                                        action="{{ route('admin.teachers.toggle', $teacher) }}"
                                        data-teacher-name="{{ $teacher->full_name }}"
                                        data-is-active="{{ $teacher->is_active ? '1' : '0' }}"
                                        @submit.prevent="confirmToggleAccount($el)"
                                    >
                                        @csrf
                                        <x-button :variant="$teacher->is_active ? 'danger' : 'accent'" size="sm" type="submit">
                                            {{ $teacher->is_active ? 'Suspender' : 'Reactivar' }}
                                        </x-button>
                                    </form>

                                    {{-- Mini formulario de asignación de plan --}}
                                    <div
                                        x-show="openAssign"
                                        x-cloak
                                        x-transition.opacity.duration.150ms
                                        @click.outside="openAssign = false"
                                        class="absolute right-0 top-full z-30 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-xl"
                                    >
                                        <h4 class="text-xs font-bold text-acode-primary mb-2">Asignar Paquete a {{ $teacher->first_name }}</h4>
                                        <form method="POST" action="{{ route('admin.teachers.assign', $teacher) }}" class="space-y-3">
                                            @csrf
                                            <x-form.select name="subscription_package_id">
                                                @forelse ($packages as $pkg)
                                                    <option value="{{ $pkg->id }}">{{ $pkg->name }} (${{ $pkg->monthly_price }} MXN)</option>
                                                @empty
                                                    <option value="" disabled>Sin paquetes disponibles</option>
                                                @endforelse
                                            </x-form.select>

                                            <x-form.select name="payment_status">
                                                <option value="paid">Pagado (Acreditado / Vigente)</option>
                                                <option value="pending">Pendiente de Acreditación</option>
                                                <option value="overdue">Vencido</option>
                                                <option value="suspended">Suspendido por Falta de Pago</option>
                                            </x-form.select>

                                            <div class="flex justify-end gap-2 pt-2">
                                                <x-button variant="neutral" size="sm" type="button" @click="openAssign = false">Cancelar</x-button>
                                                <x-button variant="primary" size="sm" type="submit">Guardar</x-button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                                No se encontraron cuentas docentes con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($teachers->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $teachers->links() }}
            </div>
        @endif
    </x-card>

    {{-- Modal único de Revisión Docente (Alpine.js, transición suave ADR-047) --}}
    <div
        x-show="reviewModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="teacher-review-modal-title"
    >
        {{-- Backdrop desenfocado --}}
        <div
            x-show="reviewModalOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            @click="closeReview()"
            aria-hidden="true"
        ></div>

        {{-- Contenedor principal del modal --}}
        <div
            x-show="reviewModalOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative z-10 w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
            {{-- Encabezado --}}
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h3 id="teacher-review-modal-title" class="text-base font-bold text-acode-primary tracking-tight">
                        Revisión de Identidad Docente
                    </h3>
                    <template x-if="activeTeacher && activeTeacher.status === 'pending'">
                        <x-badge variant="warning">Pendiente</x-badge>
                    </template>
                    <template x-if="activeTeacher && activeTeacher.status === 'rejected'">
                        <x-badge variant="danger">Rechazado</x-badge>
                    </template>
                </div>

                <x-button
                    type="button"
                    variant="neutral"
                    size="sm"
                    :icon-only="true"
                    x-on:click="closeReview()"
                    aria-label="Cerrar modal"
                    title="Cerrar modal"
                >
                    <x-icon name="xMark" class="h-5 w-5" />
                </x-button>
            </div>

            {{-- Cuerpo desplazable --}}
            <div class="max-h-[calc(100vh-12rem)] overflow-y-auto px-6 py-5 space-y-5" x-show="activeTeacher">
                {{-- Datos generales del docente --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Docente</p>
                        <p class="mt-0.5 text-sm font-bold text-acode-primary" x-text="activeTeacher?.fullName"></p>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Correo electrónico</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="text-xs font-medium text-slate-800 break-all" x-text="activeTeacher?.email"></span>
                            <template x-if="activeTeacher?.isInstitutionalEmail">
                                <x-badge variant="info">Institucional</x-badge>
                            </template>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Teléfono</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-700" x-text="activeTeacher?.phone"></p>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Institución educativa</p>
                        <p class="mt-0.5 text-sm font-semibold text-acode-primary" x-text="activeTeacher?.institutionName"></p>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nivel educativo</p>
                        <p class="mt-0.5 text-sm text-slate-700" x-text="activeTeacher?.educationalLevelLabel"></p>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tipo de comprobante</p>
                        <p class="mt-0.5 text-sm text-slate-700" x-text="activeTeacher?.credentialTypeLabel"></p>
                    </div>

                    {{-- Cédula profesional SEP --}}
                    <div class="sm:col-span-2 lg:col-span-3 pt-2 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Cédula profesional SEP</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span
                                    class="font-mono text-sm font-bold text-acode-primary"
                                    x-text="activeTeacher?.professionalLicense || 'No proporcionada'"
                                ></span>

                                <template x-if="activeTeacher?.professionalLicense">
                                    <x-button
                                        type="button"
                                        variant="neutral"
                                        size="sm"
                                        x-on:click="copyLicense(activeTeacher.professionalLicense)"
                                        title="Copiar cédula profesional"
                                    >
                                        <x-icon name="copy" class="h-3.5 w-3.5" />
                                        <span>Copiar</span>
                                    </x-button>
                                </template>
                            </div>
                        </div>

                        <template x-if="activeTeacher?.professionalLicense">
                            <a
                                href="https://cedulaprofesional.sep.gob.mx/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-acode-tech/10 px-3 py-1.5 text-xs font-semibold text-acode-tech transition hover:bg-acode-tech/20"
                            >
                                <x-icon name="search" class="h-3.5 w-3.5" />
                                <span>Abrir consulta SEP</span>
                            </a>
                        </template>
                    </div>
                </div>

                {{-- Motivo de rechazo si ya fue dictaminado como rechazado --}}
                <template x-if="activeTeacher && activeTeacher.status === 'rejected' && activeTeacher.rejectionReason">
                    <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs font-bold text-acode-danger">
                            <x-icon name="warning" class="h-4 w-4 shrink-0" />
                            <span>Motivo de rechazo registrado</span>
                        </div>
                        <p class="text-sm text-acode-ink" x-text="activeTeacher.rejectionReason"></p>
                        <p class="text-[11px] text-slate-500" x-show="activeTeacher.reviewedAt || activeTeacher.reviewerName">
                            <span x-show="activeTeacher.reviewerName" x-text="`Dictaminado por ${activeTeacher.reviewerName}`"></span>
                            <span x-show="activeTeacher.reviewedAt" x-text="` · ${activeTeacher.reviewedAt}`"></span>
                        </p>
                    </div>
                </template>

                {{-- Visor inline del comprobante documental --}}
                <div class="space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-acode-primary flex items-center gap-1.5">
                            <x-icon name="document" class="h-4 w-4 text-acode-tech" />
                            <span>Comprobante docente adjunto</span>
                        </h4>

                        <template x-if="activeTeacher?.hasDocument">
                            <a
                                :href="activeTeacher.documentUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-acode-primary transition hover:bg-acode-surface"
                            >
                                <x-icon name="view" class="h-3.5 w-3.5" />
                                <span>Abrir archivo</span>
                            </a>
                        </template>
                    </div>

                    <template x-if="activeTeacher?.hasDocument && isImage(activeTeacher.documentMimeType)">
                        <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-3 flex items-center justify-center">
                            <img
                                :src="activeTeacher.documentUrl"
                                alt="Comprobante docente"
                                class="max-h-96 w-full rounded-lg object-contain bg-white"
                            >
                        </div>
                    </template>

                    <template x-if="activeTeacher?.hasDocument && isPdf(activeTeacher.documentMimeType)">
                        <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-2">
                            <iframe
                                :src="activeTeacher.documentUrl"
                                title="Visor de comprobante docente PDF"
                                class="h-96 w-full rounded-lg border border-slate-200 bg-white"
                            ></iframe>
                        </div>
                    </template>

                    <template x-if="activeTeacher?.hasDocument && !isImage(activeTeacher.documentMimeType) && !isPdf(activeTeacher.documentMimeType)">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-600">
                            El comprobante está disponible en almacenamiento privado. Usa el botón <strong>Abrir archivo</strong> para consultarlo en una nueva pestaña.
                        </div>
                    </template>

                    <template x-if="!activeTeacher?.hasDocument">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500">
                            El comprobante no se encuentra disponible o ya fue eliminado automáticamente tras concluir la revisión (LFPDPPP).
                        </div>
                    </template>
                </div>

                {{-- Acciones de Dictamen (solo solicitudes pendientes) --}}
                <template x-if="activeTeacher && activeTeacher.status === 'pending'">
                    <div class="space-y-5 border-t border-slate-200 pt-5">
                        {{-- Aprobación directa --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-acode-accent/35 bg-acode-accent/10 p-4">
                            <div class="text-xs text-acode-primary space-y-0.5">
                                <p class="font-bold">Aprobar solicitud y habilitar panel docente</p>
                                <p class="text-slate-600">
                                    Al aceptar, el docente recibirá acceso completo y su comprobante será purgado automáticamente (LFPDPPP).
                                </p>
                            </div>

                            <form
                                method="POST"
                                :action="activeTeacher.approveUrl"
                                x-ref="approveForm"
                                @submit.prevent="confirmApprove($el)"
                                class="shrink-0"
                            >
                                @csrf
                                <x-button type="submit" variant="accent" size="md">
                                    <x-icon name="check" class="h-4 w-4" />
                                    Aceptar solicitud
                                </x-button>
                            </form>
                        </div>

                        {{-- Rechazo con motivos rápidos y textarea obligatorio --}}
                        <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/5 p-4 space-y-3">
                            <div>
                                <h4 class="text-xs font-bold text-acode-danger uppercase tracking-wider">Rechazar solicitud</h4>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Selecciona un motivo rápido o redacta una observación clara (mínimo 10 caracteres). El comprobante actual se eliminará automáticamente.
                                </p>
                            </div>

                            {{-- 3 chips de motivos rápidos --}}
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="rejectionReason = 'El comprobante cargado es ilegible o está incompleto'"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-acode-danger hover:text-acode-danger"
                                >
                                    Comprobante ilegible o incompleto
                                </button>
                                <button
                                    type="button"
                                    @click="rejectionReason = 'La credencial enviada corresponde a un estudiante y no a personal docente'"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-acode-danger hover:text-acode-danger"
                                >
                                    Credencial de estudiante
                                </button>
                                <button
                                    type="button"
                                    @click="rejectionReason = 'El nombre o la institución no coinciden con los datos registrados'"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-acode-danger hover:text-acode-danger"
                                >
                                    Datos no coinciden
                                </button>
                            </div>

                            <form
                                method="POST"
                                :action="activeTeacher.rejectUrl"
                                @submit.prevent="confirmReject($el)"
                                class="space-y-3"
                            >
                                @csrf

                                <x-form.group name="rejection_reason" label="Motivo de rechazo" required>
                                    <x-form.textarea
                                        name="rejection_reason"
                                        rows="3"
                                        required
                                        minlength="10"
                                        maxlength="500"
                                        x-model="rejectionReason"
                                        placeholder="Describe claramente el motivo por el cual se rechaza la solicitud..."
                                    />
                                </x-form.group>

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-[11px] text-slate-500">
                                        <span x-text="rejectionReason.length"></span> / 500 caracteres
                                    </span>

                                    <x-button type="submit" variant="danger" size="md">
                                        Enviar rechazo
                                    </x-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Pie del modal --}}
            <div class="flex items-center justify-end border-t border-slate-100 bg-slate-50 px-6 py-3.5">
                <x-button type="button" variant="neutral" size="sm" @click="closeReview()">
                    Cerrar
                </x-button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.acodeAdminTeachersPanel = function () {
        return {
            reviewModalOpen: false,
            activeTeacher: null,
            rejectionReason: '',

            openReviewFromElement(el) {
                try {
                    const raw = el?.dataset?.review;
                    if (!raw) {
                        return;
                    }

                    const parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        this.openReview(parsed);
                    }
                } catch (error) {
                    if (typeof window.AcodeAlert !== 'undefined') {
                        window.AcodeAlert.error(
                            'No se pudo abrir la revisión',
                            'Ocurrió un problema al cargar los datos del expediente docente'
                        );
                    }
                }
            },

            openReview(teacherData) {
                this.activeTeacher = teacherData;
                this.rejectionReason = '';
                this.reviewModalOpen = true;
            },

            closeReview() {
                this.reviewModalOpen = false;
                this.rejectionReason = '';
            },

            isImage(mimeType) {
                const normalized = (mimeType || '').toLowerCase();
                return ['image/jpeg', 'image/jpg', 'image/png'].includes(normalized);
            },

            isPdf(mimeType) {
                return (mimeType || '').toLowerCase() === 'application/pdf';
            },

            copyLicense(license) {
                if (!license || !navigator.clipboard) {
                    return;
                }

                navigator.clipboard.writeText(license).then(() => {
                    if (typeof window.AcodeAlert !== 'undefined') {
                        window.AcodeAlert.toast('Cédula copiada al portapapeles', 'success');
                    }
                });
            },

            confirmToggleAccount(form) {
                const teacherName = form.dataset.teacherName || 'este docente';
                const isActive = form.dataset.isActive === '1';

                if (typeof window.AcodeAlert !== 'undefined') {
                    window.AcodeAlert.confirm({
                        title: isActive ? '¿Suspender cuenta docente?' : '¿Reactivar cuenta docente?',
                        text: isActive
                            ? `La cuenta de ${teacherName} quedará suspendida temporalmente y no podrá iniciar sesión.`
                            : `La cuenta de ${teacherName} recuperará acceso a la plataforma.`,
                        confirmButtonText: 'Aceptar',
                        cancelButtonText: 'Cancelar',
                        isDanger: isActive,
                        onConfirm: () => form.submit(),
                    });
                } else {
                    form.submit();
                }
            },

            confirmApprove(form) {
                const teacherName = this.activeTeacher?.fullName || 'el docente';

                if (typeof window.AcodeAlert !== 'undefined') {
                    window.AcodeAlert.confirm({
                        title: '¿Aceptar acreditación docente?',
                        text: `Se habilitará la cuenta de ${teacherName} con acceso completo al panel docente y el comprobante adjunto se eliminará automáticamente conforme a la LFPDPPP.`,
                        confirmButtonText: 'Aceptar',
                        cancelButtonText: 'Cancelar',
                        isDanger: false,
                        onConfirm: () => form.submit(),
                    });
                } else {
                    form.submit();
                }
            },

            confirmReject(form) {
                const reason = (this.rejectionReason || '').trim();
                if (reason.length < 10) {
                    if (typeof window.AcodeAlert !== 'undefined') {
                        window.AcodeAlert.warning(
                            'Motivo demasiado corto',
                            'El motivo de rechazo debe tener al menos 10 caracteres'
                        );
                    }
                    return;
                }

                const teacherName = this.activeTeacher?.fullName || 'el docente';

                if (typeof window.AcodeAlert !== 'undefined') {
                    window.AcodeAlert.confirm({
                        title: '¿Enviar rechazo de solicitud?',
                        text: `Se notificará a ${teacherName} el motivo indicado y el comprobante cargado se eliminará automáticamente (LFPDPPP). El docente podrá corregir y reenviar un nuevo archivo.`,
                        confirmButtonText: 'Enviar',
                        cancelButtonText: 'Cancelar',
                        isDanger: true,
                        onConfirm: () => form.submit(),
                    });
                } else {
                    form.submit();
                }
            },
        };
    };
</script>
@endpush
