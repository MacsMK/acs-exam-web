{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/evaluation-periods/index.blade.php
    Ubicación:   resources/views/evaluation-periods/index.blade.php
    Descripción: Gestión operativa y configuración de periodos de evaluación
                 (parciales) y ponderaciones porcentuales por grupo escolar con
                 validación aritmética al 100% en tiempo real y tokens acode-*
                 (RF-CAL-001..005, RW-28..31, MPW-V3 §7.3, §7.4.2, §8.1–§8.12).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Periodos de Evaluación - ' . $group->name)

@section('content')
@php
    $activePeriods = $periods->filter(fn ($period) => $period->is_active_today);
    $activePeriod = $activePeriods->first();
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ' %';
    $periodWeights = array_map(
        fn ($value) => is_scalar($value) ? \App\Support\AcademicNumber::forInput($value) : null,
        [
            'exams' => old('exams_weight', 50),
            'homework' => old('homework_weight', 25),
            'project' => old('project_weight', 15),
            'other' => old('other_weight', 10),
        ],
    );
    $earliestPeriodStart = \App\Http\Requests\EvaluationPeriodStoreRequest::earliestStartDate();
    $periodStart = old('starts_at', now()->toDateString());
    $periodEnd = old('ends_at', now()->addMonthNoOverflow()->toDateString());
    $periodStart = is_string($periodStart) ? $periodStart : '';
    $periodEnd = is_string($periodEnd) ? $periodEnd : '';
    $catalogUrl = $catalogQuery === null ? null : route('teacher.gradebook.hub', $catalogQuery);
    $groupUrl = route('groups.show', [$group, ...$catalogContext]);
@endphp

<div
    x-data="{
        openCreate: {{ $errors->any() ? 'true' : 'false' }},
        returnFocus: null,
        saving: false,
        periodStart: @js($periodStart),
        periodEnd: @js($periodEnd),
        earliestPeriodStart: @js($earliestPeriodStart->toDateString()),
        startDateError: @js($errors->has('starts_at')),
        endDateError: @js($errors->has('ends_at')),
        get earliestPeriodEnd() {
            return this.periodStart > this.earliestPeriodStart ? this.periodStart : this.earliestPeriodStart;
        },
        get startsTooEarly() {
            return this.periodStart && this.periodStart < this.earliestPeriodStart;
        },
        get datesOutOfOrder() {
            return this.periodStart && this.periodEnd && this.periodEnd < this.periodStart;
        },
        exams: @js($periodWeights['exams']),
        homework: @js($periodWeights['homework']),
        project: @js($periodWeights['project']),
        other: @js($periodWeights['other']),
        get weights() {
            return [this.exams, this.homework, this.project, this.other].map(value => {
                const input = String(value ?? '');
                const number = Number(input.replace(',', '.'));
                return /^[0-9]+([.,][0-9]{1,2})?$/.test(input) && number <= 100 ? Math.round(number * 100) : null;
            });
        },
        get total() {
            return this.weights.reduce((sum, weight) => sum + (weight ?? 0), 0);
        },
        get isValid() {
            return this.weights.every(weight => weight !== null) && this.total === 10000;
        },
        get weightHint() {
            if (this.weights.some(weight => weight === null)) return 'Captura valores de 0 a 100 con hasta dos decimales.';

            const remaining = 10000 - this.total;
            return remaining > 0 ? 'Faltan ' + (remaining / 100) + ' % para completar el total.'
                : 'Reduce ' + (-remaining / 100) + ' % para completar el total.';
        },
        focusForm() {
            const form = this.$refs.createPeriodForm;
            (form.querySelector('[aria-invalid=true]') || form.querySelector('#name')).focus();
        },
        openForm(trigger) {
            this.returnFocus = trigger;
            this.openCreate = true;
            this.$nextTick(() => this.focusForm());
        },
        closeForm() {
            this.openCreate = false;
            this.$nextTick(() => (this.returnFocus || this.$refs.createPeriodTrigger).focus());
        }
    }"
    @unless ($group->is_closed)
        x-init="if (openCreate) $nextTick(() => focusForm())"
    @endunless
    class="space-y-6"
