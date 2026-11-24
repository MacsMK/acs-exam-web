{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/evaluation-periods/gradebook.blade.php
    Ubicación:   resources/views/evaluation-periods/gradebook.blade.php
    Descripción: Sábana interactiva de captura y cálculo ponderado de
                 calificaciones por periodo escolar con exportación Excel (.xlsx),
                 protección ante cambios sin guardar y tokens oficiales acode-*
                 (RF-CAL-009, RF-CAL-012, RF-CAL-017, RF-CAL-021, RF-CAL-027,
                 RW-28..31, MPW-V3 §7.3, §7.4.2, §8.1–§8.12).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Control de Calificaciones - ' . $period->name . ' - ' . $group->name)

@section('content')
@php
    $rubrics = ['exams' => 'Exámenes', 'homework' => 'Tareas', 'project' => 'Proyectos', 'other' => 'Otros'];
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ' %';
    $weightsTotal = array_sum($weights) + $period->previousPeriods->sum('pivot.weight');
    $manualEntry = $group->allowsManualGradeEntry();
    $scale = $group->grading_scale ?? '0-100';
    $numeric = $group->grading_scale === '0-100' ? 'integer' : 'decimal';
    $scaleDescription = $group->grading_scale === null ? 'Escala anterior 0–100' : 'Escala ' . str_replace('-', '–', $scale);
    $passingScore = $group->passing_score ?? 60;
    $passingScoreDescription = 'Mínimo aprobatorio ' . $group->formatGrade($passingScore);
    $precisionDescription = $numeric === 'integer' ? 'Números enteros' : 'Hasta dos decimales';
    $periodHasStarted = $period->hasStarted();
    $editableRubrics = array_diff_key(array_filter($weights, fn ($weight) => $weight > 0), $weightedExams ? ['exams' => true] : []);
    $canSave = $periodHasStarted && $students->isNotEmpty() && $editableRubrics !== [];
    $requiredExamCount = $examDelivery['requiredCount'] ?? 0;
    $previousPeriodNames = $period->previousPeriods->pluck('name', 'id');
    $periodDates = $period->starts_at->format('d/m/Y');
    if (! $period->starts_at->isSameDay($period->ends_at)) {
        $periodDates .= ' – ' . $period->ends_at->format('d/m/Y');
    }
    $catalogUrl = $catalogQuery === null ? null : route('teacher.gradebook.hub', $catalogQuery);
    $periodsUrl = route('groups.periods.index', [$group, ...$catalogContext]);
    $backUrl = $catalogUrl ?? $periodsUrl;
    $restoredGrades = old('grades');
    $hasRestoredDraft = $errors->any() && is_array($restoredGrades) && $restoredGrades !== [];
@endphp

<div class="space-y-6" x-data="{ saving: false }" @pageshow.window="saving = false"
     x-init="$nextTick(() => $el.querySelector('#gradebook-form [aria-invalid=true]')?.focus())">
    <x-page-header
        title="Control de Calificaciones"
        :institutional="true"
        :subtitle="'Periodo: ' . $period->name . ' · Grupo: ' . $group->name . ' (' . $periodDates . ')'"
        :breadcrumbs="[
            ['label' => $catalogUrl ? 'Calificaciones' : 'Grupos', 'href' => $catalogUrl ?? route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', [$group, ...$catalogContext])],
            ['label' => 'Periodos', 'href' => $periodsUrl],
            ['label' => $period->name],
        ]"
    >
        <x-slot:badge>
            @include('evaluation-periods.partials.status-badge')
            @if ($group->is_closed)
                <x-badge variant="neutral">Grupo cerrado</x-badge>
            @endif
        </x-slot:badge>

        <x-slot:actions>
            <x-button :href="$backUrl" variant="neutral" size="md" class="whitespace-nowrap">
                <x-icon name="arrowLeft" class="h-4 w-4" />
                {{ $catalogUrl ? 'Volver a calificaciones' : 'Volver a periodos' }}
            </x-button>
            <x-button :href="route('groups.periods.gradebook.export', [$group, $period])" variant="secondary" size="md" class="whitespace-nowrap">
                <x-icon name="downloadReport" class="h-4 w-4" />
                Descargar reporte Excel
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-section-header title="Ponderaciones del periodo" :institutional="true" />
        <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($rubrics as $key => $label)
                <div class="rounded-lg border border-slate-200 bg-acode-surface px-3 py-2">
                    <dt class="text-xs text-slate-600">{{ $label }}</dt>
                    <dd class="text-sm font-semibold text-acode-primary">{{ $percent($weights[$key]) }}</dd>
                </div>
            @endforeach
            @foreach ($period->previousPeriods as $source)
                <div class="rounded-lg border border-slate-200 bg-acode-surface px-3 py-2">
                    <dt class="break-words text-xs text-slate-600">Periodo {{ $source->name }}</dt>
                    <dd class="text-sm font-semibold text-acode-primary">{{ $percent($source->pivot->weight) }}</dd>
                </div>
            @endforeach
            <div class="rounded-lg border border-slate-200 bg-acode-surface px-3 py-2">
                <dt class="text-xs text-slate-600">Total</dt>
                <dd class="text-sm font-semibold text-acode-primary">{{ $percent($weightsTotal) }}</dd>
            </div>
        </dl>
        @unless ($period->is_valid_formula)
            <p class="mt-3 text-sm font-medium text-acode-danger-dark">Las ponderaciones del periodo requieren revisión.</p>
            @unless ($group->is_closed)
                <a class="inline-flex min-h-8 items-center text-sm font-semibold text-acode-tech underline underline-offset-2"
                   href="{{ route('groups.periods.plan', [$group, $period]) }}">Editar ponderaciones</a>
            @endunless
        @endunless
    </x-card>

    <section class="space-y-4">
        <x-section-header
            :title="'Estudiantes (' . $students->total() . ')'"
            :subtitle="$scaleDescription . ' · ' . $passingScoreDescription . ($canSave && $manualEntry ? ' · ' . $precisionDescription . ' · Un campo vacío indica una nota pendiente.' : '')"
            :institutional="true"
        />
        @if (! $periodHasStarted)
            <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
                La captura de calificaciones abrirá el {{ $period->starts_at->format('d/m/Y') }}. Puedes consultar las notas y los comentarios ya registrados.
            </p>
        @elseif ($group->is_closed)
            <p class="text-sm text-slate-700">
                {{ $canSave ? 'El grupo está cerrado. Puedes registrar correcciones y descargar el reporte.' : 'El grupo está cerrado. Puedes consultar y descargar el reporte.' }}
            </p>
        @endif
        @if ($canSave && ! $manualEntry)
            <p class="text-sm text-slate-700">La captura numérica en escala 0–10 está pendiente de definir la unidad institucional. Puedes guardar comentarios.</p>
        @elseif ($periodHasStarted && $students->isNotEmpty() && ! $canSave && $period->is_valid_formula)
            <p class="text-sm text-slate-700">Las calificaciones se calculan con las fuentes configuradas.</p>
        @endif

        @if ($students->isEmpty())
            <x-empty-state icon="userGroup" title="No hay estudiantes inscritos"
                message="Los estudiantes aparecerán en esta lista al inscribirse." />
        @else
            <p class="text-xs text-slate-700 sm:hidden">Desliza la tabla para consultar todos los rubros.</p>
            @if ($period->is_valid_formula)
                <p class="text-sm text-slate-700">
                    Cada nota se multiplica por su peso y luego se suman los aportes.
                </p>
            @endif
            @if ($canSave && $students->hasPages())
                <p class="text-sm text-slate-700">Guarda los cambios de esta página antes de pasar a la siguiente.</p>
            @endif

            @if ($canSave)
                <form id="gradebook-form" method="POST" action="{{ route('groups.periods.gradebook.save', [$group, $period, ...$catalogContext]) }}"
                      class="space-y-4" @submit="if (saving) $event.preventDefault(); else saving = true">
                    @csrf
                    <input type="hidden" name="gradebook_page" value="{{ $students->currentPage() }}">
            @endif
                <x-table zebra="slate" scroll-label="Calificaciones por estudiante con desplazamiento horizontal">
                    <x-slot:header>
                        <x-table.th class="sticky left-0 z-20 bg-acode-primary" align="center">Estudiante</x-table.th>
                        @foreach ($rubrics as $key => $label)
                            <x-table.th align="center">{{ $label }} ({{ $percent($weights[$key]) }})</x-table.th>
                        @endforeach
                        <x-table.th align="center">Calificación del periodo</x-table.th>
                    </x-slot:header>

                    @foreach ($students as $student)
                        @php
                            $grade = $savedGrades->get($student->id);
                            $examAvg = $examAverages->get($student->id);
                            $rowName = "grades[$student->id]";
                            $errorKey = "grades.$student->id";
                            $reasonError = $errors->first($errorKey . '.reason');
                            $finalGrade = $periodHasStarted ? $grade?->final_grade : $grade?->getRawOriginal('final_grade');
                            $canShowBreakdown = $periodHasStarted
                                || ($finalGrade !== null && ! $weightedExams && $period->previousPeriods->isEmpty());
                            $contributions = $canShowBreakdown ? ($periodBreakdowns[$student->id] ?? []) : [];
                            $missingExams = $examDelivery['missingByStudent'][$student->id] ?? [];
                            $missingExamCount = count($missingExams);
                        @endphp
                        <tr class="group">
                            <th scope="row" @class([
                                'sticky left-0 z-10 w-36 max-w-36 border-r border-slate-200 px-3 py-3 text-left align-top group-hover:bg-blue-50 sm:w-48 sm:max-w-48',
                                'bg-slate-50' => $loop->even,
                                'bg-white' => $loop->odd,
                            ])>
                                @if ($canSave)
                                    <input type="hidden" name="{{ $rowName }}[student_id]" value="{{ $student->id }}">
                                @endif
                                <p class="break-words font-semibold text-acode-primary">{{ $student->full_name }}</p>
                                <p class="break-all text-xs font-normal text-slate-600">{{ $student->email }}</p>
                            </th>

                            @foreach ($rubrics as $key => $label)
                                @php
                                    $gradeField = $key . '_component';
                                    $numberName = $rowName . '[' . $gradeField . ']';
                                    $commentName = $rowName . '[manual_comments][' . $key . ']';
                                    $numberError = $errors->first($errorKey . '.' . $gradeField);
                                    $commentError = $errors->first($errorKey . '.manual_comments.' . $key);
                                    $fieldId = 'grade-' . $student->id . '-' . $key;
                                    $automatic = $key === 'exams' && $weightedExams;
                                    $weighted = $weights[$key] > 0;
                                    $editable = $canSave && $weighted && ! $automatic;
                                    $awaitsExamSubmission = $editable && $key === 'exams'
                                        && ($requiredExamCount === 0 || $missingExams !== []);
                                    $storedScore = $periodHasStarted ? $grade?->{$gradeField} : $grade?->getRawOriginal($gradeField);
                                    $storedComment = $grade?->manual_comments[$key] ?? null;
                                @endphp
                                <x-table.td class="min-w-36 align-top">
                                    @unless ($weighted)
                                        <p class="text-xs text-slate-600">No pondera</p>
                                    @endunless

                                    @if ($editable && $manualEntry && ! $awaitsExamSubmission)
                                        <x-form.input
                                            :name="$numberName" :numeric="$numeric" size="sm" min="0"
                                            :max="$scale === '0-10' ? 10 : 100"
                                            :value="old($errorKey . '.' . $gradeField, $group->formatGrade($storedScore))"
                                            :has-error="(bool) $numberError"
                                            :aria-label="$label . ': ' . $student->full_name"
                                            :aria-invalid="$numberError ? 'true' : null"
                                            :aria-describedby="$numberError ? $fieldId . '-error' : null"
                                        />
                                        @if ($key === 'exams' && $examAvg !== null && $storedScore === null)
                                            <button type="button" class="mt-1 min-h-8 text-xs font-semibold text-acode-tech underline underline-offset-2"
                                                    aria-label="Usar {{ $examAvg }} en Exámenes de {{ $student->full_name }}"
                                                    @click="const input = document.getElementById(@js($numberName)); input.value = @js($examAvg); input.dispatchEvent(new Event('input', { bubbles: true })); input.focus()">
                                                Usar sugerencia: {{ $examAvg }}
                                            </button>
                                        @endif
                                    @elseif ($storedScore !== null || ($weighted && ! $awaitsExamSubmission))
                                        <p class="text-sm font-semibold text-acode-primary">{{ $group->formatGrade($storedScore) ?? 'Pendiente' }}</p>
                                    @endif

                                    @if ($weighted && $automatic && $periodHasStarted)
                                        <p class="text-xs text-slate-600">Nota automática</p>
                                    @endif

                                    @if ($key === 'exams' && $weighted && $periodHasStarted)
                                        @if ($requiredExamCount === 0)
                                            <p class="mt-1 text-xs text-slate-700">
                                                {{ $period->uses_exam_selection
                                                    ? 'No hay exámenes seleccionados para este periodo.'
                                                    : 'No hay exámenes publicados para este periodo.' }}
                                            </p>
                                        @elseif ($missingExams !== [])
                                            <p class="mt-1 text-xs font-semibold text-slate-700">
                                                @if ($requiredExamCount === 1)
                                                    Falta 1 entrega
                                                @else
                                                    {{ $missingExamCount === 1 ? 'Falta' : 'Faltan' }} {{ $missingExamCount }} de {{ $requiredExamCount }} entregas
                                                @endif
                                            </p>
                                            <ul class="mt-1 list-disc space-y-1 ps-4 text-xs text-slate-700">
                                                @foreach ($missingExams as $examTitle)
                                                    <li class="break-words">{{ $examTitle }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @endif

                                    @if ($numberError)
                                        <p id="{{ $fieldId }}-error" class="mt-1 text-xs text-acode-danger-dark">{{ $numberError }}</p>
                                    @endif

                                    @if ($editable)
                                        <label for="{{ $commentName }}" class="mt-2 block text-xs font-medium text-slate-700">Comentario</label>
                                        <x-form.textarea :name="$commentName" rows="1" maxlength="2000"
                                            :has-error="(bool) $commentError"
                                            :aria-label="'Comentario de ' . $label . ': ' . $student->full_name"
                                            :aria-invalid="$commentError ? 'true' : null"
                                            :aria-describedby="$commentError ? $fieldId . '-comment-error' : null"
                                        >{{ old($errorKey . '.manual_comments.' . $key, $storedComment ?? '') }}</x-form.textarea>
                                        @if ($commentError)
                                            <p id="{{ $fieldId }}-comment-error" class="mt-1 text-xs text-acode-danger-dark">{{ $commentError }}</p>
                                        @endif
                                    @elseif (filled($storedComment))
                                        <p class="mt-1 break-words text-xs text-slate-700">{{ $storedComment }}</p>
                                    @endif
                                </x-table.td>
                            @endforeach

                            <x-table.td class="min-w-44 align-top">
                                @if ($finalGrade !== null)
                                    <p class="text-sm font-semibold text-acode-primary">{{ $group->formatGrade($finalGrade, 2) }}</p>
                                    <p class="text-xs text-slate-700">{{ (float) $finalGrade >= $passingScore ? 'Aprobado' : 'Reprobado' }}</p>
                                @else
                                    <x-badge variant="neutral">Pendiente</x-badge>
                                @endif
                                @if ($contributions !== [])
                                    <details class="mt-2 text-xs text-slate-700">
                                        <summary aria-label="Puntos aportados de {{ $student->full_name }}"
                                                 class="min-h-8 cursor-pointer py-2 font-semibold text-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">Puntos aportados</summary>
                                        <ul class="space-y-2 pb-2">
                                            @foreach ($contributions as $source => $item)
                                                @php
                                                    $sourceLabel = $rubrics[$source] ?? ('Periodo ' . ($previousPeriodNames->get(substr($source, 7)) ?? 'anterior'));
                                                    $sourceWeight = \App\Support\AcademicNumber::forInput((string) $item['weight']);
                                                @endphp
                                                <li>
                                                    <span class="block font-semibold">{{ $sourceLabel }}</span>
                                                    @if ($item['score'] !== null)
                                                        <span>{{ \App\Support\AcademicNumber::forInput((string) $item['score']) }} × {{ $sourceWeight }} % = {{ \App\Support\AcademicNumber::forInput($item['points']) }}</span>
                                                    @else
                                                        <span>Pendiente · {{ $sourceWeight }} %</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                                @if ($periodHasStarted && isset($finals[$student->id]))
                                    <p class="mt-1 text-xs text-slate-700">Final del grupo · {{ $group->formatGrade($finals[$student->id]) }}</p>
                                @endif
                                @if ($canSave && $manualEntry)
                                    <label for="{{ $rowName }}[reason]" class="mt-2 block text-xs font-medium text-slate-700">Motivo si corriges una nota</label>
                                    <x-form.input :name="$rowName . '[reason]'" size="sm" maxlength="1000" :value="old($errorKey . '.reason')"
                                        :has-error="(bool) $reasonError" :aria-label="'Motivo de corrección: ' . $student->full_name"
                                        :aria-invalid="$reasonError ? 'true' : null"
                                        :aria-describedby="$reasonError ? 'reason-error-' . $student->id : null" />
                                    @if ($reasonError)
                                        <p id="reason-error-{{ $student->id }}" class="mt-1 text-xs text-acode-danger-dark">{{ $reasonError }}</p>
                                    @endif
                                @endif
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>

            @if ($canSave)
                    <div class="sticky bottom-0 z-20 flex flex-wrap items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <x-unsaved-changes
                            form-id="gradebook-form"
                            :cancel-url="$backUrl"
                            :has-restored-draft="$hasRestoredDraft"
                        />
                        <x-button type="submit" variant="primary" size="md" ::disabled="saving" class="whitespace-nowrap">
                            <x-icon name="save" class="h-4 w-4" x-show="!saving" />
                            @include('evaluation-periods.partials.save-spinner')
                            <span x-text="saving ? 'Guardando…' : 'Guardar calificaciones'">Guardar calificaciones</span>
                        </x-button>
                    </div>
                    <input type="hidden" name="gradebook_complete" value="1">
                </form>
            @endif

            @if ($students->hasPages())
                <div data-cancel-form="gradebook-form">
                    {{ $students->onEachSide(1)->links('pagination.acode', ['ariaLabel' => 'Paginación de estudiantes']) }}
                </div>
            @endif
        @endif
    </section>
</div>
@endsection
