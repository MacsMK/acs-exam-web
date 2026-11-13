{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/show.blade.php
    Ubicación:   resources/views/groups/show.blade.php
    Descripción: Vista operativa detallada de grupo escolar y sus 4 apartados
                 (Estudiantes, Exámenes, Calificaciones y Periodos de Evaluación)
                 con arquitectura jerárquica en 5 niveles, UI Kit A-Code,
                 avatares vectoriales (ADR-049), transiciones a 60 FPS (ADR-047)
                 y modal de código QR institucional (MPW-V2 §5.2.1, §7, §8).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', $group->name . ' · Detalle del Grupo')

@section('content')
<div
    x-data="{
        activeTab: window.location.hash ? window.location.hash.substring(1) : 'students',
        studentSearch: '',
        gradeSearch: '',
        gradeStatus: 'all',

        setActiveTab(tab) {
            this.activeTab = tab;
            window.location.hash = tab;
        },

        copyFolio() {
            const folio = @js($group->access_folio);
            if (!folio) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(folio).then(() => {
                    if (typeof AcodeAlert !== 'undefined') {
                        AcodeAlert.toast('Folio copiado al portapapeles con éxito', 'success');
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            iconColor: '#16A6A1',
                            title: 'Folio copiado al portapapeles con éxito',
                            showConfirmButton: false,
                            timer: 2500,
                            timerProgressBar: true
                        });
                    }
                }).catch(() => {});
            }
        },

        confirmClose() {
            if (typeof AcodeAlert !== 'undefined') {
                AcodeAlert.confirm({
                    title: '¿Cerrar este grupo escolar?',
                    text: 'El grupo dejará de aceptar nuevos intentos e incorporaciones. La información académica se conservará intacta.',
                    confirmButtonText: 'Sí, cerrar grupo',
                    cancelButtonText: 'Cancelar',
                    isDanger: true,
                    onConfirm: () => {
                        document.getElementById('group-close-form').submit();
                    }
                });
                return;
            }
            if (confirm('¿Cerrar este grupo escolar? Dejará de aceptar nuevos intentos e incorporaciones.')) {
                document.getElementById('group-close-form').submit();
            }
        },

        confirmReopen() {
            if (typeof AcodeAlert !== 'undefined') {
                AcodeAlert.confirm({
                    title: '¿Reabrir este grupo escolar?',
                    text: 'El grupo volverá a estar activo y permitirá la incorporación de alumnos y aplicación de exámenes.',
                    confirmButtonText: 'Sí, reabrir grupo',
                    cancelButtonText: 'Cancelar',
                    isDanger: false,
                    onConfirm: () => {
                        document.getElementById('group-reopen-form').submit();
                    }
                });
                return;
            }
            if (confirm('¿Reabrir este grupo escolar?')) {
                document.getElementById('group-reopen-form').submit();
            }
        }
    }"
    class="space-y-6"
