{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: admin/operations/teacher.blade.php (MPW-V3 §4.1.2) --}}
@extends('layouts.app')
@section('title', 'Operaciones de la cuenta')
@section('content')
    <x-section-header title="Operaciones de la cuenta" :subtitle="$teacher->email" />
    <div class="space-y-5">
        <x-card>
            <h2>Prueba gratuita</h2>
            @foreach (['group' => 'Grupo', 'exam' => 'Examen'] as $key => $label)
                @php($usedAt = $teacher->getAttribute('trial_'.$key.'_used_at'))
                <p>{{ $label }} · {{ $usedAt?->format('d/m/Y H:i') ?? ($teacher->trial_history_complete ? 'Sin consumo' : 'Sin registro comprobable anterior') }}</p>
            @endforeach
        </x-card>
        <x-card>
            <h2>Operación de grupos y aplicaciones</h2>
            @forelse ($groups as $group)
                <p>Grupo #{{ $group->id }} · {{ $group->trashed() ? 'Eliminado' : ($group->is_closed ? 'Cerrado' : 'Abierto') }}</p>
            @empty
                <p>Sin grupos registrados</p>
            @endforelse
            {{ $groups->links() }}
            @forelse ($exams as $exam)
                <p>Examen #{{ $exam->id }} · {{ ['draft' => 'Borrador', 'scheduled' => 'Programado', 'active' => 'En aplicación', 'closed' => 'Cerrado'][$exam->operationalStatus()] }}
                    · Grupos {{ $exam->groups->pluck('id')->join(', ') ?: 'Sin asignar' }}</p>
            @empty
                <p>Sin exámenes registrados</p>
            @endforelse
            {{ $exams->links() }}
        </x-card>
        <x-card>
            <h2>Créditos de IA</h2>
            <p>Saldo disponible · {{ $credits['is_unlimited'] ? 'Ilimitado' : $credits['remaining'] }}</p>
            <p>{{ $credits['cycle_label'] }}</p>
            <h3>Consumos registrados</h3>
            @forelse ($movements as $movement)
                <p>{{ $movement->created_at->format('d/m/Y H:i') }} · {{ $movement->credits_consumed }} créditos</p>
            @empty
                <p>Sin movimientos de consumo</p>
            @endforelse
            {{ $movements->links() }}
            <p>El sistema aún no registra abonos mensuales de créditos de IA.</p>
            <h3>Renovaciones de la suscripción</h3>
            @forelse ($renewals as $renewal)
                <p>{{ $renewal->created_at->format('d/m/Y H:i') }} · {{ $renewal->statusLabel() }}</p>
            @empty
                <p>Sin renovaciones de suscripción registradas</p>
            @endforelse
            {{ $renewals->links() }}
        </x-card>
    </div>
@endsection
