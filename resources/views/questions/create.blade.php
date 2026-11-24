{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/create.blade.php (MPW-V3 §7, §8, Tabla 10) --}}
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
        class="space-y-6"
        x-data="acodeQuestionForm({
            formId: 'create-question-form',
            type: @js($initialType),
            title: @js(old('title', '')),
            subjectTag: @js(old('subject_tag', $defaultValues['subject_tag'] ?? '')),
            stem: @js(old('stem', '')),
            options: @js($initialOptions),
            correctOption: @js((int) (old('correct_option_index', $errors->has('correct_option_index') ? -1 : 0) ?? -1)),
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
        {{-- Cabecera institucional --}}
        <x-page-header
            title="Crear reactivo"
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Banco de preguntas', 'href' => route('questions.index')],
                ['label' => 'Crear reactivo'],
            ]"
        >
            @if (!empty($quotaSummary))
                <x-slot:badge>
                    <x-badge :variant="($quotaSummary['is_unlimited'] ?? false) ? 'success' : (($quotaSummary['remaining'] ?? 0) <= 5 ? 'warning' : 'info')">
                        @if ($quotaSummary['is_unlimited'] ?? false)
                            Plan {{ $quotaSummary['package_name'] ?? 'Pro' }} · Ilimitado
                        @else
                            Cuota: {{ $quotaSummary['used'] ?? 0 }} / {{ $quotaSummary['limit'] ?? 0 }} reactivos
                        @endif
                    </x-badge>
                </x-slot:badge>
            @endif

            <x-slot:actions>
                <x-button :href="route('questions.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>

                <x-button :href="route('questions.batch.create')" variant="neutral" size="md" data-cancel-form="create-question-form">
                    <x-icon name="add" class="w-4 h-4 text-acode-tech" />
                    Crear en lote
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Resumen único de errores de validación --}}
        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm text-acode-danger-dark" role="alert">
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

        {{-- formdata conserva la acción si el botón se deshabilita antes de serializar el formulario. --}}
        <form
            id="create-question-form"
            method="POST"
            enctype="multipart/form-data"
            action="{{ route('questions.store') }}"
            x-data="{ saving: null }"
            @submit="
                if (saving) {
                    $event.preventDefault();
                } else {
                    saving = $event.submitter?.name === 'save_and_new' ? 'another' : 'finish';
                }
            "
            @formdata="if (saving === 'another') $event.formData.set('save_and_new', '1')"
            @pageshow.window="saving = null"
            @keydown.enter="if ($event.target.tagName === 'INPUT' && !['submit', 'button'].includes($event.target.type)) { $event.preventDefault(); }"
            class="space-y-6 [&_:is(input,textarea):enabled::placeholder]:text-slate-500"
        >
            @csrf

            <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
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
            <div class="flex flex-col-reverse sm:flex-row sm:items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <x-unsaved-changes form-id="create-question-form" :cancel-url="route('questions.index')" />

                <div class="space-y-2 sm:text-right">
                    <div class="flex flex-wrap items-center gap-2.5 sm:justify-end">
                        <x-button type="submit" name="save_and_new" value="1" variant="secondary" size="md"
                            aria-describedby="save-and-new-help" loading-when="saving === 'another'" ::disabled="Boolean(saving)">
                            <x-icon name="add" class="w-4 h-4" x-show="saving !== 'another'" />
                            <span x-text="saving === 'another' ? 'Guardando…' : 'Guardar y crear otro'">Guardar y crear otro</span>
                        </x-button>

                        <x-button type="submit" variant="primary" size="md" loading-when="saving === 'finish'" ::disabled="Boolean(saving)">
                            <x-icon name="save" class="w-4 h-4" x-show="saving !== 'finish'" />
                            <span x-text="saving === 'finish' ? 'Guardando…' : 'Guardar reactivo'">Guardar reactivo</span>
                        </x-button>
                    </div>
                    <p id="save-and-new-help" class="text-xs text-slate-600">
                        Al crear otro se conservan el tipo, la materia, el puntaje y el tiempo sugerido.
                    </p>
                </div>
                <p class="sr-only" role="status" x-text="saving ? 'Guardando…' : ''"></p>
            </div>
        </form>
    </div>
@endsection
