{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/subscription/checkout.blade.php (MPW-V3 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 10) --}}
@extends('layouts.app')

@section('title', 'Pago en línea')

@section('content')
<div class="space-y-6">
    {{-- Encabezado --}}
    <x-page-header
        title="Pago en línea"
        subtitle="Completa tu suscripción de forma segura para activar las cuotas de tu paquete al instante."
        :breadcrumbs="[
            ['label' => 'Suscripción', 'href' => route('teacher.subscription.index')],
            ['label' => 'Pago en línea'],
        ]"
    >
        <x-slot:badge>
            <x-badge variant="info">Simulación segura</x-badge>
        </x-slot:badge>
        <x-slot:actions>
            <x-button :href="route('teacher.subscription.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Alerta por rechazo bancario simulado --}}
    @if ($errors->has('payment_gateway'))
        <x-card class="border-acode-danger/40 bg-acode-danger/5">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-acode-danger/15 text-acode-danger">
                    <x-icon name="error" class="w-5 h-5" />
                </div>
                <div class="space-y-1">
                    <h2 class="text-sm font-bold text-acode-danger">Operación rechazada por el banco</h2>
                    <p class="text-sm text-acode-ink">{{ $errors->first('payment_gateway') }}</p>
                    <p class="text-xs text-slate-600">
                        Puedes corregir los datos de tu tarjeta, probar con otra tarjeta de demostración o cambiar a transferencia SPEI para reintentar de inmediato.
                    </p>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Grid principal de 12 columnas --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Columna principal (7 cols): Formulario de pago simulado --}}
        <div class="lg:col-span-7">
            <x-card>
                <form
                    id="checkout-payment-form"
                    method="POST"
                    action="{{ route('teacher.subscription.pay', $package) }}"
                    x-data="acodeSimulatedCheckout()"
                    data-default-holder="{{ $teacher->full_name }}"
                    data-initial-method="{{ old('payment_method', 'card') }}"
                    data-initial-card-holder="{{ old('card_holder_name', $teacher->full_name) }}"
                    data-initial-spei-bank="{{ old('spei_sender_bank', '') }}"
                    data-initial-spei-holder="{{ old('spei_account_holder', $teacher->full_name) }}"
                    @submit="isSubmitting = true"
                    class="space-y-6"
                >
                    @csrf
                    <input type="hidden" name="payment_method" :value="paymentMethod">

                    {{-- Selector de método de pago en 2 pestañas --}}
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2.5">
                            Selecciona el método de pago
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="tablist" aria-label="Métodos de pago">
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="paymentMethod === 'card'"
                                @click="paymentMethod = 'card'"
                                class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                :class="paymentMethod === 'card'
                                    ? 'border-acode-primary bg-acode-primary text-white shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-acode-tech hover:bg-acode-surface/40'"
                            >
                                <div>
                                    <p class="text-sm font-bold">Tarjeta (Crédito / Débito)</p>
                                    <p class="text-xs" :class="paymentMethod === 'card' ? 'text-white/75' : 'text-slate-500'">
                                        Visa, Mastercard o Amex
                                    </p>
                                </div>
                                <x-icon name="lock" class="w-5 h-5 shrink-0" />
                            </button>

                            <button
                                type="button"
                                role="tab"
                                :aria-selected="paymentMethod === 'spei'"
                                @click="paymentMethod = 'spei'"
                                class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                :class="paymentMethod === 'spei'
                                    ? 'border-acode-primary bg-acode-primary text-white shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-acode-tech hover:bg-acode-surface/40'"
                            >
                                <div>
                                    <p class="text-sm font-bold">Transferencia SPEI al instante</p>
                                    <p class="text-xs" :class="paymentMethod === 'spei' ? 'text-white/75' : 'text-slate-500'">
                                        Clave de rastreo inmediata
                                    </p>
                                </div>
                                <x-icon name="clock" class="w-5 h-5 shrink-0" />
                            </button>
                        </div>
                    </div>

                    {{-- Pestaña 1: Tarjeta de crédito / débito --}}
                    <div x-show="paymentMethod === 'card'" class="space-y-5 acode-tab-transition">
                        {{-- Tarjeta visual previa en tokens oficiales --}}
                        <div class="rounded-2xl bg-gradient-to-br from-acode-primary via-acode-navy to-acode-tech-dark p-6 text-white shadow-lg">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-xs font-semibold uppercase tracking-widest text-white/75">
                                    A-Code Pay · Simulador
                                </span>
                                <span
                                    class="rounded-md bg-white/15 px-2.5 py-1 font-mono text-xs font-bold tracking-wider text-white"
                                    x-text="cardBrand"
                                ></span>
                            </div>

                            <div class="my-6">
                                <p class="text-xs uppercase tracking-wider text-white/60 mb-1">Número de tarjeta</p>
                                <p class="font-mono text-lg sm:text-xl font-bold tracking-widest" x-text="maskedCardPreview"></p>
                            </div>

                            <div class="flex items-end justify-between gap-4 pt-2 border-t border-white/15">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wider text-white/60">Titular</p>
                                    <p
                                        class="truncate text-xs sm:text-sm font-semibold uppercase tracking-wide"
                                        x-text="cardHolderName.trim() !== '' ? cardHolderName.toUpperCase() : 'NOMBRE DEL TITULAR'"
                                    ></p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-xs uppercase tracking-wider text-white/60">Vigencia</p>
                                    <p
                                        class="font-mono text-xs sm:text-sm font-semibold"
                                        x-text="cardExpiry.trim() !== '' ? cardExpiry : 'MM/AA'"
                                    ></p>
                                </div>
                            </div>
                        </div>

                        {{-- Barra de tarjetas de prueba en 1 clic --}}
                        <div class="rounded-xl border border-slate-200 bg-acode-surface/50 p-3.5 space-y-2">
                            <p class="text-xs font-bold text-acode-primary">
                                Tarjetas de demostración rápida (1 clic)
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="fillTestCard('4242 4242 4242 4242', '12/28', '123')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-acode-accent/40 bg-white px-3 py-1.5 text-xs font-semibold text-acode-primary transition hover:bg-acode-accent/10 focus:outline-none focus:ring-2 focus:ring-acode-accent"
                                >
                                    <x-icon name="check" class="w-3.5 h-3.5 text-acode-accent" />
                                    Visa aprobada (4242)
                                </button>

                                <button
                                    type="button"
                                    @click="fillTestCard('5555 5555 5555 4444', '11/28', '456')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-acode-tech/40 bg-white px-3 py-1.5 text-xs font-semibold text-acode-primary transition hover:bg-acode-tech/10 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                                >
                                    <x-icon name="check" class="w-3.5 h-3.5 text-acode-tech" />
                                    Mastercard aprobada (4444)
                                </button>

                                <button
                                    type="button"
                                    @click="fillTestCard('4000 0000 0000 0002', '12/28', '123')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-acode-danger/40 bg-white px-3 py-1.5 text-xs font-semibold text-acode-danger transition hover:bg-acode-danger/10 focus:outline-none focus:ring-2 focus:ring-acode-danger"
                                >
                                    <x-icon name="warning" class="w-3.5 h-3.5 text-acode-danger" />
                                    Simular rechazo (0002)
                                </button>
                            </div>
                        </div>

                        {{-- Campos de tarjeta --}}
                        <div class="space-y-4">
                            <x-form.group name="card_holder_name" label="Nombre del titular" required>
                                <x-form.input
                                    type="text"
                                    name="card_holder_name"
                                    x-model="cardHolderName"
                                    x-bind:required="paymentMethod === 'card'"
                                    x-bind:disabled="paymentMethod !== 'card'"
                                    maxlength="120"
                                    autocomplete="cc-name"
                                    placeholder="Como aparece en la tarjeta"
                                />
                            </x-form.group>

                            <div>
                                <x-form.group name="card_number" label="Número de tarjeta" required>
                                    <x-form.input
                                        type="text"
                                        name="card_number"
                                        x-model="cardNumber"
                                        @input="formatCardNumber()"
                                        x-bind:required="paymentMethod === 'card'"
                                        x-bind:disabled="paymentMethod !== 'card'"
                                        inputmode="numeric"
                                        maxlength="23"
                                        autocomplete="off"
                                        placeholder="4242 4242 4242 4242"
                                        class="font-mono tracking-wider"
                                    />
                                </x-form.group>

                                <div class="mt-1.5 flex items-center justify-between text-xs" x-show="cardDigits.length > 0" x-cloak>
                                    <span class="font-semibold text-acode-tech" x-text="`Marca detectada: ${cardBrand}`"></span>
                                    <span
                                        class="font-semibold"
                                        :class="isCardLuhnValid ? 'text-acode-accent-dark' : (cardDigits.length >= 13 ? 'text-acode-danger' : 'text-slate-500')"
                                        x-text="cardLuhnMessage"
                                    ></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-form.group name="card_expiry" label="Vigencia (MM/AA)" required>
                                    <x-form.input
                                        type="text"
                                        name="card_expiry"
                                        x-model="cardExpiry"
                                        @input="formatExpiry()"
                                        x-bind:required="paymentMethod === 'card'"
                                        x-bind:disabled="paymentMethod !== 'card'"
                                        inputmode="numeric"
                                        maxlength="5"
                                        autocomplete="off"
                                        placeholder="MM/AA"
                                        class="font-mono"
                                    />
                                </x-form.group>

                                <x-form.group name="card_cvv" label="Código de seguridad (CVV)" required>
                                    <x-form.input
                                        type="password"
                                        name="card_cvv"
                                        x-model="cardCvv"
                                        @input="formatCvv()"
                                        x-bind:required="paymentMethod === 'card'"
                                        x-bind:disabled="paymentMethod !== 'card'"
                                        inputmode="numeric"
                                        maxlength="4"
                                        autocomplete="off"
                                        placeholder="123"
                                        class="font-mono"
                                    />
                                </x-form.group>
                            </div>
                        </div>

                        {{-- Autorización de cobro mensual domiciliado (RF-SUS-003, CAM-010) --}}
                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-white p-3.5 text-xs text-slate-700">
                            <input type="hidden" name="authorize_recurring" value="0" x-bind:disabled="paymentMethod !== 'card'">
                            <input
                                type="checkbox"
                                name="authorize_recurring"
                                value="1"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                                x-bind:disabled="paymentMethod !== 'card'"
                                @checked(old('authorize_recurring', '1') === '1')
                            >
                            <span>
                                Autorizo que esta tarjeta se use para el <strong>cobro mensual automático</strong> del paquete. Puedo cancelar la renovación en cualquier momento desde Suscripción.
                            </span>
                        </label>

                        {{-- Nota de seguridad --}}
                        <div class="flex items-start gap-2.5 rounded-xl border border-acode-accent/30 bg-acode-accent/10 p-3.5 text-xs text-acode-primary">
                            <x-icon name="shield" class="w-4 h-4 text-acode-accent shrink-0 mt-0.5" />
                            <p>
                                Entorno de simulación: nunca almacenamos el número completo ni el CVV de tu tarjeta.
                            </p>
                        </div>
                    </div>

                    {{-- Pestaña 2: Transferencia SPEI al instante --}}
                    <div x-show="paymentMethod === 'spei'" x-cloak class="space-y-5 acode-tab-transition">
                        <div class="rounded-xl border border-acode-tech/30 bg-acode-surface/60 p-4 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-acode-tech">
                                    Datos bancarios SPEI simulados
                                </span>
                                <x-badge variant="info">Acreditación inmediata</x-badge>
                            </div>

                            <div class="grid grid-cols-1 gap-3 text-xs">
                                <div>
                                    <p class="text-slate-500">Beneficiario</p>
                                    <p class="font-bold text-acode-primary mt-0.5">{{ $speiBeneficiary }}</p>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5">
                                    <div>
                                        <p class="text-xs text-slate-500">CLABE interbancaria (18 dígitos)</p>
                                        <p class="font-mono text-sm font-bold text-acode-primary tracking-wider mt-0.5">
                                            {{ $speiClabe }}
                                        </p>
                                    </div>

                                    <x-button
                                        type="button"
                                        variant="neutral"
                                        size="sm"
                                        data-clabe="{{ $speiClabe }}"
                                        x-on:click="copyClabe($el.dataset.clabe)"
                                    >
                                        <x-icon name="copy" class="w-3.5 h-3.5" />
                                        Copiar CLABE
                                    </x-button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <x-form.group name="spei_sender_bank" label="Banco emisor" required>
                                <x-form.select
                                    name="spei_sender_bank"
                                    x-model="speiSenderBank"
                                    x-bind:required="paymentMethod === 'spei'"
                                    x-bind:disabled="paymentMethod !== 'spei'"
                                >
                                    <option value="">Selecciona tu banco emisor</option>
                                    <option value="BBVA México">BBVA México</option>
                                    <option value="Santander">Santander</option>
                                    <option value="Banorte">Banorte</option>
                                    <option value="Citibanamex">Citibanamex</option>
                                    <option value="HSBC">HSBC</option>
                                    <option value="Nu México">Nu México</option>
                                    <option value="Mercado Pago">Mercado Pago</option>
                                    <option value="Otro">Otro</option>
                                </x-form.select>
                            </x-form.group>

                            <x-form.group name="spei_account_holder" label="Titular de la cuenta emisora" required>
                                <x-form.input
                                    type="text"
                                    name="spei_account_holder"
                                    x-model="speiAccountHolder"
                                    x-bind:required="paymentMethod === 'spei'"
                                    x-bind:disabled="paymentMethod !== 'spei'"
                                    maxlength="120"
                                    placeholder="Nombre completo del titular SPEI"
                                />
                            </x-form.group>
                        </div>
                    </div>

                    {{-- Pie con botones oficiales --}}
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3 pt-4 border-t border-slate-100">
                        <x-unsaved-changes
                            form-id="checkout-payment-form"
                            :cancel-url="route('teacher.subscription.index')"
                        />

                        <x-button
                            type="submit"
                            variant="primary"
                            size="md"
                            class="w-full sm:w-auto"
                            x-bind:disabled="isSubmitting"
                        >
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-2">
                                Enviar pago
                            </span>
                            <span x-show="isSubmitting" x-cloak>
                                Procesando pago...
                            </span>
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- Columna resumen (5 cols) --}}
        <div class="lg:col-span-5 space-y-6">
            <x-card title="Resumen del paquete" subtitle="Desglose de contratación y capacidades incluidas">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-acode-tech/20 bg-acode-surface/50 p-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-acode-tech">Plan seleccionado</p>
                            <h3 class="text-xl font-extrabold text-acode-primary mt-0.5">{{ $package->name }}</h3>
                            @if ($activePackage)
                                <p class="text-xs text-slate-500 mt-1">
                                    Plan actual: <strong>{{ $activePackage->name }}</strong>
                                </p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-black text-acode-primary tabular-nums">
                                ${{ number_format((float) $package->monthly_price, 2) }}
                            </p>
                            <p class="text-xs text-slate-500">MXN</p>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs text-slate-600 border-b border-slate-100 pb-4">
                        <div class="flex justify-between">
                            <span>Vigencia del ciclo:</span>
                            <span class="font-semibold text-acode-primary">1 mes / 30 días</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Activación:</span>
                            <span class="font-semibold text-acode-accent-dark">Inmediata al aprobar</span>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5">
                            Capacidades incluidas
                        </p>
                        <ul class="space-y-2 text-xs text-slate-700">
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                @if ($package->max_groups === null)
                                    <span><strong>Ilimitados</strong> grupos escolares</span>
                                @else
                                    <span>Hasta <strong>{{ number_format((int) $package->max_groups) }}</strong> grupos escolares</span>
                                @endif
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                @if ($package->max_questions === null)
                                    <span><strong>Ilimitados</strong> reactivos en banco</span>
                                @else
                                    <span>Hasta <strong>{{ number_format((int) $package->max_questions) }}</strong> reactivos en banco</span>
                                @endif
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>Hasta <strong>{{ $package->max_concurrent_students }}</strong> alumnos en vivo</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                @if ($package->max_active_exams === null)
                                    <span><strong>Ilimitados</strong> exámenes guardados</span>
                                @else
                                    <span>Hasta <strong>{{ number_format((int) $package->max_active_exams) }}</strong> exámenes guardados</span>
                                @endif
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>{{ $package->ai_requests_limit !== null ? 'Hasta ' . number_format((int) $package->ai_requests_limit) . ' evaluaciones IA / mes' : 'Evaluaciones con IA ilimitadas' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </x-card>

            {{-- Alternativa comercial por WhatsApp --}}
            <x-card class="border-acode-accent/30 bg-acode-accent/5">
                <div class="space-y-3">
                    <h3 class="text-sm font-bold text-acode-primary">Atención comercial por WhatsApp</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        ¿Requieres facturación institucional, orden de compra escolar o asistencia personalizada para activar tu plan {{ $package->name }}?
                    </p>
                    <x-button
                        :href="'https://wa.me/' . $whatsappNumber . '?text=' . urlencode('Hola A-Code Solutions, solicito apoyo comercial para contratar el paquete ' . $package->name . ' ($' . $package->monthly_price . ' MXN/mes).' . PHP_EOL . 'Docente: ' . $teacher->full_name . PHP_EOL . 'Correo: ' . $teacher->email)"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="neutral"
                        size="md"
                        class="w-full"
                    >
                        <x-icon name="chat" class="w-4 h-4" />
                        Abrir WhatsApp comercial
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.acodeSimulatedCheckout = function () {
        return {
            paymentMethod: 'card',
            defaultHolder: '',
            cardHolderName: '',
            cardNumber: '',
            cardExpiry: '',
            cardCvv: '',
            speiSenderBank: '',
            speiAccountHolder: '',
            isSubmitting: false,

            init() {
                const ds = this.$el.dataset;
                this.defaultHolder = ds.defaultHolder || '';
                this.paymentMethod = ds.initialMethod === 'spei' ? 'spei' : 'card';
                this.cardHolderName = ds.initialCardHolder || this.defaultHolder;
                this.speiSenderBank = ds.initialSpeiBank || '';
                this.speiAccountHolder = ds.initialSpeiHolder || this.defaultHolder;
            },

            get cardDigits() {
                return (this.cardNumber || '').replace(/\D/g, '').slice(0, 19);
            },

            get cardBrand() {
                const d = this.cardDigits;
                if (d.startsWith('4')) {
                    return 'VISA';
                }
                if (/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/.test(d)) {
                    return 'MASTERCARD';
                }
                if (/^3[47]/.test(d)) {
                    return 'AMEX';
                }
                return 'TARJETA';
            },

            get maskedCardPreview() {
                const d = this.cardDigits;
                if (!d) {
                    return '•••• •••• •••• ••••';
                }
                const last4 = d.length >= 4 ? d.slice(-4) : d.padEnd(4, '•');
                return `•••• •••• •••• ${last4}`;
            },

            get isCardLuhnValid() {
                const d = this.cardDigits;
                if (d.length < 13 || d.length > 19) {
                    return false;
                }
                let sum = 0;
                let shouldDouble = false;
                for (let i = d.length - 1; i >= 0; i--) {
                    let digit = parseInt(d.charAt(i), 10);
                    if (shouldDouble) {
                        digit *= 2;
                        if (digit > 9) {
                            digit -= 9;
                        }
                    }
                    sum += digit;
                    shouldDouble = !shouldDouble;
                }
                return sum % 10 === 0;
            },

            get cardLuhnMessage() {
                if (this.cardDigits.length < 13) {
                    return `${this.cardDigits.length}/16 dígitos`;
                }
                return this.isCardLuhnValid ? 'Formato Luhn válido' : 'Número inválido (Luhn)';
            },

            formatCardNumber() {
                const digits = this.cardDigits;
                const groups = digits.match(/.{1,4}/g);
                this.cardNumber = groups ? groups.join(' ') : '';
            },

            formatExpiry() {
                const digits = (this.cardExpiry || '').replace(/\D/g, '').slice(0, 4);
                if (digits.length > 2) {
                    this.cardExpiry = `${digits.slice(0, 2)}/${digits.slice(2)}`;
                } else {
                    this.cardExpiry = digits;
                }
            },

            formatCvv() {
                this.cardCvv = (this.cardCvv || '').replace(/\D/g, '').slice(0, 4);
            },

            fillTestCard(number, expiry, cvv) {
                this.paymentMethod = 'card';
                this.cardNumber = number;
                this.cardExpiry = expiry;
                this.cardCvv = cvv;
                if (!this.cardHolderName || this.cardHolderName.trim() === '') {
                    this.cardHolderName = this.defaultHolder;
                }
            },

            async copyClabe(clabe) {
                try {
                    await navigator.clipboard.writeText(clabe);
                    if (typeof window.AcodeAlert !== 'undefined') {
                        window.AcodeAlert.toast('CLABE copiada al portapapeles', 'success');
                    }
                } catch (e) {
                    if (typeof window.AcodeAlert !== 'undefined') {
                        window.AcodeAlert.error('No se pudo copiar la CLABE', 'Cópiala manualmente desde la pantalla.');
                    }
                }
            }
        };
    };
</script>
@endpush
