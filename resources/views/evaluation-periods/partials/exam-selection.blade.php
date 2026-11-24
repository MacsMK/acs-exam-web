@php
    $applications = collect($examApplications ?? []);
    $weighted = $periodHasWeightedExams ?? false;
    $hasFixedSelection = (bool) $examSelectionPeriod?->uses_exam_selection;
    $submittedSelection = in_array(old('uses_exam_selection'), [1, '1', true], true);
    $selectedIds = $submittedSelection ? old('required_exam_ids', []) : ($selectedExamApplicationIds ?? []);
    $selectedIds = is_array($selectedIds) ? array_map('strval', array_filter($selectedIds, 'is_scalar')) : [];
    $selectionError = $errors->first('required_exam_ids')
        ?: $errors->first('required_exam_ids.*')
        ?: $errors->first('uses_exam_selection');
@endphp

<fieldset class="min-w-0 space-y-3 rounded-xl border border-slate-200 px-4 pb-4"
          aria-describedby="period-exam-selection-help{{ $selectionError ? ' period-exam-selection-error' : '' }}"
          @if ($selectionError) aria-invalid="true" tabindex="-1" @endif>
    <legend class="px-1 text-base font-semibold text-acode-primary">Exámenes del periodo</legend>

    @if ($weighted)
        <x-badge variant="tech">Por ponderación</x-badge>
        <p id="period-exam-selection-help" class="text-sm text-slate-700">
            Los exámenes de este periodo se definen en Ponderaciones.
        </p>
        <a href="{{ route('groups.periods.plan', [$group, $examSelectionPeriod]) }}"
           class="inline-flex min-h-8 items-center rounded-lg text-sm font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
            Editar ponderaciones
        </a>
    @else
        <div x-data="{ selectingExams: @js($hasFixedSelection || $submittedSelection) }" class="space-y-3">
            @if ($hasFixedSelection)
                <x-badge variant="tech">Selección manual</x-badge>
                <input type="hidden" name="uses_exam_selection" value="1">
            @else
                <x-badge variant="neutral" x-show="!selectingExams">Por fechas</x-badge>
                <x-badge variant="tech" x-show="selectingExams" x-cloak>Selección manual</x-badge>
            @endif

            <p id="period-exam-selection-help" class="text-sm text-slate-700">
                @unless ($hasFixedSelection)
                    Por fechas, se consideran los exámenes publicados del grupo que inician dentro de la vigencia del periodo.
                @endunless
                La selección manual se mantiene aunque cambies las fechas.
            </p>

            @unless ($hasFixedSelection)
                <label class="flex min-h-8 items-center gap-2 text-sm font-medium text-acode-primary">
                    <input
                        type="checkbox"
                        name="uses_exam_selection"
                        value="1"
                        x-model="selectingExams"
                        @checked($submittedSelection)
                        class="h-4 w-4 rounded-md accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
                    >
                    Elegir los exámenes del periodo
                </label>
            @endunless

            <fieldset @unless ($hasFixedSelection) x-show="selectingExams" x-bind:disabled="!selectingExams" x-cloak @endunless
                      class="min-w-0 space-y-2">
                <legend class="sr-only">Exámenes para selección manual</legend>
                @forelse ($applications as $application)
                    <label class="flex min-h-10 items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:border-acode-tech">
                        <input
                            type="checkbox"
                            name="required_exam_ids[]"
                            value="{{ $application->id }}"
                            @checked(in_array((string) $application->id, $selectedIds, true))
                            class="mt-0.5 h-4 w-4 shrink-0 rounded-md accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
                        >
                        <span class="min-w-0 break-words">
                            <span class="font-medium text-acode-primary">{{ $application->title }}</span>
                            @if ($application->starts_at || ! $application->is_published)
                                <span class="block text-xs text-slate-700">
                                    @if ($application->starts_at)
                                        Inicio {{ \Illuminate\Support\Carbon::parse($application->starts_at)->format('d/m/Y') }}
                                    @endif
                                    @unless ($application->is_published)
                                        {{ $application->starts_at ? ' · ' : '' }}No publicado
                                    @endunless
                                </span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-slate-700">No hay exámenes publicados para este grupo.</p>
                @endforelse
                <p class="text-xs text-slate-700">Si no marcas ninguno, el periodo quedará sin exámenes seleccionados.</p>
            </fieldset>
        </div>
    @endif

    @if ($selectionError)
        <p id="period-exam-selection-error" class="text-sm text-acode-danger-dark">{{ $selectionError }}</p>
    @endif
</fieldset>
