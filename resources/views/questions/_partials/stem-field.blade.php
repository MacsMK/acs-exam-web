<x-form.group name="stem" label="Enunciado del reactivo" :required="true">
    <x-form.textarea
        name="stem"
        x-model="stem"
        rows="4"
        minlength="3"
        maxlength="5000"
        required
        aria-describedby="stem-help"
        placeholder="Redacta el planteamiento que verá el estudiante…"
    >{{ old('stem', $question?->stem ?? '') }}</x-form.textarea>
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs text-slate-500">
        <p id="stem-help">De 3 a 5,000 caracteres.</p>
        <span class="font-mono" aria-hidden="true" x-text="stem.length + ' / 5000'"></span>
    </div>
</x-form.group>
