{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/free-answer-form.blade.php (MPW-V3 §7.5) --}}
<form method="post" action="{{ route('attempts.answer', $attempt) }}"
      x-data="freeAnswerForm(@js(route('attempts.draft', $attempt)), {{ $revision }}, {{ $current['remaining_seconds'] }})"
      @online.window="if (dirty && !expired) flush()" @submit.prevent="submit($event)" @input="changed($event)" @change="changed($event)" class="space-y-4">
    @csrf
    <input type="hidden" name="exam_question_id" value="{{ $current['exam_question_id'] }}">
    <input type="hidden" name="revision" :value="revision" value="{{ $revision }}">
    <p role="timer">Tiempo restante del examen · <span x-text="clock">{{ $current['remaining_seconds'] }} s</span></p>
    <fieldset :inert="submitting || expired" class="space-y-4">
        @include('exams.answer-fields')
    </fieldset>

    <p role="status" aria-live="polite" x-text="message"></p>
    <label>Ir a pregunta
        <select name="next_index">
            @for ($i = 0; $i < $current['total']; $i++)
                <option value="{{ $i }}" @selected($i === $current['index'])>{{ $i + 1 }}</option>
            @endfor
        </select>
    </label>
    <x-button type="submit" variant="primary">Guardar e ir</x-button>
    <button type="submit" name="finish_after_save" value="1" class="text-acode-danger">Finalizar examen</button>
    <a href="{{ request()->fullUrl() }}" x-show="message && failed">Volver a cargar la respuesta guardada</a>
</form>
<form method="post" action="{{ route('attempts.finish', $attempt) }}" id="free-timeout-finish">@csrf</form>

@push('scripts')
<script>
function freeAnswerForm(endpoint, revision, seconds) {
    return {
        revision, seconds, message: '', failed: false, dirty: false, submitting: false, expired: false,
        timer: null, debounce: null, pending: null,
        init() {
            if (this.seconds <= 0) {
                this.expire();
                return;
            }
            const deadline = performance.now() + this.seconds * 1000;
            this.timer = setInterval(() => {
                this.seconds = Math.max(0, Math.ceil((deadline - performance.now()) / 1000));
                if (this.seconds === 0 && !this.expired) this.expire();
            }, 200);
        },
        destroy() { clearInterval(this.timer); clearTimeout(this.debounce); },
        get clock() { return Math.floor(this.seconds / 60) + ':' + String(this.seconds % 60).padStart(2, '0'); },
        changed(event) {
            if (event.target.name === 'next_index' || this.expired) return;
            this.dirty = true;
            this.message = 'Cambios pendientes';
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.flush(), 350);
        },
        async flush() {
            clearTimeout(this.debounce);
            if (this.pending) return this.pending;
            this.pending = this.saveChanges();
            try { return await this.pending; }
            finally { this.pending = null; }
        },
        async saveChanges() {
            try {
                while (this.dirty) {
                    this.dirty = false;
                    const data = new FormData(this.$el);
                    data.set('revision', this.revision);
                    const response = await fetch(endpoint, { method: 'POST', headers: {Accept: 'application/json'}, body: data });
                    const result = await response.json();
                    if (!response.ok || result.saved !== true) throw new Error(result.message || 'No se pudo guardar la respuesta');
                    this.revision = result.revision;
                    this.message = this.dirty ? 'Cambios pendientes' : 'Respuesta guardada';
                    this.failed = false;
                }
                return true;
            } catch (error) {
                this.dirty = true;
                this.failed = true;
                this.message = error.message || 'No se pudo confirmar el guardado';
                return false;
            }
        },
        async submit(event) {
            if (this.submitting || this.expired) return;
            const finish = event.submitter?.name === 'finish_after_save';
            if (finish && !window.confirm('¿Entregar el examen con las respuestas actuales? Las preguntas sin responder quedarán en blanco.')) return;
            this.submitting = true;
            if (!await this.flush() || this.expired) {
                this.submitting = false;
                return;
            }
            if (finish) {
                document.getElementById('free-timeout-finish').submit();
            } else {
                const target = new URL(window.location.href);
                target.searchParams.set('pregunta', this.$el.elements.next_index.value);
                window.location.assign(target);
            }
        },
        async expire() {
            this.expired = true;
            clearInterval(this.timer);
            await this.flush();
            document.getElementById('free-timeout-finish').submit();
        },
    };
}
</script>
@endpush
