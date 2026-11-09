@extends('layouts.app')

@section('title', 'Inicio')

@section('content')

    {{-- Bienvenida --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-acode-primary sm:text-3xl">
            ¡Hola, {{ auth()->user()->first_name }}!
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Bienvenido a tu panel docente. Aquí puedes consultar y administrar
            tus grupos, preguntas y exámenes.
        </p>
    </div>

    {{-- Resumen --}}
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-acode-primary">
            Resumen general
        </h2>
        <p class="mt-1 text-sm text-slate-500">
            Información actual de tu cuenta.
        </p>
    </div>

    <div class="mb-10 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">

        {{-- Grupos --}}
        <a href="{{ route('groups.index') }}"
           class="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-acode-tech hover:shadow-md">

            <div class="flex items-start justify-between">
               <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-nav-icon name="groups" class="h-6 w-6" />
                </div>
                <span class="text-sm text-slate-400 transition group-hover:text-acode-tech">
                    ↗
                </span>
            </div>

            <p class="mt-5 text-3xl font-bold text-acode-primary">
                {{ $groupsCount }}
            </p>

            <p class="mt-1 text-sm font-semibold text-slate-700">
                Mis grupos
            </p>

            <p class="mt-2 text-xs text-slate-500">
                Consulta y administra tus grupos.
            </p>
        </a>

        {{-- Preguntas --}}
        <a href="{{ route('questions.index') }}"
           class="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-acode-tech hover:shadow-md">

            <div class="flex items-start justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-nav-icon name="questions" class="h-6 w-6" />
                </div>
                <span class="text-sm text-slate-400 transition group-hover:text-acode-tech">
                    ↗
                </span>
            </div>

            <p class="mt-5 text-3xl font-bold text-acode-primary">
                {{ $questionsCount }}
            </p>

            <p class="mt-1 text-sm font-semibold text-slate-700">
                Banco de preguntas
            </p>

            <p class="mt-2 text-xs text-slate-500">
                Consulta tus preguntas registradas.
            </p>
        </a>

        {{-- Exámenes --}}
        <a href="{{ route('exams.index') }}"
           class="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-acode-tech hover:shadow-md">

            <div class="flex items-start justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-nav-icon name="exams" class="h-6 w-6" />
                </div>
                <span class="text-sm text-slate-400 transition group-hover:text-acode-tech">
                    ↗
                </span>
            </div>

            <p class="mt-5 text-3xl font-bold text-acode-primary">
                {{ $examsCount }}
            </p>

            <p class="mt-1 text-sm font-semibold text-slate-700">
                Exámenes creados
            </p>

            <p class="mt-2 text-xs text-slate-500">
                Consulta y administra tus exámenes.
            </p>
        </a>

    </div>

    {{-- Accesos rápidos --}}
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-acode-primary">
            Accesos rápidos
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Comienza una nueva actividad.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">

        <a href="{{ route('groups.create') }}"
           class="flex items-center justify-between rounded-xl bg-acode-primary px-5 py-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
            <span>+ Crear grupo</span>
            <span>→</span>
        </a>

        <a href="{{ route('questions.create') }}"
           class="flex items-center justify-between rounded-xl bg-acode-tech px-5 py-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
            <span>+ Crear pregunta</span>
            <span>→</span>
        </a>

        <a href="{{ route('exams.create') }}"
           class="flex items-center justify-between rounded-xl bg-acode-primary px-5 py-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
            <span>+ Crear examen</span>
            <span>→</span>
        </a>

    </div>

@endsection