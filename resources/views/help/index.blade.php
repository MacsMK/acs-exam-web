@extends('layouts.app')

@section('title', 'Centro de Ayuda')

@section('content')
@php($user = auth()->user())

<div @class(['mx-auto max-w-4xl space-y-6', 'px-4 py-8' => auth()->guest()])>
    <x-page-header title="Centro de Ayuda" :institutional="true" />

    @if ($user?->role === 'teacher')
        @if (! $user->isVerifiedTeacher())
            <x-card>
                <x-section-header title="Verificación de cuenta" :institutional="true" />
                <p class="mt-2">Consulta el estado de tu verificación para saber si hace falta corregir o completar algún dato.</p>
                <x-button :href="route('teacher.verification.status')" variant="secondary" class="mt-3">Abrir verificación de cuenta</x-button>
            </x-card>
        @elseif ($user->needsPlanOnboarding())
            <x-card>
                <x-section-header title="Selección de plan" :institutional="true" />
                <p class="mt-2">Confirma la prueba gratuita o elige un paquete para entrar al Panel Docente.</p>
                <x-button :href="route('dashboard')" variant="secondary" class="mt-3">Abrir selección de plan</x-button>
            </x-card>
        @else
            <x-card>
                <x-section-header title="Revisión de entregas" :institutional="true" />
                <p class="mt-2">En Revisión, abre una entrega para calificarla manualmente o solicitar una evaluación con IA para los reactivos abiertos y de código.</p>
                <x-button :href="route('teacher.reviews.index')" variant="secondary" class="mt-3">Abrir revisión</x-button>
            </x-card>

            <x-card>
                <x-section-header title="Créditos de IA" :institutional="true" />
                <p class="mt-2">Consulta el saldo disponible, los créditos usados y la próxima reposición cuando aplique.</p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <x-button :href="route('teacher.ai-credits.index')" variant="secondary">Abrir Créditos de IA</x-button>
                    <x-button :href="route('teacher.subscription.index')" variant="secondary">Abrir suscripción</x-button>
                </div>
            </x-card>
        @endif
    @elseif ($user?->role === 'student')
        <x-card>
            <x-section-header title="Mis exámenes" :institutional="true" />
            <p class="mt-2">Consulta los exámenes asignados y abre las instrucciones antes de comenzar. Si tu grupo no aparece, confirma con tu docente el folio de inscripción.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <x-button :href="route('student.exams.index')" variant="secondary">Abrir mis exámenes</x-button>
                <x-button :href="route('student.groups.index')" variant="secondary">Abrir mis grupos</x-button>
            </div>
        </x-card>
    @elseif ($user?->role === 'super_admin')
        <x-card>
            <x-section-header title="Gestión de docentes" :institutional="true" />
            <p class="mt-2">Abre el listado de docentes para localizar una cuenta y consultar su estado de verificación y suscripción.</p>
            <x-button :href="route('admin.teachers.index')" variant="secondary" class="mt-3">Abrir docentes</x-button>
        </x-card>
    @else
        <x-card>
            <x-section-header title="Acceso a la plataforma" :institutional="true" />
            <p class="mt-2">Entra con el correo que registraste para consultar las opciones de tu cuenta.</p>
            <x-button :href="route('auth.login.show')" variant="secondary" class="mt-3">Abrir inicio de sesión</x-button>
        </x-card>
    @endif

    <x-card>
        <x-section-header title="Soporte Técnico" :institutional="true" />
        <p class="mt-2">Consulta qué hacer si una página muestra un error o si tienes problemas para acceder.</p>
        <x-button :href="route('help.support')" variant="secondary" class="mt-3">Abrir Soporte Técnico</x-button>
    </x-card>
</div>
@endsection
