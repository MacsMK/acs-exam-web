{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/take-exam.blade.php (MPW-V2 §5.5, §7.3, §7.5.1, §7.7, §7.8.2, §8.9.1, ADR-020) --}}
@extends('layouts.exam-runner')

@section('title', 'Presentando examen · ' . $attempt->exam->title)

@section('content')
    <div class="max-w-2xl mx-auto">
        {{-- Barra de Progreso y Cronómetro Regresivo Contrarreloj --}}
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-200">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {{ $attempt->exam->title }}
                </p>
                <p class="text-sm font-bold text-acode-primary">
                    Reactivo {{ $current['index'] + 1 }} de {{ $current['total'] }}
                </p>
            </div>

            {{-- Cronómetro Alpine.js con aviso visual rojo en <=10s y auto-envío SweetAlert2 (ADR-020) --}}
            <x-exam.timer
                :seconds="(int) $current['remaining_seconds']"
                formRef="answerForm"
            />
        </div>

        {{-- Tarjeta Principal del Reactivo --}}
        <x-card class="mb-5 shadow-sm border-slate-200">
            <h2 class="text-xs font-bold uppercase tracking-wider text-acode-tech mb-3">
                Reactivo activo
            </h2>
            <div class="text-sm text-slate-700 mb-6 whitespace-pre-line leading-relaxed">
                {{ $current['question']['stem'] }}
            </div>

            {{-- Formulario de Respuesta por Tipo de Reactivo --}}
            <form
                method="POST"
                action="{{ route('attempts.answer', $attempt) }}"
                x-ref="answerForm"
                class="space-y-4"
            >
                @csrf
                <input type="hidden" name="exam_question_id" value="{{ $current['exam_question_id'] }}">

                @switch($current['question']['question_type'])
                    @case('multiple_choice')
                        <div class="space-y-2.5">
                            @forelse ($current['question']['options'] as $option)
                                <label class="flex items-center gap-3 border border-slate-200 rounded-xl px-4 py-3 text-sm hover:bg-acode-tech/5 hover:border-acode-tech/40 cursor-pointer transition">
                                    <input
                                        type="radio"
                                        name="selected_option_index"
                                        value="{{ $option['index'] }}"
                                        required
                                        class="h-4 w-4 text-acode-tech focus:ring-acode-tech border-slate-300"
                                    />
                                    <span class="text-slate-800 font-medium">{{ $option['text'] }}</span>
                                </label>
                            @empty
                                <x-empty-state message="Sin opciones configuradas en este reactivo." />
                            @endforelse
                        </div>
                        @break

                    @case('matching')
                        <div class="space-y-3">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                                Relaciona cada concepto con su definición correspondiente:
                            </p>
                            @forelse ($current['question']['left_items'] as $i => $left)
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                                    <span class="flex-1 text-sm font-semibold text-slate-800">{{ $left['text'] }}</span>
                                    <input type="hidden" name="pairs[{{ $i }}][left_id]" value="{{ $left['id'] }}">
                                    <div class="flex-1">
                                        <select
                                            name="pairs[{{ $i }}][right_id]"
                                            required
                                            class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                        >
                                            <option value="">Selecciona definición…</option>
                                            @forelse ($current['question']['right_items'] as $right)
                                                <option value="{{ $right['id'] }}">{{ $right['text'] }}</option>
                                            @empty
                                                <option value="" disabled>Sin definiciones</option>
                                            @endforelse
                                        </select>
                                    </div>
                                </div>
                            @empty
                                <x-empty-state message="Sin pares configurados en este reactivo." />
                            @endforelse
                        </div>
                        @break

                    @case('open')
                        <x-form.group name="answer_text" label="Tu respuesta analítica" required>
                            <x-form.textarea
                                name="answer_text"
                                rows="6"
                                required
                                placeholder="Escribe tu argumentación pedagógica aquí…"
                            ></x-form.textarea>
                        </x-form.group>
                        @break

                    @case('code')
                        <div class="space-y-2">
                            @if (!empty($current['question']['instructions']))
                                <p class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                                    <strong>Instrucciones:</strong> {{ $current['question']['instructions'] }}
                                </p>
                            @endif

                            {{-- Aviso Normativo de Integridad Académica (RF-EXA-039) --}}
                            <div class="text-xs text-acode-warning bg-acode-warning/15 border border-acode-warning/30 rounded-lg px-3 py-2 flex items-center gap-2">
                                <x-icon name="warning" class="w-4 h-4 text-acode-warning shrink-0" />
                                <span>Por integridad académica, el pegado de código externo está estrictamente deshabilitado.</span>
                            </div>

                            <textarea
                                name="answer_text"
                                rows="10"
                                required
                                @paste.prevent
                                onpaste="return false;"
                                placeholder="// Escribe tu solución aquí..."
                                class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 font-mono text-sm bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-acode-tech leading-relaxed"
                            >{{ $current['question']['starter_code'] }}</textarea>
                        </div>
                        @break
                @endswitch

                <div class="pt-4 flex items-center justify-between">
                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="check" class="w-4 h-4 mr-1.5" />
                        Enviar respuesta
                    </x-button>
                </div>
            </form>
        </x-card>

        {{-- Formulario para Finalizar Examen con Confirmación SweetAlert2 (Tabla 20) --}}
        <div class="flex justify-end pt-2">
            <form
                id="finish-exam-form"
                method="POST"
                action="{{ route('attempts.finish', $attempt) }}"
                x-data="{
                    confirmFinish() {
                        if (typeof AcodeAlert !== 'undefined') {
                            AcodeAlert.confirm({
                                title: '¿Finalizar el examen ahora?',
                                text: 'Las preguntas que no hayas respondido obtendrán cero puntos y tu intento será remitido para calificación definitiva.',
                                confirmButtonText: 'Finalizar examen',
                                cancelButtonText: 'Continuar examen',
                                isDanger: true,
                                onConfirm: () => document.getElementById('finish-exam-form').submit()
                            });
                        } else {
                            if (confirm('¿Finalizar el examen ahora? Las preguntas sin responder no obtendrán puntos.')) {
                                document.getElementById('finish-exam-form').submit();
                            }
                        }
                    }
                }"
            >
                @csrf
                <button
                    type="button"
                    @click="confirmFinish()"
                    class="text-xs text-acode-danger font-semibold hover:underline flex items-center gap-1.5 p-1 rounded transition"
                >
                    <x-icon name="lock" class="w-3.5 h-3.5 text-acode-danger" />
                    <span>Finalizar examen</span>
                </button>
            </form>
        </div>
    </div>
@endsection
