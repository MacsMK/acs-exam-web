{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: dashboard/student.blade.php (MPW-V2 §7.8) --}}
@extends('layouts.app')

@section('title', 'Panel del alumno')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-1">Hola, {{ auth()->user()->first_name }}</h1>
    <p class="text-sm text-slate-500 mb-6">Resumen de tu actividad como alumno.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8 max-w-xl">
        <a href="{{ route('student.groups.join.form') }}" class="block">
            <x-card class="hover:shadow-md transition">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Grupos inscritos</p>
                <p class="text-3xl font-bold text-acode-primary mt-1">{{ $groupsCount }}</p>
            </x-card>
        </a>
        <a href="{{ route('student.exams.index') }}" class="block">
            <x-card class="hover:shadow-md transition">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Ver mis exámenes</p>
                <p class="text-sm text-acode-tech font-semibold mt-3">Ver detalle</p>
            </x-card>
        </a>
    </div>

    <x-button :href="route('student.groups.join.form')" variant="primary">
        Enviar solicitud a un grupo
    </x-button>
@endsection
