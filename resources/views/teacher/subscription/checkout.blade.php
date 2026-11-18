{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/subscription/checkout.blade.php (MPW-V2 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Pago en línea simulado')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Encabezado --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-3">
                <x-button href="{{ route('teacher.subscription.index') }}" variant="neutral" size="sm">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
                <x-badge variant="info">Simulación segura</x-badge>
            </div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Pago en línea</h1>
            <p class="text-sm text-slate-500">
                Activa o actualiza tu paquete docente al instante mediante tarjeta o transferencia SPEI simulada.
            </p>
        </div>
    </div>

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
                                    <p class="text-[11px]" :class="paymentMethod === 'card' ? 'text-white/75' : 'text-slate-500'">
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
                                    <p class="text-[11px]" :class="paymentMethod === 'spei' ? 'text-white/75' : 'text-slate-500'">
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
                                <span class="text-[11px] font-semibold uppercase tracking-widest text-white/75">
                                    A-Code Pay · Simulador
                                </span>
                                <span
                                    class="rounded-md bg-white/15 px-2.5 py-1 font-mono text-xs font-bold tracking-wider text-white"
                                    x-text="cardBrand"
                                ></span>
                            </div>

                            <div class="my-6">
                                <p class="text-[11px] uppercase tracking-wider text-white/60 mb-1">Número de tarjeta</p>
                                <p class="font-mono text-lg sm:text-xl font-bold tracking-widest" x-text="maskedCardPreview"></p>
                            </div>

                            <div class="flex items-end justify-between gap-4 pt-2 border-t border-white/15">
                                <div class="min-w-0">
                                    <p class="text-[10px] uppercase tracking-wider text-white/60">Titular</p>
                                    <p
                                        class="truncate text-xs sm:text-sm font-semibold uppercase tracking-wide"
                                        x-text="cardHolderName.trim() !== '' ? cardHolderName.toUpperCase() : 'NOMBRE DEL TITULAR'"
                                    ></p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-[10px] uppercase tracking-wider text-white/60">Vigencia</p>
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

                                <div class="mt-1.5 flex items-center justify-between text-[11px]" x-show="cardDigits.length > 0" x-cloak>
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
                                        <p class="text-[11px] text-slate-500">CLABE Interbancaria (18 dígitos)</p>
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
                        <x-button href="{{ route('teacher.subscription.index') }}" variant="neutral" class="w-full sm:w-auto">
                            Cancelar
                        </x-button>

                        <x-button
                            type="submit"
                            variant="primary"
                            class="w-full sm:w-auto"
                            x-bind:disabled="isSubmitting"
                        >
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-2">
                                <x-icon name="lock" class="w-4 h-4" />
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
        <div class="lg:col-span-5 space-y-5">
            <x-card title="Resumen del paquete" subtitle="Desglose de contratación y capacidades incluidas">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-acode-tech/20 bg-acode-surface/50 p-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-acode-tech">Plan seleccionado</p>
                            <h3 class="text-xl font-extrabold text-acode-primary mt-0.5">{{ $package->name }}</h3>
                            @if ($activePackage)
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Plan actual: <strong>{{ $activePackage->name }}</strong>
                                </p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-black text-acode-primary">
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
                                <span>Hasta <strong>{{ $package->max_groups ?? 'Ilimitados' }}</strong> grupos escolares</span>
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
                                <span>Hasta <strong>{{ $package->max_active_exams }}</strong> exámenes guardados</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                <span>Evaluación con IA Gemini incluida</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </x-card>

            {{-- Alternativa comercial por WhatsApp --}}
            <x-card class="border-acode-accent/30 bg-acode-accent/5">
                <div class="space-y-3">
                    <h3 class="text-sm font-bold text-acode-primary">Atención Comercial por WhatsApp</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        ¿Requieres facturación institucional, orden de compra escolar o asistencia personalizada para activar tu plan {{ $package->name }}?
                    </p>
                    <a
                        href="https://wa.me/{{ $whatsappNumber }}?text={{ urlencode("Hola A-Code Solutions, solicito apoyo comercial para contratar el paquete {$package->name} (\${$package->monthly_price} MXN/mes).\nDocente: {$teacher->full_name}\nCorreo: {$teacher->email}") }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-acode-accent hover:bg-acode-accent-dark px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all duration-200"
                    >
                        <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                        </svg>
                        Contactar por WhatsApp
                    </a>
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
