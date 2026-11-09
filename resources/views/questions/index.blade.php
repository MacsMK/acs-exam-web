@extends('layouts.app')

@section('title', 'Banco de reactivos')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">

    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech hover:underline">
        <span aria-hidden="true">←</span>
        Volver a Inicio
    </a>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">
                Banco de preguntas
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Consulta y administra las preguntas que utilizarás en tus exámenes.
            </p>
        </div>

        <a href="{{ route('questions.create') }}"
           class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-acode-primary px-5 text-sm font-semibold text-white hover:opacity-90">
            <span aria-hidden="true">+</span>
            Nuevo reactivo
        </a>
    </div>

    @php
        $typeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código',
        ];

        $typeColors = [
            'multiple_choice' => 'bg-blue-50 text-blue-700',
            'matching' => 'bg-teal-50 text-teal-700',
            'open' => 'bg-amber-50 text-amber-700',
            'code' => 'bg-violet-50 text-violet-700',
        ];
    @endphp

    @if ($questions->isEmpty())

        <div class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-acode-primary">
                ?
            </div>

            <h2 class="mt-4 text-lg font-semibold text-acode-primary">
                Todavía no tienes reactivos
            </h2>

            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Crea tu primera pregunta para comenzar a construir
                el banco de reactivos de tus exámenes.
            </p>

            <a href="{{ route('questions.create') }}"
               class="mt-6 inline-flex h-11 items-center justify-center rounded-lg bg-acode-primary px-5 text-sm font-semibold text-white hover:opacity-90">
                + Crear primer reactivo
            </a>
        </div>

    @else

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-acode-primary">
                        Mis reactivos
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Preguntas registradas en tu banco.
                    </p>
                </div>

                <span class="rounded-full bg-acode-tech/10 px-3 py-1 text-xs font-semibold text-acode-tech">
                    {{ $questions->total() }} reactivos
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[750px] text-left text-sm">

                    <thead class="bg-acode-primary text-xs font-semibold uppercase tracking-wide text-white">
                        <tr>
                            <th class="px-5 py-4">Título</th>
                            <th class="px-4 py-4">Tipo</th>
                            <th class="px-4 py-4">Materia</th>
                            <th class="px-4 py-4">Puntos</th>
                            <th class="px-4 py-4">Paráfrasis</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($questions as $question)
                            <tr class="transition-colors hover:bg-slate-50">

                                <td class="px-5 py-4">
                                    <span class="font-semibold text-acode-primary">
                                        {{ $question->title }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeColors[$question->question_type] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                    </span>
                                </td>

                                <td class="px-4 py-4 text-slate-600">
                                    {{ $question->subject_tag ?? '—' }}
                                </td>

                                <td class="px-4 py-4">
                                    <span class="font-semibold text-acode-primary">
                                        {{ $question->default_points }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        {{ $question->paraphrases()->count() }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <form method="POST"
                                          action="{{ route('questions.paraphrase.store', $question) }}"
                                          onsubmit="return promptParaphrase(this, {{ $question->id }});"
                                          class="inline">
                                        @csrf

                                        <input type="hidden" name="stem" value="">

                                        <button type="submit"
                                                class="inline-flex items-center gap-1 text-sm font-semibold text-acode-tech hover:underline">
                                            <span aria-hidden="true">+</span>
                                            Paráfrasis
                                        </button>
                                    </form>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </section>

        <div>
            {{ $questions->links() }}
        </div>

    @endif

</div>

<script>
    function promptParaphrase(form, questionId) {
        const stem = window.prompt(
            'Redacción alternativa (misma respuesta, distinto enunciado) — anti-copia:'
        );

        if (!stem) {
            return false;
        }

        form.querySelector('input[name="stem"]').value = stem;

        return true;
    }
</script>
@endsection