@extends('layouts.app')

@section('title', 'Soporte Técnico')

@section('content')
<div @class(['mx-auto max-w-4xl space-y-6', 'px-4 py-8' => auth()->guest()])>
    <x-page-header title="Soporte Técnico" :institutional="true" />

    <x-card>
        <x-section-header title="Problemas de acceso" :institutional="true" />
        <dl class="mt-3 space-y-4">
            <div>
                <dt class="font-semibold text-acode-primary">No recibí el código de verificación</dt>
                <dd>Revisa el correo asociado a tu cuenta y la carpeta de correo no deseado. En la pantalla de verificación puedes solicitar otro código.</dd>
            </div>
            <div>
                <dt class="font-semibold text-acode-primary">Acceso restringido (403)</dt>
                <dd>Confirma que entraste con la cuenta a la que corresponde ese grupo, examen o módulo. Si el error continúa, conserva el folio de incidente.</dd>
            </div>
            <div>
                <dt class="font-semibold text-acode-primary">Página no encontrada (404)</dt>
                <dd>Vuelve al inicio del panel y abre la página desde el menú. Si el enlace te lo compartió tu docente, pídele que confirme que sigue vigente.</dd>
            </div>
            <div>
                <dt class="font-semibold text-acode-primary">Error interno del servidor (500)</dt>
                <dd>Intenta abrir la página nuevamente. Si el error ocurrió al guardar, enviar o pagar, revisa el estado de esa operación antes de repetirla.</dd>
            </div>
        </dl>
        @guest
            <x-button :href="route('auth.login.show')" variant="secondary" class="mt-3">Abrir inicio de sesión</x-button>
        @endguest
    </x-card>

    <x-card>
        <x-section-header title="Folio de incidente" :institutional="true" />
        <p class="mt-2">Si el problema continúa, anota el folio que aparece en la página de error, la fecha y hora, y la acción que intentaste realizar.</p>
        <p class="mt-2">Si tu institución cuenta con un canal de soporte, comparte esos datos por ese medio. Evita incluir contraseñas, códigos de verificación, datos de pago o respuestas de estudiantes.</p>
    </x-card>

    <x-button :href="route('help.index')" variant="secondary">Abrir Centro de Ayuda</x-button>
</div>
@endsection
