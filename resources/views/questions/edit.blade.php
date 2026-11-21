{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/edit.blade.php (MPW-V3 §7, §8, Tabla 10, RF-BAN-007) --}}
@extends('layouts.app')

@section('title', 'Editar Reactivo · ' . $question->title)

@section('content')
    @php
        $typeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código fuente',
        ];
        $typeVariants = [
            'multiple_choice' => 'info',
            'matching' => 'accent',
            'open' => 'warning',
            'code' => 'primary',
        ];
        $isLinked = (bool) ($isLinkedToExams ?? $isLinkedToExam ?? false);
    @endphp

    <div
        class="space-y-6"
        x-data="acodeQuestionForm({
            formId: 'edit-question-form',
            type: @js($question->question_type),
            title: @js(old('title', $question->title)),
            subjectTag: @js(old('subject_tag', $question->subject_tag ?? '')),
            stem: @js(old('stem', $question->stem)),
            options: @js(old('options', $initialOptions)),
            correctOption: @js((int) old('correct_option_index', $initialCorrectOption)),
            pairs: @js(old('concepts') !== null ? collect(old('concepts'))->values()->map(static fn ($c, $i) => ['concept' => (string) $c, 'definition' => (string) (old('definitions')[$i] ?? '')])->all() : $initialPairs),
            referenceAnswer: @js(old('reference_answer', $question->questionable?->reference_answer ?? '')),
            evaluationRubric: @js(old('evaluation_rubric', $question->questionable?->evaluation_rubric ?? '')),
            programmingLanguage: @js(old('programming_language', $question->questionable?->programming_language ?? 'Python')),
            isFindError: @js((bool) old('is_find_error', $initialIsFindError)),
            instructions: @js(old('instructions', $question->questionable?->instructions ?? '')),
            starterCode: @js(old('starter_code', $question->questionable?->starter_code ?? '')),
            referenceSolution: @js(old('reference_solution', $question->questionable?->reference_solution ?? '')),
            defaultPoints: @js((float) old('default_points', $question->default_points)),
            defaultTime: @js((int) old('default_time_seconds', $question->default_time_seconds)),
            existingSubjects: @js($subjects->values()),
            suggestUrl: @js(route('questions.subjects.suggestions')),
            serverErrors: @js($errors->toArray()),
        })"
    >
        {{-- Cabecera institucional --}}
        <x-page-header
            title="Editar reactivo"
            :subtitle="$question->title"
            :breadcrumbs="[
                ['label' => 'Banco de preguntas', 'href' => route('questions.index')],
                ['label' => $question->title, 'href' => route('questions.show', $question)],
                ['label' => 'Editar'],
            ]"
        >
            <x-slot:badge>
                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'">
                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                </x-badge>
                @if ($isLinked)
                    <x-badge variant="warning">
                        Próxima versión v{{ $nextVersionNumber }}
                    </x-badge>
                @endif
            </x-slot:badge>

            <x-slot:actions>
                <x-button :href="route('questions.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>

                <x-button :href="route('questions.show', $question)" variant="neutral" size="md">
                    <x-icon name="view" class="w-4 h-4" />
                    Ver detalles
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Banner dinámico de Versionado Copy-on-Write --}}
        @if ($isLinked)
            <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-4 text-slate-800 flex items-start gap-3">
                <span class="w-9 h-9 rounded-lg bg-acode-warning/30 text-acode-primary flex items-center justify-center shrink-0">
                    <x-icon name="warning" class="w-5 h-5" />
                </span>
                <div class="space-y-1 text-xs">
                    <p class="font-semibold text-acode-primary text-sm">
                        Este reactivo está vinculado a exámenes: al guardar se creará la nueva versión <span class="font-mono px-1.5 py-0.5 rounded bg-acode-warning/40 text-acode-primary">v{{ $nextVersionNumber }}</span>
                    </p>
                    <p class="text-slate-700 leading-relaxed">
                        Los exámenes y resultados históricos conservarán intacto este reactivo original (<span class="font-mono font-semibold text-acode-primary">#{{ $question->id }}</span>), mientras que tus nuevos exámenes usarán automáticamente la versión <strong>v{{ $nextVersionNumber }}</strong>.
                    </p>
                </div>
            </div>
        @endif

        {{-- Resumen único de errores de validación --}}
        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm text-acode-danger" role="alert">
                <div class="flex items-start gap-2.5">
                    <x-icon name="error" class="w-5 h-5 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold">Revisa los siguientes datos antes de actualizar:</p>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-5 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form
            id="edit-question-form"
            method="POST"
            action="{{ route('questions.update', $question) }}"
            @keydown.enter="if ($event.target.tagName === 'INPUT' && !['submit', 'button'].includes($event.target.type)) { $event.preventDefault(); }"
            class="space-y-6"
        >
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0">
                    @include('questions._partials.form-fields', [
                        'mode' => 'edit',
                        'subjects' => $subjects,
                        'question' => $question,
                        'isLinkedToExams' => $isLinked,
                    ])
                </div>

                @include('questions._partials.student-preview')
            </div>

            {{-- Barra inferior de acciones normativas --}}
            <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <x-unsaved-changes form-id="edit-question-form" :cancel-url="route('questions.show', $question)" />

                <div class="flex items-center justify-end gap-2.5">
                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="save" class="w-4 h-4" />
                        {{ $isLinked ? 'Guardar como v' . $nextVersionNumber : 'Actualizar reactivo' }}
                    </x-button>
                </div>
            </div>
        </form>
    </div>
@endsection
