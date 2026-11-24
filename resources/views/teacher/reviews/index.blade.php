{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/reviews/index.blade.php (MPW-V3 §4.1.2, §7, §8, RF-IAR-002, RW-11) --}}
@extends('layouts.app')

@section('title', 'Revisiones de Exámenes')

@section('content')
    @php
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $hasActiveFilters = $search !== '' || $status !== 'all';
        $centerUrl = route('teacher.reviews.index');
        $centerQuery = array_filter([
            'center_search' => $search !== '' ? $search : null,
            'center_status' => $status !== 'all' ? $status : null,
            'center_page' => $exams->currentPage() > 1 ? $exams->currentPage() : null,
        ], static fn ($value): bool => $value !== null);

        $statusBadges = [
            'draft' => ['label' => 'Borrador', 'variant' => 'warning'],
            'scheduled' => ['label' => 'Programado', 'variant' => 'info'],
            'active' => ['label' => 'Activo', 'variant' => 'accent'],
            'closed' => ['label' => 'Cerrado', 'variant' => 'neutral'],
        ];
        $resultCount = $exams->total();
        $resultStatus = $resultCount === 1 ? '1 examen encontrado' : $resultCount . ' exámenes encontrados';
    @endphp

    <div class="space-y-6">
        <x-page-header
            title="Centro de revisiones docentes"
            subtitle="Abre los intentos del examen para revisar las respuestas de tus estudiantes."
            institutional
        >
            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral">
                    <x-icon name="document" class="w-4 h-4" />
                    Abrir exámenes
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-search-filter
            :action="$centerUrl"
            search-label="Buscar exámenes"
            :search-value="$search"
            placeholder="Buscar por título o descripción…"
            results-id="teacher-review-exam-results"
            :clear-url="$centerUrl"
            :has-active-filters="$hasActiveFilters"
        >
            <x-slot:filters>
                <x-form.group name="status" label="Estado" class="sm:w-56">
                    <x-form.select name="status" size="sm">
                        <option value="all" @selected($status === 'all')>Todos los estados</option>
                        <option value="pending_review" @selected($status === 'pending_review')>Pendientes de revisión</option>
                        <option value="draft" @selected($status === 'draft')>Borradores</option>
                        <option value="scheduled" @selected($status === 'scheduled')>Programados</option>
                        <option value="active" @selected($status === 'active')>Activos</option>
                        <option value="closed" @selected($status === 'closed')>Cerrados</option>
                    </x-form.select>
                </x-form.group>
            </x-slot:filters>
        </x-search-filter>

        @fragment('teacher-review-exam-results')
            <section
                id="teacher-review-exam-results"
                class="space-y-4"
                aria-label="Exámenes para revisión"
                tabindex="-1"
                data-has-active-filters="{{ $hasActiveFilters ? '1' : '0' }}"
                data-search-status="{{ $resultStatus }}"
            >
                @if ($exams->isEmpty())
                    <x-empty-state
                        :icon="$hasActiveFilters ? 'search' : 'document'"
                        :title="$hasActiveFilters ? 'Sin exámenes para esos filtros' : 'Sin exámenes registrados'"
                        :message="$hasActiveFilters
                            ? 'Cambia la búsqueda o limpia los filtros para ver los exámenes.'
                            : 'Las entregas estarán disponibles para revisión cuando tus estudiantes presenten un examen.'"
                    >
                        @if ($hasActiveFilters)
                            <x-button :href="$centerUrl" variant="secondary" size="sm">
                                Limpiar filtros
                            </x-button>
                        @else
                            <x-button :href="route('exams.create')" variant="primary">
                                <x-icon name="add" class="w-4 h-4" />
                                Crear examen
                            </x-button>
                        @endif
                    </x-empty-state>
                @else
                    <p class="text-sm text-slate-700">{{ $resultStatus }}</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($exams as $exam)
                            @php
                                $statusInfo = $statusBadges[$exam->operationalStatus()] ?? $statusBadges['draft'];
                                $pendingCount = (int) ($exam->pending_recoteje_count ?? 0);
                                $attemptsCount = (int) ($exam->student_attempts_count ?? 0);
                                $attemptsLabel = $pendingCount === 1 ? '1 intento por revisar' : $pendingCount . ' intentos por revisar';
                            @endphp

                            <x-card padding="p-4" class="flex min-w-0 flex-col [&>.card-body]:flex [&>.card-body]:flex-1 [&>.card-body]:flex-col">
                                <div class="mb-3 space-y-2">
                                    <h2 class="break-words text-xl font-semibold leading-7 text-acode-primary">{{ $exam->title }}</h2>
                                    <div class="flex flex-wrap gap-1.5">
                                        @if ($pendingCount > 0)
                                            <x-badge variant="warning">{{ $attemptsLabel }}</x-badge>
                                        @endif
                                        <x-badge :variant="$statusInfo['variant']">{{ $statusInfo['label'] }}</x-badge>
                                    </div>
                                </div>

                                <dl class="mb-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
                                    <div>
                                        <dt class="inline font-medium">Intentos registrados</dt>
                                        <dd class="inline font-semibold">{{ $attemptsCount }}</dd>
                                    </div>
                                    <div>
                                        <dt class="inline font-medium">Escala</dt>
                                        <dd class="inline font-semibold">{{ $exam->grading_scale }}</dd>
                                    </div>
                                    <div>
                                        <dt class="inline font-medium">Visibilidad</dt>
                                        <dd class="inline font-semibold">
                                            {{ match ($exam->result_visibility_mode) {
                                                'final_score' => 'Calificación final',
                                                'estimated_score' => 'Calificación estimada',
                                                'objective_only_pending' => 'Solo puntaje objetivo (pendiente)',
                                                default => 'Oculto al alumno',
                                            } }}
                                        </dd>
                                    </div>
                                </dl>

                                <div class="mt-auto flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-3">
                                    @if ($exam->created_at)
                                        <span class="mr-auto text-xs text-slate-600">
                                            Creado el <time datetime="{{ $exam->created_at->toDateString() }}">{{ $exam->created_at->format('d/m/Y') }}</time>
                                        </span>
                                    @endif
                                    <x-button
                                        :href="route('exams.revision.index', [$exam, ...$centerQuery])"
                                        :variant="$pendingCount > 0 ? 'primary' : 'secondary'"
                                        :aria-label="'Abrir intentos de ' . $exam->title"
                                        size="sm"
                                    >
                                        Abrir intentos
                                    </x-button>
                                </div>
                            </x-card>
                        @endforeach
                    </div>

                    @if ($exams->hasPages())
                        <div class="flex justify-end">
                            {{ $exams->links() }}
                        </div>
                    @endif
                @endif

                <x-catalog-summary :title="$hasActiveFilters ? 'Resumen de los exámenes filtrados' : 'Resumen de exámenes'">
                    <x-summary-item label="Exámenes registrados" :value="(int) ($stats['total'] ?? 0)" icon="document" />
                    <x-summary-item label="Exámenes activos" :value="(int) ($stats['published_active'] ?? 0)" icon="success" />
                    <x-summary-item label="Exámenes con intentos pendientes" :value="(int) ($stats['pending_recoteje_exams'] ?? 0)" icon="clock" />
                </x-catalog-summary>
            </section>
        @endfragment
    </div>
@endsection
