@extends('layouts.app')

@section('title', 'Panel del docente')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-1">Hola, {{ auth()->user()->first_name }}</h1>
    <p class="text-sm text-slate-500 mb-6">Resumen de tu actividad como docente.</p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <a href="{{ route('groups.index') }}" class="bg-white rounded-lg border border-slate-200 p-5 hover:shadow-sm transition">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Grupos</p>
            <p class="text-3xl font-bold text-acode-primary mt-1">{{ $groupsCount }}</p>
        </a>
        <a href="{{ route('questions.index') }}" class="bg-white rounded-lg border border-slate-200 p-5 hover:shadow-sm transition">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Reactivos en banco</p>
            <p class="text-3xl font-bold text-acode-primary mt-1">{{ $questionsCount }}</p>
        </a>
        <a href="{{ route('exams.index') }}" class="bg-white rounded-lg border border-slate-200 p-5 hover:shadow-sm transition">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Exámenes creados</p>
            <p class="text-3xl font-bold text-acode-primary mt-1">{{ $examsCount }}</p>
        </a>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('groups.create') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            + Nuevo grupo
        </a>
        <a href="{{ route('questions.create') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-tech text-white text-sm font-semibold hover:bg-[#1d4f85]">
            + Nuevo reactivo
        </a>
        <a href="{{ route('exams.create') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-accent text-white text-sm font-semibold hover:opacity-90">
            + Nuevo examen
        </a>
    </div>
@endsection
