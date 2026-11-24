{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/subscription/index.blade.php (MPW-V3 §§6.9, 7.3, 7.8 y 8) --}}
@extends('layouts.app')

@section('title', 'Suscripción y límites de uso')

@section('content')
@php
    $isOnboarding = $isOnboarding ?? false;
    $isSuspendedSubscription = $isSuspendedSubscription ?? false;
    $isExpiredSubscription = $isExpiredSubscription ?? false;
    $isInGracePeriod = $isInGracePeriod ?? false;
    $isAwaitingRenewal = $isAwaitingRenewal ?? false;
    $expiresAt = $currentSubscription?->expires_at;
    $previousPackage = $currentSubscription?->subscriptionPackage;
    $nextAiResetAt = $aiCreditSummary['next_reset_at'] ?? null;
    $canChoosePackage = $canCheckout && $packages->isNotEmpty();

    $quotaRows = [
        'groups' => [$activePackage ? 'Grupos activos simultáneos' : 'Grupos escolares', 'grupos'],
        'questions' => ['Reactivos en banco de preguntas', 'reactivos'],
        'exams' => [$activePackage ? 'Exámenes activos simultáneos' : 'Exámenes', 'exámenes'],
        'aiCredits' => ['Créditos de IA', 'créditos de IA'],
    ];

    $contactMessage = 'Hola A-Code Solutions, deseo solicitar información sobre mi suscripción docente.'
        . PHP_EOL . 'Docente: ' . $teacher->full_name
        . PHP_EOL . 'Correo: ' . $teacher->email
        . PHP_EOL . 'ID: ' . $teacher->id;
    $whatsappUrl = 'https://wa.me/' . $whatsappNumber . '?text=' . urlencode($contactMessage);
@endphp

<div
    class="space-y-6"
    x-data="{
        sendingForm: null,
        confirming: false,
        submitOnce(event) {
            if (this.sendingForm) {
                event.preventDefault();
                return;
            }
            this.sendingForm = event.currentTarget.id;
        },
        async confirmAndSubmit(form) {
            if (this.sendingForm || this.confirming) return;

            this.confirming = true;
            try {
                const result = await window.AcodeAlert.confirm({
                    title: form.dataset.confirmTitle,
                    text: form.dataset.confirmMessage,
                    confirmButtonText: form.dataset.confirmAction,
                    cancelButtonText: 'Volver',
                    isDanger: form.dataset.confirmDanger === 'true',
                    focusCancel: true,
                    institutional: true,
                });

                if (result.isConfirmed) {
                    this.sendingForm = form.id;
                    form.submit();
                }
            } finally {
                this.confirming = false;
            }
        },
    }"
    x-on:pageshow.window="sendingForm = null; confirming = false"
