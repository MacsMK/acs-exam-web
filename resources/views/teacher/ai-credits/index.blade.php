{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/ai-credits/index.blade.php (MPW-V3 §4.1.2, §5.1.4, §7.3, §8.6, RF-IAR-001, RF-IAR-011, RF-EVA-008, ADR-059) --}}
@extends('layouts.app')

@section('title', 'Créditos de inteligencia artificial')

@section('content')
<div class="space-y-6">
    {{-- Encabezado --}}
    <x-page-header
        title="Créditos de inteligencia artificial"
        subtitle="Supervisa el consumo de solicitudes bajo demanda para la evaluación asistida de reactivos abiertos y de código."
    >
        <x-slot:badge>
            @if ($hasGeminiKey && $activeDriver === 'gemini')
                <x-badge variant="accent">Google Gemini activo</x-badge>
            @else
                <x-badge variant="warning">Modo respaldo local (sin cobro de créditos)</x-badge>
            @endif
        </x-slot:badge>

        <x-slot:actions>
            <x-button :href="route('teacher.reviews.index')" variant="neutral" size="md">
                <x-icon name="view" class="w-4 h-4" />
                Abrir revisión
            </x-button>
            <x-button :href="route('teacher.subscription.index')" variant="primary" size="md">
                <x-nav-icon name="subscription" class="w-4 h-4" />
                Abrir suscripción
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Alerta por suscripción suspendida --}}
    @if ($creditSummary['is_suspended'])
        <x-card class="border-acode-danger/40 bg-acode-danger/5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <x-icon name="warning" class="w-5 h-5 text-acode-danger shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <p class="text-sm font-bold text-acode-danger">Suscripción suspendida o vencida</p>
                        <p class="text-xs text-slate-700 leading-relaxed">
                            No cuentas con saldo disponible para nuevas evaluaciones con IA hasta reactivar tu plan. Aún puedes calificar y recotejar manualmente todos los reactivos sin costo.
                        </p>
                    </div>
                </div>
                <x-button :href="route('teacher.subscription.index')" variant="primary" size="md" class="shrink-0">
                    <x-nav-icon name="subscription" class="w-4 h-4" />
                    Abrir suscripción
                </x-button>
            </div>
        </x-card>
    @endif

    {{-- Métricas históricas de IA --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-kpi-card
            label="Total evaluaciones IA"
            :value="number_format((int) $totalAiEvaluations)"
            meta="Respuestas procesadas"
            nav-icon="ai-credits"
            tone="tech"
        />

        <x-kpi-card
            label="Recotejes realizados"
            :value="number_format((int) $recotejadasCount)"
            meta="Calificaciones validadas"
            icon="check"
            tone="accent"
        />

        <x-kpi-card
            label="Promedio de similitud"
            :value="number_format((float) $promedioSimilitud, 1) . '%'"
            meta="Cotejo semántico"
            icon="reportBar"
            tone="primary"
            :mono="true"
        />
    </div>

    {{-- Estado de la cuota de IA ($creditSummary) --}}
    <x-card class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-acode-primary">Bolsa de evaluaciones asistidas</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Plan actual: <strong class="text-acode-primary">{{ $creditSummary['package_name'] }}</strong>
                    · <span>{{ $creditSummary['cycle_label'] }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if ($creditSummary['is_unlimited'])
                    <x-badge variant="accent">Ilimitado</x-badge>
                    <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded bg-acode-surface text-acode-primary">
                        {{ number_format((int) $creditSummary['used']) }} / Ilimitado
                    </span>
                @else
                    <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded bg-acode-surface text-acode-primary">
                        {{ number_format((int) $creditSummary['used']) }} / {{ number_format((int) ($creditSummary['limit'] ?? 0)) }} créditos
                    </span>
                @endif
            </div>
        </div>

        <x-progress-bar
            :value="$creditSummary['percent']"
            :variant="$creditSummary['variant']"
            size="lg"
            label="Créditos de evaluaciones asistidas utilizados"
        />

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-500 pt-1">
            <div>
                @if ($creditSummary['is_unlimited'])
                    <span class="font-semibold text-acode-accent-dark">Cuentas con evaluaciones asistidas con IA ilimitadas en tu plan vigente.</span>
                @elseif ($creditSummary['is_suspended'])
                    <span class="font-semibold text-acode-danger">0 créditos disponibles (suscripción suspendida).</span>
                @else
                    <span>
                        Tienes <strong class="text-acode-primary">{{ number_format((int) ($creditSummary['remaining'] ?? 0)) }}</strong>
                        crédito(s) disponibles ({{ max(0, 100 - (int) $creditSummary['percent']) }}% restante en el ciclo).
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($hasGeminiKey && $activeDriver === 'gemini')
                    <span>
                        Motor de evaluación: <strong class="text-acode-primary">Google Gemini</strong>
                        <span class="font-mono text-slate-500">({{ $activeModel }})</span>
                    </span>
                @else
                    <span>
                        Motor de evaluación: <strong class="text-acode-primary">Respaldo local sin consumo de créditos</strong>
                    </span>
                @endif
            </div>
        </div>
    </x-card>

    {{-- Reglas de consumo bajo demanda y protección de créditos (RF-IAR-001, RF-IAR-011, RF-EVA-008) --}}
    <x-card class="border-acode-accent/30 bg-acode-accent/5 space-y-3">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-acode-accent/15 rounded-lg text-acode-accent shrink-0 mt-0.5">
                <x-icon name="check" class="w-5 h-5" />
            </div>
            <div class="text-xs text-slate-700 space-y-1.5 leading-relaxed">
                <p class="font-bold text-acode-primary text-sm">Evaluación bajo demanda y garantía de saldo justo</p>
                <p>
                    Las evaluaciones con IA se ejecutan únicamente cuando las solicitas desde la pantalla de recoteje docente, de forma individual o en lote (<strong>RF-IAR-001</strong>).
                </p>
                <p>
                    <strong>Sin cobro en contingencias ni respuestas en blanco:</strong> si el proveedor externo no está disponible y se aplica el modo de respaldo local (<strong>RF-IAR-011</strong>), o si el estudiante dejó la respuesta vacía y se asigna puntaje 0 automáticamente (<strong>RF-EVA-008</strong>), <strong>no se descuenta ningún crédito</strong> de tu bolsa. Además, siempre puedes calificar manualmente sin requerir créditos.
                </p>
            </div>
        </div>
    </x-card>

    {{-- Principios de Privacidad y Minimización --}}
    <x-card class="bg-acode-surface/60 border-acode-tech/30 space-y-3">
        <div class="flex items-start gap-3">
            <div class="p-2 bg-acode-tech/10 rounded-lg text-acode-tech shrink-0 mt-0.5">
                <x-icon name="shield" class="w-5 h-5" />
            </div>
            <div class="text-xs text-slate-700 space-y-1 leading-relaxed">
                <p class="font-bold text-acode-primary text-sm">Privacidad LFPDPPP y minimización de datos</p>
                <p>
                    En estricto cumplimiento del Aviso de Privacidad y la normativa mexicana, las evaluaciones semánticas se transmiten a la API de IA de forma totalmente anónima: solo viaja el texto del reactivo, la respuesta del estudiante y la rúbrica de referencia. <strong>Ningún nombre, correo ni identificador personal de tus alumnos es enviado al modelo de lenguaje.</strong>
                </p>
            </div>
        </div>
    </x-card>
</div>
@endsection
