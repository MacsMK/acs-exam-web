{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/assemble.blade.php (MPW-V2 §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', 'Editar examen: '.$exam->title)

@section('content')
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-acode-primary">{{ $exam->title }}</h1>
        @if ($exam->is_published)
            <x-badge variant="accent">Publicado</x-badge>
        @else
            <x-badge variant="warning">Borrador</x-badge>
        @endif
    </div>
    <p class="text-sm text-slate-500 mb-6">{{ $exam->description }}</p>

    <h2 class="text-lg font-semibold text-acode-primary mb-3">Apartados</h2>

    @forelse ($exam->sections as $section)
        <x-card class="mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-800">{{ $section->title }}</h3>
                <span class="text-xs text-slate-500">{{ $section->exam_questions_count ?? $section->examQuestions->count() }} / {{ $section->requested_quantity }} reactivos</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($section->examQuestions as $examQuestion)
                    <li class="py-2 flex items-center justify-between text-sm">
                        <span>{{ $examQuestion->question->title }}</span>
                        <form method="POST" action="{{ route('exams.questions.reshuffle', [$exam, $examQuestion]) }}">
                            @csrf
                            <button type="submit" class="text-acode-tech text-xs font-medium hover:underline">Actualizar reactivo</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2">
                        <x-empty-state
                            class="p-4"
                            message="Este apartado aún no tiene reactivos sorteados."
                        />
                    </li>
                @endforelse
            </ul>
        </x-card>
    @empty
        <x-empty-state
            class="mb-4"
            message="Este examen todavía no tiene apartados."
        />
    @endforelse

    <x-card class="mb-6 max-w-lg">
        <h3 class="font-semibold text-slate-800 mb-3">Crear apartado (sorteo automático)</h3>
        <form method="POST" action="{{ route('exams.sections.store', $exam) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Título del apartado</label>
                <input type="text" name="title" required maxlength="100"
                       placeholder="Ej. Opción múltiple — Parcial 1"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-800 mb-1">Tipo de reactivo</label>
                    <select name="question_type" required
                            class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        <option value="multiple_choice">Opción múltiple</option>
                        <option value="matching">Relación de columnas</option>
                        <option value="open">Pregunta abierta</option>
                        <option value="code">Código</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-800 mb-1">Cantidad a sortear</label>
                    <input type="number" min="1" name="requested_quantity" value="5" required
                           class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
            </div>
            <p class="text-xs text-slate-500">
                Se sortean reactivos de tu banco que coincidan con el tipo elegido. Asegúrate de tener suficientes creados.
            </p>
            <x-button type="submit" variant="secondary">
                Guardar apartado
            </x-button>
        </form>
    </x-card>

    @unless ($exam->is_published)
        <form method="POST" action="{{ route('exams.publish', $exam) }}"
              onsubmit="return confirm('¿Publicar el examen? Los alumnos de los grupos asignados podrán presentarlo.');">
            @csrf
            <x-button type="submit" variant="accent">
                Publicar examen
            </x-button>
        </form>
    @endunless
@endsection
