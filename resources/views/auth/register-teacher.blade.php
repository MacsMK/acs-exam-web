{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/register-teacher.blade.php (MPW-V2 §4.1.2, §7.3, §7.8.2, §8.5, §8.6.1, §8.10.6, Tabla 20, Tabla 28, ADR-057) --}}
@extends('layouts.guest')

@section('title', 'Registro de Docente')

@section('content')
    @php
        $resolvedCode = old('invitation_code', $prefilledInvitationCode ?? '');
        $hasStepOneErrors = $errors->hasAny([
            'first_name',
            'last_name',
            'email',
            'phone',
            'password',
            'password_confirmation',
            'accepts_privacy_notice',
        ]);
        $hasStepTwoErrors = $errors->hasAny([
            'institution_name',
            'professional_license',
            'verification_document',
            'invitation_code',
        ]);
        $initialStep = (! $hasStepOneErrors && $hasStepTwoErrors) ? 2 : 1;
        $initialMode = ($resolvedCode !== '' || $errors->has('invitation_code')) ? 'code' : 'document';
        $hasInitialLicense = old('professional_license') !== null && old('professional_license') !== '';
    @endphp

    <div class="mb-5">
        <h1 class="text-xl font-bold text-acode-primary tracking-tight">Registro de Docente</h1>
        <p class="text-xs text-slate-500 mt-1">Crea tu cuenta oficial para gestionar grupos, reactivos y exámenes.</p>
    </div>

    <div
        x-data="{
            step: {{ $initialStep }},
            password: '',
            passwordConfirmation: '',
            acceptedTerms: {{ old('accepts_privacy_notice') ? 'true' : 'false' }},
            termsModalOpen: false,
            hasScrolledToBottom: false,
            registrationMode: '{{ $initialMode }}',
            showOptionalLicense: {{ $hasInitialLicense ? 'true' : 'false' }},
            selectedFileName: '',
            selectedFileSize: '',
            fileSizeError: '',
            get passwordsMatch() {
                if (!this.password || !this.passwordConfirmation) return true;
                return this.password === this.passwordConfirmation;
            },
            setMode(mode) {
                this.registrationMode = mode;
                this.$dispatch('teacher-mode-changed', mode);
            },
            goToStepTwo() {
                const form = document.getElementById('register-form');
                const stepOneNames = ['first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation', 'accepts_privacy_notice'];

                for (const name of stepOneNames) {
                    const field = form.querySelector(`[name='${name}']`);
                    if (field && !field.reportValidity()) {
                        return;
                    }
                }

                if (!this.passwordsMatch) {
                    return;
                }

                this.step = 2;
                this.$dispatch('teacher-step-changed', 2);
            },
            goToStepOne() {
                this.step = 1;
                this.$dispatch('teacher-step-changed', 1);
            },
            handleFileChange(event) {
                const input = event.target;
                const file = input.files && input.files[0] ? input.files[0] : null;
                this.fileSizeError = '';

                if (!file) {
                    this.selectedFileName = '';
                    this.selectedFileSize = '';
                    input.setCustomValidity('');
                    return;
                }

                this.selectedFileName = file.name;
                const sizeMb = file.size / (1024 * 1024);
                this.selectedFileSize = sizeMb >= 1
                    ? `${sizeMb.toFixed(2)} MB`
                    : `${Math.max(1, Math.round(file.size / 1024))} KB`;

                if (file.size > 5 * 1024 * 1024) {
                    this.fileSizeError = 'El tamaño del comprobante de acreditación docente debe ser menor o igual que 5 MB';
                    input.setCustomValidity(this.fileSizeError);
                } else {
                    input.setCustomValidity('');
                }
            },
            checkScroll(e) {
                const el = e.target;
                if (el.scrollHeight - el.scrollTop <= el.clientHeight + 25) {
                    this.hasScrolledToBottom = true;
                }
            }
        }"
    >
        {{-- Indicador compacto de asistente en 2 pasos (Ley de Hick, MPW-V2 §7.8.2) --}}
        <div class="mb-5">
            <div class="flex items-center justify-between text-xs font-semibold mb-2">
                <span x-bind:class="step === 1 ? 'text-acode-primary' : 'text-acode-accent'">
                    1. Tus datos
                </span>
                <span class="text-slate-400" x-text="`Paso ${step} de 2`">Paso 1 de 2</span>
                <span x-bind:class="step === 2 ? 'text-acode-primary' : 'text-slate-400'">
                    2. Acreditación docente
                </span>
            </div>
            <div class="grid grid-cols-2 gap-1.5">
                <div class="h-1.5 rounded-full bg-acode-accent transition-colors duration-200"></div>
                <div
                    class="h-1.5 rounded-full transition-colors duration-200"
                    x-bind:class="step === 2 ? 'bg-acode-tech' : 'bg-slate-200'"
                ></div>
            </div>
        </div>

        {{-- Aviso contextual si ingresó mediante enlace directo de invitación (?codigo=...) --}}
        @if (! empty($prefilledInvitationCode))
            <div class="mb-4 rounded-xl border border-acode-accent/35 bg-acode-accent/10 px-3.5 py-2.5 text-xs text-acode-primary flex items-center gap-2">
                <x-icon name="key" class="w-4 h-4 text-acode-accent shrink-0" />
                <span>
                    Invitación institucional detectada (<strong class="font-mono">{{ $prefilledInvitationCode }}</strong>). Solo llena tus datos para activación inmediata.
                </span>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('auth.register.teacher.store') }}"
            id="register-form"
            enctype="multipart/form-data"
            class="space-y-4"
        >
            @csrf

            {{-- Versión del aviso de privacidad vigente --}}
            @if ($privacyNotice)
                <input type="hidden" name="privacy_notice_id" value="{{ $privacyNotice->id }}">
            @endif

            {{-- PASO 1: Datos básicos de acceso --}}
            <div x-show="step === 1" x-transition.opacity.duration.200ms class="space-y-4">
                {{-- Grid de 2 columnas para Nombres y Apellidos --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-form.group name="first_name" label="Nombre(s)" required>
                        <x-form.input
                            type="text"
                            name="first_name"
                            maxlength="100"
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
                            value="{{ old('last_name') }}"
                            required
                            placeholder="Tus apellidos"
                        />
                    </x-form.group>
                </div>

                {{-- Correo Electrónico --}}
                <x-form.group
                    name="email"
                    label="Correo electrónico (se recomienda institucional)"
                    helpText="Puedes usar correo institucional o personal."
                    required
                >
                    <x-form.input
                        type="email"
                        name="email"
                        maxlength="255"
                        value="{{ old('email') }}"
                        required
                        prefixIcon="userTie"
                        placeholder="docente@institucion.edu"
                    />
                </x-form.group>

                {{-- Teléfono de contacto (10 dígitos) --}}
                <x-form.group name="phone" label="Teléfono de contacto (10 dígitos)" required>
                    <x-form.input
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        maxlength="10"
                        pattern="[0-9]{10}"
                        inputmode="numeric"
                        required
                        prefixIcon="shield"
                        placeholder="5512345678"
                    />
                </x-form.group>

                {{-- Grid de 2 columnas para Contraseñas --}}
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

                {{-- Casilla vinculada con Modal de Términos de Servicio y Privacidad --}}
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

                {{-- Botón Normativo Oficial (Continuar al paso 2) --}}
                <div class="pt-2">
                    <x-button
                        type="button"
                        variant="primary"
                        size="md"
                        class="w-full"
                        @click="goToStepTwo()"
                        ::disabled="!passwordsMatch || !acceptedTerms"
                    >
                        Continuar
                    </x-button>
                </div>
            </div>

            {{-- PASO 2: Acreditación docente (máx. 2 campos visibles por defecto - Ley de Hick) --}}
            <div x-show="step === 2" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                {{-- Carril por comprobante docente (default) --}}
                <div
                    x-show="registrationMode === 'document'"
                    x-transition.opacity.duration.200ms
                    class="space-y-3.5"
                >
                    <x-form.group name="institution_name" label="Institución o plantel donde impartes clases" required>
                        <x-form.input
                            type="text"
                            name="institution_name"
                            maxlength="150"
                            value="{{ old('institution_name') }}"
                            placeholder="Ej. CBTis 122, UNAM o Docente independiente"
                            x-bind:required="step === 2 && registrationMode === 'document'"
                            x-bind:disabled="registrationMode === 'code'"
                        />
                    </x-form.group>

                    <x-form.group name="verification_document" label="Credencial docente, constancia u horario oficial" required>
                        <div class="relative">
                            <label
                                for="verification_document"
                                class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-5 text-center cursor-pointer transition-colors"
                                x-bind:class="fileSizeError
                                    ? 'border-acode-danger bg-acode-danger/5'
                                    : (selectedFileName ? 'border-acode-accent bg-acode-accent/5' : 'border-acode-tech/35 bg-slate-50/70 hover:border-acode-tech hover:bg-acode-surface/40')"
                            >
                                <input
                                    type="file"
                                    id="verification_document"
                                    name="verification_document"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="sr-only"
                                    x-bind:required="step === 2 && registrationMode === 'document'"
                                    x-bind:disabled="registrationMode === 'code'"
                                    @change="handleFileChange($event)"
                                >

                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full"
                                    x-bind:class="fileSizeError
                                        ? 'bg-acode-danger/15 text-acode-danger'
                                        : (selectedFileName ? 'bg-acode-accent/15 text-acode-accent' : 'bg-acode-tech/10 text-acode-tech')"
                                >
                                    <x-icon name="upload" class="w-5 h-5" />
                                </div>

                                <div class="space-y-0.5">
                                    <p class="text-xs font-semibold text-acode-primary" x-show="!selectedFileName">
                                        Haz clic para adjuntar foto o PDF de tu comprobante
                                    </p>
                                    <p class="text-xs font-semibold text-acode-primary break-all" x-show="selectedFileName" x-cloak>
                                        <span x-text="selectedFileName"></span>
                                        <span class="ml-1 font-normal text-slate-500" x-text="`(${selectedFileSize})`"></span>
                                    </p>
                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                        PDF, JPG o PNG (máx. 5 MB). Se elimina automáticamente al concluir la revisión (LFPDPPP).
                                    </p>
                                </div>
                            </label>
                        </div>

                        <p x-show="fileSizeError" x-cloak class="text-xs text-acode-danger mt-1 flex items-center gap-1 font-medium">
                            <x-icon name="warning" class="w-3.5 h-3.5 shrink-0" />
                            <span x-text="fileSizeError"></span>
                        </p>
                    </x-form.group>

                    {{-- Cédula SEP opcional bajo divulgación progresiva --}}
                    <div>
                        <button
                            type="button"
                            @click="showOptionalLicense = !showOptionalLicense"
                            class="text-xs font-medium text-slate-500 hover:text-acode-tech transition flex items-center gap-1"
                        >
                            <span x-text="showOptionalLicense ? '− Ocultar cédula profesional SEP' : '+ Agregar cédula profesional SEP (opcional)'"></span>
                        </button>

                        <div x-show="showOptionalLicense" x-cloak x-transition.opacity.duration.150ms class="mt-2">
                            <x-form.group name="professional_license" label="Cédula profesional SEP (opcional)">
                                <x-form.input
                                    type="text"
                                    name="professional_license"
                                    value="{{ old('professional_license') }}"
                                    maxlength="8"
                                    pattern="[0-9]{7,8}"
                                    inputmode="numeric"
                                    placeholder="7 u 8 dígitos"
                                    x-bind:disabled="registrationMode === 'code'"
                                />
                            </x-form.group>
                        </div>
                    </div>

                    <div class="pt-1 border-t border-slate-100 text-center">
                        <button
                            type="button"
                            @click="setMode('code')"
                            class="text-xs font-semibold text-acode-tech hover:underline"
                        >
                            ¿Tienes un código de invitación institucional?
                        </button>
                    </div>
                </div>

                {{-- Carril por código de invitación institucional --}}
                <div
                    x-show="registrationMode === 'code'"
                    x-cloak
                    x-transition.opacity.duration.200ms
                    class="space-y-3"
                >
                    <div class="rounded-xl border border-acode-accent/35 bg-acode-accent/5 p-4 space-y-3">
                        <x-form.group
                            name="invitation_code"
                            label="Código de invitación institucional"
                            helpText="Aprobación inmediata al verificar tu código 2FA por correo."
                            required
                        >
                            <x-form.input
                                type="text"
                                name="invitation_code"
                                maxlength="32"
                                value="{{ $resolvedCode }}"
                                prefixIcon="key"
                                placeholder="Ej. INST-XXXX-XXXX"
                                class="uppercase font-mono tracking-wider"
                                x-bind:required="step === 2 && registrationMode === 'code'"
                                x-bind:disabled="registrationMode === 'document'"
                            />
                        </x-form.group>
                    </div>

                    <div class="text-center">
                        <button
                            type="button"
                            @click="setMode('document')"
                            class="text-xs font-semibold text-slate-500 hover:text-acode-tech hover:underline"
                        >
                            No tengo código, prefiero subir mi comprobante docente
                        </button>
                    </div>
                </div>

                {{-- Botones de acción del Paso 2 (MPW-V2 §7.8.2: Volver / Enviar solicitud) --}}
                <div class="flex items-center gap-3 pt-2">
                    <x-button
                        type="button"
                        variant="neutral"
                        size="md"
                        class="w-1/3"
                        @click="goToStepOne()"
                    >
                        Volver
                    </x-button>

                    <x-button
                        type="submit"
                        variant="primary"
                        size="md"
                        class="w-2/3"
                        ::disabled="registrationMode === 'document' && fileSizeError !== ''"
                    >
                        Enviar solicitud
                    </x-button>
                </div>
            </div>
        </form>

        {{-- Modal de Términos de Servicio y Privacidad con scroll obligatorio (MPW-V2 §8.6.1) --}}
        <x-modal name="terms-modal" title="Aviso de Privacidad y Términos de Servicio" maxWidth="2xl">
            <div class="space-y-3">
                <p class="text-xs text-slate-500">
                    Para proteger tus datos personales conforme a la LFPDPPP, desplázate hasta el final del documento para habilitar la aceptación.
                </p>

                <div
                    @scroll="checkScroll($event)"
                    class="max-h-64 overflow-y-auto p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 space-y-3 leading-relaxed"
                >
                    {{-- Aviso de privacidad vigente versionado en BD --}}
                    @if ($privacyNotice)
                        <div class="p-3 bg-acode-surface border border-slate-200 rounded-lg">
                            <p class="font-bold text-acode-primary">Aviso de privacidad vigente · Versión {{ $privacyNotice->version }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $privacyNotice->content }}</p>
                        </div>
                    @endif

                    <h4 class="font-bold text-acode-primary">1. Identidad y Domicilio del Responsable</h4>
                    <p>A-Code Solutions (Equipo 4), con domicilio operativo institucional, es responsable del tratamiento y salvaguarda de sus datos personales conforme a los lineamientos normativos de la Plataforma Evaluadora.</p>

                    <h4 class="font-bold text-acode-primary">2. Finalidades del Tratamiento de Datos</h4>
                    <p>Los datos recabados (nombre, correo electrónico, teléfono, adscripción institucional, métricas de evaluación académica y registros de conexión) serán utilizados única y exclusivamente para la acreditación de identidad docente, gestión de exámenes, emisión de actas de calificaciones y auditoría anti-suplantación en tiempo real.</p>

                    <h4 class="font-bold text-acode-primary">3. Tratamiento Temporal y Borrado Automático del Comprobante Docente (LFPDPPP)</h4>
                    <p>El documento probatorio adjuntado para acreditar su rol docente (credencial institucional, constancia laboral u horario oficial) se resguarda exclusivamente en almacenamiento privado no público, es consultado únicamente por personal administrativo autorizado para dictaminar la solicitud y se elimina de manera automática e irreversible al concluir la revisión, conservando únicamente el registro de auditoría del dictamen.</p>

                    <h4 class="font-bold text-acode-primary">4. Medidas de Seguridad y Anti-Fraude</h4>
                    <p>La plataforma registra marcas de tiempo server-side, direcciones IP, incidencias por pérdida de foco y eventos de cámara web supervisada. Queda prohibida la reproducción, copia o extracción no autorizada de reactivos de examen.</p>

                    <h4 class="font-bold text-acode-primary">5. Aceptación y Consentimiento</h4>
                    <p>Al confirmar el presente documento, usted otorga su consentimiento expreso e informado para el tratamiento de su información académica, documental y operativa en el ecosistema SaaS A-Code Solutions.</p>

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
<script>
    {{-- Validación en cliente (MPW-V2 Tabla 17/18): mensajes en español bajo cada campo, sin punto final. --}}
    (function () {
        const form = document.getElementById('register-form');

        if (!form) {
            return;
        }

        function filterInput(field, allowedCharacters) {
            if (!field) {
                return;
            }

            function clean() {
                const original = field.value;
                const cursor = field.selectionStart;
                const beforeCursor = original.slice(0, cursor);
                const cleaned = original.replace(allowedCharacters, '');
                const cleanedBeforeCursor = beforeCursor.replace(allowedCharacters, '');

                if (cleaned !== original) {
                    field.value = cleaned;
                    field.setSelectionRange(
                        cleanedBeforeCursor.length,
                        cleanedBeforeCursor.length
                    );
                }
            }

            field.addEventListener('input', function (event) {
                if (!event.isComposing) {
                    clean();
                }
            });

            field.addEventListener('compositionend', clean);
        }

        filterInput(form.querySelector('[name="first_name"]'), /[^\p{L}\p{M} ]/gu);
        filterInput(form.querySelector('[name="last_name"]'), /[^\p{L}\p{M} ]/gu);
        filterInput(form.querySelector('[name="phone"]'), /[^0-9]/g);
        filterInput(form.querySelector('[name="professional_license"]'), /[^0-9]/g);

        const invitationField = form.querySelector('[name="invitation_code"]');
        if (invitationField) {
            invitationField.addEventListener('input', function () {
                const normalized = invitationField.value.replace(/\s+/g, '').toUpperCase();
                if (normalized !== invitationField.value) {
                    invitationField.value = normalized;
                }
            });
        }

        const fieldNames = {
            first_name: 'nombre',
            last_name: 'apellidos',
            email: 'correo electrónico',
            phone: 'teléfono',
            password: 'contraseña',
            password_confirmation: 'confirmar contraseña',
            institution_name: 'institución educativa',
            professional_license: 'cédula profesional',
            verification_document: 'comprobante de acreditación docente',
            invitation_code: 'código de invitación',
            accepts_privacy_notice: 'aceptación del aviso de privacidad'
        };

        function errorMessage(field) {
            const name = fieldNames[field.name] || field.name;

            if (field.validity.customError) {
                return field.validationMessage.replace(/\.$/, '');
            }

            if (field.validity.valueMissing) {
                return `El campo ${name} es obligatorio`;
            }

            if (field.validity.typeMismatch) {
                return `El ${name} no tiene un formato válido`;
            }

            if (field.validity.patternMismatch && field.name === 'phone') {
                return 'El teléfono debe contener 10 dígitos';
            }

            if (field.validity.patternMismatch && field.name === 'professional_license') {
                return 'La cédula profesional no tiene un formato válido';
            }

            if (field.validity.tooShort) {
                if (field.name === 'password' || field.name === 'password_confirmation') {
                    return `La ${name} debe tener al menos ${field.minLength} caracteres`;
                }

                return `El campo ${name} debe tener al menos ${field.minLength} caracteres`;
            }

            return field.validationMessage.replace(/\.$/, '');
        }

        function clearFieldError(field) {
            if (!field || !field.name) {
                return;
            }

            const message = form.querySelector(`[data-error-for="${field.name}"]`);

            if (message) {
                message.remove();
            }

            if (field.type !== 'checkbox' && field.type !== 'file') {
                field.classList.remove('border-acode-danger');
                field.classList.add('border-slate-300');
            }
        }

        form.addEventListener('invalid', function (event) {
            const field = event.target;

            event.preventDefault();

            let message = form.querySelector(`[data-error-for="${field.name}"]`);

            if (!message) {
                message = document.createElement('p');
                message.dataset.errorFor = field.name;
                message.className = 'text-xs text-acode-danger mt-1 font-medium';
                message.setAttribute('aria-live', 'polite');

                const anchor = field.type === 'checkbox'
                    ? field.closest('label')
                    : (field.closest('.relative') || field);

                anchor.insertAdjacentElement('afterend', message);
            }

            message.textContent = errorMessage(field);

            if (field.type !== 'checkbox' && field.type !== 'file') {
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

        window.addEventListener('teacher-mode-changed', function () {
            ['institution_name', 'professional_license', 'verification_document', 'invitation_code']
                .forEach(function (fieldName) {
                    clearFieldError(form.querySelector(`[name="${fieldName}"]`));
                });
        });
    })();
</script>
@endpush
