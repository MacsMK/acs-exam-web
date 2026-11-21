{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/reviews/index.blade.php (MPW-V3 §4.1.2, §7, §8, RF-IAR-002, RW-11) --}}
@extends('layouts.app')

@section('title', 'Revisiones de Exámenes')

@section('content')
    @php
        $hasActiveFilters = filled($filters['search'] ?? null)
            || (filled($filters['status'] ?? null) && ($filters['status'] ?? 'all') !== 'all');

        $statusBadges = [
            'draft' => ['label' => 'Borrador', 'variant' => 'warning'],
            'scheduled' => ['label' => 'Programado', 'variant' => 'info'],
            'active' => ['label' => 'Activo', 'variant' => 'accent'],
            'closed' => ['label' => 'Cerrado', 'variant' => 'neutral'],
        ];
        $pendingRecotejeExams = (int) ($stats['pending_recoteje_exams'] ?? 0);
    @endphp

    <div class="space-y-6">
        <x-page-header
            title="Centro de revisiones docentes"
            subtitle="Recoteja y ajusta calificaciones provisionales emitidas por la IA para reactivos abiertos y de programación."
        >
            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    <x-icon name="document" class="w-4 h-4" />
                    Abrir exámenes
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Resumen rápido --}}
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-5" aria-label="Indicadores del centro de revisiones">
            <x-kpi-card
                label="Exámenes totales"
                :value="(int) ($stats['total'] ?? 0)"
                nav-icon="exams"
                tone="tech"
            />
            <x-kpi-card
                label="Exámenes activos"
                :value="(int) ($stats['published_active'] ?? 0)"
                icon="clock"
                tone="accent"
            />
            <x-kpi-card
                label="Pendientes de recoteje"
                :value="$pendingRecotejeExams"
                :icon="$pendingRecotejeExams > 0 ? 'warning' : 'success'"
                :tone="$pendingRecotejeExams > 0 ? 'warning' : 'neutral'"
            />
        </section>

        {{-- Filtros --}}
        <x-search-filter
            :action="route('teacher.reviews.index')"
            search-name="search"
            :search-value="$filters['search'] ?? ''"
            placeholder="Buscar examen por título…"
            :clear-url="route('teacher.reviews.index')"
            :clear-keys="['search', 'status']"
            :has-active-filters="$hasActiveFilters"
        >
            <x-slot:filters>
                <div class="w-full sm:w-52">
                    <x-form.select name="status" size="md" aria-label="Filtrar por estado">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Todos los estados</option>
                        <option value="pending_review" @selected(($filters['status'] ?? '') === 'pending_review')>Pendientes de recoteje</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option>
                        <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Cerrados</option>
                    </x-form.select>
                </div>
            </x-slot:filters>
        </x-search-filter>

        @if ($exams->isEmpty())
            <x-empty-state
                icon="document"
                :title="$hasActiveFilters ? 'Sin exámenes para esos filtros' : 'Sin exámenes registrados'"
                :message="$hasActiveFilters
                    ? 'Intenta limpiar los filtros de búsqueda.'
                    : 'Cuando crees exámenes y los alumnos comiencen a responder, aquí podrás revisar sus entregas y ajustar las ponderaciones de la IA.'"
            >
                <x-button variant="primary" :href="route('exams.create')">
                    <x-icon name="add" class="w-4 h-4" />
                    Crear examen
                </x-button>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($exams as $exam)
                    @php
                        $opStatus = $exam->operationalStatus();
                        $statusInfo = $statusBadges[$opStatus] ?? $statusBadges['draft'];
                        $pendingCount = (int) ($exam->pending_recoteje_count ?? $exam->pending_reviews_count ?? 0);
                    @endphp
                    <x-card class="flex flex-col justify-between hover:shadow-md transition-shadow">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <x-badge :variant="$statusInfo['variant']">
                                    {{ $statusInfo['label'] }}
                                </x-badge>

                                @if ($pendingCount > 0)
                                    <x-badge variant="warning">
                                        {{ $pendingCount }} por recotejar
                                    </x-badge>
                                @endif
                            </div>

                            <h2 class="text-lg font-bold text-acode-primary mb-2 line-clamp-2">
                                {{ $exam->title }}
                            </h2>

                            <div class="text-xs text-slate-600 space-y-1.5 mb-4">
                                <p><strong>Intentos registrados:</strong> {{ (int) ($exam->student_attempts_count ?? 0) }}</p>
                                <p><strong>Escala:</strong> {{ $exam->grading_scale }}</p>
                                <p>
                                    <strong>Visibilidad:</strong>
                                    {{ match ($exam->result_visibility_mode) {
                                        'final_score' => 'Calificación final',
                                        'estimated_score' => 'Calificación estimada',
                                        'objective_only_pending' => 'Solo puntaje objetivo (pendiente)',
                                        default => 'Oculto al alumno',
                                    } }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">
                                {{ $exam->created_at?->format('d/m/Y') }}
                            </span>
                            <x-button variant="secondary" size="sm" :href="route('exams.revision.index', $exam)">
                                Abrir intentos
                            </x-button>
                        </div>
                    </x-card>
                @empty
                    <div class="col-span-full">
                        <x-empty-state
                            icon="document"
                            title="Sin exámenes registrados"
                            message="Aún no hay exámenes disponibles para revisar."
                        />
                    </div>
                @endforelse
            </div>

            <div class="mt-6 flex justify-end">
                {{ $exams->links() }}
            </div>
        @endif
    </div>
@endsection
