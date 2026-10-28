{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/create.blade.php (MPW-V2 §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', 'Crear examen')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Crear examen</h1>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('exams.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Título</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="150"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Descripción (opcional)</label>
                <textarea name="description" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-800 mb-1">Escala de calificación</label>
                    <select name="grading_scale" required
                            class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        <option value="0-10">0 - 10</option>
                        <option value="0-100">0 - 100</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-800 mb-1">Visibilidad del resultado</label>
                    <select name="result_visibility_mode" required
                            class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        <option value="final_score">Calificación final</option>
                        <option value="estimated_score">Calificación estimada</option>
                        <option value="objective_only_pending">Solo objetivo (pendiente de IA)</option>
                        <option value="hidden">Oculto</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-800 mb-1">Inicia</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required
                           class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-800 mb-1">Termina</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->addWeek()->format('Y-m-d\TH:i')) }}" required
                           class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Incidentes máximos permitidos</label>
                <input type="number" min="0" name="max_allowed_incidents" value="{{ old('max_allowed_incidents', 3) }}" required
                       class="w-32 h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="randomize_question_order" value="1" checked>
                    Orden de preguntas aleatorio
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="require_webcam" value="1">
                    Requiere cámara web
                </label>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-800 mb-2">Asignar a grupos</label>
                <div class="space-y-1">
                    @forelse ($groups as $group)
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="group_ids[]" value="{{ $group->id }}">
                            {{ $group->name }} <span class="text-slate-400">({{ $group->access_folio }})</span>
                        </label>
                    @empty
                        <x-empty-state
                            class="p-4 text-left"
                            message="No tienes grupos todavía. Puedes crear el examen y asignarlo después."
                        />
                    @endforelse
                </div>
            </div>

            <x-button type="submit" variant="primary">
                Crear examen
            </x-button>
        </form>
    </x-card>
@endsection
