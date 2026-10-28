{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/take-exam.blade.php (MPW-V2 §5.5, §7.5.1, §7.7, §7.8) --}}
@extends('layouts.exam-runner')

@section('title', 'Presentando examen')

@section('content')
    <div class="max-w-2xl mx-auto"
         x-data="{
            remaining: {{ (int) $current['time_limit_seconds'] }},
            timerId: null,
            submitting: false,
            startTimer() {
                this.timerId = setInterval(() => this.tick(), 1000);
            },
            tick() {
                if (this.remaining > 0) {
                    this.remaining--;
                }
                if (this.remaining <= 0 && !this.submitting) {
                    this.submitting = true;
                    clearInterval(this.timerId);
                    const form = this.$refs.answerForm;
                    if (form) {
                        form.querySelectorAll('[required]').forEach((el) => el.removeAttribute('required'));
                        form.submit();
                    }
                }
            },
         }"
         x-init="startTimer()">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-slate-500 uppercase tracking-wide">
                {{ $attempt->exam->title }} · Reactivo {{ $current['index'] + 1 }} de {{ $current['total'] }}
            </p>
            <div class="font-mono text-sm px-3 py-1 rounded-lg font-semibold"
                 :class="remaining <= 10 ? 'bg-acode-danger/10 text-acode-danger' : 'bg-acode-surface text-acode-primary'">
                <span x-text="Math.floor(remaining / 60).toString().padStart(2,'0') + ':' + (remaining % 60).toString().padStart(2,'0')"></span>
            </div>
        </div>

        <x-card class="mb-4">
            <h2 class="font-semibold text-slate-800 mb-1">{{ $current['question']['title'] }}</h2>
            <p class="text-sm text-slate-700 mb-5 whitespace-pre-line">{{ $current['question']['stem'] }}</p>

            <form method="POST" action="{{ route('attempts.answer', $attempt) }}" x-ref="answerForm" class="space-y-3">
                @csrf
                <input type="hidden" name="exam_question_id" value="{{ $current['exam_question_id'] }}">

                @switch($current['question']['question_type'])
                    @case('multiple_choice')
                        @forelse ($current['question']['options'] as $option)
                            <label class="flex items-center gap-3 border border-slate-200 rounded-lg px-4 py-3 text-sm hover:bg-acode-tech/5 cursor-pointer">
                                <input type="radio" name="selected_option_index" value="{{ $option['index'] }}" required>
                                {{ $option['text'] }}
                            </label>
                        @empty
                            <x-empty-state message="Sin opciones configuradas." />
                        @endforelse
                        @break

                    @case('matching')
                        <div class="space-y-3">
                            @forelse ($current['question']['left_items'] as $i => $left)
                                <div class="flex items-center gap-3">
                                    <span class="flex-1 text-sm font-medium text-slate-800">{{ $left['text'] }}</span>
                                    <input type="hidden" name="pairs[{{ $i }}][left_id]" value="{{ $left['id'] }}">
                                    <select name="pairs[{{ $i }}][right_id]" required
                                            class="flex-1 h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                                        <option value="">Selecciona…</option>
                                        @forelse ($current['question']['right_items'] as $right)
                                            <option value="{{ $right['id'] }}">{{ $right['text'] }}</option>
                                        @empty
                                            <option value="" disabled>Sin definiciones</option>
                                        @endforelse
                                    </select>
                                </div>
                            @empty
                                <x-empty-state message="Sin pares configurados." />
                            @endforelse
                        </div>
                        @break

                    @case('open')
                        <textarea name="answer_text" rows="6" required
                                  class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                  placeholder="Escribe tu respuesta…"></textarea>
                        @break

                    @case('code')
                        <p class="text-xs text-slate-500 mb-2">{{ $current['question']['instructions'] }}</p>
                        <p class="text-xs text-acode-warning bg-acode-warning/15 border border-acode-warning/30 rounded-lg px-3 py-1.5 mb-2">
                            Por integridad académica, el pegado de código externo está deshabilitado en este editor.
                        </p>
                        <textarea name="answer_text" rows="10" required
                                  @paste.prevent
                                  onpaste="return false;"
                                  class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ $current['question']['starter_code'] }}</textarea>
                        @break
                @endswitch

                <x-button type="submit" variant="primary">
                    Enviar respuesta
                </x-button>
            </form>
        </x-card>

        <form method="POST" action="{{ route('attempts.finish', $attempt) }}"
              onsubmit="return confirm('¿Finalizar el examen ahora? Las preguntas sin responder no obtendrán puntos.');">
            @csrf
            <button type="submit" class="text-xs text-acode-danger font-medium hover:underline">Finalizar examen</button>
        </form>
    </div>

    @push('scripts')
        <script>
            history.pushState(null, '', location.href);
            window.addEventListener('popstate', () => {
                history.pushState(null, '', location.href);
            });

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    console.warn('A-Code Anti-Cheat: Cambio de pestaña o ventana detectado.');
                }
            });
        </script>
    @endpush
@endsection
