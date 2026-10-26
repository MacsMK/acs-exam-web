@extends('layouts.app')

@section('title', 'Presentando examen')

@section('content')
    @php
        $question = $current['question'];
    @endphp

    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-slate-500 uppercase tracking-wide">
                {{ $attempt->exam->title }} · Reactivo {{ $current['index'] + 1 }} de {{ $current['total'] }}
            </p>
            <div x-data="{
                    remaining: {{ (int) $current['time_limit_seconds'] }},
                    tick() {
                        if (this.remaining > 0) {
                            this.remaining--;
                        } else {
                            this.$refs.answerForm.requestSubmit();
                        }
                    },
                 }"
                 x-init="setInterval(() => tick(), 1000)"
                 class="font-mono text-sm px-3 py-1 rounded-md"
                 :class="remaining <= 10 ? 'bg-acode-danger/10 text-acode-danger' : 'bg-acode-surface text-acode-primary'">
                <span x-text="Math.floor(remaining / 60).toString().padStart(2,'0') + ':' + (remaining % 60).toString().padStart(2,'0')"></span>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-6 mb-4">
            <h2 class="font-semibold text-slate-800 mb-1">{{ $question['title'] }}</h2>
            <p class="text-sm text-slate-700 mb-5 whitespace-pre-line">{{ $question['stem'] }}</p>

            <form method="POST" action="{{ route('attempts.answer', $attempt) }}" x-ref="answerForm" class="space-y-3">
                @csrf
                <input type="hidden" name="exam_question_id" value="{{ $current['exam_question_id'] }}">

                @switch($question['question_type'])
                    @case('multiple_choice')
                        @foreach ($question['options'] as $option)
                            <label class="flex items-center gap-3 border border-slate-200 rounded-md px-4 py-3 text-sm hover:bg-blue-50/70 cursor-pointer">
                                <input type="radio" name="selected_option_index" value="{{ $option['index'] }}" required>
                                {{ $option['text'] }}
                            </label>
                        @endforeach
                        @break

                    @case('matching')
                        <div class="space-y-3">
                            @foreach ($question['left_items'] as $i => $left)
                                <div class="flex items-center gap-3">
                                    <span class="flex-1 text-sm font-medium text-slate-800">{{ $left['text'] }}</span>
                                    <input type="hidden" name="pairs[{{ $i }}][left_id]" value="{{ $left['id'] }}">
                                    <select name="pairs[{{ $i }}][right_id]" required
                                            class="flex-1 h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                                        <option value="">Selecciona…</option>
                                        @foreach ($question['right_items'] as $right)
                                            <option value="{{ $right['id'] }}">{{ $right['text'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        @break

                    @case('open')
                        <textarea name="answer_text" rows="6" required
                                  class="w-full px-3 py-2 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                  placeholder="Escribe tu respuesta…"></textarea>
                        @break

                    @case('code')
                        <p class="text-xs text-slate-500 mb-2">{{ $question['instructions'] }}</p>
                        <textarea name="answer_text" rows="10" required
                                  class="w-full px-3 py-2 rounded-md border border-slate-300 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">{{ $question['starter_code'] }}</textarea>
                        @break
                @endswitch

                <button type="submit" class="h-10 px-4 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
                    Siguiente reactivo →
                </button>
            </form>
        </div>

        <form method="POST" action="{{ route('attempts.finish', $attempt) }}"
              onsubmit="return confirm('¿Terminar el examen ahora? Las preguntas sin responder no obtendrán puntos.');">
            @csrf
            <button type="submit" class="text-xs text-acode-danger font-medium">Terminar examen antes de tiempo</button>
        </form>
    </div>
@endsection