>

    {{-- Nivel 1: Encabezado Institucional con Breadcrumbs y Acciones --}}
    <x-page-header
        :institutional="true"
        title="Periodos de Evaluación"
        :subtitle="'Configura parciales, ponderaciones porcentuales y control de notas del grupo ' . $group->name . ($group->subject ? ' (' . $group->subject . ')' : '') . '.'"
        :breadcrumbs="[
            ['label' => $catalogUrl ? 'Calificaciones' : 'Grupos', 'href' => $catalogUrl ?? route('groups.index')],
            ['label' => $group->name, 'href' => $groupUrl],
            ['label' => 'Periodos'],
        ]"
    >
        <x-slot:badge>
            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                {{ $group->is_closed ? 'Grupo cerrado' : 'Grupo activo' }}
            </x-badge>
        </x-slot:badge>

        <x-slot:actions>
            <x-button :href="$catalogUrl ?? $groupUrl" variant="neutral" size="md" class="whitespace-nowrap">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                {{ $catalogUrl ? 'Volver a calificaciones' : 'Volver al grupo' }}
            </x-button>

            @unless ($group->is_closed)
                <x-button :href="route('groups.final-plan', $group)" variant="secondary" size="md" class="whitespace-nowrap">
                    Configurar calificación final
                </x-button>
                <x-button type="button" x-ref="createPeriodTrigger" @click="openForm($el)" x-show="!openCreate" x-cloak
                    aria-controls="create-period-panel" ::aria-expanded="openCreate.toString()" variant="primary" size="md" class="whitespace-nowrap">
                    <x-icon name="add" class="w-4 h-4" />
                    Crear periodo
                </x-button>
            @endunless
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Barra de 3 Tarjetas KPI Canónicas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Periodos registrados"
            :value="$periods->count()"
            meta="Parciales configurados"
            icon="clock"
            tone="tech"
        />

        <x-kpi-card
            label="Vigencia de periodos"
            icon="success"
            :tone="$activePeriod ? 'accent' : 'neutral'"
            :meta="$activePeriods->count() === 1
                ? $activePeriod->starts_at->format('d/m/Y') . ' al ' . $activePeriod->ends_at->format('d/m/Y')
                : 'Según las fechas de cada periodo'"
        >
            <x-slot:valueSlot>
                <span class="block text-xl lg:text-2xl font-bold leading-snug tracking-normal">
                    @if ($activePeriods->count() > 1)
                        {{ $activePeriods->count() }} periodos vigentes
                    @else
                        {{ $activePeriod?->name ?? 'Sin periodo vigente' }}
                    @endif
                </span>
            </x-slot:valueSlot>
        </x-kpi-card>

        <x-kpi-card
            label="Estudiantes con calificación"
            :value="$evaluatedStudentsTotal"
            meta="Con al menos un periodo calificado"
            nav-icon="groups"
            tone="primary"
        />
    </div>

    {{-- Nivel 3: Formulario Desplegable de Creación de Periodo (RF-CAL-001, RF-CAL-004, RF-CAL-005) --}}
    @if (!$group->is_closed)
        <div
            id="create-period-panel"
            x-show="openCreate"
            x-cloak
            @keydown.escape.stop="closeForm()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
        >
            <x-card
                title="Registrar nuevo periodo de evaluación"
                subtitle="Define la vigencia del parcial y distribuye el 100 % de la ponderación entre los rubros evaluativos."
                class="border-2 border-acode-tech/30 shadow-md"
            >
                <x-slot:actions>
                    <x-button
                        type="button"
                        variant="neutral"
                        size="sm"
                        :icon-only="true"
                        x-on:click="closeForm()"
                        title="Cerrar formulario"
                        aria-label="Cerrar formulario"
                    >
                        <x-icon name="xMark" class="w-4 h-4" />
                    </x-button>
                </x-slot:actions>

                <form method="POST" action="{{ route('groups.periods.store', [$group, ...$catalogContext]) }}" class="space-y-5"
                      x-ref="createPeriodForm" @submit="if (saving) $event.preventDefault(); else saving = true">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-form.group name="name" label="Nombre del periodo" required>
                            <x-form.input
                                name="name"
                                type="text"
                                placeholder="Ej. Primer Parcial"
                                value="{{ old('name') }}"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                required
                            />
                        </x-form.group>

                        <x-form.group name="starts_at" label="Fecha de inicio" required>
                            <x-form.input
                                name="starts_at"
                                type="date"
                                :value="$periodStart"
                                :min="$earliestPeriodStart->toDateString()"
                                x-model="periodStart"
                                aria-invalid="{{ $errors->has('starts_at') ? 'true' : 'false' }}"
                                x-bind:aria-invalid="(startsTooEarly || startDateError).toString()"
                                aria-describedby="period-start-help"
                                required
                            />
                            <p id="period-start-help" class="text-xs text-slate-600">
                                Puedes iniciar el periodo a partir del {{ $earliestPeriodStart->format('d/m/Y') }}.
                            </p>
                        </x-form.group>

                        <x-form.group name="ends_at" label="Fecha de cierre" required>
                            <x-form.input
                                name="ends_at"
                                type="date"
                                :value="$periodEnd"
                                :min="$earliestPeriodStart->toDateString()"
                                x-model="periodEnd"
                                x-bind:min="earliestPeriodEnd"
                                aria-invalid="{{ $errors->has('ends_at') ? 'true' : 'false' }}"
                                x-bind:aria-invalid="(datesOutOfOrder || endDateError).toString()"
                                aria-describedby="period-date-order"
                                required
                            />
                        </x-form.group>
                    </div>
                    <p id="period-date-order" x-cloak x-show="datesOutOfOrder"
                       class="text-sm text-acode-danger-dark" role="status">
                        La fecha de cierre debe ser igual o posterior a la fecha de inicio.
                    </p>

                    {{-- Configuración de Ponderaciones (RF-CAL-004, RF-CAL-005) --}}
                    <div class="bg-acode-surface/60 p-4 rounded-xl border border-slate-200 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-acode-primary">Ponderaciones de rubros del periodo (%)</p>
                                <p id="period-weight-help" class="text-xs text-slate-600">Cada rubro admite hasta dos decimales. Los cuatro deben sumar 100 %.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-700">Suma total:</span>
                                <span
                                    class="px-3 py-1 text-xs font-mono font-bold rounded-full border"
                                    :class="isValid
                                        ? 'bg-acode-accent/15 text-acode-primary border-acode-accent/40'
                                        : 'bg-acode-danger/10 text-acode-danger-dark border-acode-danger/30'"
                                    role="status"
                                    x-text="(total / 100) + ' %'"
                                ></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <x-form.group name="exams_weight" label="Exámenes (%)" required>
                                <x-form.input
                                    name="exams_weight"
                                    numeric="decimal"
                                    min="0"
                                    max="100"
                                    x-model="exams"
                                    :value="$periodWeights['exams']"
                                    aria-describedby="period-weight-help"
                                    aria-invalid="{{ $errors->has('exams_weight') ? 'true' : 'false' }}"
                                    required
                                />
                            </x-form.group>

                            <x-form.group name="homework_weight" label="Tareas (%)" required>
                                <x-form.input
                                    name="homework_weight"
                                    numeric="decimal"
                                    min="0"
                                    max="100"
                                    x-model="homework"
                                    :value="$periodWeights['homework']"
                                    aria-describedby="period-weight-help"
                                    aria-invalid="{{ $errors->has('homework_weight') ? 'true' : 'false' }}"
                                    required
                                />
                            </x-form.group>

                            <x-form.group name="project_weight" label="Proyectos (%)" required>
                                <x-form.input
                                    name="project_weight"
                                    numeric="decimal"
                                    min="0"
                                    max="100"
                                    x-model="project"
                                    :value="$periodWeights['project']"
                                    aria-describedby="period-weight-help"
                                    aria-invalid="{{ $errors->has('project_weight') ? 'true' : 'false' }}"
                                    required
                                />
                            </x-form.group>

                            <x-form.group name="other_weight" label="Otros (%)" required>
                                <x-form.input
                                    name="other_weight"
                                    numeric="decimal"
                                    min="0"
                                    max="100"
                                    x-model="other"
                                    :value="$periodWeights['other']"
                                    aria-describedby="period-weight-help"
                                    aria-invalid="{{ $errors->has('other_weight') ? 'true' : 'false' }}"
                                    required
                                />
                            </x-form.group>
                        </div>

                        <p x-show="!isValid" x-cloak class="text-xs font-medium text-acode-danger-dark flex items-center gap-1.5">
                            <x-icon name="warning" class="w-4 h-4 shrink-0 text-acode-danger" />
                            <span x-text="weightHint"></span>
                        </p>
                    </div>

                    @include('evaluation-periods.partials.exam-selection', ['examSelectionPeriod' => null])

                    <div class="flex flex-wrap justify-end gap-2.5 pt-2 border-t border-slate-100">
                        <x-button type="button" @click="closeForm()" variant="neutral" size="md">
                            Cancelar
                        </x-button>
                        <x-button type="submit" variant="primary" size="md" ::disabled="saving || !isValid">
                            <x-icon name="save" class="w-4 h-4" x-show="!saving" />
                            @include('evaluation-periods.partials.save-spinner')
                            <span x-text="saving ? 'Guardando…' : 'Guardar periodo'">Guardar periodo</span>
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    @endif

    {{-- Nivel 4: Listado de Periodos Configurados (sin doble borde de x-card) --}}
    <section class="space-y-3">
        <x-section-header
            title="Periodos registrados"
            subtitle="Consulta los parciales del ciclo lectivo y captura calificaciones por periodo."
            :institutional="true"
        >
            <x-slot:actions>
                <span class="text-xs font-medium text-slate-700">
                    {{ $periods->count() }} {{ $periods->count() === 1 ? 'periodo registrado' : 'periodos registrados' }}
                </span>
            </x-slot:actions>
        </x-section-header>

        @if ($periods->isNotEmpty())
            <p class="text-xs text-slate-700 sm:hidden">Desliza la tabla para ver todas las columnas.</p>
        @endif

        <x-table zebra="slate" scroll-label="Periodos registrados del grupo">
            <x-slot:header>
                <x-table.th align="center">Periodo</x-table.th>
                <x-table.th align="center">Vigencia</x-table.th>
                <x-table.th align="center">Ponderaciones</x-table.th>
                <x-table.th align="center">Calificados</x-table.th>
                <x-table.th align="center">Acciones</x-table.th>
            </x-slot:header>

            @forelse ($periods as $period)
                @php
                    $weights = $period->parsed_weights;
                    $weightedExamCount = (int) $period->weighted_exams_count;
                    $selectedExamCount = (int) $period->required_exams_count;
                @endphp
                <tr>
                    <th scope="row" class="min-w-32 px-4 py-3 text-left text-sm font-semibold text-acode-primary">
                        <span class="block max-w-64 break-words">{{ $period->name }}</span>
                    </th>

                    <x-table.td>
                        <div class="space-y-1 text-xs font-medium text-slate-700">
                            <p>
                                <span class="whitespace-nowrap">{{ $period->starts_at?->format('d/m/Y') ?? 'Sin inicio' }}</span>
                                @unless ($period->ends_at !== null && $period->starts_at?->isSameDay($period->ends_at))
                                    – <span class="whitespace-nowrap">{{ $period->ends_at?->format('d/m/Y') ?? 'Sin cierre' }}</span>
                                @endunless
                            </p>
                            @include('evaluation-periods.partials.status-badge')
                        </div>
                    </x-table.td>

                    <x-table.td>
                        @if (!empty($weights))
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if (isset($weights['exams']))
                                    <span class="inline-flex items-center rounded-md border border-acode-tech/25 bg-acode-tech/10 px-2 py-0.5 text-xs font-semibold text-acode-tech">
                                        Exámenes: {{ $percent($weights['exams']) }}
                                    </span>
                                @endif
                                @if (isset($weights['homework']))
                                    <span class="inline-flex items-center rounded-md border border-acode-accent/30 bg-acode-accent/10 px-2 py-0.5 text-xs font-semibold text-acode-primary">
                                        Tareas: {{ $percent($weights['homework']) }}
                                    </span>
                                @endif
                                @if (isset($weights['project']))
                                    <span class="inline-flex items-center rounded-md border border-acode-warning/40 bg-acode-warning/15 px-2 py-0.5 text-xs font-semibold text-slate-900">
                                        Proyectos: {{ $percent($weights['project']) }}
                                    </span>
                                @endif
                                @if (isset($weights['other']))
                                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                        Otros: {{ $percent($weights['other']) }}
                                    </span>
                                @endif
                                @foreach ($period->previousPeriods as $source)
                                    <x-badge variant="neutral">Periodo {{ $source->name }}: {{ $percent($source->pivot->weight) }}</x-badge>
                                @endforeach
                            </div>
                        @else
                            <code class="px-2 py-0.5 bg-acode-surface border border-slate-200 rounded text-xs text-acode-primary font-mono select-all">
                                {{ $period->formula }}
                            </code>
                        @endif
                        @if (($weights['exams'] ?? 0) > 0)
                            <p class="mt-2 text-xs text-slate-700">
                                <span class="font-semibold">Exámenes:</span>
                                @if ($weightedExamCount > 0)
                                    Por ponderación · {{ $weightedExamCount }} {{ $weightedExamCount === 1 ? 'aplicación' : 'aplicaciones' }}
                                @elseif ($period->uses_exam_selection)
                                    Selección manual · {{ $selectedExamCount }} {{ $selectedExamCount === 1 ? 'examen elegido' : 'exámenes elegidos' }}
                                @else
                                    Por fechas
                                @endif
                            </p>
                        @endif
                    </x-table.td>

                    <x-table.td align="center">
                        @php($gradedCount = $gradedByPeriod->get($period->id, 0))
                        <span class="relative font-semibold tabular-nums text-acode-primary">
                            {{ $gradedCount }}
                            <span class="sr-only">{{ $gradedCount === 1 ? 'estudiante calificado' : 'estudiantes calificados' }}</span>
                        </span>
                    </x-table.td>

                    <x-table.td>
                        <div class="flex w-max flex-col items-start gap-1">
                            <a href="{{ route('groups.periods.gradebook', [$group, $period, ...$catalogContext]) }}"
                               aria-label="Abrir calificaciones de {{ $period->name }} · {{ $group->name }}"
                               class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                Abrir calificaciones
                            </a>
                            @unless ($group->is_closed)
                                <a href="{{ route('groups.periods.edit', [$group, $period]) }}"
                                   aria-label="Editar periodo: {{ $period->name }}"
                                   class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    Editar periodo
                                </a>
                                <a href="{{ route('groups.periods.plan', [$group, $period]) }}"
                                   aria-label="Editar ponderaciones de {{ $period->name }}"
                                   class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    Editar ponderaciones
                                </a>
                            @endunless
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-10"
                            icon="clock"
                            title="No hay periodos de evaluación definidos"
                            :message="$group->is_closed
                                ? 'El grupo se cerró sin periodos de evaluación.'
                                : 'Crea el primer periodo y asigna las ponderaciones correspondientes.'"
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </section>

</div>
@endsection
