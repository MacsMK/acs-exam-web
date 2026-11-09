@extends('layouts.app')

@section('title', 'Mis grupos')

@section('content')

<div
    x-data="{
        search: '',
        filter: 'all',
        groups: @js(
            $groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
                'students_count' => $group->students_count,
                'is_closed' => (bool) $group->is_closed,
                'url' => route('groups.show', $group),
            ])->values()
        ),

        get filteredGroups() {
            const term = this.search.trim().toLocaleLowerCase();

            return this.groups
                .filter(group => {
                    const matchesSearch =
                        group.name.toLocaleLowerCase().includes(term);

                    const matchesStatus =
                        this.filter === 'all' ||
                        (this.filter === 'active' && !group.is_closed) ||
                        (this.filter === 'closed' && group.is_closed);

                    return matchesSearch && matchesStatus;
                })
                .sort((a, b) => {
                    if (a.is_closed !== b.is_closed) {
                        return Number(a.is_closed) - Number(b.is_closed);
                    }

                    return a.name.localeCompare(b.name, 'es');
                });
        }
    }"
    class="space-y-6"
>

    {{-- Regresar --}}
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-2 text-sm font-medium text-acode-tech hover:underline">
        <span aria-hidden="true">←</span>
        Volver a Inicio
    </a>

    {{-- Encabezado --}}
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

        <div>
            <h1 class="text-2xl font-bold text-acode-primary">
                Mis grupos
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Grupos Escolares: Consulta y administra los grupos que has creado.
            </p>
        </div>

        <a href="{{ route('groups.create') }}"
           class="inline-flex h-11 items-center justify-center rounded-lg bg-acode-primary px-5 text-sm font-semibold text-white transition hover:opacity-90">
            + Crear grupo
        </a>
    </div>

    {{-- Búsqueda y filtros --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

            <div class="w-full lg:max-w-sm">
                <label for="group-search"
                       class="mb-2 block text-xs font-semibold text-slate-600">
                    Buscar grupo
                </label>

                <input
                    id="group-search"
                    type="search"
                    x-model="search"
                    placeholder="Escribe el nombre del grupo..."
                    class="h-11 w-full rounded-lg border border-slate-300 px-4 text-sm outline-none transition focus:border-acode-tech focus:ring-2 focus:ring-acode-tech/20"
                >
            </div>

            <div>
                <p class="mb-2 text-xs font-semibold text-slate-600">
                    Estado
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

    {{-- Sin grupos registrados --}}
    @if ($groups->isEmpty())

        <div class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">

            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                <x-nav-icon name="groups" class="h-7 w-7" />
            </div>

            <h2 class="text-lg font-semibold text-acode-primary">
                Todavía no tienes grupos
            </h2>

            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Crea tu primer grupo para organizar a tus estudiantes
                y asignarles exámenes.
            </p>

            <a href="{{ route('groups.create') }}"
               class="mt-6 inline-flex rounded-lg bg-acode-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90">
                Crear mi primer grupo
            </a>

        </div>

    @else

        {{-- Resultados --}}
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-acode-primary">
                Grupos registrados
            </h2>

            <p class="text-sm text-slate-500">
                <span x-text="filteredGroups.length"></span>
                de
                {{ $groups->count() }}
                grupos
            </p>
        </div>

        {{-- Tarjetas --}}
        <div x-show="filteredGroups.length > 0"
             class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">

            <template x-for="group in filteredGroups" :key="group.id">

                <article class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">

                    <div class="flex items-start justify-between gap-3">

                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                            <x-nav-icon name="groups" class="h-6 w-6" />
                        </div>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="group.is_closed
                                ? 'bg-slate-100 text-slate-600'
                                : 'bg-acode-accent/10 text-teal-700'"
                            x-text="group.is_closed ? 'Cerrado' : 'Activo'">
                        </span>

                    </div>

                    <h3 class="mt-5 break-words text-lg font-bold text-acode-primary"
                        x-text="group.name">
                    </h3>

                    <p class="mt-1 min-h-5 text-sm text-slate-500"
                       x-text="group.subject || 'Sin materia especificada'">
                    </p>

                    <div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4 text-sm text-slate-600">

                        <x-nav-icon name="groups" class="h-5 w-5 text-acode-tech" />

                        <span x-text="group.students_count"></span>

                        <span x-text="group.students_count === 1 ? 'estudiante' : 'estudiantes'"></span>

                    </div>

                    <a :href="group.url"
                       class="mt-5 inline-flex h-11 items-center justify-center rounded-lg bg-acode-primary px-4 text-sm font-semibold text-white transition hover:opacity-90">
                        Ver grupo
                        <span class="ml-2" aria-hidden="true">→</span>
                    </a>

                </article>

            </template>

        </div>

        {{-- Sin coincidencias --}}
        <div x-show="filteredGroups.length === 0"
             x-cloak
             class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">

            <h3 class="text-base font-semibold text-acode-primary">
                No encontramos grupos
            </h3>

            <p class="mt-2 text-sm text-slate-500">
                Prueba con otro nombre o cambia el filtro seleccionado.
            </p>

            <button type="button"
                    @click="search = ''; filter = 'all'"
                    class="mt-5 rounded-lg border border-acode-tech px-5 py-2.5 text-sm font-semibold text-acode-tech hover:bg-acode-surface">
                Limpiar filtros
            </button>

        </div>

    @endif

</div>

@endsection
