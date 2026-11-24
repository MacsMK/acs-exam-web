{{-- Selección bloqueante de plan y pago simulado en dos pasos (ADR-060, MPW-V3 §§7 y 8). --}}
@php
    $onboardingTeacher = $onboardingOverlay['teacher'];
    $onboardingPackages = $onboardingOverlay['packages'];
    $freeTrial = $onboardingOverlay['freemiumLimits'];
@endphp

<div
    class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="onboarding-overlay-title"
    x-data="acodePlanOnboardingOverlay(@js([
        'packages' => $onboardingPackages->map(fn ($package) => [
            'id' => (int) $package->id,
            'payUrl' => route('teacher.subscription.pay', $package),
        ])->values()->all(),
        'initialSelectedPackageId' => (int) session('onboarding_selected_package_id'),
        'defaultHolder' => $onboardingTeacher->full_name,
        'paymentMethod' => old('payment_method') === 'spei' ? 'spei' : 'card',
        'cardHolderName' => (string) old('card_holder_name', $onboardingTeacher->full_name),
        'speiSenderBank' => (string) old('spei_sender_bank', ''),
        'speiAccountHolder' => (string) old('spei_account_holder', $onboardingTeacher->full_name),
    ]))"
    @keydown.tab="keepDialogFocus($event)"
    x-on:pageshow.window="isSubmitting = false; isStartingTrial = false; isLoggingOut = false"
