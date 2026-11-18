{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review.blade.php (MPW-V2 §4.1.2, §7, §8, ADR-056) --}}
@extends('layouts.app')

@section('title', 'Revisar y publicar: ' . $exam->title)

@section('content')
    @php
        $totalQuestions = $exam->sections->sum(fn ($section) => $section->examQuestions->count());
        $resolvedPoints = (float) ($totalPoints ?? $publishChecklist['total_weight'] ?? 0);
        $attemptsCount = (int) ($exam->student_attempts_count ?? 0);
        $opStatus = $exam->operationalStatus();

        $resultModes = [
            'final_score' => 'Calificación final inmediata',
            'estimated_score' => 'Puntaje preliminar estimado',
            'objective_only_pending' => 'Solo puntaje objetivo (pendiente docente)',
            'hidden' => 'Resultados ocultos hasta liberación',
        ];

        $checklistItems = [
            [
                'label' => 'Al menos 1 apartado creado',
                'passed' => (bool) ($publishChecklist['has_sections'] ?? false),
                'fixUrl' => route('exams.assemble', $exam),
                'fixLabel' => 'Armar apartados',
            ],
            [
                'label' => 'Todos los apartados contienen reactivos',
                'passed' => (bool) ($publishChecklist['no_empty_sections'] ?? false),
                'fixUrl' => route('exams.assemble', $exam),
                'fixLabel' => 'Completar apartados',
            ],
            [
                'label' => 'La suma de ponderaciones es exactamente 100.00 pts',
                'passed' => (bool) ($publishChecklist['exact_weight'] ?? false),
                'fixUrl' => route('exams.configuration', $exam),
                'fixLabel' => 'Ajustar pesos',
            ],
            [
                'label' => 'Al menos 1 grupo escolar abierto asignado',
                'passed' => (bool) ($publishChecklist['has_open_group'] ?? false),
                'fixUrl' => route('exams.configuration', $exam),
                'fixLabel' => 'Asignar grupos',
            ],
            [
                'label' => 'La fecha de cierre es posterior al momento actual',
                'passed' => (bool) ($publishChecklist['has_future_window'] ?? false),
                'fixUrl' => route('exams.configuration', $exam),
                'fixLabel' => 'Actualizar ventana',
            ],
        ];
    @endphp

    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="{
            confirmAction(formId, title, text, confirmText, isDanger = false) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: title,
                        text: text,
                        confirmButtonText: confirmText,
                        cancelButtonText: 'Cancelar',
                        isDanger: isDanger,
                        onConfirm: () => {
                            const form = document.getElementById(formId);
                            if (form) form.submit();
                        },
                    });
                    return;
                }
                if (confirm(title + '\n' + text)) {
                    const form = document.getElementById(formId);
                    if (form) form.submit();
                }
            },

            copyShareToken(token) {
                if (!token) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(token).then(() => {
                        if (typeof AcodeAlert !== 'undefined') {
                            AcodeAlert.toast('Folio de examen copiado al portapapeles', 'success');
                        }
                    }).catch(() => {});
                }
            }
        }"
    >
        {{-- Encabezado --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <a href="{{ route('exams.configuration', $exam) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-acode-tech hover:underline">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver a Configuración
                </a>
                <h1 class="mt-2 text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">
                    Revisar y publicar examen
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Verifica las 5 condiciones de integridad antes de habilitar el examen para tus estudiantes.
                </p>
            </div>

            <x-button :href="route('exams.index')" variant="neutral" size="sm">
                Volver al listado
            </x-button>
        </div>

        @include('exams.partials._stepper', ['currentStep' => 4, 'exam' => $exam, 'totalPoints' => $resolvedPoints])

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger" role="alert">
                <p class="font-semibold mb-1">No se pudo completar la operación:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Resumen KPI del Examen --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Apartados</p>
                <p class="mt-1 text-2xl font-bold text-acode-primary">{{ $exam->sections->count() }}</p>
            </x-card>
            <x-card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reactivos</p>
                <p class="mt-1 text-2xl font-bold text-acode-primary">{{ $totalQuestions }}</p>
            </x-card>
            <x-card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ponderación</p>
                <p class="mt-1 font-mono text-2xl font-bold {{ ($publishChecklist['exact_weight'] ?? false) ? 'text-acode-accent' : 'text-acode-warning-dark' }}">
                    {{ number_format($resolvedPoints, 2) }} / 100
                </p>
            </x-card>
            <x-card padding="p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Grupos asignados</p>
                <p class="mt-1 text-2xl font-bold text-acode-primary">{{ $exam->groups->count() }}</p>
            </x-card>
        </div>

        @if (! $exam->is_published)
            {{-- Checklist Pre-Publicación 5/5 y Formulario Real de Publicación --}}
            <x-card
                title="Checklist pre-publicación ({{ (int) ($publishChecklist['passed_count'] ?? 0) }} / {{ (int) ($publishChecklist['total_checks'] ?? 5) }} verificaciones)"
                subtitle="Todas las condiciones deben cumplirse para publicar el examen y notificar a los estudiantes."
            >
                <div class="space-y-3">
                    @foreach ($checklistItems as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border p-3.5 {{ $item['passed'] ? 'border-acode-accent/30 bg-acode-accent/5' : 'border-acode-danger/30 bg-acode-danger/5' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $item['passed'] ? 'bg-acode-accent text-white' : 'bg-acode-danger text-white' }}">
                                    <x-icon name="{{ $item['passed'] ? 'check' : 'xMark' }}" class="w-4 h-4" />
                                </span>
                                <span class="text-sm font-semibold {{ $item['passed'] ? 'text-acode-primary' : 'text-acode-danger' }}">
                                    {{ $item['label'] }}
                                </span>
                            </div>

                            @if ($item['passed'])
                                <x-badge variant="accent">Cumple</x-badge>
                            @else
                                <x-button :href="$item['fixUrl']" variant="warning" size="sm">
                                    {{ $item['fixLabel'] }} →
                                </x-button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 pt-5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="text-xs text-slate-600 max-w-xl">
                        @if ($publishChecklist['can_publish'] ?? false)
                            <p class="font-semibold text-acode-accent text-sm">¡Todo listo para publicar!</p>
                            <p class="mt-0.5">Al confirmar, el examen quedará visible para los alumnos inscritos en sus grupos y se enviará la notificación correspondiente.</p>
                        @else
                            <p class="font-semibold text-acode-primary text-sm">Faltan requisitos por completar</p>
                            <p class="mt-0.5">Corrige los puntos marcados arriba para habilitar el botón de publicación.</p>
                        @endif
                    </div>

                    <form id="publish-exam-form" method="POST" action="{{ route('exams.publish', $exam) }}" class="shrink-0">
                        @csrf
                        <x-button
                            type="button"
                            variant="accent"
                            size="lg"
                            :disabled="! ($publishChecklist['can_publish'] ?? false)"
                            @click="confirmAction(
                                'publish-exam-form',
                                '¿Publicar examen ahora?',
                                'El examen ' + {{ \Illuminate\Support\Js::from($exam->title) }} + ' quedará disponible para los estudiantes de los grupos asignados en la ventana configurada.',
                                'Publicar examen',
                                false
                            )"
                        >
                            <x-icon name="success" class="w-5 h-5" />
                            Publicar examen
                        </x-button>
                    </form>
                </div>
            </x-card>
        @else
            {{-- Panel de Examen Publicado: Folio y Acciones Operativas --}}
            <x-card title="Examen publicado y operativo" subtitle="Supervisa el progreso en vivo o gestiona su ciclo de aplicación.">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center mb-5">
                    <div class="rounded-xl border border-slate-200 bg-acode-surface p-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Token / Folio del examen</p>
                            <p class="mt-1 font-mono text-base font-bold text-acode-primary truncate select-all">
                                {{ $exam->share_token }}
                            </p>
                        </div>
                        <x-button type="button" variant="neutral" size="sm" @click="copyShareToken({{ \Illuminate\Support\Js::from($exam->share_token) }})">
                            <x-icon name="copy" class="w-4 h-4" />
                            Copiar
                        </x-button>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 space-y-1">
                        <p><strong>Estado operativo:</strong> {{ ucfirst($opStatus) }}</p>
                        <p><strong>Intentos registrados:</strong> {{ $attemptsCount }}</p>
                        <p><strong>Visibilidad:</strong> {{ $resultModes[$exam->result_visibility_mode] ?? $exam->result_visibility_mode }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 pt-3 border-t border-slate-100">
                    <x-button :href="route('exams.monitor', $exam)" variant="secondary" size="md">
                        <x-icon name="view" class="w-4 h-4" />
                        Monitoreo en vivo
                    </x-button>

                    <x-button :href="route('exams.revision.index', $exam)" variant="primary" size="md">
                        <x-icon name="edit" class="w-4 h-4" />
                        Recoteje
                    </x-button>

                    @if (! $exam->isWindowClosed())
                        <form id="review-close-exam-form" method="POST" action="{{ route('exams.close', $exam) }}" class="inline">
                            @csrf
                            <x-button
                                type="button"
                                variant="warning"
                                size="md"
                                @click="confirmAction(
                                    'review-close-exam-form',
                                    '¿Cerrar examen anticipadamente?',
                                    'Se cerrará inmediatamente la ventana de aplicación y ningún alumno podrá iniciar o enviar nuevos intentos.',
                                    'Cerrar anticipadamente',
                                    true
                                )"
                            >
                                Cerrar anticipadamente
                            </x-button>
                        </form>
                    @endif

                    @if ($attemptsCount === 0)
                        <form id="review-unpublish-exam-form" method="POST" action="{{ route('exams.unpublish', $exam) }}" class="inline">
                            @csrf
                            <x-button
                                type="button"
                                variant="neutral"
                                size="md"
                                @click="confirmAction(
                                    'review-unpublish-exam-form',
                                    '¿Despublicar examen?',
                                    'El examen regresará a estado borrador (no tiene intentos registrados).',
                                    'Despublicar',
                                    false
                                )"
                            >
                                Despublicar
                            </x-button>
                        </form>
                    @endif

                    @if ($exam->result_visibility_mode === 'hidden')
                        <form id="review-release-results-form" method="POST" action="{{ route('exams.results.release', $exam) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <x-button
                                type="button"
                                variant="accent"
                                size="md"
                                @click="confirmAction(
                                    'review-release-results-form',
                                    '¿Liberar resultados a los alumnos?',
                                    'Los estudiantes podrán consultar sus calificaciones finales desde su panel.',
                                    'Liberar resultados',
                                    false
                                )"
                            >
                                Liberar resultados
                            </x-button>
                        </form>
                    @endif
                </div>
            </x-card>
        @endif

        {{-- Resumen de Configuración y Apartados --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <x-card title="Configuración general" class="lg:col-span-5">
                @if (! $exam->is_published)
                    <x-slot:actions>
                        <a href="{{ route('exams.configuration', $exam) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                            Editar
                        </a>
                    </x-slot:actions>
                @endif

                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Escala</dt>
                        <dd class="font-bold text-acode-primary">{{ $exam->grading_scale }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Resultados</dt>
                        <dd class="font-semibold text-acode-primary text-right">{{ $resultModes[$exam->result_visibility_mode] ?? 'No definido' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Orden de reactivos</dt>
                        <dd class="font-semibold text-acode-primary">{{ $exam->randomize_question_order ? 'Aleatorio' : 'Fijo por apartado' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Incidencias máximas</dt>
                        <dd class="font-mono font-bold text-acode-primary">{{ $exam->max_allowed_incidents }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Inicio</dt>
                        <dd class="font-semibold text-acode-primary">{{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Cierre</dt>
                        <dd class="font-semibold text-acode-primary">{{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 mb-1.5">Grupos asignados</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @forelse ($exam->groups as $group)
                                <x-badge :variant="$group->is_closed ? 'neutral' : 'info'">
                                    {{ $group->name }}{{ $group->is_closed ? ' (Cerrado)' : '' }}
                                </x-badge>
                            @empty
                                <span class="text-slate-400 italic">Sin grupos asignados</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Apartados y reactivos" class="lg:col-span-7">
                @if ($exam->supportsStructuralEdit())
                    <x-slot:actions>
                        <a href="{{ route('exams.assemble', $exam) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                            Armar / Editar
                        </a>
                    </x-slot:actions>
                @endif

                <div class="space-y-3">
                    @forelse ($exam->sections as $section)
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5 border-b border-slate-100">
                                <span class="font-bold text-sm text-acode-primary">{{ $section->title }}</span>
                                <span class="text-xs font-semibold text-slate-500">{{ $section->examQuestions->count() }} reactivo(s)</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @forelse ($section->examQuestions as $examQuestion)
                                    <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-xs">
                                        <span class="text-slate-700 font-medium truncate">
                                            {{ $loop->iteration }}. {{ $examQuestion->question?->title ?? 'Reactivo #' . $examQuestion->question_id }}
                                        </span>
                                        <span class="font-mono font-bold text-acode-tech shrink-0">
                                            {{ number_format((float) $examQuestion->points_weight, 2) }} pts
                                        </span>
                                    </div>
                                @empty
                                    <p class="px-4 py-3 text-xs text-acode-danger">Apartado vacío sin reactivos.</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <x-empty-state
                            icon="document"
                            title="Sin apartados registrados"
                            message="Regresa a la etapa Armar reactivos para crear apartados."
                        />
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
@endsection
