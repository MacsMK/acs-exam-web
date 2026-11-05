@extends('layouts.verify-2fa')

@section('title', 'Verificación en dos pasos')

@section('content')
    @php
        // Oculta parcialmente el correo solo para su presentación.
        $emailParts = explode('@', $user->email, 2);
        $emailName = $emailParts[0];
        $emailDomain = $emailParts[1] ?? '';

        $visibleCharacters = min(2, strlen($emailName));

        $maskedEmail = substr($emailName, 0, $visibleCharacters)
            . '******@' . $emailDomain;
    @endphp

    <div class="text-center mb-8">

        <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-acode-accent/10 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="32"
                 height="32"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="#16A6A1"
                 stroke-width="1.8"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="2" y="4" width="20" height="16" rx="3"/>
                <path d="m3 6 9 7 9-7"/>
                <path d="m16 16 2 2 4-4"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-acode-primary mb-3">
            Verifica tu identidad
        </h1>

        <p class="text-sm text-slate-500 leading-relaxed">
            Ingresa el código de 6 dígitos que enviamos
            a tu correo electrónico.
        </p>

        <div class="inline-flex items-center justify-center mt-4 px-4 py-2 rounded-lg bg-slate-100">
            <span class="text-sm font-semibold text-acode-primary break-all">
                {{ $maskedEmail }}
            </span>
        </div>

    </div>

    <form method="POST"
          action="{{ route('auth.verify-2fa.store') }}"
          class="space-y-5">
        @csrf

        <input type="hidden" name="user_id" value="{{ $user->id }}">

        <div>
            <label for="verification-code"
                   class="block text-sm font-semibold text-slate-700 mb-2">
                Código de verificación
            </label>

            <input id="verification-code"
                   type="text"
                   name="code"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   maxlength="6"
                   pattern="[0-9]{6}"
                   required
                   autofocus
                   value="{{ old('code', '') }}"
                   aria-describedby="code-help code-warning"
                   aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"
                   class="w-full h-12 px-4 rounded-lg border {{ $errors->has('code') ? 'border-acode-danger' : 'border-slate-300' }} bg-white text-center text-xl font-semibold text-acode-primary tracking-[0.45em] focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech transition-colors">

            <p id="code-warning"
               class="text-acode-danger text-xs mt-2"
               hidden
               aria-live="polite"></p>

            <p id="code-help"
               class="mt-3 text-xs text-slate-500 flex items-center justify-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="15"
                     height="15"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>

                El código es válido durante 10 minutos.
            </p>
        </div>

        <button type="submit"
                class="w-full h-11 rounded-lg bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36] focus:outline-none focus:ring-2 focus:ring-acode-tech focus:ring-offset-2 transition-colors">
            Verificar y entrar
        </button>

    </form>

    <div class="mt-7 pt-6 border-t border-slate-200 text-center">

        <a href="{{ route('auth.login.show') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech hover:text-acode-primary transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="16"
                 height="16"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="m15 18-6-6 6-6"/>
            </svg>

            Volver al inicio de sesión
        </a>

    </div>
@endsection

@push('scripts')
<script>
    const codeField = document.getElementById('verification-code');
    const codeWarning = document.getElementById('code-warning');

    function updateCodeWarning(show) {
        codeWarning.hidden = !show;

        codeWarning.textContent = show
            ? (codeField.validity.valueMissing
                ? 'El campo código de verificación es obligatorio'
                : 'El código de verificación debe contener 6 dígitos')
            : '';

        codeField.classList.toggle('border-acode-danger', show);
        codeField.classList.toggle('border-slate-300', !show);
        codeField.setAttribute('aria-invalid', String(show));
    }

    codeField.addEventListener('invalid', function (event) {
        event.preventDefault();
        updateCodeWarning(true);
        codeField.focus();
    });

    codeField.addEventListener('input', function () {
        codeField.value = codeField.value.replace(/[^0-9]/g, '').slice(0, 6);

        if (!codeWarning.hidden) {
            updateCodeWarning(!codeField.validity.valid);
        }
    });
</script>
@endpush
