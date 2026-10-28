{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: dashboard/teacher.blade.php (MPW-V2 §7.8) --}}
@extends('layouts.app')

@section('title', 'Panel del docente')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-1">Hola, {{ auth()->user()->first_name }}</h1>
    <p class="text-sm text-slate-500 mb-6">Resumen de tu actividad como docente.</p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <a href="{{ route('groups.index') }}" class="block">
            <x-card class="hover:shadow-md transition">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Grupos</p>
                <p class="text-3xl font-bold text-acode-primary mt-1">{{ $groupsCount }}</p>
            </x-card>
        </a>
        <a href="{{ route('questions.index') }}" class="block">
            <x-card class="hover:shadow-md transition">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Reactivos en banco</p>
                <p class="text-3xl font-bold text-acode-primary mt-1">{{ $questionsCount }}</p>
            </x-card>
        </a>
        <a href="{{ route('exams.index') }}" class="block">
            <x-card class="hover:shadow-md transition">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Exámenes creados</p>
                <p class="text-3xl font-bold text-acode-primary mt-1">{{ $examsCount }}</p>
            </x-card>
        </a>
    </div>

    <div class="flex flex-wrap gap-3">
        <x-button :href="route('groups.create')" variant="primary">
            Crear grupo
        </x-button>
        <x-button :href="route('questions.create')" variant="secondary">
            Crear reactivo
        </x-button>
        <x-button :href="route('exams.create')" variant="accent">
            Crear examen
        </x-button>
    </div>
@endsection
