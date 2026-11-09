@extends('layouts.app')

@section('title', 'Créditos y Evaluaciones con IA')

@section('content')
<div class="space-y-8 max-w-5xl mx-auto">
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl font-bold text-acode-primary">Créditos y Monitoreo de Inteligencia Artificial</h1>
        <p class="text-sm text-slate-500 mt-1">
            Supervise el consumo de solicitudes a Google Gemini 1.5 Flash para la evaluación asistida de preguntas abiertas y de código.
        </p>
    </div>

    {{-- Métricas de IA --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-card>
            <p class="text-xs font-semibold uppercase text-slate-400">Total Evaluaciones IA</p>
            <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ number_format($totalAiEvaluations) }}</p>
            <p class="text-xs text-slate-500 mt-2">Respuestas procesadas por el motor semántico</p>
        </x-card>

        <x-card>
            <p class="text-xs font-semibold uppercase text-slate-400">Recotejes Realizados</p>
            <p class="text-3xl font-extrabold text-acode-accent mt-1">{{ number_format($recotejadasCount) }}</p>
            <p class="text-xs text-slate-500 mt-2">Calificaciones ajustadas por el docente</p>
        </x-card>

        <x-card>
            <p class="text-xs font-semibold uppercase text-slate-400">Promedio de Similitud</p>
            <p class="text-3xl font-extrabold text-acode-tech mt-1">{{ $promedioSimilitud }}%</p>
            <p class="text-xs text-slate-500 mt-2">Cotejo contra solución modelo de referencia</p>
        </x-card>
    </div>

    {{-- Estado de la cuota mensual de IA --}}
    <x-card class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-acode-primary">Bolsa de Evaluaciones Asistidas</h3>
                <p class="text-xs text-slate-500">Cuota incluida en su plan actual: <strong>{{ $activePackage ? $activePackage->name : 'Prueba Freemium' }}</strong></p>
            </div>
            <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded bg-acode-surface text-acode-primary">
                {{ $totalAiEvaluations }} / {{ $aiRequestsLimit }} solicitudes
            </span>
        </div>

        @php
            $pctAi = min(100, (int) round(($totalAiEvaluations / max(1, $aiRequestsLimit)) * 100));
        @endphp
        <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full {{ $pctAi >= 90 ? 'bg-acode-warning' : 'bg-acode-tech' }} rounded-full transition-all duration-500" style="width: {{ $pctAi }}%"></div>
        </div>

        <div class="flex items-center justify-between text-xs text-slate-500">
            <span>{{ 100 - $pctAi }}% disponible en el ciclo actual</span>
            <span>Driver activo: <strong class="text-slate-700">Google Gemini 1.5 Flash (con Mock Fallback)</strong></span>
        </div>
    </x-card>

    {{-- Principios de Privacidad y Minimización --}}
    <x-card class="bg-blue-50/50 border-acode-tech/30 space-y-3">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-acode-tech/10 rounded-lg text-acode-tech shrink-0 mt-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="text-xs text-slate-700 space-y-1">
                <p class="font-bold text-acode-primary text-sm">Privacidad LFPDPPP y Minimización de Datos</p>
                <p>
                    En estricto cumplimiento del Aviso de Privacidad y la normativa mexicana, las evaluaciones semánticas se transmiten a la API de IA de forma totalmente anónima: solo viaja el texto del reactivo, la respuesta del estudiante y la rúbrica de referencia. <strong>Ningún nombre, correo ni identificador personal de los alumnos es enviado al modelo de lenguaje.</strong>
                </p>
            </div>
        </div>
    </x-card>
</div>
@endsection
