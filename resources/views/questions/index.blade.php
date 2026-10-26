@extends('layouts.app')

@section('title', 'Banco de reactivos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-acode-primary">Banco de reactivos</h1>
        <a href="{{ route('questions.create') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            + Nuevo reactivo
        </a>
    </div>

    @php
        $typeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código',
        ];
    @endphp

    @if ($questions->isEmpty())
        <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-500">
            Todavía no tienes reactivos. Crea el primero para poder armar exámenes.
        </div>
    @else
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-acode-primary text-white text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Título</th>
                        <th class="text-left px-4 py-3">Tipo</th>
                        <th class="text-left px-4 py-3">Materia</th>
                        <th class="text-left px-4 py-3">Puntos</th>
                        <th class="text-left px-4 py-3">Paráfrasis</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($questions as $question)
                        <tr class="even:bg-acode-surface hover:bg-blue-50/70">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $question->title }}</td>
                            <td class="px-4 py-3">{{ $typeLabels[$question->question_type] ?? $question->question_type }}</td>
                            <td class="px-4 py-3">{{ $question->subject_tag ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $question->default_points }}</td>
                            <td class="px-4 py-3">{{ $question->paraphrases()->count() }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('questions.paraphrase.store', $question) }}"
                                      onsubmit="return promptParaphrase(this, {{ $question->id }});" class="inline">
                                    @csrf
                                    <input type="hidden" name="stem" value="">
                                    <button type="submit" class="text-acode-tech font-medium">+ Paráfrasis</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $questions->links() }}
    @endif

    <script>
        function promptParaphrase(form, questionId) {
            const stem = window.prompt('Redacción alternativa (misma respuesta, distinto enunciado) — anti-copia:');
            if (!stem) {
                return false;
            }
            form.querySelector('input[name="stem"]').value = stem;
            return true;
        }
    </script>
@endsection
