{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/profile/index.blade.php (MPW-V3 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 10) --}}
@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="space-y-6">
    {{-- Encabezado --}}
    <x-page-header
        title="Mi perfil"
        subtitle="Administra tu información personal, consulta tu estado de verificación docente y actualiza tu contraseña de acceso."
    >
        <x-slot:actions>
            <x-button :href="route('teacher.subscription.index')" variant="neutral" size="md">
                <x-nav-icon name="subscription" class="w-4 h-4" />
                Abrir suscripción
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <form id="teacher-profile-form" method="POST" action="{{ route('teacher.profile.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Columna izquierda (8 cols): Información personal + Seguridad --}}
            <div class="lg:col-span-8 space-y-6">
                {{-- Datos Generales --}}
                <x-card>
                    <x-slot:title>Información personal</x-slot:title>
                    <x-slot:subtitle>Datos registrados para la emisión de reportes académicos</x-slot:subtitle>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-form.group name="first_name" label="Nombre" :error="$errors->first('first_name')" required>
                            <x-form.input id="first_name" name="first_name" :value="old('first_name', $user->first_name)" required />
                        </x-form.group>

                        <x-form.group name="last_name" label="Apellidos" :error="$errors->first('last_name')" required>
                            <x-form.input id="last_name" name="last_name" :value="old('last_name', $user->last_name)" required />
                        </x-form.group>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <x-form.group name="email" label="Correo electrónico">
                            <x-form.input id="email" name="email" :value="$user->email" disabled class="bg-slate-50 text-slate-500 cursor-not-allowed" />
                            <p class="text-xs text-slate-500 mt-1">Tu correo electrónico no puede modificarse por motivos de seguridad.</p>
                        </x-form.group>

                        <x-form.group name="phone" label="Teléfono (10 dígitos)" :error="$errors->first('phone')" required>
                            <x-form.input id="phone" name="phone" :value="old('phone', $user->phone)" maxlength="10" placeholder="5512345678" required />
                        </x-form.group>
                    </div>
                </x-card>

                {{-- Seguridad y Contraseña --}}
                <x-card>
                    <x-slot:title>Seguridad y credenciales</x-slot:title>
                    <x-slot:subtitle>Actualiza tu contraseña de acceso o revisa tu segundo factor de autenticación</x-slot:subtitle>

                    <div class="p-3.5 bg-acode-surface/50 rounded-xl border border-slate-200/80 mb-5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-acode-tech/10 rounded-lg text-acode-tech shrink-0">
                                <x-icon name="lock" class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-acode-primary">Autenticación de dos pasos (2FA)</p>
                                <p class="text-xs text-slate-500">Se solicita un código de 6 dígitos enviado a tu correo en cada inicio de sesión.</p>
                            </div>
                        </div>
                        <x-badge variant="success">Activo</x-badge>
                    </div>

                    <div class="space-y-4">
                        <x-form.group name="current_password" label="Contraseña actual (solo si deseas cambiarla)" :error="$errors->first('current_password')">
                            <x-form.input id="current_password" name="current_password" type="password" />
                        </x-form.group>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-form.group name="new_password" label="Nueva contraseña" :error="$errors->first('new_password')">
                                <x-form.input id="new_password" name="new_password" type="password" />
                            </x-form.group>

                            <x-form.group name="new_password_confirmation" label="Confirmar nueva contraseña">
                                <x-form.input id="new_password_confirmation" name="new_password_confirmation" type="password" />
                            </x-form.group>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Columna derecha (4 cols): Resumen de cuenta y verificación docente --}}
            <div class="lg:col-span-4 space-y-6">
                <x-card>
                    <x-slot:title>Resumen de cuenta</x-slot:title>
                    <x-slot:subtitle>Estado de tu acreditación y resguardo profesional</x-slot:subtitle>
                    <x-slot:actions>
                        @if ($user->isPendingTeacherVerification())
                            <x-badge variant="warning">En revisión</x-badge>
                        @elseif ($user->isRejectedTeacherVerification())
                            <x-badge variant="danger">Acción requerida</x-badge>
                        @else
                            <x-badge variant="success">Cuenta verificada</x-badge>
                        @endif
                    </x-slot:actions>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-acode-surface/40 p-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-acode-primary text-base font-bold text-white">
                                {{ strtoupper(substr($user->first_name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-acode-primary">{{ $user->full_name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                            </div>
                        </div>

                        <dl class="space-y-3">
                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                                <dt class="text-xs text-slate-500">Institución</dt>
                                <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                    {{ $user->teacherVerification?->institution_name ?: 'Institución registrada' }}
                                </dd>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                                <dt class="text-xs text-slate-500">Método de acreditación</dt>
                                <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                    {{ $user->teacherVerification?->verificationMethodLabel() ?? 'Verificación institucional' }}
                                </dd>
                            </div>

                            @if ($user->teacherVerification?->professional_license)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                                    <dt class="text-xs text-slate-500">Cédula profesional SEP</dt>
                                    <dd class="mt-1 text-sm font-semibold font-mono text-acode-primary">
                                        {{ $user->teacherVerification->professional_license }}
                                    </dd>
                                </div>
                            @endif

                            @if ($user->teacherVerification?->reviewed_at)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                                    <dt class="text-xs text-slate-500">Fecha de verificación</dt>
                                    <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                        {{ $user->teacherVerification->reviewed_at->format('d/m/Y H:i') }}
                                    </dd>
                                </div>
                            @endif
                        </dl>

                        @if ($user->teacherVerification?->document_purged_at)
                            <div class="rounded-xl border border-acode-accent/30 bg-acode-accent/10 px-4 py-3 flex items-start gap-2.5">
                                <x-icon name="shield" class="h-4 w-4 text-acode-accent shrink-0 mt-0.5" />
                                <p class="text-xs text-acode-primary leading-relaxed">
                                    Tu comprobante enviado fue eliminado automáticamente tras la verificación de tu identidad.
                                </p>
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>
        </div>

        {{-- Barra inferior unificada de acciones --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-slate-500">
                Al guardar se aplicarán los cambios de tu información personal y, si los capturaste, tu nueva contraseña.
            </p>
            <div class="flex items-center justify-end gap-3 shrink-0">
                <x-unsaved-changes
                    form-id="teacher-profile-form"
                    :cancel-url="route('dashboard')"
                />
                <x-button variant="primary" size="md" type="submit">
                    <x-icon name="save" class="w-4 h-4" />
                    Guardar cambios
                </x-button>
            </div>
        </div>
    </form>
</div>
@endsection
