{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: evaluation-periods/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Editar periodo')

@section('content')
@php
    $back = route('groups.periods.index', $group);
    $originalStart = $period->starts_at->toDateString();
    $earliestStart = \App\Http\Requests\EvaluationPeriodStoreRequest::earliestStartDate();
    $minimumStart = $earliestStart->toDateString();
    $name = old('name', $period->name);
    $start = old('starts_at', $originalStart);
    $end = old('ends_at', $period->ends_at->toDateString());
    $start = is_string($start) ? $start : '';
    $end = is_string($end) ? $end : '';
@endphp

<div class="space-y-6">
    <x-page-header
        title="Editar periodo"
        :subtitle="$group->name . ' · ' . $period->name"
        :institutional="true"
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', $group)],
            ['label' => 'Periodos', 'href' => $back],
            ['label' => 'Editar periodo'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="$back" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="h-4 w-4" />
                Volver a periodos
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form id="edit-period-form" method="POST" action="{{ route('groups.periods.update', [$group, $period]) }}"
              class="space-y-5"
              x-data="{
                  start: @js($start),
                  end: @js($end),
                  originalStart: @js($originalStart),
                  minimumStart: @js($minimumStart),
                  startError: @js($errors->has('starts_at')),
                  endError: @js($errors->has('ends_at')),
                  saving: false,
                  get earliestStart() {
                      return this.start === this.originalStart && this.originalStart < this.minimumStart
                          ? this.originalStart : this.minimumStart;
                  },
                  get startIsTooEarly() {
                      return this.start && this.start < this.earliestStart;
                  },
                  get datesAreOutOfOrder() {
                      return this.start && this.end && this.end < this.start;
                  }
              }"
              x-init="$nextTick(() => $el.querySelector('[aria-invalid=true]')?.focus())"
              @pageshow.window="saving = false"
              @submit="if (saving) $event.preventDefault(); else saving = true">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-form.group name="name" label="Nombre del periodo" required>
                    <x-form.input name="name" size="md" :value="is_string($name) ? $name : ''"
                        maxlength="100" :aria-invalid="$errors->has('name') ? 'true' : null" required />
                </x-form.group>

                <x-form.group name="starts_at" label="Fecha de inicio" required>
                    <x-form.input name="starts_at" type="date" size="md" :value="$start"
                        :min="$start === $originalStart && $originalStart < $minimumStart ? $originalStart : $minimumStart"
                        x-model="start" x-bind:min="earliestStart"
                        aria-describedby="period-edit-start-help period-edit-start-error"
                        :aria-invalid="$errors->has('starts_at') ? 'true' : null"
                        x-bind:aria-invalid="Boolean(startIsTooEarly || startError).toString()"
                        required />
                    <p id="period-edit-start-help" class="text-xs text-slate-700">
                        Si cambias el inicio, elige una fecha a partir del {{ $earliestStart->format('d/m/Y') }}.
                    </p>
                    <p id="period-edit-start-error" x-show="startIsTooEarly" x-cloak role="status" class="text-xs text-acode-danger-dark">
                        Selecciona una fecha válida o conserva la fecha de inicio original.
                    </p>
                </x-form.group>

                <x-form.group name="ends_at" label="Fecha de cierre" required>
                    <x-form.input name="ends_at" type="date" size="md" :value="$end" :min="$start"
                        x-model="end" x-bind:min="start"
                        aria-describedby="period-edit-date-order"
                        :aria-invalid="$errors->has('ends_at') ? 'true' : null"
                        x-bind:aria-invalid="Boolean(datesAreOutOfOrder || endError).toString()"
                        required />
                    <p id="period-edit-date-order" class="text-xs text-slate-700">
                        La fecha de cierre puede ser igual o posterior al inicio.
                    </p>
                </x-form.group>
            </div>

            @include('evaluation-periods.partials.exam-selection', ['examSelectionPeriod' => $period])

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <x-unsaved-changes form-id="edit-period-form" :cancel-url="$back" />
                <x-button type="submit" variant="primary" size="md" ::disabled="saving">
                    <x-icon name="save" class="h-4 w-4" x-show="!saving" />
                    @include('evaluation-periods.partials.save-spinner')
                    <span x-text="saving ? 'Guardando…' : 'Guardar cambios'">Guardar cambios</span>
                </x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
