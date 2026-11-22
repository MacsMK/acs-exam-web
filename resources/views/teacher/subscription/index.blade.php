{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/subscription/index.blade.php (MPW-V3 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 10) --}}
@extends('layouts.app')

@section('title', 'Suscripción y límites de uso')

@section('content')
<div class="space-y-6">
    {{-- Encabezado --}}
    <x-page-header
        title="Suscripción y límites de uso"
        subtitle="Consulta tus cuotas de servicio, la vigencia de tu plan y activa o renueva tu paquete docente al instante."
    >
        <x-slot:actions>
            <x-button
                variant="accent"
                size="md"
                :href="'https://wa.me/' . $whatsappNumber . '?text=' . urlencode('Hola A-Code Solutions, deseo solicitar/renovar un paquete docente para mi cuenta:' . PHP_EOL . 'Docente: ' . $teacher->full_name . PHP_EOL . 'Correo: ' . $teacher->email . PHP_EOL . 'ID: ' . $teacher->id)"
                target="_blank"
                rel="noopener noreferrer"
            >
                <x-icon name="chat" class="w-4 h-4" />
                Abrir WhatsApp comercial
            </x-button>
        </x-slot:actions>
    </x-page-header>

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
                    <p class="text-xs text-slate-700 leading-relaxed">
                        Tu suscripción anterior requiere renovación para restablecer tus cuotas de paquete. Elige un paquete en el catálogo inferior para reactivar tu plan al instante.
                    </p>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Estado actual del docente y consumo de cuotas --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card class="lg:col-span-1 border-acode-tech/30 bg-gradient-to-br from-white to-acode-surface/40">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-acode-tech mb-1">Plan actual</h2>
            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-2xl font-black text-acode-primary">
                    {{ $activePackage ? $activePackage->name : 'Prueba gratuita' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                {{ $activePackage ? 'Paquete activo con cuota mensual asignada.' : 'Beneficio gratuito inicial con límite histórico acumulado.' }}
            </p>

            <div class="pt-3 border-t border-slate-200/60 text-xs text-slate-600 space-y-2">
                <div class="flex justify-between items-center gap-2">
                    <span>Estado:</span>
                    @if ($isSuspendedSubscription ?? false)
                        <x-badge variant="danger">Suspendido</x-badge>
                    @else
                        <x-badge variant="accent">Activo</x-badge>
                    @endif
                </div>
                @if ($activePackage && ($currentSubscription ?? null)?->expires_at)
                    <div class="flex justify-between items-center gap-2">
                        <span>Vigencia:</span>
                        <span class="font-medium text-slate-800">{{ $currentSubscription->expires_at->format('d/m/Y') }}</span>
                    </div>
                @endif
                @if ($activePackage)
                    {{-- Medio de pago domiciliado y próxima fecha de cobro (RF-SUS-003, RF-SUS-006) --}}
                    <div class="flex justify-between items-center gap-2">
                        <span>Medio de pago:</span>
                        <span class="font-medium text-slate-800 text-right">{{ $currentSubscription?->maskedCard() ?? 'Sin domiciliar' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-2">
                        <span>Precio mensual:</span>
                        <span class="font-medium text-slate-800 tabular-nums">${{ number_format((float) $activePackage->monthly_price, 2) }} MXN</span>
                    </div>
                    <div class="flex justify-between items-center gap-2">
                        <span>Próximo cobro:</span>
                        <span class="font-medium text-slate-800 text-right">
                            @if ($nextChargeAt ?? null)
                                {{ $nextChargeAt->format('d/m/Y') }}
                            @elseif ($currentSubscription?->cancelled_at)
                                Sin cobro: renovación cancelada
                            @else
                                Sin renovación automática
                            @endif
                        </span>
                    </div>
                @else
                    <div class="flex justify-between items-center gap-2">
                        <span>Modalidad de pago:</span>
                        <span class="font-medium text-slate-800 text-right">Prueba gratuita / WhatsApp</span>
                    </div>
                @endif
            </div>

            @if ($isInGracePeriod ?? false)
                <p class="mt-3 rounded-lg bg-acode-warning/10 p-2.5 text-xs text-amber-800">
                    El cobro de tu renovación fue rechazado. Lo reintentaremos durante 3 días; mientras tanto tu plan sigue activo.
                </p>
            @endif

            @if ($activePackage && ($currentSubscription ?? null)?->renewsAutomatically())
                {{-- Cancelación de la renovación (RF-SUS-014) --}}
                <form
                    method="POST"
                    action="{{ route('teacher.subscription.cancel-renewal') }}"
                    class="mt-4"
                    x-data
                    x-on:submit.prevent="window.AcodeAlert.confirm({
                        title: '¿Cancelar la renovación?',
                        text: 'No se harán más cobros. Tu plan seguirá activo hasta el {{ $currentSubscription->expires_at?->format('d/m/Y') }}.',
                        confirmButtonText: 'Cancelar renovación',
                        cancelButtonText: 'Volver',
                        isDanger: true,
                        onConfirm: () => $el.submit(),
                    })"
                >
                    @csrf
                    <x-button type="submit" variant="neutral" size="md" class="w-full">
                        <x-icon name="xMark" class="w-4 h-4" />
                        Cancelar renovación
                    </x-button>
                </form>
            @elseif ($activePackage && ($currentSubscription ?? null)?->cancelled_at)
                <p class="mt-3 text-xs text-slate-500">
                    Cancelaste la renovación el {{ $currentSubscription->cancelled_at->format('d/m/Y') }}. Tu plan termina el {{ $currentSubscription->expires_at?->format('d/m/Y') }}.
                </p>
            @endif
        </x-card>

        {{-- Barras de cuotas y consumo (consumidas desde TeacherSubscriptionService::$quotaMetrics) --}}
        <x-card class="lg:col-span-2 space-y-5">
            <h2 class="text-base font-bold text-acode-primary">Consumo de cuotas del paquete</h2>

            {{-- Grupos escolares --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold mb-1.5">
                    <div>
                        <span class="text-slate-700">Grupos escolares</span>
                        <span class="ml-1.5 font-normal text-slate-500">({{ $quotaMetrics['groups']['description'] }})</span>
                    </div>
                    <span class="{{ $quotaMetrics['groups']['is_unlimited'] ? 'text-acode-accent font-bold' : 'text-acode-primary' }}">
                        {{ $quotaMetrics['groups']['usage_label'] }}
                    </span>
                </div>
                <x-progress-bar
                    :value="$quotaMetrics['groups']['percent']"
                    :variant="$quotaMetrics['groups']['variant']"
                    label="Grupos escolares creados"
                />
                @if (! $activePackage && ! ($isSuspendedSubscription ?? false) && $cumulativeGroupsCount >= 1)
                    <p class="text-xs text-acode-danger mt-1.5">Alcanzaste el límite de tu prueba gratuita. Contrata un paquete para agregar más grupos.</p>
                @endif
            </div>

            {{-- Banco de preguntas --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold mb-1.5">
                    <div>
                        <span class="text-slate-700">Reactivos en banco de preguntas</span>
                        <span class="ml-1.5 font-normal text-slate-500">({{ $quotaMetrics['questions']['description'] }})</span>
                    </div>
                    <span class="{{ $quotaMetrics['questions']['is_unlimited'] ? 'text-acode-accent font-bold' : 'text-acode-primary' }}">
                        {{ $quotaMetrics['questions']['usage_label'] }}
                    </span>
                </div>
                <x-progress-bar
                    :value="$quotaMetrics['questions']['percent']"
                    :variant="$quotaMetrics['questions']['variant']"
                    label="Reactivos en banco de preguntas"
                />
            </div>

            {{-- Exámenes activos --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold mb-1.5">
                    <div>
                        <span class="text-slate-700">Exámenes guardados / activos</span>
                        <span class="ml-1.5 font-normal text-slate-500">({{ $quotaMetrics['exams']['description'] }})</span>
                    </div>
                    <span class="{{ $quotaMetrics['exams']['is_unlimited'] ? 'text-acode-accent font-bold' : 'text-acode-primary' }}">
                        {{ $quotaMetrics['exams']['usage_label'] }}
                    </span>
                </div>
                <x-progress-bar
                    :value="$quotaMetrics['exams']['percent']"
                    :variant="$quotaMetrics['exams']['variant']"
                    label="Exámenes guardados o activos"
                />
            </div>

            {{-- Créditos de Inteligencia Artificial --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold mb-1.5">
                    <div>
                        <span class="text-slate-700">Créditos de inteligencia artificial</span>
                        <span class="ml-1.5 font-normal text-slate-500">({{ $quotaMetrics['aiCredits']['description'] }})</span>
                    </div>
                    <span class="{{ $quotaMetrics['aiCredits']['is_unlimited'] ? 'text-acode-accent font-bold' : 'text-acode-primary' }}">
                        {{ $quotaMetrics['aiCredits']['usage_label'] }}
                    </span>
                </div>
                <x-progress-bar
                    :value="$quotaMetrics['aiCredits']['percent']"
                    :variant="$quotaMetrics['aiCredits']['variant']"
                    label="Créditos de inteligencia artificial utilizados"
                />
            </div>
        </x-card>
    </div>

    {{-- Catálogo de Paquetes Oficiales (MPW-V3 Tabla 8) --}}
    <div class="space-y-4">
        <x-section-header
            title="Catálogo de paquetes docentes"
            subtitle="Elige el paquete que mejor se adapte a tus grupos y actívalo de inmediato."
        />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($packages as $pkg)
                <x-card class="flex flex-col justify-between relative {{ ($activePackage && $activePackage->id === $pkg->id) ? 'ring-2 ring-acode-accent shadow-md' : 'border-slate-200' }}">
                    @if ($activePackage && $activePackage->id === $pkg->id)
                        <div class="absolute -top-3 right-4 rounded-full bg-white shadow-sm">
                            <x-badge variant="accent">Tu plan actual</x-badge>
                        </div>
                    @endif

                    <div>
                        <h3 class="text-lg font-bold text-acode-primary mb-1">{{ $pkg->name }}</h3>
                        <div class="flex items-baseline gap-1 my-3">
                            <span class="text-3xl font-extrabold text-acode-primary tabular-nums">${{ number_format((float) $pkg->monthly_price, 0) }}</span>
                            <span class="text-xs text-slate-500">MXN / mes</span>
                        </div>

                        <ul class="space-y-2.5 text-xs text-slate-600 my-5">
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                @if ($pkg->max_groups === null)
                                    <span><strong>Ilimitados</strong> grupos escolares</span>
                                @else
                                    <span>Hasta <strong>{{ number_format((int) $pkg->max_groups) }}</strong> grupos escolares</span>
                                @endif
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
                                @if ($pkg->max_active_exams === null)
                                    <span><strong>Ilimitados</strong> exámenes guardados</span>
                                @else
                                    <span>Hasta <strong>{{ number_format((int) $pkg->max_active_exams) }}</strong> exámenes guardados</span>
                                @endif
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>{{ $pkg->ai_requests_limit !== null ? 'Hasta ' . number_format((int) $pkg->ai_requests_limit) . ' evaluaciones IA / mes' : 'Evaluaciones con IA ilimitadas' }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-4 border-t border-slate-100 space-y-2.5">
                        @if ($activePackage && $activePackage->id === $pkg->id)
                            <x-button variant="neutral" size="md" class="w-full" disabled>
                                Plan vigente
                            </x-button>
                        @elseif ($activePackage && (float) $pkg->monthly_price > (float) $activePackage->monthly_price && $currentSubscription?->renewsAutomatically())
                            {{-- Actualización a nivel superior sin cobro inmediato (RF-SUS-011, RF-SUS-012) --}}
                            <form
                                method="POST"
                                action="{{ route('teacher.subscription.upgrade', $pkg) }}"
                                x-data
                                x-on:submit.prevent="window.AcodeAlert.confirm({
                                    title: '¿Actualizar a {{ $pkg->name }}?',
                                    text: 'Sus capacidades se activan de inmediato y sin cobro. Desde tu siguiente renovación pagarás ${{ number_format((float) $pkg->monthly_price, 2) }} MXN al mes.',
                                    confirmButtonText: 'Actualizar a este nivel',
                                    cancelButtonText: 'Cancelar',
                                    isDanger: false,
                                    onConfirm: () => $el.submit(),
                                })"
                            >
                                @csrf
                                <x-button type="submit" variant="primary" size="md" class="w-full">
                                    <x-icon name="check" class="w-4 h-4" />
                                    Actualizar a este nivel
                                </x-button>
                            </form>
                        @elseif ($activePackage && (float) $pkg->monthly_price > (float) $activePackage->monthly_price)
                            <x-button variant="neutral" size="md" class="w-full" disabled>
                                Disponible al terminar tu periodo
                            </x-button>
                        @elseif ($activePackage)
                            <x-button variant="neutral" size="md" class="w-full" disabled>
                                No disponible con tu plan actual
                            </x-button>
                        @else
                            <x-button :href="route('teacher.subscription.checkout', $pkg)" variant="primary" size="md" class="w-full">
                                Contratar este paquete
                            </x-button>

                            <x-button
                                :href="'https://wa.me/' . $whatsappNumber . '?text=' . urlencode('Hola A-Code Solutions, me interesa contratar/renovar el paquete ' . $pkg->name . ' ($' . $pkg->monthly_price . ' MXN/mes) para mi cuenta docente:' . PHP_EOL . 'Nombre: ' . $teacher->full_name . PHP_EOL . 'Correo: ' . $teacher->email)"
                                target="_blank"
                                rel="noopener noreferrer"
                                variant="neutral"
                                size="md"
                                class="w-full"
                            >
                                <x-icon name="chat" class="w-4 h-4" />
                                Contratar vía WhatsApp
                            </x-button>
                        @endif
                    </div>
                </x-card>
            @empty
                <div class="md:col-span-3">
                    <x-empty-state
                        icon="document"
                        title="Sin paquetes disponibles"
                        message="No hay paquetes de suscripción disponibles en este momento. Contáctanos por WhatsApp para recibir asistencia comercial."
                    />
                </div>
            @endforelse
        </div>
    </div>

    {{-- Historial de pagos y comprobantes (RF-SUS-013) --}}
    <div class="space-y-4">
        <x-section-header
            title="Historial de pagos"
            subtitle="Registro de transacciones y comprobantes procesados en tu cuenta docente."
        />

        <x-table>
            <x-slot:header>
                <x-table.th>Folio</x-table.th>
                <x-table.th>Fecha</x-table.th>
                <x-table.th>Concepto</x-table.th>
                <x-table.th>Paquete</x-table.th>
                <x-table.th>Método</x-table.th>
                <x-table.th>Monto</x-table.th>
                <x-table.th>Estado</x-table.th>
            </x-slot:header>

            @forelse (($transactions ?? collect()) as $tx)
                <tr>
                    <x-table.td class="whitespace-nowrap font-mono text-xs font-bold text-acode-primary">
                        {{ $tx->reference_code }}
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap text-xs text-slate-600">
                        {{ $tx->created_at?->format('d/m/Y H:i') ?? '—' }}
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap text-xs text-slate-700">
                        {{ $tx->conceptLabel() }}
                    </x-table.td>
                    <x-table.td class="font-semibold text-acode-primary text-xs">
                        {{ $tx->package?->name ?? 'Paquete SaaS' }}
                    </x-table.td>
                    <x-table.td class="text-xs text-slate-700">
                        {{ $tx->maskedPaymentSummary() }}
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap font-semibold text-acode-primary text-xs tabular-nums">
                        ${{ number_format((float) $tx->amount, 2) }} MXN
                    </x-table.td>
                    <x-table.td>
                        @if ($tx->status === 'paid')
                            <x-badge variant="success">Pagado</x-badge>
                        @elseif ($tx->status === 'no_charge')
                            <x-badge variant="neutral">Sin cobro</x-badge>
                            @if ($tx->metadata['next_charge_amount'] ?? null)
                                <p class="mt-1 text-xs text-slate-500">Se cobra ${{ number_format((float) $tx->metadata['next_charge_amount'], 2) }} MXN desde la siguiente renovación.</p>
                            @endif
                        @else
                            <x-badge variant="danger">Rechazado</x-badge>
                            @if ($tx->failure_message)
                                <p class="mt-1 text-xs text-acode-danger max-w-xs">{{ $tx->failure_message }}</p>
                            @endif
                        @endif
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-8"
                            icon="document"
                            title="Sin pagos registrados"
                            message="Aún no tienes pagos ni comprobantes registrados en tu historial."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
@endsection
