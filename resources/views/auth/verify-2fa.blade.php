{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/verify-2fa.blade.php (MPW-V2 §7.3, §7.8.2, §8.5, Tabla 20, Tabla 28) --}}
@extends('layouts.guest')

@section('title', 'Verificación en Dos Pasos (2FA)')

@section('content')
    <div class="mb-5 text-center">
        <div class="w-12 h-12 rounded-full bg-acode-surface text-acode-tech mx-auto flex items-center justify-center mb-3">
            <x-icon name="key" class="w-6 h-6" />
        </div>
        <h1 class="text-xl font-bold text-acode-primary tracking-tight">Verificación en Dos Pasos</h1>
        <p class="text-xs text-slate-500 mt-1">
            Hemos generado un código de 6 dígitos para tu cuenta <strong class="text-slate-700">{{ $user->email }}</strong>.
        </p>
    </div>

    {{-- Banner de código en modo demo: solo si el correo no sale de verdad (MAIL_MAILER=log|array), igual que `demo_code` en la API --}}
    @if ($user->two_factor_code && in_array(config('mail.default'), ['log', 'array'], true))
        <div class="mb-5 rounded-xl border border-acode-warning/40 bg-acode-warning/10 p-3.5 text-xs text-slate-800 flex items-start gap-2.5">
            <x-icon name="warning" class="w-4 h-4 text-acode-warning shrink-0 mt-0.5" />
            <div>
                <span class="font-bold">Modo demo / Entorno de pruebas:</span> tu código es
                <span class="font-mono font-bold text-sm text-acode-primary bg-white px-2 py-0.5 rounded border border-slate-200 tracking-widest ml-1 select-all">
                    {{ $user->two_factor_code }}
                </span>
            </div>
        </div>
    @endif

    <div
        x-data="{
            remaining: 600, // 10 minutos normativos
            timerId: null,
            init() {
                this.timerId = setInterval(() => {
                    if (this.remaining > 0) {
                        this.remaining--;
                    } else {
                        clearInterval(this.timerId);
                    }
                }, 1000);
            },
            formattedTime() {
                const mins = Math.floor(this.remaining / 60).toString().padStart(2, '0');
                const secs = (this.remaining % 60).toString().padStart(2, '0');
                return `${mins}:${secs}`;
            }
        }"
        class="space-y-4"
    >
        <form method="POST" action="{{ route('auth.verify-2fa.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="user_id" value="{{ $user->id }}">

            <div>
                <label for="verification-code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 text-center mb-2">
                    Código de 6 dígitos
                </label>
                <input
                    id="verification-code"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    autofocus
                    placeholder="000000"
                    autocomplete="one-time-code"
                    aria-describedby="code-warning"
                    class="w-full h-14 rounded-lg border border-slate-300 text-center text-2xl font-mono tracking-[0.5em] text-acode-primary bg-white focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech font-bold"
                />
                {{-- Mensaje de validación en cliente (RF-AUT-005) --}}
                <p id="code-warning" class="text-acode-danger text-xs mt-1 text-center" hidden aria-live="polite"></p>
            </div>

            {{-- Contador regresivo de validez de 10 minutos --}}
            <div class="flex items-center justify-center gap-1.5 text-xs text-slate-500 font-mono py-1">
                <x-icon name="clock" class="w-4 h-4 text-slate-400" />
                <span>Tiempo de validez restante:</span>
                <span class="font-bold text-acode-primary" x-text="formattedTime()"></span>
            </div>

            {{-- Botón Normativo Oficial (Continuar) --}}
            <div class="pt-2">
                <x-button type="submit" variant="primary" size="md" class="w-full">
                    Continuar
                </x-button>
            </div>
        </form>

        <div class="text-center pt-2">
            <a href="{{ route('auth.login.show') }}" class="text-xs text-acode-tech hover:underline font-semibold flex items-center justify-center gap-1">
                <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90" />
                <span>Volver al inicio de sesión</span>
            </a>
        </div>
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
        codeField.value = codeField.value.replace(/[^0-9]/g, '');

        if (!codeWarning.hidden) {
            updateCodeWarning(!codeField.validity.valid);
        }
    });
</script>
@endpush