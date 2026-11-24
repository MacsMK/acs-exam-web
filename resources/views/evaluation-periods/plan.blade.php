{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: evaluation-periods/plan.blade.php (MPW-V3 §4.1.2) --}}
@extends('layouts.app')
@section('title', 'Configuración de calificaciones')
@section('content')
@php
    $rubrics = ['exams' => 'Exámenes', 'homework' => 'Tareas', 'project' => 'Proyectos', 'other' => 'Otros'];
    $back = route('groups.periods.index', $group);
    $scale = old('scale', $plan['scale'] ?? null);
@endphp
<div class="space-y-6">
    <x-page-header
        :title="$period ? 'Ponderaciones de ' . $period->name : 'Calificación final del grupo'"
        :subtitle="$group->name"
        :institutional="true"
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', $group)],
            ['label' => 'Periodos', 'href' => $back],
            ['label' => $period ? 'Ponderaciones' : 'Calificación final'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="$back" variant="neutral">Volver a periodos</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" class="space-y-4"
          x-data="{
              scale: @js($scale),
              requiresPeriodTotal: @js($period !== null),
              totals: { period: 0, applications: 0, final: 0 },
              invalid: { period: false, applications: false, final: false },
              refreshTotals() {
                  const totals = { period: 0, applications: 0, final: 0 };
                  const invalid = { period: false, applications: false, final: false };
                  for (const field of this.$el.querySelectorAll('[data-weight]')) {
                      const group = field.dataset.weight;
                      const number = Number(field.value.replace(',', '.'));
                      if (field.validity.patternMismatch || field.validity.valueMissing || !Number.isFinite(number) || number > 100) {
                          invalid[group] = true;
                          continue;
                      }
                      totals[group] += Math.round(number * 100);
                  }
                  this.totals = totals;
                  this.invalid = invalid;
              },
              get weightsValid() {
                  return Object.values(this.invalid).every(invalid => !invalid)
                      && (!this.requiresPeriodTotal || this.totals.period === 10000)
                      && [0, 10000].includes(this.totals.applications)
                      && [0, 10000].includes(this.totals.final);
              },
              totalLabel(group) {
                  return (this.totals[group] / 100) + ' %';
              },
              totalHint(group) {
                  if (this.invalid[group]) return 'Captura valores de 0 a 100 con hasta dos decimales.';

                  const total = this.totals[group];
                  if (total === 10000 || (group !== 'period' && total === 0)) return '';

                  return total < 10000 ? 'Faltan ' + ((10000 - total) / 100) + ' %.'
                      : 'Reduce ' + ((total - 10000) / 100) + ' %.';
              }
          }"
          x-init="refreshTotals(); $nextTick(() => $el.querySelector('[aria-invalid=true]')?.focus())"
          @input="refreshTotals()">
        @csrf
        @if ($period)
            <x-card>
                <fieldset class="space-y-4">
                    <legend class="text-base font-semibold text-acode-primary">Porcentajes del periodo</legend>
                    <p class="text-sm text-slate-700">Los rubros y los periodos anteriores deben sumar 100 %.</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($rubrics as $key => $label)
                            @include('evaluation-periods.partials.weight-field', [
                                'section' => 'components', 'key' => $key, 'label' => $label,
                                'value' => $plan['components'][$key], 'required' => true, 'totalGroup' => 'period',
                            ])
                        @endforeach
                        @foreach ($periods->filter(fn ($item) => $item->starts_at < $period->starts_at) as $item)
                            @include('evaluation-periods.partials.weight-field', [
                                'section' => 'previous', 'key' => $item->id, 'label' => 'Periodo ' . $item->name,
                                'value' => $plan['previous'][$item->id] ?? null, 'totalGroup' => 'period',
                            ])
                        @endforeach
                    </div>
                    <p id="weight-period-total" class="text-sm font-semibold text-acode-primary" role="status" x-cloak>
                        Total: <span x-text="totalLabel('period')"></span>
                        <span class="ml-1 text-acode-danger-dark" x-text="totalHint('period')"></span>
                    </p>
                    @error('components') <p class="text-sm text-acode-danger-dark">{{ $message }}</p> @enderror
                    @error('previous') <p class="text-sm text-acode-danger-dark">{{ $message }}</p> @enderror
                </fieldset>
            </x-card>
            <x-card>
                <fieldset class="space-y-4">
                    <legend class="text-base font-semibold text-acode-primary">Aplicaciones del rubro de exámenes</legend>
                    <p class="text-sm text-slate-700">Si eliges aplicaciones, sus pesos deben sumar 100 % dentro del rubro.</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @forelse (collect($applications)->filter(fn ($application) => $application->assigned_period_id === null || $application->assigned_period_id === $period->id) as $application)
                            @include('evaluation-periods.partials.weight-field', [
                                'section' => 'exams', 'key' => $application->id, 'label' => $application->title,
                                'value' => $plan['exams'][$application->id] ?? null, 'totalGroup' => 'applications',
                            ])
                        @empty
                            <p class="text-sm text-slate-700 sm:col-span-2">No hay aplicaciones disponibles para este periodo.</p>
                        @endforelse
                    </div>
                    <p id="weight-applications-total" class="text-sm font-semibold text-acode-primary" role="status" x-cloak>
                        Total: <span x-text="totalLabel('applications')"></span>
                        <span class="ml-1 text-acode-danger-dark" x-text="totalHint('applications')"></span>
                    </p>
                    @error('exams') <p class="text-sm text-acode-danger-dark">{{ $message }}</p> @enderror
                </fieldset>
            </x-card>
        @else
            <x-card>
                <fieldset class="space-y-3">
                    <legend class="text-base font-semibold text-acode-primary">Escala y mínimo aprobatorio</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.group name="scale" label="Escala del grupo" required>
                            <x-form.select name="scale" required x-model="scale"
                                @change="$nextTick(() => window.academicNumber?.validate($refs.minimum))"
                                :aria-invalid="$errors->has('scale') ? 'true' : null">
                                <option value="">Seleccionar</option>
                                <option value="0-10" @selected($scale === '0-10')>0–10</option>
                                <option value="0-100" @selected($scale === '0-100')>0–100</option>
                            </x-form.select>
                        </x-form.group>
                        <x-form.group name="minimum" label="Mínimo aprobatorio" required>
                            <x-form.input name="minimum" :numeric="$scale === '0-100' ? 'integer' : 'decimal'" required min="0"
                                x-ref="minimum"
                                :max="$scale === '0-10' ? 10 : 100" ::max="scale === '0-10' ? 10 : 100"
                                ::data-numeric="scale === '0-100' ? 'integer' : 'decimal'"
                                ::inputmode="scale === '0-100' ? 'numeric' : 'decimal'"
                                ::pattern="scale === '0-100' ? '[0-9]+' : '[0-9]+([.,][0-9]{1,2})?'"
                                aria-describedby="minimum-format"
                                :value="old('minimum', $plan['minimum'])"
                                :aria-invalid="$errors->has('minimum') ? 'true' : null" />
                            <p id="minimum-format" class="mt-1 text-xs text-slate-700"
                               x-text="scale === '0-100' ? 'Entero de 0 a 100.' : 'De 0 a 10, con hasta dos decimales.'"></p>
                        </x-form.group>
                    </div>
                    @if (config('grading.manual_input_for_ten') === null)
                        <p x-show="scale === '0-10'" x-cloak class="text-sm text-slate-700">En escala 0–10, la captura manual quedará disponible cuando se defina su unidad. Las notas exactas de exámenes sí pueden integrarse.</p>
                    @endif
                </fieldset>
            </x-card>
            <x-card>
                <fieldset class="space-y-4">
                    <legend class="text-base font-semibold text-acode-primary">Periodos de la calificación final</legend>
                    @if ($periods->isEmpty())
                        <p class="text-sm text-slate-700">Todavía no hay periodos para ponderar. Puedes guardar la escala y el mínimo antes de crearlos.</p>
                        <x-button :href="$back" variant="secondary" size="sm">Abrir periodos</x-button>
                    @else
                        <p class="text-sm text-slate-700">Si eliges periodos, sus porcentajes deben sumar 100 %.</p>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($periods as $item)
                                @include('evaluation-periods.partials.weight-field', [
                                    'section' => 'periods', 'key' => $item->id, 'label' => $item->name,
                                    'value' => $plan['periods'][$item->id] ?? null, 'totalGroup' => 'final',
                                ])
                            @endforeach
                        </div>
                        <p id="weight-final-total" class="text-sm font-semibold text-acode-primary" role="status" x-cloak>
                            Total: <span x-text="totalLabel('final')"></span>
                            <span class="ml-1 text-acode-danger-dark" x-text="totalHint('final')"></span>
                        </p>
                    @endif
                    @error('periods') <p class="text-sm text-acode-danger-dark">{{ $message }}</p> @enderror
                </fieldset>
            </x-card>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <x-button :href="$back" variant="neutral">Cancelar</x-button>
            <x-button type="submit" ::disabled="!weightsValid">Guardar configuración</x-button>
        </div>
    </form>
</div>
@endsection
