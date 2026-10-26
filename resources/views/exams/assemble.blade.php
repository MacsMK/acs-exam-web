@extends('layouts.app')

@section('title', 'Armar: '.$exam->title)

@section('content')
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-acode-primary">{{ $exam->title }}</h1>
        @if ($exam->is_published)
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-acode-accent/15 text-acode-accent">Publicado</span>
        @else
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-acode-warning/20 text-acode-primary">Borrador</span>
        @endif
    </div>
    <p class="text-sm text-slate-500 mb-6">{{ $exam->description }}</p>

    <h2 class="text-lg font-semibold text-acode-primary mb-3">Apartados (RW-12)</h2>

    @forelse ($exam->sections as $section)
        <div class="bg-white rounded-lg border border-slate-200 p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-800">{{ $section->title }}</h3>
                <span class="text-xs text-slate-500">{{ $section->examQuestions->count() }} / {{ $section->requested_quantity }} reactivos</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($section->examQuestions as $examQuestion)
                    <li class="py-2 flex items-center justify-between text-sm">
                        <span>{{ $examQuestion->question->title }}</span>
                        <form method="POST" action="{{ route('exams.questions.reshuffle', [$exam, $examQuestion]) }}">
                            @csrf
                            <button type="submit" class="text-acode-tech text-xs font-medium">Nuevo sorteo (RW-14)</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="bg-white rounded-lg border border-slate-200 p-6 text-sm text-slate-500 mb-4">
            Este examen todavía no tiene apartados.
        </div>
    @endforelse

    <div class="bg-white rounded-lg border border-slate-200 p-5 mb-6 max-w-lg">
        <h3 class="font-semibold text-slate-800 mb-3">+ Nuevo apartado (sorteo automático, RW-13)</h3>
        <form method="POST" action="{{ route('exams.sections.store', $exam) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Título del apartado</label>
                <input type="text" name="title" required maxlength="100"
                       placeholder="Ej. Opción múltiple — Parcial 1"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Tipo de reactivo</label>
                    <select name="question_type" required
                            class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        <option value="multiple_choice">Opción múltiple</option>
                        <option value="matching">Relación de columnas</option>
                        <option value="open">Pregunta abierta</option>
                        <option value="code">Código</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Cantidad a sortear</label>
                    <input type="number" min="1" name="requested_quantity" value="5" required
                           class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                </div>
            </div>
            <p class="text-xs text-slate-500">
                Se sortean reactivos de tu banco que coincidan con el tipo elegido. Asegúrate de tener suficientes creados.
            </p>
            <button type="submit" class="h-10 px-4 rounded-md bg-acode-tech text-white text-sm font-semibold hover:bg-[#1d4f85]">
                Armar apartado
            </button>
        </form>
    </div>

    @unless ($exam->is_published)
        <form method="POST" action="{{ route('exams.publish', $exam) }}"
              onsubmit="return confirm('¿Publicar el examen? Los alumnos de los grupos asignados podrán presentarlo.');">
            @csrf
            <button type="submit" class="h-10 px-4 rounded-md bg-acode-accent text-white text-sm font-semibold hover:opacity-90">
                Publicar examen
            </button>
        </form>
    @endunless
@endsection
