{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/index.blade.php (MPW-V2 §2.4, §5.2.2, §7.3, §7.8.2, §8.4, §8.7, §8.8, Tabla 20, Tabla 31) --}}
@extends('layouts.app')

@section('title', 'Banco de Reactivos')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Banco de Reactivos</h1>
            <p class="text-sm text-slate-500 mt-0.5">Repositorio institucional de preguntas polimórficas con soporte de paráfrasis anti-copia.</p>
        </div>

        <x-button :href="route('questions.create')" variant="primary" size="md">
            <x-icon name="add" class="w-4 h-4 mr-1.5" />
            Crear reactivo
        </x-button>
    </div>

    {{-- Barra de Búsqueda Predictiva con Debounce de 300 ms (MPW-V2 §8.8) --}}
    <x-search-filter
        :action="route('questions.index')"
        placeholder="Buscar reactivo por título, enunciado o materia…"
    />

    <x-table>
        <x-slot:header>
            <x-table.th>Título / Enunciado</x-table.th>
            <x-table.th>Tipo de Reactivo</x-table.th>
            <x-table.th>Materia</x-table.th>
            <x-table.th align="center">Puntos</x-table.th>
            <x-table.th align="center">Paráfrasis</x-table.th>
            <x-table.th align="right">Acciones</x-table.th>
        </x-slot:header>

        @forelse ($questions as $question)
            <tr>
                <x-table.td>
                    <p class="font-bold text-slate-800 leading-snug">{{ $question->title }}</p>
                    <p class="text-xs text-slate-500 line-clamp-1 mt-0.5">{{ $question->stem }}</p>
                </x-table.td>
                <x-table.td>
                    @php
                        $qType = $question->question_type ?? $question->type;
                    @endphp
                    @switch($qType)
                        @case('multiple_choice')
                            <x-badge variant="info">Opción múltiple</x-badge>
                            @break
                        @case('matching')
                            <x-badge variant="accent">Relación de columnas</x-badge>
                            @break
                        @case('open')
                            <x-badge variant="warning">Pregunta abierta</x-badge>
                            @break
                        @case('code')
                            <x-badge variant="primary">Código fuente</x-badge>
                            @break
                        @default
                            <x-badge variant="neutral">{{ $qType }}</x-badge>
                    @endswitch
                </x-table.td>
                <x-table.td>
                    <span class="text-xs text-slate-600 font-medium">{{ $question->subject_tag ?? 'General' }}</span>
                </x-table.td>
                <x-table.td align="center">
                    <span class="font-mono font-semibold text-slate-800">{{ number_format((float) $question->default_points, 1) }}</span>
                </x-table.td>
                <x-table.td align="center">
                    <span class="inline-flex items-center gap-1 font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                        {{ $question->paraphrases_count ?? $question->paraphrases->count() }}
                    </span>
                </x-table.td>
                <x-table.td align="right">
                    <form
                        id="paraphrase-form-{{ $question->id }}"
                        method="POST"
                        action="{{ route('questions.paraphrase.store', $question) }}"
                        class="inline"
                    >
                        @csrf
                        <input type="hidden" name="stem" value="">
                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            onclick="promptParaphrase(document.getElementById('paraphrase-form-{{ $question->id }}'), {{ $question->id }})"
                        >
                            <x-icon name="edit" class="w-3.5 h-3.5 mr-1 text-acode-tech" />
                            Crear paráfrasis
                        </x-button>
                    </form>
                </x-table.td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="p-0">
                    <x-empty-state
                        class="border-0 shadow-none rounded-none py-10"
                        icon="edit"
                        title="Banco de reactivos vacío"
                        message="Todavía no cuentas con reactivos registrados. Da de alta tu primer reactivo."
                    >
                        <x-button :href="route('questions.create')" variant="primary" size="sm">
                            <x-icon name="add" class="w-4 h-4 mr-1.5" />
                            Crear reactivo
                        </x-button>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $questions->links() }}
    </div>

    @push('scripts')
        <script>
            function promptParaphrase(form, questionId) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.prompt({
                        title: 'Crear paráfrasis anti-copia',
                        text: 'Ingresa el enunciado alternativo pedagógicamente equivalente para mitigar copias entre alumnos:',
                        inputPlaceholder: 'Escribe aquí la redacción alternativa…',
                        onConfirm: function(stem) {
                            if (stem && stem.trim().length > 0) {
                                form.querySelector('input[name="stem"]').value = stem.trim();
                                form.submit();
                            }
                        }
                    });
                    return false;
                }

                const fallbackStem = window.prompt('Redacción alternativa (misma respuesta, distinto enunciado):');
                if (fallbackStem && fallbackStem.trim().length > 0) {
                    form.querySelector('input[name="stem"]').value = fallbackStem.trim();
                    form.submit();
                }
                return false;
            }
        </script>
    @endpush
@endsection
