{{-- ============================================================================
A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
============================================================================
Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
Archivo:        review-index.blade.php
Ubicación:      resources/views/exams/review-index.blade.php
Requerimiento:  RF-IAR-002, RF-EVA-009, RW-11 (Listado de Intentos para Recoteje)
Autor:          Equipo 4 (A-Code Solutions)
Versión:        1.0.0
Fecha Creación: 08/11/2026
============================================================================ --}}
@extends('layouts.app')

@section('title', 'Revisiones de Examen: ' . $exam->title)

@section('content')
<div class="space-y-6">
    {{-- Encabezado y Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-500 mb-2">
                <a href="{{ route('exams.index') }}" class="hover:text-acode-primary transition">Exámenes</a>
                <span>/</span>
                <span class="text-acode-primary font-bold">{{ $exam->title }}</span>
                <span>/</span>
                <span class="text-slate-400">Intentos y Recoteje</span>
            </nav>
            <h1 class="text-2xl font-bold text-acode-primary">Revisiones de Examen</h1>
            <p class="text-sm text-slate-600 mt-1">Supervisa y ajusta las calificaciones asistidas por IA de los estudiantes que presentaron el examen.</p>
        </div>

        <a href="{{ route('exams.index') }}" class="inline-flex items-center text-sm font-semibold text-acode-tech hover:text-acode-tech-dark">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver a Exámenes
        </a>
    </div>

    {{-- Tabla de Intentos --}}
    <x-card padding="p-0" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-acode-primary text-white text-xs uppercase tracking-wider">
                        <th class="py-3 px-4 font-semibold">Estudiante</th>
                        <th class="py-3 px-4 font-semibold">Estado</th>
                        <th class="py-3 px-4 font-semibold">Fecha de Envío</th>
                        <th class="py-3 px-4 font-semibold text-right">Puntaje Objetivo</th>
                        <th class="py-3 px-4 font-semibold text-right">Estimado IA</th>
                        <th class="py-3 px-4 font-semibold text-right">Calificación Final</th>
                        <th class="py-3 px-4 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($attempts as $attempt)
                        @php
                            $hasPendingAi = $attempt->answers->contains(fn ($ans) => in_array($ans->examQuestion?->question?->question_type, ['open', 'code'], true) && $ans->teacher_recoteje_score === null);
                        @endphp
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-medium text-slate-900">
                                <div class="font-bold text-acode-navy">{{ $attempt->student->name }}</div>
                                <div class="text-xs text-slate-500">{{ $attempt->student->email }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($attempt->status === 'submitted')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Entregado
                                    </span>
                                @elseif ($attempt->status === 'cancelled_by_incident')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                                        Cancelado por Incidencias
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                        {{ $attempt->status }}
                                    </span>
                                @endif

                                @if ($hasPendingAi)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 ml-1">
                                        Requiere Recoteje
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600">
                                {{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m/Y H:i') : 'En curso' }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-slate-700">
                                {{ number_format((float) $attempt->objective_score, 1) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-slate-700">
                                {{ number_format((float) $attempt->ai_estimated_score, 1) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold {{ $attempt->final_score !== null ? 'text-acode-navy' : 'text-slate-400' }}">
                                {{ $attempt->final_score !== null ? number_format((float) $attempt->final_score, 1) : 'Pendiente' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="{{ route('exams.revision.show', [$exam, $attempt]) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-acode-surface hover:bg-slate-200 text-acode-navy border border-slate-300 transition">
                                    <span>Recotejar</span>
                                    <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                No hay intentos presentados para este examen aún.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($attempts->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $attempts->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection
