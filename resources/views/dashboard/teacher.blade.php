{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/dashboard/teacher.blade.php
    Ubicación:   resources/views/dashboard/teacher.blade.php
    Descripción: Panel de control docente con exámenes recientes, accesos de
                 trabajo, grupos y resumen de calificaciones (MPW-V3 §4.1.2,
                 §5.2.2, §7.2, §7.3, §7.8.2, §7.9, §8, Tabla 10; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Panel Docente')

@section('content')
<div
    @if (! empty($showPlanOnboardingOverlay))
        inert
        aria-hidden="true"
        class="space-y-6 pointer-events-none select-none opacity-55"
    @else
        class="space-y-6"
    @endif
>
    <x-page-header :title="'¡Hola, ' . auth()->user()->first_name . '!'" :institutional="true">
        <x-slot:actions>
            <x-button :href="route('exams.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Crear examen
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <section class="space-y-3">
        <x-section-header
            title="Exámenes Recientes"
            :subtitle="'Exámenes creados: ' . $examsCount . ' · Activos: ' . $activeExamsCount"
            action-label="Ver todos los exámenes"
            :action-href="route('exams.index')"
            :institutional="true"
        />

        @if ($recentExams->isNotEmpty())
            <p class="text-xs text-slate-700 sm:hidden">Desliza la tabla para ver las acciones.</p>
        @endif

        <x-table zebra="slate" scroll-label="Exámenes Recientes">
            <x-slot:header>
                <x-table.th align="center">Examen</x-table.th>
                <x-table.th align="center">Grupos</x-table.th>
                <x-table.th align="center">Reactivos</x-table.th>
                <x-table.th align="center">Entregas</x-table.th>
                <x-table.th align="center">Estado</x-table.th>
                <x-table.th align="center">Acciones</x-table.th>
            </x-slot:header>

            @forelse ($recentExams as $exam)
                @php
                    $status = $exam->operationalStatus();
                    [$statusLabel, $statusTone] = match ($status) {
                        'active' => ['Activo', 'accent'],
                        'scheduled' => ['Programado', 'tech'],
                        'closed' => ['Cerrado', 'neutral'],
                        default => ['Borrador', 'warning'],
                    };
                    $canAssemble = $exam->supportsStructuralEdit();
                    $deliveries = (int) $exam->submitted_attempts_count;
                    $actions = array_filter([
                        'Armar' => $canAssemble ? route('exams.assemble', $exam) : null,
                        'Monitoreo' => $status === 'active' ? route('exams.monitor', $exam) : null,
                        'Revisar entregas' => $deliveries > 0 ? route('exams.revision.index', $exam) : null,
                        'Abrir resumen' => $canAssemble ? null : route('exams.review', $exam),
                    ]);

                    if ($exam->is_published && $status !== 'active') {
                        $actions['Monitoreo'] = route('exams.monitor', $exam);
                    }
                @endphp
                <tr>
                    <th scope="row" class="min-w-40 px-4 py-3 text-left text-sm">
                        <p class="font-semibold leading-snug text-acode-primary">
                            {{ $exam->title }}
                        </p>
                        <p class="mt-0.5 text-xs font-normal text-slate-700">
                            Escala {{ $exam->grading_scale }} · {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha programada' }}
                        </p>
                    </th>

                    <x-table.td class="min-w-40 max-w-xs">
                        <div class="flex flex-wrap items-center gap-1.5">
                            @forelse ($exam->groups as $group)
                                <x-badge variant="tech" class="break-words">{{ $group->name }}</x-badge>
                            @empty
                                <span class="text-xs text-slate-700">Sin grupo asignado</span>
                            @endforelse
                        </div>
                    </x-table.td>

                    <x-table.td align="center" class="font-mono tabular-nums">
                        {{ $exam->exam_questions_count }}
                    </x-table.td>

                    <x-table.td align="center" class="font-mono tabular-nums">
                        {{ $deliveries }}
                    </x-table.td>

                    <x-table.td align="center">
                        <x-badge :variant="$statusTone">{{ $statusLabel }}</x-badge>
                    </x-table.td>

                    <x-table.td align="center" class="min-w-44">
                        <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                            @foreach ($actions as $label => $href)
                                @if ($loop->first)
                                    <x-button :href="$href" :aria-label="$label . ': ' . $exam->title" variant="secondary" size="sm">
                                        {{ $label }}
                                    </x-button>
                                @else
                                    <a href="{{ $href }}" aria-label="{{ $label }}: {{ $exam->title }}"
                                       class="inline-flex min-h-8 items-center rounded-lg px-2 text-xs font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                        {{ $label }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-0">
                        <x-empty-state
                            class="rounded-none border-0 py-8 shadow-none"
                            icon="document"
                            title="Todavía no has creado exámenes"
                            message="Configura tu primer examen y asígnalo a tus grupos activos."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>

        <div class="flex flex-wrap gap-2">
            <x-button :href="route('teacher.reviews.index')" variant="secondary" size="sm">
                <x-icon name="view" class="w-4 h-4" />
                Centro de revisión
            </x-button>

            <x-button :href="route('teacher.gradebook.hub')" variant="secondary" size="sm">
                <x-icon name="reportBar" class="w-4 h-4" />
                Control de calificaciones
            </x-button>
        </div>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">
        <section class="min-w-0 space-y-3 xl:col-span-2">
            <x-section-header
                title="Grupos Recientes"
                :subtitle="'Mis grupos: ' . $groupsCount"
                :institutional="true"
            >
                <x-slot:actions>
                    <a href="{{ route('groups.index') }}"
                       class="inline-flex min-h-8 items-center rounded-lg px-2 text-xs font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                        Ver todos los grupos
                    </a>
                    <x-button :href="route('groups.create')" variant="secondary" size="sm">
                        <x-icon name="add" class="w-4 h-4" />
                        Crear grupo
                    </x-button>
                </x-slot:actions>
            </x-section-header>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse ($recentGroups as $group)
                    <x-card class="min-w-0" padding="p-4">
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="break-words text-xs font-semibold uppercase tracking-wider text-acode-tech">
                                    {{ $group->subject ?? 'Materia General' }}
                                </span>
                                <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                    {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                                </x-badge>
                            </div>
                            <h3 class="break-words text-base font-semibold leading-6 text-acode-primary">{{ $group->name }}</h3>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-xs">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-slate-700">
                                <span class="font-mono font-semibold">Folio: {{ $group->access_folio }}</span>
                                <span>{{ $group->students_count }} {{ $group->students_count === 1 ? 'estudiante' : 'estudiantes' }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <a href="{{ route('groups.show', $group) }}" aria-label="Ver grupo: {{ $group->name }}"
                                   class="inline-flex min-h-8 items-center rounded-lg px-2 font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    Ver grupo
                                </a>
                                <a href="{{ route('groups.periods.index', $group) }}" aria-label="Periodos: {{ $group->name }}"
                                   class="inline-flex min-h-8 items-center rounded-lg px-2 font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    Periodos
                                </a>
                            </div>
                        </div>
                    </x-card>
                @empty
                    <x-empty-state
                        class="sm:col-span-2"
                        icon="userGroup"
                        title="Todavía no tienes grupos"
                        message="Crea tu primer grupo escolar para inscribir estudiantes mediante código QR o folio."
                    />
                @endforelse
            </div>
        </section>

        <section class="min-w-0 space-y-3">
            <x-section-header title="Banco de preguntas" :institutional="true">
                <x-slot:actions>
                    <x-button :href="route('questions.create')" variant="secondary" size="sm">
                        <x-icon name="add" class="w-4 h-4" />
                        Crear pregunta
                    </x-button>
                </x-slot:actions>
            </x-section-header>

            <x-kpi-card
                label="Reactivos registrados"
                :value="$questionsCount"
                nav-icon="questions"
                tone="tech"
                radius="xl"
                :href="route('questions.index')"
            />
        </section>
    </div>

    <section class="space-y-3">
        <x-section-header title="Calificaciones" :institutional="true" />

        <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
            <x-kpi-card
                class="!h-auto"
                label="Promedio general"
                :value="$averageScore === null ? '—' : number_format($averageScore, 1)"
                meta="Escala 0–10"
                nav-icon="grades"
                tone="accent"
                radius="xl"
                :mono="true"
            >
                <dl class="space-y-1.5 text-sm text-slate-700">
                    <div class="flex justify-between gap-2">
                        <dt>Intentos calificados</dt>
                        <dd class="font-semibold text-acode-primary">{{ $gradedCount }}</dd>
                    </div>
                    @if ($gradedCount > 0)
                        <div class="flex justify-between gap-2">
                            <dt>Índice de aprobación</dt>
                            <dd class="font-semibold text-acode-primary">{{ round($passedCount / $gradedCount * 100) }}%</dd>
                        </div>
                    @endif
                    @if ($pendingCount > 0)
                        <div class="flex justify-between gap-2">
                            <dt>Pendientes de revisión</dt>
                            <dd class="font-semibold text-acode-primary">{{ $pendingCount }}</dd>
                        </div>
                    @endif
                    @if ($outOfScaleCount > 0)
                        <div class="flex justify-between gap-2">
                            <dt>Calificaciones fuera de escala</dt>
                            <dd class="font-semibold text-acode-danger-dark">{{ $outOfScaleCount }}</dd>
                        </div>
                    @endif
                </dl>
            </x-kpi-card>

            <x-analytics.group-chart
                class="min-w-0 lg:col-span-2"
                :passed-count="$passedCount"
                :failed-count="$failedCount"
                title="Aprobados y reprobados"
                dataset-label="Intentos"
                :empty-title="$outOfScaleCount > 0 ? 'No hay calificaciones dentro de su escala' : 'Aún no hay calificaciones definitivas'"
                empty-message="La distribución se mostrará cuando haya entregas calificadas dentro de su escala."
            />
        </div>
    </section>
</div>

@if (! empty($showPlanOnboardingOverlay) && ! empty($onboardingOverlay))
    @include('dashboard.partials._plan-onboarding-overlay', ['onboardingOverlay' => $onboardingOverlay])
@endif
@endsection