>
    <p role="status" class="sr-only" x-text="sendingForm ? 'Enviando solicitud.' : ''"></p>

    <x-page-header title="Suscripción y límites de uso" :institutional="true" />

    @if ($isOnboarding)
        <x-card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <x-badge variant="success">Cuenta verificada</x-badge>
                    <p class="mt-2 text-sm text-slate-700">
                        ¡Tu cuenta docente ya está verificada!
                        @if ($canChoosePackage)
                            Puedes iniciar la prueba gratuita o elegir un paquete del catálogo.
                        @else
                            Puedes iniciar la prueba gratuita.
                        @endif
                    </p>
                </div>

                <form
                    id="subscription-start-trial"
                    method="POST"
                    action="{{ route('teacher.subscription.free-trial') }}"
                    class="shrink-0"
                    x-on:submit="submitOnce($event)"
                >
                    @csrf
                    <x-button
                        type="submit"
                        variant="primary"
                        class="h-auto min-h-10 w-full py-2 text-center lg:w-auto"
                        loading-when="sendingForm === 'subscription-start-trial'"
                        ::disabled="sendingForm !== null"
                    >
                        <x-icon name="check" class="w-4 h-4" x-show="!sendingForm" />
                        Continuar con prueba gratuita
                    </x-button>
                </form>
            </div>
        </x-card>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card class="min-w-0" padding="p-4 sm:p-6">
            <div class="space-y-4">
                <x-section-header title="Plan actual" :institutional="true" />

                <div class="flex flex-wrap items-center gap-2">
                    <p class="min-w-0 break-words text-lg font-semibold text-acode-primary">
                        @if ($isSuspendedSubscription || $isAwaitingRenewal)
                            {{ $previousPackage?->name ?? 'Suscripción docente' }}
                        @else
                            {{ $activePackage?->name ?? 'Prueba gratuita' }}
                        @endif
                    </p>

                    @if ($isSuspendedSubscription)
                        <x-badge variant="danger" class="!text-acode-danger-dark">Suspendido</x-badge>
                    @elseif ($isAwaitingRenewal)
                        <x-badge variant="warning">Renovación pendiente</x-badge>
                    @elseif ($isInGracePeriod)
                        <x-badge variant="warning">Pago pendiente</x-badge>
                    @elseif ($isOnboarding)
                        <x-badge variant="neutral">Por iniciar</x-badge>
                    @else
                        <x-badge variant="success">Activo</x-badge>
                    @endif
                </div>

                @if ($isSuspendedSubscription)
                    <p class="text-sm text-slate-700">
                        @if ($canChoosePackage)
                            Para recuperar los límites de un plan, elige un paquete en el catálogo.
                        @else
                            Abre WhatsApp comercial para consultar cómo reactivar tu plan.
                        @endif
                    </p>
                @elseif ($isAwaitingRenewal)
                    <p class="text-sm text-slate-700">
                        El periodo pagado terminó@if ($expiresAt) el {{ $expiresAt->format('d/m/Y') }}@endif.
                        Tienes los límites históricos de la prueba gratuita mientras se procesa la renovación.
                    </p>
                @elseif ($isExpiredSubscription)
                    <p class="text-sm text-slate-700">
                        Tu plan{{ $previousPackage ? ' ' . $previousPackage->name : ' de pago' }} venció@if ($expiresAt) el {{ $expiresAt->format('d/m/Y') }}@endif.
                        Tienes los límites históricos de la prueba gratuita.
                    </p>
                @elseif (! $activePackage && ! $isOnboarding)
                    <p class="text-sm text-slate-700">Tus límites de prueba gratuita son acumulados.</p>
                @endif

                @if ($activePackage)
                    <dl class="space-y-2 border-t border-slate-200 pt-4 text-sm">
                        @if ($expiresAt)
                            <div class="flex flex-wrap justify-between gap-x-3">
                                <dt>{{ $isInGracePeriod ? 'Periodo pagado hasta' : 'Vigencia hasta' }}</dt>
                                <dd class="font-semibold text-acode-primary tabular-nums">{{ $expiresAt->format('d/m/Y') }}</dd>
                            </div>
                        @endif
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <dt>Precio mensual</dt>
                            <dd class="font-semibold text-acode-primary tabular-nums">${{ number_format((float) $activePackage->monthly_price, 2) }} MXN</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <dt>Renovación automática</dt>
                            <dd class="font-medium text-acode-primary">
                                @if ($currentSubscription?->renewsAutomatically())
                                    Activa
                                @elseif ($currentSubscription?->cancelled_at)
                                    Cancelada
                                @else
                                    Desactivada
                                @endif
                            </dd>
                        </div>
                        @if ($currentSubscription?->maskedCard() && $currentSubscription->renewsAutomatically())
                            <div class="flex flex-wrap justify-between gap-x-3">
                                <dt>Tarjeta domiciliada</dt>
                                <dd class="font-medium text-acode-primary">{{ $currentSubscription->maskedCard() }}</dd>
                            </div>
                        @endif
                        @if ($currentSubscription?->renewsAutomatically())
                            <div class="flex flex-wrap justify-between gap-x-3">
                                <dt>{{ $isInGracePeriod ? 'Próximo intento de cobro' : 'Próximo cobro' }}</dt>
                                <dd class="font-medium text-acode-primary tabular-nums">
                                    {{ $nextChargeAt?->format('d/m/Y') ?? 'Fecha sin confirmar' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                @endif

                @if ($isInGracePeriod)
                    <p class="rounded-lg bg-acode-warning/15 p-3 text-sm text-acode-primary">
                        El cobro de tu renovación fue rechazado. Conservas las cuotas de tu plan mientras se reintenta el cobro durante el periodo de gracia.
                    </p>
                @endif

                @if ($canCancelRenewal)
                    <form
                        id="subscription-cancel-renewal"
                        method="POST"
                        action="{{ route('teacher.subscription.cancel-renewal') }}"
                        data-confirm-title="¿Cancelar la renovación?"
                        data-confirm-message="{{ $isInGracePeriod || $isAwaitingRenewal
                            ? 'Se detendrán los cobros de esta renovación. Como ya venció el periodo pagado, solo se aplicarán los límites históricos de la prueba gratuita.'
                            : ($expiresAt
                                ? 'No se harán más cobros. Tu plan seguirá activo hasta el ' . $expiresAt->format('d/m/Y') . '.'
                                : 'Se desactivará la renovación automática de tu plan.') }}"
                        data-confirm-action="Cancelar renovación"
                        data-confirm-danger="true"
                        x-on:submit.prevent="confirmAndSubmit($el)"
                    >
                        @csrf
                        <x-button
                            type="submit"
                            variant="neutral"
                            class="h-auto min-h-10 w-full py-2 text-center"
                            :disabled="true"
                            loading-when="sendingForm === 'subscription-cancel-renewal'"
                            ::disabled="sendingForm !== null"
                        >
                            <x-icon name="xMark" class="w-4 h-4" x-show="!sendingForm" />
                            Cancelar renovación
                        </x-button>
                    </form>
                @elseif ($activePackage && $currentSubscription?->cancelled_at)
                    <p class="text-sm text-slate-700">
                        Cancelaste la renovación el {{ $currentSubscription->cancelled_at->format('d/m/Y') }}.
                    </p>
                @endif
            </div>
        </x-card>

        <x-card class="min-w-0 xl:col-span-2" padding="p-4 sm:p-6">
            <div class="space-y-4">
                <x-section-header
                    :title="$activePackage || $isSuspendedSubscription ? 'Consumo de cuotas del paquete' : 'Cuotas de la prueba gratuita'"
                    :institutional="true"
                />

                @if ($isSuspendedSubscription)
                    <p class="text-sm text-slate-700">Las cuotas del paquete no están disponibles mientras la suscripción esté suspendida. Tienes 0 créditos de IA disponibles.</p>
                @elseif ($isOnboarding)
                    <p class="text-sm text-slate-700">Esta sección mostrará tu consumo después de iniciar la prueba gratuita o contratar un paquete.</p>
                @else
                    <ul class="space-y-4" role="list">
                        @foreach ($quotaRows as $key => [$label, $unit])
                            @php
                                $quota = $quotaMetrics[$key];
                                $used = $quota['used'];
                                $limit = $quota['limit'];
                                $description = $quota['description'];
                                $isFinite = $limit !== null && $limit > 0;
                                $isAiQuota = $key === 'aiCredits';
                            @endphp
                            <li class="space-y-1.5">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <h3 class="min-w-0 break-words text-base font-semibold text-acode-primary">{{ $label }}</h3>
                                    @if ($isAiQuota)
                                        @if ($quota['is_unlimited'])
                                            <x-badge variant="accent">{{ $quota['limit_label'] }}</x-badge>
                                        @else
                                            <p class="text-sm font-semibold text-acode-primary tabular-nums">
                                                {{ number_format($quota['remaining']) }} {{ $quota['remaining'] === 1 ? 'crédito disponible' : 'créditos disponibles' }}
                                            </p>
                                        @endif
                                    @else
                                        <p class="text-sm font-semibold text-acode-primary tabular-nums">
                                            @if ($isFinite)
                                                {{ number_format($used) }} / {{ number_format($limit) }} {{ $unit }}
                                            @elseif ($quota['is_unlimited'])
                                                {{ number_format($used) }} / Ilimitado
                                            @else
                                                Sin cupo disponible
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                @if ($isAiQuota)
                                    <p class="text-sm text-slate-700 tabular-nums">{{ $quota['usage_label'] }}</p>
                                @endif
                                @if ($isFinite)
                                    <x-progress-bar :value="$used" :max="$limit" :variant="$quota['variant']" aria-hidden="true" />
                                @endif
                                @if (! $activePackage || $isAiQuota)
                                    <p class="text-xs text-slate-700">{{ $description }}</p>
                                @endif
                                @if ($isAiQuota && $nextAiResetAt && $isFinite)
                                    <p class="text-xs text-slate-700">
                                        Próxima reposición si tu plan sigue activo: <time datetime="{{ $nextAiResetAt->toIso8601String() }}">{{ $nextAiResetAt->format('d/m/Y') }} a las {{ $nextAiResetAt->format('H:i') }}</time>.
                                    </p>
                                @elseif ($isAiQuota && ! $quota['is_unlimited'] && $activePackage)
                                    <p class="text-xs text-slate-700">Sin reposición programada.</p>
                                @endif
                                @if (($isFinite || $isAiQuota) && $quota['remaining'] === 0)
                                    <p class="text-sm font-semibold text-acode-primary">Has alcanzado el límite.</p>
                                @elseif ($isFinite && $quota['variant'] === 'warning')
                                    <p class="text-xs text-slate-700">Cerca del límite.</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-card>
    </div>

    <section class="space-y-4" aria-labelledby="subscription-catalog-heading">
        <x-section-header
            id="subscription-catalog-heading"
            title="Catálogo de paquetes docentes"
            :subtitle="$canChoosePackage ? 'El paquete se activa cuando se aprueba el pago simulado en línea.' : null"
            :institutional="true"
        />

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($packages as $pkg)
                @php
                    $isCurrent = $activePackage?->id === $pkg->id;
                    $isHigherTier = $activePackage && (float) $pkg->monthly_price > (float) $activePackage->monthly_price;
                @endphp
                <x-card @class([
                    'flex min-w-0 flex-col [&>.card-body]:flex [&>.card-body]:flex-1 [&>.card-body]:flex-col',
                    'ring-2 ring-acode-accent' => $isCurrent,
                ]) padding="p-4 sm:p-6">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h3 class="min-w-0 break-words text-base font-semibold text-acode-primary">{{ $pkg->name }}</h3>
                        @if ($isCurrent)
                            <x-badge variant="accent">Tu plan actual</x-badge>
                        @endif
                    </div>

                    <p class="mt-2 flex flex-wrap items-baseline gap-x-1 text-acode-primary">
                        <span class="text-2xl font-bold tabular-nums">${{ number_format((float) $pkg->monthly_price, 2) }}</span>
                        <span class="text-xs text-slate-700">MXN / mes</span>
                    </p>

                    <x-subscription.package-capabilities :package="$pkg" class="mb-4 mt-4" />

                    @if (! $isCurrent && ($activePackage || $canCheckout))
                        <div class="mt-auto space-y-2.5 border-t border-slate-200 pt-4">
                            @if ($isHigherTier && $canUpgrade)
                                <form
                                    id="subscription-upgrade-{{ $pkg->id }}"
                                    method="POST"
                                    action="{{ route('teacher.subscription.upgrade', $pkg) }}"
                                    data-confirm-title="¿Actualizar a {{ $pkg->name }}?"
                                    data-confirm-message="Sus capacidades se activan de inmediato y sin cobro en este paso. Desde el próximo cobro pagarás ${{ number_format((float) $pkg->monthly_price, 2) }} MXN al mes."
                                    data-confirm-action="Actualizar a este nivel"
                                    x-on:submit.prevent="confirmAndSubmit($el)"
                                >
                                    @csrf
                                    <x-button
                                        type="submit"
                                        variant="primary"
                                        class="h-auto min-h-10 w-full py-2 text-center"
                                        :disabled="true"
                                        :loading-when="'sendingForm === ' . json_encode('subscription-upgrade-' . $pkg->id)"
                                        ::disabled="sendingForm !== null"
                                    >
                                        <x-icon name="check" class="w-4 h-4" x-show="!sendingForm" />
                                        Actualizar a este nivel<span class="sr-only">: {{ $pkg->name }}</span>
                                    </x-button>
                                </form>
                            @elseif ($activePackage)
                                <p class="text-sm text-slate-700">
                                    @if ($isHigherTier && $expiresAt)
                                        Disponible al terminar tu periodo el {{ $expiresAt->format('d/m/Y') }}.
                                    @else
                                        No disponible con tu plan actual.
                                    @endif
                                </p>
                            @elseif ($canCheckout)
                                <x-button
                                    :href="route('teacher.subscription.checkout', $pkg)"
                                    variant="primary"
                                    class="h-auto min-h-10 w-full py-2 text-center"
                                >
                                    Contratar este paquete<span class="sr-only">: {{ $pkg->name }} (pago en línea)</span>
                                </x-button>
                                <x-button
                                    :href="'https://wa.me/' . $whatsappNumber . '?text=' . urlencode('Hola A-Code Solutions, me interesa contratar/renovar el paquete ' . $pkg->name . ' ($' . $pkg->monthly_price . ' MXN/mes) para mi cuenta docente:' . PHP_EOL . 'Nombre: ' . $teacher->full_name . PHP_EOL . 'Correo: ' . $teacher->email)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    variant="secondary"
                                    class="h-auto min-h-10 w-full py-2 text-center"
                                    aria-describedby="subscription-whatsapp-description"
                                >
                                    <x-icon name="chat" class="w-4 h-4" />
                                    Contratar vía WhatsApp<span class="sr-only">: {{ $pkg->name }}</span>
                                </x-button>
                            @endif
                        </div>
                    @endif
                </x-card>
            @empty
                <x-empty-state
                    class="col-span-full"
                    icon="document"
                    title="Sin paquetes disponibles"
                    message="Puedes consultar la disponibilidad con WhatsApp comercial."
                />
            @endforelse
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <x-button
                :href="$whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                :variant="$isSuspendedSubscription && ! $canChoosePackage ? 'primary' : 'secondary'"
                aria-describedby="subscription-whatsapp-description"
                class="h-auto min-h-10 py-2 text-center"
            >
                <x-icon name="chat" class="w-4 h-4" />
                Abrir WhatsApp comercial
            </x-button>
            <p id="subscription-whatsapp-description" class="text-xs text-slate-700">
                Se abre en otra pestaña con un mensaje preparado para enviar.
            </p>
        </div>
    </section>

    <div class="space-y-4">
        <x-section-header
            title="Historial de pagos"
            subtitle="Hasta 10 operaciones recientes."
            :institutional="true"
        />

        <x-table scroll-label="Historial de pagos" zebra="slate">
            <x-slot:header>
                <x-table.th align="center">Folio</x-table.th>
                <x-table.th align="center">Fecha</x-table.th>
                <x-table.th align="center">Concepto</x-table.th>
                <x-table.th align="center">Paquete</x-table.th>
                <x-table.th align="center">Método</x-table.th>
                <x-table.th align="center">Monto</x-table.th>
                <x-table.th align="center">Estado</x-table.th>
            </x-slot:header>

            @forelse (($transactions ?? collect()) as $tx)
                <tr>
                    <x-table.td class="whitespace-nowrap font-mono text-xs font-semibold text-acode-primary">
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
                                <p class="mt-1 text-xs text-slate-700">Se cobra ${{ number_format((float) $tx->metadata['next_charge_amount'], 2) }} MXN desde la siguiente renovación.</p>
                            @endif
                        @else
                            <x-badge variant="danger" class="!text-acode-danger-dark">Rechazado</x-badge>
                            @if ($tx->failure_message)
                                <p class="mt-1 max-w-xs break-words text-xs text-acode-danger-dark">{{ $tx->failure_message }}</p>
                            @endif
                        @endif
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-0">
                        <x-empty-state
                            class="rounded-none border-0 py-8 shadow-none"
                            icon="document"
                            title="Sin pagos registrados"
                            message="Aún no tienes operaciones registradas."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
@endsection
