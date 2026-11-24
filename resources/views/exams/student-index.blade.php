@extends('layouts.app')

@section('title', 'Mis Exámenes Asignados')

@section('content')
<div class="space-y-6">
    <x-page-header title="Mis Exámenes Asignados" institutional>
        @if ($hasGroups)
            <x-slot:actions>
                <x-button :href="route('student.grades.index')" variant="secondary">
                    <x-nav-icon name="grades" class="h-4 w-4" />
                    Abrir calificaciones
                </x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($hasAssignedExams)
        <x-search-filter
            :action="route('student.exams.index')"
            search-label="Buscar examen"
            :search-value="$search"
            placeholder="Buscar por título, descripción o grupo…"
            results-id="student-exam-results"
            :clear-url="route('student.exams.index')"
            :has-active-filters="$hasActiveFilters"
            live-clear
        >
            <x-slot:filters>
                <x-form.group name="status" label="Estado" class="min-w-0 sm:w-60">
                    <x-form.select name="status" size="sm" accessible>
                        <option value="all" @selected($status === 'all')>Todos</option>
                        @foreach ($sectionTitles as $key => $title)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $title }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.group>
            </x-slot:filters>
        </x-search-filter>
    @endif

    @fragment('student-exam-results')
        <div
            id="student-exam-results"
            role="region"
            aria-label="Resultados de exámenes asignados"
            tabindex="-1"
            class="space-y-6 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech"
            data-has-active-filters="{{ $hasActiveFilters ? '1' : '0' }}"
            data-search-status="{{ trans_choice('{0} No se encontraron exámenes|{1} 1 examen encontrado|[2,*] :count exámenes encontrados', $exams->total()) }}"
        >
            @if ($exams->isNotEmpty())
                <p class="text-xs text-slate-700">
                    Mostrando {{ $exams->firstItem() }}–{{ $exams->lastItem() }} de {{ $exams->total() }}.
                    Zona horaria: {{ $timeZone }}.
                </p>
            @endif

            @forelse ($sections as $key => $rows)
                <section class="space-y-3" aria-label="{{ $sectionTitles[$key] }}">
                    <x-section-header :title="$sectionTitles[$key]" institutional />
                    <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($rows as $row)
                            @include('exams.partials._student-exam-card', ['row' => $row, 'showCatalogMetadata' => true])
                        @endforeach
                    </div>
                </section>
            @empty
                @if (! $hasGroups)
                    <x-empty-state
                        icon="userGroup"
                        title="Aún no tienes grupos inscritos"
                        message="Solicita el folio de tu clase a tu profesor para unirte."
                    >
                        <x-button :href="route('student.groups.join.form')" variant="primary">Unirme a un grupo</x-button>
                    </x-empty-state>
                @elseif (! $hasAssignedExams)
                    <x-empty-state
                        icon="clock"
                        title="No tienes exámenes asignados"
                        message="Los exámenes publicados para tus grupos aparecerán aquí."
                    />
                @else
                    <x-empty-state
                        icon="search"
                        title="No hay exámenes que coincidan"
                        message="Cambia la búsqueda o limpia los filtros para ver otros exámenes."
                    />
                @endif
            @endforelse

            {{ $exams->links('pagination.acode', ['ariaLabel' => 'Paginación de exámenes']) }}
        </div>
    @endfragment
</div>
@endsection
