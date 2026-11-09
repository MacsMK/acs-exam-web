@extends('layouts.app')

@section('title', 'Banco de reactivos')

@section('content')
<div class="mx-auto max-w-6xl space-y-6" x-data="{
    paraphraseModal: false,
    paraphraseAction: '',
    paraphraseTitle: '',
    paraphraseStem: '',
    openParaphrase(url, title) {
        this.paraphraseAction = url;
        this.paraphraseTitle = title;
        this.paraphraseStem = '';
        this.paraphraseModal = true;
    }
}">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">
                Banco de Preguntas
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Capture y administre reactivos educativos para componer sus exámenes contrarreloj.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <x-button variant="primary" href="{{ route('questions.create') }}">
                <x-icon name="add" class="w-4 h-4" />
                Crear reactivo
            </x-button>
        </div>
    </div>

    @php
        $typeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código / Prog.',
        ];

        $typeBadges = [
            'multiple_choice' => 'bg-acode-tech/15 text-acode-tech',
            'matching' => 'bg-acode-accent/15 text-acode-accent',
            'open' => 'bg-acode-warning/25 text-slate-800',
            'code' => 'bg-acode-primary/10 text-acode-primary',
        ];
    @endphp

    @if ($questions->isEmpty())
        <x-card>
            <x-empty-state
                title="Todavía no tienes reactivos en tu banco"
                description="Cree su primera pregunta de opción múltiple, abierta, de relación de columnas o de programación para componer sus pruebas."
            >
                <x-slot:action>
                    <x-button variant="primary" href="{{ route('questions.create') }}">
                        Crear primer reactivo
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        {{-- Tarjetas en Móvil (<768px) para evitar scroll horizontal --}}
        <div class="block md:hidden space-y-4">
            @foreach ($questions as $question)
                <x-card class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $typeBadges[$question->question_type] ?? 'bg-slate-100 text-slate-700' }}">
                            {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                        </span>
                        <span class="text-xs font-mono font-bold text-acode-primary bg-slate-100 px-2 py-0.5 rounded">
                            {{ $question->default_points }} pts
                        </span>
                    </div>

                    <h3 class="font-bold text-sm text-acode-primary">
                        {{ $question->title }}
                    </h3>

                    @if ($question->subject_tag)
                        <p class="text-xs text-slate-500">
                            Materia: <strong class="text-slate-700">{{ $question->subject_tag }}</strong>
                        </p>
                    @endif

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500">Paráfrasis: <strong>{{ $question->paraphrases()->count() }}</strong></span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="openParaphrase(@js(route('questions.paraphrase.store', $question)), @js($question->title))"
                                class="text-xs font-semibold text-acode-tech hover:underline"
                            >
                                + Paráfrasis
                            </button>
                            <x-button variant="neutral" size="sm" href="{{ route('questions.edit', $question) }}">
                                Editar
                            </x-button>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>

        {{-- Tabla en Desktop (>768px) --}}
        <x-card class="hidden md:block p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-acode-primary text-xs font-semibold uppercase text-white">
                        <tr>
                            <th class="px-5 py-4">Título del Reactivo</th>
                            <th class="px-4 py-4">Tipo</th>
                            <th class="px-4 py-4">Materia</th>
                            <th class="px-4 py-4">Puntos</th>
                            <th class="px-4 py-4">Paráfrasis</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($questions as $question)
                            <tr class="hover:bg-blue-50/40 transition">
                                <td class="px-5 py-4 font-semibold text-acode-primary">
                                    {{ $question->title }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $typeBadges[$question->question_type] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    {{ $question->subject_tag ?? '—' }}
                                </td>
                                <td class="px-4 py-4 font-bold text-acode-primary">
                                    {{ $question->default_points }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                        {{ $question->paraphrases()->count() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="openParaphrase(@js(route('questions.paraphrase.store', $question)), @js($question->title))"
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-acode-tech hover:underline px-2 py-1"
                                        >
                                            + Paráfrasis
                                        </button>

                                        <x-button variant="neutral" size="sm" href="{{ route('questions.edit', $question) }}">
                                            Editar
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($questions->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $questions->links() }}
                </div>
            @endif
        </x-card>
    @endif

    {{-- Modal Institucional de Paráfrasis (Sustituye a window.prompt, ADR-023) --}}
    <div
        x-show="paraphraseModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        @keydown.escape.window="paraphraseModal = false"
    >
        <div
            class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 transform transition-all duration-200"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="paraphraseModal = false"
        >
            <div class="flex items-start justify-between border-b border-slate-100 pb-3 mb-4">
                <div>
                    <h3 class="text-lg font-bold text-acode-primary">Agregar Paráfrasis Anti-Copia</h3>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-1" x-text="'Reactivo: ' + paraphraseTitle"></p>
                </div>
                <button type="button" @click="paraphraseModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form :action="paraphraseAction" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="paraphrase-stem" class="block text-xs font-semibold text-slate-700 mb-1">
                        Redacción alternativa (misma respuesta, distinto enunciado):
                    </label>
                    <textarea
                        id="paraphrase-stem"
                        name="stem"
                        x-model="paraphraseStem"
                        rows="3"
                        required
                        maxlength="500"
                        placeholder="Escriba la variante del enunciado..."
                        class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:border-acode-tech focus:ring-2 focus:ring-acode-tech"
                    ></textarea>
                    <p class="text-[11px] text-slate-400 text-right mt-1" x-text="paraphraseStem.length + '/500 caracteres'"></p>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <x-button variant="neutral" size="sm" type="button" @click="paraphraseModal = false">
                        Cancelar
                    </x-button>
                    <x-button variant="primary" size="sm" type="submit" x-bind:disabled="!paraphraseStem.trim()">
                        Guardar paráfrasis
                    </x-button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
