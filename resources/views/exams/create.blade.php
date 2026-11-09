@extends('layouts.app')

@section('title', 'Crear examen')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">

    <a href="{{ route('exams.index') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech hover:underline">
        <span aria-hidden="true">←</span>
        Volver a mis exámenes
    </a>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-acode-tech">
            Etapa 1 de 4 · Datos
        </p>

        <h1 class="mt-1 text-2xl font-bold text-acode-primary">
            Crear examen
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Comienza con la información general.
            Después seleccionarás las preguntas y configurarás
            las condiciones de aplicación.
        </p>
    </div>

    {{-- Indicador de etapas --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="grid grid-cols-4 gap-2">
            @foreach (['Datos', 'Preguntas', 'Configuración', 'Revisión'] as $index => $label)
                <div class="flex flex-col items-center gap-2 text-center">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold
                        {{ $index === 0
                            ? 'bg-acode-primary text-white'
                            : 'bg-slate-100 text-slate-500' }}">
                        {{ $index + 1 }}
                    </span>

                    <span class="text-xs {{ $index === 0
                        ? 'font-semibold text-acode-primary'
                        : 'text-slate-500' }}">
                        {{ $label }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <form id="create-exam-form"
          method="POST"
          action="{{ route('exams.store') }}"
          class="space-y-6">

        @csrf

        <section class="rounded-xl border border-slate-200 bg-white p-6 md:p-8">

            <div class="border-b border-slate-200 pb-5">
                <h2 class="text-lg font-semibold text-acode-primary">
                    Datos generales
                </h2>

                <p class="mt-2 text-sm text-slate-500">
                    Identifica tu examen y proporciona las instrucciones
                    que recibirán los estudiantes.
                </p>
            </div>

            <div class="mt-6 space-y-6">

                <div>
                    <label for="exam-title"
                           class="mb-2 block text-sm font-semibold text-slate-800">
                        Título del examen
                    </label>

                    <input
                        id="exam-title"
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        maxlength="150"
                        required
                        placeholder="Ej. Primer examen parcial de Programación"
                        aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}"
                        class="h-12 w-full rounded-lg border px-4 text-sm
                               focus:outline-none focus:ring-2 focus:ring-acode-tech
                               @error('title') border-[#DC2626]
                               @else border-slate-300 @enderror">

                    @error('title')
                        <p class="mt-2 text-xs text-[#DC2626]" role="alert">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="exam-description"
                           class="mb-2 block text-sm font-semibold text-slate-800">
                        Descripción e instrucciones generales
                        <span class="font-normal text-slate-400">
                            (opcional)
                        </span>
                    </label>

                    <textarea
                        id="exam-description"
                        name="description"
                        rows="6"
                        placeholder="Ej. Lee cuidadosamente cada pregunta antes de responder. Revisa las instrucciones de cada apartado."
                        aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}"
                        class="w-full rounded-lg border px-4 py-3 text-sm
                               focus:outline-none focus:ring-2 focus:ring-acode-tech
                               @error('description') border-[#DC2626]
                               @else border-slate-300 @enderror">{{ old('description') }}</textarea>

                    @error('description')
                        <p class="mt-2 text-xs text-[#DC2626]" role="alert">
                            {{ $message }}
                        </p>
                    @enderror

                    <p class="mt-2 text-xs text-slate-500">
                        Puedes incluir el objetivo, recomendaciones
                        o indicaciones para realizar el examen.
                    </p>
                </div>

            </div>
        </section>

        {{-- Programación del examen --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 md:p-8">

            <div class="border-b border-slate-200 pb-5">
                <h2 class="text-lg font-semibold text-acode-primary">
                    Programación del examen
                </h2>

                <p class="mt-2 text-sm text-slate-500">
                    Selecciona la fecha y hora de inicio y término del examen.
                </p>
            </div>

            @php
                $inicioAnterior = old('starts_at', '');
                $terminoAnterior = old('ends_at', '');
            @endphp

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                {{-- Inicio del examen --}}
                <div class="space-y-5">
                    <h3 class="text-sm font-semibold text-acode-primary">
                        Inicio del examen
                    </h3>

                    <div>
                        <label for="start_date"
                               class="mb-2 block text-sm font-semibold text-slate-800">
                            Fecha de inicio
                        </label>

                        <div class="relative">
                            <input
                                id="start_date"
                                type="date"
                                lang="es-MX"
                                required
                                value="{{ substr($inicioAnterior, 0, 10) }}"
                                aria-invalid="{{ $errors->has('starts_at') ? 'true' : 'false' }}"
                                class="h-12 w-full rounded-lg border px-4 text-sm
                                       focus:outline-none focus:ring-2 focus:ring-acode-tech
                                       @error('starts_at') border-[#DC2626]
                                       @else border-slate-300 @enderror">
                        </div>
                    </div>

                    <div>
                        <label for="start_hour"
                               class="mb-2 block text-sm font-semibold text-slate-800">
                            Hora de inicio
                        </label>

                        <div class="flex h-12 items-center gap-1 rounded-lg border
                                    border-slate-300 bg-white px-3
                                    focus-within:ring-2 focus-within:ring-acode-tech">

                            <select id="start_hour"
                                    required
                                    aria-label="Hora de inicio"
                                    class="min-w-0 flex-1 bg-transparent text-center text-sm
                                           text-slate-400 focus:outline-none">
                                <option value="" disabled selected>HH</option>
                                @for ($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">
                                        {{ sprintf('%02d', $i) }}
                                    </option>
                                @endfor
                            </select>

                            <span class="text-sm font-semibold text-slate-500">:</span>

                            <select id="start_minute"
                                    required
                                    aria-label="Minutos de inicio"
                                    class="min-w-0 flex-1 bg-transparent text-center text-sm
                                           text-slate-400 focus:outline-none">
                                <option value="" disabled selected>MM</option>
                                @for ($i = 0; $i < 60; $i++)
                                    <option value="{{ $i }}">
                                        {{ sprintf('%02d', $i) }}
                                    </option>
                                @endfor
                            </select>

                            <select id="start_period"
                                    required
                                    aria-label="AM o PM de inicio"
                                    class="min-w-0 flex-1 bg-transparent text-center text-sm
                                           text-slate-400 focus:outline-none">
                                <option value="" disabled selected>AM/PM</option>
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>

                    @error('starts_at')
                        <p class="text-xs text-[#DC2626]" role="alert">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Término del examen --}}
                <div class="space-y-5">
                    <h3 class="text-sm font-semibold text-acode-primary">
                        Término del examen
                    </h3>

                    <div>
                        <label for="end_date"
                               class="mb-2 block text-sm font-semibold text-slate-800">
                            Fecha de término
                        </label>

                        <div class="relative">
                            <input
                                id="end_date"
                                type="date"
                                lang="es-MX"
                                required
                                value="{{ substr($terminoAnterior, 0, 10) }}"
                                aria-invalid="{{ $errors->has('ends_at') ? 'true' : 'false' }}"
                                class="h-12 w-full rounded-lg border px-4 text-sm
                                       focus:outline-none focus:ring-2 focus:ring-acode-tech
                                       @error('ends_at') border-[#DC2626]
                                       @else border-slate-300 @enderror">
                        </div>
                    </div>

                    <div>
                        <label for="end_hour"
                               class="mb-2 block text-sm font-semibold text-slate-800">
                            Hora de término
                        </label>

                        <div class="flex h-12 items-center gap-1 rounded-lg border
                                    border-slate-300 bg-white px-3
                                    focus-within:ring-2 focus-within:ring-acode-tech">

                            <select id="end_hour"
                                    required
                                    aria-label="Hora de término"
                                    class="min-w-0 flex-1 bg-transparent text-center text-sm
                                           text-slate-400 focus:outline-none">
                                <option value="" disabled selected>HH</option>
                                @for ($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">
                                        {{ sprintf('%02d', $i) }}
                                    </option>
                                @endfor
                            </select>

                            <span class="text-sm font-semibold text-slate-500">:</span>

                            <select id="end_minute"
                                    required
                                    aria-label="Minutos de término"
                                    class="min-w-0 flex-1 bg-transparent text-center text-sm
                                           text-slate-400 focus:outline-none">
                                <option value="" disabled selected>MM</option>
                                @for ($i = 0; $i < 60; $i++)
                                    <option value="{{ $i }}">
                                        {{ sprintf('%02d', $i) }}
                                    </option>
                                @endfor
                            </select>

                            <select id="end_period"
                                    required
                                    aria-label="AM o PM de término"
                                    class="min-w-0 flex-1 bg-transparent text-center text-sm
                                           text-slate-400 focus:outline-none">
                                <option value="" disabled selected>AM/PM</option>
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>

                    @error('ends_at')
                        <p class="text-xs text-[#DC2626]" role="alert">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            {{-- Campos existentes que recibe Laravel --}}
            <input type="hidden"
                   id="starts_at"
                   name="starts_at"
                   value="{{ $inicioAnterior }}">

            <input type="hidden"
                   id="ends_at"
                   name="ends_at"
                   value="{{ $terminoAnterior }}">

            <input type="hidden" name="grading_scale" value="0-10">
            <input type="hidden" name="result_visibility_mode" value="hidden">
            <input type="hidden" name="max_allowed_incidents" value="3">
            <input type="hidden" name="randomize_question_order" value="1">
            <input type="hidden" name="require_webcam" value="0">
            <p id="error-fechas"
                class="hidden mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-[#DC2626]"
                role="alert"
                tabindex="-1">
            </p>
        </section>

        <div class="rounded-xl border border-acode-tech/20 bg-acode-tech/5 p-5">
            <div class="flex items-start gap-3">
                <span class="text-lg text-acode-tech" aria-hidden="true">ⓘ</span>

                <div>
                    <p class="text-sm font-semibold text-acode-primary">
                        Tu examen se guardará como borrador
                    </p>

                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                        En las siguientes etapas podrás seleccionar
                        los reactivos, definir sus condiciones de aplicación
                        y revisar el examen antes de publicarlo.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3
                    border-t border-slate-200 pt-5">

            <x-unsaved-changes
                form-id="create-exam-form"
                :cancel-url="route('exams.index')"
            />

            <button type="submit"
                    class="rounded-lg bg-acode-primary px-6 py-3
                           text-sm font-semibold text-white
                           transition hover:opacity-90">
                Crear borrador y continuar
                <span class="ml-2" aria-hidden="true">→</span>
            </button>
        </div>

    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('create-exam-form');

    function actualizarColores() {
        [
            'start_hour',
            'start_minute',
            'start_period',
            'end_hour',
            'end_minute',
            'end_period'
        ].forEach(function (id) {
            const campo = document.getElementById(id);

            campo.classList.toggle('text-slate-400', campo.value === '');
            campo.classList.toggle('text-slate-800', campo.value !== '');
        });

        ['start_date', 'end_date'].forEach(function (id) {
            const campo = document.getElementById(id);

            campo.classList.toggle('text-slate-400', campo.value === '');
            campo.classList.toggle('text-slate-800', campo.value !== '');
        });
    }

    function restaurar(prefijo, valor) {
        if (!valor || !/[T ]/.test(valor)) {
            return;
        }

        const partes = valor.split(/[T ]/);

        if (!partes[1] || partes[1].length < 5) {
            return;
        }

        const hora24 = Number(partes[1].substring(0, 2));
        const minuto = Number(partes[1].substring(3, 5));

        document.getElementById(prefijo + '_hour').value =
            String(hora24 % 12 || 12);

        document.getElementById(prefijo + '_minute').value =
            String(minuto);

        document.getElementById(prefijo + '_period').value =
            hora24 >= 12 ? 'PM' : 'AM';
    }

    function convertir(prefijo, destino) {
        const fecha = document.getElementById(prefijo + '_date').value;
        const hora = document.getElementById(prefijo + '_hour').value;
        const minuto = document.getElementById(prefijo + '_minute').value;
        const periodo = document.getElementById(prefijo + '_period').value;

        if (!fecha || hora === '' || minuto === '' || periodo === '') {
            document.getElementById(destino).value = '';
            return;
        }

        let hora24 = Number(hora) % 12;

        if (periodo === 'PM') {
            hora24 += 12;
        }

        const horaFormateada = String(hora24).padStart(2, '0');
        const minutoFormateado = String(minuto).padStart(2, '0');

        document.getElementById(destino).value =
            fecha + 'T' + horaFormateada + ':' + minutoFormateado;
    }
    function actualizarFechas() {
        convertir('start', 'starts_at');
        convertir('end', 'ends_at');
        actualizarColores();
    }

    restaurar('start', @json($inicioAnterior));
    restaurar('end', @json($terminoAnterior));

    [
        'start_date',
        'start_hour',
        'start_minute',
        'start_period',
        'end_date',
        'end_hour',
        'end_minute',
        'end_period'
    ].forEach(function (id) {
        document.getElementById(id).addEventListener('change', actualizarFechas);
    });

    form.addEventListener('submit', function (event) {
        actualizarFechas();

        const inicio = document.getElementById('starts_at').value;
        const termino = document.getElementById('ends_at').value;

        const error = document.getElementById('error-fechas');

        error.textContent = '';
        error.classList.add('hidden');

        let mensaje = '';

        if (!inicio || !termino) {
            mensaje = 'Completa las fechas y horas de inicio y término.';
        } else {
            const fechaInicio = new Date(inicio);
            const fechaTermino = new Date(termino);
            const ahora = new Date();

            if (
                Number.isNaN(fechaInicio.getTime()) ||
                Number.isNaN(fechaTermino.getTime())
            ) {
                mensaje = 'Selecciona fechas y horas válidas.';
            } else if (fechaInicio <= ahora) {
                mensaje = 'El inicio del examen debe ser posterior al momento actual.';
            } else if (fechaTermino <= fechaInicio) {
                mensaje = 'El término del examen debe ser posterior a su inicio.';
            }
        }

        if (mensaje) {
            event.preventDefault();
            error.textContent = mensaje;
            error.classList.remove('hidden');
            error.focus();
        }
    });
    actualizarFechas();
});
</script>
@endsection