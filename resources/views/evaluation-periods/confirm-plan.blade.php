{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: evaluation-periods/confirm-plan.blade.php (MPW-V3 §4.1.2) --}}
@extends('layouts.app')
@section('title', 'Confirmar cambios de calificaciones')
@section('content')
@php
    $labels = [
        'scale' => 'Escala', 'minimum' => 'Mínimo aprobatorio',
        'exams' => 'Exámenes', 'homework' => 'Tareas', 'project' => 'Proyectos', 'other' => 'Otros',
    ];
    $target = $period ? route('groups.periods.plan.update', [$group, $period]) : route('groups.final-plan.update', $group);
    $formatValue = function ($value, $percent) {
        if ($value === null) {
            return 'Sin asignar';
        }

        return \App\Support\AcademicNumber::forInput($value) . ($percent ? ' %' : '');
    };
@endphp

<div class="space-y-5">
    <x-page-header
        title="Confirmar cambios de calificaciones"
        :subtitle="$period ? $period->name . ' · ' . $group->name : $group->name"
        :institutional="true"
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', $group)],
            ['label' => 'Periodos', 'href' => route('groups.periods.index', $group)],
            ['label' => 'Confirmar cambios'],
        ]"
    />
    <p class="rounded-xl border border-acode-warning/50 bg-acode-warning/15 p-4 text-sm text-slate-900">
        Hay calificaciones registradas. El cambio puede afectar los resultados del periodo, de los periodos dependientes o la calificación final.
    </p>
    <x-table zebra="slate" scroll-label="Comparación de la configuración actual y propuesta">
        <x-slot:header>
            <x-table.th align="center">Elemento</x-table.th>
            <x-table.th align="center">Configuración actual</x-table.th>
            <x-table.th align="center">Propuesta</x-table.th>
        </x-slot:header>
        @foreach ($proposedPlan as $key => $entry)
            @foreach (is_array($entry) ? array_unique(array_merge(array_keys($previousPlan[$key]), array_keys($entry))) : [''] as $id)
                @php
                    $before = is_array($entry) ? ($previousPlan[$key][$id] ?? null) : $previousPlan[$key];
                    $after = is_array($entry) ? ($entry[$id] ?? null) : $entry;
                    $itemLabel = match ($key) {
                        'components' => $labels[$id],
                        'previous', 'periods' => 'Periodo · ' . ($periodNames->get($id) ?? 'No disponible'),
                        'exams' => 'Aplicación · ' . ($applicationNames->get($id) ?? 'No disponible'),
                        default => $labels[$key],
                    };
                @endphp
                @if ($before != $after || ($before === null) !== ($after === null))
                    <tr>
                        <th scope="row" class="px-4 py-3 text-left text-sm font-semibold text-acode-primary">{{ $itemLabel }}</th>
                        <x-table.td align="center">{{ $formatValue($before, is_array($entry)) }}</x-table.td>
                        <x-table.td align="center">{{ $formatValue($after, is_array($entry)) }}</x-table.td>
                    </tr>
                @endif
            @endforeach
        @endforeach
    </x-table>
    <form method="POST" action="{{ $target }}" class="flex flex-wrap justify-end gap-2">
        @csrf
        @foreach (collect($input)->except('confirmation') as $key => $value)
            @if (is_array($value))
                @foreach ($value as $id => $weight)
                    <input type="hidden" name="{{ $key }}[{{ $id }}]" value="{{ $weight }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="confirmation" value="{{ $confirmation }}">
        <x-button type="submit" name="edit_plan" value="1" variant="neutral">Volver a editar</x-button>
        <x-button type="submit">Confirmar cambios</x-button>
    </form>
</div>
@endsection
