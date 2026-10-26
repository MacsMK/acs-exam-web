@extends('layouts.app')

@section('title', 'Panel del alumno')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-1">Hola, {{ auth()->user()->first_name }}</h1>
    <p class="text-sm text-slate-500 mb-6">Resumen de tu actividad como alumno.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8 max-w-xl">
        <a href="{{ route('student.groups.join.form') }}" class="bg-white rounded-lg border border-slate-200 p-5 hover:shadow-sm transition">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Grupos inscritos</p>
            <p class="text-3xl font-bold text-acode-primary mt-1">{{ $groupsCount }}</p>
        </a>
        <a href="{{ route('student.exams.index') }}" class="bg-white rounded-lg border border-slate-200 p-5 hover:shadow-sm transition">
            <p class="text-xs text-slate-500 uppercase tracking-wide">Ver mis exámenes</p>
            <p class="text-sm text-acode-tech font-semibold mt-3">Ir a la lista →</p>
        </a>
    </div>

    <a href="{{ route('student.groups.join.form') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
        Unirme a un grupo con folio
    </a>
@endsection
