@extends('layouts.guest')

@section('title', 'Verificación en dos pasos')

@section('content')
    <h1 class="text-lg font-semibold text-acode-primary mb-1">
        Verificación en dos pasos
    </h1>

    <p class="text-sm text-slate-500 mb-5">
        Enviamos un código de 6 dígitos a
        <strong>{{ $user->email }}</strong>, válido por 10 minutos.
    </p>

    <form method="POST"
          action="{{ route('auth.verify-2fa.store') }}"
          class="space-y-4">
        @csrf

        <input type="hidden" name="user_id" value="{{ $user->id }}">

        <div>
            <label for="verification-code"
                   class="block text-[0.8125rem] font-medium text-slate-800 mb-1">
                Código de 6 dígitos
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
                   aria-describedby="code-warning"
                   class="w-full h-10 px-3 rounded-md border border-slate-300 text-center text-lg tracking-[0.5em] font-mono focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech">

            <p id="code-warning"
               class="text-acode-danger text-xs mt-1"
               hidden
               aria-live="polite"></p>
        </div>

        <button type="submit"
                class="w-full h-10 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            Verificar y entrar
        </button>
    </form>
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