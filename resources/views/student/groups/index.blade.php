{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/groups/index.blade.php
    Ubicación:   resources/views/student/groups/index.blade.php
    Descripción: Catálogo de Mis Grupos del estudiante homologado al UI Kit A-Code.
                 Permite consultar asignaturas matriculadas, docentes titulares,
                 estado de grupo y salida voluntaria (RF-GRU-010, RF-GRU-012;
                 MPW-V3 §7.3, §7.4.2, §7.8.2, §8.1–§8.6; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis Grupos')

@section('content')
<div
    x-data="{
        search: '',
        filter: 'all',
        leaveModalOpen: false,
        selectedGroup: null,
        groups: @js(
            collect($groups ?? auth()->user()->groupsAsStudent()->with(['teacher'])->get())->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject ?? 'Sin asignatura asignada',
                'teacher_name' => $group->teacher?->full_name ?? 'Docente titular',
                'is_closed' => (bool) $group->is_closed,
                'joined_at' => $group->pivot?->joined_at ? \Carbon\Carbon::parse($group->pivot->joined_at)->format('d/m/Y') : null,
                'exams_url' => route('student.exams.index'),
                'grades_url' => route('student.grades.show', $group->id),
                'leave_url' => route('student.groups.leave', $group->id),
            ])->values()
        ),

        get activeGroupsCount() {
            return this.groups.filter(g => !g.is_closed).length;
        },

        get uniqueTeachersCount() {
            return new Set(this.groups.map(g => g.teacher_name)).size;
        },

        get filteredGroups() {
            const term = this.search.trim().toLowerCase();

            return this.groups.filter(group => {
                const matchesSearch =
                    group.name.toLowerCase().includes(term) ||
                    group.subject.toLowerCase().includes(term) ||
                    group.teacher_name.toLowerCase().includes(term);

                const matchesStatus =
                    this.filter === 'all' ||
                    (this.filter === 'active' && !group.is_closed) ||
                    (this.filter === 'closed' && group.is_closed);

                return matchesSearch && matchesStatus;
            });
        },

        openLeaveModal(group) {
            this.selectedGroup = group;
            this.leaveModalOpen = true;
        },

        closeLeaveModal() {
            this.leaveModalOpen = false;
            this.selectedGroup = null;
        }
    }"
    @keydown.escape.window="closeLeaveModal()"
    class="space-y-6"
