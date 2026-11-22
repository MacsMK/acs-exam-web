{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/show.blade.php
    Ubicación:   resources/views/groups/show.blade.php
    Descripción: Vista operativa detallada de grupo escolar y sus 4 apartados
                 (Estudiantes, Exámenes, Calificaciones y Periodos de Evaluación)
                 homologada con componentes canónicos A-Code Solutions, cero N+1
                 y tokens oficiales acode-* (MPW-V3 §7.3, §7.4.2, §7.8.2, §8.1–§8.12).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', $group->name . ' · Detalle del grupo')

@section('content')
@php
    $submittedCount = $attempts->where('status', 'submitted')->count();
    $inProgressCount = $attempts->where('status', 'in_progress')->count();
    $studentsCount = $group->students->count();
    $sortedPeriods = $group->evaluationPeriods->sortBy('starts_at');
@endphp

<div
    x-data="{
        activeTab: window.location.hash ? window.location.hash.substring(1) : 'students',
        studentSearch: '',
        gradeSearch: '',
        gradeStatus: 'all',

        studentsIndex: @js(
            $group->students->map(fn ($student) => [
                'id' => $student->id,
                'haystack' => mb_strtolower($student->full_name . ' ' . $student->email),
            ])->values()
        ),

        attemptsIndex: @js(
            $attempts->map(fn ($attempt) => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'haystack' => mb_strtolower(
                    ($attempt->student?->full_name ?? '') . ' ' .
                    ($attempt->student?->email ?? '') . ' ' .
                    ($attempt->exam?->title ?? '')
                ),
            ])->values()
        ),

        get filteredStudents() {
            const term = this.studentSearch.toLowerCase().trim();
            return this.studentsIndex.filter(s => !term || s.haystack.includes(term));
        },

        get filteredAttempts() {
            const term = this.gradeSearch.toLowerCase().trim();
            return this.attemptsIndex.filter(a =>
                (!term || a.haystack.includes(term)) &&
                (this.gradeStatus === 'all' || this.gradeStatus === a.status)
            );
        },

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

    {{-- Nivel 1: Encabezado Institucional con Breadcrumbs, Folio Copiable y Acciones --}}
    <x-page-header
        :title="$group->name"
        :subtitle="$group->description
            ?: ($group->subject
                ? 'Gestiona matrícula, exámenes asignados, calificaciones y periodos de evaluación de ' . $group->subject . '.'
                : 'Gestiona matrícula, exámenes asignados, calificaciones y periodos de evaluación de este grupo escolar.')"
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name],
        ]"
    >
        <x-slot:badge>
            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
            </x-badge>
        </x-slot:badge>

        <x-slot:meta>
            <span class="inline-flex items-center gap-1.5 font-medium text-acode-tech">
                <x-icon name="tag" class="w-3.5 h-3.5" />
                {{ $group->subject ?? 'Sin asignatura especificada' }}
            </span>

            <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-acode-surface/70 px-2.5 py-1">
                <span class="font-semibold text-slate-500">Folio:</span>
                <span class="font-mono font-bold text-acode-primary select-all">{{ $group->access_folio }}</span>
                <button
                    type="button"
                    title="Copiar folio"
                    aria-label="Copiar folio"
                    x-on:click="copyFolio()"
                    class="inline-flex h-5 w-5 items-center justify-center rounded text-slate-500 transition hover:bg-white hover:text-acode-tech focus:outline-none focus:ring-2 focus:ring-acode-tech"
                >
                    <x-icon name="copy" class="w-3.5 h-3.5" />
                </button>
            </span>

            <span class="inline-flex items-center gap-1.5 text-slate-600">
                <x-icon name="userGroup" class="w-3.5 h-3.5 text-acode-tech" />
                <strong class="text-acode-primary">{{ $studentsCount }}</strong>
                {{ $studentsCount === 1 ? 'estudiante inscrito' : 'estudiantes inscritos' }}
            </span>
        </x-slot:meta>

        <x-slot:actions>
            <x-button :href="route('groups.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>

            <x-button
                type="button"
                @click="$dispatch('open-modal', 'group-qr-modal')"
                variant="secondary"
                size="md"
            >
                <x-icon name="qrCode" class="w-4 h-4" />
                Abrir QR
            </x-button>

            <x-button :href="route('groups.periods.index', $group)" variant="neutral" size="md">
                <x-icon name="clock" class="w-4 h-4" />
                Abrir periodos
            </x-button>

            @unless ($group->is_closed)
                <x-button :href="route('groups.edit', $group)" variant="neutral" size="md">
                    <x-icon name="edit" class="w-4 h-4" />
                    Editar grupo
                </x-button>
            @endunless

            @if ($group->is_closed)
                <x-button
                    type="button"
                    @click="confirmReopen()"
                    variant="accent"
                    size="md"
                >
                    <x-icon name="check" class="w-4 h-4" />
                    Reabrir grupo
                </x-button>
            @else
                <x-button
                    type="button"
                    @click="confirmClose()"
                    variant="danger"
                    size="md"
                >
                    Cerrar grupo
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Aviso de eliminación automática del grupo cerrado (RF-GRU-015, CAM-009) --}}
    @if ($deletionDate)
        <div class="flex items-start gap-3 rounded-xl border border-acode-warning/40 bg-acode-warning/10 px-4 py-3.5 text-acode-primary" role="status">
            <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
            <p class="text-sm leading-relaxed">
                Este grupo está cerrado y se eliminará automáticamente el <strong>{{ $deletionDate->format('d/m/Y') }}</strong>.
                Antes de esa fecha puedes exportar sus calificaciones desde <strong>Abrir periodos</strong> o reabrirlo para conservarlo.
            </p>
        </div>
    @endif

    {{-- Formularios POST ocultos para cierre y reapertura semestral --}}
    <form id="group-close-form" method="POST" action="{{ route('groups.close', $group) }}" class="hidden">
        @csrf
    </form>
    <form id="group-reopen-form" method="POST" action="{{ route('groups.reopen', $group) }}" class="hidden">
        @csrf
    </form>

    {{-- Nivel 2: Barra de 4 Tarjetas KPI Canónicas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card
            label="Estudiantes inscritos"
            :value="$studentsCount"
            meta="Alumnos matriculados"
            nav-icon="groups"
            tone="tech"
        />

        <x-kpi-card
            label="Exámenes asignados"
            :value="$group->exams->count()"
            meta="Pruebas vinculadas al grupo"
            nav-icon="exams"
            tone="accent"
        />

        <x-kpi-card
            label="Entregas registradas"
            :value="$submittedCount"
            :meta="'De ' . $attempts->count() . ' intentos totales'"
            icon="reportBar"
            tone="warning"
        />

        <x-kpi-card
            label="Periodos de evaluación"
            :value="$group->evaluationPeriods->count()"
            meta="Parciales configurados"
            icon="clock"
            tone="primary"
        />
    </div>

    {{-- Nivel 3: Sistema de Pestañas --}}
    <div>
        <div class="flex gap-2 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Secciones del grupo">
            <x-tab
                name="students"
                label="Estudiantes"
                icon="userGroup"
                :count="$studentsCount"
                select="setActiveTab('students')"
            />

            <x-tab
                name="exams"
                label="Exámenes"
                icon="document"
                :count="$group->exams->count()"
                select="setActiveTab('exams')"
            />

            <x-tab
                name="grades"
                label="Calificaciones"
                icon="reportBar"
                :count="$attempts->count()"
                select="setActiveTab('grades')"
            />

            <x-tab
                name="evaluation"
                label="Configuración de evaluación"
                icon="clock"
                :count="$group->evaluationPeriods->count()"
                select="setActiveTab('evaluation')"
            />
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
        class="space-y-4"
    >
        <x-section-header
            title="Censo de estudiantes"
            :subtitle="$studentsCount . ' ' . ($studentsCount === 1 ? 'integrante matriculado' : 'integrantes matriculados') . ' en este grupo.'"
        >
            @if ($group->students->isNotEmpty())
                <x-slot:actions>
                    <div class="w-full sm:w-72">
                        <x-form.input
                            type="search"
                            prefixIcon="search"
                            x-model="studentSearch"
                            placeholder="Buscar por nombre o correo..."
                            aria-label="Buscar estudiante"
                        />
                    </div>
                </x-slot:actions>
            @endif
        </x-section-header>

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
                    <x-icon name="qrCode" class="w-4 h-4" />
                    Abrir QR
                </x-button>
            </x-empty-state>
        @else
            <div x-show="filteredStudents.length > 0">
                <x-table>
                    <x-slot:header>
                        <x-table.th>Estudiante</x-table.th>
                        <x-table.th>Correo electrónico</x-table.th>
                        <x-table.th>Fecha de incorporación</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        @unless ($group->is_closed)
                            <x-table.th align="right">Acciones</x-table.th>
                        @endunless
                    </x-slot:header>

                    @forelse ($group->students as $student)
                        <tr
                            x-show="!studentSearch.trim() || @js(mb_strtolower($student->full_name . ' ' . $student->email)).includes(studentSearch.toLowerCase().trim())"
                        >
                            <x-table.td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$student" />
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

                            @unless ($group->is_closed)
                                {{-- Retiro del estudiante con confirmación (RF-GRU-011, RF-GRU-016, CAM-008) --}}
                                <x-table.td align="right">
                                    <form
                                        method="POST"
                                        action="{{ route('groups.students.remove', [$group, $student]) }}"
                                        x-on:submit.prevent="window.AcodeAlert.confirm({
                                            title: '¿Retirar a este estudiante?',
                                            text: @js($student->full_name . ' perderá su pertenencia al grupo y el acceso a sus recursos y resultados. Sus registros académicos se conservarán. No se puede retirar si tiene un examen en curso.'),
                                            confirmButtonText: 'Retirar estudiante',
                                            cancelButtonText: 'Cancelar',
                                            isDanger: true,
                                            onConfirm: () => $el.submit(),
                                        })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm" class="whitespace-nowrap">
                                            Retirar
                                        </x-button>
                                    </form>
                                </x-table.td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state
                                    class="border-0 shadow-none rounded-none"
                                    icon="userGroup"
                                    title="Sin estudiantes registrados"
                                    message="Aún no hay alumnos inscritos en este grupo."
                                />
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            </div>

            <div x-show="filteredStudents.length === 0" x-cloak>
                <x-empty-state
                    icon="search"
                    title="No se encontraron estudiantes"
                    message="Ningún estudiante coincide con el criterio de búsqueda ingresado."
                >
                    <x-button type="button" variant="neutral" size="sm" @click="studentSearch = ''">
                        Limpiar búsqueda
                    </x-button>
                </x-empty-state>
            </div>
        @endif
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
        class="space-y-4"
    >
        <x-section-header
            :title="'Exámenes asignados (' . $group->exams->count() . ')'"
            subtitle="Administra y monitorea las pruebas asignadas a los alumnos de este grupo."
        >
            <x-slot:actions>
                <x-button :href="route('exams.create')" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4" />
                    Crear examen
                </x-button>
            </x-slot:actions>
        </x-section-header>

        <div class="space-y-4">
            @forelse ($group->exams as $exam)
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech/50 hover:shadow-md">
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
                            <p class="text-slate-500 font-semibold">Inicio programado</p>
                            <p class="mt-1 font-semibold text-slate-700">
                                {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-semibold">Cierre de ventana</p>
                            <p class="mt-1 font-semibold text-slate-700">
                                {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-semibold">Escala evaluativa</p>
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
                                <span class="text-acode-warning-dark font-semibold">Examen en borrador.</span> Puedes continuar preparando los reactivos y apartados.
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-button :href="route('exams.assemble', $exam)" variant="neutral" size="sm">
                                Editar examen
                            </x-button>

                            @if ($exam->is_published)
                                <x-button :href="route('exams.monitor', $exam)" variant="neutral" size="sm">
                                    Abrir monitoreo
                                </x-button>
                            @endif

                            @if (($exam->group_attempts_count ?? 0) > 0)
                                <x-button :href="route('exams.revision.index', $exam)" variant="primary" size="sm">
                                    Abrir recoteje
                                </x-button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <x-empty-state
                    icon="document"
                    title="No hay exámenes asignados a este grupo"
                    message="Crea un examen y asígnalo a este salón escolar para comenzar a evaluar a tus estudiantes."
                >
                    <x-button :href="route('exams.create')" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4" />
                        Crear examen
                    </x-button>
                </x-empty-state>
            @endforelse
        </div>
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
        class="space-y-4"
    >
        <x-section-header
            title="Calificaciones e intentos de examen"
            subtitle="Supervisa los resultados de las evaluaciones presentadas en este grupo."
        >
            <x-slot:actions>
                <x-button :href="route('groups.periods.index', $group)" variant="neutral" size="md">
                    <x-icon name="reportBar" class="w-4 h-4" />
                    Abrir periodos
                </x-button>
            </x-slot:actions>
        </x-section-header>

        {{-- Resumen de métricas de entregas --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-kpi-card
                label="Intentos registrados"
                :value="$attempts->count()"
                icon="document"
                tone="tech"
            />

            <x-kpi-card
                label="Exámenes entregados"
                :value="$submittedCount"
                icon="success"
                tone="accent"
            />

            <x-kpi-card
                label="En progreso"
                :value="$inProgressCount"
                icon="clock"
                tone="warning"
            />
        </div>

        @if ($attempts->isEmpty())
            <x-empty-state
                icon="reportBar"
                title="Aún no hay intentos de examen registrados"
                message="Los resultados aparecerán cuando los estudiantes comiencen a resolver las pruebas del grupo."
            />
        @else
            {{-- Filtros de Calificaciones --}}
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-form.group name="grade-search" label="Buscar estudiante o examen">
                        <x-form.input
                            id="grade-search"
                            type="search"
                            prefixIcon="search"
                            x-model="gradeSearch"
                            placeholder="Nombre, correo o título de examen..."
                        />
                    </x-form.group>

                    <x-form.group name="grade-status" label="Estado del intento">
                        <x-form.select
                            id="grade-status"
                            x-model="gradeStatus"
                        >
                            <option value="all">Todos los estados</option>
                            <option value="submitted">Entregado</option>
                            <option value="in_progress">En progreso</option>
                            <option value="expired">Expirado</option>
                            <option value="cancelled_by_incident">Cancelado por incidente</option>
                        </x-form.select>
                    </x-form.group>
                </div>
            </div>

            {{-- Tabla de Intentos --}}
            <div x-show="filteredAttempts.length > 0">
                <x-table>
                    <x-slot:header>
                        <x-table.th>Estudiante</x-table.th>
                        <x-table.th>Examen</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        <x-table.th>Calificación</x-table.th>
                        <x-table.th>Fecha de entrega</x-table.th>
                        <x-table.th align="right">Acciones</x-table.th>
                    </x-slot:header>

                    @forelse ($attempts as $attempt)
                        <tr
                            x-show="
                                (!gradeSearch.trim() || @js(mb_strtolower(($attempt->student?->full_name ?? '') . ' ' . ($attempt->student?->email ?? '') . ' ' . ($attempt->exam?->title ?? ''))).includes(gradeSearch.toLowerCase().trim()))
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
                                        Abrir detalles
                                    </x-button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </x-table.td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state
                                    class="border-0 shadow-none rounded-none"
                                    icon="reportBar"
                                    title="Sin intentos registrados"
                                    message="Aún no existen entregas en este grupo."
                                />
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            </div>

            <div x-show="filteredAttempts.length === 0" x-cloak>
                <x-empty-state
                    icon="search"
                    title="No se encontraron intentos"
                    message="Ningún intento coincide con la búsqueda o el estado seleccionado."
                >
                    <x-button type="button" variant="neutral" size="sm" @click="gradeSearch = ''; gradeStatus = 'all'">
                        Limpiar filtros
                    </x-button>
                </x-empty-state>
            </div>
        @endif
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
        class="space-y-4"
    >
        <x-section-header
            title="Periodos y ponderaciones"
            subtitle="Consulta y gestiona los parciales escolares, ponderaciones porcentuales y sábanas de notas."
        >
            <x-slot:actions>
                <x-button :href="route('groups.periods.index', $group)" variant="primary" size="md">
                    <x-icon name="clock" class="w-4 h-4" />
                    Abrir periodos
                </x-button>
            </x-slot:actions>
        </x-section-header>

        <div class="space-y-4">
            @forelse ($sortedPeriods as $period)
                @php
                    $weights = $period->parsed_weights;
                    $totalIs100 = $period->is_valid_formula;
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech/50">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-bold text-acode-primary">
                                    {{ $period->name }}
                                </h3>
                                @if ($period->is_active_today)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-acode-accent">
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

                    {{-- Distribución de ponderaciones con tokens acode-* --}}
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <h4 class="mb-3 text-xs font-semibold text-slate-500">
                            Distribución de rubros evaluativos
                        </h4>

                        @if (!empty($weights))
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div class="rounded-lg border border-acode-tech/25 bg-acode-tech/10 p-3">
                                    <p class="text-xs font-semibold text-acode-tech">Exámenes</p>
                                    <p class="mt-1 text-lg font-extrabold text-acode-primary font-mono">
                                        {{ number_format($weights['exams'] ?? 0, 1) }} %
                                    </p>
                                </div>

                                <div class="rounded-lg border border-acode-accent/30 bg-acode-accent/10 p-3">
                                    <p class="text-xs font-semibold text-acode-accent-dark">Tareas</p>
                                    <p class="mt-1 text-lg font-extrabold text-acode-primary font-mono">
                                        {{ number_format($weights['homework'] ?? 0, 1) }} %
                                    </p>
                                </div>

                                <div class="rounded-lg border border-acode-warning/40 bg-acode-warning/15 p-3">
                                    <p class="text-xs font-semibold text-acode-warning-dark">Proyectos</p>
                                    <p class="mt-1 text-lg font-extrabold text-acode-primary font-mono">
                                        {{ number_format($weights['project'] ?? 0, 1) }} %
                                    </p>
                                </div>

                                <div class="rounded-lg border border-slate-200 bg-slate-100 p-3">
                                    <p class="text-xs font-semibold text-slate-700">Otros</p>
                                    <p class="mt-1 text-lg font-extrabold text-acode-primary font-mono">
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
                            <x-icon name="document" class="w-4 h-4" />
                            Abrir calificaciones
                        </x-button>
                    </div>
                </article>
            @empty
                <x-empty-state
                    icon="clock"
                    title="No hay periodos de evaluación registrados"
                    message="Configura el primer parcial del grupo escolar para definir las ponderaciones de exámenes, tareas, proyectos y otros rubros."
                >
                    <x-button :href="route('groups.periods.index', $group)" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4" />
                        Crear periodo
                    </x-button>
                </x-empty-state>
            @endforelse
        </div>
    </section>

    {{-- Nivel 5: Modal de Código QR Institucional Oficial --}}
    <x-group.qr-modal
        name="group-qr-modal"
        :groupName="$group->name"
        :folio="$group->access_folio"
    />

</div>
@endsection
