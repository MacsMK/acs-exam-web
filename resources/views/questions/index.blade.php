{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/index.blade.php (MPW-V2 §2.4, §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', 'Banco de reactivos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-acode-primary">Banco de reactivos</h1>
        <x-button :href="route('questions.create')" variant="primary">
            Crear reactivo
        </x-button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-4">
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
                @forelse ($questions as $question)
                    <tr class="even:bg-acode-surface hover:bg-acode-tech/5">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $question->title }}</td>
                        <td class="px-4 py-3">
                            {{ match ($question->question_type ?? $question->type) {
                                'multiple_choice' => 'Opción múltiple',
                                'matching' => 'Relación de columnas',
                                'open' => 'Pregunta abierta',
                                'code' => 'Código',
                                default => $question->question_type ?? $question->type,
                            } }}
                        </td>
                        <td class="px-4 py-3">{{ $question->subject_tag ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $question->default_points }}</td>
                        <td class="px-4 py-3">{{ $question->paraphrases_count ?? $question->paraphrases->count() }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('questions.paraphrase.store', $question) }}"
                                  onsubmit="return promptParaphrase(this, {{ $question->id }});" class="inline">
                                @csrf
                                <input type="hidden" name="stem" value="">
                                <button type="submit" class="text-acode-tech font-medium hover:underline">Crear paráfrasis</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-0">
                            <x-empty-state
                                class="border-0 shadow-none rounded-none"
                                message="Todavía no tienes reactivos. Crea el primero para poder armar exámenes."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $questions->links() }}

    @push('scripts')
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
    @endpush
@endsection
