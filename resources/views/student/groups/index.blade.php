{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/groups/index.blade.php
    Descripción: Vista de Mis Grupos del estudiante.
                 Permite consultar asignaturas matriculadas, docentes titulares,
                 estado de grupo y salida voluntaria del grupo (RF-GRU-010, RF-GRU-012).
    Estándares:  MPW-V2 §7.3, §7.8, §8.3, §8.10.4, Tabla 20 (Verbos oficiales).
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
                'leave_url' => route('student.groups.leave', $group->id),
            ])->values()
        ),

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
    class="space-y-6"
>
    {{-- Encabezado de sección --}}
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                Mis Grupos Matriculados
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Consulta las materias en las que estás inscrito y gestiona tu pertenencia escolar (RF-GRU-010).
            </p>
        </div>

        <div class="flex items-center gap-3">
            <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                <x-icon name="add" class="h-4 w-4 mr-1.5" />
                Unirme a un grupo
            </x-button>
        </div>
    </div>

    {{-- Barra de búsqueda y filtros --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="w-full lg:max-w-md">
                <label for="student-group-search" class="mb-2 block text-xs font-semibold text-slate-600">
                    Buscar grupo o asignatura
                </label>
                <div class="relative">
                    <input
                        id="student-group-search"
                        type="search"
                        x-model="search"
                        placeholder="Buscar por materia, grupo o nombre de profesor..."
                        class="h-11 w-full rounded-lg border border-slate-300 pl-10 pr-4 text-sm outline-none transition focus:border-acode-tech focus:ring-2 focus:ring-acode-tech/20"
                    >
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-5 w-5" />
                    </div>
                </div>
            </div>

            <div>
                <p class="mb-2 text-xs font-semibold text-slate-600">
                    Estado del grupo
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            @click="filter = 'all'"
                            :class="filter === 'all'
                                ? 'bg-acode-primary text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="rounded-lg px-4 py-2.5 text-sm font-medium transition">
                        Todos
                    </button>
                    <button type="button"
                            @click="filter = 'active'"
                            :class="filter === 'active'
                                ? 'bg-acode-primary text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="rounded-lg px-4 py-2.5 text-sm font-medium transition">
                        Activos
                    </button>
                    <button type="button"
                            @click="filter = 'closed'"
                            :class="filter === 'closed'
                                ? 'bg-acode-primary text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="rounded-lg px-4 py-2.5 text-sm font-medium transition">
                        Cerrados
                    </button>
                </div>
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
                <x-icon name="add" class="h-4 w-4 mr-1.5" />
                Unirme a un grupo
            </x-button>
        </x-empty-state>
    </template>

    {{-- Listado de grupos --}}
    <div x-show="groups.length > 0" class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-acode-primary">
                Grupos inscritos
            </h2>
            <p class="text-sm text-slate-500">
                <span x-text="filteredGroups.length"></span>
                de
                <span x-text="groups.length"></span>
                grupos
            </p>
        </div>

        <div x-show="filteredGroups.length > 0"
             class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">

            <template x-for="group in filteredGroups" :key="group.id">
                <article class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div>
                        {{-- Cabecera de tarjeta --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                                <x-nav-icon name="groups" class="h-6 w-6" />
                            </div>

                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="group.is_closed
                                    ? 'bg-slate-100 text-slate-600'
                                    : 'bg-acode-accent/15 text-acode-accent border border-acode-accent/30'"
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
                        <a :href="group.exams_url"
                           class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-acode-primary px-3 text-xs font-semibold text-white transition hover:bg-acode-primary-dark">
                            <x-nav-icon name="exams" class="h-4 w-4 mr-1.5" />
                            Ver exámenes
                        </a>

                        <button
                            type="button"
                            @click="openLeaveModal(group)"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-acode-danger transition hover:bg-red-50 focus:outline-none"
                            title="Abandonar grupo (RF-GRU-012)">
                            <x-icon name="delete" class="h-4 w-4" />
                            <span class="ml-1 hidden sm:inline">Salir</span>
                        </button>
                    </div>
                </article>
            </template>
        </div>

        {{-- Sin coincidencias de búsqueda --}}
        <div x-show="filteredGroups.length === 0"
             x-cloak
             class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
            <h3 class="text-base font-semibold text-acode-primary">
                No encontramos grupos con esos criterios
            </h3>
            <p class="mt-2 text-sm text-slate-500">
                Prueba con otro término de búsqueda o restablece los filtros aplicados.
            </p>
            <button type="button"
                    @click="search = ''; filter = 'all'"
                    class="mt-4 rounded-lg border border-acode-tech px-4 py-2 text-xs font-semibold text-acode-tech hover:bg-acode-surface">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- Modal de confirmación de salida voluntaria de grupo (RF-GRU-012) --}}
    <div
        x-show="leaveModalOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    >
        <div
            @click.outside="closeLeaveModal()"
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4"
        >
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 text-acode-danger">
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

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 leading-relaxed">
                <p class="font-semibold text-amber-950 mb-1">Advertencia de antecedentes académicos (RF-GRU-012):</p>
                <p>Al confirmar, perderás la pertenencia al grupo y el acceso a sus exámenes y recursos. No se permite la salida si tienes un intento de examen en curso en este grupo.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button type="button" variant="neutral" size="sm" @click="closeLeaveModal()">
                    Cancelar operación
                </x-button>

                <form :action="selectedGroup ? selectedGroup.leave_url : '#'" method="POST" class="inline">
                    @csrf
                    <x-button type="submit" variant="danger" size="sm">
                        Confirmar salida
                    </x-button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
