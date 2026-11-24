{{-- Panel del Estudiante · MPW-V3 §§5.2.2, 7.3, 7.8.2, 8.4 --}}
@extends('layouts.app')

@section('title', 'Panel del Estudiante')

@section('content')
<div class="space-y-4 sm:space-y-6">
    <x-page-header
        :title="'¡Hola, ' . (auth()->user()->first_name ?? auth()->user()->full_name) . '!'"
        :institutional="true"
        class="!gap-2 !p-4 sm:!gap-3 sm:!p-6"
    >
        @if ($enrolledGroups->isNotEmpty())
            <x-slot:actions>
                <x-button :href="route('student.grades.index')" variant="secondary">
                    <x-nav-icon name="grades" class="h-4 w-4" />
                    Abrir calificaciones
                </x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($enrolledGroups->isEmpty())
        <x-empty-state
            icon="userGroup"
            title="Aún no tienes grupos inscritos"
            message="Solicita el folio de tu clase a tu profesor para unirte."
        >
            <x-button :href="route('student.groups.join.form')" variant="primary">
                <x-icon name="add" class="h-4 w-4" />
                Unirme a un grupo
            </x-button>
        </x-empty-state>
    @else
        <section class="min-w-0 space-y-2 sm:space-y-3">
            <x-section-header
                title="Exámenes activos y programados"
                action-label="Abrir exámenes"
                :action-href="route('student.exams.index')"
                :institutional="true"
            />

            @if ($examRows->isEmpty())
                <x-empty-state
                    icon="clock"
                    title="No tienes exámenes activos ni programados"
                    message="Puedes consultar tus demás exámenes en el catálogo."
                />
            @else
                @php
                    $shownExamCounts = $examRows->countBy('state');
                    $nextStart = $examRows->firstWhere('state', 'scheduled')['startsAt'] ?? null;
                @endphp
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-700">
                    @if ($inProgressExamCount > 0)
                        <p>
                            En progreso <strong class="font-semibold text-acode-primary">{{ $inProgressExamCount }}</strong>
                            @if ($inProgressExamCount > $shownExamCounts->get('in_progress', 0))
                                <span class="text-xs">(se muestran {{ $shownExamCounts->get('in_progress', 0) }})</span>
                            @endif
                        </p>
                    @endif

                    @if ($activeExamCount > 0)
                        <p>
                            Activos <strong class="font-semibold text-acode-primary">{{ $activeExamCount }}</strong>
                            @if ($activeExamCount > $shownExamCounts->get('active', 0))
                                <span class="text-xs">(se muestran {{ $shownExamCounts->get('active', 0) }})</span>
                            @endif
                        </p>
                    @else
                        <p>No tienes exámenes activos.</p>
                    @endif

                    @if ($scheduledExamCount > 0)
                        <p>
                            Programados <strong class="font-semibold text-acode-primary">{{ $scheduledExamCount }}</strong>
                            @if ($scheduledExamCount > $shownExamCounts->get('scheduled', 0))
                                <span class="text-xs">(se muestran {{ $shownExamCounts->get('scheduled', 0) }})</span>
                            @endif
                        </p>
                        @if ($nextStart)
                            <p>
                                Próximo inicio
                                <time class="whitespace-nowrap font-semibold text-acode-primary" datetime="{{ $nextStart->toAtomString() }}">
                                    {{ $nextStart->format('d/m/Y H:i') }}
                                </time>
                            </p>
                        @endif
                    @else
                        <p>No tienes exámenes programados.</p>
                    @endif
                </div>

                <p class="text-xs text-slate-700">Zona horaria: {{ $examTimezone }}</p>

                <div class="grid gap-3 sm:grid-cols-2 xl:hidden">
                    @foreach ($examRows as $row)
                        @include('exams.partials._student-exam-card', ['row' => $row, 'showDescription' => false])
                    @endforeach
                </div>

                <x-table class="hidden xl:block" zebra="slate" scroll-label="Exámenes activos y programados">
                    <x-slot:header>
                        <x-table.th align="center">Examen</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        <x-table.th align="center">Fechas</x-table.th>
                        <x-table.th align="center">Tiempo máximo</x-table.th>
                        <x-table.th align="center">Reactivos</x-table.th>
                        <x-table.th align="center">Acción</x-table.th>
                    </x-slot:header>

                    @foreach ($examRows as $row)
                        <tr>
                            <th scope="row" class="px-4 py-3 text-left text-sm">
                                <p class="break-words font-semibold leading-snug text-acode-primary">{{ $row['exam']->title }}</p>
                                <p class="mt-0.5 break-words text-xs font-normal text-slate-600">
                                    {{ $row['groupNames'] }}
                                </p>
                            </th>
                            <x-table.td align="center">
                                <x-badge :variant="$row['statusVariant']">{{ $row['statusLabel'] }}</x-badge>
                                @if ($row['reapplicationNumber'] !== null)
                                    <p class="mt-1 text-xs text-slate-600">Reaplicación autorizada</p>
                                @endif
                            </x-table.td>
                            <x-table.td align="center">
                                <dl class="space-y-1 text-xs text-slate-600">
                                    @if ($row['state'] === 'scheduled' && $row['startsAt'])
                                        <div>
                                            <dt>Fecha de inicio</dt>
                                            <dd class="whitespace-nowrap font-semibold text-acode-primary">
                                                <time datetime="{{ $row['startsAt']->toAtomString() }}">{{ $row['startsAt']->format('d/m/Y H:i') }}</time>
                                            </dd>
                                        </div>
                                    @endif

                                    @if ($row['state'] === 'in_progress')
                                        @if ($row['attemptEndsAt'])
                                            <div>
                                                <dt>{{ $row['attemptHasGrace'] ? 'Fecha límite de edición' : 'Fecha límite del intento' }}</dt>
                                                <dd class="whitespace-nowrap font-semibold text-acode-primary">
                                                    <time datetime="{{ $row['attemptEndsAt']->toAtomString() }}">{{ $row['attemptEndsAt']->format('d/m/Y H:i') }}</time>
                                                </dd>
                                            </div>
                                            @if ($row['attemptHasGrace'])
                                                <div>
                                                    <dt>Fecha límite de envío</dt>
                                                    <dd class="whitespace-nowrap font-semibold text-acode-primary">
                                                        <time datetime="{{ $row['attemptDeadline']->toAtomString() }}">{{ $row['attemptDeadline']->format('d/m/Y H:i') }}</time>
                                                    </dd>
                                                </div>
                                            @endif
                                        @else
                                            <div><dt class="sr-only">Fecha límite del intento</dt><dd>—</dd></div>
                                        @endif
                                    @else
                                        <div>
                                            <dt>Fecha límite</dt>
                                            <dd class="whitespace-nowrap font-semibold text-acode-primary">
                                                @if ($row['endsAt'])
                                                    <time datetime="{{ $row['endsAt']->toAtomString() }}">{{ $row['endsAt']->format('d/m/Y H:i') }}</time>
                                                @else
                                                    Sin vencimiento
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </x-table.td>
                            <x-table.td align="center" class="whitespace-nowrap">{{ $row['durationMinutes'] }} min</x-table.td>
                            <x-table.td align="center">{{ $row['exam']->exam_questions_count }}</x-table.td>
                            <x-table.td align="center">
                                @if ($row['href'])
                                    <x-button
                                        :href="$row['href']"
                                        :variant="$row['actionVariant']"
                                        :aria-label="$row['actionLabel'] . ' · ' . $row['exam']->title . ($row['groupNames'] ? ', ' . $row['groupNames'] : '')"
                                        size="sm"
                                        class="whitespace-nowrap"
                                    >
                                        {{ $row['actionLabel'] }}
                                    </x-button>
                                @else
                                    <span class="text-xs text-slate-600">—</span>
                                @endif
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        <section class="min-w-0 space-y-3">
            <x-section-header
                title="Historial de intentos recientes"
                :subtitle="$attemptRows->isNotEmpty() ? 'Hasta ' . $attemptLimit . ' intentos vigentes de tus grupos activos.' : null"
                :institutional="true"
            />

            @if ($attemptRows->isEmpty())
                <x-empty-state
                    icon="reportBar"
                    title="Aún no hay intentos recientes"
                    message="Aquí verás el historial de tus grupos activos."
                />
            @else
                <p class="text-xs text-slate-700 md:hidden">Desliza la tabla para ver las acciones.</p>

                <x-table zebra="slate" scroll-label="Historial de intentos recientes">
                    <x-slot:header>
                        <x-table.th align="center">Evaluación</x-table.th>
                        <x-table.th align="center">Calificación</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        <x-table.th align="center">Detalle</x-table.th>
                    </x-slot:header>

                    @foreach ($attemptRows as $row)
                        <tr>
                            <th scope="row" class="px-4 py-3 text-left text-sm">
                                <p class="break-words font-semibold leading-snug text-acode-primary">{{ $row['attempt']->exam->title }}</p>
                                <p class="mt-0.5 break-words text-xs font-normal text-slate-600">{{ $row['attempt']->group?->name ?? 'Sin grupo' }}</p>
                                @if ($row['timestamp'])
                                    <p class="mt-1 text-xs font-normal text-slate-600">
                                        {{ $row['timestampLabel'] }}
                                        <time class="whitespace-nowrap" datetime="{{ $row['timestamp']->toAtomString() }}">{{ $row['timestamp']->format('d/m/Y H:i') }}</time>
                                    </p>
                                @endif
                            </th>
                            <x-table.td align="center">
                                <x-student.attempt-grade :grade="$row['grade']" />
                            </x-table.td>
                            <x-table.td align="center">
                                <x-badge :variant="$row['statusVariant']" :class="$row['statusVariant'] === 'danger' ? '!text-acode-danger-dark' : ''">
                                    {{ $row['statusLabel'] }}
                                </x-badge>
                            </x-table.td>
                            <x-table.td align="center">
                                @if ($row['actionHref'])
                                    <x-button
                                        :href="$row['actionHref']"
                                        :aria-label="$row['actionLabel'] . ' · ' . $row['attempt']->exam->title . ($row['attempt']->group ? ', ' . $row['attempt']->group->name : '')"
                                        variant="neutral"
                                        size="sm"
                                        class="whitespace-nowrap"
                                    >
                                        {{ $row['actionLabel'] }}
                                    </x-button>
                                @endif
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        <section class="min-w-0 space-y-3">
            <x-section-header
                title="Mis grupos"
                :subtitle="$enrolledGroups->count() > 3 ? 'Se muestran 3 de tus ' . $enrolledGroups->count() . ' grupos activos.' : null"
                action-label="Ver todos los grupos"
                :action-href="route('student.groups.index')"
                :institutional="true"
            />

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($enrolledGroups->take(3) as $group)
                    <x-card :title="$group->name" class="min-w-0 break-words" padding="p-4">
                        @if (filled($group->subject))
                            <p class="text-sm text-slate-700">{{ $group->subject }}</p>
                        @endif
                        @if ($group->teacher)
                            <p class="mt-0.5 text-xs text-slate-600">Profesor: {{ $group->teacher->full_name }}</p>
                        @endif
                        <a href="{{ route('student.grades.show', $group) }}" aria-label="Abrir calificaciones · {{ $group->name }}"
                           class="-ml-2 mt-1 inline-flex min-h-8 items-center rounded-lg px-2 text-xs font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                            Abrir calificaciones
                        </a>
                    </x-card>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
