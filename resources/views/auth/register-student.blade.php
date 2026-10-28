{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/register-student.blade.php (MPW-V2 §7.8) --}}
@extends('layouts.guest')

@section('title', 'Registro de alumno')

@section('content')
    <h1 class="text-lg font-semibold text-acode-primary mb-1">Registro de alumno</h1>
    <p class="text-sm text-slate-500 mb-5">Crea tu cuenta para unirte a grupos y presentar exámenes.</p>

    <form method="POST" action="{{ route('auth.register.student.store') }}" class="space-y-4">
        @csrf

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Nombre(s)</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Apellido(s)</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-800 mb-1">Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-800 mb-1">Teléfono (opcional, 10 dígitos)</label>
            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="10"
                   class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Contraseña</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" required minlength="8"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" name="accepts_privacy_notice" value="1" class="mt-0.5">
            Acepto el aviso de privacidad.
        </label>

        <x-button type="submit" variant="primary" class="w-full">
            Crear cuenta de alumno
        </x-button>
    </form>

    <p class="text-sm text-slate-500 mt-4 text-center">
        ¿Ya tienes cuenta? <a href="{{ route('auth.login.show') }}" class="text-acode-tech font-medium hover:underline">Inicia sesión</a>
    </p>
    <p class="text-sm text-slate-500 mt-1 text-center">
        ¿Eres docente? <a href="{{ route('auth.register.teacher.show') }}" class="text-acode-tech font-medium hover:underline">Regístrate aquí</a>
    </p>
@endsection
