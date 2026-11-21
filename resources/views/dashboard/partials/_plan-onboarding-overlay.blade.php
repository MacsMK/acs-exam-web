{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/dashboard/partials/_plan-onboarding-overlay.blade.php
    Ubicación:   resources/views/dashboard/partials/_plan-onboarding-overlay.blade.php
    Descripción: Capa bloqueante post-verificación docente sobre el Dashboard
                 con flujo Alpine.js en 2 pasos (selección de paquete o prueba
                 gratuita + pago simulado con tarjeta Luhn o SPEI) sin cierre
                 por Escape, clic fuera ni botón X (ADR-060, MPW-V3 §7 y §8).
    ============================================================================
--}}
<div
    class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/65 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="onboarding-overlay-title"
    x-data="acodePlanOnboardingOverlay()"
>
    <div class="flex min-h-full items-start justify-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-6xl rounded-2xl border border-slate-200 bg-acode-surface shadow-2xl overflow-hidden my-auto">

            {{-- Barra superior institucional del overlay (sin botón X de cierre) --}}
            <div class="bg-acode-primary px-5 py-4 sm:px-7 text-white border-b border-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-acode-accent">
                                <x-icon name="shield" class="w-5 h-5" />
                            </div>
                            <span class="text-sm sm:text-base font-bold tracking-tight text-white">
                                A-Code Solutions · Evaluador de Exámenes
                            </span>
                        </div>
                        <x-badge variant="accent" class="bg-acode-accent/25 text-white border-acode-accent/60">
                            Cuenta verificada · Paso obligatorio
                        </x-badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <a
                            href="{{ route('privacy.show.public') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-white/75 hover:text-white underline underline-offset-2 transition"
                        >
                            Aviso de privacidad
                        </a>

                        <form method="POST" action="{{ route('auth.logout') }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-acode-danger hover:border-acode-danger focus:outline-none focus:ring-2 focus:ring-white/40"
                            >
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Cuerpo principal del asistente en 2 pasos --}}
            <div class="p-5 sm:p-7 space-y-6">

                {{-- Encabezado contextual e indicador de pasos --}}
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="text-xs font-semibold uppercase tracking-wider text-acode-tech" x-text="step === 1 ? 'Paso 1 de 2 · Elige tu modalidad' : 'Paso 2 de 2 · Pago y activación inmediata'"></span>
                        </div>
                        <h2 id="onboarding-overlay-title" class="text-xl sm:text-2xl font-bold text-acode-primary tracking-tight">
                            ¡Bienvenido, {{ $onboardingOverlay['teacher']->first_name }}! Elige cómo iniciar en la plataforma
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Para desbloquear tu panel docente selecciona la Prueba Gratuita (Freemium) o activa uno de nuestros paquetes mensuales al instante.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 text-xs font-semibold">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 border transition"
                            :class="step === 1
                                ? 'bg-acode-primary text-white border-acode-primary'
                                : 'bg-acode-accent/15 text-acode-primary border-acode-accent/40'"
                        >
                            <span>1. Selección de plan</span>
                        </span>
                        <span class="text-slate-400">→</span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 border transition"
                            :class="step === 2
                                ? 'bg-acode-primary text-white border-acode-primary'
                                : 'bg-white text-slate-500 border-slate-200'"
                        >
                            <span>2. Pago simulado</span>
                        </span>
                    </div>
                </div>

                {{-- ============================================================ --}}
                {{-- PASO 1: SELECCIÓN DE PRUEBA GRATUITA O PAQUETE OFICIAL       --}}
                {{-- ============================================================ --}}
                <div x-show="step === 1" class="space-y-6">

                    {{-- Tarjeta destacada: Prueba Gratuita (Freemium - $0 MXN) --}}
                    <x-card class="border-acode-accent/50 bg-gradient-to-r from-white via-white to-acode-accent/10">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <x-badge variant="accent">Freemium · $0 MXN</x-badge>
                                    <h3 class="text-lg font-extrabold text-acode-primary">
                                        Prueba Gratuita (Freemium — $0 MXN)
                                    </h3>
                                </div>

                                <p class="text-xs sm:text-sm text-slate-600 max-w-3xl">
                                    Explora el flujo completo de creación de grupos, banco de reactivos, aplicación segura de exámenes y evaluación asistida con IA sin costo inicial (límite histórico acumulado).
                                </p>

                                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 text-xs text-slate-700 pt-1">
                                    <li class="flex items-center gap-1.5 rounded-lg bg-white/90 border border-slate-200 px-2.5 py-2">
                                        <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                        <span><strong>{{ $onboardingOverlay['freemiumLimits']['max_groups'] }}</strong> grupo histórico</span>
                                    </li>
                                    <li class="flex items-center gap-1.5 rounded-lg bg-white/90 border border-slate-200 px-2.5 py-2">
                                        <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                        <span><strong>{{ $onboardingOverlay['freemiumLimits']['max_exams'] }}</strong> examen histórico</span>
                                    </li>
                                    <li class="flex items-center gap-1.5 rounded-lg bg-white/90 border border-slate-200 px-2.5 py-2">
                                        <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                        <span><strong>{{ number_format((int) $onboardingOverlay['freemiumLimits']['max_questions']) }}</strong> reactivos</span>
                                    </li>
                                    <li class="flex items-center gap-1.5 rounded-lg bg-white/90 border border-slate-200 px-2.5 py-2">
                                        <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                        <span><strong>{{ $onboardingOverlay['freemiumLimits']['max_students'] }}</strong> alumnos por grupo</span>
                                    </li>
                                    <li class="flex items-center gap-1.5 rounded-lg bg-white/90 border border-slate-200 px-2.5 py-2">
                                        <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                        <span><strong>{{ $onboardingOverlay['freemiumLimits']['max_ai_evaluations'] }}</strong> evaluaciones asistidas con IA</span>
                                    </li>
                                </ul>
                            </div>

                            <form method="POST" action="{{ route('teacher.subscription.free-trial') }}" class="shrink-0">
                                @csrf
                                <x-button type="submit" variant="accent" size="lg" class="w-full lg:w-auto">
                                    <x-icon name="check" class="w-4 h-4" />
                                    Confirmar prueba gratuita
                                </x-button>
                            </form>
                        </div>
                    </x-card>

                    {{-- Grid de los 3 paquetes oficiales (Básico, Avanzado, Pro) --}}
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-base font-bold text-acode-primary">
                                O activa un paquete mensual con activación inmediata
                            </h3>
                            <span class="text-xs text-slate-500">
                                Pago simulado con tarjeta bancaria (Luhn) o transferencia SPEI al instante
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            @forelse ($onboardingOverlay['packages'] as $pkg)
                                <x-card class="flex flex-col justify-between border-slate-200 hover:border-acode-tech hover:shadow-md transition">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <h4 class="text-lg font-bold text-acode-primary">{{ $pkg->name }}</h4>
                                            @if ($pkg->tier === 'pro')
                                                <x-badge variant="tech">Recomendado</x-badge>
                                            @endif
                                        </div>

                                        <div class="flex items-baseline gap-1 my-3">
                                            <span class="text-3xl font-extrabold text-acode-primary">
                                                ${{ number_format((float) $pkg->monthly_price, 0) }}
                                            </span>
                                            <span class="text-xs text-slate-500">MXN / mes</span>
                                        </div>

                                        <ul class="space-y-2.5 text-xs text-slate-600 my-4">
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
                                                    <span><strong>Ilimitados</strong> exámenes activos</span>
                                                @else
                                                    <span>Hasta <strong>{{ number_format((int) $pkg->max_active_exams) }}</strong> exámenes activos</span>
                                                @endif
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                                <span>
                                                    {{ $pkg->ai_requests_limit !== null ? number_format((int) $pkg->ai_requests_limit) . ' evaluaciones IA / mes' : 'Evaluaciones con IA ilimitadas' }}
                                                </span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="pt-4 border-t border-slate-100">
                                        <x-button
                                            type="button"
                                            variant="primary"
                                            class="w-full"
                                            @click="selectPackage({{ $pkg->id }})"
                                        >
                                            <x-icon name="lock" class="w-4 h-4" />
                                            Seleccionar paquete
                                        </x-button>
                                    </div>
                                </x-card>
                            @empty
                                <div class="md:col-span-3">
                                    <x-empty-state
                                        icon="document"
                                        title="Sin paquetes comerciales activos"
                                        message="Por ahora puedes iniciar inmediatamente con tu Prueba Gratuita (Freemium)."
                                    />
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- ============================================================ --}}
                {{-- PASO 2: PAGO SIMULADO INTEGRADO (TARJETA LUHN O SPEI)        --}}
                {{-- ============================================================ --}}
                <div x-show="step === 2" x-cloak class="space-y-5">

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <x-button type="button" variant="neutral" size="sm" @click="goBackToStep1()">
                            <x-icon name="arrowLeft" class="w-4 h-4" />
                            Cambiar paquete
                        </x-button>
                        <x-badge variant="info">Pago simulado seguro · Activación inmediata</x-badge>
                    </div>

                    {{-- Alerta por rechazo bancario simulado --}}
                    @if ($errors->has('payment_gateway'))
                        <x-card class="border-acode-danger/40 bg-acode-danger/5">
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-acode-danger/15 text-acode-danger">
                                    <x-icon name="error" class="w-5 h-5" />
                                </div>
                                <div class="space-y-1">
                                    <h3 class="text-sm font-bold text-acode-danger">Operación rechazada por el banco</h3>
                                    <p class="text-sm text-acode-ink">{{ $errors->first('payment_gateway') }}</p>
                                    <p class="text-xs text-slate-600">
                                        Corrige los datos de tu tarjeta, prueba con otra tarjeta de demostración o cambia a transferencia SPEI para reintentar al instante.
                                    </p>
                                </div>
                            </div>
                        </x-card>
                    @endif

                    {{-- Resumen de errores de validación de campos --}}
                    @if ($errors->any() && ! $errors->has('payment_gateway'))
                        <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm text-acode-danger" role="alert">
                            <p class="font-bold mb-1">Revisa los datos ingresados para continuar:</p>
                            <ul class="list-disc list-inside space-y-0.5 text-xs">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        {{-- Columna izquierda (7 cols): Formulario de pago simulado --}}
                        <div class="lg:col-span-7">
                            <x-card>
                                <form
                                    method="POST"
                                    :action="selectedPackage ? selectedPackage.payUrl : ''"
                                    @submit="isSubmitting = true"
                                    class="space-y-6"
                                >
                                    @csrf
                                    <input type="hidden" name="onboarding_overlay" value="1">
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
                                    <div x-show="paymentMethod === 'card'" class="space-y-5">
                                        {{-- Tarjeta visual previa --}}
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

                                            <div class="my-5">
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

                                        {{-- Barra de tarjetas demo en 1 clic --}}
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

                                        <div class="flex items-start gap-2.5 rounded-xl border border-acode-accent/30 bg-acode-accent/10 p-3.5 text-xs text-acode-primary">
                                            <x-icon name="shield" class="w-4 h-4 text-acode-accent shrink-0 mt-0.5" />
                                            <p>
                                                Entorno de simulación: nunca almacenamos el número completo ni el CVV de tu tarjeta.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Pestaña 2: Transferencia SPEI al instante --}}
                                    <div x-show="paymentMethod === 'spei'" x-cloak class="space-y-5">
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
                                                    <p class="font-bold text-acode-primary mt-0.5">{{ $onboardingOverlay['speiBeneficiary'] }}</p>
                                                </div>

                                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5">
                                                    <div>
                                                        <p class="text-[11px] text-slate-500">CLABE Interbancaria (18 dígitos)</p>
                                                        <p class="font-mono text-sm font-bold text-acode-primary tracking-wider mt-0.5">
                                                            {{ $onboardingOverlay['speiClabe'] }}
                                                        </p>
                                                    </div>

                                                    <x-button
                                                        type="button"
                                                        variant="neutral"
                                                        size="sm"
                                                        data-clabe="{{ $onboardingOverlay['speiClabe'] }}"
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

                                    {{-- Botón de confirmación de pago --}}
                                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-slate-100">
                                        <x-button type="button" variant="neutral" @click="goBackToStep1()" class="w-full sm:w-auto">
                                            Cambiar paquete
                                        </x-button>

                                        <x-button
                                            type="submit"
                                            variant="primary"
                                            class="w-full sm:w-auto"
                                            x-bind:disabled="isSubmitting || !selectedPackage"
                                        >
                                            <span x-show="!isSubmitting" class="inline-flex items-center gap-2">
                                                <x-icon name="lock" class="w-4 h-4" />
                                                Confirmar y activar plan
                                            </span>
                                            <span x-show="isSubmitting" x-cloak>
                                                Procesando pago...
                                            </span>
                                        </x-button>
                                    </div>
                                </form>
                            </x-card>
                        </div>

                        {{-- Columna derecha (5 cols): Resumen del paquete elegido y WhatsApp comercial --}}
                        <div class="lg:col-span-5 space-y-4">
                            <x-card title="Resumen del paquete" subtitle="Activación inmediata al confirmar tu pago">
                                <div class="space-y-4" x-show="selectedPackage">
                                    <div class="flex items-start justify-between gap-3 rounded-xl border border-acode-tech/20 bg-acode-surface/60 p-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-acode-tech">Plan seleccionado</p>
                                            <h3 class="text-xl font-extrabold text-acode-primary mt-0.5" x-text="selectedPackage ? selectedPackage.name : ''"></h3>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-black text-acode-primary" x-text="selectedPackage ? `$${selectedPackage.priceFormatted}` : ''"></p>
                                            <p class="text-xs text-slate-500">MXN / mes</p>
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
                                                <span x-text="selectedPackage ? selectedPackage.groupsLabel : ''"></span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                                <span x-text="selectedPackage ? selectedPackage.questionsLabel : ''"></span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                                <span x-text="selectedPackage ? selectedPackage.studentsLabel : ''"></span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                                <span x-text="selectedPackage ? selectedPackage.examsLabel : ''"></span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <x-icon name="check" class="w-4 h-4 text-acode-accent shrink-0" />
                                                <span x-text="selectedPackage ? selectedPackage.aiLabel : ''"></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </x-card>

                            <div class="rounded-xl border border-acode-accent/30 bg-white p-4 text-xs text-slate-600 space-y-2.5 shadow-sm">
                                <p class="font-semibold text-acode-primary">
                                    ¿Necesitas facturación institucional o asesoría comercial?
                                </p>
                                <a
                                    :href="selectedPackage ? selectedPackage.whatsappUrl : '#'"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2 font-semibold text-acode-accent-dark hover:underline"
                                >
                                    <span>Atención comercial por WhatsApp →</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.__acodePlanOnboardingConfig = @js([
        'packages' => $onboardingOverlay['packages']->map(static function ($pkg) use ($onboardingOverlay): array {
            $teacher = $onboardingOverlay['teacher'];
            $waText = "Hola A-Code Solutions, solicito apoyo comercial para activar el paquete {$pkg->name} (\${$pkg->monthly_price} MXN/mes).\nDocente: {$teacher->full_name}\nCorreo: {$teacher->email}";

            return [
                'id' => (int) $pkg->id,
                'name' => (string) $pkg->name,
                'priceFormatted' => number_format((float) $pkg->monthly_price, 2),
                'payUrl' => route('teacher.subscription.pay', $pkg),
                'groupsLabel' => $pkg->max_groups !== null
                    ? 'Hasta ' . number_format((int) $pkg->max_groups) . ' grupos escolares'
                    : 'Grupos escolares ilimitados',
                'questionsLabel' => $pkg->max_questions !== null
                    ? 'Hasta ' . number_format((int) $pkg->max_questions) . ' reactivos en banco'
                    : 'Reactivos en banco ilimitados',
                'studentsLabel' => 'Hasta ' . (int) $pkg->max_concurrent_students . ' alumnos en vivo',
                'examsLabel' => $pkg->max_active_exams !== null
                    ? 'Hasta ' . number_format((int) $pkg->max_active_exams) . ' exámenes activos'
                    : 'Exámenes activos ilimitados',
                'aiLabel' => $pkg->ai_requests_limit !== null
                    ? number_format((int) $pkg->ai_requests_limit) . ' evaluaciones IA / mes'
                    : 'Evaluaciones con IA ilimitadas',
                'whatsappUrl' => 'https://wa.me/' . $onboardingOverlay['whatsappNumber'] . '?text=' . urlencode($waText),
            ];
        })->values()->all(),
        'initialSelectedPackageId' => session('onboarding_selected_package_id') !== null
            ? (int) session('onboarding_selected_package_id')
            : null,
        'defaultHolder' => (string) $onboardingOverlay['teacher']->full_name,
        'initialMethod' => (string) old('payment_method', 'card'),
        'initialCardHolder' => (string) old('card_holder_name', $onboardingOverlay['teacher']->full_name),
        'initialSpeiBank' => (string) old('spei_sender_bank', ''),
        'initialSpeiHolder' => (string) old('spei_account_holder', $onboardingOverlay['teacher']->full_name),
    ]);

    window.acodePlanOnboardingOverlay = function () {
        const cfg = window.__acodePlanOnboardingConfig || {};
        const packages = Array.isArray(cfg.packages) ? cfg.packages : [];
        const initialPkg = cfg.initialSelectedPackageId
            ? packages.find((p) => Number(p.id) === Number(cfg.initialSelectedPackageId)) || null
            : null;

        return {
            step: initialPkg ? 2 : 1,
            packages: packages,
            selectedPackage: initialPkg,
            paymentMethod: cfg.initialMethod === 'spei' ? 'spei' : 'card',
            defaultHolder: cfg.defaultHolder || '',
            cardHolderName: cfg.initialCardHolder || cfg.defaultHolder || '',
            cardNumber: '',
            cardExpiry: '',
            cardCvv: '',
            speiSenderBank: cfg.initialSpeiBank || '',
            speiAccountHolder: cfg.initialSpeiHolder || cfg.defaultHolder || '',
            isSubmitting: false,

            selectPackage(packageId) {
                const found = this.packages.find((p) => Number(p.id) === Number(packageId));
                if (!found) {
                    return;
                }
                this.selectedPackage = found;
                this.step = 2;
                this.isSubmitting = false;
            },

            goBackToStep1() {
                this.step = 1;
                this.isSubmitting = false;
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
