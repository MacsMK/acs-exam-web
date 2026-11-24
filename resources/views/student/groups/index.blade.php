{{--
    Archivo: resources/views/student/groups/index.blade.php
    Consulta de grupos activos y salida voluntaria del estudiante (RF-GRU-010, RF-GRU-012).
--}}
@extends('layouts.app')

@section('title', 'Mis Grupos')

@section('content')
<div
    x-data="{
        search: '',
        groups: @js($groups->mapWithKeys(fn ($group) => [
            $group->id => mb_strtolower(implode(' ', [$group->name, $group->subject, $group->teacher?->full_name])),
        ])),
        selectedGroup: null,
        submitting: false,
        get visibleGroups() {
            const term = this.search.trim().toLowerCase();
            return Object.keys(this.groups).filter(id => this.groups[id].includes(term));
        }
    }"
    x-on:modal-closed="if ($event.detail === 'leave-student-group') selectedGroup = null"
    x-on:pageshow.window="submitting = false"
    class="space-y-6"
>
    <x-page-header :institutional="true" title="Mis Grupos Matriculados">
        @if ($groups->isNotEmpty())
            <x-slot:actions>
                <x-button :href="route('student.groups.join.form')" variant="secondary">
                    <x-icon name="add" class="h-4 w-4" />
                    Unirme a un grupo
                </x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($groups->isNotEmpty())
        <form role="search" aria-label="Mis grupos" x-on:submit.prevent="$refs.studentGroupResults.focus()">
            <x-search-panel>
                <x-slot:field>
                    <x-form.input
                        id="student-group-search"
                        type="search"
                        prefix-icon="search"
                        enterkeyhint="search"
                        x-ref="studentGroupSearch"
                        x-model.debounce.300ms="search"
                        placeholder="Buscar por materia, grupo o docente"
                        aria-label="Buscar por materia, grupo o docente"
                        :accessible="true"
                    />
                </x-slot:field>
                <x-slot:actions>
                    <x-button type="button" variant="secondary" x-show="search.trim() !== ''" x-cloak
                              x-on:click="search = ''; $refs.studentGroupSearch.focus()">
                        Limpiar búsqueda
                    </x-button>
                </x-slot:actions>
            </x-search-panel>
        </form>

        <section x-ref="studentGroupResults" aria-labelledby="student-group-heading" tabindex="-1"
                 class="space-y-4 rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
            <div id="student-group-heading">
                <x-section-header title="Grupos inscritos" :institutional="true">
                    <x-slot:actions>
                        <p role="status" class="text-sm text-slate-700"
                           x-text="'Mostrando ' + visibleGroups.length + ' de ' + Object.keys(groups).length + ' grupos'">
                            Mostrando {{ $groups->count() }} de {{ $groups->count() }} grupos
                        </p>
                    </x-slot:actions>
                </x-section-header>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" x-show="visibleGroups.length > 0">
                @foreach ($groups as $group)
                    <x-card padding="p-5" class="min-w-0" :data-group-id="$group->id"
                            x-show="visibleGroups.includes($el.dataset.groupId)">
                        <h3 class="break-words text-base font-semibold leading-6 text-acode-primary">{{ $group->name }}</h3>
                        @if (filled($group->subject))
                            <p class="mt-0.5 break-words text-sm text-slate-700">{{ $group->subject }}</p>
                        @endif
                        <div class="mt-3 space-y-1 text-sm text-slate-700">
                            <p class="break-words">Docente: {{ $group->teacher?->full_name ?? 'Docente titular' }}</p>
                            @if ($group->pivot?->joined_at)
                                <p>Inscripción: {{ \Illuminate\Support\Carbon::parse($group->pivot->joined_at)->format('d/m/Y') }}</p>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <x-button
                                :href="route('student.grades.show', $group)"
                                :aria-label="'Abrir calificaciones de '.$group->name"
                                variant="primary"
                                size="sm"
                            >
                                Abrir calificaciones
                            </x-button>
                            <x-button
                                variant="neutral"
                                size="sm"
                                type="button"
                                :aria-label="'Salir del grupo '.$group->name"
                                :data-group-name="$group->name"
                                :data-leave-url="route('student.groups.leave', $group)"
                                x-on:click="selectedGroup = { name: $el.dataset.groupName, url: $el.dataset.leaveUrl }; $dispatch('open-modal', 'leave-student-group')"
                            >
                                Salir del grupo
                            </x-button>
                        </div>
                    </x-card>
                @endforeach
            </div>

            <div x-show="visibleGroups.length === 0" x-cloak>
                <x-empty-state
                    icon="search"
                    title="No encontramos grupos con esos criterios"
                    message="Prueba con otro término de búsqueda."
                />
            </div>
        </section>

        <x-modal name="leave-student-group" title="¿Deseas abandonar este grupo escolar?" max-width="md" :focus-close="false">
            <p class="break-words">
                Al salir de <strong x-text="selectedGroup?.name"></strong> perderás el acceso a sus exámenes, calificaciones y recursos.
                La salida no está disponible si tienes un intento en curso en ese grupo.
            </p>

            <x-slot:footer>
                <div class="flex flex-wrap justify-end gap-2">
                    <x-button type="button" variant="neutral" autofocus x-on:click="$dispatch('close-modal', 'leave-student-group')">
                        Cancelar
                    </x-button>

                    <form method="POST" x-bind:action="selectedGroup?.url"
                          x-on:submit="submitting ? $event.preventDefault() : submitting = true">
                        @csrf
                        @method('DELETE')

                        <x-button type="submit" variant="danger" loading-when="submitting" x-bind:disabled="submitting">
                            <x-slot:label>Eliminar inscripción</x-slot:label>
                        </x-button>
                    </form>
                </div>
            </x-slot:footer>
        </x-modal>
    @else
        <x-empty-state
            icon="userGroup"
            title="Aún no estás inscrito en ningún grupo"
            message="Solicita el folio de tu clase a tu profesor para unirte."
        >
            <x-button :href="route('student.groups.join.form')" variant="primary">
                <x-icon name="add" class="h-4 w-4" />
                Unirme a un grupo
            </x-button>
        </x-empty-state>
    @endif
</div>
@endsection
