@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="mx-auto flex min-h-[65vh] max-w-2xl items-center justify-center">

        <div class="w-full rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm sm:p-12">

            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-acode-surface text-acode-tech">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-8 w-8"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M12 6v6l4 2"/>
                    <circle cx="12" cy="12" r="9"/>
                </svg>
            </div>

            <p class="mt-6 text-sm font-semibold text-acode-tech">
                {{ $title }}
            </p>

            <h1 class="mt-2 text-2xl font-bold text-acode-primary sm:text-3xl">
                Función aún no disponible
            </h1>

            <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600">
                Esta sección forma parte del Evaluador de Exámenes
                de A-Code Solutions, pero todavía se encuentra en desarrollo.
            </p>

            <p class="mt-2 text-sm text-slate-500">
                Podrás acceder a sus funciones cuando estén implementadas
                y validadas.
            </p>

            <a href="{{ route('dashboard') }}"
               class="mt-8 inline-flex h-12 items-center justify-center rounded-lg bg-acode-primary px-6 text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                Volver al inicio
            </a>

        </div>

    </div>
@endsection