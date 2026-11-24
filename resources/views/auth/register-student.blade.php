{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/register-student.blade.php (MPW-V3 §7.3, §7.8.2, §8.5, §8.6.1, Tabla 10, Tabla 18) --}}
@extends('layouts.guest')

@section('title', 'Registro de Alumno')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-acode-primary tracking-tight">Registro de Alumno</h1>
        <p class="text-xs text-slate-500 mt-1">Crea tu cuenta oficial para unirte a grupos escolares y presentar evaluaciones.</p>
    </div>

    <div
        x-data="{
            password: '',
            passwordConfirmation: '',
            acceptedTerms: {{ old('accepts_privacy_notice') ? 'true' : 'false' }},
            termsModalOpen: false,
            hasScrolledToBottom: false,
            get passwordsMatch() {
                if (!this.password || !this.passwordConfirmation) return true;
                return this.password === this.passwordConfirmation;
            },
            checkScroll(e) {
                const el = e.target;
                if (el.scrollHeight - el.scrollTop <= el.clientHeight + 25) {
                    this.hasScrolledToBottom = true;
                }
            }
        }"
    >
        <form method="POST" action="{{ route('auth.register.student.store') }}" id="register-form" class="space-y-4" enctype="multipart/form-data">
            @csrf

            {{-- Versión del aviso de privacidad que se acepta (RF-AUT-011) --}}
            @if ($privacyNotice)
                <input type="hidden" name="privacy_notice_id" value="{{ $privacyNotice->id }}">
            @endif

            {{-- Grid de 2 columnas para Nombres y Apellidos --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-form.group name="first_name" label="Nombre(s)" required>
                    <x-form.input
                        type="text"
                        name="first_name"
                        maxlength="100"
                        pattern="(?=.*\p{L})[\p{L}\p{M} ]+"
                        data-character-filter="name"
                        value="{{ old('first_name') }}"
                        required
                        autofocus
                        placeholder="Tu nombre"
                    />
                </x-form.group>

                <x-form.group name="last_name" label="Apellido(s)" required>
                    <x-form.input
                        type="text"
                        name="last_name"
                        maxlength="100"
                        pattern="(?=.*\p{L})[\p{L}\p{M} ]+"
                        data-character-filter="name"
                        value="{{ old('last_name') }}"
                        required
                        placeholder="Tus apellidos"
                    />
                </x-form.group>
            </div>

            {{-- Correo Electrónico Institucional --}}
            <x-form.group name="email" label="Correo electrónico institucional o personal" required>
                <x-form.input
                    type="email"
                    name="email"
                    maxlength="255"
                    value="{{ old('email') }}"
                    required
                    prefixIcon="user"
                    placeholder="alumno@institucion.edu"
                />
            </x-form.group>

            {{-- Teléfono de contacto (opcional) --}}
            <x-form.group name="phone" label="Teléfono móvil (opcional, 10 dígitos)">
                <x-form.input
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    maxlength="10"
                    pattern="[0-9]{10}"
                    inputmode="numeric"
                    autocomplete="tel-national"
                    data-character-filter="digits"
                    data-mexican-phone
                    prefixIcon="shield"
                    placeholder="5512345678"
                />
            </x-form.group>

            {{-- Fotografía opcional del estudiante: JPG o PNG de hasta 5 MB (RF-AUT-008) --}}
            <x-form.group name="photo" label="Fotografía (opcional, JPG o PNG de hasta 5 MB)">
                <input
                    id="photo"
                    type="file"
                    name="photo"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    class="block w-full h-10 text-sm text-slate-700 file:mr-3 file:h-full file:px-3.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-acode-primary file:text-white hover:file:bg-acode-tech border border-slate-300 rounded-lg bg-white p-1"
                />
                <p class="text-xs text-slate-500 mt-1">Si no agregas una, se mostrará la imagen predeterminada con tu inicial.</p>
            </x-form.group>

            {{-- Grid de 2 columnas para Contraseñas con validación reactiva Alpine --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-form.group name="password" label="Contraseña" required>
                    <x-form.input
                        type="password"
                        name="password"
                        x-model="password"
                        required
                        minlength="8"
                        prefixIcon="lock"
                        placeholder="Mínimo 8 car."
                    />
                </x-form.group>

                <x-form.group name="password_confirmation" label="Confirmar contraseña" required>
                    <x-form.input
                        type="password"
                        name="password_confirmation"
                        x-model="passwordConfirmation"
                        required
                        minlength="8"
                        prefixIcon="lock"
                        placeholder="Repite la clave"
                        ::class="!passwordsMatch ? 'border-acode-danger focus:ring-acode-danger' : ''"
                    />
                </x-form.group>
            </div>

            {{-- Alerta reactiva de no coincidencia de contraseñas --}}
            <div x-show="!passwordsMatch" x-cloak class="text-xs text-acode-danger flex items-center gap-1 font-medium">
                <x-icon name="warning" class="w-4 h-4 shrink-0" />
                <span>Los campos contraseña y confirmar contraseña deben coincidir</span>
            </div>

            {{-- Casilla vinculada con Modal de Términos de Servicio y Privacidad (RF-AUT-011) --}}
            <div class="pt-1">
                <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="accepts_privacy_notice"
                        value="1"
                        x-model="acceptedTerms"
                        required
                        class="rounded border-slate-300 text-acode-tech focus:ring-acode-tech mt-0.5"
                    >
                    <span>
                        He leído y acepto los
                        <button
                            type="button"
                            @click="$dispatch('open-modal', 'terms-modal')"
                            class="text-acode-tech font-semibold hover:underline"
                        >
                            Términos del Servicio y Aviso de Privacidad
                        </button>
                    </span>
                </label>
            </div>

            {{-- Botón Normativo Oficial (Continuar) --}}
            <div class="pt-2">
                <x-button
                    type="submit"
                    variant="primary"
                    size="md"
                    class="w-full"
                    ::disabled="!passwordsMatch || !acceptedTerms"
                >
                    Continuar
                </x-button>
            </div>
        </form>

        {{-- Modal de Términos de Servicio y Privacidad con scroll obligatorio (RF-AUT-011, MPW-V3 §8.6.1) --}}
        <x-modal name="terms-modal" title="Aviso de Privacidad y Términos de Servicio" maxWidth="2xl">
            <div class="space-y-3">
                <p class="text-xs text-slate-500">
                    Conforme al requerimiento normativo <strong>RF-AUT-011</strong>, es obligatorio desplazarse hasta el final del documento para habilitar la casilla de aceptación.
                </p>

                <div
                    x-effect="if (show) $nextTick(() => { if ($el.clientHeight > 0) checkScroll({target: $el}) })"
                    @scroll="checkScroll($event)"
                    class="max-h-64 overflow-y-auto p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 space-y-3 leading-relaxed"
                >
                    {{-- Aviso de privacidad vigente, versionado en BD (RF-AUT-011): su versión queda registrada al aceptar --}}
                    @if ($privacyNotice)
                        <div class="p-3 bg-acode-surface border border-slate-200 rounded-lg">
                            <p class="font-bold text-acode-primary">Aviso de privacidad vigente · Versión {{ $privacyNotice->version }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $privacyNotice->content }}</p>
                        </div>
                    @endif

                    @if ($termsVersion ?? null)
                        <h4 class="font-bold text-acode-primary">Términos y condiciones · {{ $termsVersion->version }}</h4>
                        <p>Vigentes desde {{ $termsVersion->effective_at->format('d/m/Y H:i') }}</p>
                        <p class="whitespace-pre-line">{{ $termsVersion->content }}</p>
                    @else
                        @include('legal.terms-student-original')
                    @endif
                    <div class="p-3 bg-acode-surface border border-slate-200 rounded-lg text-center text-[11px] font-semibold text-acode-primary">
                        --- Fin del Aviso de Privacidad y Términos del Servicio ---
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-between w-full">
                    <span class="text-xs text-slate-500" x-show="!hasScrolledToBottom">
                        Desplázate hacia abajo para habilitar el botón
                    </span>
                    <span class="text-xs font-semibold text-acode-accent" x-show="hasScrolledToBottom" x-cloak>
                        ✓ Lectura completada
                    </span>

                    <div class="flex items-center gap-2">
                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            @click="$dispatch('close-modal', 'terms-modal')"
                        >
                            Cerrar
                        </x-button>
                        <x-button
                            type="button"
                            variant="accent"
                            size="sm"
                            ::disabled="!hasScrolledToBottom"
                            @click="acceptedTerms = true; $dispatch('close-modal', 'terms-modal')"
                        >
                            Aceptar términos
                        </x-button>
                    </div>
                </div>
            </x-slot:footer>
        </x-modal>
    </div>

    <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
        ¿Ya cuentas con usuario registrado?
        <a href="{{ route('auth.login.show') }}" class="text-acode-tech font-semibold hover:underline ml-1">
            Inicia sesión
        </a>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/character-filter.js') }}"></script>
<script>
    {{-- Validación en cliente (RF-AUT-002, RF-AUT-011): mensajes bajo cada campo, mismas frases que el servidor. --}}
    (function () {
        const form = document.getElementById('register-form');

        const fieldNames = {
            first_name: 'nombre',
            last_name: 'apellidos',
            email: 'correo electrónico',
            phone: 'teléfono',
            password: 'contraseña',
            password_confirmation: 'confirmar contraseña',
            accepts_privacy_notice: 'aceptación del aviso de privacidad'
        };

        function errorMessage(field) {
            const name = fieldNames[field.name] || field.name;

            if (field.validity.valueMissing) {
                return `El campo ${name} es obligatorio`;
            }

            if (field.validity.typeMismatch) {
                return `El ${name} no tiene un formato válido`;
            }

            if (field.validity.patternMismatch && field.name === 'phone') {
                return 'El teléfono debe contener 10 dígitos';
            }

            if (field.validity.patternMismatch && field.name === 'first_name') {
                return 'El nombre no tiene un formato válido';
            }

            if (field.validity.patternMismatch && field.name === 'last_name') {
                return 'El campo apellidos no tiene un formato válido';
            }

            if (field.validity.tooShort) {
                return `La ${name} debe tener al menos ${field.minLength} caracteres`;
            }

            return field.validationMessage;
        }

        function clearFieldError(field) {
            const message = form.querySelector(`[data-error-for="${field.name}"]`);

            if (message) {
                message.remove();
            }

            if (field.type !== 'checkbox') {
                field.classList.remove('border-acode-danger');
                field.classList.add('border-slate-300');
            }
        }

        // Cada campo inválido genera este evento al intentar enviar.
        form.addEventListener('invalid', function (event) {
            const field = event.target;

            // Se sustituye la burbuja nativa por mensajes bajo los campos.
            event.preventDefault();

            let message = form.querySelector(`[data-error-for="${field.name}"]`);

            if (!message) {
                message = document.createElement('p');
                message.dataset.errorFor = field.name;
                message.className = 'text-xs text-acode-danger mt-1 font-medium';
                message.setAttribute('aria-live', 'polite');

                // El input vive dentro de un contenedor relativo (x-form.input): el mensaje va debajo de éste.
                const anchor = field.type === 'checkbox'
                    ? field.closest('label')
                    : (field.closest('.relative') || field);

                anchor.insertAdjacentElement('afterend', message);
            }

            message.textContent = errorMessage(field);

            if (field.type !== 'checkbox') {
                field.classList.remove('border-slate-300');
                field.classList.add('border-acode-danger');
            }
        }, true);

        function updateFieldError(event) {
            const field = event.target;

            if (field.name && field.validity.valid) {
                clearFieldError(field);
            }
        }

        form.addEventListener('input', updateFieldError);
        form.addEventListener('change', updateFieldError);
    })();
</script>
@endpush
