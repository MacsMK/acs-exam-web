{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/register-student.blade.php (MPW-V2 §7.3, §7.8.2, §8.5, §8.6.1, Tabla 20, Tabla 28) --}}
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
        <form method="POST" action="{{ route('auth.register.student.store') }}" class="space-y-4">
            @csrf

            {{-- Grid de 2 columnas para Nombres y Apellidos --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-form.group name="first_name" label="Nombre(s)" required>
                    <x-form.input
                        type="text"
                        name="first_name"
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
                    prefixIcon="shield"
                    placeholder="5512345678"
                />
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
                <span>Las contraseñas no coinciden. Por favor verifícalas.</span>
            </div>

            {{-- Casilla vinculada con Modal de Términos de Servicio y Privacidad (RF-AUT-003) --}}
            <div class="pt-1">
                <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="accepts_privacy_notice"
                        value="1"
                        x-model="acceptedTerms"
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

        {{-- Modal de Términos de Servicio y Privacidad con scroll obligatorio (RF-AUT-003, MPW-V2 §8.6.1) --}}
        <x-modal name="terms-modal" title="Aviso de Privacidad y Términos de Servicio" maxWidth="2xl">
            <div class="space-y-3">
                <p class="text-xs text-slate-500">
                    Conforme al requerimiento normativo <strong>RF-AUT-003</strong>, es obligatorio desplazarse hasta el final del documento para habilitar la casilla de aceptación.
                </p>

                <div
                    @scroll="checkScroll($event)"
                    class="max-h-64 overflow-y-auto p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 space-y-3 leading-relaxed"
                >
                    <h4 class="font-bold text-acode-primary">1. Identidad y Domicilio del Responsable</h4>
                    <p>A-Code Solutions (Equipo 4) es responsable del tratamiento y resguardo confidencial de los datos personales y académicos de los alumnos matriculados en la Plataforma Evaluadora.</p>

                    <h4 class="font-bold text-acode-primary">2. Tratamiento de Evaluaciones y Actas</h4>
                    <p>Las respuestas provistas en exámenes contrarreloj, códigos de programación e intentos de evaluación serán procesadas mediante motores de calificación deterministas e inteligentes, garantizando imparcialidad y trazabilidad histórica.</p>

                    <h4 class="font-bold text-acode-primary">3. Normativa de Integridad Académica</h4>
                    <p>El estudiante se compromete a no realizar trampas, plagios de código o suplantación de identidad. Todo evento anómalo de navegación durante las pruebas será notificado de forma automática al docente titular.</p>

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
