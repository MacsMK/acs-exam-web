{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/exams/instructions.blade.php
    Ubicación:   resources/views/student/exams/instructions.blade.php
    Descripción: Pantalla de instrucciones previas y confirmación expresa antes
                 de iniciar examen contrarreloj (RF-EXA-028, RF-EXA-029, RW-25;
                 MPW-V3 §7.3, §7.4.2, §7.7, §8.1–§8.3; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Instrucciones del Examen · ' . $exam->title)

@section('content')
@php
    $canStart = $row['action'] === 'start';
    $scheduled = $row['state'] === 'scheduled';
@endphp

<div class="space-y-6">
    <x-page-header
        :title="$exam->title"
        :subtitle="$exam->description"
        :breadcrumbs="[
            ['label' => 'Mis Exámenes', 'href' => route('student.exams.index')],
            ['label' => 'Instrucciones del Examen'],
        ]"
        institutional
    >
        <x-slot:badge>
            <x-badge
                :variant="$row['statusVariant']"
                :class="$row['statusVariant'] === 'danger' ? '!text-acode-danger-dark' : ''"
            >{{ $row['statusLabel'] }}</x-badge>
        </x-slot:badge>

        <x-slot:meta>
            <span>
                Profesor titular: <strong class="text-slate-700">{{ $exam->teacher?->full_name ?? 'Docente titular' }}</strong>
            </span>
            <span>·</span>
            <span>Grupo: <strong class="text-slate-700">{{ $row['groupNames'] }}</strong></span>
            <span>·</span>
            <span>
                Escala: <strong class="text-slate-700">{{ $exam->grading_scale === '0-100' ? '0 a 100 puntos' : '0 a 10 con decimales' }}</strong>
            </span>
        </x-slot:meta>

        @unless ($canStart)
            <x-slot:actions>
                <x-button :href="route('student.exams.index')" variant="secondary">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        @endunless
    </x-page-header>

    <x-card padding="p-4 sm:p-5" class="space-y-3">
        <x-summary-list>
            @if ($row['startsAt'])
                <x-summary-item label="Fecha de inicio" compact>
                    <x-slot:valueSlot>
                        <time datetime="{{ $row['startsAt']->toIso8601String() }}">{{ $row['startsAt']->format('d/m/Y H:i') }}</time>
                    </x-slot:valueSlot>
                </x-summary-item>
            @endif
            <x-summary-item label="Fecha límite" compact>
                <x-slot:valueSlot>
                    @if ($row['endsAt'])
                        <time datetime="{{ $row['endsAt']->toIso8601String() }}">{{ $row['endsAt']->format('d/m/Y H:i') }}</time>
                    @else
                        Sin vencimiento
                    @endif
                </x-slot:valueSlot>
            </x-summary-item>
            <x-summary-item label="Zona horaria" :value="$timeZone" compact />
        </x-summary-list>
    </x-card>

    @unless ($canStart)
        <x-empty-state
            icon="clock"
            :title="$scheduled ? 'Examen programado' : 'Examen cerrado'"
            :message="$scheduled
                ? 'Podrás iniciar el examen a partir de la fecha de inicio.'
                : 'Este examen ya no está disponible para iniciar un intento.'"
        />
    @endunless

    @if ($canStart || $scheduled)
        <x-card padding="p-4 sm:p-5">
            <x-summary-list>
                <x-summary-item label="Reactivos" :value="$questionsCount" compact />
                <x-summary-item
                    label="Tiempo máximo"
                    :value="$durationMinutes.' min'"
                    :note="$exam->free_duration_minutes !== null ? 'Navegación libre' : 'Suma de los tiempos por pregunta'"
                    compact
                />
                <x-summary-item label="Orden de preguntas" :value="$exam->randomize_question_order ? 'Aleatorio' : 'Fijo'" compact />
            </x-summary-list>
        </x-card>

        <x-card padding="p-6 sm:p-8" class="[&_.card-body]:space-y-6">
            <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-5 space-y-3">
                <h2 class="flex items-center gap-2.5 text-xl font-semibold leading-7 text-acode-primary">
                    <x-icon name="warning" class="h-5 w-5 shrink-0" />
                    Condiciones obligatorias para presentar esta evaluación
                </h2>
                <ul class="list-inside list-disc space-y-2 text-sm text-slate-700">
                    <li><strong>El tiempo corre en el servidor:</strong> Este examen es contrarreloj. Una vez iniciado, el cronómetro no se detendrá ni se puede pausar.</li>
                    @if ($exam->free_duration_minutes !== null)
                        <li><strong>Navegación libre.</strong> Puedes volver a las preguntas y modificar tus respuestas durante los {{ $exam->free_duration_minutes }} minutos del examen.</li>
                    @else
                        <li><strong>Secuencia irreversible:</strong> Cada reactivo debe responderse dentro de su límite temporal. Al agotarse el tiempo o enviar la respuesta, el sistema avanza automáticamente sin posibilidad de retroceso.</li>
                    @endif
                    <li><strong>Supervisión de foco:</strong> Cambiar de pestaña, abrir otras aplicaciones o minimizar la ventana será registrado como una incidencia de seguridad.</li>
                    <li><strong>Guardado automático:</strong> {{ $exam->free_duration_minutes !== null ? 'Los cambios se guardan mientras respondes. Comprueba la confirmación antes de salir.' : 'Tu respuesta se guarda en el servidor mientras respondes y se registra al pulsar «Enviar respuesta» o al agotarse el tiempo de la pregunta.' }}</li>
                </ul>
            </div>

            @if ($reapplicationNumber !== null)
                <div class="rounded-lg border border-acode-tech/30 bg-acode-tech/10 px-4 py-3 text-sm text-slate-700">
                    <strong>Reaplicación autorizada:</strong> tu docente te dio un intento adicional. Este será tu intento {{ $reapplicationNumber }}. Se conservará el anterior y el nuevo determinará tu calificación.
                </div>
            @endif

            @if ($canStart)
                <div class="flex flex-col gap-4 border-t border-slate-100 pt-5 sm:flex-row-reverse sm:items-center sm:justify-between">
                    <form
                        method="POST"
                        action="{{ route('attempts.start', $exam) }}"
                        x-data="{ submitting: false }"
                        x-on:pageshow.window="submitting = false"
                        x-on:submit="if (submitting) $event.preventDefault(); else submitting = true"
                    >
                        @csrf
                        <x-button
                            type="submit"
                            variant="primary"
                            size="lg"
                            class="w-full sm:w-auto"
                            loading-when="submitting"
                            x-bind:aria-busy="submitting.toString()"
                            x-bind:disabled="submitting"
                        >
                            <x-icon name="clock" class="h-5 w-5" x-show="!submitting" />
                            <x-slot:label>Confirmar e iniciar examen</x-slot:label>
                        </x-button>
                    </form>
                    <x-button :href="route('student.exams.index')" variant="neutral" class="w-full sm:w-auto">
                        <x-icon name="arrowLeft" class="w-4 h-4" />
                        Cancelar y volver
                    </x-button>
                </div>
            @endif
        </x-card>
    @endif
</div>
@endsection
