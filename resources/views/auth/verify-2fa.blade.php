{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/verify-2fa.blade.php (MPW-V2 §7.8) --}}
@extends('layouts.guest')

@section('title', 'Verificación en dos pasos')

@section('content')
    <h1 class="text-lg font-semibold text-acode-primary mb-1">Verificación en dos pasos</h1>
    <p class="text-sm text-slate-500 mb-5">
        Enviamos un código de 6 dígitos a <strong>{{ $user->email }}</strong>, válido por 10 minutos.
    </p>

    @if ($user->two_factor_code)
        <div class="mb-5 rounded-lg border border-acode-warning/40 bg-acode-warning/10 px-4 py-3 text-sm text-acode-primary">
            <span class="font-semibold">Modo demo:</span> tu código es
            <span class="font-mono text-base tracking-widest">{{ $user->two_factor_code }}</span>
            (también queda registrado en <code class="font-mono text-xs">storage/logs/laravel.log</code>, por si aquí no se ve).
        </div>
    @endif

    <form method="POST" action="{{ route('auth.verify-2fa.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="user_id" value="{{ $user->id }}">

        <div>
            <label class="block text-xs font-medium text-slate-800 mb-1">Código de 6 dígitos</label>
            <input type="text" name="code" inputmode="numeric" maxlength="6" required autofocus
                   class="w-full h-10 px-3 rounded-lg border border-slate-300 text-center text-lg tracking-[0.5em] font-mono focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <x-button type="submit" variant="primary" class="w-full">
            Continuar
        </x-button>
    </form>
@endsection
