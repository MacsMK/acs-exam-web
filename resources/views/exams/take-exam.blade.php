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
            @if ($attempt->free_close_at === null)
            <x-exam.timer
                :seconds="(int) $current['remaining_seconds']"
                formRef="answerForm"
            />
            @endif
        </div>

        {{-- Tarjeta Principal del Reactivo --}}
        <x-card class="mb-5 shadow-sm border-slate-200">
            <h2 class="text-xs font-bold uppercase tracking-wider text-acode-tech mb-3">
                Reactivo activo
            </h2>
            {{-- whitespace-pre-wrap conserva espacios y sangrías del código en el enunciado (RF-BAN-014) --}}
            <div class="text-sm text-slate-700 mb-6 whitespace-pre-wrap break-words leading-relaxed">{{ $current['question']['stem'] }}</div>
            <x-question.files class="mb-6" :question-id="$current['question']['id']" :has-image="$current['question']['has_image'] ?? false" :has-pdf="$current['question']['has_pdf'] ?? false" />

            @if ($attempt->free_close_at !== null)
                @include('exams.free-answer-form')
            @else
            {{-- Formulario de Respuesta por Tipo de Reactivo --}}
            <form
                method="POST"
                action="{{ route('attempts.answer', $attempt) }}"
                x-ref="answerForm"
                {{-- RF-EXA-031: se puede avanzar sin responder, previa confirmación; el auto-envío por tiempo no pregunta --}}
                x-data="{
                    confirmedBlank: false,
                    saveState: @js($draft !== null ? 'saved' : 'idle'),
                    saveTimer: null,
                    queueSave() {
                        this.saveState = 'pending';
                        clearTimeout(this.saveTimer);
                        this.saveTimer = setTimeout(() => this.saveDraft(), 800);
                    },
                    async saveDraft() {
                        this.saveState = 'saving';
                        try {
                            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                            const response = await fetch(@js(route('attempts.draft', $attempt)), {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                                body: new FormData(this.$el),
                            });
                            const data = await response.json().catch(() => ({}));
                            this.saveState = response.ok && data.saved === true ? 'saved' : 'error';
                        } catch (error) {
                            this.saveState = 'error';
                        }
                        if (this.saveState === 'error') {
                            clearTimeout(this.saveTimer);
                            this.saveTimer = setTimeout(() => this.saveDraft(), 5000);
                        }
                    },
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
                @submit="clearTimeout(saveTimer); guard($event)"
                @input="queueSave()"
                @change="queueSave()"
                class="space-y-4"
            >
                @csrf
                <input type="hidden" name="exam_question_id" value="{{ $current['exam_question_id'] }}">

                @include('exams.answer-fields')

                <div class="pt-4 flex flex-wrap items-center justify-between gap-3">
                    {{-- RF-EXA-041: «guardada» solo tras la confirmación del servidor --}}
                    <p class="order-2 text-xs font-medium" role="status" aria-live="polite">
                        <span x-show="saveState === 'saved'" style="display: none" class="text-acode-accent">✓ Respuesta guardada</span>
                        <span x-show="saveState === 'pending' || saveState === 'saving'" style="display: none" class="text-slate-500">Guardando…</span>
                        <span x-show="saveState === 'error'" style="display: none" class="text-acode-danger">No se pudo guardar; se reintentará automáticamente</span>
                    </p>
                    <x-button type="submit" variant="primary" size="md" class="order-1">
                        <x-icon name="check" class="w-4 h-4" />
                        Enviar respuesta
                    </x-button>
                </div>
            </form>
            @endif
        </x-card>

        @if ($attempt->free_close_at === null)
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
        @endif
    </div>
@endsection

@push('scripts')
    {{-- RF-EXA-049: señal de presencia; si otra sesión tiene el intento, se sale del examen --}}
    <script>
        (() => {
            const endpoint = @js(route('attempts.heartbeat', $attempt));
            const ping = async () => {
                try {
                    const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                    const response = await fetch(endpoint, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
                    if (response.status === 409) {
                        const data = await response.json().catch(() => ({}));
                        const go = () => { window.location.href = data.redirect_url || '/'; };
                        if (data.message && typeof AcodeAlert !== 'undefined') {
                            AcodeAlert.warning('Intento abierto en otra sesión', data.message).then(go);
                        } else {
                            go();
                        }
                    }
                } catch (error) {
                    // Sin conexión: se reintenta en la siguiente señal
                }
            };
            ping();
            setInterval(ping, 15000);
        })();
    </script>
    @if (\Illuminate\Support\Facades\Route::has('attempts.incident'))
        <script>
            window.acodeIncidentEndpoint = @js(route('attempts.incident', $attempt));
        </script>
    @endif
@endpush
