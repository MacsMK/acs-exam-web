{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: evaluation-periods/index.blade.php (RF-CAL-001..005, MPW-V2 §7, §8) --}}
@extends('layouts.app')

@section('title', 'Periodos de Evaluación - ' . $group->name)

@section('content')
<div x-data="{
    openCreate: {{ $errors->any() ? 'true' : 'false' }},
    exams: {{ old('exams_weight', 50) }},
    homework: {{ old('homework_weight', 25) }},
    project: {{ old('project_weight', 15) }},
    other: {{ old('other_weight', 10) }},
    get total() {
        return (parseFloat(this.exams) || 0) + (parseFloat(this.homework) || 0) + (parseFloat(this.project) || 0) + (parseFloat(this.other) || 0);
    },
    get isValid() {
        return Math.abs(this.total - 100) < 0.01;
    }
}" class="space-y-6">

    {{-- Encabezado de Página --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                <a href="{{ route('groups.index') }}" class="hover:text-acode-tech">Grupos</a>
                <span>/</span>
                <a href="{{ route('groups.show', $group) }}" class="hover:text-acode-tech">{{ $group->name }}</a>
                <span>/</span>
                <span class="text-acode-primary">Periodos de Evaluación</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">Periodos de Evaluación</h1>
            <p class="text-sm text-slate-600 mt-1">Configuración de parciales, ponderaciones porcentuales y sábanas de notas del grupo <strong>{{ $group->name }}</strong>.</p>
        </div>

        <div class="flex items-center gap-2">
            <x-button :href="route('groups.show', $group)" variant="neutral" size="sm">
                <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90 mr-1" />
                Volver al Grupo
            </x-button>
            <x-button type="button" @click="openCreate = !openCreate" variant="primary" size="sm">
                <x-icon name="plus" class="w-4 h-4 mr-1.5" />
                Nuevo Periodo
            </x-button>
        </div>
    </div>

    {{-- Alerta de Notificaciones --}}
    @if (session('status'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800 text-sm font-medium flex items-center gap-2">
            <x-icon name="checkCircle" class="w-5 h-5 text-emerald-600 shrink-0" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Formulario Desplegable de Creación de Periodo (RF-CAL-001, RF-CAL-004, RF-CAL-005) --}}
    <div x-show="openCreate" x-cloak class="transition-all">
        <x-card class="border-2 border-acode-tech/30 shadow-md">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-acode-primary flex items-center gap-2">
                    <x-icon name="calendar" class="w-5 h-5 text-acode-tech" />
                    Registrar Nuevo Periodo de Evaluación
                </h2>
                <button type="button" @click="openCreate = false" class="text-slate-400 hover:text-slate-600 text-sm">
                    Cerrar &times;
                </button>
            </div>

            <form method="POST" action="{{ route('groups.periods.store', $group) }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">Nombre del Periodo <span class="text-rose-600">*</span></label>
                        <x-form.input id="name" name="name" type="text" placeholder="Ej: Primer Parcial" value="{{ old('name') }}" required />
                        @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="starts_at" class="block text-xs font-semibold text-slate-700 mb-1">Fecha de Inicio <span class="text-rose-600">*</span></label>
                        <x-form.input id="starts_at" name="starts_at" type="date" value="{{ old('starts_at', now()->format('Y-m-d')) }}" required />
                        @error('starts_at') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ends_at" class="block text-xs font-semibold text-slate-700 mb-1">Fecha de Cierre <span class="text-rose-600">*</span></label>
                        <x-form.input id="ends_at" name="ends_at" type="date" value="{{ old('ends_at', now()->addMonth()->format('Y-m-d')) }}" required />
                        @error('ends_at') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Configuración de Ponderaciones (RF-CAL-004, RF-CAL-005) --}}
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 mt-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                        <div>
                            <p class="text-sm font-bold text-slate-800">Ponderaciones de Rubros del Periodo (%)</p>
                            <p class="text-xs text-slate-500">La suma de los porcentajes debe ser exactamente 100 % (RF-CAL-005).</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase text-slate-500">Suma total:</span>
                            <span
                                class="px-2.5 py-1 text-xs font-mono font-bold rounded-full"
                                :class="isValid ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                x-text="total.toFixed(1) + ' %'"
                            ></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label for="exams_weight" class="block text-xs font-semibold text-slate-700 mb-1">Exámenes (%) <span class="text-rose-600">*</span></label>
                            <x-form.input id="exams_weight" name="exams_weight" type="number" step="0.5" min="0" max="100" x-model.number="exams" required />
                            @error('exams_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="homework_weight" class="block text-xs font-semibold text-slate-700 mb-1">Tareas (%) <span class="text-rose-600">*</span></label>
                            <x-form.input id="homework_weight" name="homework_weight" type="number" step="0.5" min="0" max="100" x-model.number="homework" required />
                            @error('homework_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="project_weight" class="block text-xs font-semibold text-slate-700 mb-1">Proyectos (%) <span class="text-rose-600">*</span></label>
                            <x-form.input id="project_weight" name="project_weight" type="number" step="0.5" min="0" max="100" x-model.number="project" required />
                            @error('project_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="other_weight" class="block text-xs font-semibold text-slate-700 mb-1">Otros (%) <span class="text-rose-600">*</span></label>
                            <x-form.input id="other_weight" name="other_weight" type="number" step="0.5" min="0" max="100" x-model.number="other" required />
                            @error('other_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p x-show="!isValid" class="text-xs font-medium text-rose-600 mt-2 flex items-center gap-1">
                        <x-icon name="exclamationCircle" class="w-4 h-4 shrink-0" />
                        Atención: Ajusta los valores para que la suma resulte exactamente 100 %.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" @click="openCreate = false" variant="neutral" size="sm">
                        Cancelar
                    </x-button>
                    <x-button type="submit" variant="primary" size="sm" ::disabled="!isValid">
                        Guardar Periodo
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- Listado de Periodos Configurados (RF-CAL-002) --}}
    <x-card>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-acode-primary">Periodos Registrados</h2>
            <span class="text-xs font-medium text-slate-500">{{ $periods->count() }} periodo(s) activo(s)</span>
        </div>

        <x-table>
            <x-slot:header>
                <x-table.th>Periodo</x-table.th>
                <x-table.th>Vigencia</x-table.th>
                <x-table.th>Fórmula de Evaluación</x-table.th>
                <x-table.th align="center">Calificados</x-table.th>
                <x-table.th align="right">Acciones</x-table.th>
            </x-slot:header>

            @forelse ($periods as $period)
                <tr>
                    <x-table.td>
                        <div class="font-bold text-slate-900">{{ $period->name }}</div>
                        <div class="text-xs text-slate-400">ID #{{ $period->id }}</div>
                    </x-table.td>
                    <x-table.td>
                        <div class="text-xs text-slate-600">
                            {{ $period->starts_at->format('d/m/Y') }} &mdash; {{ $period->ends_at->format('d/m/Y') }}
                        </div>
                    </x-table.td>
                    <x-table.td>
                        <code class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-xs text-slate-700 font-mono select-all">
                            {{ $period->formula }}
                        </code>
                    </x-table.td>
                    <x-table.td align="center">
                        <x-badge variant="{{ $period->student_grades_count > 0 ? 'accent' : 'neutral' }}">
                            {{ $period->student_grades_count }} alumno(s)
                        </x-badge>
                    </x-table.td>
                    <x-table.td align="right">
                        <x-button :href="route('groups.periods.gradebook', [$group, $period])" variant="primary" size="sm">
                            <x-icon name="documentText" class="w-4 h-4 mr-1.5" />
                            Sábana de Notas
                        </x-button>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-10"
                            icon="calendar"
                            title="No hay periodos de evaluación definidos"
                            message="Define el primer parcial o periodo escolar para este grupo y asigna las ponderaciones correspondientes."
                        >
                            <x-button type="button" @click="openCreate = true" variant="primary" size="sm">
                                <x-icon name="plus" class="w-4 h-4 mr-1.5" />
                                Crear Primer Periodo
                            </x-button>
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</div>
@endsection
