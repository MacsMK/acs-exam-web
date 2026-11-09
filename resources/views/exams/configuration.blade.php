@extends('layouts.app')

@section('title', 'Configuración: '.$exam->title)

@section('content')
@php
    $examQuestions = $exam->sections->flatMap(fn ($section) => $section->examQuestions)->values();
    $totalPoints = $examQuestions->sum(fn ($item) => (float) $item->points_weight);
    $totalTime = $examQuestions->sum('time_limit_seconds');
    $isPublished = (bool) $exam->is_published;
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('exams.assemble', $exam) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech hover:underline">
        <span aria-hidden="true">←</span> Volver a Preguntas
    </a>

    <header>
        <p class="text-xs font-semibold uppercase tracking-wide text-acode-tech">Etapa 3 de 4 · Configuración</p>
        <h1 class="mt-1 text-2xl font-bold text-acode-primary">Configurar examen</h1>
        <p class="mt-2 text-sm text-slate-500">Ajusta la calificación, el orden, los tiempos y las ponderaciones antes de continuar.</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="grid grid-cols-4 gap-2">
            @foreach (['Datos', 'Preguntas', 'Configuración', 'Revisión'] as $index => $label)
                <div class="flex flex-col items-center gap-2 text-center">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold {{ $index === 2 ? 'bg-acode-primary text-white' : ($index < 2 ? 'bg-acode-accent text-white' : 'bg-slate-100 text-slate-500') }}">
                        {{ $index < 2 ? '✓' : $index + 1 }}
                    </span>
                    <span class="text-xs {{ $index === 2 ? 'font-semibold text-acode-primary' : 'text-slate-500' }}">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($isPublished)
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">Este examen está publicado. Su configuración es de solo lectura.</div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-[#DC2626]/30 bg-red-50 p-4 text-sm text-[#DC2626]" role="alert">
            <p class="font-semibold">Revisa los siguientes datos:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('exams.configuration.update', $exam) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="space-y-6 min-w-0">
                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Datos generales</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="title" class="mb-2 block text-sm font-medium text-slate-800">Nombre del examen</label>
                            <input id="title" name="title" type="text" required maxlength="150" value="{{ old('title', $exam->title) }}" @disabled($isPublished)
                                   class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                            @error('title') <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="description" class="mb-2 block text-sm font-medium text-slate-800">Descripción e instrucciones</label>
                            <textarea id="description" name="description" rows="4" @disabled($isPublished)
                                      class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ old('description', $exam->description) }}</textarea>
                            @error('description') <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Calificación y resultados</h2>
                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="grading_scale" class="mb-2 block text-sm font-medium text-slate-800">Escala de calificación</label>
                            <select id="grading_scale" name="grading_scale" @disabled($isPublished) class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                <option value="0-10" @selected(old('grading_scale', $exam->grading_scale) === '0-10')>0 a 10</option>
                                <option value="0-100" @selected(old('grading_scale', $exam->grading_scale) === '0-100')>0 a 100</option>
                            </select>
                        </div>
                        <div>
                            <label for="result_visibility_mode" class="mb-2 block text-sm font-medium text-slate-800">Visibilidad de resultados</label>
                            <select id="result_visibility_mode" name="result_visibility_mode" @disabled($isPublished) class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                <option value="final_score" @selected(old('result_visibility_mode', $exam->result_visibility_mode) === 'final_score')>Calificación final</option>
                                <option value="hidden" @selected(old('result_visibility_mode', $exam->result_visibility_mode) === 'hidden')>Ocultar resultados</option>
                                @if (in_array($exam->result_visibility_mode, ['estimated_score', 'objective_only_pending'], true))
                                    <option value="{{ $exam->result_visibility_mode }}" @selected(old('result_visibility_mode', $exam->result_visibility_mode) === $exam->result_visibility_mode)>Configuración heredada (pendiente de revisión)</option>
                                @endif
                            </select>
                            <p class="mt-2 text-xs text-slate-500">Las modalidades de resultados provisionales siguen pendientes de validación.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Opciones de aplicación</h2>
                    <div class="mt-5 space-y-5">
                        <div>
                            <p class="mb-2 block text-sm font-medium text-slate-800">Orden de las preguntas</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ([0 => ['Fijo', 'Conservar el orden de los reactivos'], 1 => ['Aleatorio', 'Mezclar los reactivos al aplicar']] as $value => $option)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-4 hover:border-acode-tech">
                                        <input type="radio" name="randomize_question_order" value="{{ $value }}" @checked((int) old('randomize_question_order', $exam->randomize_question_order) === $value) @disabled($isPublished) class="mt-1 text-acode-tech focus:ring-acode-tech">
                                        <span><strong class="block text-sm text-acode-primary">{{ $option[0] }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $option[1] }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-acode-primary">Modalidad actual: secuencial</p>
                            <p class="mt-1 text-xs text-slate-600">Se presenta una pregunta a la vez y no se puede regresar. La modalidad libre aún no está disponible.</p>
                        </div>
                        <div>
                            <label for="max_allowed_incidents" class="mb-2 block text-sm font-medium text-slate-800">Número máximo de incidencias</label>
                            <input id="max_allowed_incidents" name="max_allowed_incidents" type="number" min="0" required value="{{ old('max_allowed_incidents', $exam->max_allowed_incidents) }}" @disabled($isPublished)
                                   class="h-12 w-full max-w-xs rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                        </div>
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="hidden" name="require_webcam" value="0" @disabled($isPublished)>
                            <input type="checkbox" name="require_webcam" value="1" @checked(old('require_webcam', $exam->require_webcam)) @disabled($isPublished) class="mt-1 rounded border-slate-300 text-acode-tech">
                            <span><strong class="block text-acode-primary">Solicitar cámara web</strong>Mantener la configuración de cámara del examen.</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Puntos y tiempo por pregunta</h2>
                    <p class="mt-2 text-sm text-slate-500">Ajusta la ponderación y el tiempo individual de cada reactivo. La suma de puntos debe ser 100.</p>
                    @error('questions') <p class="mt-3 text-sm font-medium text-[#DC2626]">{{ $message }}</p> @enderror
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[480px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr><th class="px-3 py-3">Pregunta</th><th class="px-3 py-3 w-32">Puntos</th><th class="px-3 py-3 w-36">Tiempo (seg.)</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($exam->sections->sortBy('sort_order') as $section)
                                    <tr class="bg-slate-50/70"><th colspan="3" class="px-3 py-3 font-semibold text-acode-primary">{{ $section->title }}</th></tr>
                                    @foreach ($section->examQuestions->sortBy('sort_order') as $examQuestion)
                                        <tr>
                                            <td class="px-3 py-3 text-slate-700">
                                                <span class="font-medium">Pregunta {{ $loop->iteration }}</span>
                                                <span class="mt-1 block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($examQuestion->question?->question_text ?? 'Reactivo '.$examQuestion->question_id, 85) }}</span>
                                            </td>
                                            <td class="px-3 py-3">
                                                    <input type="number" name="questions[{{ $examQuestion->id }}][points_weight]" min="0" max="100" step="0.01" required data-points-input @disabled($isPublished)
                                                        value="{{ old('questions.'.$examQuestion->id.'.points_weight', $examQuestion->points_weight) }}" aria-label="Puntos de la pregunta {{ $examQuestion->id }}"
                                                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                            </td>
                                            <td class="px-3 py-3">
                                                    <input type="number" name="questions[{{ $examQuestion->id }}][time_limit_seconds]" min="1" step="1" required data-time-input @disabled($isPublished)
                                                       value="{{ old('questions.'.$examQuestion->id.'.time_limit_seconds', $examQuestion->time_limit_seconds) }}" aria-label="Tiempo de la pregunta {{ $examQuestion->id }}"
                                                       class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr><td colspan="3" class="px-3 py-5 text-center text-slate-500">Todavía no hay apartados ni preguntas. Vuelve a la Etapa 2.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">El tiempo sobrante de una pregunta no se acumula para la siguiente.</p>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Programación y grupos</h2>
                    <p class="mt-2 text-sm text-slate-500">Estos datos se conservarán como fueron registrados. La edición de fechas queda pendiente de validar la zona horaria.</p>
                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs text-slate-500">Fecha de inicio registrada</p><p class="mt-1 font-semibold text-acode-primary">{{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs text-slate-500">Fecha de término registrada</p><p class="mt-1 font-semibold text-acode-primary">{{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p></div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Horarios mostrados tal como los interpreta Laravel. La zona horaria sigue pendiente de validación.</p>
                    <p class="mb-2 mt-5 text-sm font-medium text-slate-800">Grupos asignados</p>
                    @forelse ($exam->groups as $group)
                        <span class="mb-2 mr-2 inline-flex rounded-full bg-acode-tech/10 px-3 py-1 text-xs font-semibold text-acode-tech">{{ $group->name }}</span>
                    @empty
                        <p class="text-sm text-slate-500">No hay grupos asignados.</p>
                    @endforelse
                </section>
            </div>

            <aside class="rounded-xl border border-slate-200 bg-white p-6 lg:sticky lg:top-6">
                <h2 class="text-lg font-semibold text-acode-primary">Resumen del examen</h2>
                <div class="mt-5 space-y-4">
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-500">Ponderación actual</p>
                        <p class="mt-1 text-2xl font-bold text-acode-primary"><span id="points-total">{{ number_format($totalPoints, 2) }}</span><span class="text-base font-medium text-slate-500">/ 100</span></p>
                        <p id="points-status" class="mt-1 text-xs {{ abs($totalPoints - 100) < 0.001 ? 'text-teal-700' : 'text-amber-700' }}"> {{ abs($totalPoints - 100) < 0.001 ? 'Suma actual completa' : 'Ajusta los puntos hasta sumar 100' }}</p>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3 text-sm"><span class="text-slate-500">Preguntas</span><strong class="text-acode-primary">{{ $examQuestions->count() }}</strong></div>
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-3 text-sm"><span class="text-slate-500">Apartados</span><strong class="text-acode-primary">{{ $exam->sections->count() }}</strong></div>
                    <div class="flex justify-between gap-3 text-sm"><span class="text-slate-500">Tiempo asignado</span><strong class="text-acode-primary"><span id="time-total">{{ floor($totalTime / 60) }} min {{ $totalTime % 60 }} s</span></strong></div>
                    <p class="text-xs text-slate-500">El resumen se actualiza mientras editas. Los cambios se guardan al continuar.</p>
                </div>
            </aside>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-between">
            <a href="{{ route('exams.assemble', $exam) }}" class="rounded-lg border border-slate-300 px-5 py-3 text-center text-sm font-semibold text-acode-primary hover:bg-slate-50">Volver a Preguntas</a>
            @unless ($isPublished)
                <button type="submit" id="save-configuration" class="rounded-lg bg-acode-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90">Guardar y continuar a Revisión</button>
            @else
                <a href="{{ route('exams.review', $exam) }}" class="rounded-lg bg-acode-primary px-5 py-3 text-center text-sm font-semibold text-white">Ir a Revisión</a>
            @endunless
        </div>
    </form>
</div>

@unless ($isPublished)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const scale = document.getElementById('grading_scale');
    const points = [...document.querySelectorAll('[data-points-input]')];
    const times = [...document.querySelectorAll('[data-time-input]')];

    const totalLabel = document.getElementById('points-total');
    const statusLabel = document.getElementById('points-status');
    const timeLabel = document.getElementById('time-total');
    const saveButton = document.getElementById('save-configuration');

    function updateSummary() {
        const integersOnly = scale.value === '0-100';

        points.forEach(input => {
            input.step = integersOnly ? '1' : '0.01';
        });

        const validPoints = points.length > 0 &&
            points.every(input =>
                input.value !== '' && input.validity.valid
            );

        const totalCents = points.reduce((sum, input) => {
            return sum + Math.round((Number(input.value) || 0) * 100);
        }, 0);

        const total = totalCents / 100;
        const complete = validPoints && totalCents === 10000;

        totalLabel.textContent = total.toFixed(2);

        statusLabel.textContent = complete
            ? 'Suma actual completa'
            : integersOnly
                ? 'La suma debe ser 100 y los puntos deben ser enteros.'
                : 'Ajusta los puntos hasta sumar 100.';

        statusLabel.classList.toggle('text-teal-700', complete);
        statusLabel.classList.toggle('text-amber-700', !complete);

        const totalSeconds = times.reduce((sum, input) => {
            return sum + (Number(input.value) || 0);
        }, 0);

        timeLabel.textContent =
            `${Math.floor(totalSeconds / 60)} min ${totalSeconds % 60} s`;

        const validTimes = times.length === points.length &&
            times.every(input => input.value !== '' && input.validity.valid);

        saveButton.disabled = !complete || !validTimes;
        saveButton.classList.toggle('opacity-50', saveButton.disabled);
        saveButton.classList.toggle('cursor-not-allowed', saveButton.disabled);
    }

    [...points, ...times].forEach(input => {
        input.addEventListener('input', updateSummary);
    });

    scale.addEventListener('change', updateSummary);

    updateSummary();
});
</script>
@endunless
@endsection