>
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Mis Grupos Matriculados"
        subtitle="Consulta las materias en las que estás inscrito, accede a sus evaluaciones y gestiona tu pertenencia escolar."
    >
        <x-slot:actions>
            <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Unirme a un grupo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjetas KPI Reactivas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Grupos inscritos"
            meta="Asignaturas en tu historial"
            nav-icon="groups"
            tone="tech"
        >
            <x-slot:valueSlot>
                <span x-text="groups.length">0</span>
            </x-slot:valueSlot>
        </x-kpi-card>

        <x-kpi-card
            label="Grupos activos"
            meta="Con evaluaciones habilitadas"
            icon="success"
            tone="accent"
        >
            <x-slot:valueSlot>
                <span x-text="activeGroupsCount">0</span>
            </x-slot:valueSlot>
        </x-kpi-card>

        <x-kpi-card
            label="Docentes titulares"
            meta="Profesores asignados"
            icon="userTie"
            tone="primary"
        >
            <x-slot:valueSlot>
                <span x-text="uniqueTeachersCount">0</span>
            </x-slot:valueSlot>
        </x-kpi-card>
    </div>

    {{-- Nivel 3: Barra de búsqueda y filtros --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="w-full lg:max-w-md">
                <label for="student-group-search" class="mb-2 block text-xs font-semibold text-slate-600">
                    Buscar grupo o asignatura
                </label>
                <x-form.input
                    id="student-group-search"
                    type="search"
                    prefixIcon="search"
                    x-model="search"
                    placeholder="Buscar por materia, grupo o nombre de profesor..."
                />
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <p class="mb-2 text-xs font-semibold text-slate-600">
                        Estado del grupo
                    </p>
                    <x-toggle-group
                        model="filter"
                        :options="['all' => 'Todos', 'active' => 'Activos', 'closed' => 'Cerrados']"
                        label="Filtrar grupos por estado"
                        class="flex-wrap"
                    />
                </div>

                <template x-if="search.trim() !== '' || filter !== 'all'">
                    <x-button type="button" variant="neutral" size="md" x-on:click="search = ''; filter = 'all'">
                        <x-icon name="filter" class="w-4 h-4 text-slate-500" />
                        Limpiar filtros
                    </x-button>
                </template>
            </div>
        </div>
    </div>

    {{-- Vista cuando no hay grupos registrados en la cuenta --}}
    <template x-if="groups.length === 0">
        <x-empty-state
            icon="userGroup"
            title="Aún no estás inscrito en ningún grupo"
            message="Para comenzar a resolver evaluaciones, solicita a tu profesor el folio escolar de tu asignatura e ingrésalo en el sistema."
        >
            <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Unirme a un grupo
            </x-button>
        </x-empty-state>
    </template>

    {{-- Nivel 4: Listado de grupos --}}
    <div x-show="groups.length > 0" class="space-y-4">
        <x-section-header title="Grupos inscritos">
            <x-slot:actions>
                <p class="text-xs font-medium text-slate-500">
                    Mostrando <span class="font-semibold text-acode-primary" x-text="filteredGroups.length"></span>
                    de <span class="font-semibold text-acode-primary" x-text="groups.length"></span> grupos
                </p>
            </x-slot:actions>
        </x-section-header>

        <div x-show="filteredGroups.length > 0"
             class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">

            <template x-for="group in filteredGroups" :key="group.id">
                <article class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech hover:shadow-md">
                    <div>
                        {{-- Cabecera de tarjeta --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                                <x-nav-icon name="groups" class="h-6 w-6" />
                            </div>

                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="group.is_closed
                                    ? 'bg-slate-100 text-slate-600 border border-slate-200'
                                    : 'bg-acode-accent/15 text-acode-primary border border-acode-accent/40'"
                                x-text="group.is_closed ? 'Ciclo cerrado' : 'Activo'">
                            </span>
                        </div>

                        {{-- Nombre y asignatura --}}
                        <h3 class="mt-4 break-words text-lg font-bold text-acode-primary leading-snug"
                            x-text="group.name">
                        </h3>

                        <p class="mt-1 text-sm font-medium text-acode-tech"
                           x-text="group.subject">
                        </p>

                        {{-- Datos del docente y fecha --}}
                        <div class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            <div class="flex items-center gap-2">
                                <x-icon name="userTie" class="h-4 w-4 text-slate-400 shrink-0" />
                                <span class="text-slate-500">Docente:</span>
                                <span class="font-medium text-slate-800" x-text="group.teacher_name"></span>
                            </div>

                            <template x-if="group.joined_at">
                                <div class="flex items-center gap-2">
                                    <x-icon name="clock" class="h-4 w-4 text-slate-400 shrink-0" />
                                    <span class="text-slate-500">Inscripción:</span>
                                    <span class="font-mono text-slate-700" x-text="group.joined_at"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Acciones de tarjeta --}}
                    <div class="mt-5 flex items-center justify-between gap-2 border-t border-slate-100 pt-4">
                        <x-button ::href="group.exams_url" variant="primary" size="sm" class="flex-1">
                            <x-nav-icon name="exams" class="h-4 w-4" />
                            Abrir exámenes
                        </x-button>

                        <x-button ::href="group.grades_url" variant="neutral" size="sm" title="Ver calificaciones del grupo">
                            <x-nav-icon name="grades" class="h-4 w-4 text-acode-tech" />
                            <span class="hidden sm:inline">Notas</span>
                        </x-button>

                        <x-button
                            variant="danger"
                            size="sm"
                            type="button"
                            x-on:click="openLeaveModal(group)"
                            title="Salir del grupo"
                            aria-label="Salir del grupo"
                        >
                            <x-icon name="delete" class="h-4 w-4" />
                        </x-button>
                    </div>
                </article>
            </template>
        </div>

        {{-- Sin coincidencias de búsqueda --}}
        <div x-show="filteredGroups.length === 0" x-cloak>
            <x-empty-state
                icon="search"
                title="No encontramos grupos con esos criterios"
                message="Prueba con otro término de búsqueda o restablece los filtros aplicados."
            >
                <x-button
                    variant="secondary"
                    size="sm"
                    type="button"
                    x-on:click="search = ''; filter = 'all'"
                >
                    Limpiar filtros
                </x-button>
            </x-empty-state>
        </div>
    </div>

    {{-- Modal institucional de confirmación de salida voluntaria de grupo (MPW-V3 §8.6, RF-GRU-012) --}}
    <div
        x-show="leaveModalOpen"
        x-cloak
        x-transition.opacity
        role="dialog"
        aria-modal="true"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
        <div
            @click.outside="closeLeaveModal()"
            class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl space-y-4"
        >
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-acode-danger/10 text-acode-danger">
                    <x-icon name="warning" class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-acode-primary">
                        ¿Deseas abandonar este grupo escolar?
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                        Estás a punto de salir del grupo
                        <span class="font-bold text-slate-800" x-text="selectedGroup ? selectedGroup.name : ''"></span>.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-3.5 text-xs text-acode-primary leading-relaxed">
                <p class="font-semibold mb-1">Advertencia de pertenencia académica</p>
                <p class="text-slate-700">Al confirmar, perderás el acceso a los exámenes y recursos del grupo. No se permite la salida si tienes un intento de examen en curso en este grupo.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button type="button" variant="neutral" size="md" @click="closeLeaveModal()">
                    Cancelar
                </x-button>

                <form :action="selectedGroup ? selectedGroup.leave_url : '#'" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="md">
                        Eliminar inscripción
                    </x-button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
