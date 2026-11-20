{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review-index.blade.php (MPW-V2 §4.1.2, §7, §8, RF-IAR-002, RW-11) --}}
@extends('layouts.app')

@section('title', 'Revisiones de Examen: ' . $exam->title)

@section('content')
    @php
        $hasActiveFilters = filled($filters['search'] ?? null)
            || (filled($filters['status'] ?? null) && ($filters['status'] ?? 'all') !== 'all');

        $attemptsCollection = $attempts->getCollection();
        $registeredAttemptsCount = (int) $attempts->total();
        $pendingRecotejeCount = $attemptsCollection->filter(
            static fn ($att): bool => $att->status === 'submitted'
                && $att->answers->contains(
                    static fn ($ans): bool => in_array($ans->examQuestion?->question?->question_type, ['open', 'code'], true)
                        && $ans->teacher_recoteje_score === null
                )
        )->count();
        $validatedGradesCount = $attemptsCollection->filter(
            static fn ($att): bool => $att->final_score !== null
        )->count();

        $attemptStatusLabels = [
            'in_progress' => 'En progreso',
            'submitted' => 'Entregado',
            'expired' => 'Expirado',
            'cancelled_by_incident' => 'Cancelado por incidencias',
            'graded' => 'Calificado',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Encabezado y Breadcrumbs --}}
        <x-page-header
            :title="$exam->title"
            subtitle="Revisa las entregas de tus estudiantes y valida las calificaciones asistidas por IA."
            :breadcrumbs="[
                ['label' => 'Revisión', 'href' => route('teacher.reviews.index')],
                ['label' => $exam->title],
            ]"
        >
            <x-slot:actions>
                <x-button :href="route('teacher.reviews.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- KPIs Resumen de Entregas --}}
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-5" aria-label="Resumen de entregas del examen">
            <x-kpi-card
                label="Entregas registradas"
                :value="$registeredAttemptsCount"
                icon="document"
                tone="tech"
            />
            <x-kpi-card
                label="Pendientes de recoteje"
                :value="$pendingRecotejeCount"
                :icon="$pendingRecotejeCount > 0 ? 'warning' : 'success'"
                :tone="$pendingRecotejeCount > 0 ? 'warning' : 'neutral'"
            />
            <x-kpi-card
                label="Calificaciones validadas"
                :value="$validatedGradesCount"
                icon="success"
                tone="accent"
            />
        </section>

        {{-- Búsqueda y filtro por estado de recoteje --}}
        <x-search-filter
            :action="route('exams.revision.index', $exam)"
            search-name="search"
            :search-value="$filters['search'] ?? ''"
            placeholder="Buscar estudiante por nombre o correo…"
            :clear-url="route('exams.revision.index', $exam)"
            :clear-keys="['search', 'status']"
            :has-active-filters="$hasActiveFilters"
        >
            <x-slot:filters>
                <div class="w-full sm:w-56">
                    <x-form.select name="status" size="md" aria-label="Filtrar intentos por estado">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Todos los intentos</option>
                        <option value="pending_recoteje" @selected(($filters['status'] ?? '') === 'pending_recoteje')>Requiere Recoteje</option>
                        <option value="graded" @selected(($filters['status'] ?? '') === 'graded')>Calificados</option>
                        <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Cancelados por incidencias</option>
                    </x-form.select>
                </div>
            </x-slot:filters>
        </x-search-filter>

        {{-- Tabla de Intentos --}}
        @if ($attempts->isEmpty())
            <x-empty-state
                icon="document"
                :title="$hasActiveFilters ? 'Sin intentos con esos filtros' : 'No hay intentos presentados para este examen aún'"
                :message="$hasActiveFilters
                    ? 'Intenta limpiar los filtros de búsqueda.'
                    : 'Los intentos aparecerán aquí en cuanto los estudiantes comiencen o entreguen su evaluación.'"
            />
        @else
            <x-table>
                <x-slot:header>
                    <x-table.th>Estudiante</x-table.th>
                    <x-table.th>Estado</x-table.th>
                    <x-table.th>Fecha de Envío</x-table.th>
                    <x-table.th align="right">Puntaje Objetivo</x-table.th>
                    <x-table.th align="right">Estimado IA</x-table.th>
                    <x-table.th align="right">Calificación Final</x-table.th>
                    <x-table.th align="center">Acciones</x-table.th>
                </x-slot:header>

                @forelse ($attempts as $attempt)
                    @php
                        $hasPendingAi = $attempt->status === 'submitted'
                            && $attempt->answers->contains(
                                fn ($ans): bool => in_array($ans->examQuestion?->question?->question_type, ['open', 'code'], true)
                                    && $ans->teacher_recoteje_score === null
                            );
                    @endphp
                    <tr>
                        <x-table.td class="font-medium text-slate-900">
                            <div class="font-bold text-acode-primary">{{ $attempt->student?->full_name ?? 'Estudiante' }}</div>
                            <div class="text-xs text-slate-500">{{ $attempt->student?->email ?? '—' }}</div>
                        </x-table.td>

                        <x-table.td>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if ($attempt->status === 'submitted')
                                    <x-badge variant="accent">Entregado</x-badge>
                                @elseif ($attempt->status === 'cancelled_by_incident')
                                    <x-badge variant="danger">Cancelado por Incidencias</x-badge>
                                @elseif ($attempt->status === 'expired')
                                    <x-badge variant="warning">Expirado</x-badge>
                                @else
                                    <x-badge variant="neutral">{{ $attemptStatusLabels[$attempt->status] ?? 'En curso' }}</x-badge>
                                @endif

                                @if ($hasPendingAi)
                                    <x-badge variant="warning">Requiere Recoteje</x-badge>
                                @endif
                            </div>
                        </x-table.td>

                        <x-table.td class="text-xs text-slate-600 whitespace-nowrap">
                            {{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m/Y H:i') : 'En curso' }}
                        </x-table.td>

                        <x-table.td align="right" class="font-mono text-slate-700">
                            {{ number_format((float) $attempt->objective_score, 2) }}
                        </x-table.td>

                        <x-table.td align="right" class="font-mono text-slate-700">
                            {{ number_format((float) $attempt->ai_estimated_score, 2) }}
                        </x-table.td>

                        <x-table.td align="right" class="font-mono font-bold {{ $attempt->final_score !== null ? 'text-acode-primary' : 'text-slate-400' }}">
                            {{ $attempt->final_score !== null ? number_format((float) $attempt->final_score, 2) : 'Pendiente' }}
                        </x-table.td>

                        <x-table.td align="center" class="whitespace-nowrap">
                            <x-button :href="route('exams.revision.show', [$exam, $attempt])" variant="neutral" size="sm">
                                Abrir recoteje
                            </x-button>
                        </x-table.td>
                    </tr>
                @empty
                    <tr>
                        <x-table.td colspan="7" class="py-8">
                            <x-empty-state
                                icon="document"
                                title="Sin intentos disponibles"
                                message="No hay intentos presentados para este examen aún."
                            />
                        </x-table.td>
                    </tr>
                @endforelse
            </x-table>

            <div class="mt-6 flex justify-end">
                {{ $attempts->links() }}
            </div>
        @endif
    </div>
@endsection
