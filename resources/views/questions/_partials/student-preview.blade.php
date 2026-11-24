{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Parcial: questions/_partials/student-preview.blade.php (MPW-V3 §7, §8) --}}
<aside class="lg:sticky lg:top-24 space-y-4" aria-label="Vista previa en tiempo real para el estudiante">
    <x-card class="border-slate-200 shadow-sm">
        <div class="flex items-center justify-between gap-2 pb-3 mb-4 border-b border-slate-100">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-acode-tech">
                    Reactivo activo
                </span>
                <span class="block text-xs text-slate-500">
                    Así lo verá el estudiante en el examen
                </span>
            </div>
            <x-badge variant="info">Vista previa</x-badge>
        </div>

        {{-- Métricas simuladas de examen (sin exponer el título interno docente) --}}
        <div class="flex items-center justify-between gap-2 mb-4 rounded-xl bg-acode-surface/80 px-3 py-2 text-xs">
            <span class="font-semibold text-acode-primary">
                Valor: <span class="font-mono" x-text="formattedPoints + ' pts'"></span>
            </span>
            <span class="inline-flex items-center gap-1 font-mono font-semibold text-acode-tech">
                <x-icon name="clock" class="w-3.5 h-3.5" />
                <span x-text="formattedTime"></span>
            </span>
        </div>

        {{-- Enunciado: whitespace-pre-wrap conserva espacios y sangrías (RF-BAN-014) --}}
        <div class="text-sm text-slate-700 leading-5 mb-5 break-words min-h-[3rem]">
            <span
                class="whitespace-pre-wrap"
                x-show="stem.trim().length > 0"
                x-text="stem"
            ></span>
            <span
                x-show="stem.trim().length === 0"
                class="text-slate-400 italic"
            >Escribe el enunciado principal para previsualizar el planteamiento que leerá el estudiante…</span>
        </div>

        {{-- Vista previa: Opción múltiple --}}
        <div x-show="type === 'multiple_choice'" class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                Selecciona una respuesta
            </p>
            <template x-for="(opt, i) in options" :key="opt.uid">
                <button
                    type="button"
                    @click="previewSelectedOption = i"
                    class="w-full text-left flex items-start gap-2.5 rounded-xl border px-3 py-2.5 text-sm transition-all"
                    :class="previewSelectedOption === i
                        ? 'border-acode-tech bg-acode-tech/5 text-acode-primary font-semibold'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                >
                    <span
                        class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold shrink-0"
                        :class="previewSelectedOption === i
                            ? 'bg-acode-tech text-white'
                            : 'bg-slate-100 text-slate-600'"
                        x-text="String.fromCharCode(65 + i)"
                    ></span>
                    <span
                        class="leading-5 pt-0.5 break-words"
                        :class="!opt.text.trim() ? 'italic text-slate-400' : ''"
                        x-text="opt.text.trim() || ('Opción ' + String.fromCharCode(65 + i))"
                    ></span>
                </button>
            </template>
            <p class="text-xs text-slate-500 pt-1">
                Las claves correctas nunca se muestran al estudiante durante el examen.
            </p>
        </div>

        {{-- Vista previa: Relación de columnas (definiciones mezcladas sin .reverse()) --}}
        <div x-show="type === 'matching'" x-cloak class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                Emparejamiento (orden mezclado)
            </p>
            <div class="space-y-2">
                <template x-for="(pair, i) in pairs" :key="pair.uid">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 space-y-2 text-xs">
                        <div class="flex items-start gap-2">
                            <span class="font-mono text-xs font-bold px-1.5 py-0.5 rounded bg-acode-primary text-white shrink-0" x-text="i + 1"></span>
                            <span
                                class="text-sm font-semibold text-slate-800 break-words"
                                x-text="pair.concept.trim() || ('Concepto ' + (i + 1))"
                            ></span>
                        </div>
                        <select
                            disabled
                            aria-label="Selector simulado de definición"
                            class="w-full h-8 rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-600"
                        >
                            <option>Selecciona la definición correspondiente…</option>
                            <template x-for="defItem in previewShuffledDefinitions" :key="defItem.key">
                                <option x-text="defItem.label + ' · ' + defItem.text"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>
        </div>

        {{-- Vista previa: Pregunta abierta --}}
        <div x-show="type === 'open'" x-cloak class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                Área de respuesta del estudiante
            </p>
            <textarea
                rows="4"
                disabled
                placeholder="Aquí redactará su respuesta abierta el estudiante…"
                class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm text-slate-500 resize-none"
            ></textarea>
        </div>

        {{-- Vista previa: Código fuente (MPW-V3 Tabla 14 & MC-V3 Tabla 4) --}}
        <div x-show="type === 'code'" x-cloak class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-mono text-xs font-semibold px-2.5 py-0.5 rounded-md bg-acode-tech/10 text-acode-tech border border-acode-tech/30" x-text="programmingLanguage || 'Código'"></span>
                <span
                    class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                    :class="isFindError ? 'bg-acode-warning/25 text-acode-primary border border-acode-warning/50' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                    x-text="isFindError ? 'Depurar error' : 'Escribir solución'"
                ></span>
            </div>

            <div x-show="instructions.trim().length > 0" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 whitespace-pre-line leading-5">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Instrucciones técnicas</span>
                <span x-text="instructions"></span>
            </div>

            <div class="rounded-lg overflow-hidden border border-slate-300 border-l-4 border-l-acode-primary bg-slate-100">
                <div class="bg-white/80 px-3 py-1.5 border-b border-slate-200 text-xs font-mono text-slate-600 flex items-center justify-between">
                    <span x-text="'editor.' + codeExtension"></span>
                    <span class="uppercase tracking-wider text-slate-500 font-sans font-semibold">Editor alumno</span>
                </div>
                <pre class="p-3 text-[13px] font-mono text-acode-ink overflow-x-auto whitespace-pre leading-5 min-h-[5.5rem]"><code x-text="starterCode || '// El estudiante partirá de un editor en blanco en ' + (programmingLanguage || 'este lenguaje')"></code></pre>
            </div>
        </div>
    </x-card>

    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 flex items-start gap-2">
        <x-icon name="lock" class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
        <span>
            El título interno del reactivo y la solución canónica permanecen ocultos al alumno en todo momento.
        </span>
    </div>
</aside>
