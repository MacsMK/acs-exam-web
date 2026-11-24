{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/assemble.blade.php (MPW-V3 §4.1.2, §7, §8, RW-12..14, ADR-027) --}}
@extends('layouts.app')

@section('title', 'Armar reactivos: ' . $exam->title)

@section('content')
    @php
        $questionTypes = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Programación',
        ];
        $typeVariants = [
            'multiple_choice' => 'info',
            'matching' => 'accent',
            'open' => 'warning',
            'code' => 'primary',
        ];

        $totalQuestions = $exam->sections->sum(fn ($section) => $section->examQuestions->count());
        $totalSeconds = (int) $exam->sections->sum(fn ($section) => $section->examQuestions->sum('time_limit_seconds'));
        $hasProposal = is_array($proposal) && ! empty($proposal['question_ids']);
        $manualSelectionSaved = (bool) session('manual_selection_saved');
        $editingSection = (int) old('editing_section_id', 0);
        $manualSections = (object) $exam->sections->pluck('question_type', 'id')->all();
        $assignedQuestionIds = $exam->sections->flatMap->examQuestions->pluck('question_id')->map('strval')->values();
        $defaultManualTarget = $exam->sections->isEmpty() ? 'new' : 'existing';
        $initialManualTarget = old('manual_target_mode', $defaultManualTarget);
        $initialManualTarget = in_array($initialManualTarget, ['existing', 'new'], true) ? $initialManualTarget : $defaultManualTarget;
        $initialManualTitle = old('new_section_title', '');

        $hasManualFilters = filled($manualFilters['search'] ?? null)
            || filled($manualFilters['question_type'] ?? null)
            || filled($manualFilters['subject_tag'] ?? null)
            || filled($manualFilters['subject_id'] ?? null)
            || request()->integer('page', 1) > 1;

        $hasManualErrors = $errors->has('question_ids') || $errors->has('question_ids.*')
            || $errors->has('exam_section_id') || $errors->has('new_section_title');
        $defaultTab = ($manualSelectionSaved || request()->query('mode') === 'manual' || $hasManualFilters || $hasManualErrors)
            ? 'manual'
            : 'draw';
        $initialCandidates = $manualCandidates->getCollection()->map(fn ($candidate): array => [
            'id' => (string) $candidate->id,
            'type' => $candidate->question_type,
            'title' => $candidate->title,
        ])->values();

        $inventorySubjects = collect($bankInventory['subjects'] ?? []);
        $inventoryNamesLower = $inventorySubjects->map(fn (array $item): string => mb_strtolower($item['name']))->all();
        $extraSubjects = collect($subjects ?? [])->filter(
            fn (string $name): bool => ! in_array(mb_strtolower($name), $inventoryNamesLower, true)
        );
        $maxDrawQuantity = \App\Http\Requests\ExamSectionStoreRequest::MAX_QUANTITY;
        $defaultDrawQuantity = min(5, $maxDrawQuantity, (int) ($bankInventory['by_type']['multiple_choice'] ?? 0));
        $initialDrawQuantity = old('requested_quantity', $defaultDrawQuantity ?: '');
        $initialDrawQuantity = is_scalar($initialDrawQuantity) ? (string) $initialDrawQuantity : '';
    @endphp

    <div
        class="space-y-6"
        x-data="{
            activeTab: @js($defaultTab),
            bankInventory: @js($bankInventory),
            drawType: @js(old('question_type', 'multiple_choice')),
            selectedSubjectKey: @js(old('subject_id') ? 'id:' . old('subject_id') : ''),
            selectedSubjectTagInput: @js(old('subject_tag', '')),
            requestedQty: @js($initialDrawQuantity),
            submission: null,
            confirmationOpen: false,
            confirmationUnavailable: false,
            rowQuestion: null,
            pendingRowConfirmation: null,
            manualTargetMode: @js($initialManualTarget),
            manualSectionId: @js((string) ((int) old('exam_section_id', 0) ?: '')),
            manualSectionTitle: @js(is_string($initialManualTitle) ? $initialManualTitle : ''),
            manualSections: @js($manualSections),
            questionTypes: @js($questionTypes),
            selectedManual: [],
            selectionKey: @js('acode-exam-assembly:' . $exam->teacher_id . ':' . $exam->id),
            editingSectionId: @js($editingSection ?: null),

            init() {
                this.$watch('drawQuantityLimit', () => this.syncMaxQuantity());
                this.restoreManualSelection();
                this.$watch('manualTargetMode', () => {
                    this.checkManualDestination();
                    this.saveManualSelection();
                });
                this.$watch('manualSectionId', () => this.saveManualSelection());
                this.$watch('manualSectionTitle', () => this.saveManualSelection());
            },

            setTab(tab, focus = false) {
                this.activeTab = tab;
                if (focus) this.$nextTick(() => this.$refs[tab === 'draw' ? 'drawTab' : 'manualTab'].focus());
            },

            restoreManualSelection() {
                if (@js($manualSelectionSaved || !$canEditStructure)) {
                    try { sessionStorage.removeItem(this.selectionKey); } catch {}
                    return;
                }

                let saved = {};
                try { saved = JSON.parse(sessionStorage.getItem(this.selectionKey)) ?? {}; } catch {}

                const assigned = new Set(@js($assignedQuestionIds));
                const submittedIds = @js(array_map('strval', array_filter((array) old('question_ids', []), 'is_scalar')));
                const savedCandidates = Array.isArray(saved.candidates) ? saved.candidates : [];
                const restoreCandidates = @js($hasManualErrors)
                    ? [...new Map([...savedCandidates, ...@js($initialCandidates)].map(item => [item.id, item])).values()]
                        .filter(item => submittedIds.includes(item.id))
                    : savedCandidates;

                this.selectedManual = restoreCandidates.filter(item => !assigned.has(item.id));

                if (!@js($hasManualErrors)) {
                    this.manualTargetMode = saved.mode ?? this.manualTargetMode;
                    this.manualSectionId = saved.sectionId ?? this.manualSectionId;
                    this.manualSectionTitle = saved.title ?? this.manualSectionTitle;
                }
                if (!Object.keys(this.manualSections).length) this.manualTargetMode = 'new';
                if (!this.manualSections[this.manualSectionId]) this.manualSectionId = '';
                this.checkManualDestination();
            },

            saveManualSelection() {
                const selection = {
                    candidates: this.selectedManual,
                    mode: this.manualTargetMode,
                    sectionId: this.manualSectionId,
                    title: this.manualSectionTitle,
                };
                try { sessionStorage.setItem(this.selectionKey, JSON.stringify(selection)); } catch {}
            },

            get selectedManualType() {
                return this.selectedManual[0]?.type ?? '';
            },

            get requiredManualType() {
                return this.selectedManualType || (this.manualTargetMode === 'existing' ? this.manualSections[this.manualSectionId] : '') || '';
            },

            get manualCanSubmit() {
                if (!this.selectedManualType) return false;
                if (this.manualTargetMode === 'new') return this.manualSectionTitle.trim().length > 0;
                return this.manualSections[this.manualSectionId] === this.selectedManualType;
            },

            checkManualDestination() {
                if (this.manualTargetMode === 'existing' && this.selectedManualType && this.manualSections[this.manualSectionId] !== this.selectedManualType) {
                    this.manualSectionId = '';
                }
            },

            isManualCandidateSelected(id) {
                return this.selectedManual.some(candidate => candidate.id === id);
            },

            toggleManualCandidate(candidate, checked) {
                if (!checked) {
                    this.selectedManual = this.selectedManual.filter(item => item.id !== candidate.id);
                } else if (!this.isManualCandidateSelected(candidate.id) && (!this.requiredManualType || this.requiredManualType === candidate.type)) {
                    this.selectedManual.push(candidate);
                }
                this.saveManualSelection();
            },

            clearManualSelection() {
                this.selectedManual = [];
                this.saveManualSelection();
            },

            focusManualSelection() {
                this.$nextTick(() => {
                    const target = this.selectedManual.length ? this.$refs.selectionSummary : document.getElementById('manual-candidate-results');
                    target?.focus();
                });
            },

            get resolvedSubjectId() {
                if (this.selectedSubjectKey.startsWith('id:')) {
                    return this.selectedSubjectKey.slice(3);
                }
                return '';
            },

            get resolvedSubjectTag() {
                if (this.selectedSubjectKey.startsWith('tag:')) {
                    const opt = this.$refs.subjectSelect?.selectedOptions?.[0];
                    return opt?.dataset?.label || '';
                }
                if (this.selectedSubjectKey.startsWith('raw:')) {
                    return this.selectedSubjectKey.slice(4);
                }
                if (this.selectedSubjectKey.startsWith('id:')) {
                    const opt = this.$refs.subjectSelect?.selectedOptions?.[0];
                    return opt?.dataset?.label || '';
                }
                return '';
            },

            get availableCount() {
                if (!this.selectedSubjectKey) {
                    return Number(this.bankInventory?.by_type?.[this.drawType] ?? 0);
                }
                if (this.selectedSubjectKey.startsWith('id:')) {
                    const key = `${this.drawType}::${this.selectedSubjectKey}`;
                    if (this.bankInventory?.by_type_and_subject?.[key] !== undefined) {
                        return Number(this.bankInventory.by_type_and_subject[key]);
                    }
                    const opt = this.$refs.subjectSelect?.selectedOptions?.[0];
                    const norm = opt?.dataset?.normalized || '';
                    return Number(this.bankInventory?.by_type_and_subject?.[`${this.drawType}::${norm}`] ?? 0);
                }
                if (this.selectedSubjectKey.startsWith('tag:')) {
                    const norm = this.selectedSubjectKey.slice(4);
                    return Number(this.bankInventory?.by_type_and_subject?.[`${this.drawType}::${norm}`] ?? 0);
                }
                return 0;
            },

            get drawQuantityLimit() {
                return Math.min(this.availableCount, @js($maxDrawQuantity));
            },

            syncMaxQuantity() {
                const limit = this.drawQuantityLimit;
                if (limit > 0 && /^[0-9]+$/.test(this.requestedQty) && Number(this.requestedQty) > limit) {
                    this.requestedQty = String(limit);
                }
                this.$nextTick(() => window.academicNumber?.validate(this.$refs.drawQuantity));
            },

            trackSubmission(event) {
                const form = event.target;
                if (form.method !== 'post' || event.defaultPrevented) return;
                if (this.submission) {
                    event.preventDefault();
                    return;
                }
                this.submission = form.id;
            },

            openQuestionActions(question, trigger) {
                this.rowQuestion = question;
                trigger.dispatchEvent(new CustomEvent('open-modal', { bubbles: true, detail: 'assembly-question-actions' }));
            },

            selectQuestionAction(action) {
                this.pendingRowConfirmation = action;
                this.$dispatch('close-modal', 'assembly-question-actions');
            },

            confirmQuestionAction(modal) {
                if (modal !== 'assembly-question-actions' || !this.pendingRowConfirmation) return;

                const { id, title } = this.rowQuestion;
                const action = this.pendingRowConfirmation;
                this.pendingRowConfirmation = null;
                if (action === 'replace') {
                    this.confirmAction('replace-eq-' + id, '¿Sustituir reactivo?', 'Se reemplazará ' + title + ' por otro reactivo. Sus puntos y tiempo se conservarán.', 'Sustituir reactivo', false);
                } else {
                    this.confirmAction('remove-eq-' + id, '¿Eliminar reactivo del apartado?', title + ' se quitará de este apartado y seguirá en tu banco.', 'Eliminar');
                }
            },

            async confirmAction(formId, title, text, confirmLabel, isDanger = true) {
                if (this.submission || this.confirmationOpen) return;
                this.confirmationUnavailable = false;
                this.confirmationOpen = true;
                try {
                    const result = await window.AcodeAlert.confirm({
                        title,
                        text,
                        confirmButtonText: confirmLabel,
                        cancelButtonText: 'Cancelar',
                        isDanger,
                    });
                    if (result.isConfirmed) document.getElementById(formId).requestSubmit();
                } catch {
                    this.confirmationUnavailable = true;
                    this.$nextTick(() => this.$refs.confirmationError.focus());
                } finally {
                    this.confirmationOpen = false;
                }
            }
        }"
        @submit="trackSubmission($event)"
        @modal-closed="confirmQuestionAction($event.detail)"
        @pageshow.window="submission = null"
    >
        {{-- Encabezado --}}
        <x-page-header
            :title="$exam->title"
            subtitle="Construye apartados con sorteo equitativo por banco o selecciona reactivos manualmente."
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => $exam->title],
                ['label' => 'Armar reactivos'],
            ]"
        >
            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
                <x-button :href="route('exams.edit', $exam)" variant="neutral" size="md">
                    <x-icon name="edit" class="w-4 h-4" />
                    Editar datos
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @include('exams.partials._stepper', ['currentStep' => 2, 'exam' => $exam])

        <div x-show="confirmationUnavailable" x-cloak x-ref="confirmationError" tabindex="-1"
             class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-acode-danger/30 bg-white px-4 py-3 text-sm text-acode-danger-dark focus:outline focus:outline-2 focus:outline-acode-danger-dark">
            <p role="alert">No se pudo abrir la confirmación. Recarga la página e inténtalo de nuevo.</p>
            <x-button :href="request()->fullUrl()" variant="primary" size="sm">Reintentar</x-button>
        </div>
        <p class="sr-only" role="status" x-text="submission ? 'Enviando los cambios…' : ''"></p>

        @if ($proposalWarning ?? null)
            <p class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-4 py-3 text-sm text-acode-primary" role="status">
                {{ $proposalWarning }}
            </p>
        @endif

        @if (! $canEditStructure)
            <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-4 py-3 text-sm text-acode-primary flex items-start gap-2.5">
                <x-icon name="lock" class="w-5 h-5 shrink-0 text-acode-warning-dark mt-0.5" />
                <div>
                    <p class="font-semibold">Estructura bloqueada por integridad académica</p>
                    <p class="text-xs text-slate-700 mt-0.5">
                        Este examen ya registra intentos de estudiantes o su aplicación publicada ya inició/concluyó, por lo que no puedes agregar, quitar ni re-sortear reactivos.
                    </p>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger-dark" role="alert">
                <p class="font-semibold mb-1">Revisa los siguientes mensajes:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($hasProposal)
            <x-card id="pending-draw-proposal" class="scroll-mt-24 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <x-section-header
                        title="Previsualización de propuesta"
                        :subtitle="($proposal['title'] ?? 'Apartado propuesto')
                            . ' · ' . ($questionTypes[$proposal['question_type'] ?? ''] ?? 'Reactivos')
                            . ' · ' . (($proposal['subject_tag'] ?? null) ?: 'Todas las materias')
                            . ' · ' . count($proposal['question_ids']) . ' reactivos'"
                        institutional
                    />
                    <x-badge variant="info">Pendiente</x-badge>
                </div>

                <ol class="mt-4 max-h-64 divide-y divide-slate-200 overflow-y-auto rounded-xl border border-slate-200 bg-white"
                    aria-label="Reactivos de la propuesta" tabindex="0">
                    @forelse ($proposedQuestions as $proposed)
                        <li class="px-4 py-3">
                            <p class="break-words text-sm font-semibold text-acode-primary">{{ $proposed->title }}</p>
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-700">{{ $proposed->stem }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                {{ $proposed->default_points }} pts base · {{ $proposed->default_time_seconds }} s
                            </p>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-acode-danger-dark">
                            Los reactivos de esta propuesta ya no están disponibles. Cancela la propuesta o crea otra.
                        </li>
                    @endforelse
                </ol>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <form id="accept-draw-proposal" method="POST" action="{{ route('exams.proposals.accept', $exam) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="sm" :disabled="! $canEditStructure || $proposedQuestions->isEmpty()"
                                  loading-when="submission === 'accept-draw-proposal'"
                                  x-bind:disabled="{{ $canEditStructure && $proposedQuestions->isNotEmpty() ? 'submission !== null' : 'true' }}">
                            <x-icon name="check" class="w-4 h-4" x-show="submission !== 'accept-draw-proposal'" />
                            <span x-text="submission === 'accept-draw-proposal' ? 'Guardando…' : 'Aceptar propuesta'">Aceptar propuesta</span>
                        </x-button>
                    </form>

                    <form id="replace-draw-proposal" method="POST" action="{{ route('exams.proposals.generate', $exam) }}">
                        @csrf
                        <input type="hidden" name="title" value="{{ $proposal['title'] ?? '' }}">
                        <input type="hidden" name="question_type" value="{{ $proposal['question_type'] ?? '' }}">
                        <input type="hidden" name="subject_id" value="{{ $proposal['subject_id'] ?? '' }}">
                        <input type="hidden" name="subject_tag" value="{{ $proposal['subject_tag'] ?? '' }}">
                        <input type="hidden" name="requested_quantity" value="{{ $proposal['requested_quantity'] ?? count($proposal['question_ids']) }}">
                        <x-button type="button" variant="secondary" size="sm" :disabled="! $canEditStructure"
                                  loading-when="submission === 'replace-draw-proposal'"
                                  x-bind:disabled="{{ $canEditStructure ? 'submission !== null' : 'true' }}"
                                  @click="confirmAction('replace-draw-proposal', '¿Crear otra propuesta de sorteo?', 'Si se encuentra un nuevo conjunto, reemplazará la propuesta pendiente y conservará los mismos criterios.', 'Crear propuesta de sorteo', false)">
                            <span x-text="submission === 'replace-draw-proposal' ? 'Sorteando…' : 'Crear propuesta de sorteo'">Crear propuesta de sorteo</span>
                        </x-button>
                    </form>

                    <form id="cancel-draw-proposal" method="POST" action="{{ route('exams.proposals.discard', $exam) }}">
                        @csrf
                        <x-button type="submit" variant="neutral" size="sm"
                                  loading-when="submission === 'cancel-draw-proposal'" x-bind:disabled="submission !== null">
                            <span x-text="submission === 'cancel-draw-proposal' ? 'Cancelando…' : 'Cancelar'">Cancelar</span>
                        </x-button>
                    </form>
                </div>
            </x-card>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Columna Izquierda: Constructor Dual (Sorteo Inteligente + Selección Manual) --}}
            <div class="lg:col-span-5 space-y-5">
                <x-card padding="p-0" class="overflow-hidden">
                    {{-- Selector de Pestañas del Constructor Dual --}}
                    <div class="grid grid-cols-2 border-b border-slate-200 bg-slate-50" role="tablist" aria-label="Modo de asignación de reactivos"
                         @keydown.left.prevent="setTab(activeTab === 'draw' ? 'manual' : 'draw', true)"
                         @keydown.right.prevent="setTab(activeTab === 'draw' ? 'manual' : 'draw', true)"
                         @keydown.home.prevent="setTab('draw', true)" @keydown.end.prevent="setTab('manual', true)">
                        <x-tab id="assembly-draw-tab" name="draw" label="Sorteo inteligente" icon="filter"
                               aria-controls="assembly-draw-panel" x-ref="drawTab" x-bind:tabindex="activeTab === 'draw' ? 0 : -1"
                               class="!whitespace-normal justify-center" />
                        <x-tab id="assembly-manual-tab" name="manual" label="Selección manual" icon="check"
                               aria-controls="assembly-manual-panel" x-ref="manualTab" x-bind:tabindex="activeTab === 'manual' ? 0 : -1"
                               class="!whitespace-normal justify-center" />
                    </div>

                    {{-- Pestaña A: Sorteo Inteligente --}}
                    <div id="assembly-draw-panel" role="tabpanel" aria-labelledby="assembly-draw-tab"
                         x-show="activeTab === 'draw'" x-cloak class="p-5 space-y-5">
                        @if ($hasProposal)
                            <p class="text-sm text-slate-600">
                                Revisa la propuesta pendiente arriba. Puedes aceptarla, cancelarla o crear otra con los mismos criterios.
                            </p>
                        @else
                        <form id="create-draw-proposal" method="POST" action="{{ route('exams.proposals.generate', $exam) }}" class="space-y-4"
                              @submit="if (drawQuantityLimit === 0) $event.preventDefault()">
                            @csrf
                            <input type="hidden" name="subject_id" :value="resolvedSubjectId">
                            <input type="hidden" name="subject_tag" :value="resolvedSubjectTag">

                            <x-section-header title="Sortear nuevo apartado"
                                              subtitle="Elige el tipo y la cantidad de reactivos. Podrás revisar la propuesta antes de aceptarla."
                                              institutional />

                            <x-form.group name="title" label="Nombre del apartado" :required="true">
                                <x-form.input
                                    name="title"
                                    value="{{ old('title') }}"
                                    maxlength="150"
                                    required
                                    :disabled="! $canEditStructure"
                                    placeholder="Ej. Fundamentos de Algoritmos"
                                />
                            </x-form.group>

                            <x-form.group name="question_type" label="Tipo de reactivo" :required="true">
                                <x-form.select
                                    name="question_type"
                                    x-model="drawType"
                                    :disabled="! $canEditStructure"
                                >
                                    @foreach ($questionTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}">
                                            {{ $typeLabel }} ({{ (int) ($bankInventory['by_type'][$typeValue] ?? 0) }} disp.)
                                        </option>
                                    @endforeach
                                </x-form.select>
                            </x-form.group>

                            <x-form.group name="subject_filter_ui" label="Materia del banco">
                                <x-form.select
                                    id="subject_filter_ui"
                                    x-ref="subjectSelect"
                                    x-model="selectedSubjectKey"
                                    :disabled="! $canEditStructure"
                                >
                                    <option value="">Todas las materias ({{ (int) ($bankInventory['total'] ?? 0) }} disp.)</option>
                                    @foreach ($inventorySubjects as $invSubject)
                                        @php
                                            $optKey = ! empty($invSubject['subject_id'])
                                                ? 'id:' . $invSubject['subject_id']
                                                : 'tag:' . $invSubject['normalized_name'];
                                        @endphp
                                        <option
                                            value="{{ $optKey }}"
                                            data-label="{{ $invSubject['name'] }}"
                                            data-normalized="{{ $invSubject['normalized_name'] }}"
                                        >
                                            {{ $invSubject['name'] }} ({{ (int) $invSubject['total'] }} disp.)
                                        </option>
                                    @endforeach
                                    @foreach ($extraSubjects as $extraName)
                                        <option value="raw:{{ $extraName }}" data-label="{{ $extraName }}">
                                            {{ $extraName }} (0 disp.)
                                        </option>
                                    @endforeach
                                </x-form.select>
                            </x-form.group>

                            {{-- Indicador en vivo de inventario disponible --}}
                            <div
                                class="rounded-xl border px-3.5 py-3 text-xs flex flex-col gap-2"
                                :class="availableCount > 0 ? 'border-acode-accent/40 bg-acode-accent/10 text-acode-primary' : 'border-acode-warning/60 bg-acode-warning/15 text-acode-primary'"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">Disponibles para este filtro:</span>
                                    <span class="font-mono text-sm font-bold" x-text="availableCount + ' reactivos'"></span>
                                </div>

                                <template x-if="availableCount === 0">
                                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-acode-warning/40">
                                        <span>No hay reactivos libres con estos criterios.</span>
                                        <x-button :href="route('questions.create')" variant="warning" size="sm">
                                            <x-icon name="add" class="w-3.5 h-3.5" />
                                            Crear reactivos
                                        </x-button>
                                    </div>
                                </template>
                            </div>

                            <x-form.group name="requested_quantity" label="Cantidad a sortear" :required="true" accessible>
                                <x-form.input
                                    numeric="integer"
                                    name="requested_quantity"
                                    min="1"
                                    :max="$maxDrawQuantity"
                                    :value="$initialDrawQuantity"
                                    x-bind:max="drawQuantityLimit"
                                    x-model="requestedQty"
                                    x-ref="drawQuantity"
                                    :disabled="! $canEditStructure"
                                    x-bind:disabled="{{ $canEditStructure ? 'drawQuantityLimit === 0' : 'true' }}"
                                    :aria-describedby="$errors->has('requested_quantity') ? 'draw-quantity-help requested_quantity-error' : 'draw-quantity-help'"
                                    required
                                    accessible
                                />
                                <p id="draw-quantity-help" class="text-xs text-slate-600" role="status"
                                   x-text="drawQuantityLimit > 0
                                       ? (drawQuantityLimit === 1 ? 'Puedes sortear 1 reactivo.' : `Puedes sortear de 1 a ${drawQuantityLimit} reactivos.`)
                                       : 'Cambia los filtros para encontrar reactivos disponibles.'">
                                </p>
                            </x-form.group>

                            <x-button
                                type="submit"
                            :variant="$assemblyBlockReason ? 'primary' : 'secondary'"
                                size="md"
                                class="w-full"
                                loading-when="submission === 'create-draw-proposal'"
                                :disabled="! $canEditStructure"
                                x-bind:disabled="{{ $canEditStructure ? 'drawQuantityLimit === 0 || submission !== null' : 'true' }}"
                            >
                                <x-icon name="add" class="w-4 h-4" x-show="submission !== 'create-draw-proposal'" />
                                <span x-text="submission === 'create-draw-proposal' ? 'Generando propuesta…' : 'Crear propuesta de sorteo'">Crear propuesta de sorteo</span>
                            </x-button>
                        </form>
                        @endif
                    </div>

                    <div id="assembly-manual-panel" role="tabpanel" aria-labelledby="assembly-manual-tab"
                         x-show="activeTab === 'manual'" x-cloak class="p-5 space-y-4">
                        <x-section-header title="Selección manual desde el banco"
                                          subtitle="Se excluyen los reactivos usados en este examen y sus versiones."
                                          institutional />

                        <x-search-filter
                            :action="route('exams.assemble', $exam)"
                            :search-value="$manualFilters['search'] ?? ''"
                            search-label="Buscar reactivos"
                            placeholder="Buscar por título o enunciado…"
                            results-id="manual-candidate-results"
                            :has-active-filters="$hasManualFilters"
                            :clear-url="route('exams.assemble', ['exam' => $exam, 'mode' => 'manual'])"
                        >
                            <x-slot:parameters>
                                <input type="hidden" name="mode" value="manual">
                            </x-slot:parameters>

                            <x-slot:filters>
                                <x-form.group name="assembly-manual-question-type" label="Tipo de reactivo" class="flex-1 min-w-36">
                                    <x-form.select name="question_type" id="assembly-manual-question-type" size="sm">
                                        <option value="">Todos los tipos</option>
                                        @foreach ($questionTypes as $typeValue => $typeLabel)
                                            <option value="{{ $typeValue }}" @selected(($manualFilters['question_type'] ?? '') === $typeValue)>
                                                {{ $typeLabel }}
                                            </option>
                                        @endforeach
                                    </x-form.select>
                                </x-form.group>

                                <x-form.group name="assembly-manual-subject" label="Materia del banco" class="flex-1 min-w-36">
                                    <x-form.select name="subject_tag" id="assembly-manual-subject" size="sm">
                                        <option value="">Todas las materias</option>
                                        @foreach ($subjects as $subjectName)
                                            <option value="{{ $subjectName }}" @selected(($manualFilters['subject_tag'] ?? '') === $subjectName)>
                                                {{ $subjectName }}
                                            </option>
                                        @endforeach
                                    </x-form.select>
                                </x-form.group>
                            </x-slot:filters>
                        </x-search-filter>

                        <form id="add-manual-questions" method="POST" action="{{ route('exams.questions.manual.store', $exam) }}"
                              class="space-y-4" @submit="if (!manualCanSubmit) $event.preventDefault()">
                            @csrf
                            <input type="hidden" name="manual_target_mode" :value="manualTargetMode">

                            <fieldset class="rounded-xl border border-slate-200 bg-white px-3.5 pb-3.5 pt-1.5 space-y-3"
                                      @disabled(! $canEditStructure)>
                                <legend class="px-1 text-sm font-semibold text-acode-primary">Apartado destino</legend>
                                <x-toggle-group
                                    model="manualTargetMode"
                                    label="Apartado destino"
                                    :options="$exam->sections->isNotEmpty()
                                        ? ['new' => 'Crear apartado', 'existing' => 'Apartado existente']
                                        : ['new' => 'Crear apartado']"
                                    class="flex-wrap"
                                />

                                <div x-show="manualTargetMode === 'new'">
                                    <x-form.group name="new_section_title" label="Título del nuevo apartado" required accessible>
                                        <x-form.input
                                            name="new_section_title"
                                            x-model="manualSectionTitle"
                                            maxlength="120"
                                            x-bind:disabled="manualTargetMode !== 'new'"
                                            :aria-describedby="$errors->has('new_section_title') ? 'new_section_title-error' : null"
                                            placeholder="Ej. Problemas prácticos"
                                            required
                                            accessible
                                        />
                                    </x-form.group>
                                </div>

                                @if ($exam->sections->isNotEmpty())
                                    <div x-show="manualTargetMode === 'existing'">
                                        <x-form.group name="exam_section_id" label="Apartado existente" required accessible>
                                            <x-form.select
                                                name="exam_section_id"
                                                x-model="manualSectionId"
                                                x-bind:disabled="manualTargetMode !== 'existing'"
                                                :aria-describedby="$errors->has('exam_section_id') ? 'manual-types exam_section_id-error' : 'manual-types'"
                                                required
                                                accessible
                                            >
                                                <option value="">Selecciona un apartado del mismo tipo…</option>
                                                @foreach ($exam->sections as $secOption)
                                                    <option value="{{ $secOption->id }}"
                                                            x-bind:disabled="selectedManualType && selectedManualType !== @js($secOption->question_type)">
                                                        {{ $secOption->title }} ({{ $questionTypes[$secOption->question_type] ?? $secOption->question_type }})
                                                    </option>
                                                @endforeach
                                            </x-form.select>
                                        </x-form.group>
                                    </div>
                                @endif
                            </fieldset>

                            <p id="manual-types" class="text-sm text-slate-600">
                                Cada apartado admite reactivos de un solo tipo.
                                <span class="font-semibold text-acode-primary" x-show="requiredManualType" x-cloak>
                                    Tipo compatible: <span x-text="questionTypes[requiredManualType]"></span>.
                                </span>
                            </p>

                            @if ($hasManualErrors && ($errors->has('question_ids') || $errors->has('question_ids.*')))
                                <p class="text-sm font-medium text-acode-danger-dark" role="alert">
                                    {{ $errors->first('question_ids') ?: $errors->first('question_ids.*') }}
                                </p>
                            @endif

                            <div x-show="selectedManual.length" x-cloak class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-acode-primary" role="status" tabindex="-1" x-ref="selectionSummary"
                                       x-text="selectedManual.length === 1 ? '1 reactivo seleccionado' : selectedManual.length + ' reactivos seleccionados'">
                                    </p>
                                    <x-button type="button" variant="neutral" size="sm" @click="clearManualSelection(); focusManualSelection()">
                                        Limpiar selección
                                    </x-button>
                                </div>
                                <ul class="max-h-36 space-y-1 overflow-y-auto" aria-label="Reactivos seleccionados">
                                    <template x-for="candidate in selectedManual" :key="candidate.id">
                                        <li class="flex items-center justify-between gap-2 text-xs text-slate-700">
                                            <span class="min-w-0 break-words" x-text="candidate.title"></span>
                                            <input type="hidden" name="question_ids[]" :value="candidate.id">
                                            <x-button type="button" variant="neutral" size="sm"
                                                      x-bind:aria-label="'Quitar de la selección: ' + candidate.title"
                                                      @click="toggleManualCandidate(candidate, false); focusManualSelection()">
                                                Quitar
                                            </x-button>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            @fragment('assembly-manual-results')
                            <section id="manual-candidate-results" aria-label="Resultados del banco de reactivos" tabindex="-1"
                                     data-has-active-filters="{{ $hasManualFilters ? 1 : 0 }}"
                                     data-search-status="{{ $manualCandidates->total() }} reactivos disponibles"
                                     class="space-y-2">
                                <p class="text-sm text-slate-600">
                                    {{ $manualCandidates->total() }} {{ $manualCandidates->total() === 1 ? 'reactivo disponible' : 'reactivos disponibles' }}
                                </p>
                                <div class="space-y-2 max-h-[28rem] overflow-y-auto pr-1">
                                    @forelse ($manualCandidates as $candidate)
                                        @php
                                            $candidateOption = [
                                                'id' => (string) $candidate->id,
                                                'type' => $candidate->question_type,
                                                'title' => $candidate->title,
                                            ];
                                        @endphp
                                        <label
                                            class="flex items-start gap-3 rounded-xl border p-3 transition focus-within:ring-2 focus-within:ring-acode-tech"
                                            :class="isManualCandidateSelected(@js($candidateOption['id'])) ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200 bg-white'"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isManualCandidateSelected(@js($candidateOption['id']))"
                                                @change="toggleManualCandidate(@js($candidateOption), $event.target.checked)"
                                                @disabled(! $canEditStructure)
                                                x-bind:disabled="{{ $canEditStructure ? 'Boolean(requiredManualType && requiredManualType !== ' . \Illuminate\Support\Js::from($candidate->question_type) . ')' : 'true' }}"
                                                aria-label="{{ $candidate->title }} — {{ $questionTypes[$candidate->question_type] ?? $candidate->question_type }}"
                                                aria-describedby="manual-types"
                                                class="mt-1 h-4 w-4 rounded-md border-slate-500 text-acode-tech focus:ring-acode-tech"
                                            >
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <x-badge :variant="$typeVariants[$candidate->question_type] ?? 'neutral'">
                                                        {{ $questionTypes[$candidate->question_type] ?? $candidate->question_type }}
                                                    </x-badge>
                                                    <x-badge variant="neutral">
                                                        {{ $candidate->subject?->name ?? $candidate->subject_tag ?? 'Sin materia' }}
                                                    </x-badge>
                                                </div>
                                                <p class="text-sm font-semibold text-acode-primary leading-5">{{ $candidate->title }}</p>
                                                <p class="text-xs text-slate-600 line-clamp-2">
                                                    {{ \Illuminate\Support\Str::limit($candidate->stem, 115) }}
                                                </p>
                                            </div>
                                        </label>
                                    @empty
                                        <x-empty-state
                                            icon="document"
                                            title="Sin reactivos disponibles"
                                            message="No hay candidatos en tu banco para los filtros aplicados."
                                        >
                                            <x-button :href="route('questions.create')" variant="secondary" size="sm">
                                                <x-icon name="add" class="w-4 h-4" />
                                                Crear reactivo
                                            </x-button>
                                        </x-empty-state>
                                    @endforelse
                                </div>

                                @if ($manualCandidates->hasPages())
                                    <div class="pt-2 flex justify-end">
                                        {{ $manualCandidates->appends('mode', 'manual')->links() }}
                                    </div>
                                @endif
                            </section>
                            @endfragment

                            <x-button type="submit" :variant="$hasProposal || !$assemblyBlockReason ? 'secondary' : 'primary'"
                                      size="md" class="w-full" :disabled="! $canEditStructure"
                                      loading-when="submission === 'add-manual-questions'"
                                      x-bind:disabled="{{ $canEditStructure ? '!manualCanSubmit || submission !== null' : 'true' }}">
                                <x-icon name="add" class="w-4 h-4" x-show="submission !== 'add-manual-questions'" />
                                <span x-text="submission === 'add-manual-questions' ? 'Agregando…' : 'Agregar reactivos al examen'">Agregar reactivos al examen</span>
                            </x-button>
                        </form>
                    </div>
                </x-card>
            </div>

            {{-- Columna Derecha: Estructura del Examen --}}
            <div class="lg:col-span-7 space-y-5">
                {{-- Barra de métricas y avance a Etapa 3 --}}
                <x-card padding="p-5">
                    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div class="grid grid-cols-3 gap-4 flex-1">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Apartados</p>
                                <p class="text-xl font-bold text-acode-primary tabular-nums">{{ $exam->sections->count() }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reactivos</p>
                                <p class="text-xl font-bold text-acode-primary tabular-nums">{{ $totalQuestions }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-600">Suma de tiempos por reactivo</p>
                                <p class="text-xl font-bold text-acode-tech font-mono tabular-nums">
                                    {{ intdiv($totalSeconds, 60) }} min {{ $totalSeconds % 60 }} s
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if (!$assemblyBlockReason)
                                <x-button :href="route('exams.configuration', $exam)" variant="primary" size="md">
                                    Continuar a Configuración
                                    <x-icon name="chevronRight" class="w-4 h-4" />
                                </x-button>
                            @else
                                <x-button
                                    type="button"
                                    variant="primary"
                                    size="md"
                                    :disabled="true"
                                    aria-describedby="assembly-block-reason"
                                >
                                    Continuar a Configuración
                                    <x-icon name="chevronRight" class="w-4 h-4" />
                                </x-button>
                            @endif
                        </div>
                    </div>
                    @if ($assemblyBlockReason)
                        <p id="assembly-block-reason" class="mt-3 border-t border-slate-100 pt-3 text-sm font-medium text-acode-primary">
                            {{ $assemblyBlockReason }}
                        </p>
                    @endif
                </x-card>

                {{-- Tarjetas por Apartado --}}
                <div class="space-y-4">
                    <x-section-header title="Apartados y reactivos" institutional />
                    @forelse ($exam->sections as $section)
                        <x-card id="section-{{ $section->id }}" padding="p-0" class="scroll-mt-24">
                            <div class="border-b border-slate-100 bg-slate-50/90 px-5 py-4 space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-semibold text-acode-primary leading-6">{{ $section->title }}</h3>
                                            <x-badge :variant="$typeVariants[$section->question_type] ?? 'neutral'">
                                                {{ $questionTypes[$section->question_type] ?? $section->question_type }}
                                            </x-badge>
                                            @if ($section->subject?->name || $section->subject_tag)
                                                <x-badge variant="neutral">
                                                    {{ $section->subject?->name ?? $section->subject_tag }}
                                                </x-badge>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">
                                            {{ $section->examQuestions->count() }} reactivo(s) asignado(s)
                                        </p>
                                    </div>

                                    @if ($canEditStructure)
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <x-button
                                                type="button"
                                                variant="neutral"
                                                size="sm"
                                                id="edit-section-{{ $section->id }}"
                                                aria-controls="update-section-{{ $section->id }}"
                                                x-bind:aria-expanded="String(editingSectionId === {{ $section->id }})"
                                                x-bind:disabled="submission !== null"
                                                @click="editingSectionId = (editingSectionId === {{ $section->id }} ? null : {{ $section->id }}); if (editingSectionId) $nextTick(() => document.getElementById('section-title-{{ $section->id }}').focus())"
                                            >
                                                <x-icon name="edit" class="w-3.5 h-3.5" />
                                                Editar
                                            </x-button>

                                            <form
                                                id="delete-section-{{ $section->id }}"
                                                method="POST"
                                                action="{{ route('exams.sections.destroy', [$exam, $section]) }}"
                                                class="inline"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <x-button
                                                    type="button"
                                                    variant="danger"
                                                    size="sm"
                                                    loading-when="submission === 'delete-section-{{ $section->id }}'"
                                                    x-bind:disabled="submission !== null"
                                                    @click="confirmAction(
                                                        'delete-section-{{ $section->id }}',
                                                        '¿Eliminar apartado?',
                                                        'Se eliminará el apartado ' + {{ \Illuminate\Support\Js::from($section->title) }} + ' junto con sus reactivos asignados en este examen.',
                                                        'Eliminar',
                                                        true
                                                    )"
                                                >
                                                    <x-icon name="delete" class="w-3.5 h-3.5" x-show="submission !== 'delete-section-{{ $section->id }}'" />
                                                    <span x-text="submission === 'delete-section-{{ $section->id }}' ? 'Eliminando…' : 'Eliminar'">Eliminar</span>
                                                </x-button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                {{-- Edición inline del apartado --}}
                                @if ($canEditStructure)
                                    <form
                                        id="update-section-{{ $section->id }}"
                                        x-show="editingSectionId === {{ $section->id }}"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('exams.sections.update', [$exam, $section]) }}"
                                        class="pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end"
                                    >
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="editing_section_id" value="{{ $section->id }}">
                                        <x-form.group
                                            name="section-title-{{ $section->id }}"
                                            label="Título del apartado"
                                            :error="$editingSection === (int) $section->id ? $errors->first('title') : null"
                                            class="sm:col-span-5"
                                            required accessible
                                        >
                                            <x-form.input
                                                name="title"
                                                id="section-title-{{ $section->id }}"
                                                :aria-describedby="$editingSection === (int) $section->id && $errors->has('title') ? 'section-title-' . $section->id . '-error' : null"
                                                :value="$editingSection === (int) $section->id ? old('title', $section->title) : $section->title"
                                                maxlength="150"
                                                required accessible
                                            />
                                        </x-form.group>
                                        <x-form.group
                                            name="section-subject-{{ $section->id }}"
                                            label="Materia (opcional)"
                                            :error="$editingSection === (int) $section->id ? $errors->first('subject_tag') : null"
                                            class="sm:col-span-4"
                                            accessible
                                        >
                                            <x-form.input
                                                name="subject_tag"
                                                id="section-subject-{{ $section->id }}"
                                                :aria-describedby="$editingSection === (int) $section->id && $errors->has('subject_tag') ? 'section-subject-' . $section->id . '-error' : null"
                                                :value="$editingSection === (int) $section->id ? old('subject_tag', $section->subject?->name ?? $section->subject_tag) : ($section->subject?->name ?? $section->subject_tag)"
                                                maxlength="100"
                                                accessible
                                            />
                                        </x-form.group>
                                        <div class="sm:col-span-3 flex flex-wrap items-center gap-1.5">
                                            <x-button type="submit" variant="primary" size="sm" class="flex-1"
                                                      loading-when="submission === 'update-section-{{ $section->id }}'"
                                                      x-bind:disabled="submission !== null">
                                                <span x-text="submission === 'update-section-{{ $section->id }}' ? 'Guardando…' : 'Guardar'">Guardar</span>
                                            </x-button>
                                            <x-button type="button" variant="neutral" size="sm" x-bind:disabled="submission !== null"
                                                      @click="editingSectionId = null; $nextTick(() => document.getElementById('edit-section-{{ $section->id }}').focus())">
                                                Cancelar
                                            </x-button>
                                        </div>
                                    </form>
                                @endif
                            </div>

                            <div class="divide-y divide-slate-100">
                                @forelse ($section->examQuestions as $examQuestion)
                                    @php
                                        $q = $examQuestion->question;
                                    @endphp
                                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-acode-surface/50 transition">
                                        <div class="flex items-start gap-3 min-w-0">
                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-acode-surface text-xs font-bold text-acode-primary">
                                                {{ $loop->iteration }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-acode-primary break-words">
                                                    {{ $q?->title ?? 'Reactivo no disponible' }}
                                                </p>
                                                @if ($q?->stem)
                                                    <p class="text-xs text-slate-600 mt-0.5 line-clamp-2">
                                                        {{ \Illuminate\Support\Str::limit($q->stem, 130) }}
                                                    </p>
                                                @endif
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs font-mono text-slate-600">
                                                    <span class="font-semibold text-acode-primary">{{ number_format((float) $examQuestion->points_weight, 2) }} pts</span>
                                                    <span>·</span>
                                                    <span>{{ (int) $examQuestion->time_limit_seconds }} s</span>
                                                    @if ($q?->subject?->name || $q?->subject_tag)
                                                        <span>·</span>
                                                        <span class="font-sans text-slate-600">{{ $q?->subject?->name ?? $q?->subject_tag }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        @if ($canEditStructure)
                                            <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                                                {{-- RF-EXA-005: editar solo para este examen --}}
                                                @if ($q && ! $q->trashed())
                                                    <x-button :href="route('exams.questions.edit', [$exam, $examQuestion])" variant="neutral" size="sm" title="Modificar este reactivo solo para este examen">
                                                        Editar
                                                    </x-button>
                                                @endif
                                                <form id="replace-eq-{{ $examQuestion->id }}" method="POST"
                                                      action="{{ route('exams.questions.reshuffle', [$exam, $examQuestion]) }}" hidden>
                                                    @csrf
                                                </form>

                                                <form
                                                    id="remove-eq-{{ $examQuestion->id }}"
                                                    method="POST"
                                                    action="{{ route('exams.questions.destroy', [$exam, $examQuestion]) }}"
                                                    hidden
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <x-button type="button" variant="neutral" size="sm" aria-haspopup="dialog"
                                                          :aria-label="'Más acciones de ' . ($q?->title ?? 'este reactivo')"
                                                          loading-when="submission === 'replace-eq-{{ $examQuestion->id }}' || submission === 'remove-eq-{{ $examQuestion->id }}'"
                                                          x-bind:disabled="submission !== null"
                                                          @click="openQuestionActions({{ \Illuminate\Support\Js::from(['id' => $examQuestion->id, 'title' => $q?->title ?? 'el reactivo seleccionado']) }}, $event.currentTarget)">
                                                    <span x-text="submission === 'replace-eq-{{ $examQuestion->id }}' ? 'Sustituyendo…' : (submission === 'remove-eq-{{ $examQuestion->id }}' ? 'Eliminando…' : 'Más')">Más</span>
                                                </x-button>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-6">
                                        <x-empty-state
                                            icon="document"
                                            title="Apartado sin reactivos"
                                            message="Agrega reactivos desde la pestaña de Selección manual o elimina este apartado."
                                        />
                                    </div>
                                @endforelse
                            </div>
                        </x-card>
                    @empty
                        <x-empty-state
                            icon="document"
                            title="Todavía no hay apartados en este examen"
                            message="Usa el Sorteo inteligente o la Selección manual del panel izquierdo para construir los apartados de tu evaluación."
                        />
                    @endforelse
                </div>
            </div>
        </div>
        @if ($canEditStructure && $totalQuestions > 0)
            <x-modal name="assembly-question-actions" title="Más acciones del reactivo" max-width="sm">
                <p class="break-words font-semibold text-acode-primary" x-text="rowQuestion?.title"></p>
                <p class="mt-2">La sustitución conserva los puntos y tiempo. Al eliminar, el reactivo permanece en el banco.</p>
                <x-slot:footer>
                    <div class="flex flex-wrap justify-end gap-2">
                        <x-button type="button" variant="secondary" size="sm" @click="selectQuestionAction('replace')">
                            Sustituir reactivo
                        </x-button>
                        <x-button type="button" variant="danger" size="sm" @click="selectQuestionAction('remove')">
                            Eliminar
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-modal>
        @endif
    </div>
@endsection
