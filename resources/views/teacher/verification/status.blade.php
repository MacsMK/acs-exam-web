{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: teacher/verification/status.blade.php (MPW-V3 §4.1.2, §7.3, §7.8.2, §8.5, §8.6, Tabla 10, Tabla 18) --}}
@extends('layouts.app')

@section('title', 'Verificación de cuenta docente')

@section('content')
<div class="space-y-6">
    {{-- Encabezado principal --}}
    <x-page-header
        title="Verificación de cuenta docente"
        subtitle="Consulta el estado de validación de tu perfil académico o canjea un código institucional para activar tu acceso."
    >
        <x-slot:badge>
            @if ($user->isPendingTeacherVerification())
                <x-badge variant="warning">En revisión</x-badge>
            @elseif ($user->isRejectedTeacherVerification())
                <x-badge variant="danger">Acción requerida</x-badge>
            @else
                <x-badge variant="success">Cuenta verificada</x-badge>
            @endif
        </x-slot:badge>
    </x-page-header>

    {{-- Indicador de progreso de 3 pasos --}}
    <x-card padding="p-5">
        <ol class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Progreso de verificación docente">
            {{-- Paso 1: Registro y 2FA completados --}}
            <li class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-acode-accent text-white shadow-sm">
                    <x-icon name="check" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-acode-accent">Paso 1 · Completado</p>
                    <p class="text-sm font-semibold text-acode-primary">Registro y 2FA completados</p>
                </div>
            </li>

            {{-- Paso 2: Validación docente --}}
            <li class="flex items-center gap-3">
                @if ($user->isVerifiedTeacher())
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-acode-accent text-white shadow-sm">
                        <x-icon name="check" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-acode-accent">Paso 2 · Completado</p>
                        <p class="text-sm font-semibold text-acode-primary">Validación docente</p>
                    </div>
                @elseif ($user->isRejectedTeacherVerification())
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-acode-danger text-white shadow-sm">
                        <x-icon name="warning" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-acode-danger">Paso 2 · Requiere corrección</p>
                        <p class="text-sm font-semibold text-acode-primary">Validación docente</p>
                    </div>
                @else
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-acode-tech text-white font-bold text-sm shadow-sm">
                        2
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-acode-tech">Paso 2 · En curso</p>
                        <p class="text-sm font-semibold text-acode-primary">Validación docente</p>
                    </div>
                @endif
            </li>

            {{-- Paso 3: Acceso al panel --}}
            <li class="flex items-center gap-3">
                @if ($user->isVerifiedTeacher())
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-acode-accent text-white shadow-sm">
                        <x-icon name="check" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-acode-accent">Paso 3 · Habilitado</p>
                        <p class="text-sm font-semibold text-acode-primary">Acceso al panel</p>
                    </div>
                @else
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 font-bold text-sm">
                        3
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Paso 3 · Pendiente</p>
                        <p class="text-sm font-medium text-slate-500">Acceso al panel</p>
                    </div>
                @endif
            </li>
        </ol>
    </x-card>

    {{-- Estado 1: Solicitud pendiente de revisión --}}
    @if ($user->isPendingTeacherVerification())
        <x-card>
            <x-slot:title>Solicitud recibida y en revisión</x-slot:title>
            <x-slot:subtitle>Nuestro equipo administrativo revisará tu comprobante docente en breve</x-slot:subtitle>

            <div class="space-y-5">
                <div class="rounded-xl border border-acode-warning/40 bg-acode-warning/15 p-4 flex items-start gap-3">
                    <x-icon name="clock" class="h-5 w-5 text-acode-warning-dark shrink-0 mt-0.5" />
                    <div class="text-sm text-acode-primary space-y-1">
                        <p class="font-semibold">Tu cuenta está casi lista para operar.</p>
                        <p class="text-xs text-slate-700 leading-relaxed">
                            Estamos validando la información y el comprobante que enviaste. En cuanto concluya la revisión recibirás una notificación en
                            <span class="font-semibold text-acode-primary">{{ $user->email }}</span>
                            y se habilitará tu acceso completo al panel docente.
                        </p>
                    </div>
                </div>

                {{-- Resumen de datos declarados --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Datos declarados en tu solicitud</h4>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                            <dt class="text-xs text-slate-500">Institución educativa</dt>
                            <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                {{ $verification?->institution_name ?: 'No especificada' }}
                            </dd>
                        </div>

                        @if ($verification?->educational_level)
                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                                <dt class="text-xs text-slate-500">Nivel educativo</dt>
                                <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                    {{ $verification->educationalLevelLabel() }}
                                </dd>
                            </div>
                        @endif

                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                            <dt class="text-xs text-slate-500">Acreditación adjunta</dt>
                            <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                {{ $verification?->credentialTypeLabel() ?? 'Comprobante de adscripción docente' }}
                            </dd>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                            <dt class="text-xs text-slate-500">Cédula profesional SEP</dt>
                            <dd class="mt-1 text-sm font-semibold font-mono text-acode-primary">
                                {{ $verification?->professional_license ?: 'No proporcionada' }}
                            </dd>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                            <dt class="text-xs text-slate-500">Fecha y hora de envío</dt>
                            <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                {{ $verification?->submitted_at?->format('d/m/Y H:i') ?? 'Reciente' }}
                            </dd>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                            <dt class="text-xs text-slate-500">Resguardo documental</dt>
                            <dd class="mt-1 text-sm font-semibold text-acode-primary">
                                @if ($verification?->hasDocument())
                                    Comprobante en revisión privada
                                @elseif ($verification?->document_purged_at)
                                    Comprobante eliminado tras revisión
                                @else
                                    En proceso de validación
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Aviso LFPDPPP sobre purga automática --}}
                <div class="rounded-xl border border-acode-tech/25 bg-acode-surface/60 p-4 flex items-start gap-3">
                    <x-icon name="shield" class="h-5 w-5 text-acode-tech shrink-0 mt-0.5" />
                    <p class="text-xs text-slate-700 leading-relaxed">
                        <span class="font-semibold text-acode-primary">Protección de datos personales (LFPDPPP):</span>
                        Tu comprobante permanece en almacenamiento privado de acceso restringido únicamente durante la dictaminación y se elimina de manera automática e irreversible al finalizar la revisión.
                    </p>
                </div>
            </div>
        </x-card>

    {{-- Estado 2: Solicitud rechazada — corrección y reenvío --}}
    @elseif ($user->isRejectedTeacherVerification())
        {{-- Alerta con motivo de rechazo --}}
        <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-5 text-acode-danger shadow-sm" role="alert">
            <div class="flex items-start gap-3">
                <x-icon name="warning" class="h-5 w-5 shrink-0 mt-0.5" />
                <div class="space-y-1.5">
                    <h2 class="text-sm font-bold">Tu solicitud de verificación fue rechazada</h2>
                    <p class="text-xs text-slate-700">Motivo registrado por el equipo administrativo:</p>
                    <p class="rounded-lg bg-white/80 border border-acode-danger/20 px-3.5 py-2.5 text-sm font-medium text-acode-ink">
                        {{ $verification?->rejection_reason ?: 'El comprobante proporcionado no pudo ser validado. Por favor corrige tus datos y adjunta un documento legible.' }}
                    </p>
                    <p class="text-xs text-slate-600 pt-1">
                        Tu archivo anterior fue eliminado automáticamente por seguridad (LFPDPPP). Puedes corregir la información y enviar un nuevo comprobante a continuación.
                    </p>
                </div>
            </div>
        </div>

        {{-- Formulario de reenvío documental --}}
        <x-card>
            <x-slot:title>Corregir datos y reenviar comprobante</x-slot:title>
            <x-slot:subtitle>Asegúrate de adjuntar un documento claro, vigente y legible en formato PDF, JPG o PNG (máx. 5 MB)</x-slot:subtitle>

            <form
                method="POST"
                action="{{ route('teacher.verification.resubmit') }}"
                enctype="multipart/form-data"
                id="resubmit-verification-form"
                class="space-y-4"
                x-data="{
                    selectedFileName: '',
                    selectedFileSize: '',
                    fileSizeError: '',
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
                    }
                }"
            >
                @csrf

                <x-form.group name="institution_name" label="Institución o plantel donde impartes clases" required>
                    <x-form.input
                        type="text"
                        name="institution_name"
                        maxlength="150"
                        value="{{ old('institution_name', $verification?->institution_name) }}"
                        placeholder="Ej. CBTis 122, UNAM o Docente independiente"
                        required
                    />
                </x-form.group>

                <x-form.group name="verification_document" label="Nuevo comprobante docente (credencial, constancia u horario)" required>
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
                                required
                                class="sr-only"
                                @change="handleFileChange($event)"
                            >

                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full"
                                x-bind:class="fileSizeError
                                    ? 'bg-acode-danger/15 text-acode-danger'
                                    : (selectedFileName ? 'bg-acode-accent/15 text-acode-accent' : 'bg-acode-tech/10 text-acode-tech')"
                            >
                                <x-icon name="upload" class="h-5 w-5" />
                            </div>

                            <div class="space-y-0.5">
                                <p class="text-xs font-semibold text-acode-primary" x-show="!selectedFileName">
                                    Haz clic para seleccionar el nuevo comprobante
                                </p>
                                <p class="text-xs font-semibold text-acode-primary break-all" x-show="selectedFileName" x-cloak>
                                    <span x-text="selectedFileName"></span>
                                    <span class="ml-1 font-normal text-slate-500" x-text="`(${selectedFileSize})`"></span>
                                </p>
                                <p class="text-xs text-slate-500">
                                    Formatos PDF, JPG o PNG (máx. 5 MB). Se resguarda en almacenamiento privado y se elimina automáticamente al concluir la revisión.
                                </p>
                            </div>
                        </label>
                    </div>

                    <p x-show="fileSizeError" x-cloak class="mt-1 flex items-center gap-1 text-xs font-medium text-acode-danger">
                        <x-icon name="warning" class="h-3.5 w-3.5 shrink-0" />
                        <span x-text="fileSizeError"></span>
                    </p>
                </x-form.group>

                <div x-data="{ showLicense: {{ old('professional_license', $verification?->professional_license) ? 'true' : 'false' }} }">
                    <button
                        type="button"
                        @click="showLicense = !showLicense"
                        class="text-xs font-medium text-slate-500 hover:text-acode-tech transition"
                        x-text="showLicense ? '− Ocultar cédula profesional SEP' : '+ Agregar o corregir cédula profesional SEP (opcional)'"
                    ></button>

                    <div x-show="showLicense" x-cloak x-transition.opacity.duration.150ms class="mt-2">
                        <x-form.group name="professional_license" label="Cédula profesional SEP (opcional)">
                            <x-form.input
                                type="text"
                                name="professional_license"
                                value="{{ old('professional_license', $verification?->professional_license) }}"
                                maxlength="8"
                                pattern="[0-9]{7,8}"
                                inputmode="numeric"
                                placeholder="7 u 8 dígitos (opcional)"
                            />
                        </x-form.group>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-button
                        type="submit"
                        variant="primary"
                        size="md"
                        x-bind:disabled="fileSizeError !== ''"
                    >
                        Enviar solicitud
                    </x-button>
                </div>
            </form>
        </x-card>

    {{-- Estado 3: Cuenta ya verificada --}}
    @else
        <x-card>
            <x-slot:title>Identidad docente verificada</x-slot:title>
            <x-slot:subtitle>Tu cuenta se encuentra habilitada para operar en la plataforma</x-slot:subtitle>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <p class="text-sm text-slate-600">
                    Ya puedes administrar tus grupos, banco de preguntas, exámenes y calificaciones desde tu panel docente.
                </p>
                <x-button :href="route('dashboard')" variant="primary" size="md" class="shrink-0">
                    Abrir panel docente
                </x-button>
            </div>
        </x-card>
    @endif

    {{-- Tarjeta secundaria de canje inmediato de código institucional (para pending o rejected) --}}
    @if ($user->isPendingTeacherVerification() || $user->isRejectedTeacherVerification())
        <x-card class="border-acode-accent/35">
            <x-slot:title>¿Ya cuentas con un código de invitación institucional?</x-slot:title>
            <x-slot:subtitle>Si tu institución o coordinación te entregó un código vigente, ingrésalo para activar tu cuenta de inmediato</x-slot:subtitle>

            <form
                method="POST"
                action="{{ route('teacher.verification.redeem-code') }}"
                id="redeem-code-form"
                class="flex flex-col gap-3 sm:flex-row sm:items-end"
            >
                @csrf

                <x-form.group name="invitation_code" label="Código de invitación institucional" required class="flex-1">
                    <x-form.input
                        type="text"
                        name="invitation_code"
                        maxlength="32"
                        value="{{ old('invitation_code') }}"
                        prefixIcon="key"
                        placeholder="Ej. INST-XXXX-XXXX"
                        class="uppercase font-mono tracking-wider"
                        required
                    />
                </x-form.group>

                <x-button type="submit" variant="accent" size="md" class="w-full sm:w-auto shrink-0">
                    Continuar
                </x-button>
            </form>
        </x-card>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const licenseField = document.querySelector('#resubmit-verification-form [name="professional_license"]');
        if (licenseField) {
            licenseField.addEventListener('input', function () {
                const cleaned = licenseField.value.replace(/[^0-9]/g, '');
                if (cleaned !== licenseField.value) {
                    licenseField.value = cleaned;
                }
            });
        }

        const codeField = document.querySelector('#redeem-code-form [name="invitation_code"]');
        if (codeField) {
            codeField.addEventListener('input', function () {
                const normalized = codeField.value.replace(/\s+/g, '').toUpperCase();
                if (normalized !== codeField.value) {
                    codeField.value = normalized;
                }
            });
        }
    })();
</script>
@endpush
