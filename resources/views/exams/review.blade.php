{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review.blade.php (MPW-V3 §4.1.2, §7, §8, ADR-056) --}}
@extends('layouts.app')

@section('title', ($exam->is_published ? 'Resumen del examen: ' : 'Revisar y publicar: ') . $exam->title)

@section('content')
    @php
        $examQuestions = $exam->sections->flatMap->examQuestions->keyBy('id');
        $resolvedPoints = (float) ($totalPoints ?? $publishChecklist['total_weight'] ?? 0);
        $openGroupsCount = (int) $publishChecklist['open_groups_count'];
        $attemptsCount = (int) ($exam->student_attempts_count ?? 0);
        $opStatus = $exam->operationalStatus();
        $status = match ($opStatus) {
            'scheduled' => ['label' => 'Programado', 'variant' => 'info'],
            'active' => ['label' => 'Activo', 'variant' => 'accent'],
            'closed' => ['label' => 'Cerrado', 'variant' => 'neutral'],
            default => ['label' => 'Borrador', 'variant' => 'warning'],
        };
        $canEditStructure = $exam->supportsStructuralEdit();
        $canEditConfiguration = ! $exam->is_published || $opStatus === 'scheduled';
        $canPublish = (bool) $publishChecklist['can_publish'];
        $pageTitle = $exam->is_published ? 'Resumen del examen' : 'Revisar y publicar examen';
        $assembleUrl = route('exams.assemble', $exam);
        $configurationUrl = route('exams.configuration', $exam);
        $timezone = \App\Models\Exam::timezoneLabel();
        $startsAt = $exam->starts_at?->format('d/m/Y H:i');
        $endsAt = $exam->ends_at?->format('d/m/Y H:i');
        $usesFreeNavigation = $exam->free_duration_minutes !== null;
        $totalSeconds = (int) $examQuestions->sum('time_limit_seconds');

        $resultModes = [
            'final_score' => 'Calificación final inmediata',
            'estimated_score' => 'Puntaje preliminar estimado',
            'objective_only_pending' => 'Solo puntaje objetivo (pendiente docente)',
            'hidden' => 'Resultados ocultos hasta liberación',
        ];

        $checklistItems = [
            [
                'passed' => $publishChecklist['has_sections'],
                'label' => 'Al menos un apartado creado',
                'detail' => null,
                'links' => [
                    ['url' => $assembleUrl, 'label' => 'Armar apartados'],
                ],
            ],
            [
                'passed' => $publishChecklist['no_empty_sections'],
                'label' => 'Todos los apartados contienen reactivos completos',
                'detail' => null,
                'links' => $publishChecklist['has_sections'] ? [] : [
                    ['url' => $assembleUrl, 'label' => 'Completar apartados'],
                ],
            ],
            [
                'passed' => $publishChecklist['exact_weight'],
                'label' => 'Ponderación de 100.00 pts con tolerancia de 0.01 pts',
                'detail' => 'Total actual: ' . number_format($resolvedPoints, 2, '.', '') . ' pts',
                'links' => [
                    ['url' => $configurationUrl . '#weights', 'label' => 'Ajustar pesos'],
                ],
            ],
            [
                'passed' => $publishChecklist['has_open_group'],
                'label' => 'Al menos un grupo escolar abierto asignado',
                'detail' => 'Grupos abiertos: ' . $openGroupsCount . ' de ' . $exam->groups->count() . ' asignados',
                'links' => [
                    ['url' => $configurationUrl . '#assigned-groups', 'label' => 'Asignar grupos'],
                ],
            ],
            [
                'passed' => $publishChecklist['has_future_window'],
                'label' => 'La fecha de cierre es posterior al momento actual',
                'detail' => $endsAt ? 'Cierre: ' . $endsAt . ' · ' . $timezone : 'Sin fecha de cierre',
                'links' => [
                    ['url' => $configurationUrl . '#application-window', 'label' => 'Actualizar ventana'],
                ],
            ],
        ];

        foreach ($publishChecklist['empty_sections'] ?? [] as $section) {
            $checklistItems[1]['links'][] = [
                'detail' => 'Apartado sin reactivos: ' . $section['title'],
                'url' => $assembleUrl . '#section-' . $section['id'],
                'label' => 'Completar apartado',
            ];
        }

        foreach ($publishChecklist['incomplete_questions'] ?? [] as $issue) {
            if ($issue['missing_time']) {
                $checklistItems[1]['links'][] = [
                    'detail' => 'Tiempo inválido: ' . $issue['title'],
                    'url' => $configurationUrl . '#question-time-' . $issue['id'],
                    'label' => 'Ajustar tiempo',
                ];
            }

            if ($issue['missing_reference']) {
                $examQuestion = $examQuestions->get($issue['id']);
                $question = $examQuestion?->question;
                $canEditReference = $canEditStructure && $question && ! $question->trashed();
                $checklistItems[1]['links'][] = [
                    'detail' => 'Respuesta de referencia faltante o incompleta: ' . $issue['title'],
                    'url' => $canEditReference
                        ? route('exams.questions.edit', [$exam, $issue['id'], 'return_to' => 'review'])
                        : ($examQuestion ? $assembleUrl . '#section-' . $examQuestion->exam_section_id : $assembleUrl),
                    'label' => $canEditReference ? 'Editar reactivo' : 'Ver apartado',
                ];
            }
        }

        $publishAction = [
            'key' => 'publish',
            'title' => '¿Publicar examen ahora?',
            'text' => 'El examen ' . $exam->title . ' quedará disponible para los estudiantes de los grupos asignados en la ventana configurada. La plataforma enviará el aviso de asignación.',
            'label' => 'Publicar examen',
            'loading' => 'Publicando…',
        ];
        $publishedActions = [];
        if ($exam->is_published && $opStatus !== 'closed') {
            $publishedActions[] = [
                'key' => 'close',
                'url' => route('exams.close', $exam),
                'title' => '¿Cerrar examen anticipadamente?',
                'text' => 'La ventana se cerrará de inmediato. Los intentos en curso quedarán expirados y se conservarán sus borradores sin calificarlos. Los estudiantes ya no podrán iniciar ni entregar intentos.',
                'label' => 'Cerrar anticipadamente',
                'loading' => 'Cerrando…',
                'variant' => 'danger',
            ];
        }

        if ($exam->is_published && $attemptsCount === 0) {
            $publishedActions[] = [
                'key' => 'unpublish',
                'url' => route('exams.unpublish', $exam),
                'title' => '¿Despublicar examen?',
                'text' => 'El examen regresará a estado borrador y dejará de estar disponible para los estudiantes.',
                'label' => 'Despublicar',
                'loading' => 'Despublicando…',
                'variant' => 'neutral',
            ];
        }

        if ($exam->is_published && $exam->result_visibility_mode === 'hidden') {
            $publishedActions[] = [
                'key' => 'release-results',
                'url' => route('exams.results.release', $exam),
                'method' => 'PATCH',
                'title' => '¿Liberar resultados a los alumnos?',
                'text' => 'Los estudiantes podrán consultar sus calificaciones finales desde su panel.',
                'label' => 'Liberar resultados',
                'loading' => 'Liberando resultados…',
                'variant' => 'secondary',
            ];
        }

        $publishedLinks = [
            [
                'url' => route('exams.monitor', $exam),
                'icon' => 'view',
                'label' => 'Monitoreo en vivo',
                'variant' => $opStatus === 'active' ? 'primary' : 'secondary',
            ],
            [
                'url' => route('exams.revision.index', $exam),
                'icon' => 'edit',
                'label' => 'Revisar entregas',
                'variant' => $opStatus === 'closed' ? 'primary' : 'secondary',
            ],
        ];
        if ($opStatus === 'closed') {
            $publishedLinks = array_reverse($publishedLinks);
        }
    @endphp

    <div
        class="space-y-4 sm:space-y-5"
        x-data="{
            confirming: false,
            submitting: null,
            submissionStatus: '',
            confirmationUnavailable: false,
            copyStatus: '',

            async confirmAction(form, action, trigger) {
                if (this.confirming || this.submitting !== null) return;

                this.confirmationUnavailable = false;
                this.confirming = true;

                try {
                    if (typeof window.AcodeAlert?.confirm !== 'function') {
                        throw new Error('Confirmation unavailable');
                    }

                    const result = await window.AcodeAlert.confirm({
                        title: action.title,
                        text: action.text,
                        confirmButtonText: action.label,
                        cancelButtonText: 'Cancelar',
                        isDanger: action.variant === 'danger',
                    });

                    if (!result.isConfirmed) {
                        trigger.focus();
                        return;
                    }

                    this.submitting = action.key;
                    this.submissionStatus = action.loading;
                    await this.$nextTick();
                    form.submit();
                } catch {
                    this.resetAction();
                    this.confirmationUnavailable = true;
                    this.$nextTick(() => this.$refs.confirmationError.focus());
                } finally {
                    this.confirming = false;
                }
            },

            resetAction() {
                this.submitting = null;
                this.submissionStatus = '';
            },

            async copyFolio() {
                this.copyStatus = '';
                await this.$nextTick();

                const field = this.$refs.shareToken;
                const copied = await window.copyGroupFolio?.(field.value);
                if (!copied) {
                    this.copyStatus = 'No se pudo copiar el folio. Puedes copiarlo manualmente.';
                    field.focus();
                    field.select();
                    return;
                }

                if (typeof window.Swal?.fire === 'function' && typeof window.AcodeAlert?.toast === 'function') {
                    window.AcodeAlert.toast('Folio de examen copiado al portapapeles', 'success');
                } else {
                    this.copyStatus = 'Folio de examen copiado al portapapeles.';
                }
            },
        }"
        @pageshow.window="resetAction()"
    >
        <x-page-header
            :title="$pageTitle"
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => $exam->title],
                ['label' => $exam->is_published ? 'Resumen' : 'Revisar y publicar'],
            ]"
        >
            @if ($exam->is_published)
                <x-slot:badge>
                    <x-badge :variant="$status['variant']">{{ $status['label'] }}</x-badge>
                </x-slot:badge>
            @endif
            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver a Exámenes
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @unless ($exam->is_published)
            @include('exams.partials._stepper', ['currentStep' => 4, 'exam' => $exam, 'showPoints' => false])
        @endunless

        <div
            x-show="confirmationUnavailable"
            x-cloak
            x-ref="confirmationError"
            tabindex="-1"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-acode-danger/30 bg-white px-4 py-3 text-sm text-acode-danger-dark focus:outline focus:outline-2 focus:outline-acode-danger-dark"
        >
            <p role="alert">No se pudo confirmar la acción. Recarga la página e inténtalo de nuevo.</p>
            <x-button :href="route('exams.review', $exam)" variant="primary" size="sm">Reintentar</x-button>
        </div>
        <p role="status" class="sr-only" x-text="submissionStatus"></p>

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-white px-4 py-3 text-sm text-acode-danger-dark" role="alert">
                <p class="mb-1 font-semibold">No se pudo completar la operación</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($exam->is_published)
            <x-card>
                <x-section-header
                    title="Requisitos de publicación"
                    subtitle="{{ $publishChecklist['passed_count'] }} de {{ $publishChecklist['total_checks'] }} requisitos cumplidos"
                    :institutional="true"
                    class="mb-4"
                />

                <ul class="space-y-2" role="list">
                    @foreach (collect($checklistItems)->sortBy('passed') as $item)
                        <li class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-start gap-2.5">
                                <x-icon
                                    :name="$item['passed'] ? 'check' : 'xMark'"
                                    :class="'mt-0.5 w-4 h-4 ' . ($item['passed'] ? 'text-acode-accent-dark' : 'text-acode-danger-dark')"
                                />
                                <p class="min-w-0 flex-1 text-sm font-semibold text-acode-primary">
                                    {{ $item['label'] }}
                                </p>
                                <x-badge :variant="$item['passed'] ? 'accent' : 'warning'">
                                    {{ $item['passed'] ? 'Cumple' : 'Pendiente' }}
                                </x-badge>
                            </div>

                            @if ($item['detail'])
                                <p class="mt-0.5 ml-6 text-xs text-slate-700">{{ $item['detail'] }}</p>
                            @endif

                            @unless ($item['passed'])
                                @if ($item['links'] !== [])
                                    <ul class="mt-2 ml-6 space-y-2" role="list">
                                        @foreach ($item['links'] as $link)
                                            <li class="flex flex-wrap items-center gap-2">
                                                @isset($link['detail'])
                                                    <p class="min-w-0 break-words text-sm text-slate-700">{{ $link['detail'] }}</p>
                                                @endisset
                                                <x-button
                                                    :href="$link['url']"
                                                    :aria-label="isset($link['detail']) ? $link['label'] . '. ' . $link['detail'] : null"
                                                    variant="secondary"
                                                    size="sm"
                                                >
                                                    {{ $link['label'] }}
                                                </x-button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endunless
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <p id="publish-exam-requirements" class="text-sm text-slate-700">
                        @if ($canPublish)
                            Se cumplen los requisitos del examen. Al publicar, los grupos asignados podrán acceder en la ventana indicada.
                        @else
                            Resuelve los requisitos pendientes para publicar.
                        @endif
                    </p>
                    <form method="POST" action="{{ route('exams.publish', $exam) }}" :aria-busy="submitting === 'publish'">
                        @csrf
                        <x-button
                            type="button"
                            variant="primary"
                            size="md"
                            :disabled="! $canPublish"
                            ::disabled="{{ $canPublish ? 'false' : 'true' }} || submitting !== null"
                            aria-describedby="publish-exam-requirements"
                            loading-when="submitting === 'publish'"
                            @click="confirmAction($el.form, {{ \Illuminate\Support\Js::from($publishAction) }}, $el)"
                        >
                            <x-icon name="success" class="w-4 h-4" x-show="submitting !== 'publish'" />
                            <span x-text="submitting === 'publish' ? 'Publicando…' : 'Publicar examen'">Publicar examen</span>
                        </x-button>
                    </form>
                </div>
            </x-card>
        @else
            <x-card>
                <x-section-header
                    title="Aplicación del examen"
                    :institutional="true"
                    class="mb-4"
                >
                    <x-slot:actions>
                        @foreach ($publishedLinks as $link)
                            <x-button :href="$link['url']" :variant="$link['variant']">
                                <x-icon :name="$link['icon']" class="w-4 h-4" />
                                {{ $link['label'] }}
                            </x-button>
                        @endforeach
                    </x-slot:actions>
                </x-section-header>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="min-w-0">
                        <x-summary-list>
                            <x-summary-item label="Intentos registrados" :value="$attemptsCount" compact />
                            <x-summary-item label="Fecha y hora de inicio" :value="$startsAt ?? 'Sin fecha'" compact />
                            <x-summary-item label="Fecha y hora de cierre" :value="$endsAt ?? 'Sin fecha'" compact />
                        </x-summary-list>
                        <p class="mt-3 text-xs text-slate-700">{{ $timezone }}</p>
                    </div>

                    @if (filled($exam->share_token))
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <label for="exam-share-folio" class="block text-sm font-semibold text-acode-primary">Folio del examen</label>
                            <div class="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                <x-form.input
                                    id="exam-share-folio"
                                    x-ref="shareToken"
                                    :value="$exam->share_token"
                                    readonly
                                    accessible
                                    class="font-mono font-bold"
                                />
                                <x-button type="button" variant="neutral" @click="copyFolio()">
                                    <x-icon name="copy" class="w-4 h-4" />
                                    Copiar folio
                                </x-button>
                            </div>
                            <p role="status" :class="copyStatus ? 'mt-2 text-sm text-slate-700' : 'sr-only'" x-text="copyStatus"></p>
                        </div>
                    @endif
                </div>

                @if ($publishedActions !== [])
                    <div class="mt-4 flex flex-wrap gap-2.5 border-t border-slate-200 pt-4">
                        @foreach ($publishedActions as $action)
                            <form method="POST" action="{{ $action['url'] }}" :aria-busy="submitting === @js($action['key'])">
                                @csrf
                                @isset($action['method'])
                                    @method($action['method'])
                                @endisset
                                <x-button
                                    type="button"
                                    :variant="$action['variant']"
                                    :loading-when="'submitting === ' . \Illuminate\Support\Js::from($action['key'])"
                                    ::disabled="submitting !== null"
                                    @click="confirmAction($el.form, {{ \Illuminate\Support\Js::from($action) }}, $el)"
                                >
                                    <span x-text="submitting === @js($action['key']) ? @js($action['loading']) : @js($action['label'])">
                                        {{ $action['label'] }}
                                    </span>
                                </x-button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endunless

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <x-card class="lg:col-span-5">
                <x-section-header title="Configuración general" :institutional="true" class="mb-4">
                    <x-slot:actions>
                        <x-button :href="$configurationUrl" variant="neutral" size="sm">
                            <x-icon :name="$canEditConfiguration ? 'edit' : 'view'" class="w-4 h-4" />
                            {{ $canEditConfiguration ? 'Configurar pesos' : 'Ver detalles' }}
                        </x-button>
                        @if ($exam->is_published && $canEditStructure)
                            <x-button :href="route('exams.edit', $exam)" variant="neutral" size="sm">
                                Editar datos
                            </x-button>
                        @endif
                    </x-slot:actions>
                </x-section-header>

                <dl class="space-y-2.5 text-sm">
                    <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                        <dt class="text-slate-700">Escala</dt>
                        <dd class="font-semibold text-acode-primary">{{ $exam->grading_scale }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                        <dt class="text-slate-700">Visibilidad de resultados</dt>
                        <dd class="font-semibold text-acode-primary">{{ $resultModes[$exam->result_visibility_mode] ?? 'No definido' }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                        <dt class="text-slate-700">Orden de reactivos</dt>
                        <dd class="font-semibold text-acode-primary">{{ $exam->randomize_question_order ? 'Aleatorio' : 'Fijo por apartado' }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                        <dt class="text-slate-700">Incidencias máximas</dt>
                        <dd class="font-semibold text-acode-primary">{{ $exam->max_allowed_incidents }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                        <dt class="text-slate-700">Modalidad de aplicación</dt>
                        <dd class="font-semibold text-acode-primary">{{ $usesFreeNavigation ? 'Libre' : 'Secuencial' }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                        <dt class="text-slate-700">{{ $usesFreeNavigation ? 'Tiempo total en modalidad libre' : 'Tiempo total por reactivos' }}</dt>
                        <dd class="font-semibold text-acode-primary">
                            @if ($usesFreeNavigation)
                                {{ $exam->free_duration_minutes }} min
                            @else
                                {{ intdiv($totalSeconds, 60) }} min {{ $totalSeconds % 60 }} s
                            @endif
                        </dd>
                    </div>
                    @if ($exam->is_published)
                        <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                            <dt class="text-slate-700">Ponderación total</dt>
                            <dd class="font-semibold text-acode-primary">{{ number_format($resolvedPoints, 2, '.', '') }} pts</dd>
                        </div>
                    @else
                        <div class="flex flex-wrap justify-between gap-x-3 border-b border-slate-100 pb-2">
                            <dt class="text-slate-700">Fecha y hora de inicio</dt>
                            <dd class="font-semibold text-acode-primary">{{ $startsAt ? $startsAt . ' · ' . $timezone : 'Sin fecha' }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="mb-1.5 text-slate-700">Grupos asignados</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @forelse ($exam->groups as $group)
                                <x-badge :variant="$group->is_closed ? 'neutral' : 'info'">
                                    {{ $group->name }}{{ $group->is_closed ? ' (Cerrado)' : '' }}
                                </x-badge>
                            @empty
                                <span class="text-slate-600">Sin grupos asignados</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card class="lg:col-span-7">
                <x-section-header
                    title="Apartados y reactivos"
                    subtitle="{{ $exam->sections->count() }} apartados · {{ $examQuestions->count() }} reactivos"
                    :institutional="true"
                    class="mb-4"
                >
                    @if ($canEditStructure)
                        <x-slot:actions>
                            <x-button :href="$assembleUrl" variant="neutral" size="sm">
                                <x-icon name="edit" class="w-4 h-4" />
                                Editar apartados
                            </x-button>
                        </x-slot:actions>
                    @endif
                </x-section-header>

                <div class="space-y-3">
                    @forelse ($exam->sections as $section)
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <h3 class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-base font-semibold leading-6 text-acode-primary">
                                {{ $section->title }}
                            </h3>
                            @if ($section->examQuestions->isEmpty())
                                <p class="px-4 py-3 text-sm text-acode-danger-dark">Apartado vacío sin reactivos.</p>
                            @else
                                <ol class="divide-y divide-slate-100" role="list">
                                    @foreach ($section->examQuestions as $examQuestion)
                                        <li class="flex items-start justify-between gap-3 px-4 py-2.5 text-sm">
                                            <span class="min-w-0 break-words text-slate-700">
                                                {{ $loop->iteration }}. {{ $examQuestion->question?->title ?? 'Reactivo #' . $examQuestion->question_id }}
                                            </span>
                                            <span class="shrink-0 font-semibold text-acode-tech tabular-nums">
                                                {{ number_format((float) $examQuestion->points_weight, 2) }} pts
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @empty
                        <x-empty-state
                            icon="document"
                            title="Sin apartados registrados"
                            :message="$canEditStructure ? 'Regresa a la etapa Armar reactivos para crear apartados.' : 'El examen se conserva en modo de consulta.'"
                        />
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/group-access.js') }}" defer></script>
@endpush