>
    <div class="flex min-h-full items-start justify-center p-3 sm:p-6 lg:p-8">
        <div class="my-auto w-full max-w-6xl overflow-hidden rounded-2xl border border-t-4 border-slate-200 border-t-acode-primary bg-white shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
                <x-badge variant="success">Cuenta verificada</x-badge>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button :href="route('privacy.show.public')" target="_blank" rel="noopener noreferrer" variant="neutral">
                        Aviso de privacidad
                    </x-button>
                    <form method="POST" action="{{ route('auth.logout') }}" @submit.prevent="confirmLogout($event.currentTarget)">
                        @csrf
                        <x-button type="submit" variant="neutral" loading-when="isLoggingOut" x-bind:disabled="isLoggingOut || isSubmitting || isStartingTrial">
                            Cerrar sesión
                        </x-button>
                    </form>
                </div>
            </div>

            <div class="space-y-5 p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 id="onboarding-overlay-title" x-ref="onboardingTitle" tabindex="-1" class="text-2xl leading-8 font-bold text-acode-primary">
                            Elige cómo iniciar, {{ $onboardingTeacher->first_name }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-700">Activa la prueba gratuita o elige un paquete para entrar al Panel Docente.</p>
                    </div>
                    <ol class="flex flex-wrap gap-2 text-xs font-semibold text-acode-primary" aria-label="Pasos de suscripción">
                        <li class="rounded-full border border-slate-300 px-3 py-1" :class="{ 'bg-acode-primary text-white': step === 1 }" :aria-current="step === 1 ? 'step' : null">1. Selección de plan</li>
                        <li class="rounded-full border border-slate-300 px-3 py-1" :class="{ 'bg-acode-primary text-white': step === 2 }" :aria-current="step === 2 ? 'step' : null">2. Pago simulado</li>
                    </ol>
                </div>

                <noscript>
                    <p class="rounded-xl border border-slate-200 p-4 text-sm text-slate-700">Para elegir un paquete, activa JavaScript. También puedes continuar desde <a href="{{ route('teacher.subscription.index') }}" class="font-semibold text-acode-tech underline">Suscripción y límites de uso</a>.</p>
                </noscript>

                <div x-show="step === 1" x-cloak class="space-y-5">
                    @if ($errors->any())
                        <div x-ref="onboardingError" tabindex="-1" role="alert" class="space-y-2 rounded-xl border border-acode-danger/40 bg-acode-danger/5 p-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech">
                            <p class="font-semibold text-acode-danger-dark">Revisa los siguientes datos</p>
                            <ul class="list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <x-card padding="p-4 sm:p-6">
                        <div class="space-y-4">
                            <x-section-header title="Prueba gratuita" :institutional="true">
                                <x-slot:actions><x-badge variant="success">$0 MXN</x-badge></x-slot:actions>
                            </x-section-header>
                            <p class="text-sm text-slate-700">Los cupos de prueba son históricos. La capacidad de alumnos se aplica por grupo.</p>
                            <ul class="grid gap-2 text-sm text-slate-700 sm:grid-cols-2 lg:grid-cols-3">
                                <li><strong>{{ $freeTrial['max_groups'] }}</strong> grupo escolar</li>
                                <li><strong>{{ number_format((int) $freeTrial['max_questions']) }}</strong> reactivos en banco</li>
                                <li><strong>{{ $freeTrial['max_students'] }}</strong> alumnos inscritos por grupo</li>
                                <li><strong>{{ $freeTrial['max_exams'] }}</strong> examen</li>
                                <li><strong>{{ $freeTrial['max_ai_evaluations'] }}</strong> créditos de IA</li>
                            </ul>
                            <form method="POST" action="{{ route('teacher.subscription.free-trial') }}" @submit="beginTrial($event)" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <x-button type="submit" variant="primary" loading-when="isStartingTrial" x-bind:disabled="isStartingTrial" class="w-full sm:w-auto">
                                    <span x-show="!isStartingTrial">Confirmar prueba gratuita</span>
                                    <span x-show="isStartingTrial" x-cloak>Activando prueba gratuita</span>
                                </x-button>
                                <span role="status" class="sr-only" x-text="isStartingTrial ? 'Activando prueba gratuita' : ''"></span>
                            </form>
                        </div>
                    </x-card>

                    <section class="space-y-3">
                        <x-section-header
                            title="Catálogo de paquetes docentes"
                            subtitle="Puedes simular el pago con tarjeta o transferencia SPEI."
                            :institutional="true"
                        />
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @forelse ($onboardingPackages as $package)
                                <x-card padding="p-4 sm:p-6" class="flex flex-col [&>.card-body]:flex [&>.card-body]:flex-1 [&>.card-body]:flex-col">
                                    <h3 class="text-base leading-6 font-semibold text-acode-primary">{{ $package->name }}</h3>
                                    <p class="mb-4 mt-2 text-sm text-slate-700"><strong class="text-xl text-acode-primary tabular-nums">${{ number_format((float) $package->monthly_price, 2) }} MXN</strong> / mes</p>
                                    <x-subscription.package-capabilities :package="$package" class="mb-4" />
                                    <div class="mt-auto border-t border-slate-200 pt-4">
                                        <x-button
                                            type="button"
                                            variant="secondary"
                                            data-onboarding-package="{{ $package->id }}"
                                            aria-label="Seleccionar paquete {{ $package->name }}"
                                            @click="selectPackage({{ (int) $package->id }})"
                                            x-bind:disabled="isStartingTrial"
                                            class="w-full"
                                        >
                                            Seleccionar paquete
                                        </x-button>
                                    </div>
                                </x-card>
                            @empty
                                <div class="md:col-span-2 xl:col-span-3">
                                    <x-empty-state
                                        icon="document"
                                        title="Sin paquetes disponibles"
                                        message="Puedes continuar con la prueba gratuita."
                                    />
                                </div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-4">
                    <x-button type="button" variant="neutral" @click="goBackToSelection()" x-bind:disabled="isSubmitting">
                        <x-icon name="arrowLeft" class="h-4 w-4" />
                        Cambiar paquete
                    </x-button>

                    <div class="grid items-start gap-5 lg:grid-cols-12">
                        <div class="lg:col-span-5">
                            @foreach ($onboardingPackages as $package)
                                <div x-show="selectedPackage?.id === {{ (int) $package->id }}">
                                    <x-subscription.package-summary :package="$package" />
                                </div>
                            @endforeach
                        </div>
                        <div class="min-w-0 lg:col-span-7">
                            <x-subscription.payment-form
                                :onboarding="true"
                                :spei-beneficiary="$onboardingOverlay['speiBeneficiary']"
                                :spei-clabe="$onboardingOverlay['speiClabe']"
                            />
                        </div>
                    </div>

                    @foreach ($onboardingPackages as $package)
                        <div x-show="selectedPackage?.id === {{ (int) $package->id }}">
                            <x-subscription.commercial-contact :package="$package" :teacher="$onboardingTeacher" :whatsapp-number="$onboardingOverlay['whatsappNumber']" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.acodePlanOnboardingOverlay = function (config) {
        const restoredPackage = config.packages.find((item) => item.id === config.initialSelectedPackageId) ?? null;

        return {
            ...window.acodeSubscriptionPayment(config),
            step: restoredPackage ? 2 : 1,
            selectedPackage: restoredPackage,
            isStartingTrial: false,
            isLoggingOut: false,

            selectPackage(packageId) {
                this.selectedPackage = config.packages.find((item) => item.id === packageId);
                this.step = 2;
                this.$nextTick(() => this.$refs.paymentTitle.focus());
            },

            goBackToSelection() {
                this.step = 1;
                this.$nextTick(() => {
                    const packageButton = this.$root.querySelector(`[data-onboarding-package="${this.selectedPackage.id}"]`);
                    (packageButton ?? this.$refs.onboardingTitle).focus();
                });
            },

            beginTrial(event) {
                if (this.isStartingTrial) {
                    event.preventDefault();
                    return;
                }
                this.isStartingTrial = true;
            },

            confirmLogout(form) {
                if (this.isLoggingOut) return;

                window.AcodeAlert.confirm({
                    title: '¿Cerrar sesión?',
                    text: 'Volverás a la pantalla de inicio de sesión.',
                    confirmButtonText: 'Cerrar sesión',
                    cancelButtonText: 'Cancelar',
                    focusCancel: true,
                    institutional: true,
                    onConfirm: () => {
                        this.isLoggingOut = true;
                        form.submit();
                    },
                });
            },

            keepDialogFocus(event) {
                const controls = Array.from(this.$root.querySelectorAll('a[href], button, input, select, textarea, [tabindex]'))
                    .filter((element) => element.tabIndex >= 0 && !element.matches(':disabled') && element.getClientRects().length > 0);
                const first = controls[0];
                const last = controls.at(-1);
                const boundary = event.shiftKey ? first : last;

                if (document.activeElement === boundary) {
                    event.preventDefault();
                    (event.shiftKey ? last : first)?.focus();
                }
            },
        };
    };
</script>
@endpush
