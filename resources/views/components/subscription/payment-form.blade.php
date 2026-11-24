@props([
    'action' => null,
    'onboarding' => false,
    'speiBeneficiary',
    'speiClabe',
    'cancelUrl' => null,
])

@php
    $method = old('payment_method') === 'spei' ? 'spei' : 'card';
    $demoExpiry = now()->addYears(2)->format('m/y');
@endphp

<x-card padding="p-4 sm:p-6">
    <form
        id="subscription-payment-form"
        method="POST"
        @if ($onboarding)
            x-bind:action="selectedPackage?.payUrl"
        @else
            action="{{ $action }}"
        @endif
        @submit="beginPayment($event)"
        aria-labelledby="subscription-payment-title"
        class="space-y-5"
    >
        @csrf
        @if ($onboarding)
            <input type="hidden" name="onboarding_overlay" value="1">
        @endif

        <h2 id="subscription-payment-title" x-ref="paymentTitle" tabindex="-1" class="text-xl leading-7 font-semibold text-acode-primary">Pago simulado</h2>

        @if ($errors->any())
            <div x-ref="paymentError" role="alert" tabindex="-1" class="space-y-2 rounded-xl border border-acode-danger/40 bg-acode-danger/5 p-4 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech">
                @if ($errors->has('payment_gateway'))
                    <p class="font-semibold text-acode-danger-dark">Pago simulado rechazado</p>
                    <p>{{ $errors->first('payment_gateway') }}</p>
                    <p x-show="paymentMethod === 'card'">Usa otra tarjeta de demostración o cambia a SPEI.</p>
                    <p x-show="paymentMethod === 'spei'" x-cloak>Revisa los datos de SPEI o cambia a tarjeta.</p>
                    <x-button type="button" variant="primary" @click="retryPayment()">Reintentar</x-button>
                @else
                    <p class="font-semibold text-acode-danger-dark">Revisa los datos del pago</p>
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <p class="text-sm text-slate-700">Usa datos de demostración. Al aprobar el pago simulado, se activa un mes de vigencia del paquete.</p>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-acode-primary">Método de pago</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    class="flex min-h-12 items-center gap-3 rounded-lg border px-3.5 py-2.5 text-sm font-medium text-acode-primary focus-within:ring-2 focus-within:ring-acode-tech"
                    :class="paymentMethod === 'card' ? 'border-acode-tech bg-acode-surface' : 'border-slate-500 bg-white'"
                >
                    <input type="radio" name="payment_method" value="card" x-model="paymentMethod" @checked($method === 'card') aria-controls="subscription-card-fields" required class="h-4 w-4 shrink-0 accent-acode-tech">
                    <span>Tarjeta de crédito o débito</span>
                </label>
                <label
                    class="flex min-h-12 items-center gap-3 rounded-lg border px-3.5 py-2.5 text-sm font-medium text-acode-primary focus-within:ring-2 focus-within:ring-acode-tech"
                    :class="paymentMethod === 'spei' ? 'border-acode-tech bg-acode-surface' : 'border-slate-500 bg-white'"
                >
                    <input type="radio" name="payment_method" value="spei" x-model="paymentMethod" @checked($method === 'spei') aria-controls="subscription-spei-fields" class="h-4 w-4 shrink-0 accent-acode-tech">
                    <span>Transferencia SPEI</span>
                </label>
            </div>
        </fieldset>

        <fieldset id="subscription-card-fields" x-show="paymentMethod === 'card'" x-bind:disabled="paymentMethod !== 'card'" @disabled($method !== 'card') @if ($method !== 'card') x-cloak @endif class="min-w-0 space-y-4">
            <legend class="sr-only">Datos de tarjeta de demostración</legend>
            <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3.5">
                <p class="text-sm font-semibold text-acode-primary">Tarjetas de demostración</p>
                <p class="text-xs text-slate-700">Selecciona una tarjeta para completar los datos de prueba.</p>
                <div class="flex flex-wrap gap-2">
                    <x-button type="button" variant="neutral" data-number="4242 4242 4242 4242" :data-expiry="$demoExpiry" data-cvv="123" @click="fillTestCard($el)">
                        Visa aprobada (4242)
                    </x-button>
                    <x-button type="button" variant="neutral" data-number="5555 5555 5555 4444" :data-expiry="$demoExpiry" data-cvv="456" @click="fillTestCard($el)">
                        Mastercard aprobada (4444)
                    </x-button>
                    <x-button type="button" variant="neutral" data-number="4000 0000 0000 0002" :data-expiry="$demoExpiry" data-cvv="123" @click="fillTestCard($el)">
                        Simular rechazo (0002)
                    </x-button>
                </div>
            </div>

            <x-form.group name="card_holder_name" label="Nombre del titular" :accessible="true" required>
                <x-form.input name="card_holder_name" :accessible="true" x-model="cardHolderName" :value="old('card_holder_name')" :aria-describedby="$errors->has('card_holder_name') ? 'card_holder_name-error' : null" maxlength="120" autocomplete="off" required />
            </x-form.group>

            <x-form.group name="card_number" label="Número de tarjeta" :accessible="true" required>
                <x-form.input name="card_number" :accessible="true" x-model="cardNumber" x-ref="cardNumber" @input="formatCardNumber()" :aria-describedby="$errors->has('card_number') ? 'card_number-error subscription-card-number-status' : 'subscription-card-number-status'" inputmode="numeric" maxlength="23" autocomplete="off" class="font-mono" required />
                <p id="subscription-card-number-status" class="text-xs text-slate-700" x-text="cardNumberStatus">El número de demostración debe tener entre 13 y 19 dígitos.</p>
            </x-form.group>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.group name="card_expiry" label="Vigencia de la tarjeta (MM/AA)" :accessible="true" required>
                    <x-form.input name="card_expiry" :accessible="true" x-model="cardExpiry" @input="formatExpiry()" :aria-describedby="$errors->has('card_expiry') ? 'card_expiry-error' : null" inputmode="numeric" maxlength="5" autocomplete="off" class="font-mono" required />
                </x-form.group>

                <x-form.group name="card_cvv" label="Código de seguridad (CVV)" :accessible="true" required>
                    <x-form.input name="card_cvv" type="password" :accessible="true" x-model="cardCvv" @input="formatCvv()" :aria-describedby="$errors->has('card_cvv') ? 'card_cvv-error' : null" inputmode="numeric" maxlength="4" autocomplete="off" class="font-mono" required />
                </x-form.group>
            </div>

            <input type="hidden" name="authorize_recurring" value="0">
            <label class="flex min-h-12 items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-sm text-slate-700">
                <input type="checkbox" name="authorize_recurring" value="1" @checked((string) old('authorize_recurring', '0') === '1') class="mt-0.5 h-4 w-4 shrink-0 accent-acode-tech">
                <span>
                    <strong>Autorizar la renovación automática mensual</strong>
                    <span class="mt-1 block text-xs">Se simulará un cobro cada mes. Puedes cancelar la renovación desde Suscripción.</span>
                </span>
            </label>
        </fieldset>

        <fieldset id="subscription-spei-fields" x-show="paymentMethod === 'spei'" x-bind:disabled="paymentMethod !== 'spei'" @disabled($method !== 'spei') @if ($method !== 'spei') x-cloak @endif class="min-w-0 space-y-4">
            <legend class="sr-only">Datos de la transferencia SPEI</legend>
            <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5">
                <p class="text-sm font-semibold text-acode-primary">Datos SPEI de demostración</p>
                <p class="text-sm text-slate-700">Completa los datos para simular la transferencia. La vigencia es de un mes, sin renovación automática.</p>
                <dl class="space-y-2 text-sm text-slate-700">
                    <div>
                        <dt>Beneficiario</dt>
                        <dd class="font-semibold text-acode-primary">{{ $speiBeneficiary }}</dd>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-between">
                        <div>
                            <dt>CLABE interbancaria</dt>
                            <dd class="break-all font-mono text-acode-primary">{{ $speiClabe }}</dd>
                        </div>
                        <x-button type="button" variant="neutral" data-clabe="{{ $speiClabe }}" @click="copyClabe($el.dataset.clabe)">
                            <x-icon name="copy" class="h-4 w-4" />
                            Copiar CLABE
                        </x-button>
                    </div>
                </dl>
                <p role="status" x-text="clipboardMessage" class="text-xs text-slate-700"></p>
            </div>

            <x-form.group name="spei_sender_bank" label="Banco emisor" :accessible="true" required>
                <x-form.select name="spei_sender_bank" :accessible="true" x-model="speiSenderBank" x-ref="speiBank" :aria-describedby="$errors->has('spei_sender_bank') ? 'spei_sender_bank-error' : null" required>
                    <option value="">Selecciona tu banco emisor</option>
                    @foreach (['BBVA México', 'Santander', 'Banorte', 'Citibanamex', 'HSBC', 'Nu México', 'Mercado Pago', 'Otro'] as $bank)
                        <option value="{{ $bank }}" @selected(old('spei_sender_bank') === $bank)>{{ $bank }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group name="spei_account_holder" label="Titular de la cuenta emisora" :accessible="true" required>
                <x-form.input name="spei_account_holder" :accessible="true" x-model="speiAccountHolder" :value="old('spei_account_holder')" :aria-describedby="$errors->has('spei_account_holder') ? 'spei_account_holder-error' : null" maxlength="120" required />
            </x-form.group>
        </fieldset>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4" x-bind:aria-busy="isSubmitting.toString()">
            @if ($cancelUrl)
                <div x-show="!isSubmitting">
                    <x-unsaved-changes form-id="subscription-payment-form" :cancel-url="$cancelUrl" />
                </div>
            @endif
            <x-button type="submit" variant="primary" loading-when="isSubmitting" x-bind:disabled="isSubmitting" class="w-full sm:w-auto">
                <span x-show="!isSubmitting">Enviar pago simulado</span>
                <span x-show="isSubmitting" x-cloak>Procesando pago</span>
            </x-button>
        </div>
    </form>
    <span role="status" class="sr-only" x-text="isSubmitting ? 'Procesando pago' : ''"></span>
</x-card>

@once
    @push('scripts')
        <script src="{{ asset('js/subscription-payment.js') }}"></script>
    @endpush
@endonce
