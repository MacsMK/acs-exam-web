{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/index.blade.php (MPW-V3 §4.1.2, §7, §8, Tabla 10, Tabla 18, RF-EXA) --}}
@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
    @php
        $hasActiveFilters = filled($filters['search'] ?? null)
            || (filled($filters['status'] ?? null) && ($filters['status'] ?? 'all') !== 'all')
            || filled($filters['group_id'] ?? null);

        $statusLabels = [
            'draft' => ['label' => 'Borrador', 'variant' => 'warning'],
            'scheduled' => ['label' => 'Programado', 'variant' => 'info'],
            'active' => ['label' => 'Activo', 'variant' => 'accent'],
            'closed' => ['label' => 'Cerrado', 'variant' => 'neutral'],
        ];
        $groupNameCounts = $groups->countBy('name');
        $groupLabels = $groups->mapWithKeys(static function ($group) use ($groupNameCounts): array {
            $label = $group->name;
            if ($groupNameCounts->get($group->name, 0) > 1 && filled($group->access_folio)) {
                $label .= ' · Folio ' . $group->access_folio;
            }

            return [$group->id => $label];
        });
        $resultCount = $exams->total();
        $resultStatus = $resultCount === 1 ? '1 examen encontrado' : $resultCount . ' exámenes encontrados';
        $activeExams = (int) ($stats['published_active'] ?? 0);
        $pendingReviewExams = (int) ($stats['pending_recoteje_exams'] ?? 0);
    @endphp

    <div
        class="space-y-6"
        x-data="{
            pendingExamAction: null,
            submittingDialog: null,
            confirmationUnavailable: false,
            requestExamAction(dialogName, action) {
                if (this.submittingDialog) return;
                this.pendingExamAction = { dialogName, ...action };
                this.$dispatch('close-modal', dialogName);
            },
            async confirmExamAction(dialogName) {
                if (this.pendingExamAction?.dialogName !== dialogName) return;

                const { formId, title, text, label, isDanger } = this.pendingExamAction;
                this.pendingExamAction = null;
                this.confirmationUnavailable = false;

                if (typeof window.AcodeAlert?.confirm !== 'function') {
                    this.showConfirmationError();
                    return;
                }

                try {
                    const result = await window.AcodeAlert.confirm({
                        title,
                        text,
                        confirmButtonText: label,
                        cancelButtonText: 'Cancelar',
                        isDanger,
                    });
                    if (!result.isConfirmed) return;

                    const form = document.getElementById(formId);
                    if (!form) {
                        this.showConfirmationError();
                        return;
                    }

                    this.submittingDialog = dialogName;
                    await this.$nextTick();
                    form.submit();
                } catch {
                    this.submittingDialog = null;
                    this.showConfirmationError();
                }
            },
            showConfirmationError() {
                this.confirmationUnavailable = true;
                this.$nextTick(() => this.$refs.confirmationError.focus());
            },
        }"
        @modal-closed="confirmExamAction($event.detail)"
        @pageshow.window="submittingDialog = null"
    >
        <p
            x-show="confirmationUnavailable"
            x-cloak
            x-ref="confirmationError"
            tabindex="-1"
            role="alert"
            class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger-dark"
        >
            No se pudo confirmar la acción. Recarga la página e inténtalo de nuevo.
        </p>

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger-dark" role="alert">
                <p class="mb-1 font-semibold">Revisa lo siguiente:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-page-header title="Mis exámenes" :institutional="true">
            <x-slot:actions>
                @if ($activeExams > 0)
                    <x-button :href="route('exams.index', ['status' => 'active'])" variant="secondary" size="md">
                        Ver activos ({{ $activeExams }})
                    </x-button>
                @endif
                <x-button :href="route('exams.create')" variant="primary" size="md" title="Nuevo examen">
                    <x-icon name="add" class="w-4 h-4" />
                    Crear examen
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <section aria-label="Filtros del listado de exámenes">
            <x-search-filter
                :action="route('exams.index')"
                search-name="search"
                search-label="Buscar exámenes"
                :search-value="$filters['search'] ?? ''"
                placeholder="Buscar por título o descripción…"
                results-id="exam-search-results"
                :clear-url="route('exams.index')"
                :clear-keys="['search', 'status', 'group_id']"
                :has-active-filters="$hasActiveFilters"
                live-clear
            >
                <x-slot:filters>
                    <x-form.group name="status" label="Estado" class="min-w-0 flex-1 basis-32 sm:w-40 sm:flex-none">
                        <x-form.select name="status" size="sm">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Todos los estados</option>
                            <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Borradores</option>
                            <option value="scheduled" @selected(($filters['status'] ?? '') === 'scheduled')>Programados</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos ahora</option>
                            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Cerrados</option>
                            <option value="pending_review" @selected(($filters['status'] ?? '') === 'pending_review')>Pendientes de revisión</option>
                        </x-form.select>
                    </x-form.group>

                    <x-form.group name="group_id" label="Grupo asignado" class="min-w-0 flex-1 basis-32 sm:w-52 sm:flex-none">
                        <x-form.select name="group_id" size="sm">
                            <option value="">Todos los grupos</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>
                                    {{ $groupLabels->get($group->id) }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>
                </x-slot:filters>
            </x-search-filter>
        </section>

        @fragment('exam-search-results')
            <section
                id="exam-search-results"
                aria-label="Resultados de búsqueda de exámenes"
                tabindex="-1"
                data-has-active-filters="{{ $hasActiveFilters ? '1' : '0' }}"
                data-search-status="{{ $resultStatus }}"
                class="space-y-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
            >
                <x-section-header title="Exámenes registrados" :subtitle="\App\Models\Exam::timezoneLabel()" :institutional="true">
                    <x-slot:actions>
                        <p class="text-sm text-slate-700">{{ $resultStatus }}</p>
                    </x-slot:actions>
                </x-section-header>
                @if ($exams->isEmpty())
                    <x-empty-state
                        :icon="$hasActiveFilters ? 'search' : 'document'"
                        :title="$hasActiveFilters ? 'No encontramos exámenes con esos filtros' : 'Todavía no has creado exámenes'"
                        :message="$hasActiveFilters
                            ? 'Cambia la búsqueda o limpia los filtros para ver más exámenes.'
                            : 'Crea tu primer examen para armar reactivos, definir ponderaciones sobre 100.00 puntos y asignarlo a tus grupos.'"
                    />
                @else
                    <div>
                        <p class="mb-2 text-xs text-slate-700 xl:hidden">Desplaza la tabla para ver todas las columnas.</p>
                        <x-table zebra="slate" scroll-label="Exámenes registrados" table-class="min-w-[56rem] table-fixed" class="[&_th]:px-3 [&_td]:px-3">
                            <x-slot:columns>
                                <colgroup>
                                    <col class="w-1/4">
                                    <col class="w-36">
                                    <col>
                                    <col class="w-48">
                                    <col class="w-52">
                                </colgroup>
                            </x-slot:columns>
                            <x-slot:header>
                                <x-table.th align="center">Examen</x-table.th>
                                <x-table.th align="center">Estado</x-table.th>
                                <x-table.th align="center">Grupos asignados</x-table.th>
                                <x-table.th align="center" class="whitespace-nowrap">Ventana de aplicación</x-table.th>
                                <x-table.th align="center">Acciones</x-table.th>
                            </x-slot:header>

                            @foreach ($exams as $exam)
                                @php
                                    $opStatus = $exam->operationalStatus();
                                    $badgeMeta = $statusLabels[$opStatus] ?? $statusLabels['draft'];
                                    $sectionsCount = (int) ($exam->sections_count ?? 0);
                                    $questionsCount = (int) ($exam->exam_questions_count ?? 0);
                                    $attemptsCount = (int) ($exam->student_attempts_count ?? 0);
                                    $pendingReview = (int) ($exam->pending_recoteje_count ?? 0);
                                    $totalWeight = (float) ($exam->total_points_weight ?? 0);
                                    $summaryUrl = route('exams.review', $exam);
                                    $assembleUrl = route('exams.assemble', $exam);
                                    $monitorUrl = route('exams.monitor', $exam);
                                    $reviewUrl = route('exams.revision.index', $exam);
                                    $menuName = 'exam-actions-' . $exam->id;
                                    $menuSubmitting = "submittingDialog === '{$menuName}'";
                                    [$rowLabel, $rowUrl] = match ($opStatus) {
                                        'draft' => ['Armar reactivos', $assembleUrl],
                                        'active' => ['Monitoreo', $monitorUrl],
                                        'closed' => ['Revisar entregas', $reviewUrl],
                                        default => ['Abrir resumen', $summaryUrl],
                                    };

                                    $moreLinks = match ($opStatus) {
                                        'draft' => [
                                            'Editar' => route('exams.edit', $exam),
                                            'Configurar pesos' => route('exams.configuration', $exam),
                                            'Revisar y publicar' => $summaryUrl,
                                        ],
                                        'active' => [
                                            'Revisar entregas' => $reviewUrl,
                                            'Abrir resumen' => $summaryUrl,
                                        ],
                                        'closed' => [
                                            'Abrir resumen' => $summaryUrl,
                                            'Monitoreo' => $monitorUrl,
                                        ],
                                        default => [
                                            'Monitoreo' => $monitorUrl,
                                            'Revisar entregas' => $reviewUrl,
                                        ],
                                    };

                                    $changes = [];
                                    if ($opStatus === 'draft' && $attemptsCount === 0) {
                                        $changes[] = [
                                            'formId' => 'delete-exam-' . $exam->id,
                                            'url' => route('exams.destroy', $exam),
                                            'method' => 'DELETE',
                                            'title' => '¿Eliminar borrador?',
                                            'text' => 'El examen «' . $exam->title . '» y sus apartados se eliminarán de forma permanente.',
                                            'label' => 'Eliminar',
                                            'isDanger' => true,
                                        ];
                                    }
                                    if ($opStatus !== 'draft' && $attemptsCount === 0) {
                                        $changes[] = [
                                            'formId' => 'unpublish-exam-' . $exam->id,
                                            'url' => route('exams.unpublish', $exam),
                                            'method' => 'POST',
                                            'title' => '¿Despublicar examen?',
                                            'text' => 'El examen regresará a borrador porque aún no registra intentos de estudiantes.',
                                            'label' => 'Despublicar',
                                            'isDanger' => false,
                                        ];
                                    }
                                    if ($opStatus !== 'draft' && $exam->result_visibility_mode === 'hidden') {
                                        $changes[] = [
                                            'formId' => 'release-exam-' . $exam->id,
                                            'url' => route('exams.results.release', $exam),
                                            'method' => 'PATCH',
                                            'title' => '¿Liberar resultados?',
                                            'text' => 'Los estudiantes podrán consultar sus calificaciones obtenidas en este examen.',
                                            'label' => 'Liberar resultados',
                                            'isDanger' => false,
                                        ];
                                    }
                                    if (in_array($opStatus, ['active', 'scheduled'], true)) {
                                        $changes[] = [
                                            'formId' => 'close-exam-' . $exam->id,
                                            'url' => route('exams.close', $exam),
                                            'method' => 'POST',
                                            'title' => '¿Cerrar examen anticipadamente?',
                                            'text' => 'Ya no se podrán iniciar intentos en «' . $exam->title . '». Si hay intentos en curso, se detendrán y quedarán vencidos sin calificación final. Las respuestas guardadas se conservarán.',
                                            'label' => 'Cerrar examen',
                                            'isDanger' => true,
                                        ];
                                    }
                                @endphp

                                <tr>
                                    <th scope="row" aria-labelledby="exam-title-{{ $exam->id }}" class="px-3 py-3 text-left align-top">
                                        <a
                                            id="exam-title-{{ $exam->id }}"
                                            href="{{ $opStatus === 'draft' ? $assembleUrl : $summaryUrl }}"
                                            class="rounded-sm text-sm font-semibold text-acode-primary underline decoration-acode-tech/50 underline-offset-2 hover:text-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech [overflow-wrap:anywhere]"
                                        >
                                            {{ $exam->title }}
                                        </a>
                                        <p class="mt-1 text-xs font-normal text-slate-600">
                                            {{ $sectionsCount }} {{ $sectionsCount === 1 ? 'apartado' : 'apartados' }} · {{ $questionsCount }} {{ $questionsCount === 1 ? 'reactivo' : 'reactivos' }}<br>
                                            <span class="font-semibold tabular-nums">{{ number_format($totalWeight, 2) }} / 100.00 pts</span> · Escala {{ $exam->grading_scale }}
                                        </p>
                                        @if (filled($filters['search'] ?? null) && filled($exam->description))
                                            <p class="mt-1 line-clamp-2 text-xs font-normal text-slate-600 [overflow-wrap:anywhere]">
                                                {{ \Illuminate\Support\Str::limit($exam->description, 90) }}
                                            </p>
                                        @endif
                                    </th>

                                    <x-table.td align="center" class="align-top">
                                        <div class="flex flex-col items-center gap-1">
                                            <x-badge :variant="$badgeMeta['variant']">{{ $badgeMeta['label'] }}</x-badge>
                                            @if ($pendingReview > 0)
                                                <a
                                                    href="{{ route('exams.revision.index', ['exam' => $exam, 'status' => 'pending_recoteje']) }}"
                                                    aria-label="{{ $pendingReview }} por revisar en {{ $exam->title }}"
                                                    class="inline-flex min-h-8 items-center rounded-full underline decoration-acode-tech/50 underline-offset-2 hover:decoration-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
                                                >
                                                    <x-badge variant="warning">{{ $pendingReview }} por revisar</x-badge>
                                                </a>
                                            @elseif ($attemptsCount > 0)
                                                <span class="text-xs text-slate-600">
                                                    {{ $attemptsCount }} {{ $attemptsCount === 1 ? 'intento' : 'intentos' }}
                                                </span>
                                            @endif
                                        </div>
                                    </x-table.td>

                                    <x-table.td class="align-top">
                                        <ul class="flex flex-wrap gap-1.5">
                                            @forelse ($exam->groups as $group)
                                                <li class="min-w-0 max-w-full">
                                                    <x-badge variant="info">
                                                        <span class="min-w-0 [overflow-wrap:anywhere]">
                                                            {{ $groupLabels->get($group->id, $group->name) }}
                                                        </span>
                                                    </x-badge>
                                                </li>
                                            @empty
                                                <li class="text-xs text-slate-600">Sin grupos asignados</li>
                                            @endforelse
                                        </ul>
                                    </x-table.td>

                                    <x-table.td class="space-y-1 whitespace-nowrap align-top text-xs tabular-nums">
                                        <p><span class="font-semibold">Inicio:</span> {{ $exam->starts_at?->format('d/m/Y H:i') ?? '—' }}</p>
                                        <p><span class="font-semibold">Cierre:</span> {{ $exam->ends_at?->format('d/m/Y H:i') ?? '—' }}</p>
                                    </x-table.td>

                                    <x-table.td align="center" class="align-top">
                                        <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                            <x-button :href="$rowUrl" variant="secondary" size="sm" :aria-label="$rowLabel . ' en ' . $exam->title">
                                                {{ $rowLabel }}
                                            </x-button>
                                            <x-button
                                                type="button"
                                                variant="neutral"
                                                size="sm"
                                                :aria-label="'Más acciones de ' . $exam->title"
                                                aria-haspopup="dialog"
                                                :loading-when="$menuSubmitting"
                                                x-bind:aria-busy="{{ $menuSubmitting }}"
                                                class="min-w-12"
                                                ::disabled="submittingDialog !== null"
                                                @click="$dispatch('open-modal', '{{ $menuName }}')"
                                            >
                                                <span x-show="!({{ $menuSubmitting }})">Más</span>
                                                <span x-show="{{ $menuSubmitting }}" x-cloak class="sr-only">Enviando…</span>
                                            </x-button>
                                        </div>

                                        <x-modal :name="$menuName" :title="'Más acciones de ' . $exam->title" max-width="sm">
                                            <div class="flex flex-col gap-2">
                                                @foreach ($moreLinks as $label => $url)
                                                    <x-button :href="$url" variant="neutral" size="sm">{{ $label }}</x-button>
                                                @endforeach
                                            </div>
                                            @if ($changes !== [])
                                                <div class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-4">
                                                    @foreach ($changes as $action)
                                                        <x-button
                                                            type="button"
                                                            :variant="$action['isDanger'] ? 'danger' : 'neutral'"
                                                            size="sm"
                                                            @click="requestExamAction('{{ $menuName }}', {{ \Illuminate\Support\Js::from($action) }})"
                                                        >
                                                            {{ $action['label'] }}
                                                        </x-button>

                                                        <form id="{{ $action['formId'] }}" method="POST" action="{{ $action['url'] }}" class="hidden">
                                                            @csrf
                                                            @if ($action['method'] !== 'POST')
                                                                @method($action['method'])
                                                            @endif
                                                        </form>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </x-modal>
                                    </x-table.td>
                                </tr>
                            @endforeach
                        </x-table>
                    </div>

                    @if ($exams->hasPages())
                        <div class="flex justify-end">
                            {{ $exams->links() }}
                        </div>
                    @endif
                @endif
            </section>
        @endfragment

        <x-catalog-summary title="Resumen de exámenes">
            <x-slot:actions>
                <a href="{{ route('teacher.reviews.index') }}" class="inline-flex min-h-8 items-center rounded-sm text-xs font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                    Abrir centro de revisiones
                </a>
            </x-slot:actions>

            <x-summary-item label="Total de exámenes" :value="(int) ($stats['total'] ?? 0)" icon="document"
                :note="(int) ($stats['closed'] ?? 0) . ' cerrados'" />
            <x-summary-item label="Borradores" :value="(int) ($stats['drafts'] ?? 0)" icon="edit" />
            <x-summary-item label="Activos ahora" :value="$activeExams" icon="success"
                :note="(int) ($stats['scheduled'] ?? 0) . ' programados'" />
            <x-summary-item label="Exámenes pendientes de revisión" :value="$pendingReviewExams" icon="clock">
                @if ($pendingReviewExams > 0)
                    <a href="{{ route('exams.index', ['status' => 'pending_review']) }}" class="inline-flex min-h-8 items-center rounded-sm font-semibold text-acode-tech underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                        Ver pendientes de revisión
                    </a>
                @endif
            </x-summary-item>
        </x-catalog-summary>
    </div>
@endsection
