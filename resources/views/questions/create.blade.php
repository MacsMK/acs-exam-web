{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/create.blade.php (MPW-V2 §7, §8, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Nueva pregunta')

@section('content')
    @php
        $initialType = old('question_type', $defaultValues['question_type'] ?? 'multiple_choice');
        $initialOptions = old('options', ['', '']);
        $oldConcepts = old('concepts');
        $oldDefinitions = old('definitions');
        $initialPairs = ($oldConcepts !== null && $oldDefinitions !== null)
            ? collect($oldConcepts)->values()->map(static fn ($c, $i) => [
                'concept' => (string) $c,
                'definition' => (string) ($oldDefinitions[$i] ?? ''),
            ])->all()
            : [['concept' => '', 'definition' => ''], ['concept' => '', 'definition' => '']];
        $initialParaphrases = old('initial_paraphrases', []);
    @endphp

    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="acodeQuestionForm({
            formId: 'create-question-form',
            type: @js($initialType),
            title: @js(old('title', '')),
            subjectTag: @js(old('subject_tag', $defaultValues['subject_tag'] ?? '')),
            stem: @js(old('stem', '')),
            options: @js($initialOptions),
            correctOption: @js((int) old('correct_option_index', 0)),
            pairs: @js($initialPairs),
            referenceAnswer: @js(old('reference_answer', '')),
            evaluationRubric: @js(old('evaluation_rubric', '')),
            programmingLanguage: @js(old('programming_language', 'Python')),
            isFindError: @js((bool) old('is_find_error', false)),
            instructions: @js(old('instructions', '')),
            starterCode: @js(old('starter_code', '')),
            referenceSolution: @js(old('reference_solution', '')),
            defaultPoints: @js((float) old('default_points', $defaultValues['default_points'] ?? 1)),
            defaultTime: @js((int) old('default_time_seconds', $defaultValues['default_time_seconds'] ?? 60)),
            initialParaphrases: @js($initialParaphrases),
            hasParaphraseErrors: @js($errors->has('initial_paraphrases') || $errors->has('initial_paraphrases.*')),
            existingSubjects: @js($subjects->values()),
            suggestUrl: @js(route('questions.subjects.suggestions')),
            serverErrors: @js($errors->toArray()),
        })"
    >
        {{-- Navegación superior --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 select-none" aria-label="Migas de pan">
                <a href="{{ route('questions.index') }}" class="hover:text-acode-tech transition flex items-center gap-1 font-medium">
                    <x-icon name="arrowLeft" class="w-3.5 h-3.5" />
                    <span>Banco de preguntas</span>
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-700">Nueva pregunta</span>
            </nav>

            <div class="flex items-center gap-2">
                @if (!empty($quotaSummary))
                    <x-badge :variant="($quotaSummary['is_unlimited'] ?? false) ? 'success' : (($quotaSummary['remaining'] ?? 0) <= 5 ? 'warning' : 'info')">
                        @if ($quotaSummary['is_unlimited'] ?? false)
                            Plan {{ $quotaSummary['package_name'] ?? 'Pro' }} · Ilimitado
                        @else
                            Cuota: {{ $quotaSummary['used'] ?? 0 }} / {{ $quotaSummary['limit'] ?? 0 }} reactivos
                        @endif
                    </x-badge>
                @endif

                <x-button :href="route('questions.batch.create')" variant="neutral" size="sm">
                    <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                    Captura rápida en lote
                </x-button>
            </div>
        </div>

        {{-- Encabezado principal --}}
        <header>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Nueva pregunta</h1>
            <p class="mt-1 text-sm text-slate-500">
                Completa los tres pasos y verifica en la columna derecha cómo se mostrará el reactivo al estudiante.
            </p>
        </header>

        {{-- Resumen único de errores de validación --}}
        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm text-acode-danger" role="alert">
                <div class="flex items-start gap-2.5">
                    <x-icon name="error" class="w-5 h-5 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold">Revisa los siguientes datos antes de guardar:</p>
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
            id="create-question-form"
            method="POST"
            action="{{ route('questions.store') }}"
            @keydown.enter="if ($event.target.tagName === 'INPUT' && !['submit', 'button'].includes($event.target.type)) { $event.preventDefault(); }"
            class="space-y-6"
        >
            @csrf

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0">
                    @include('questions._partials.form-fields', [
                        'mode' => 'create',
                        'subjects' => $subjects,
                        'defaultValues' => $defaultValues ?? [],
                    ])
                </div>

                @include('questions._partials.student-preview')
            </div>

            {{-- Barra inferior de acciones normativas --}}
            <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-xs">
                <x-unsaved-changes form-id="create-question-form" :cancel-url="route('questions.index')" />

                <div class="flex flex-wrap items-center justify-end gap-2.5">
                    <x-button type="submit" name="save_and_new" value="1" variant="secondary" size="md">
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        Guardar y crear otra
                    </x-button>

                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="save" class="w-4 h-4 mr-1.5" />
                        Guardar reactivo
                    </x-button>
                </div>
            </div>
        </form>
    </div>
@endsection
