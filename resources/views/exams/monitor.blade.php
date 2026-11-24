{{-- Proyecto: acs-exam-web | Monitoreo docente de intentos (MPW-V3 §§7–8, RW-12, RW-16) --}}
@extends('layouts.app')

@section('title', 'Monitoreo - ' . $exam->title)

@section('content')
    @php
        $monitorUrl = route('exams.monitor', $exam);
        $filterParams = array_filter($monitorFilters, static fn ($value, $key) => $key === 'status' ? $value !== 'all' : $value !== '', ARRAY_FILTER_USE_BOTH);
        $refreshUrl = route('exams.monitor', ['exam' => $exam] + $filterParams);
        $statusBadges = [
            'in_progress' => ['label' => 'En examen', 'variant' => 'success'],
            'submitted' => ['label' => 'Entregado', 'variant' => 'info'],
            'expired' => ['label' => 'Expirado', 'variant' => 'warning'],
            'cancelled_by_incident' => ['label' => 'Cancelado por incidencias', 'variant' => 'danger'],
            'not_started' => ['label' => 'Sin iniciar', 'variant' => 'neutral'],
        ];
        $metrics = [
            'enrolled' => ['label' => 'Matriculados', 'icon' => 'userGroup', 'tone' => 'tech'],
            'inProgress' => ['label' => 'En examen', 'icon' => 'clock', 'tone' => 'accent'],
            'submitted' => ['label' => 'Entregados', 'icon' => 'success', 'tone' => 'tech'],
            'incidents' => ['label' => 'Incidentes reportados', 'icon' => 'shield', 'tone' => 'neutral'],
        ];
        $gradingScale = str_replace('-', '–', (string) $exam->grading_scale);
        $monitorData = [
            'examId' => $exam->id,
            'snapshot' => $monitorSnapshot,
            'snapshotUrl' => $snapshotUrl,
            'realtime' => $reverbConfig,
            'authUrl' => url('/broadcasting/auth'),
            'csrfToken' => csrf_token(),
            'filters' => $monitorFilters,
        ];
    @endphp

    <div class="space-y-5 sm:space-y-6" x-data="typeof examMonitor === 'undefined' ? { ready: false } : examMonitor">
        <script type="application/json" id="exam-monitor-data">{!! \Illuminate\Support\Js::encode($monitorData) !!}</script>

        <x-page-header
            :title="'Monitoreo · ' . $exam->title"
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => $exam->title, 'href' => route('exams.review', $exam)],
                ['label' => 'Monitoreo'],
            ]"
        >
            <x-slot:badge>
                <span role="status" aria-atomic="true" tabindex="-1" x-ref="monitorConnection"
                      class="rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                    <x-badge variant="neutral" x-show="!ready">Sin conexión en tiempo real</x-badge>
                    <x-badge variant="success" x-cloak x-show="ready && connectionState === 'live'" x-text="ready ? connectionLabel : ''"></x-badge>
                    <x-badge variant="warning" x-cloak x-show="ready && connectionState !== 'live'" x-text="ready ? connectionLabel : ''"></x-badge>
                </span>
            </x-slot:badge>

            <x-slot:meta>
                <p class="text-slate-700" x-show="!ready">
                    Última actualización a las <time datetime="{{ $monitorSnapshot['observedAt'] }}">{{ $monitorSnapshot['observedAtLabel'] }}</time>.
                </p>
                <p class="text-slate-700" x-cloak x-show="ready && connectionMessage" x-text="ready ? connectionMessage : ''"></p>
                <span x-show="!ready || connectionState === 'offline' || connectionState === 'reconnecting'">
                    <x-button :href="$refreshUrl" variant="primary" size="sm"
                              x-on:click="if (ready) { $event.preventDefault(); $refs.monitorConnection.focus(); retry() }">
                        Reintentar
                    </x-button>
                </span>
            </x-slot:meta>

            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral">
                    <x-icon name="arrowLeft" class="h-4 w-4" />
                    Exámenes
                </x-button>
                <x-button :href="route('exams.assemble', $exam)" variant="secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Abrir estructura
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <section class="space-y-3" aria-labelledby="monitor-summary-title">
            <x-section-header id="monitor-summary-title" title="Resumen del examen"
                              subtitle="Los totales corresponden al examen completo. Los filtros solo cambian el listado."
                              :institutional="true" />

            <div class="grid grid-cols-1 gap-3 min-[400px]:grid-cols-2 md:gap-4 xl:grid-cols-4">
                @foreach ($metrics as $key => $metric)
                    <x-kpi-card :label="$metric['label']" :icon="$metric['icon']" :tone="$metric['tone']" radius="xl">
                        <x-slot:valueSlot>
                            <span x-text="ready ? counts.{{ $key }} : $el.dataset.initial"
                                  data-initial="{{ $monitorSnapshot['counts'][$key] }}">{{ $monitorSnapshot['counts'][$key] }}</span>
                        </x-slot:valueSlot>
                    </x-kpi-card>
                @endforeach
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="monitor-students-title">
            <x-section-header id="monitor-students-title" title="Supervisión de estudiantes"
                              :subtitle="\App\Models\Exam::timezoneLabel()" :institutional="true" />

            <span class="sr-only" role="status" aria-atomic="true" x-text="ready ? announcement : ''"></span>
            <span class="sr-only" role="alert" aria-atomic="true" x-text="ready ? criticalAnnouncement : ''"></span>
            <div class="space-y-2" x-ref="monitorAlerts" x-cloak x-show="ready && alerts.length > 0">
                <template x-for="notice in (ready ? alerts : [])" :key="notice.id">
                    <x-card padding="p-3" x-bind:class="notice.type === 'danger' ? 'border-l-4 border-l-acode-danger' : 'border-l-4 border-l-acode-warning'">
                        <div class="flex items-center justify-between gap-3">
                            <p class="flex items-center gap-2 text-sm font-semibold text-acode-primary">
                                <x-icon name="warning" class="h-5 w-5 shrink-0" />
                                <span x-text="notice.text"></span>
                            </p>
                            <x-button type="button" variant="neutral" size="sm" :icon-only="true"
                                      class="shrink-0" data-monitor-alert-dismiss
                                      x-on:click="removeAlert(notice.id, $event)"
                                      x-bind:aria-label="'Descartar aviso: ' + notice.text">
                                <x-icon name="xMark" class="h-4 w-4" />
                            </x-button>
                        </div>
                    </x-card>
                </template>
            </div>

            <form method="GET" action="{{ $monitorUrl }}" role="search" aria-label="Estudiantes supervisados"
                  x-on:submit="if (ready) { $event.preventDefault(); focusResults() }">
                <x-search-panel>
                    <x-slot:field>
                        <x-form.group field-id="monitor-search" label="Buscar por nombre o correo">
                            <x-form.input id="monitor-search" name="search" type="search" prefix-icon="search"
                                          enterkeyhint="search" maxlength="200" accessible
                                          :value="$monitorFilters['search']" x-ref="monitorSearch"
                                          x-on:input="if (ready) searchQuery = $event.target.value"
                                          x-effect="if (ready && $el.value !== searchQuery) $el.value = searchQuery" />
                        </x-form.group>
                    </x-slot:field>
                    <x-slot:actions>
                        @if ($filterParams)
                            <x-button :href="$monitorUrl" variant="secondary" x-show="!ready">Limpiar filtros</x-button>
                        @endif
                        <x-button type="button" variant="secondary" x-cloak x-show="ready && hasFilters" x-on:click="clearFilters()">
                            Limpiar filtros
                        </x-button>
                    </x-slot:actions>
                    <x-slot:filters>
                        <x-form.group field-id="monitor-status" label="Estado" class="sm:w-52">
                            <x-form.select id="monitor-status" name="status" size="sm" accessible
                                           x-on:change="if (ready) statusFilter = $event.target.value"
                                           x-effect="if (ready) $el.value = statusFilter">
                                <option value="all" @selected($monitorFilters['status'] === 'all')>Todos los estados</option>
                                @foreach ($statusBadges as $status => $badge)
                                    <option value="{{ $status }}" @selected($monitorFilters['status'] === $status)>{{ $badge['label'] }}</option>
                                @endforeach
                            </x-form.select>
                        </x-form.group>
                    </x-slot:filters>
                    <x-slot:feedback>
                        <p class="text-sm text-slate-700" role="status" aria-atomic="true"
                           data-initial="{{ count($visibleStudents) }} de {{ $monitorSnapshot['counts']['enrolled'] }} estudiantes"
                           x-text="ready ? filteredStudents.length + ' de ' + counts.enrolled + ' estudiantes' : $el.dataset.initial">
                            {{ count($visibleStudents) }} de {{ $monitorSnapshot['counts']['enrolled'] }} estudiantes
                        </p>
                    </x-slot:feedback>
                </x-search-panel>
            </form>

            <div x-show="!ready">
                @if ($visibleStudents)
                    @include('exams.partials._monitor-table', ['reactive' => false])
                @else
                    @include('exams.partials._monitor-empty', ['withoutStudents' => $monitorSnapshot['counts']['enrolled'] === 0])
                @endif
            </div>

            <div x-cloak x-show="ready">
                <div x-show="ready && filteredStudents.length > 0" x-ref="monitorResults"
                     role="region" aria-label="Resultados de la supervisión" tabindex="-1"
                     class="rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                    @include('exams.partials._monitor-table', ['reactive' => true])
                </div>
                <div x-show="ready && filteredStudents.length === 0" x-ref="monitorEmptyResults"
                     role="region" aria-label="Resultados de la supervisión" tabindex="-1"
                     class="rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                    <template x-if="ready && counts.enrolled === 0">
                        @include('exams.partials._monitor-empty', ['withoutStudents' => true])
                    </template>
                    <template x-if="ready && counts.enrolled > 0">
                        @include('exams.partials._monitor-empty', ['withoutStudents' => false])
                    </template>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/exam-monitor.js') }}"></script>
@endpush
