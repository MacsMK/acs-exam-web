{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/join.blade.php
    Ubicación:   resources/views/groups/join.blade.php
    Descripción: Formulario institucional para que el alumno se inscriba a un grupo
                 mediante folio alfanumérico (MPW-V3 §7.3, §7.8.2, §8.5; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Unirme a un grupo')

@section('content')
<div class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        :institutional="true"
        title="Unirme a un grupo"
        :breadcrumbs="[
            ['label' => 'Mis grupos', 'href' => route('student.groups.index')],
            ['label' => 'Unirme a un grupo'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="route('student.groups.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjeta de captura focalizada --}}
    <div class="mx-auto max-w-xl">
        <x-card padding="p-6 sm:p-8">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-acode-surface text-acode-tech">
                <x-nav-icon name="groups" class="h-7 w-7" />
            </div>

            @php($folio = old('access_folio', request('folio', '')))

            <form method="POST" action="{{ route('student.groups.join') }}" class="space-y-6"
                  x-data="{ submitting: false }"
                  x-on:submit="submitting ? $event.preventDefault() : submitting = true"
                  x-on:pageshow.window="submitting = false">
                @csrf

                <x-form.group
                    name="access_folio"
                    label="Folio de acceso"
                    help-text="Usa el folio que te compartió tu docente. Al unirte podrás consultar los exámenes del grupo."
                    :accessible="true"
                    required
                >
                    <x-form.input
                        name="access_folio"
                        size="lg"
                        :value="is_string($folio) ? strtoupper($folio) : ''"
                        x-on:input="$event.target.value = $event.target.value.toUpperCase()"
                        autocapitalize="characters"
                        autocomplete="off"
                        spellcheck="false"
                        required
                        maxlength="12"
                        placeholder="Ej. 7F3K9QRT"
                        :accessible="true"
                        :aria-describedby="$errors->has('access_folio') ? 'access_folio-help access_folio-error' : 'access_folio-help'"
                        class="mt-1 text-center font-mono font-bold uppercase tracking-widest text-acode-primary"
                    />
                </x-form.group>

                <div class="flex flex-col-reverse sm:flex-row items-center gap-3 pt-2">
                    <x-button :href="route('student.groups.index')" variant="neutral" size="md" class="w-full sm:flex-1">
                        Cancelar
                    </x-button>

                    <x-button type="submit" variant="primary" size="md" class="w-full sm:flex-1"
                              loading-when="submitting" x-bind:disabled="submitting">
                        <x-icon name="add" class="w-4 h-4" x-show="!submitting" />
                        <x-slot:label>Unirme al grupo</x-slot:label>
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection
