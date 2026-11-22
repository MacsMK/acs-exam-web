{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/edit.blade.php
    Ubicación:   resources/views/groups/edit.blade.php
    Descripción: Formulario para modificar nombre, materia y descripción de un
                 grupo activo (RF-GRU-003, ACT-WEB-020; MPW-V3 §8.1–§8.12).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Editar grupo')

@section('content')
<div class="space-y-6">

    <x-page-header
        title="Editar grupo"
        subtitle="Modifica los datos generales del grupo. El folio de acceso y los estudiantes inscritos no cambian."
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', $group)],
            ['label' => 'Editar grupo'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="route('groups.show', $group)" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Información del grupo" subtitle="Solo los grupos activos pueden modificarse.">
        <form
            id="edit-group-form"
            method="POST"
            action="{{ route('groups.update', $group) }}"
            class="space-y-6"
        >
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-form.group name="name" label="Nombre del grupo" required>
                    <x-form.input
                        name="name"
                        size="md"
                        value="{{ old('name', $group->name) }}"
                        required
                        maxlength="120"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                    />
                </x-form.group>

                <x-form.group name="subject" label="Materia (opcional)">
                    <x-form.input
                        name="subject"
                        size="md"
                        value="{{ old('subject', $group->subject) }}"
                        maxlength="120"
                        aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                    />
                </x-form.group>
            </div>

            <x-form.group name="description" label="Descripción (opcional)" help-text="Hasta 500 caracteres.">
                <x-form.textarea
                    name="description"
                    rows="3"
                    maxlength="500"
                    aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}"
                >{{ old('description', $group->description) }}</x-form.textarea>
            </x-form.group>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <x-unsaved-changes
                    form-id="edit-group-form"
                    :cancel-url="route('groups.show', $group)"
                />

                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="save" class="w-4 h-4" />
                    Guardar cambios
                </x-button>
            </div>
        </form>
    </x-card>

</div>
@endsection
