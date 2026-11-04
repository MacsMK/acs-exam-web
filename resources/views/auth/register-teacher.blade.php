@extends('layouts.guest')

@section('title', 'Registro de docente')

@section('content')
    <h1 class="text-lg font-semibold text-acode-primary mb-1">Registro de docente</h1>
    <p class="text-sm text-slate-500 mb-5">Crea tu cuenta para administrar grupos, reactivos y exámenes.</p>

    <form method="POST" action="{{ route('auth.register.teacher.store') }}" class="space-y-4">
        @csrf

        @if ($privacyNotice)
            <input type="hidden" name="privacy_notice_id" value="{{ $privacyNotice->id }}">
        @endif

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Nombre(s)</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Apellido(s)</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
        </div>

        <div>
            <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                   class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <div>
            <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Teléfono (10 dígitos)</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   maxlength="10" pattern="[0-9]{10}" inputmode="numeric" required
                   class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Contraseña</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>
            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" required minlength="8"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                <p class="text-acode-danger text-xs mt-1" data-password-warning hidden aria-live="polite">
                    Los campos contraseña y confirmar contraseña deben coincidir
                </p>
            </div>
        </div>

        <div class="text-sm text-slate-600">
            <label class="inline-flex items-start gap-2">
                <input type="checkbox"
                    name="accepts_privacy_notice"
                    value="1"
                    class="mt-0.5"
                    required>
                <span>Acepto el </span>
            </label>

            <button type="button"
                    class="text-acode-tech underline cursor-pointer"
                    onclick="document.getElementById('privacy-dialog').showModal()">
                aviso de privacidad
            </button>

            <span>y los</span>

            <button type="button"
                    class="text-acode-tech underline cursor-pointer"
                    onclick="document.getElementById('terms-dialog').showModal()">
                términos y condiciones
            </button>.
        </div>

        <button type="submit" class="w-full h-10 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            Crear cuenta de docente
        </button>
    </form>

    <dialog id="privacy-dialog"
            aria-labelledby="privacy-title"
            class="w-11/12 max-w-2xl rounded-lg p-6 shadow-lg backdrop:bg-black/50">
        <h2 id="privacy-title" class="text-lg font-semibold text-acode-primary">
            Aviso de privacidad
        </h2>

        <div class="mt-4 max-h-80 overflow-y-auto text-sm text-slate-700">
            @if ($privacyNotice)
                <p class="font-medium">
                    Versión {{ $privacyNotice->version }}
                </p>

                <p class="mt-2">
                    {{ $privacyNotice->content }}
                </p>
            @else
                <p>Por el momento no hay un aviso de privacidad disponible.</p>
            @endif
        </div>

        <button type="button"
                class="mt-5 rounded-md bg-acode-primary px-4 py-2 text-sm text-white cursor-pointer"
                onclick="document.getElementById('privacy-dialog').close()">
            Cerrar
        </button>
    </dialog>

    <dialog id="terms-dialog"
            aria-labelledby="terms-title"
            class="w-11/12 max-w-2xl rounded-lg p-6 shadow-lg backdrop:bg-black/50">
        <h2 id="terms-title" class="text-lg font-semibold text-acode-primary">
            Términos y condiciones
        </h2>

        <div class="mt-4 max-h-80 overflow-y-auto text-sm text-slate-700">
            <p>
                Texto provisional para demostración. Los términos y condiciones
                definitivos están pendientes de elaboración y aprobación.
            </p>
        </div>

        <button type="button"
                class="mt-5 rounded-md bg-acode-primary px-4 py-2 text-sm text-white cursor-pointer"
                onclick="document.getElementById('terms-dialog').close()">
            Cerrar
        </button>
    </dialog>

    <p class="text-sm text-slate-500 mt-4 text-center">
        ¿Ya tienes cuenta? <a href="{{ route('auth.login.show') }}" class="text-acode-tech font-medium">Inicia sesión</a>
    </p>
    <p class="text-sm text-slate-500 mt-1 text-center">
        ¿Eres alumno? <a href="{{ route('auth.register.student.show') }}" class="text-acode-tech font-medium">Regístrate aquí</a>
    </p>
@endsection

@push('scripts')
<script>
    const form = document.querySelector('form');
    function filterInput(field, allowedCharacters) {
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

    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="password_confirmation"]');
    const warning = form.querySelector('[data-password-warning]');

    const fieldNames = {
        first_name: 'nombre',
        last_name: 'apellidos',
        email: 'correo electrónico',
        phone: 'teléfono',
        password: 'contraseña',
        password_confirmation: 'confirmar contraseña',
        accepts_privacy_notice: 'aceptación del aviso de privacidad'
    };

    function checkPasswords() {
        const mismatch = confirmation.value !== '' &&
                         password.value !== confirmation.value;

        warning.hidden = !mismatch;
        confirmation.classList.toggle('border-acode-danger', mismatch);
        confirmation.classList.toggle('border-slate-300', !mismatch);
    }

    password.addEventListener('input', checkPasswords);
    confirmation.addEventListener('input', checkPasswords);

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

        if (field.validity.tooShort) {
            return `La ${name} debe tener al menos ${field.minLength} caracteres`;
        }

        return field.validationMessage;
    }

    function clearFieldError(field) {
        const message = form.querySelector(
            `[data-error-for="${field.name}"]`
        );

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

        let message = form.querySelector(
            `[data-error-for="${field.name}"]`
        );

        if (!message) {
            message = document.createElement('p');
            message.dataset.errorFor = field.name;
            message.className = 'text-acode-danger text-xs mt-1';
            message.setAttribute('aria-live', 'polite');

            const anchor = field.type === 'checkbox'
                ? field.closest('label')
                : field;

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

            // Conserva el borde rojo si las contraseñas no coinciden.
            if (field === confirmation) {
                checkPasswords();
            }
        }
    }

    form.addEventListener('input', updateFieldError);
    form.addEventListener('change', updateFieldError);
</script>
@endpush