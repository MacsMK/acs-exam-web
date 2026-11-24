{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/ai-credits/index.blade.php (MPW-V3 §7.3, §7.8, §8.2, RF-IAR-001, RF-IAR-011, RF-EVA-008, ADR-059) --}}
@extends('layouts.app')

@section('title', 'Créditos de IA')

@section('content')
@php
    $remainingCredits = $creditSummary['remaining'];
    $isSuspended = $creditSummary['is_suspended'];
    $isUnlimited = $creditSummary['is_unlimited'];
    $hasCreditLimit = ($creditSummary['limit'] ?? 0) > 0;
    $isExhausted = ! $isSuspended && $remainingCredits === 0;
    $nextResetAt = $creditSummary['next_reset_at'];
    $needsSubscription = $isSuspended || ($isExhausted && $nextResetAt === null);
    $usesGemini = $hasGeminiKey && $activeDriver === 'gemini';
@endphp

<div class="space-y-6">
    <x-page-header title="Créditos de IA" :institutional="true">
        <x-slot:actions>
            <x-button :href="route('teacher.reviews.index')" variant="secondary">
                <x-icon name="view" class="h-4 w-4" />
                <x-slot:label>Abrir revisión</x-slot:label>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="[&>.card-body]:space-y-4">
        <x-section-header title="Saldo de créditos de IA" :institutional="true" />

        <div class="space-y-1">
            <p class="text-2xl font-bold leading-8 tabular-nums text-acode-primary">
                @if ($isUnlimited)
                    Ilimitado
                @else
                    {{ number_format($remainingCredits) }} {{ $remainingCredits === 1 ? 'crédito disponible' : 'créditos disponibles' }}
                @endif
            </p>

            @if ($isUnlimited || $hasCreditLimit)
                <p class="text-sm leading-5 text-slate-700">
                    @if ($isUnlimited)
                        {{ number_format($creditSummary['used']) }} {{ $creditSummary['used'] === 1 ? 'crédito usado' : 'créditos usados' }}
                    @else
                        {{ number_format($creditSummary['used']) }} de {{ number_format($creditSummary['limit']) }} créditos usados
                        {{ $creditSummary['tier'] === 'freemium' ? 'en la prueba gratuita' : 'en este ciclo' }}
                    @endif
                </p>
            @endif
        </div>

        @if ($hasCreditLimit)
            <x-progress-bar
                :value="$creditSummary['used']"
                :max="$creditSummary['limit']"
                :variant="$creditSummary['variant']"
                size="lg"
                aria-hidden="true"
            />
        @endif

        @if ($isSuspended || $isExhausted)
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1 text-sm leading-5 text-slate-700">
                    @if ($isSuspended)
                        <p class="font-semibold text-acode-danger-dark">Suscripción suspendida</p>
                        <p>Abre Suscripción para consultar cómo reactivar tu plan.</p>
                    @else
                        <p class="font-semibold text-acode-primary">Has alcanzado el límite.</p>
                        @if ($needsSubscription)
                            <p>Consulta las opciones de tu plan en Suscripción para volver a solicitar evaluaciones con IA.</p>
                        @endif
                    @endif
                    <p>Puedes seguir calificando manualmente.</p>
                </div>
                @if ($needsSubscription)
                    <x-button :href="route('teacher.subscription.index')" variant="primary" class="shrink-0">
                        <x-nav-icon name="subscription" class="h-4 w-4" />
                        <x-slot:label>Abrir suscripción</x-slot:label>
                    </x-button>
                @endif
            </div>
        @elseif ($creditSummary['variant'] === 'warning')
            <p class="font-semibold text-acode-primary text-sm leading-5">Cerca del límite.</p>
        @endif

        @unless ($isSuspended)
            <div class="space-y-1 text-sm leading-5 text-slate-700">
                <p>Plan actual: <strong class="font-semibold text-acode-primary">{{ $creditSummary['package_name'] }}</strong></p>
                <p>{{ $creditSummary['cycle_label'] }}</p>
                @if ($nextResetAt)
                    <p>Próxima reposición si tu plan sigue activo: <time datetime="{{ $nextResetAt->toIso8601String() }}">{{ $nextResetAt->format('d/m/Y') }} a las {{ $nextResetAt->format('H:i') }}</time>.</p>
                @elseif (! $isUnlimited && $creditSummary['tier'] !== 'freemium')
                    <p>Sin reposición programada.</p>
                @endif
            </div>
        @endunless
    </x-card>

    {{-- Evaluación a solicitud y reglas para el consumo de créditos (RF-IAR-001, RF-IAR-011, RF-EVA-008). --}}
    <x-card class="[&>.card-body]:space-y-2">
        <x-section-header title="Evaluación bajo demanda" :institutional="true">
            <x-slot:actions>
                @if ($usesGemini)
                    <x-badge variant="tech">Google Gemini configurado</x-badge>
                @else
                    <x-badge variant="warning">Respaldo local configurado</x-badge>
                @endif
            </x-slot:actions>
        </x-section-header>
        <p class="text-sm leading-5 text-slate-700">Solicita la evaluación desde Revisión, de forma individual o en lote.</p>
        <p class="text-sm leading-5 text-slate-700">
            <strong class="font-semibold">Se descuenta un crédito por sugerencia de IA utilizable.</strong>
            El respaldo local y las respuestas en blanco no consumen créditos.
            @unless ($isSuspended || $isExhausted)
                Puedes calificar manualmente sin consumir créditos.
            @endunless
        </p>
    </x-card>

    <div class="space-y-3">
        <x-section-header title="Resumen de evaluaciones con IA" :institutional="true" />
        <p class="text-sm leading-5 text-slate-700">
            Cada respuesta de tus exámenes cuenta una vez si su última evaluación guardada se hizo con IA externa.
        </p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-kpi-card
                label="Respuestas con puntaje sugerido por IA"
                :value="number_format($aiEvaluatedAnswersCount)"
                :meta="$aiEvaluatedAnswersCount === 0 ? 'Aún no hay sugerencias de IA.' : null"
                nav-icon="ai-credits"
                tone="tech"
                radius="xl"
            />
            <x-kpi-card
                label="Respuestas en las que guardaste un puntaje"
                :value="$aiEvaluatedAnswersCount > 0
                    ? number_format($aiReviewedAnswersCount).' de '.number_format($aiEvaluatedAnswersCount)
                    : '0'"
                meta="Entre las respuestas con sugerencia de IA"
                icon="check"
                tone="accent"
                radius="xl"
            />
        </div>
    </div>

    <x-card class="[&>.card-body]:space-y-2">
        <x-section-header
            title="Privacidad"
            action-label="Aviso de Privacidad"
            :action-href="route('privacy.show.public')"
            :institutional="true"
        />
        <p class="text-sm leading-5 text-slate-700">
            Cuando se usa Google Gemini, la evaluación envía la respuesta del estudiante y el contexto del reactivo,
            como el enunciado, la respuesta de referencia y la rúbrica, según corresponda.
            Antes del envío, el sistema sustituye los datos identificativos que detecta en ese contenido.
        </p>
    </x-card>
</div>
@endsection