>

    {{-- Nivel 1: Hero Institucional con Folio Visible y Barra de Acciones --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm">
        <div class="flex flex-col gap-4">
            {{-- Navegación de retorno --}}
            <div class="flex items-center justify-between">
                <x-button :href="route('groups.index')" variant="neutral" size="sm">
                    <x-icon name="arrowLeft" class="w-4 h-4 mr-1.5" />
                    Volver a Mis grupos
                </x-button>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 pt-2">
                <div class="space-y-2 max-w-2xl">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">
                            {{ $group->name }}
                        </h1>
                        <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                            {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                        </x-badge>
                    </div>

                    <p class="text-sm font-semibold uppercase tracking-wider text-acode-tech">
                        {{ $group->subject ?? 'SIN ASIGNATURA ESPECIFICADA' }}
                    </p>

                    {{-- Chip de Folio Alfanumérico visible --}}
                    <div class="inline-flex items-center gap-2 rounded-xl bg-acode-surface px-3 py-1.5 border border-slate-200/80">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Folio de acceso:</span>
                        <span class="font-mono text-sm font-bold text-acode-primary select-all">{{ $group->access_folio }}</span>
                        <button
                            type="button"
                            @click="copyFolio()"
                            class="rounded p-1 text-slate-400 hover:text-acode-tech hover:bg-white transition focus:outline-none"
                            title="Copiar folio al portapapeles"
                            aria-label="Copiar folio"
                        >
                            <x-icon name="copy" class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                {{-- Barra de Acciones de Cabecera --}}
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    {{-- 1. Ver código QR --}}
                    <x-button
                        type="button"
                        @click="$dispatch('open-modal', 'group-qr-modal')"
                        variant="secondary"
                        size="md"
                    >
                        <x-icon name="qrCode" class="w-4 h-4 mr-1.5" />
                        Ver código QR
                    </x-button>

                    {{-- 2. Periodos de evaluación --}}
                    <x-button
                        :href="route('groups.periods.index', $group)"
                        variant="neutral"
                        size="md"
                    >
                        <x-icon name="clock" class="w-4 h-4 mr-1.5" />
                        Periodos
                    </x-button>

                    {{-- 3. Cerrar o Reabrir grupo con SweetAlert2 --}}
                    @if ($group->is_closed)
                        <x-button
                            type="button"
                            @click="confirmReopen()"
                            variant="neutral"
                            size="md"
                        >
                            <x-icon name="check" class="w-4 h-4 mr-1.5 text-acode-accent" />
                            Reabrir grupo
                        </x-button>
                    @else
                        <x-button
                            type="button"
                            @click="confirmClose()"
                            variant="danger"
                            size="md"
                        >
                            <x-icon name="lock" class="w-4 h-4 mr-1.5" />
                            Cerrar grupo
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Formularios POST ocultos para cierre y reapertura semestral --}}
    <form id="group-close-form" method="POST" action="{{ route('groups.close', $group) }}" class="hidden">
        @csrf
    </form>
    <form id="group-reopen-form" method="POST" action="{{ route('groups.reopen', $group) }}" class="hidden">
        @csrf
    </form>

    {{-- Nivel 2: Barra de 4 Tarjetas KPI Contextuales del Grupo --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {{-- KPI 1: Estudiantes matriculados --}}
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="userGroup" class="w-5 h-5 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Estudiantes
                </span>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-2xl lg:text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $group->students->count() }}
                </p>
                <p class="text-xs text-slate-500">
                    Alumnos matriculados
                </p>
            </div>
        </div>

        {{-- KPI 2: Exámenes asignados --}}
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="document" class="w-5 h-5 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Exámenes
                </span>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-2xl lg:text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $group->exams->count() }}
                </p>
                <p class="text-xs text-slate-500">
                    Asignados a este salón
                </p>
            </div>
        </div>

        {{-- KPI 3: Evaluaciones entregadas --}}
        @php
            $submittedCount = $attempts->where('status', 'submitted')->count();
        @endphp
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-accent/15 text-acode-accent">
                    <x-icon name="reportBar" class="w-5 h-5 text-acode-accent" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Entregas
                </span>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-2xl lg:text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $submittedCount }}
                </p>
                <p class="text-xs text-slate-500">
                    De {{ $attempts->count() }} intentos totales
                </p>
            </div>
        </div>

        {{-- KPI 4: Periodos escolares --}}
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="clock" class="w-5 h-5 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Periodos
                </span>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-2xl lg:text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $group->evaluationPeriods->count() }}
                </p>
                <p class="text-xs text-slate-500">
                    Parciales configurados
                </p>
            </div>
        </div>
    </div>

    {{-- Nivel 3: Sistema de Pestañas con Contadores y Transiciones Suaves (ADR-047) --}}
    <div>
        <div class="flex gap-2 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Secciones del grupo">
            {{-- Pestaña 1: Estudiantes --}}
            <button
                type="button"
                role="tab"
                @click="setActiveTab('students')"
                :aria-selected="activeTab === 'students'"
                :class="activeTab === 'students'
                    ? 'border-acode-tech text-acode-primary font-bold'
                    : 'border-transparent text-slate-500 hover:text-acode-primary font-medium'"
                class="inline-flex items-center gap-2 border-b-[3px] px-4 py-3 text-sm transition whitespace-nowrap"
            >
                <x-icon name="userGroup" class="w-4 h-4 text-acode-tech" />
                <span>Estudiantes</span>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="activeTab === 'students' ? 'bg-acode-tech text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ $group->students->count() }}
                </span>
            </button>

            {{-- Pestaña 2: Exámenes --}}
            <button
                type="button"
                role="tab"
                @click="setActiveTab('exams')"
                :aria-selected="activeTab === 'exams'"
                :class="activeTab === 'exams'
                    ? 'border-acode-tech text-acode-primary font-bold'
                    : 'border-transparent text-slate-500 hover:text-acode-primary font-medium'"
                class="inline-flex items-center gap-2 border-b-[3px] px-4 py-3 text-sm transition whitespace-nowrap"
            >
                <x-icon name="document" class="w-4 h-4 text-acode-tech" />
                <span>Exámenes</span>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="activeTab === 'exams' ? 'bg-acode-tech text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ $group->exams->count() }}
                </span>
            </button>

            {{-- Pestaña 3: Calificaciones --}}
            <button
                type="button"
                role="tab"
                @click="setActiveTab('grades')"
                :aria-selected="activeTab === 'grades'"
                :class="activeTab === 'grades'
                    ? 'border-acode-tech text-acode-primary font-bold'
                    : 'border-transparent text-slate-500 hover:text-acode-primary font-medium'"
                class="inline-flex items-center gap-2 border-b-[3px] px-4 py-3 text-sm transition whitespace-nowrap"
            >
                <x-icon name="reportBar" class="w-4 h-4 text-acode-tech" />
                <span>Calificaciones</span>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="activeTab === 'grades' ? 'bg-acode-tech text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ $attempts->count() }}
                </span>
            </button>

            {{-- Pestaña 4: Configuración de evaluación --}}
            <button
                type="button"
                role="tab"
                @click="setActiveTab('evaluation')"
                :aria-selected="activeTab === 'evaluation'"
                :class="activeTab === 'evaluation'
                    ? 'border-acode-tech text-acode-primary font-bold'
                    : 'border-transparent text-slate-500 hover:text-acode-primary font-medium'"
                class="inline-flex items-center gap-2 border-b-[3px] px-4 py-3 text-sm transition whitespace-nowrap"
            >
                <x-icon name="clock" class="w-4 h-4 text-acode-tech" />
                <span>Configuración de evaluación</span>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="activeTab === 'evaluation' ? 'bg-acode-tech text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ $group->evaluationPeriods->count() }}
                </span>
            </button>
        </div>
    </div>

    {{-- Nivel 4A: Apartado 1 - Estudiantes --}}
    <section
        x-show="activeTab === 'students'"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1.5"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1.5"
    >
        <x-card>
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                        <x-icon name="userGroup" class="h-6 w-6 text-acode-tech" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-acode-primary">
                            Censo de Estudiantes
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ $group->students->count() }} integrantes matriculados en este grupo
                        </p>
                    </div>
                </div>

                @if (!$group->students->isEmpty())
                    <div class="w-full sm:max-w-xs relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-icon name="search" class="w-4 h-4" />
                        </div>
                        <input
                            type="search"
                            x-model="studentSearch"
                            placeholder="Buscar estudiante por nombre o correo..."
                            class="h-10 w-full rounded-lg border border-slate-300 pl-9 pr-3 text-sm focus:border-acode-tech focus:outline-none focus:ring-2 focus:ring-acode-tech/20"
                        >
                    </div>
                @endif
            </div>

            @if ($group->students->isEmpty())
                <x-empty-state
                    icon="userGroup"
                    title="Aún no hay estudiantes en este grupo"
                    message="Comparte el código QR o el folio de acceso con tus alumnos para que comiencen a matricularse."
                >
                    <x-button
                        type="button"
                        @click="$dispatch('open-modal', 'group-qr-modal')"
                        variant="primary"
                        size="md"
                    >
                        <x-icon name="qrCode" class="w-4 h-4 mr-1.5" />
                        Ver código de incorporación
                    </x-button>
                </x-empty-state>
            @else
                <x-table>
                    <x-slot:header>
                        <x-table.th>Estudiante</x-table.th>
                        <x-table.th>Correo electrónico</x-table.th>
                        <x-table.th>Fecha de incorporación</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                    </x-slot:header>

                    @foreach ($group->students as $student)
                        <tr
                            x-show="!studentSearch || @js(mb_strtolower($student->full_name . ' ' . $student->email)).includes(studentSearch.toLowerCase().trim())"
                        >
                            {{-- Columna Estudiante con Avatar Vectorial (ADR-049) --}}
                            <x-table.td>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-acode-primary text-sm font-bold text-white shadow-sm">
                                        {{ strtoupper(substr($student->first_name ?? $student->full_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-acode-primary leading-tight">
                                            {{ $student->full_name }}
                                        </p>
                                        <p class="text-xs text-slate-400 mt-0.5 font-mono">
                                            ID #{{ $student->id }}
                                        </p>
                                    </div>
                                </div>
                            </x-table.td>

                            <x-table.td>
                                <a href="mailto:{{ $student->email }}" class="text-xs sm:text-sm font-medium text-acode-tech hover:underline">
                                    {{ $student->email }}
                                </a>
                            </x-table.td>

                            <x-table.td>
                                <span class="text-xs text-slate-600">
                                    {{ $student->pivot->joined_at ? \Carbon\Carbon::parse($student->pivot->joined_at)->format('d/m/Y H:i') : '—' }}
                                </span>
                            </x-table.td>

                            <x-table.td align="center">
                                <x-badge variant="accent">
                                    Inscrito
                                </x-badge>
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </section>

    {{-- Nivel 4B: Apartado 2 - Exámenes --}}
    <section
        x-show="activeTab === 'exams'"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1.5"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1.5"
    >
        <x-card>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-acode-primary">
                        Exámenes asignados ({{ $group->exams->count() }})
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Administra y monitorea las pruebas asignadas a los alumnos de este grupo.
                    </p>
                </div>

                <x-button :href="route('exams.create')" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4 mr-1.5" />
                    Crear examen
                </x-button>
            </div>

            @forelse ($group->exams as $exam)
                <div class="space-y-4 mb-4">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech hover:shadow-md">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-lg text-acode-primary">
                                    {{ $exam->title }}
                                </h3>
                                @if ($exam->description)
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $exam->description }}
                                    </p>
                                @endif
                            </div>

                            <x-badge :variant="$exam->is_published ? 'accent' : 'warning'">
                                {{ $exam->is_published ? 'Publicado' : 'Borrador' }}
                            </x-badge>
                        </div>

                        <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 text-xs sm:grid-cols-3">
                            <div>
                                <p class="text-slate-400 font-semibold uppercase">Inicio programado</p>
                                <p class="mt-1 font-semibold text-slate-700">
                                    {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-400 font-semibold uppercase">Cierre de ventana</p>
                                <p class="mt-1 font-semibold text-slate-700">
                                    {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-400 font-semibold uppercase">Escala evaluativa</p>
                                <p class="mt-1 font-semibold text-slate-700 font-mono">
                                    Escala {{ $exam->grading_scale }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <div class="text-xs text-slate-500">
                                @if ($exam->is_published)
                                    <span class="text-acode-accent font-semibold">Examen publicado en línea.</span> Los estudiantes pueden presentar según la ventana programada.
                                @else
                                    <span class="text-amber-600 font-semibold">Examen en borrador.</span> Puedes continuar preparando los reactivos y apartados.
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <x-button :href="route('exams.assemble', $exam)" variant="neutral" size="sm">
                                    Editar examen
                                </x-button>

                                @if ($exam->is_published)
                                    <x-button :href="route('exams.monitor', $exam)" variant="neutral" size="sm">
                                        Monitoreo
                                    </x-button>
                                @endif

                                @if (($exam->studentAttempts()->where('group_id', $group->id)->count()) > 0)
                                    <x-button :href="route('exams.revision.index', $exam)" variant="primary" size="sm">
                                        Revisar entregas
                                    </x-button>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <x-empty-state
                    icon="document"
                    title="No hay exámenes asignados a este grupo"
                    message="Crea un examen y asígnalo a este salón escolar para comenzar a evaluar a tus estudiantes."
                >
                    <x-button :href="route('exams.create')" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        Crear examen
                    </x-button>
                </x-empty-state>
            @endforelse
        </x-card>
    </section>

    {{-- Nivel 4C: Apartado 3 - Calificaciones --}}
    <section
        x-show="activeTab === 'grades'"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1.5"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1.5"
    >
        <x-card>
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-acode-primary">
                        Calificaciones e Intentos de Examen
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Supervisa los resultados de las evaluaciones presentadas en este grupo.
                    </p>
                </div>

                <x-button :href="route('groups.periods.index', $group)" variant="neutral" size="sm">
                    <x-icon name="reportBar" class="w-4 h-4 mr-1.5" />
                    Ir a Sábana de Notas
                </x-button>
            </div>

            {{-- Resumen de Métricas de Calificaciones --}}
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-acode-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Intentos registrados
                    </p>
                    <p class="mt-2 text-2xl font-extrabold text-acode-primary">
                        {{ $attempts->count() }}
                    </p>
                </div>

                <div class="rounded-xl bg-acode-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Exámenes entregados
                    </p>
                    <p class="mt-2 text-2xl font-extrabold text-acode-primary">
                        {{ $attempts->where('status', 'submitted')->count() }}
                    </p>
                </div>

                <div class="rounded-xl bg-acode-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        En progreso
                    </p>
                    <p class="mt-2 text-2xl font-extrabold text-acode-primary">
                        {{ $attempts->where('status', 'in_progress')->count() }}
                    </p>
                </div>
            </div>

            @if ($attempts->isEmpty())
                <x-empty-state
                    icon="reportBar"
                    title="Aún no hay intentos de examen registrados"
                    message="Los resultados aparecerán cuando los estudiantes comiencen a resolver las pruebas del grupo."
                />
            @else
                {{-- Filtros de Calificaciones --}}
                <div class="mb-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="grade-search" class="mb-1.5 block text-xs font-semibold text-acode-primary">
                            Buscar estudiante o examen
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-icon name="search" class="w-4 h-4" />
                            </div>
                            <input
                                id="grade-search"
                                type="search"
                                x-model="gradeSearch"
                                placeholder="Nombre, correo o título de examen..."
                                class="h-10 w-full rounded-lg border border-slate-300 pl-9 pr-3 text-sm focus:border-acode-tech focus:outline-none focus:ring-2 focus:ring-acode-tech/20"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="grade-status" class="mb-1.5 block text-xs font-semibold text-acode-primary">
                            Estado del intento
                        </label>
                        <select
                            id="grade-status"
                            x-model="gradeStatus"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-acode-tech focus:outline-none focus:ring-2 focus:ring-acode-tech/20"
                        >
                            <option value="all">Todos los estados</option>
                            <option value="submitted">Entregado</option>
                            <option value="in_progress">En progreso</option>
                            <option value="expired">Expirado</option>
                            <option value="cancelled_by_incident">Cancelado por incidente</option>
                        </select>
                    </div>
                </div>

                {{-- Tabla de Intentos --}}
                <x-table>
                    <x-slot:header>
                        <x-table.th>Estudiante</x-table.th>
                        <x-table.th>Examen</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        <x-table.th>Calificación</x-table.th>
                        <x-table.th>Fecha de entrega</x-table.th>
                        <x-table.th align="right">Acciones</x-table.th>
                    </x-slot:header>

                    @foreach ($attempts as $attempt)
                        <tr
                            x-show="
                                (!gradeSearch || @js(mb_strtolower(($attempt->student?->full_name ?? '') . ' ' . ($attempt->student?->email ?? '') . ' ' . ($attempt->exam?->title ?? ''))).includes(gradeSearch.toLowerCase().trim()))
                                &&
                                (gradeStatus === 'all' || gradeStatus === @js($attempt->status))
                            "
                        >
                            <x-table.td>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-acode-primary text-xs font-bold text-white shadow-sm">
                                        {{ strtoupper(substr($attempt->student?->first_name ?? $attempt->student?->full_name ?? 'E', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-acode-primary leading-tight">
                                            {{ $attempt->student?->full_name ?? 'Estudiante no disponible' }}
                                        </p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            {{ $attempt->student?->email ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                            </x-table.td>

                            <x-table.td>
                                <p class="font-semibold text-slate-800 text-xs sm:text-sm">
                                    {{ $attempt->exam?->title ?? 'Examen no disponible' }}
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5 font-mono">
                                    Escala {{ $attempt->exam?->grading_scale ?? '—' }}
                                </p>
                            </x-table.td>

                            <x-table.td align="center">
                                @switch($attempt->status)
                                    @case('submitted')
                                        <x-badge variant="accent">Entregado</x-badge>
                                        @break
                                    @case('in_progress')
                                        <x-badge variant="tech">En progreso</x-badge>
                                        @break
                                    @case('expired')
                                        <x-badge variant="neutral">Expirado</x-badge>
                                        @break
                                    @case('cancelled_by_incident')
                                        <x-badge variant="danger">Cancelado</x-badge>
                                        @break
                                @endswitch
                            </x-table.td>

                            <x-table.td>
                                @if ($attempt->status === 'submitted' && $attempt->final_score !== null)
                                    <p class="font-mono font-bold text-acode-primary text-sm">
                                        {{ number_format((float) $attempt->final_score, 2) }}
                                        <span class="text-xs font-normal text-slate-400">/ {{ $attempt->exam?->grading_scale ?? '—' }}</span>
                                    </p>
                                @elseif ($attempt->status === 'submitted')
                                    <span class="text-xs text-slate-500 italic">Pendiente de cálculo</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </x-table.td>

                            <x-table.td>
                                <span class="text-xs text-slate-600">
                                    {{ $attempt->submitted_at?->format('d/m/Y H:i') ?? '—' }}
                                </span>
                            </x-table.td>

                            <x-table.td align="right">
                                @if ($attempt->status === 'submitted' && $attempt->exam)
                                    <x-button
                                        :href="route('exams.revision.show', [$attempt->exam, $attempt])"
                                        variant="neutral"
                                        size="sm"
                                    >
                                        Auditar intento
                                    </x-button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </section>

    {{-- Nivel 4D: Apartado 4 - Configuración de Evaluación (Periodos y Fórmulas) --}}
    <section
        x-show="activeTab === 'evaluation'"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1.5"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1.5"
    >
        <x-card>
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-acode-primary">
                        Configuración de Periodos y Ponderaciones
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Consulta y gestiona los parciales escolares, ponderaciones porcentuales y sábanas de notas.
                    </p>
                </div>

                <x-button :href="route('groups.periods.index', $group)" variant="primary" size="md">
                    <x-icon name="clock" class="w-4 h-4 mr-1.5" />
                    Gestionar periodos y ponderaciones
                </x-button>
            </div>

            @forelse ($group->evaluationPeriods->sortBy('starts_at') as $period)
                @php
                    $weights = $period->parsed_weights;
                    $totalIs100 = $period->is_valid_formula;
                @endphp
                <div class="space-y-4 mb-4">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-acode-primary">
                                        {{ $period->name }}
                                    </h3>
                                    @if ($period->is_active_today)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-acode-accent">
                                            <span class="h-1.5 w-1.5 rounded-full bg-acode-accent"></span>
                                            Activo hoy
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    Vigencia: {{ $period->starts_at?->format('d/m/Y') ?? 'Sin inicio' }} al {{ $period->ends_at?->format('d/m/Y') ?? 'Sin cierre' }}
                                </p>
                            </div>

                            @if ($totalIs100)
                                <x-badge variant="accent">
                                    Ponderación: 100 %
                                </x-badge>
                            @else
                                <x-badge variant="warning">
                                    Ponderación por revisar
                                </x-badge>
                            @endif
                        </div>

                        {{-- Distribución de ponderaciones --}}
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">
                                Distribución de rubros evaluativos
                            </h4>

                            @if (!empty($weights))
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div class="rounded-lg bg-blue-50/70 border border-blue-200/50 p-3">
                                        <p class="text-xs font-semibold text-acode-tech">Exámenes</p>
                                        <p class="mt-1 text-lg font-extrabold text-acode-primary">
                                            {{ number_format($weights['exams'] ?? 0, 1) }} %
                                        </p>
                                    </div>

                                    <div class="rounded-lg bg-teal-50/70 border border-teal-200/50 p-3">
                                        <p class="text-xs font-semibold text-teal-700">Tareas</p>
                                        <p class="mt-1 text-lg font-extrabold text-acode-primary">
                                            {{ number_format($weights['homework'] ?? 0, 1) }} %
                                        </p>
                                    </div>

                                    <div class="rounded-lg bg-amber-50/70 border border-amber-200/50 p-3">
                                        <p class="text-xs font-semibold text-amber-800">Proyectos</p>
                                        <p class="mt-1 text-lg font-extrabold text-acode-primary">
                                            {{ number_format($weights['project'] ?? 0, 1) }} %
                                        </p>
                                    </div>

                                    <div class="rounded-lg bg-slate-100 border border-slate-200 p-3">
                                        <p class="text-xs font-semibold text-slate-700">Otros</p>
                                        <p class="mt-1 text-lg font-extrabold text-acode-primary">
                                            {{ number_format($weights['other'] ?? 0, 1) }} %
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-lg bg-acode-surface p-3 font-mono text-xs text-slate-600">
                                    Fórmula: {{ $period->formula }}
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <span class="text-xs text-slate-500">
                                Ponderación validada y respaldada en base de datos.
                            </span>

                            <x-button :href="route('groups.periods.gradebook', [$group, $period])" variant="neutral" size="sm">
                                <x-icon name="document" class="w-4 h-4 mr-1.5" />
                                Abrir sábana de notas
                            </x-button>
                        </div>
                    </article>
                </div>
            @empty
                <x-empty-state
                    icon="clock"
                    title="No hay periodos de evaluación registrados"
                    message="Configura el primer parcial del grupo escolar para definir las ponderaciones de exámenes, tareas, proyectos y otros rubros."
                >
                    <x-button :href="route('groups.periods.index', $group)" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        Crear primer periodo
                    </x-button>
                </x-empty-state>
            @endforelse
        </x-card>
    </section>

    {{-- Nivel 5: Modal de Código QR Institucional Oficial --}}
    <x-group.qr-modal
        name="group-qr-modal"
        :groupName="$group->name"
        :folio="$group->access_folio"
    />

</div>
@endsection
