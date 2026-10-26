@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h1 class="text-lg font-semibold text-acode-primary mb-1">Iniciar sesión</h1>
    <p class="text-sm text-slate-500 mb-5">Docentes y alumnos usan el mismo acceso.</p>

    <form method="POST" action="{{ route('auth.login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <div>
            <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Contraseña</label>
            <input type="password" name="password" required
                   class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <button type="submit" class="w-full h-10 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            Continuar
        </button>
    </form>

    <p class="text-sm text-slate-500 mt-4 text-center">
        ¿Aún no tienes cuenta?
        <a href="{{ route('auth.register.teacher.show') }}" class="text-acode-tech font-medium">Docente</a>
        ·
        <a href="{{ route('auth.register.student.show') }}" class="text-acode-tech font-medium">Alumno</a>
    </p>
@endsection
