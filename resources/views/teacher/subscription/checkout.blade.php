{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/subscription/checkout.blade.php (MPW-V3 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 10) --}}
@extends('layouts.app')

@section('title', 'Pago en línea')

@section('content')
<div
    x-data="acodeSubscriptionPayment(@js([
        'defaultHolder' => $teacher->full_name,
        'paymentMethod' => old('payment_method') === 'spei' ? 'spei' : 'card',
        'cardHolderName' => (string) old('card_holder_name', $teacher->full_name),
        'speiSenderBank' => (string) old('spei_sender_bank', ''),
        'speiAccountHolder' => (string) old('spei_account_holder', $teacher->full_name),
    ]))"
    x-on:pageshow.window="isSubmitting = false"
    class="space-y-6"
>
    <x-page-header
        title="Pago en línea"
        :institutional="true"
    >
        <x-slot:actions>
            <div x-show="!isSubmitting">
                <x-button :href="route('teacher.subscription.index')" variant="neutral">
                    <x-icon name="arrowLeft" class="h-4 w-4" />
                    Volver
                </x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="grid items-start gap-5 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <x-subscription.package-summary :package="$package" />
        </div>
        <div class="min-w-0 lg:col-span-7">
            <x-subscription.payment-form
                :action="route('teacher.subscription.pay', $package)"
                :spei-beneficiary="$speiBeneficiary"
                :spei-clabe="$speiClabe"
                :cancel-url="route('teacher.subscription.index')"
            />
        </div>
    </div>

    <x-subscription.commercial-contact :package="$package" :teacher="$teacher" :whatsapp-number="$whatsappNumber" />
</div>
@endsection
