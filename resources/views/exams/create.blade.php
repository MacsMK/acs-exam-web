{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/create.blade.php (MPW-V2 §5.2.2, §7.3, §7.8.2, §8.5, Tabla 20, Tabla 28) --}}
@extends('layouts.app')

@section('title', 'Crear Examen')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Crear Examen</h1>
        <p class="text-sm text-slate-500 mt-1">Configura las reglas generales, escala de calificación y grupos objetivo de la evaluación.</p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('exams.store') }}" class="space-y-5">
            @csrf

            {{-- Título de la evaluación --}}
            <x-form.group name="title" label="Título del examen" required>
                <x-form.input
                    type="text"
                    name="title"
                    value="{{ old('title') }}"
                    required
                    maxlength="150"
                    placeholder="Ej. Examen Parcial 1 — Arquitectura de Software"
                    prefixIcon="clock"
                />
            </x-form.group>

            {{-- Descripción del examen --}}
            <x-form.group name="description" label="Descripción o instrucciones generales (opcional)">
                <x-form.textarea
                    name="description"
                    rows="2"
                    placeholder="Instrucciones previas para los estudiantes al abrir el examen…"
                >{{ old('description') }}</x-form.textarea>
            </x-form.group>

            {{-- Escala de calificación y Modo de visibilidad --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-form.group name="grading_scale" label="Escala de calificación" required>
                    <x-form.select name="grading_scale" required>
                        <option value="0-10">Escala decimal estándar (0.0 - 10.0)</option>
                        <option value="0-100">Escala porcentual (0 - 100)</option>
                    </x-form.select>
                </x-form.group>

                <x-form.group name="result_visibility_mode" label="Visibilidad del resultado final" required>
                    <x-form.select name="result_visibility_mode" required>
                        <option value="final_score">Calificación final inmediata</option>
                        <option value="estimated_score">Calificación estimada preliminar</option>
                        <option value="objective_only_pending">Solo puntaje objetivo (abiertas pendientes)</option>
                        <option value="hidden">Ocultar calificación hasta revisión docente</option>
                    </x-form.select>
                </x-form.group>
            </div>

            {{-- Ventana de aplicación: Inicia y Termina --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-form.group name="starts_at" label="Fecha y hora de inicio" required>
                    <x-form.input
                        type="datetime-local"
                        name="starts_at"
                        value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}"
                        required
                    />
                </x-form.group>

                <x-form.group name="ends_at" label="Fecha y hora de cierre" required>
                    <x-form.input
                        type="datetime-local"
                        name="ends_at"
                        value="{{ old('ends_at', now()->addWeek()->format('Y-m-d\TH:i')) }}"
                        required
                    />
                </x-form.group>
            </div>

            {{-- Tolerancia de incidentes y opciones anti-trampa --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <x-form.group
                    name="max_allowed_incidents"
                    label="Incidentes máximos"
                    helpText="Tolerancia por pérdida de foco"
                    required
                >
                    <x-form.input
                        type="number"
                        min="0"
                        name="max_allowed_incidents"
                        value="{{ old('max_allowed_incidents', 3) }}"
                        required
                        prefixIcon="shield"
                    />
                </x-form.group>

                <div class="sm:col-span-2 space-y-2 pb-1">
                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            name="randomize_question_order"
                            value="1"
                            checked
                            class="rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                        />
                        <span class="font-medium">Orden pseudoaleatorio de preguntas por alumno</span>
                    </label>

                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            name="require_webcam"
                            value="1"
                            class="rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                        />
                        <span class="font-medium">Habilitar supervisión anti-suplantación por cámara web</span>
                    </label>
                </div>
            </div>

            {{-- Asignación de Grupos Escolares --}}
            <div class="pt-2 border-t border-slate-100">
                <label class="block text-xs font-semibold text-slate-800 uppercase tracking-wider mb-2">
                    Asignar examen a grupos escolares
                </label>
                <div class="space-y-1.5 max-h-48 overflow-y-auto p-3 bg-slate-50 border border-slate-200 rounded-xl">
                    @forelse ($groups as $group)
                        <label class="flex items-center gap-2 text-xs text-slate-700 hover:bg-white p-1.5 rounded-lg cursor-pointer transition">
                            <input
                                type="checkbox"
                                name="group_ids[]"
                                value="{{ $group->id }}"
                                class="rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                            />
                            <span class="font-bold text-acode-primary">{{ $group->name }}</span>
                            <span class="font-mono text-slate-400">({{ $group->access_folio }})</span>
                            <span class="text-slate-400">· {{ $group->subject ?? 'General' }}</span>
                        </label>
                    @empty
                        <x-empty-state
                            class="p-4 text-left border-0 shadow-none bg-transparent"
                            message="No tienes grupos dados de alta todavía. Podrás asignar el examen más tarde."
                        />
                    @endforelse
                </div>
            </div>

            {{-- Botones de Acción --}}
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4 mr-1.5" />
                    Crear examen
                </x-button>

                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    Volver
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
