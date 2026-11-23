{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/take-exam.blade.php (MPW-V3 §5.5, §7.3, §7.5.1, §7.7, §7.8.2, §8.9.1, ADR-020, ADR-042) --}}
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
            {{-- whitespace-pre-wrap conserva espacios y sangrías del código en el enunciado (RF-BAN-014) --}}
            <div class="text-sm text-slate-700 mb-6 whitespace-pre-wrap break-words leading-relaxed">{{ $current['question']['stem'] }}</div>
            <x-question.files class="mb-6" :question-id="$current['question']['id']" :has-image="$current['question']['has_image'] ?? false" :has-pdf="$current['question']['has_pdf'] ?? false" />

            {{-- Formulario de Respuesta por Tipo de Reactivo --}}
            <form
                method="POST"
                action="{{ route('attempts.answer', $attempt) }}"
                x-ref="answerForm"
                {{-- RF-EXA-031: se puede avanzar sin responder, previa confirmación; el auto-envío por tiempo no pregunta --}}
                x-data="{
                    confirmedBlank: false,
                    isBlank() {
                        const f = this.$el;
                        const radios = f.querySelectorAll('input[name=selected_option_index]');
                        if (radios.length) { return ! Array.from(radios).some((r) => r.checked); }
                        const selects = f.querySelectorAll('select[name^=pairs]');
                        if (selects.length) { return Array.from(selects).every((s) => s.value === ''); }
                        const text = f.querySelector('textarea[name=answer_text]');
                        if (text) { const v = text.value.trim(); return v === '' || v === (text.dataset.starter || '').trim(); }
                        return false;
                    },
                    guard(event) {
                        if (this.confirmedBlank || ! this.isBlank()) { return; }
                        event.preventDefault();
                        AcodeAlert.confirm({
                            title: '¿Continuar sin responder?',
                            text: 'Esta pregunta obtendrá cero puntos y no podrás regresar a ella.',
                            confirmButtonText: 'Continuar',
                            cancelButtonText: 'Cancelar',
                            onConfirm: () => { this.confirmedBlank = true; this.$el.submit(); },
                        });
                    },
                }"
                @submit="guard($event)"
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
                                        <x-form.select name="pairs[{{ $i }}][right_id]">
                                            <option value="">Selecciona definición…</option>
                                            @forelse ($current['question']['right_items'] as $right)
                                                <option value="{{ $right['id'] }}">{{ $right['text'] }}</option>
                                            @empty
                                                <option value="" disabled>Sin definiciones</option>
                                            @endforelse
                                        </x-form.select>
                                    </div>
                                </div>
                            @empty
                                <x-empty-state message="Sin pares configurados en este reactivo." />
                            @endforelse
                        </div>
                        @break

                    @case('open')
                        <x-form.group name="answer_text" label="Tu respuesta analítica">
                            <x-form.textarea
                                name="answer_text"
                                rows="6"
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
                            <div class="text-xs text-acode-primary bg-acode-warning/15 border border-acode-warning/50 rounded-lg px-3 py-2 flex items-center gap-2">
                                <x-icon name="warning" class="w-4 h-4 text-acode-warning-dark shrink-0" />
                                <span>Por integridad académica, el pegado de código externo está estrictamente deshabilitado.</span>
                            </div>

                            <x-form.textarea
                                name="answer_text"
                                rows="10"
                                :mono="true"
                                data-starter="{{ $current['question']['starter_code'] }}"
                                @paste.prevent
                                onpaste="return false;"
                                placeholder="// Escribe tu solución aquí..."
                            >{{ $current['question']['starter_code'] }}</x-form.textarea>
                        </div>
                        @break
                @endswitch

                <div class="pt-4 flex items-center justify-between">
                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="check" class="w-4 h-4" />
                        Enviar respuesta
                    </x-button>
                </div>
            </form>
        </x-card>

        {{-- Formulario para Finalizar Examen con Confirmación SweetAlert2 (MPW-V3 §8.6) --}}
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
                <x-button type="button" variant="danger" size="sm" @click="confirmFinish()">
                    Finalizar examen
                </x-button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @if (\Illuminate\Support\Facades\Route::has('attempts.incident'))
        <script>
            window.acodeIncidentEndpoint = @js(route('attempts.incident', $attempt));
        </script>
    @endif
@endpush
