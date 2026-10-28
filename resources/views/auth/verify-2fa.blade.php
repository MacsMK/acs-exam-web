@extends('layouts.guest')

@section('title', 'Verificación en dos pasos')

@section('content')
    <h1 class="text-lg font-semibold text-acode-primary mb-1">Verificación en dos pasos</h1>
    <p class="text-sm text-slate-500 mb-5">
        Enviamos un código de 6 dígitos a <strong>{{ $user->email }}</strong>, válido por 10 minutos.
    </p>

    

    <form method="POST" action="{{ route('auth.verify-2fa.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="user_id" value="{{ $user->id }}">

        <div>
            <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Código de 6 dígitos</label>
            <input type="text" name="code" inputmode="numeric" maxlength="6" required autofocus
                   class="w-full h-10 px-3 rounded-md border border-slate-300 text-center text-lg tracking-[0.5em] font-mono focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <button type="submit" class="w-full h-10 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            Verificar y entrar
        </button>
    </form>
@endsection
