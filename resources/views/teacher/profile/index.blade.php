{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/profile/index.blade.php (MPW-V2 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold text-acode-primary">Configuración de Perfil Docente</h1>
        <p class="text-sm text-slate-500 mt-1">
            Administre sus datos personales, información de contacto y credenciales de acceso.
        </p>
    </div>

    <form method="POST" action="{{ route('teacher.profile.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Datos Generales --}}
        <x-card>
            <x-slot:title>Información Personal</x-slot:title>
            <x-slot:subtitle>Datos registrados para la emisión de reportes académicos</x-slot:subtitle>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <x-form.group name="first_name" label="Nombre" :error="$errors->first('first_name')" required>
                    <x-form.input id="first_name" name="first_name" :value="old('first_name', $user->first_name)" required />
                </x-form.group>

                <x-form.group name="last_name" label="Apellidos" :error="$errors->first('last_name')" required>
                    <x-form.input id="last_name" name="last_name" :value="old('last_name', $user->last_name)" required />
                </x-form.group>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <x-form.group name="email" label="Correo Electrónico">
                    <x-form.input id="email" name="email" :value="$user->email" disabled class="bg-slate-50 text-slate-500 cursor-not-allowed" />
                    <p class="text-[11px] text-slate-400 mt-1">El correo electrónico no puede modificarse por motivos de seguridad.</p>
                </x-form.group>

                <x-form.group name="phone" label="Teléfono (10 dígitos)" :error="$errors->first('phone')" required>
                    <x-form.input id="phone" name="phone" :value="old('phone', $user->phone)" maxlength="10" placeholder="5512345678" required />
                </x-form.group>
            </div>
        </x-card>

        {{-- Acreditación Docente (solo lectura) --}}
        <x-card>
            <x-slot:title>Acreditación Docente</x-slot:title>
            <x-slot:subtitle>Resumen de validación institucional y resguardo de identidad profesional</x-slot:subtitle>
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
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                            El comprobante enviado fue eliminado automáticamente tras la verificación de tu identidad.
                        </p>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Seguridad y Contraseña --}}
        <x-card>
            <x-slot:title>Seguridad y Credenciales</x-slot:title>
            <x-slot:subtitle>Cambie su contraseña de acceso o verifique su segundo factor</x-slot:subtitle>

            <div class="p-3 bg-acode-surface/50 rounded-lg border border-slate-200/60 mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-acode-tech/10 rounded text-acode-tech">
                        <x-icon name="lock" class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-acode-primary">Autenticación de Dos Pasos (2FA)</p>
                        <p class="text-[11px] text-slate-500">Se solicita un código de 6 dígitos enviado a su correo en cada inicio de sesión.</p>
                    </div>
                </div>
                <x-badge variant="success">Activo</x-badge>
            </div>

            <div class="space-y-4">
                <x-form.group name="current_password" label="Contraseña Actual (solo si desea cambiarla)" :error="$errors->first('current_password')">
                    <x-form.input id="current_password" name="current_password" type="password" />
                </x-form.group>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form.group name="new_password" label="Nueva Contraseña" :error="$errors->first('new_password')">
                        <x-form.input id="new_password" name="new_password" type="password" />
                    </x-form.group>

                    <x-form.group name="new_password_confirmation" label="Confirmar Nueva Contraseña">
                        <x-form.input id="new_password_confirmation" name="new_password_confirmation" type="password" />
                    </x-form.group>
                </div>
            </div>

            <x-slot:footer>
                <x-button variant="primary" type="submit">
                    Guardar cambios
                </x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
@endsection
