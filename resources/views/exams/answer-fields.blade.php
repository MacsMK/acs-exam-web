{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/answer-fields.blade.php (MPW-V3 §7.5) --}}
@switch($current['question']['question_type'])
    @case('multiple_choice')
        <div class="space-y-2.5">
            @forelse ($current['question']['options'] as $option)
                <label class="flex items-center gap-3 border border-slate-200 rounded-xl px-4 py-3 text-sm hover:bg-acode-tech/5 hover:border-acode-tech/40 cursor-pointer transition">
                    <input
                        type="radio"
                        name="selected_option_index"
                        value="{{ $option['index'] }}"
                        @checked(is_array($draft ?? null) && isset($draft['selected_option_index']) && (int) $draft['selected_option_index'] === (int) $option['index'])
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
                                <option value="{{ $right['id'] }}" @selected(is_array($draft ?? null) && collect($draft)->contains(fn ($p) => is_array($p) && ($p['left_id'] ?? null) == $left['id'] && ($p['right_id'] ?? null) == $right['id']))>{{ $right['text'] }}</option>
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
            >{{ is_string($draft ?? null) ? $draft : '' }}</x-form.textarea>
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
            >{{ is_string($draft ?? null) ? $draft : $current['question']['starter_code'] }}</x-form.textarea>
        </div>
        @break
@endswitch
