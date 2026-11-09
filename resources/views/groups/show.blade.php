@extends('layouts.app')

@section('title', $group->name)

@section('content')
<div x-data="{ showConfirm: false }">
    <div class="mb-7 flex flex-wrap items-start justify-between gap-5">
        <div class="min-w-0">
            <a href="{{ route('groups.index') }}" class="mb-4 inline-flex min-h-11 items-center text-sm font-semibold text-acode-tech hover:underline">← Volver a Mis grupos</a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-bold tracking-tight text-acode-primary">{{ $group->name }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $group->is_closed ? 'bg-slate-200 text-slate-700' : 'bg-acode-accent/15 text-acode-accent' }}">{{ $group->is_closed ? 'Cerrado' : 'Activo' }}</span>
            </div>
            @if ($group->subject)
                <p class="mt-2 text-sm text-slate-500">{{ $group->subject }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="$dispatch('open-group-qr')" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-acode-tech px-5 text-sm font-semibold text-white hover:opacity-90">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM21 14v3m-4 4h4m-7-4v4"/></svg>
                Abrir código de incorporación
            </button>

        </div>
    </div>

    <div x-data="groupAccess(@js($group->join_code ?? $group->access_code ?? $group->access_folio ?? $group->folio ?? $group->code ?? ''))" @open-group-qr.window="openQr()">
        <div x-show="showQr" x-cloak @keydown.escape.window="showQr = false" class="fixed inset-0 z-[100] flex items-center justify-center bg-acode-primary/60 p-4" role="dialog" aria-modal="true" aria-label="Código QR de incorporación">
            <div class="absolute inset-0" @click="showQr = false"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3"><div><h2 class="text-xl font-bold text-acode-primary">Código de incorporación</h2><p class="mt-1 text-sm text-slate-500">Comparte el QR o el folio con tus estudiantes.</p></div><button type="button" @click="showQr = false" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Cerrar">✕</button></div>
                <p class="mt-5 text-center font-mono text-2xl font-bold tracking-widest text-acode-primary" x-text="folio"></p>
                <div x-ref="qrContainer" class="mx-auto mt-4 flex min-h-[224px] w-[224px] items-center justify-center"></div>
                <p x-show="qrError" x-text="qrError" class="mt-3 text-center text-sm text-acode-danger"></p>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="copyFolio()" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-acode-primary">Copiar folio</button>
                    <button type="button" @click="downloadQr()" :disabled="!qrReady" class="min-h-11 rounded-lg bg-acode-primary px-4 text-sm font-semibold text-white disabled:opacity-50">Descargar QR</button>
                </div>
                <p x-show="copied" class="mt-2 text-xs text-acode-accent">Folio copiado.</p>
                <p x-show="copyError" class="mt-2 text-xs text-acode-danger">No se pudo copiar el folio.</p>
            </div>
        </div>
    </div>

    {{-- Navegación por pestañas --}}
    <div x-data="{ activeTab: 'students' }" class="mt-8">
        <div class="mb-7 flex gap-2 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Secciones del grupo">
            @foreach (['students' => 'Estudiantes', 'exams' => 'Exámenes', 'grades' => 'Calificaciones', 'evaluation' => 'Configuración de evaluación'] as $tab => $label)
                <button type="button" role="tab" @click="activeTab = @js($tab)" :aria-selected="activeTab === @js($tab)" :class="activeTab === @js($tab) ? 'border-acode-tech text-acode-primary' : 'border-transparent text-slate-500 hover:text-acode-primary'" class="min-h-11 whitespace-nowrap border-b-[3px] px-4 py-3 text-sm font-semibold">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Pestaña: Estudiantes --}}
        <section x-show="activeTab === 'students'" x-data="{ search: '' }">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-acode-accent/15 text-acode-accent"><x-nav-icon name="groups" class="h-6 w-6" /></div>
                        <div><h2 class="text-xl font-bold text-acode-primary">Estudiantes</h2><p class="text-sm text-slate-500">{{ $group->students->count() }} integrantes</p></div>
                    </div>
                    @unless ($group->students->isEmpty())
                        <label class="relative block w-full sm:max-w-sm"><span class="sr-only">Buscar estudiante</span><input type="search" x-model="search" placeholder="Buscar estudiante" class="min-h-11 w-full rounded-lg border border-slate-300 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech"></label>
                    @endunless
                </div>
                @if ($group->students->isEmpty())
                    <div class="rounded-xl bg-acode-surface p-8 text-center text-sm text-slate-500">Aún no se ha unido ningún estudiante a este grupo.</div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[560px] text-sm"><thead class="bg-acode-primary text-white"><tr><th class="px-5 py-4 text-left">Estudiante</th><th class="px-5 py-4 text-left">Correo</th><th class="px-5 py-4 text-left">Se unió</th></tr></thead>
                        <tbody>
                        @foreach ($group->students as $student)
                            <tr class="border-b border-slate-100 even:bg-acode-surface" x-show="@js(mb_strtolower($student->full_name . ' ' . $student->email)).includes(search.toLocaleLowerCase().trim())"><td class="px-5 py-4 font-semibold text-acode-primary">{{ $student->full_name }}</td><td class="px-5 py-4 text-slate-600">{{ $student->email }}</td><td class="px-5 py-4 text-slate-600">{{ $student->pivot->joined_at ?? '—' }}</td></tr>
                        @endforeach
                        </tbody></table>
                    </div>
                @endif
            </div>
        </section>

    {{-- Pestaña: Exámenes --}}
        <section x-show="activeTab === 'exams'" x-cloak>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-acode-primary">
                            Exámenes del grupo ({{ $group->exams->count() }})
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Consulta los exámenes asignados a este grupo.
                        </p>
                    </div>
                    <a href="{{ route('exams.create') }}"
                       class="inline-flex min-h-11 items-center rounded-lg bg-acode-primary px-4 text-sm font-semibold text-white hover:opacity-90">
                        + Nuevo examen
                    </a>
                </div>

                @if ($group->exams->isEmpty())
                    <div class="mt-6 rounded-lg bg-acode-surface p-6 text-center">
                        <p class="text-sm text-slate-600">
                            Todavía no hay exámenes asignados a este grupo.
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            Al crear un examen, podrás seleccionar los grupos a los que se asignará.
                        </p>
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($group->exams as $exam)
                            <article class="rounded-xl border border-slate-200 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-acode-primary">
                                            {{ $exam->title }}
                                        </h3>
                                        @if ($exam->description)
                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ $exam->description }}
                                            </p>
                                        @endif
                                    </div>
                                    @if ($exam->is_published)
                                        <span class="rounded-full bg-acode-accent/15 px-3 py-1 text-xs font-semibold text-acode-accent">
                                            Publicado
                                        </span>
                                    @else
                                        <span class="rounded-full bg-acode-warning/20 px-3 py-1 text-xs font-semibold text-acode-primary">
                                            Borrador
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs text-slate-500">Inicio</p>
                                        <p class="mt-1 font-medium text-slate-700">
                                            {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Finalización</p>
                                        <p class="mt-1 font-medium text-slate-700">
                                            {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Escala de calificación</p>
                                        <p class="mt-1 font-medium text-slate-700">
                                            {{ $exam->grading_scale }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                                    @if ($exam->is_published)
                                        <p class="text-xs text-slate-500">
                                            La pantalla de armado actual permite cambios; consulta con precaución.
                                        </p>
                                    @else
                                        <p class="text-xs text-slate-500">
                                            Puedes continuar preparando este examen.
                                        </p>
                                    @endif
                                    <a href="{{ route('exams.assemble', $exam) }}"
                                       class="inline-flex min-h-11 items-center rounded-lg border border-acode-tech px-4 text-sm font-semibold text-acode-tech hover:bg-acode-surface">
                                        {{ $exam->is_published ? 'Abrir armado' : 'Continuar armado' }}
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Pestaña: Calificaciones --}}
        <section x-show="activeTab === 'grades'"
                x-cloak
                x-data="{ search: '', statusFilter: 'all' }">

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-acode-primary">
                        Calificaciones del grupo
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Consulta los intentos de examen y sus resultados registrados.
                    </p>
                </div>

                {{-- Resumen --}}
                <div class="mb-6 grid gap-4 sm:grid-cols-3">

                    <div class="rounded-xl bg-acode-surface p-4">
                        <p class="text-xs font-medium text-slate-500">
                            Intentos registrados
                        </p>
                        <p class="mt-2 text-2xl font-bold text-acode-primary">
                            {{ $attempts->count() }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-acode-surface p-4">
                        <p class="text-xs font-medium text-slate-500">
                            Exámenes entregados
                        </p>
                        <p class="mt-2 text-2xl font-bold text-acode-primary">
                            {{ $attempts->where('status', 'submitted')->count() }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-acode-surface p-4">
                        <p class="text-xs font-medium text-slate-500">
                            En progreso
                        </p>
                        <p class="mt-2 text-2xl font-bold text-acode-primary">
                            {{ $attempts->where('status', 'in_progress')->count() }}
                        </p>
                    </div>

                </div>

                @if ($attempts->isEmpty())

                    <div class="rounded-xl bg-acode-surface p-8 text-center">
                        <p class="font-medium text-acode-primary">
                            Aún no hay intentos de examen registrados.
                        </p>
                        <p class="mt-2 text-sm text-slate-500">
                            Los resultados aparecerán cuando los estudiantes
                            comiencen a presentar los exámenes del grupo.
                        </p>
                    </div>

                @else

                    {{-- Filtros --}}
                    <div class="mb-5 grid gap-4 md:grid-cols-2">

                        <div>
                            <label for="grade-search"
                                class="mb-2 block text-sm font-semibold text-acode-primary">
                                Buscar estudiante o examen
                            </label>

                            <input id="grade-search"
                                type="search"
                                x-model="search"
                                placeholder="Nombre, correo o examen..."
                                class="h-11 w-full rounded-lg border border-slate-300 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                        </div>

                        <div>
                            <label for="grade-status"
                                class="mb-2 block text-sm font-semibold text-acode-primary">
                                Estado del intento
                            </label>

                            <select id="grade-status"
                                    x-model="statusFilter"
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-4 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
                                <option value="all">Todos los estados</option>
                                <option value="submitted">Entregado</option>
                                <option value="in_progress">En progreso</option>
                                <option value="expired">Expirado</option>
                                <option value="cancelled_by_incident">
                                    Cancelado por incidente
                                </option>
                            </select>
                        </div>

                    </div>

                    {{-- Tabla --}}
                    <div class="overflow-x-auto rounded-xl border border-slate-200">

                        <table class="w-full min-w-[780px] text-sm">

                            <thead class="bg-acode-primary text-xs uppercase tracking-wide text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left">Estudiante</th>
                                    <th class="px-4 py-3 text-left">Examen</th>
                                    <th class="px-4 py-3 text-left">Estado</th>
                                    <th class="px-4 py-3 text-left">Resultado registrado</th>
                                    <th class="px-4 py-3 text-left">Entrega</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($attempts as $attempt)

                                    <tr class="border-b border-slate-100 even:bg-acode-surface"
                                        x-show="
                                            @js(mb_strtolower(
                                                ($attempt->student?->full_name ?? '') . ' ' .
                                                ($attempt->student?->email ?? '') . ' ' .
                                                ($attempt->exam?->title ?? '')
                                            )).includes(search.toLocaleLowerCase().trim())
                                            &&
                                            (statusFilter === 'all' ||
                                            statusFilter === @js($attempt->status))
                                        ">

                                        <td class="px-4 py-4">
                                            <p class="font-semibold text-acode-primary">
                                                {{ $attempt->student?->full_name ?? 'Estudiante no disponible' }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ $attempt->student?->email ?? '—' }}
                                            </p>
                                        </td>

                                        <td class="px-4 py-4 text-slate-700">
                                            {{ $attempt->exam?->title ?? 'Examen no disponible' }}
                                        </td>

                                        <td class="px-4 py-4">
                                            @switch($attempt->status)
                                                @case('submitted')
                                                    <span class="rounded-full bg-acode-accent/15 px-3 py-1 text-xs font-semibold text-acode-accent">
                                                        Entregado
                                                    </span>
                                                    @break

                                                @case('in_progress')
                                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-acode-tech">
                                                        En progreso
                                                    </span>
                                                    @break

                                                @case('expired')
                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                                        Expirado
                                                    </span>
                                                    @break

                                                @case('cancelled_by_incident')
                                                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-acode-danger">
                                                        Cancelado
                                                    </span>
                                                    @break
                                            @endswitch
                                        </td>

                                        <td class="px-4 py-4">
                                            @if ($attempt->status === 'submitted'
                                                && $attempt->final_score !== null)

                                                <p class="font-semibold text-acode-primary">
                                                    {{ number_format((float) $attempt->final_score, 2) }}
                                                    / {{ $attempt->exam?->grading_scale ?? '—' }}
                                                </p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    Confirmación docente no verificada
                                                </p>

                                            @elseif ($attempt->status === 'submitted')

                                                <span class="text-sm text-slate-500">
                                                    Sin resultado final registrado
                                                </span>

                                            @else

                                                <span class="text-sm text-slate-400">
                                                    No disponible
                                                </span>

                                            @endif
                                        </td>

                                        <td class="px-4 py-4 text-slate-600">
                                            {{ $attempt->submitted_at?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="mt-5 text-xs leading-relaxed text-slate-500">
                    Los resultados mostrados corresponden a los registros existentes.
                    Esta pantalla no confirma ni modifica calificaciones.
                </p>
            </div>
        </section>

        {{-- Pestaña: Configuración de evaluación --}}
        <section x-show="activeTab === 'evaluation'" x-cloak>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                {{-- Encabezado --}}
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-acode-primary">
                        Configuración de evaluación
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Consulta los periodos de evaluación y las ponderaciones
                        registradas para este grupo.
                    </p>
                </div>

                {{-- Resumen --}}
                <div class="mb-6 grid gap-4 sm:grid-cols-2">

                    <div class="rounded-xl bg-acode-surface p-4">
                        <p class="text-xs font-medium text-slate-500">
                            Periodos registrados
                        </p>

                        <p class="mt-2 text-2xl font-bold text-acode-primary">
                            {{ $group->evaluationPeriods->count() }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-acode-surface p-4">
                        <p class="text-xs font-medium text-slate-500">
                            Estado del grupo
                        </p>

                        <p class="mt-2 text-lg font-bold text-acode-primary">
                            {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                        </p>
                    </div>

                </div>

                @if ($group->evaluationPeriods->isEmpty())

                    {{-- Estado vacío --}}
                    <div class="rounded-xl border border-slate-200 bg-acode-surface p-8 text-center">

                        <h3 class="font-semibold text-acode-primary">
                            No hay periodos de evaluación registrados
                        </h3>

                        <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-slate-500">
                            Cuando se registren periodos de evaluación para este
                            grupo, podrás consultar aquí sus fechas y la
                            distribución de sus ponderaciones.
                        </p>

                    </div>

                @else

                    {{-- Periodos existentes --}}
                    <div class="space-y-5">

                        @foreach ($group->evaluationPeriods->sortBy('starts_at') as $period)

                            @php
                                $componentNames = [
                                    'exams' => 'Exámenes',
                                    'homework' => 'Tareas',
                                    'project' => 'Proyectos',
                                    'other' => 'Otros',
                                ];

                                $formula = preg_replace(
                                    '/\s+/',
                                    '',
                                    (string) $period->formula
                                );

                                $weights = [];
                                $validFormula = $formula !== '';
                                $totalWeight = 0;

                                foreach (explode('+', $formula) as $term) {
                                    if (!preg_match(
                                        '/^(exams|homework|project|other)\*(\d+(?:\.\d+)?)$/',
                                        $term,
                                        $matches
                                    )) {
                                        $validFormula = false;
                                        break;
                                    }

                                    $component = $matches[1];
                                    $weight = (float) $matches[2];

                                    if (array_key_exists($component, $weights)) {
                                        $validFormula = false;
                                        break;
                                    }

                                    $weights[$component] = $weight * 100;
                                    $totalWeight += $weight * 100;
                                }

                                $totalIs100 = $validFormula
                                    && abs($totalWeight - 100) < 0.001;
                            @endphp

                            <article class="rounded-xl border border-slate-200 p-5">

                                {{-- Nombre del periodo --}}
                                <div class="flex flex-wrap items-start justify-between gap-3">

                                    <div>
                                        <h3 class="text-base font-semibold text-acode-primary">
                                            {{ $period->name }}
                                        </h3>

                                        <p class="mt-1 text-xs text-slate-500">
                                            Periodo de evaluación registrado
                                        </p>
                                    </div>

                                    @if ($totalIs100)
                                        <span class="rounded-full bg-acode-accent/15 px-3 py-1 text-xs font-semibold text-acode-accent">
                                            Ponderación: 100 %
                                        </span>
                                    @elseif ($validFormula)
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                            Ponderación por revisar
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                            Fórmula no interpretable
                                        </span>
                                    @endif

                                </div>

                                {{-- Fechas --}}
                                <div class="mt-5 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">

                                    <div>
                                        <p class="text-xs text-slate-500">
                                            Fecha de inicio
                                        </p>

                                        <p class="mt-1 text-sm font-medium text-slate-700">
                                            {{ $period->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-slate-500">
                                            Fecha de finalización
                                        </p>

                                        <p class="mt-1 text-sm font-medium text-slate-700">
                                            {{ $period->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                        </p>
                                    </div>

                                </div>

                                {{-- Ponderaciones --}}
                                <div class="mt-5 border-t border-slate-100 pt-4">

                                    <h4 class="mb-4 text-sm font-semibold text-acode-primary">
                                        Distribución de ponderaciones
                                    </h4>

                                    @if ($validFormula)

                                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

                                            @foreach ($componentNames as $key => $name)

                                                <div class="rounded-lg bg-acode-surface p-4">

                                                    <p class="text-xs text-slate-500">
                                                        {{ $name }}
                                                    </p>

                                                    <p class="mt-2 text-xl font-bold text-acode-primary">
                                                        {{ number_format($weights[$key] ?? 0, 2) }} %
                                                    </p>

                                                </div>

                                            @endforeach

                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-4">

                                            <span class="text-sm font-medium text-slate-700">
                                                Total de ponderaciones
                                            </span>

                                            <span class="text-lg font-bold {{ $totalIs100 ? 'text-acode-accent' : 'text-acode-danger' }}">
                                                {{ number_format($totalWeight, 2) }} %
                                            </span>

                                        </div>

                                        @unless ($totalIs100)
                                            <p class="mt-3 text-xs text-acode-danger">
                                                Las ponderaciones registradas no suman 100 %.
                                                Esta pantalla únicamente muestra la configuración existente.
                                            </p>
                                        @endunless

                                    @else

                                        <div class="rounded-lg bg-acode-surface p-4">

                                            <p class="text-sm text-slate-600">
                                                La fórmula registrada no puede representarse
                                                como una distribución simple de ponderaciones.
                                            </p>

                                            <p class="mt-3 break-all font-mono text-xs text-slate-500">
                                                {{ $period->formula }}
                                            </p>

                                        </div>

                                    @endif

                                </div>

                            </article>

                        @endforeach

                    </div>

                @endif

                {{-- Nota informativa --}}
                <p class="mt-6 text-xs leading-relaxed text-slate-500">
                    Esta sección muestra la configuración registrada en el sistema.
                    No modifica periodos, fórmulas ni calificaciones.
                    La creación y edición de ponderaciones se integrará
                    cuando esté validado su flujo funcional.
                </p>
            </div>
        </section>
    </div>

        {{-- Modal de confirmación --}}
        <div class="mt-8 flex flex-wrap items-center gap-3">
            <button type="button" @click="showConfirm = true" class="inline-flex min-h-11 items-center rounded-lg border px-5 text-sm font-semibold {{ $group->is_closed ? 'border-acode-primary text-acode-primary hover:bg-white' : 'border-acode-danger text-acode-danger hover:bg-red-50' }}">{{ $group->is_closed ? 'Reabrir grupo' : 'Cerrar grupo' }}</button>
        </div>

        <div x-show="showConfirm"
             x-cloak
             @keydown.escape.window="showConfirm = false"
             class="fixed inset-0 z-[100] flex items-center justify-center bg-acode-primary/60 p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="group-confirm-title">

            <div class="absolute inset-0"
                 @click="showConfirm = false"
                 aria-hidden="true"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
                 @click.stop>

                <div class="flex items-start gap-3">

                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl
                        {{ $group->is_closed
                            ? 'bg-acode-surface text-acode-tech'
                            : 'bg-red-50 text-acode-danger' }}">

                        @if ($group->is_closed)

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-6 w-6"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor"
                                 stroke-width="1.8"
                                 aria-hidden="true">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="M3 12a9 9 0 0 1 15.36-6.36L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.36 6.36L3 16m0 5v-5h5"/>
                            </svg>

                        @else

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-6 w-6"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor"
                                 stroke-width="1.8"
                                 aria-hidden="true">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="M12 9v4m0 4h.01M10.29 3.86 1.82 18.14A2 2 0 00 3.53 21h16.94a2 2 0 0 0 1.71-2.86L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                            </svg>

                        @endif

                    </div>

                    <div>
                        <h2 id="group-confirm-title"
                            class="text-lg font-bold text-acode-primary">

                            @if ($group->is_closed)
                                ¿Reabrir este grupo?
                            @else
                                ¿Cerrar este grupo?
                            @endif

                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Confirma la acción antes de continuar.
                        </p>
                    </div>

                </div>

                <p class="mt-5 text-sm leading-relaxed text-slate-600">

                    @if ($group->is_closed)

                        El grupo
                        <strong class="text-acode-primary">
                            {{ $group->name }}
                        </strong>
                        volverá a estar activo.

                        Se conservarán sus estudiantes, su información
                        y su folio de acceso.

                    @else

                        El grupo
                        <strong class="text-acode-primary">
                            {{ $group->name }}
                        </strong>
                        dejará de estar activo.

                        Su información y los estudiantes inscritos
                        se conservarán.

                    @endif

                </p>

                <form method="POST"
                      action="{{ $group->is_closed
                          ? route('groups.reopen', $group)
                          : route('groups.close', $group) }}"
                      class="mt-6 flex flex-wrap justify-end gap-3">

                    @csrf

                    <button type="button"
                            @click="showConfirm = false"
                            class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-acode-primary hover:bg-slate-50">
                        Cancelar
                    </button>

                    <button type="submit"
                            class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90
                            {{ $group->is_closed
                                ? 'bg-acode-primary'
                                : 'bg-acode-danger' }}">

                        @if ($group->is_closed)
                            Sí, reabrir grupo
                        @else
                            Sí, cerrar grupo
                        @endif

                    </button>

                </form>

            </div>

        </div>


</div>
@endsection

@push('scripts')
    {{-- El generador funciona en el navegador; requiere acceso al CDN. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        function groupAccess(folio) {
            return {
                folio,
                showQr: false,
                copied: false,
                copyError: false,
                qrReady: false,
                qrError: '',

                openQr() {
                    this.showQr = true;
                    if (!this.folio || !String(this.folio).trim()) {
                        this.qrError = 'No se encontró el folio de incorporación del grupo. Hay que verificar el atributo del modelo Group.';
                        return;
                    }
                    this.$nextTick(() => {
                        if (this.qrReady) return;
                        if (typeof QRCode === 'undefined') {
                            this.qrError = 'No se pudo cargar el generador de QR. Comprueba tu conexión.';
                            return;
                        }
                        this.$refs.qrContainer.replaceChildren();
                        try {
                            new QRCode(this.$refs.qrContainer, {
                                text: this.folio,
                                width: 224,
                                height: 224,
                                colorDark: '#142B4A',
                                colorLight: '#FFFFFF',
                                correctLevel: QRCode.CorrectLevel.H
                            });
                            this.qrReady = true;
                            this.qrError = '';
                        } catch (error) {
                            this.qrError = 'No fue posible generar el código QR.';
                        }
                    });
                },

                async copyFolio() {
                    try {
                        await navigator.clipboard.writeText(this.folio);
                        this.copied = true;
                        this.copyError = false;
                    } catch (error) {
                        this.copied = false;
                        this.copyError = true;
                    }
                },

                downloadQr() {
                    if (!this.qrReady) return;
                    const canvas = this.$refs.qrContainer.querySelector('canvas');
                    const img = this.$refs.qrContainer.querySelector('img');
                    const dataUrl = canvas ? canvas.toDataURL('image/png') : img?.src;
                    if (!dataUrl) {
                        this.qrError = 'No fue posible preparar la descarga.';
                        return;
                    }
                    const link = document.createElement('a');
                    link.href = dataUrl;
                    link.download = 'QR-grupo-' + this.folio + '.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }
            };
        }
    </script>
@endpush