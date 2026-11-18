{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/subscription/index.blade.php (MPW-V2 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Suscripción SaaS y Cuotas')

@section('content')
<div class="space-y-8 max-w-6xl mx-auto">
    {{-- Encabezado --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Suscripción SaaS y Límites de Uso</h1>
            <p class="text-sm text-slate-500 mt-1">
                Consulte sus cuotas de servicio, vigencia de plan y active o renueve su paquete docente al instante.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a
                href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode("Hola A-Code Solutions, deseo solicitar/renovar un paquete docente para mi cuenta:\nDocente: {$teacher->full_name}\nCorreo: {$teacher->email}\nID: {$teacher->id}") }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 bg-acode-accent hover:bg-acode-accent-dark text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-sm transition-all duration-200 hover:shadow active:scale-[0.98]"
            >
                <svg class="w-5 h-5 fill-current shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                </svg>
                Atención Comercial por WhatsApp
            </a>
        </div>
    </div>

    {{-- Banner de Onboarding (docente recién verificado sin paquete ni prueba completada) --}}
    @if ($isOnboarding ?? false)
        <x-card class="border-acode-accent/40 bg-gradient-to-r from-acode-accent/10 via-white to-acode-surface/60">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-accent text-white shadow-sm">
                        <x-icon name="success" class="w-6 h-6" />
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge variant="accent">Cuenta verificada</x-badge>
                            <span class="text-xs font-semibold uppercase tracking-wider text-acode-tech">Paso inicial de suscripción</span>
                        </div>
                        <p class="text-sm font-medium text-acode-primary leading-relaxed">
                            ¡Tu cuenta docente ya está verificada! Elige un paquete para activar tus cuotas al instante mediante pago simulado, contrata por WhatsApp o inicia gratis.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('teacher.subscription.free-trial') }}" class="shrink-0">
                    @csrf
                    <x-button type="submit" variant="accent" size="md" class="w-full lg:w-auto">
                        <x-icon name="check" class="w-4 h-4" />
                        Continuar con prueba gratuita
                    </x-button>
                </form>
            </div>
        </x-card>
    @endif

    {{-- Alerta por suscripción suspendida o vencida --}}
    @if ($isSuspendedSubscription ?? false)
        <x-card class="border-acode-danger/40 bg-acode-danger/5">
            <div class="flex items-start gap-3">
                <x-icon name="warning" class="w-5 h-5 text-acode-danger shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <p class="text-sm font-bold text-acode-danger">Suscripción suspendida o vencida</p>
                    <p class="text-xs text-slate-700">
                        Tu suscripción anterior requiere renovación para restablecer tus cuotas de paquete. Elige un paquete en el catálogo inferior para reactivar tu plan al instante.
                    </p>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Estado actual del docente y consumo de cuotas --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card class="lg:col-span-1 border-acode-tech/30 bg-gradient-to-br from-white to-acode-surface/40">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-acode-tech mb-1">Plan Actual</h3>
            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-2xl font-black text-acode-primary">
                    {{ $activePackage ? $activePackage->name : 'Prueba Freemium' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mb-4">
                {{ $activePackage ? 'Paquete activo con cuota mensual asignada.' : 'Beneficio gratuito inicial (RF-SUS-010: límite histórico acumulado).' }}
            </p>

            <div class="pt-3 border-t border-slate-200/60 text-xs text-slate-600 space-y-2">
                <div class="flex justify-between items-center">
                    <span>Estado:</span>
                    @if ($isSuspendedSubscription ?? false)
                        <span class="font-semibold text-acode-danger">● Suspendido</span>
                    @else
                        <span class="font-semibold text-acode-accent-dark">● Activo</span>
                    @endif
                </div>
                @if (($currentSubscription ?? null)?->expires_at)
                    <div class="flex justify-between items-center">
                        <span>Vigencia:</span>
                        <span class="font-medium text-slate-800">{{ $currentSubscription->expires_at->format('d/m/Y') }}</span>
                    </div>
                @endif
                <div class="flex justify-between items-center">
                    <span>Modalidad de pago:</span>
                    <span class="font-medium text-slate-800">
                        {{ $activePackage ? 'Pasarela Simulada / WhatsApp' : 'Prueba gratuita / WhatsApp' }}
                    </span>
                </div>
            </div>
        </x-card>

        {{-- Barras de cuotas y consumo (12 pasos discretos sin style inline) --}}
        <x-card class="lg:col-span-2 space-y-4">
            <h3 class="text-base font-bold text-acode-primary mb-3">Consumo de Cuotas del Paquete</h3>

            {{-- Grupos escolares --}}
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">Grupos Escolares Creados</span>
                    <span class="text-acode-primary">
                        {{ $activePackage ? $activeGroupsCount : $cumulativeGroupsCount }} / {{ $activePackage?->max_groups ?? 1 }}
                    </span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500 {{ ($activePackage ? $activeGroupsCount : $cumulativeGroupsCount) >= ($activePackage?->max_groups ?? 1) ? 'bg-acode-danger' : 'bg-acode-tech' }} {{ match (
                            ($activePackage ? $activeGroupsCount : $cumulativeGroupsCount) > 0
                                ? max(1, (int) round(min(12, (($activePackage ? $activeGroupsCount : $cumulativeGroupsCount) / max(1, (int) ($activePackage?->max_groups ?? 1))) * 12)))
                                : 0
                        ) {
                            0 => 'w-0',
                            1 => 'w-1/12',
                            2 => 'w-2/12',
                            3 => 'w-3/12',
                            4 => 'w-4/12',
                            5 => 'w-5/12',
                            6 => 'w-6/12',
                            7 => 'w-7/12',
                            8 => 'w-8/12',
                            9 => 'w-9/12',
                            10 => 'w-10/12',
                            11 => 'w-11/12',
                            default => 'w-full',
                        } }}"
                        role="progressbar"
                        aria-valuenow="{{ $activePackage ? $activeGroupsCount : $cumulativeGroupsCount }}"
                        aria-valuemin="0"
                        aria-valuemax="{{ $activePackage?->max_groups ?? 1 }}"
                    ></div>
                </div>
                @if (!$activePackage && $cumulativeGroupsCount >= 1)
                    <p class="text-[11px] text-acode-danger mt-1">Cota máxima de prueba gratuita alcanzada. Contrata un plan para agregar más grupos.</p>
                @endif
            </div>

            {{-- Banco de preguntas --}}
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">Reactivos en Banco de Preguntas</span>
                    @if ($activePackage !== null && $activePackage->max_questions === null)
                        <span class="text-acode-accent font-bold">{{ $questionsCount }} / Ilimitado</span>
                    @else
                        <span class="text-acode-primary">{{ $questionsCount }} / {{ number_format((int) ($activePackage?->max_questions ?? 50)) }}</span>
                    @endif
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    @if ($activePackage !== null && $activePackage->max_questions === null)
                        <div class="h-full w-full bg-acode-accent rounded-full transition-all duration-500" role="progressbar" aria-valuenow="{{ $questionsCount }}" aria-valuemin="0" aria-valuemax="{{ max(1, $questionsCount) }}"></div>
                    @else
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $questionsCount >= ($activePackage?->max_questions ?? 50) ? 'bg-acode-danger' : 'bg-acode-accent' }} {{ match (
                                $questionsCount > 0
                                    ? max(1, (int) round(min(12, ($questionsCount / max(1, (int) ($activePackage?->max_questions ?? 50))) * 12)))
                                    : 0
                            ) {
                                0 => 'w-0',
                                1 => 'w-1/12',
                                2 => 'w-2/12',
                                3 => 'w-3/12',
                                4 => 'w-4/12',
                                5 => 'w-5/12',
                                6 => 'w-6/12',
                                7 => 'w-7/12',
                                8 => 'w-8/12',
                                9 => 'w-9/12',
                                10 => 'w-10/12',
                                11 => 'w-11/12',
                                default => 'w-full',
                            } }}"
                            role="progressbar"
                            aria-valuenow="{{ $questionsCount }}"
                            aria-valuemin="0"
                            aria-valuemax="{{ $activePackage?->max_questions ?? 50 }}"
                        ></div>
                    @endif
                </div>
            </div>

            {{-- Exámenes activos --}}
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">Exámenes Guardados / Activos</span>
                    <span class="text-acode-primary">
                        {{ $activePackage ? $examsCount : $cumulativeExamsCount }} / {{ $activePackage?->max_active_exams ?? 1 }}
                    </span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500 {{ ($activePackage ? $examsCount : $cumulativeExamsCount) >= ($activePackage?->max_active_exams ?? 1) ? 'bg-acode-danger' : 'bg-acode-primary' }} {{ match (
                            ($activePackage ? $examsCount : $cumulativeExamsCount) > 0
                                ? max(1, (int) round(min(12, (($activePackage ? $examsCount : $cumulativeExamsCount) / max(1, (int) ($activePackage?->max_active_exams ?? 1))) * 12)))
                                : 0
                        ) {
                            0 => 'w-0',
                            1 => 'w-1/12',
                            2 => 'w-2/12',
                            3 => 'w-3/12',
                            4 => 'w-4/12',
                            5 => 'w-5/12',
                            6 => 'w-6/12',
                            7 => 'w-7/12',
                            8 => 'w-8/12',
                            9 => 'w-9/12',
                            10 => 'w-10/12',
                            11 => 'w-11/12',
                            default => 'w-full',
                        } }}"
                        role="progressbar"
                        aria-valuenow="{{ $activePackage ? $examsCount : $cumulativeExamsCount }}"
                        aria-valuemin="0"
                        aria-valuemax="{{ $activePackage?->max_active_exams ?? 1 }}"
                    ></div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Catálogo de Paquetes Oficiales (MPW-V2 Tabla 15) --}}
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-acode-primary">Catálogo de Paquetes Disponibles</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($packages as $pkg)
                <x-card class="flex flex-col justify-between relative {{ ($activePackage && $activePackage->id === $pkg->id) ? 'ring-2 ring-acode-tech shadow-md' : 'border-slate-200' }}">
                    @if ($activePackage && $activePackage->id === $pkg->id)
                        <span class="absolute -top-3 right-4 bg-acode-tech text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full shadow-sm">
                            Tu Plan Actual
                        </span>
                    @endif

                    <div>
                        <h3 class="text-lg font-bold text-acode-primary mb-1">{{ $pkg->name }}</h3>
                        <div class="flex items-baseline gap-1 my-3">
                            <span class="text-3xl font-extrabold text-acode-primary">${{ number_format((float) $pkg->monthly_price, 0) }}</span>
                            <span class="text-xs text-slate-500">MXN / mes</span>
                        </div>

                        <ul class="space-y-2.5 text-xs text-slate-600 my-5">
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>Hasta <strong>{{ $pkg->max_groups ?? 'Ilimitados' }}</strong> grupos escolares</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                @if ($pkg->max_questions === null)
                                    <span><strong>Ilimitados</strong> reactivos en banco</span>
                                @else
                                    <span>Hasta <strong>{{ number_format((int) $pkg->max_questions) }}</strong> reactivos en banco</span>
                                @endif
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>Hasta <strong>{{ $pkg->max_concurrent_students }}</strong> alumnos en vivo</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>Hasta <strong>{{ $pkg->max_active_exams }}</strong> exámenes guardados</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>Evaluación con IA Gemini incluida</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-4 border-t border-slate-100 space-y-2.5">
                        @if ($activePackage && $activePackage->id === $pkg->id)
                            <x-button variant="neutral" class="w-full" disabled>
                                Plan Vigente
                            </x-button>
                        @else
                            <x-button href="{{ route('teacher.subscription.checkout', $pkg) }}" variant="primary" class="w-full">
                                <x-icon name="lock" class="w-4 h-4" />
                                Continuar a pasarela
                            </x-button>

                            <a
                                href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode("Hola A-Code Solutions, me interesa contratar/renovar el paquete {$pkg->name} (\${$pkg->monthly_price} MXN/mes) para mi cuenta docente:\nNombre: {$teacher->full_name}\nCorreo: {$teacher->email}") }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-acode-accent/40 bg-acode-accent/10 px-4 py-2 text-xs font-semibold text-acode-primary transition hover:bg-acode-accent/20"
                            >
                                Contratar vía WhatsApp
                            </a>
                        @endif
                    </div>
                </x-card>
            @empty
                <div class="md:col-span-3">
                    <x-card class="text-center py-8 text-slate-400">
                        No hay paquetes de suscripción disponibles en este momento.
                    </x-card>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Historial de pagos y comprobantes (RF-SUS-013) --}}
    <div class="space-y-4">
        <div>
            <h2 class="text-xl font-bold text-acode-primary">Historial de pagos y comprobantes</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Registro de transacciones procesadas mediante la pasarela simulada para tu cuenta docente.
            </p>
        </div>

        <x-card padding="p-0" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-acode-primary text-white text-xs font-semibold uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3.5">Folio</th>
                            <th class="px-5 py-3.5">Fecha</th>
                            <th class="px-5 py-3.5">Paquete</th>
                            <th class="px-5 py-3.5">Método</th>
                            <th class="px-5 py-3.5">Monto</th>
                            <th class="px-5 py-3.5">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse (($transactions ?? collect()) as $tx)
                            <tr class="hover:bg-acode-surface/40 transition">
                                <td class="px-5 py-3.5 align-middle whitespace-nowrap font-mono text-xs font-bold text-acode-primary">
                                    {{ $tx->reference_code }}
                                </td>
                                <td class="px-5 py-3.5 align-middle whitespace-nowrap text-xs text-slate-600">
                                    {{ $tx->created_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 align-middle font-semibold text-acode-primary text-xs">
                                    {{ $tx->package?->name ?? 'Paquete SaaS' }}
                                </td>
                                <td class="px-5 py-3.5 align-middle text-xs text-slate-700">
                                    {{ $tx->maskedPaymentSummary() }}
                                </td>
                                <td class="px-5 py-3.5 align-middle whitespace-nowrap font-semibold text-acode-primary text-xs">
                                    ${{ number_format((float) $tx->amount, 2) }} MXN
                                </td>
                                <td class="px-5 py-3.5 align-middle">
                                    @if ($tx->status === 'paid')
                                        <x-badge variant="success">Pagado</x-badge>
                                    @else
                                        <x-badge variant="danger">Rechazado</x-badge>
                                        @if ($tx->failure_message)
                                            <p class="mt-1 text-[11px] text-acode-danger max-w-xs">{{ $tx->failure_message }}</p>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-400">
                                    Aún no tienes pagos ni comprobantes registrados en tu historial.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>
@endsection
