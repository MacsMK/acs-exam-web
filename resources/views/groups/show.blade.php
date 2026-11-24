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
    $submittedCount = $currentEnrolledAttempts->where('status', 'submitted')->count();
    $inProgressCount = $currentEnrolledAttempts->where('status', 'in_progress')->count();
    $studentsCount = $group->students->count();
    $sortedPeriods = $group->evaluationPeriods->sortBy('starts_at');
    $catalogUrl = $catalogQuery === null ? null : route('teacher.gradebook.hub', $catalogQuery);
    $periodsUrl = route('groups.periods.index', [$group, ...$catalogContext]);
@endphp

<div
    x-data="{
        activeTab: 'students',
        tabs: ['students', 'exams', 'grades', 'evaluation'],
        studentSearch: '',
        gradeSearch: '',
        gradeStatus: 'all',
        copyStatus: '',
        actionError: '',

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

        syncTabFromHash() {
            const tab = window.location.hash.slice(1);
            this.activeTab = this.tabs.includes(tab) ? tab : 'students';
        },

        setActiveTab(tab) {
            this.activeTab = tab;
            window.location.hash = tab;
        },

        moveTab(event) {
            const index = this.tabs.indexOf(this.activeTab);
            const targets = {
                ArrowLeft: (index + this.tabs.length - 1) % this.tabs.length,
                ArrowRight: (index + 1) % this.tabs.length,
                Home: 0,
                End: this.tabs.length - 1,
            };
            const target = targets[event.key];
            if (target === undefined) return;
            event.preventDefault();
            const tab = this.tabs[target];
            this.setActiveTab(tab);
            this.$nextTick(() => document.getElementById('group-tab-' + tab)?.focus());
        },

        async copyFolio() {
            this.copyStatus = '';
            const copied = await window.copyGroupFolio?.(@js($group->access_folio));
            this.copyStatus = copied ? 'Folio copiado' : 'No se pudo copiar el folio. Selecciónalo para copiarlo manualmente.';
        },

        confirmGroupAction(form, {title, text, confirmButtonText, isDanger}) {
            this.actionError = '';
            if (typeof window.AcodeAlert?.confirm !== 'function') {
                this.actionError = 'No se pudo abrir la confirmación. Recarga la página e inténtalo de nuevo.';
                return;
            }

            window.AcodeAlert.confirm({
                title,
                text,
                confirmButtonText,
                cancelButtonText: 'Cancelar',
                isDanger,
                onConfirm: () => form.submit(),
            });
        }
    }"
    x-init="syncTabFromHash()"
    @hashchange.window="syncTabFromHash()"
    class="space-y-6"
