@extends('layouts.app')

@section('title', 'Preguntas: '.$exam->title)

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('exams.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech transition hover:underline">
        <span aria-hidden="true">←</span> Volver a mis exámenes
    </a>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">{{ $exam->title }}</h1>
            @if ($exam->description)
                <p class="mt-2 text-sm text-slate-500">{{ $exam->description }}</p>
            @endif
        </div>
        @if ($exam->is_published)
            <span class="inline-flex self-start rounded-full bg-acode-accent/10 px-3 py-1 text-xs font-semibold text-acode-accent">Publicado</span>
        @else
            <span class="inline-flex self-start rounded-full bg-acode-warning/20 px-3 py-1 text-xs font-semibold text-acode-primary">Borrador</span>
        @endif
    </div>

    {{-- Indicador de etapas --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="grid grid-cols-4 gap-1 sm:gap-2">
            @foreach ([['Datos', true], ['Preguntas', false], ['Config.', false], ['Revisión', false]] as $index => [$label, $completed])
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <div class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full text-xs sm:text-sm font-bold {{ $index === 1 ? 'bg-acode-primary text-white shadow-sm' : ($completed ? 'bg-acode-accent text-white' : 'bg-slate-100 text-slate-500') }}">
                        {{ $completed ? '✓' : $index + 1 }}
                    </div>
                    <span class="text-[11px] sm:text-xs truncate {{ $index === 1 ? 'font-bold text-acode-primary' : 'text-slate-500' }}">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @php
        $totalQuestions = $exam->sections->sum(fn ($section) => $section->examQuestions->count());
        $questionTypes = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Programación',
        ];
    @endphp

    {{-- Resumen --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Apartados creados</p>
            <p class="mt-1 text-2xl font-bold text-acode-primary">{{ $exam->sections->count() }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Reactivos seleccionados</p>
            <p class="mt-1 text-2xl font-bold text-acode-primary">{{ $totalQuestions }}</p>
        </div>
    </div>

    {{-- Opciones de armado --}}
    <section>
        <h2 class="mb-3 text-lg font-semibold text-acode-primary">¿Cómo quieres agregar preguntas?</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-acode-tech bg-white p-5">
                <div class="mb-3 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-acode-tech/10 text-xl text-acode-tech">☷</div>
                    <h3 class="font-semibold text-acode-primary">Mi banco de preguntas</h3>
                </div>
                <p class="text-sm leading-relaxed text-slate-500">Crea apartados y selecciona automáticamente reactivos existentes de tu banco, sin repetir preguntas dentro del examen.</p>
                <p class="mt-4 text-xs font-semibold text-acode-accent">Disponible · Sorteo automático</p>
            </div>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5">
                <div class="mb-3 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-acode-accent/10 text-xl text-acode-accent">✦</div>
                    <h3 class="font-semibold text-acode-primary">Generar preguntas con IA</h3>
                </div>
                <p class="text-sm leading-relaxed text-slate-500">En una próxima versión podrás generar nuevas preguntas mediante IA y revisarlas antes de incorporarlas.</p>
                <button type="button" disabled class="mt-4 cursor-not-allowed rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-500">Próximamente</button>
            </div>
        </div>
    </section>

    {{-- Apartados ya guardados --}}
    <section class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-acode-primary">Apartados del examen</h2>
            <p class="mt-1 text-sm text-slate-500">Revisa las preguntas seleccionadas para cada apartado.</p>
        </div>
        @forelse ($exam->sections as $section)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-acode-primary">{{ $section->title }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $questionTypes[$section->question_type] ?? 'Tipo de reactivo' }}</p>
                    </div>
                    <span class="self-start rounded-full bg-acode-tech/10 px-3 py-1 text-xs font-semibold text-acode-tech">{{ $section->examQuestions->count() }} de {{ $section->requested_quantity }} reactivos</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($section->examQuestions as $examQuestion)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-acode-primary">{{ $loop->iteration }}</span>
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $examQuestion->question?->title ?? 'Reactivo no disponible' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Ponderación: {{ $examQuestion->points_weight }} puntos</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-5 text-sm text-slate-500">Este apartado no tiene reactivos asignados.</p>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                <p class="font-semibold text-acode-primary">Todavía no hay apartados</p>
                <p class="mt-2 text-sm text-slate-500">Agrega el primer apartado utilizando preguntas de tu banco.</p>
            </div>
        @endforelse
    </section>

    {{-- Mensajes --}}
    @if ($errors->any())
        <div class="rounded-xl border border-[#DC2626]/20 bg-[#DC2626]/5 p-4" role="alert">
            <p class="mb-2 text-sm font-semibold text-[#DC2626]">Revisa la información del apartado</p>
            <ul class="list-disc space-y-1 pl-5 text-sm text-[#DC2626]">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($exam->is_published)
        {{-- Propuesta pendiente: los IDs se obtienen únicamente de la sesión en el controlador --}}
        @if (is_array($proposal) && !empty($proposal['question_ids']))
            <section class="rounded-xl border border-acode-tech bg-white p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-acode-primary">Revisar propuesta de preguntas</h2>
                        <p class="mt-1 text-sm text-slate-500">Esta selección es provisional. No se guardará en el examen hasta que la aceptes.</p>
                    </div>
                    <span class="self-start rounded-full bg-acode-tech/10 px-3 py-1 text-xs font-semibold text-acode-tech">Pendiente de aceptación</span>
                </div>
                <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
                    <p><strong>Apartado:</strong> {{ $proposal['title'] ?? '' }}</p>
                    <p class="mt-1"><strong>Tipo:</strong> {{ $questionTypes[$proposal['question_type'] ?? ''] ?? 'No disponible' }}</p>
                    <p class="mt-1"><strong>Tema:</strong> {{ ($proposal['subject_tag'] ?? null) ?: 'Todos los temas' }}</p>
                    <p class="mt-1"><strong>Cantidad:</strong> {{ count($proposal['question_ids']) }} reactivos</p>
                </div>
                <ol class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                    @forelse ($proposedQuestions as $question)
                        <li class="flex items-start gap-3 px-4 py-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-acode-primary">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <p class="break-words text-sm font-medium text-slate-800">{{ $question->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">Ponderación inicial: {{ $question->default_points }} puntos · Tiempo inicial: {{ $question->default_time_seconds }} segundos</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-sm text-acode-danger">Las preguntas de esta propuesta ya no están disponibles. Cancela la propuesta y genera otra.</li>
                    @endforelse
                </ol>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('exams.proposals.accept', $exam) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Aceptar propuesta</x-button>
                    </form>
                    <form method="POST" action="{{ route('exams.proposals.generate', $exam) }}">
                        @csrf
                        <input type="hidden" name="title" value="{{ $proposal['title'] ?? '' }}">
                        <input type="hidden" name="question_type" value="{{ $proposal['question_type'] ?? '' }}">
                        <input type="hidden" name="subject_tag" value="{{ $proposal['subject_tag'] ?? '' }}">
                        <input type="hidden" name="requested_quantity" value="{{ $proposal['requested_quantity'] ?? count($proposal['question_ids']) }}">
                        <x-button variant="secondary" type="submit">Reintentar sorteo</x-button>
                    </form>
                    <form method="POST" action="{{ route('exams.proposals.discard', $exam) }}">
                        @csrf
                        <x-button variant="neutral" type="submit">Cancelar propuesta</x-button>
                    </form>
                </div>
                <p class="mt-3 text-xs text-slate-500">Al reintentar sorteo se genera otra selección aleatoria con los mismos filtros. Algunas preguntas podrían coincidir.</p>
            </section>
        @else
            {{-- Formulario de generación --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 md:p-8">
                <div class="border-b border-slate-200 pb-5">
                    <h2 class="text-lg font-semibold text-acode-primary">
                        Agregar apartado
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        Configura los filtros y revisa una propuesta antes de guardar las preguntas.
                    </p>
                </div>

                <form method="POST"
                    action="{{ route('exams.proposals.generate', $exam) }}"
                    class="mt-6 space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12">
                        <div class="lg:col-span-4">
                            <label for="section-title"
                                class="mb-2 block text-sm font-semibold text-slate-800">
                                Nombre del apartado
                            </label>
                            <input
                                id="section-title"
                                type="text"
                                name="title"
                                value="{{ old('title') }}"
                                maxlength="100"
                                required
                                placeholder="Ej. Fundamentos"
                                class="h-12 w-full rounded-lg border border-slate-300 px-4 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        </div>

                        <div class="lg:col-span-3">
                            <label for="section-type"
                                class="mb-2 block text-sm font-semibold text-slate-800">
                                Tipo de reactivo
                            </label>
                            <select
                                id="section-type"
                                name="question_type"
                                required
                                class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-acode-tech">
                                @foreach ($questionTypes as $type => $label)
                                    <option value="{{ $type }}"
                                            @selected(old('question_type', 'multiple_choice') === $type)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label for="section-subject"
                                class="mb-2 block text-sm font-semibold text-slate-800">
                                Tema
                            </label>
                            <select
                                id="section-subject"
                                name="subject_tag"
                                class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-acode-tech">
                                <option value="">Todos los temas</option>
                                @foreach ($subjectTags as $subjectTag)
                                    <option value="{{ $subjectTag }}"
                                            @selected(old('subject_tag') === $subjectTag)>
                                        {{ $subjectTag }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label for="section-quantity"
                                class="mb-2 block text-sm font-semibold text-slate-800">
                                Cantidad
                            </label>
                            <input
                                id="section-quantity"
                                type="number"
                                name="requested_quantity"
                                min="1"
                                value="{{ old('requested_quantity', 5) }}"
                                required
                                class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        </div>
                    </div>

                    <p class="text-xs leading-relaxed text-slate-500">
                        Las preguntas se seleccionan aleatoriamente del banco privado del docente,
                        sin repetir las que ya están asignadas a este examen.
                        La propuesta no se guarda hasta que la aceptes.
                    </p>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="rounded-lg bg-acode-primary px-6 py-3 text-sm font-semibold
                                text-white transition hover:opacity-90">
                            Generar propuesta de preguntas
                        </button>
                    </div>
                </form>
            </section>
        @endif
    @endunless

    {{-- Navegación --}}
    <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
        @unless ($exam->is_published)
            @if (is_array($proposal) && !empty($proposal['question_ids']))
                <p class="text-sm text-slate-500">Acepta o descarta la propuesta pendiente antes de continuar.</p>
            @else
                <a href="{{ route('exams.configuration', $exam) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-acode-primary px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90">Continuar a Configuración <span aria-hidden="true">→</span></a>
            @endif
        @endunless
    </div>
</div>
@endsection