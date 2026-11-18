@extends('layouts.app')

@section('title', 'Crear grupo')

@section('content')
    <div class="mx-auto w-full max-w-5xl">

        {{-- Título de la pantalla --}}
        <h1 class="mb-8 text-3xl font-bold tracking-tight text-acode-primary">
            Crear grupo
        </h1>

        {{-- Formulario --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">

            <h2 class="mb-7 text-xl font-bold text-acode-primary">
                Información del grupo
            </h2>

            <form
                id="create-group-form"
                method="POST"
                action="{{ route('groups.store') }}"
                class="space-y-6"
            >
                @csrf

                {{-- Nombre del grupo --}}
                <div>
                    <x-form.group name="name" label="Nombre del grupo" required>
                        <x-form.input
                            name="name"
                            size="lg"
                            value="{{ old('name') }}"
                            required
                            maxlength="120"
                            placeholder="Ej. Ingeniería de Software 5A"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        />
                    </x-form.group>
                </div>

                {{-- Materia --}}
                <div>
                    <x-form.group name="subject" label="Materia (opcional)">
                        <x-form.input
                            name="subject"
                            size="lg"
                            value="{{ old('subject') }}"
                            maxlength="120"
                            placeholder="Ej. Auditoría de Sistemas"
                            aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                        />
                    </x-form.group>
                </div>

                {{-- Información del folio --}}
                <div class="flex items-start gap-3 rounded-lg bg-teal-50 px-5 py-4 text-acode-primary">

                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-acode-accent"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" d="M12 11v5m0-8h.01"/>
                    </svg>

                    <p class="text-sm font-medium leading-relaxed">
                        El código de incorporación se generará automáticamente
                        al guardar el grupo.
                    </p>
                </div>

                {{-- Acciones --}}
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4">

                    <x-unsaved-changes
                        form-id="create-group-form"
                        :cancel-url="route('groups.index')"
                    />

                    <x-button type="submit" size="lg">
                        Crear grupo
                    </x-button>

                </div>

            </form>
        </div>

    </div>
@endsection