>

    <p x-cloak x-show="actionError" x-text="actionError" role="alert"
       class="rounded-lg border border-acode-danger/30 bg-white px-4 py-3 text-sm text-acode-danger"></p>

    {{-- Nivel 1: Encabezado Institucional con Breadcrumbs, Folio Copiable y Acciones --}}
    <x-page-header
        :institutional="true"
        :title="$group->name"
        :subtitle="$group->description"
        :breadcrumbs="[
            ['label' => $catalogUrl ? 'Calificaciones' : 'Grupos', 'href' => $catalogUrl ?? route('groups.index')],
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
                <span class="font-semibold text-slate-700">Folio:</span>
                <span class="font-mono font-bold text-acode-primary select-all">{{ $group->access_folio }}</span>
                @unless ($group->is_closed)
                    <x-button type="button" variant="neutral" size="sm" :icon-only="true"
                              title="Copiar folio" aria-label="Copiar folio" @click="copyFolio()">
                        <x-icon name="copy" class="w-3.5 h-3.5" />
                    </x-button>
                @endunless
            </span>
            <span x-cloak x-show="copyStatus" x-text="copyStatus" role="status" class="text-sm text-slate-700"></span>
        </x-slot:meta>

        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                @if ($catalogUrl)
                    <x-button :href="$catalogUrl" variant="neutral" size="md">
                        <x-icon name="arrowLeft" class="w-4 h-4" />
                        Volver a calificaciones
                    </x-button>
                @endif
                <x-button :href="$periodsUrl" :variant="$group->is_closed ? 'secondary' : 'primary'" size="md">
                    <x-icon name="clock" class="w-4 h-4" />
                    Abrir periodos
                </x-button>

                @unless ($group->is_closed)
                    <x-button type="button" @click="$dispatch('open-modal', 'group-qr-modal')" variant="secondary" size="md"
                              aria-haspopup="dialog">
                        <x-icon name="qrCode" class="w-4 h-4" />
                        Ver código QR
                    </x-button>
                @endunless

                @if ($group->is_closed)
                    <x-button
                        type="button"
                        @click="confirmGroupAction($refs.lifecycleForm, {
                            title: '¿Reabrir este grupo?',
                            text: 'El grupo volverá a admitir nuevas inscripciones y exámenes. Se cancelará su eliminación programada. Para reabrirlo necesitas cupo en tu plan.',
                            confirmButtonText: 'Reabrir grupo',
                            isDanger: false
                        })"
                        variant="primary"
                        size="md"
                        aria-haspopup="dialog"
                    >
                        <x-icon name="check" class="w-4 h-4" />
                        Reabrir grupo
                    </x-button>
                @endif
            </div>

            @unless ($group->is_closed)
                <div role="group" aria-label="Administración del grupo"
                     class="flex w-full flex-wrap items-center gap-2 border-t border-slate-200 pt-2 sm:w-auto sm:border-l sm:border-t-0 sm:pl-3 sm:pt-0">
                    <x-button :href="route('groups.edit', $group)" variant="neutral" size="sm">
                        <x-icon name="edit" class="w-3.5 h-3.5" />
                        Editar grupo
                    </x-button>
                    <x-button
                        type="button"
                        @click="confirmGroupAction($refs.lifecycleForm, {
                            title: '¿Cerrar este grupo escolar?',
                            text: 'El grupo dejará de aceptar inscripciones y nuevos intentos. Sus registros se conservarán durante {{ \App\Services\GroupRetentionService::RETENTION_MONTHS }} meses. Antes de su eliminación puedes exportar las calificaciones o reabrir el grupo.',
                            confirmButtonText: 'Cerrar grupo',
                            isDanger: true
                        })"
                        variant="danger"
                        size="sm"
                        aria-haspopup="dialog"
                    >
                        Cerrar grupo
                    </x-button>
                </div>
            @endunless
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

    <form x-ref="lifecycleForm" method="POST"
          action="{{ route($group->is_closed ? 'groups.reopen' : 'groups.close', $group) }}" class="hidden">
        @csrf
    </form>

    {{-- Pestañas para consultar el trabajo del grupo --}}
    <div>
        <div class="flex gap-2 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Secciones del grupo"
             @keydown="moveTab($event)">
            <x-tab
                name="students"
                id="group-tab-students"
                aria-controls="group-panel-students"
                x-bind:tabindex="activeTab === 'students' ? 0 : -1"
                label="Estudiantes"
                icon="userGroup"
                :count="$studentsCount"
                select="setActiveTab('students')"
            />

            <x-tab
                name="exams"
                id="group-tab-exams"
                aria-controls="group-panel-exams"
                x-bind:tabindex="activeTab === 'exams' ? 0 : -1"
                label="Exámenes"
                icon="document"
                :count="$group->exams->count()"
                select="setActiveTab('exams')"
            />

            <x-tab
                name="grades"
                id="group-tab-grades"
                aria-controls="group-panel-grades"
                x-bind:tabindex="activeTab === 'grades' ? 0 : -1"
                label="Calificaciones"
                icon="reportBar"
                select="setActiveTab('grades')"
            />

            <x-tab
                name="evaluation"
                id="group-tab-evaluation"
                aria-controls="group-panel-evaluation"
                x-bind:tabindex="activeTab === 'evaluation' ? 0 : -1"
                label="Configuración de evaluación"
                icon="clock"
                :count="$group->evaluationPeriods->count()"
                select="setActiveTab('evaluation')"
            />
        </div>
    </div>

    {{-- Nivel 4A: Apartado 1 - Estudiantes --}}
    <section
        id="group-panel-students"
        role="tabpanel"
        aria-labelledby="group-tab-students"
        tabindex="0"
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
        <x-section-header :institutional="true" title="Matrícula" />

        @if ($group->students->isEmpty())
            <x-empty-state
                icon="userGroup"
                title="Aún no hay estudiantes en este grupo"
                :message="$group->is_closed
                    ? 'El grupo está cerrado. Reábrelo para admitir inscripciones.'
                    : 'Comparte el código QR o el folio de acceso para inscribir estudiantes.'"
            />
        @else
            <form role="search" aria-label="Matrícula" x-on:submit.prevent="studentSearch = $refs.studentSearchInput.value">
                <x-search-panel>
                    <x-slot:field>
                        <x-form.input
                            type="search"
                            prefix-icon="search"
                            enterkeyhint="search"
                            x-ref="studentSearchInput"
                            x-model.debounce.300ms="studentSearch"
                            placeholder="Buscar por nombre o correo…"
                            aria-label="Buscar estudiante"
                        />
                    </x-slot:field>
                    <x-slot:actions>
                        <x-button
                            type="button" variant="secondary" x-show="studentSearch.trim() !== ''" x-cloak
                            x-on:click="studentSearch = ''; $refs.studentSearchInput.focus()"
                        >
                            Limpiar búsqueda
                        </x-button>
                    </x-slot:actions>
                </x-search-panel>
            </form>

            <div x-show="filteredStudents.length > 0">
                <x-table zebra="slate" scroll-label="Estudiantes inscritos">
                    <x-slot:header>
                        <x-table.th align="center">Estudiante</x-table.th>
                        <x-table.th align="center">Correo electrónico</x-table.th>
                        <x-table.th align="center">Fecha de incorporación</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        @unless ($group->is_closed)
                            <x-table.th align="center">Acciones</x-table.th>
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
                                        <p class="text-xs text-slate-500 mt-0.5 font-mono">
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
                                        data-remove-message="{{ $student->full_name }} perderá su pertenencia al grupo y el acceso a sus recursos y resultados. Sus registros académicos se conservarán. No se puede retirar si tiene un examen en curso."
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-button
                                            type="button"
                                            variant="neutral"
                                            size="sm"
                                            class="whitespace-nowrap"
                                            :aria-label="'Retirar a ' . $student->full_name"
                                            aria-haspopup="dialog"
                                            @click="confirmGroupAction($el.form, {
                                                title: '¿Retirar a este estudiante?',
                                                text: $el.form.dataset.removeMessage,
                                                confirmButtonText: 'Retirar estudiante',
                                                isDanger: true
                                            })"
                                        >
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
                    <x-button type="button" variant="neutral" size="sm" @click="studentSearch = ''; $refs.studentSearchInput.focus()">
                        Limpiar búsqueda
                    </x-button>
                </x-empty-state>
            </div>
        @endif
    </section>

    {{-- Nivel 4B: Apartado 2 - Exámenes --}}
    <section
        id="group-panel-exams"
        role="tabpanel"
        aria-labelledby="group-tab-exams"
        tabindex="0"
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
            :institutional="true"
            :title="'Exámenes asignados (' . $group->exams->count() . ')'"
            :subtitle="$group->is_closed ? 'Consulta los exámenes que se asignaron a este grupo.' : null"
        >
            @unless ($group->is_closed)
                <x-slot:actions>
                    <x-button :href="route('exams.create', ['group_id' => $group->id])" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4" />
                        Crear examen
                    </x-button>
                </x-slot:actions>
            @endunless
        </x-section-header>

        <div class="space-y-4">
            @forelse ($group->exams as $exam)
                @php
                    $examState = match ($exam->operationalStatus()) {
                        \App\Models\Exam::STATUS_ACTIVE => ['label' => 'Activo', 'variant' => 'accent'],
                        \App\Models\Exam::STATUS_SCHEDULED => ['label' => 'Programado', 'variant' => 'tech'],
                        \App\Models\Exam::STATUS_CLOSED => ['label' => 'Cerrado', 'variant' => 'neutral'],
                        default => ['label' => 'Borrador', 'variant' => 'warning'],
                    };
                    $canEditExam = ! $group->is_closed && $exam->supportsStructuralEdit();
                @endphp
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

                        <x-badge :variant="$examState['variant']">
                            {{ $examState['label'] }}
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
                            @if ($group->is_closed)
                                Esta aplicación permanece disponible para consulta.
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($canEditExam)
                                <x-button :href="route('exams.assemble', $exam)" variant="neutral" size="sm">
                                    Armar examen
                                </x-button>
                            @else
                                <x-button :href="route('exams.review', $exam)" variant="neutral" size="sm">
                                    Abrir resumen
                                </x-button>
                            @endif

                            @if ($exam->is_published)
                                <x-button :href="route('exams.monitor', $exam)" variant="neutral" size="sm">
                                    Abrir monitoreo
                                </x-button>
                            @endif

                            @if ($exam->group_submitted_attempts_count > 0)
                                <x-button :href="route('exams.revision.index', ['exam' => $exam, 'group_id' => $group->id])"
                                          variant="primary" size="sm">
                                    Revisar entregas
                                </x-button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <x-empty-state
                    icon="document"
                    title="No hay exámenes asignados a este grupo"
                    :message="$group->is_closed ? null : 'Puedes crear un examen para este grupo.'"
                >
                    @unless ($group->is_closed)
                        <x-button :href="route('exams.create', ['group_id' => $group->id])" variant="primary" size="md">
                            <x-icon name="add" class="w-4 h-4" />
                            Crear examen
                        </x-button>
                    @endunless
                </x-empty-state>
            @endforelse
        </div>
    </section>

    {{-- Nivel 4C: Apartado 3 - Calificaciones --}}
    <section
        id="group-panel-grades"
        role="tabpanel"
        aria-labelledby="group-tab-grades"
        tabindex="0"
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
            :institutional="true"
            title="Calificaciones e intentos de examen"
            subtitle="Los indicadores cuentan intentos vigentes de estudiantes inscritos. La tabla conserva el historial."
        />

        <x-card padding="p-3">
            <dl class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-700">
                <div class="flex gap-2">
                    <dt>Intentos vigentes</dt>
                    <dd class="font-semibold text-acode-primary">{{ $currentEnrolledAttempts->count() }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt>Entregados</dt>
                    <dd class="font-semibold text-acode-primary">{{ $submittedCount }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt>En progreso</dt>
                    <dd class="font-semibold text-acode-primary">{{ $inProgressCount }}</dd>
                </div>
            </dl>
        </x-card>

        @if ($attempts->isEmpty())
            <x-empty-state
                icon="reportBar"
                title="Aún no hay intentos de examen registrados"
                message="Los resultados aparecerán cuando los estudiantes comiencen a resolver las pruebas del grupo."
            />
        @else
            {{-- Filtros de Calificaciones --}}
            <form role="search" aria-label="Intentos de examen" x-on:submit.prevent="gradeSearch = $refs.gradeSearchInput.value">
                <x-search-panel>
                    <x-slot:field>
                        <x-form.input
                            id="grade-search"
                            type="search"
                            prefix-icon="search"
                            enterkeyhint="search"
                            x-ref="gradeSearchInput"
                            x-model.debounce.300ms="gradeSearch"
                            placeholder="Buscar por nombre, correo o título de examen…"
                            aria-label="Buscar estudiante o examen"
                        />
                    </x-slot:field>

                    <x-slot:actions>
                        <x-button
                            type="button" variant="secondary" x-show="gradeSearch.trim() !== '' || gradeStatus !== 'all'" x-cloak
                            x-on:click="gradeSearch = ''; gradeStatus = 'all'; $refs.gradeSearchInput.focus()"
                        >
                            Limpiar filtros
                        </x-button>
                    </x-slot:actions>

                    <x-slot:filters>
                        <x-form.group field-id="grade-status" label="Estado del intento" class="sm:w-52">
                            <x-form.select id="grade-status" size="sm" x-model="gradeStatus">
                                <option value="all">Todos los estados</option>
                                <option value="submitted">Entregado</option>
                                <option value="in_progress">En progreso</option>
                                <option value="expired">Expirado</option>
                                <option value="cancelled_by_incident">Cancelado por incidente</option>
                            </x-form.select>
                        </x-form.group>
                    </x-slot:filters>
                </x-search-panel>
            </form>

            {{-- Tabla de Intentos --}}
            <div x-show="filteredAttempts.length > 0">
                <x-table zebra="slate" scroll-label="Calificaciones e intentos de examen">
                    <x-slot:header>
                        <x-table.th align="center">Estudiante</x-table.th>
                        <x-table.th align="center">Examen</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        <x-table.th align="center">Calificación</x-table.th>
                        <x-table.th align="center">Fecha de entrega</x-table.th>
                        <x-table.th align="center">Acciones</x-table.th>
                    </x-slot:header>

                    @forelse ($attempts as $attempt)
                        @php
                            $isWithdrawn = ! $enrolledStudentIds->has($attempt->student_id);
                            $isReplaced = ! $currentAttemptIds->has($attempt->id);
                        @endphp
                        <tr
                            x-show="
                                (!gradeSearch.trim() || @js(mb_strtolower(($attempt->student?->full_name ?? '') . ' ' . ($attempt->student?->email ?? '') . ' ' . ($attempt->exam?->title ?? ''))).includes(gradeSearch.toLowerCase().trim()))
                                &&
                                (gradeStatus === 'all' || gradeStatus === @js($attempt->status))
                            "
                        >
                            <x-table.td>
                                <div class="flex items-center gap-3">
                                    <div aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-acode-primary text-xs font-bold text-white shadow-sm">
                                        {{ mb_strtoupper(mb_substr($attempt->student?->first_name ?? $attempt->student?->full_name ?? 'E', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-acode-primary leading-tight">
                                            {{ $attempt->student?->full_name ?? 'Estudiante no disponible' }}
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            {{ $attempt->student?->email ?? '—' }}
                                        </p>
                                        @if ($isWithdrawn)
                                            <x-badge variant="neutral" class="mt-1">Retirado</x-badge>
                                        @endif
                                    </div>
                                </div>
                            </x-table.td>

                            <x-table.td>
                                <p class="font-semibold text-slate-800 text-xs sm:text-sm">
                                    {{ $attempt->exam?->title ?? 'Examen no disponible' }}
                                </p>
                                <p class="text-xs text-slate-500 mt-0.5 font-mono">
                                    Escala {{ $attempt->exam?->grading_scale ?? '—' }}
                                </p>
                            </x-table.td>

                            <x-table.td align="center">
                                @if ($isReplaced)
                                    <x-badge variant="neutral">Reemplazado</x-badge>
                                @else
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
                                @endif
                            </x-table.td>

                            <x-table.td>
                                @if ($attempt->status === 'submitted' && $attempt->final_score !== null)
                                    <p class="font-mono font-bold text-acode-primary text-sm">
                                        {{ number_format((float) $attempt->final_score, 2) }}
                                    </p>
                                @elseif ($attempt->status === 'submitted')
                                    <span class="text-xs text-slate-600">Pendiente de revisión</span>
                                @else
                                    <span class="text-xs text-slate-500">—</span>
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
                                        :href="route('exams.revision.show', [
                                            'exam' => $attempt->exam,
                                            'attempt' => $attempt,
                                            'group_id' => $group->id,
                                        ])"
                                        variant="neutral"
                                        size="sm"
                                    >
                                        Revisar entrega
                                    </x-button>
                                @else
                                    <span class="text-xs text-slate-500">—</span>
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
                    <x-button type="button" variant="neutral" size="sm" @click="gradeSearch = ''; gradeStatus = 'all'; $refs.gradeSearchInput.focus()">
                        Limpiar filtros
                    </x-button>
                </x-empty-state>
            </div>
        @endif

        @if ($withdrawnPeriodGrades->isNotEmpty() || $withdrawnStudentsWithoutGrades->isNotEmpty())
            <x-section-header
                :institutional="true"
                title="Registros de estudiantes retirados"
                subtitle="Las calificaciones guardadas se conservan y no cuentan en la matrícula activa."
            />
            <x-table zebra="slate" scroll-label="Registros de estudiantes retirados">
                <x-slot:header>
                    <x-table.th align="center">Estudiante</x-table.th>
                    <x-table.th align="center">Periodo de evaluación</x-table.th>
                    <x-table.th align="center">Calificación guardada</x-table.th>
                </x-slot:header>
                @foreach ($withdrawnPeriodGrades as $grade)
                    <tr>
                        <x-table.td>
                            {{ $grade->student?->full_name ?? 'Estudiante no disponible' }}
                            <x-badge variant="neutral" class="ml-2">Retirado</x-badge>
                        </x-table.td>
                        <x-table.td>{{ $grade->evaluationPeriod?->name ?? 'Periodo no disponible' }}</x-table.td>
                        <x-table.td align="right">{{ $group->formatGrade($grade->final_grade, 2) ?? 'Pendiente' }}</x-table.td>
                    </tr>
                @endforeach
                @foreach ($withdrawnStudentsWithoutGrades as $student)
                    <tr>
                        <x-table.td>
                            {{ $student->full_name }}
                            <x-badge variant="neutral" class="ml-2">Retirado</x-badge>
                        </x-table.td>
                        <x-table.td>Sin periodo registrado</x-table.td>
                        <x-table.td align="right">Sin calificación</x-table.td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </section>

    {{-- Nivel 4D: Apartado 4 - Configuración de Evaluación (Periodos y Fórmulas) --}}
    <section
        id="group-panel-evaluation"
        role="tabpanel"
        aria-labelledby="group-tab-evaluation"
        tabindex="0"
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
            :institutional="true"
            title="Periodos y ponderaciones"
            subtitle="Consulta los periodos, las ponderaciones y las calificaciones del grupo."
        >
            <x-slot:actions>
                <x-button :href="$periodsUrl" variant="neutral" size="md">
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
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="min-w-0 break-words text-base font-bold text-acode-primary">
                                    {{ $period->name }}
                                </h3>
                                @if (! $group->is_closed && $period->is_active_today)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-acode-accent-dark">
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
                                    <p class="text-xs font-semibold text-slate-700">Tareas</p>
                                    <p class="mt-1 text-lg font-extrabold text-acode-primary font-mono">
                                        {{ number_format($weights['homework'] ?? 0, 1) }} %
                                    </p>
                                </div>

                                <div class="rounded-lg border border-acode-warning/40 bg-acode-warning/15 p-3">
                                    <p class="text-xs font-semibold text-slate-700">Proyectos</p>
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

                    <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-3">
                        <a href="{{ route('groups.periods.gradebook', [$group, $period, ...$catalogContext]) }}"
                           aria-label="Abrir calificaciones de {{ $period->name }} · {{ $group->name }}"
                           class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                            Abrir calificaciones
                        </a>
                        @unless ($group->is_closed)
                            <a href="{{ route('groups.periods.edit', [$group, $period]) }}"
                               aria-label="Editar periodo: {{ $period->name }}"
                               class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                Editar periodo
                            </a>
                            <a href="{{ route('groups.periods.plan', [$group, $period]) }}"
                               aria-label="Editar ponderaciones de {{ $period->name }}"
                               class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                Editar ponderaciones
                            </a>
                        @endunless
                    </div>
                </article>
            @empty
                <x-empty-state
                    icon="clock"
                    title="No hay periodos de evaluación registrados"
                    :message="$group->is_closed
                        ? 'El grupo está cerrado. Reábrelo si necesitas registrar un periodo.'
                        : 'Configura el primer periodo y sus ponderaciones.'"
                />
            @endforelse
        </div>
    </section>

    {{-- Nivel 5: Modal de Código QR Institucional Oficial --}}
    @unless ($group->is_closed)
        <x-group.qr-modal
            name="group-qr-modal"
            :group-name="$group->name"
            :folio="$group->access_folio"
        />
    @endunless

</div>
@endsection
