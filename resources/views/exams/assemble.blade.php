{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/assemble.blade.php (MPW-V2 §5.2.2, §7.3, §7.8.2, §8.4, §8.7, Tabla 20, Tabla 28) --}}
@extends('layouts.app')

@section('title', 'Editar Examen: ' . $exam->title)

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">{{ $exam->title }}</h1>
            @if ($exam->is_published)
                <x-badge variant="accent">Publicado</x-badge>
            @else
                <x-badge variant="warning">Borrador</x-badge>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <x-button :href="route('exams.index')" variant="neutral" size="sm">
                <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90 mr-1" />
                Volver
            </x-button>
        </div>
    </div>
    <p class="text-sm text-slate-500 mb-6">{{ $exam->description ?? 'Sin descripción adicional' }}</p>

    {{-- Lista de Apartados y Reactivos Sorteados --}}
    <div class="space-y-4 mb-8">
        <h2 class="text-lg font-bold text-acode-primary tracking-tight">Apartados y Reactivos Asignados</h2>

        @forelse ($exam->sections as $section)
            <x-card class="border-slate-200">
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-base text-acode-primary">{{ $section->title }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Tipo de reactivo: <strong class="text-slate-700">{{ $section->question_type }}</strong>
                        </p>
                    </div>
                    <x-badge variant="info">
                        {{ $section->exam_questions_count ?? $section->examQuestions->count() }} / {{ $section->requested_quantity }} reactivos sorteados
                    </x-badge>
                </div>

                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($section->examQuestions as $examQuestion)
                        <li class="py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech">
                                    {{ $loop->iteration }}
                                </span>
                                <span class="font-medium text-slate-800">{{ $examQuestion->question->title }}</span>
                            </div>

                            @unless ($exam->is_published)
                                <form method="POST" action="{{ route('exams.questions.reshuffle', [$exam, $examQuestion]) }}">
                                    @csrf
                                    <x-button type="submit" variant="neutral" size="sm">
                                        <x-icon name="edit" class="w-3.5 h-3.5 mr-1 text-acode-tech" />
                                        Actualizar reactivo
                                    </x-button>
                                </form>
                            @endunless
                        </li>
                    @empty
                        <li class="py-3">
                            <x-empty-state
                                class="p-4 border-0 shadow-none bg-slate-50"
                                message="Este apartado aún no tiene reactivos sorteados del banco."
                            />
                        </li>
                    @endforelse
                </ul>
            </x-card>
        @empty
            <x-empty-state
                icon="clock"
                title="Sin apartados configurados"
                message="Este examen todavía no cuenta con secciones. Configura un apartado para sortear preguntas de tu banco."
            />
        @endforelse
    </div>

    {{-- Formulario para Agregar Nuevo Apartado (Sorteo Algorítmico) --}}
    @unless ($exam->is_published)
        <x-card class="mb-8 max-w-xl">
            <h3 class="font-bold text-base text-acode-primary mb-3 flex items-center gap-2">
                <x-icon name="add" class="w-5 h-5 text-acode-tech" />
                <span>Crear Apartado (Sorteo Algorítmico Automático)</span>
            </h3>

            <form method="POST" action="{{ route('exams.sections.store', $exam) }}" class="space-y-4">
                @csrf

                <x-form.group name="title" label="Título del apartado" required>
                    <x-form.input
                        type="text"
                        name="title"
                        required
                        maxlength="100"
                        placeholder="Ej. Sección 1: Preguntas de Opción Múltiple"
                    />
                </x-form.group>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-form.group name="question_type" label="Tipo de reactivo a sortear" required>
                        <x-form.select name="question_type" required>
                            <option value="multiple_choice">Opción múltiple</option>
                            <option value="matching">Relación de columnas</option>
                            <option value="open">Pregunta abierta</option>
                            <option value="code">Código de programación</option>
                        </x-form.select>
                    </x-form.group>

                    <x-form.group name="requested_quantity" label="Cantidad a sortear" required>
                        <x-form.input
                            type="number"
                            min="1"
                            name="requested_quantity"
                            value="5"
                            required
                        />
                    </x-form.group>
                </div>

                <p class="text-xs text-slate-500">
                    El motor de ensamblado seleccionará reactivos de tu banco que coincidan con el tipo especificado asegurando cobertura equitativa.
                </p>

                <div class="pt-2">
                    <x-button type="submit" variant="secondary" size="md">
                        <x-icon name="save" class="w-4 h-4 mr-1.5" />
                        Guardar apartado
                    </x-button>
                </div>
            </form>
        </x-card>

        {{-- Publicación de Examen con Confirmación SweetAlert2 (MPW-V2 §8.7, Tabla 20) --}}
        <div class="pt-4 border-t border-slate-200">
            <form
                id="publish-exam-form"
                method="POST"
                action="{{ route('exams.publish', $exam) }}"
                x-data="{
                    confirmPublish() {
                        if (typeof AcodeAlert !== 'undefined') {
                            AcodeAlert.confirm({
                                title: '¿Publicar examen evaluador?',
                                text: 'Los alumnos pertenecientes a los grupos asignados podrán comenzar a presentar la evaluación en las fechas programadas.',
                                confirmButtonText: 'Publicar examen',
                                cancelButtonText: 'Cancelar',
                                isDanger: false,
                                onConfirm: () => document.getElementById('publish-exam-form').submit()
                            });
                        } else {
                            if (confirm('¿Publicar el examen?')) {
                                document.getElementById('publish-exam-form').submit();
                            }
                        }
                    }
                }"
            >
                @csrf
                <x-button
                    type="button"
                    variant="accent"
                    size="md"
                    @click="confirmPublish()"
                >
                    <x-icon name="check" class="w-4 h-4 mr-1.5" />
                    Publicar examen
                </x-button>
            </form>
        </div>
    @endunless
@endsection
