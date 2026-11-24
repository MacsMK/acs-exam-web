@php
    $status = match (true) {
        $period->starts_at === null || $period->ends_at === null => 'Sin fechas',
        $period->is_active_today => 'Vigente',
        $period->starts_at->isAfter(today()) => 'Programado',
        default => 'Concluido',
    };
@endphp

<x-badge :variant="$status === 'Vigente' ? 'accent' : 'neutral'">{{ $status }}</x-badge>
