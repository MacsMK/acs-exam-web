{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: admin/invitations/index.blade.php (MPW-V2 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, §8.7, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Códigos de Invitación Institucional')

@section('content')
<div class="space-y-6" x-data="acodeAdminInvitationsPanel()">
    {{-- Encabezado institucional --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Códigos de Invitación Institucional</h1>
            <p class="mt-1 text-sm text-slate-500">
                Genera y controla códigos con vigencia y límite de usos para acreditar docentes de instituciones aliadas de manera inmediata.
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <x-button href="{{ route('admin.teachers.index') }}" variant="neutral" size="sm">
                <x-icon name="arrowLeft" class="h-4 w-4" />
                Volver a docentes
            </x-button>
        </div>
    </div>

    {{-- 3 Tarjetas KPI --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card padding="p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total de códigos</p>
                    <p class="mt-1.5 text-3xl font-extrabold text-acode-primary">
                        {{ number_format((int) ($stats['total'] ?? 0)) }}
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-primary/10 text-acode-primary">
                    <x-icon name="key" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <x-card padding="p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Códigos activos</p>
                    <p class="mt-1.5 text-3xl font-extrabold text-acode-accent">
                        {{ number_format((int) ($stats['active'] ?? 0)) }}
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-accent/15 text-acode-accent">
                    <x-icon name="success" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <x-card padding="p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Docentes verificados</p>
                    <p class="mt-1.5 text-3xl font-extrabold text-acode-tech">
                        {{ number_format((int) ($stats['uses_total'] ?? 0)) }}
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-tech/15 text-acode-tech">
                    <x-icon name="userTie" class="h-6 w-6" />
                </div>
            </div>
        </x-card>
    </div>

    {{-- Formulario de emisión de nuevo código --}}
    <x-card>
        <x-slot:title>Generar nuevo código institucional</x-slot:title>
        <x-slot:subtitle>Los docentes que se registren o canjeen un código vigente quedarán verificados al instante tras su 2FA</x-slot:subtitle>

        <form method="POST" action="{{ route('admin.invitations.store') }}" id="create-invitation-form" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="md:col-span-5">
                    <x-form.group name="institution_name" label="Institución educativa" required>
                        <x-form.input
                            type="text"
                            name="institution_name"
                            maxlength="150"
                            value="{{ old('institution_name') }}"
                            placeholder="Ej. CBTis 122 · Campus Chihuahua"
                            required
                        />
                    </x-form.group>
                </div>

                <div class="md:col-span-2">
                    <x-form.group name="max_uses" label="Límite de usos" required>
                        <x-form.input
                            type="number"
                            name="max_uses"
                            min="1"
                            max="500"
                            value="{{ old('max_uses', 10) }}"
                            required
                        />
                    </x-form.group>
                </div>

                <div class="md:col-span-3">
                    <x-form.group name="expires_at" label="Vigencia (opcional)">
                        <x-form.input
                            type="datetime-local"
                            name="expires_at"
                            value="{{ old('expires_at') }}"
                        />
                    </x-form.group>
                </div>

                <div class="md:col-span-2">
                    <x-form.group name="custom_prefix" label="Prefijo (opcional)" helpText="2 a 10 alfanuméricos">
                        <x-form.input
                            type="text"
                            name="custom_prefix"
                            minlength="2"
                            maxlength="10"
                            pattern="[A-Za-z0-9]{2,10}"
                            value="{{ old('custom_prefix') }}"
                            placeholder="Ej. CBTIS"
                            class="uppercase font-mono"
                        />
                    </x-form.group>
                </div>
            </div>

            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="flex-1">
                    <x-form.group name="description" label="Descripción o referencia interna (opcional)">
                        <x-form.input
                            type="text"
                            name="description"
                            maxlength="255"
                            value="{{ old('description') }}"
                            placeholder="Ej. Convenio institucional ciclo escolar 2026-2"
                        />
                    </x-form.group>
                </div>

                <div class="shrink-0">
                    <x-button type="submit" variant="primary" size="md" class="w-full md:w-auto">
                        <x-icon name="add" class="h-4 w-4" />
                        Crear código
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    {{-- Tabla de códigos emitidos --}}
    <x-card padding="p-0" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-acode-primary text-white text-xs font-semibold uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-4">Código</th>
                        <th class="px-5 py-4">Institución y descripción</th>
                        <th class="px-5 py-4">Usos</th>
                        <th class="px-5 py-4">Vigencia</th>
                        <th class="px-5 py-4">Estado</th>
                        <th class="px-5 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($invitations as $invitation)
                        <tr class="hover:bg-acode-surface/40 transition">
                            {{-- Código y botones rápidos de copiado (código y enlace directo ?codigo=) --}}
                            <td class="px-5 py-4 align-middle whitespace-nowrap">
                                <div class="flex flex-col gap-1.5">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="font-mono font-bold text-acode-primary tracking-wider">
                                            {{ $invitation->code }}
                                        </span>
                                        <button
                                            type="button"
                                            data-code="{{ $invitation->code }}"
                                            @click="copyText($el.dataset.code, 'Código copiado al portapapeles')"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-acode-tech hover:text-acode-tech focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                            title="Copiar código"
                                            aria-label="Copiar código {{ $invitation->code }}"
                                        >
                                            <x-icon name="copy" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        data-invite-url="{{ route('auth.register.teacher.show', ['codigo' => $invitation->code]) }}"
                                        @click="copyText($el.dataset.inviteUrl, 'Enlace directo de registro copiado al portapapeles')"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-acode-tech hover:underline w-fit"
                                    >
                                        <x-icon name="copy" class="h-3 w-3" />
                                        <span>Copiar enlace de registro</span>
                                    </button>
                                </div>
                            </td>

                            {{-- Institución y descripción --}}
                            <td class="px-5 py-4 align-middle">
                                <div class="font-semibold text-acode-primary">{{ $invitation->institution_name }}</div>
                                @if ($invitation->description)
                                    <div class="text-xs text-slate-500 mt-0.5">{{ $invitation->description }}</div>
                                @endif
                                @if ($invitation->creator)
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        Emitido por {{ $invitation->creator->full_name }}
                                    </div>
                                @endif
                            </td>

                            {{-- Usos y barra de progreso sin style inline --}}
                            <td class="px-5 py-4 align-middle min-w-40">
                                <div class="flex items-center justify-between text-xs font-semibold mb-1.5">
                                    <span class="text-acode-primary font-mono">
                                        {{ $invitation->uses_count }} / {{ $invitation->max_uses }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        {{ $invitation->remainingUses() }} disp.
                                    </span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full transition-all duration-300 {{ $invitation->hasRemainingUses() ? 'bg-acode-tech' : 'bg-acode-danger' }} {{ match (
                                            $invitation->uses_count > 0
                                                ? max(1, (int) round(min(12, ($invitation->uses_count / max(1, $invitation->max_uses)) * 12)))
                                                : 0
                                        ) {
                                            0 => 'w-0',
                                            1 => 'w-1/12',
                                            2 => 'w-2/12',
                                            3 => 'w-3/12',
                                            4 => 'w-4/12',
                                            5 => 'w-5/12',
                                            6 => 'w-6/12',
                                            7 => 'w-7/12',
                                            8 => 'w-8/12',
                                            9 => 'w-9/12',
                                            10 => 'w-10/12',
                                            11 => 'w-11/12',
                                            default => 'w-full',
                                        } }}"
                                        role="progressbar"
                                        aria-valuenow="{{ $invitation->uses_count }}"
                                        aria-valuemin="0"
                                        aria-valuemax="{{ $invitation->max_uses }}"
                                    ></div>
                                </div>
                            </td>

                            {{-- Vigencia --}}
                            <td class="px-5 py-4 align-middle whitespace-nowrap text-xs text-slate-700">
                                {{ $invitation->expires_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}
                            </td>

                            {{-- Estado --}}
                            <td class="px-5 py-4 align-middle whitespace-nowrap">
                                @if (! $invitation->is_active)
                                    <x-badge variant="danger">Revocado</x-badge>
                                @elseif ($invitation->isExpired())
                                    <x-badge variant="warning">Expirado</x-badge>
                                @elseif (! $invitation->hasRemainingUses())
                                    <x-badge variant="neutral">Agotado</x-badge>
                                @else
                                    <x-badge variant="success">Activo</x-badge>
                                @endif
                            </td>

                            {{-- Acciones: enviar por correo / revocar / reactivar --}}
                            <td class="px-5 py-4 align-middle text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if ($invitation->isUsable())
                                        <x-button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            data-send-url="{{ route('admin.invitations.send-email', $invitation) }}"
                                            data-code="{{ $invitation->code }}"
                                            data-institution="{{ $invitation->institution_name }}"
                                            @click="openSendModal($el)"
                                        >
                                            Enviar
                                        </x-button>
                                    @endif

                                    <form
                                        method="POST"
                                        action="{{ route('admin.invitations.toggle', $invitation) }}"
                                        data-code="{{ $invitation->code }}"
                                        data-is-active="{{ $invitation->is_active ? '1' : '0' }}"
                                        @submit.prevent="confirmToggleInvitation($el)"
                                        class="inline-block"
                                    >
                                        @csrf
                                        <x-button
                                            :variant="$invitation->is_active ? 'danger' : 'accent'"
                                            size="sm"
                                            type="submit"
                                        >
                                            {{ $invitation->is_active ? 'Revocar' : 'Reactivar' }}
                                        </x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-icon name="key" class="h-8 w-8 text-slate-300" />
                                    <p class="text-sm font-medium text-slate-500">Aún no se han generado códigos de invitación institucional.</p>
                                    <p class="text-xs text-slate-400">Utiliza el formulario superior para emitir el primer código.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invitations->hasPages())
            <div class="border-t border-slate-100 p-4">
                {{ $invitations->links() }}
            </div>
        @endif
    </x-card>

    {{-- Modal para enviar invitación institucional por correo electrónico --}}
    <x-modal name="send-invitation-modal" title="Enviar invitación institucional" maxWidth="md">
        <form method="POST" :action="sendActionUrl" class="space-y-4">
            @csrf
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-600 space-y-1">
                <p>Institución: <strong class="text-acode-primary" x-text="sendInstitution"></strong></p>
                <p>Código: <strong class="font-mono text-acode-tech" x-text="sendCode"></strong></p>
            </div>

            <x-form.group
                name="email"
                label="Correo del docente o coordinador"
                helpText="Recibirá el código y un enlace directo con el código ya aplicado."
                required
            >
                <x-form.input
                    type="email"
                    name="email"
                    maxlength="255"
                    placeholder="docente@institucion.edu"
                    required
                />
            </x-form.group>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-button
                    type="button"
                    variant="neutral"
                    size="sm"
                    @click="$dispatch('close-modal', 'send-invitation-modal')"
                >
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="sm">
                    Enviar
                </x-button>
            </div>
        </form>
    </x-modal>
</div>
@endsection

@push('scripts')
<script>
    window.acodeAdminInvitationsPanel = function () {
        return {
            sendActionUrl: '',
            sendCode: '',
            sendInstitution: '',

            copyText(text, successMessage) {
                if (!text || !navigator.clipboard) {
                    return;
                }

                navigator.clipboard.writeText(text).then(() => {
                    if (typeof window.AcodeAlert !== 'undefined') {
                        window.AcodeAlert.toast(successMessage || 'Copiado al portapapeles', 'success');
                    }
                });
            },

            openSendModal(button) {
                this.sendActionUrl = button.dataset.sendUrl || '';
                this.sendCode = button.dataset.code || '';
                this.sendInstitution = button.dataset.institution || '';
                this.$dispatch('open-modal', 'send-invitation-modal');
            },

            confirmToggleInvitation(form) {
                const code = form.dataset.code || '';
                const isActive = form.dataset.isActive === '1';

                if (typeof window.AcodeAlert !== 'undefined') {
                    window.AcodeAlert.confirm({
                        title: isActive ? '¿Revocar código de invitación?' : '¿Reactivar código de invitación?',
                        text: isActive
                            ? `El código ${code} dejará de aceptarse para nuevos registros o canjes docentes.`
                            : `El código ${code} volverá a estar habilitado sujeto a su vigencia y cupo restante.`,
                        confirmButtonText: 'Aceptar',
                        cancelButtonText: 'Cancelar',
                        isDanger: isActive,
                        onConfirm: () => form.submit(),
                    });
                } else {
                    form.submit();
                }
            },
        };
    };

    (function () {
        const prefixField = document.querySelector('#create-invitation-form [name="custom_prefix"]');
        if (prefixField) {
            prefixField.addEventListener('input', function () {
                const cleaned = prefixField.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                if (cleaned !== prefixField.value) {
                    prefixField.value = cleaned;
                }
            });
        }
    })();
</script>
@endpush
