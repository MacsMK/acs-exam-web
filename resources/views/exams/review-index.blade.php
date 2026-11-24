{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review-index.blade.php (MPW-V3 §4.1.2, §7, §8, RF-IAR-002, RW-11) --}}
@extends('layouts.app')

@section('title', 'Revisiones de Examen: ' . $exam->title)

@section('content')
    @php
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $hasActiveFilters = $search !== '' || $status !== 'all';
        $scopeQuery = array_intersect_key($returnListQuery, array_flip(['group_id', 'center_search', 'center_status', 'center_page']));
        $reviewUrl = route('exams.revision.index', $exam);
        $clearUrl = route('exams.revision.index', [$exam, ...$scopeQuery]);
        $attemptCount = $attempts->total();
        $resultStatus = $attemptCount === 1 ? '1 intento encontrado' : $attemptCount . ' intentos encontrados';

        $attemptStatusLabels = [
            'in_progress' => 'En progreso',
            'submitted' => 'Entregado',
            'expired' => 'Expirado',
            'cancelled_by_incident' => 'Cancelado por incidencias',
            'graded' => 'Calificado',
        ];
    @endphp

    <div
        class="space-y-6"
        x-data="{
            actionMessage: '',
            confirmingReapplication: false,
            pendingReapplication: null,
            async confirmReapplication(button) {
                if (this.confirmingReapplication || this.pendingReapplication) return;

                this.actionMessage = '';
                const form = button.form;
                const text = 'Se conservará el intento actual con sus resultados. ' + button.dataset.studentName + ' podrá presentar el examen de nuevo mientras siga abierto. Si lo presenta otra vez, contará la calificación del nuevo intento.';
                this.confirmingReapplication = true;
                this.$dispatch('open-modal');

                try {
                    const result = await window.AcodeAlert.confirm({
                        title: '¿Autorizar reaplicación?',
                        text,
                        confirmButtonText: 'Autorizar',
                        cancelButtonText: 'Cancelar',
                        focusCancel: true,
                    });

                    if (result.isConfirmed && form.isConnected) {
                        this.pendingReapplication = form.id;
                        this.$nextTick(() => form.requestSubmit());
                    }
                } catch {
                    this.actionMessage = 'No se pudo abrir la confirmación. Vuelve a intentar la reaplicación.';
                } finally {
                    this.confirmingReapplication = false;
                }
            }
        }"
        x-on:pageshow.window="pendingReapplication = null"
    >
        <x-page-header
            :title="$exam->title"
            subtitle="Abre una entrega para revisar sus respuestas y guardar la calificación."
            :breadcrumbs="[
                ['label' => $reviewGroup?->name ?? 'Revisión', 'href' => $reviewBackUrl],
                ['label' => $exam->title],
            ]"
            institutional
        >
            <x-slot:actions>
                <x-button :href="$reviewBackUrl" variant="neutral">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-search-filter
            :action="$reviewUrl"
            search-label="Buscar estudiante"
            :search-value="$search"
            placeholder="Buscar por nombre o correo…"
            results-id="exam-review-attempt-results"
            :clear-url="$clearUrl"
            :has-active-filters="$hasActiveFilters"
        >
            <x-slot:parameters>
                @foreach ($scopeQuery as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </x-slot:parameters>

            <x-slot:filters>
                <x-form.group name="status" label="Estado" class="sm:w-56">
                    <x-form.select name="status" size="sm">
                        <option value="all" @selected($status === 'all')>Todos los intentos</option>
                        <option value="pending_recoteje" @selected($status === 'pending_recoteje')>Pendiente de revisión</option>
                        <option value="graded" @selected($status === 'graded')>Con calificación final vigente</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelados por incidencias</option>
                    </x-form.select>
                </x-form.group>
            </x-slot:filters>
        </x-search-filter>

        <p x-show="actionMessage" x-cloak x-text="actionMessage" role="alert" class="text-sm font-medium text-acode-danger-dark"></p>

        @if ($errors->has('reapplication'))
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger-dark" role="alert">
                {{ $errors->first('reapplication') }}
            </div>
        @endif

        @fragment('exam-review-attempt-results')
            <section
                id="exam-review-attempt-results"
                class="space-y-4"
                aria-label="Intentos del examen"
                tabindex="-1"
                data-has-active-filters="{{ $hasActiveFilters ? '1' : '0' }}"
                data-search-status="{{ $resultStatus }}"
            >
                @if ($attempts->isEmpty())
                    <x-empty-state
                        :icon="$hasActiveFilters ? 'search' : 'document'"
                        :title="$hasActiveFilters ? 'Sin intentos para esos filtros' : 'Sin intentos registrados'"
                        :message="$hasActiveFilters
                            ? 'Cambia la búsqueda o limpia los filtros para ver los intentos.'
                            : 'Los intentos aparecerán aquí cuando tus estudiantes comiencen el examen.'"
                    >
                        @if ($hasActiveFilters)
                            <x-button :href="$clearUrl" variant="secondary" size="sm">
                                Limpiar filtros
                            </x-button>
                        @endif
                    </x-empty-state>
                @else
                    <p class="text-sm text-slate-700">{{ $resultStatus }}</p>
                    <p class="text-xs text-slate-700 xl:hidden">Desplaza la tabla para ver todas las columnas.</p>
                    <x-table zebra="slate" scroll-label="Intentos del examen con desplazamiento horizontal" table-class="min-w-[56rem]">
                        <x-slot:header>
                            <x-table.th align="center">Estudiante</x-table.th>
                            <x-table.th align="center">Estado</x-table.th>
                            <x-table.th align="center">Fecha de envío</x-table.th>
                            <x-table.th align="center">Puntaje objetivo (pts)</x-table.th>
                            <x-table.th align="center">Estimado IA (pts)</x-table.th>
                            <x-table.th align="center">Calificación final ({{ str_replace('-', '–', $exam->grading_scale) }})</x-table.th>
                            <x-table.th align="center">Acciones</x-table.th>
                        </x-slot:header>

                        @foreach ($attempts as $attempt)
                            @php
                                $submitted = $attempt->status === 'submitted';
                                $pending = (bool) $attempt->review_pending;
                                $suggestedScore = $attempt->aiSuggestedScore();
                                $isReplaced = (int) $attempt->attempt_number < (int) ($latestAttemptNumbers[$attempt->student_id] ?? $attempt->attempt_number);
                                $canReapply = ! $isReplaced
                                    && $reapplicationOpen
                                    && $attempt->status !== 'in_progress'
                                    && $attempt->reapplication_authorized_at === null;
                                $studentName = $attempt->student?->full_name ?? 'Estudiante';
                                $reviewLabel = $submitted ? 'Revisar entrega' : 'Ver intento';
                                $detailParams = [$exam, $attempt, ...$returnListQuery];
                                $reapplyFormId = 'reapply-form-' . $attempt->id;
                            @endphp

                            <tr>
                                <x-table.td>
                                    <div class="font-semibold text-acode-primary">{{ $studentName }}</div>
                                    @if ($attempt->student?->email)
                                        <div class="break-all text-xs text-slate-600">{{ $attempt->student->email }}</div>
                                    @endif
                                    @if ((int) $attempt->attempt_number > 1 || $isReplaced)
                                        <div class="mt-1 text-xs text-slate-600">
                                            Intento {{ $attempt->attempt_number }}{{ $isReplaced ? ' (reemplazado)' : '' }}
                                        </div>
                                    @endif
                                </x-table.td>

                                <x-table.td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($submitted)
                                            <x-badge variant="accent">Entregado</x-badge>
                                        @elseif ($attempt->status === 'cancelled_by_incident')
                                            <x-badge variant="danger" class="!text-acode-danger-dark">Cancelado por incidencias</x-badge>
                                        @elseif ($attempt->status === 'expired')
                                            <x-badge variant="warning">{{ $attempt->wasStoppedByTeacher() ? 'Detenido por el docente' : 'Expirado' }}</x-badge>
                                        @else
                                            <x-badge variant="neutral">{{ $attemptStatusLabels[$attempt->status] ?? 'En curso' }}</x-badge>
                                        @endif

                                        @if ($pending)
                                            <x-badge variant="warning">Pendiente de revisión</x-badge>
                                        @endif

                                        @if ($attempt->reapplication_authorized_at !== null && ! $isReplaced)
                                            <x-badge variant="neutral">Reaplicación autorizada</x-badge>
                                        @endif
                                    </div>
                                </x-table.td>

                                <x-table.td class="whitespace-nowrap text-xs text-slate-600">
                                    @if ($attempt->submitted_at)
                                        <time datetime="{{ $attempt->submitted_at->toIso8601String() }}">{{ $attempt->submitted_at->format('d/m/Y H:i') }}</time>
                                    @else
                                        Sin envío formal
                                    @endif
                                </x-table.td>

                                <x-table.td align="right" class="tabular-nums">
                                    {{ number_format((float) $attempt->objective_score, 2, ',', '.') }}
                                </x-table.td>

                                <x-table.td align="right" class="tabular-nums">
                                    @if ($suggestedScore !== null)
                                        {{ number_format($suggestedScore, 2, ',', '.') }}
                                    @else
                                        <span aria-hidden="true">—</span>
                                        <span class="sr-only">Sin estimación de IA</span>
                                    @endif
                                </x-table.td>

                                <x-table.td align="right" class="tabular-nums">
                                    @if ($attempt->final_score !== null)
                                        <strong class="text-acode-primary">{{ number_format((float) $attempt->final_score, 2, ',', '.') }}</strong>
                                    @else
                                        {{ $pending ? 'Pendiente' : 'Sin calificación' }}
                                    @endif
                                </x-table.td>

                                <x-table.td align="center">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <x-button
                                            :href="route('exams.revision.show', $detailParams)"
                                            :variant="$submitted ? 'primary' : 'neutral'"
                                            :aria-label="$reviewLabel . ' de ' . $studentName"
                                            size="sm"
                                            class="whitespace-nowrap"
                                        >
                                            {{ $reviewLabel }}
                                        </x-button>

                                        @if ($canReapply)
                                            <form id="{{ $reapplyFormId }}" method="POST" action="{{ route('exams.revision.reapply', $detailParams) }}">
                                                @csrf
                                                <x-button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    class="whitespace-nowrap"
                                                    :aria-label="'Autorizar reaplicación a ' . $studentName"
                                                    :data-student-name="$studentName"
                                                    loading-when="pendingReapplication === $el.closest('form').id"
                                                    ::aria-busy="pendingReapplication === $el.form.id"
                                                    ::disabled="pendingReapplication !== null"
                                                    x-on:click="confirmReapplication($el)"
                                                >
                                                    Autorizar reaplicación
                                                </x-button>
                                            </form>
                                        @endif
                                    </div>
                                </x-table.td>
                            </tr>
                        @endforeach
                    </x-table>

                    @if ($attempts->hasPages())
                        <div class="flex justify-end">
                            {{ $attempts->links() }}
                        </div>
                    @endif
                @endif

                <x-catalog-summary :title="$hasActiveFilters ? 'Resumen de los intentos filtrados' : 'Resumen de intentos'">
                    <x-summary-item label="Intentos registrados" :value="$reviewStats['registered']" icon="document" />
                    <x-summary-item label="Intentos pendientes de revisión" :value="$reviewStats['pending']" icon="clock" />
                    <x-summary-item label="Calificaciones finales vigentes" :value="$reviewStats['final']" icon="success" />
                </x-catalog-summary>
            </section>
        @endfragment
    </div>
@endsection
