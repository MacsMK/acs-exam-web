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

        $hasManualFilters = filled($manualFilters['search'] ?? null)
            || filled($manualFilters['question_type'] ?? null)
            || filled($manualFilters['subject_tag'] ?? null)
            || filled($manualFilters['subject_id'] ?? null)
            || request()->has('page');

        $defaultTab = ($hasManualFilters || $errors->has('question_ids') || $errors->has('exam_section_id') || $errors->has('new_section_title'))
            ? 'manual'
            : 'draw';

        $inventorySubjects = collect($bankInventory['subjects'] ?? []);
        $inventoryNamesLower = $inventorySubjects->map(fn (array $item): string => mb_strtolower($item['name']))->all();
        $extraSubjects = collect($subjects ?? [])->filter(
            fn (string $name): bool => ! in_array(mb_strtolower($name), $inventoryNamesLower, true)
        );
    @endphp

    <div
        class="space-y-6"
        x-data="{
            activeTab: @js($defaultTab),
            bankInventory: @js($bankInventory),
            drawType: @js(old('question_type', 'multiple_choice')),
            selectedSubjectKey: @js(old('subject_id') ? 'id:' . old('subject_id') : ''),
            selectedSubjectTagInput: @js(old('subject_tag', '')),
            requestedQty: @js((int) old('requested_quantity', 5)),
            manualTargetMode: @js($exam->sections->isNotEmpty() && old('exam_section_id') !== '' ? 'existing' : 'new'),
            selectedManualIds: @js(array_map('strval', (array) old('question_ids', []))),
            editingSectionId: null,

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

            syncMaxQuantity() {
                const maxAvail = this.availableCount;
                if (maxAvail > 0 && this.requestedQty > maxAvail) {
                    this.requestedQty = maxAvail;
                }
            },

            confirmAction(formId, title, text, confirmLabel = 'Eliminar', isDanger = true) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: title,
                        text: text,
                        confirmButtonText: confirmLabel,
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
            }
        }"
    >
        {{-- Encabezado --}}
        <x-page-header
            :title="$exam->title"
            subtitle="Construye apartados con sorteo equitativo por banco o selecciona reactivos manualmente."
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
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger" role="alert">
                <p class="font-semibold mb-1">Revisa los siguientes mensajes:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Columna Izquierda: Constructor Dual (Sorteo Inteligente + Selección Manual) --}}
            <div class="lg:col-span-5 space-y-5">
                <x-card padding="p-0" class="overflow-hidden">
                    {{-- Selector de Pestañas del Constructor Dual --}}
                    <div class="grid grid-cols-2 border-b border-slate-200 bg-slate-50" role="tablist" aria-label="Modo de asignación de reactivos">
                        <x-tab name="draw" label="Sorteo inteligente" icon="filter" class="justify-center" />
                        <x-tab name="manual" label="Selección manual" icon="check" :count="$manualCandidates->total()" class="justify-center" />
                    </div>

                    {{-- Pestaña A: Sorteo Inteligente --}}
                    <div x-show="activeTab === 'draw'" class="p-5 space-y-5">
                        @if ($hasProposal)
                            <div class="rounded-xl border-2 border-acode-tech bg-acode-surface/60 p-4 space-y-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-xs font-semibold uppercase tracking-wider text-acode-tech">Previsualización de propuesta</span>
                                        <h2 class="text-base font-semibold text-acode-primary mt-0.5">
                                            {{ $proposal['title'] ?? 'Apartado propuesto' }}
                                        </h2>
                                        <p class="text-xs text-slate-600 mt-0.5">
                                            {{ $questionTypes[$proposal['question_type'] ?? ''] ?? 'Reactivos' }}
                                            · {{ ($proposal['subject_tag'] ?? null) ?: 'Todas las materias' }}
                                            · <strong>{{ count($proposal['question_ids']) }} reactivo(s)</strong>
                                        </p>
                                    </div>
                                    <x-badge variant="info">Pendiente</x-badge>
                                </div>

                                <ol class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white max-h-64 overflow-y-auto">
                                    @forelse ($proposedQuestions as $proposed)
                                        <li class="px-3.5 py-2.5 flex items-start gap-2.5 text-xs">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-acode-surface font-bold text-acode-primary">
                                                {{ $loop->iteration }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-semibold text-acode-primary break-words">{{ $proposed->title }}</p>
                                                <p class="text-xs text-slate-500 mt-0.5">
                                                    {{ $proposed->default_points }} pts base · {{ $proposed->default_time_seconds }} s
                                                </p>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="px-3.5 py-3 text-xs text-acode-danger">
                                            Los reactivos de esta propuesta ya no están disponibles. Cancela la propuesta e intenta de nuevo.
                                        </li>
                                    @endforelse
                                </ol>

                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('exams.proposals.accept', $exam) }}">
                                        @csrf
                                        <x-button type="submit" variant="primary" size="sm" :disabled="! $canEditStructure">
                                            <x-icon name="check" class="w-4 h-4" />
                                            Aceptar propuesta
                                        </x-button>
                                    </form>

                                    <form method="POST" action="{{ route('exams.proposals.generate', $exam) }}">
                                        @csrf
                                        <input type="hidden" name="title" value="{{ $proposal['title'] ?? '' }}">
                                        <input type="hidden" name="question_type" value="{{ $proposal['question_type'] ?? '' }}">
                                        <input type="hidden" name="subject_id" value="{{ $proposal['subject_id'] ?? '' }}">
                                        <input type="hidden" name="subject_tag" value="{{ $proposal['subject_tag'] ?? '' }}">
                                        <input type="hidden" name="requested_quantity" value="{{ $proposal['requested_quantity'] ?? count($proposal['question_ids']) }}">
                                        <x-button type="submit" variant="secondary" size="sm" :disabled="! $canEditStructure">
                                            Reintentar sorteo
                                        </x-button>
                                    </form>

                                    <form method="POST" action="{{ route('exams.proposals.discard', $exam) }}">
                                        @csrf
                                        <x-button type="submit" variant="neutral" size="sm">
                                            Cancelar
                                        </x-button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('exams.proposals.generate', $exam) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="subject_id" :value="resolvedSubjectId">
                            <input type="hidden" name="subject_tag" :value="resolvedSubjectTag">

                            <div>
                                <h2 class="text-base font-bold text-acode-primary">Sortear nuevo apartado</h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Selecciona tipo y materia con conteo real de disponibilidad sin duplicar linajes Copy-On-Write.
                                </p>
                            </div>

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
                                    @change="syncMaxQuantity()"
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
                                    @change="syncMaxQuantity()"
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

                            <x-form.group name="requested_quantity" label="Cantidad a sortear" :required="true">
                                <x-form.input
                                    type="number"
                                    name="requested_quantity"
                                    min="1"
                                    x-bind:max="Math.max(1, availableCount)"
                                    x-model.number="requestedQty"
                                    :disabled="! $canEditStructure"
                                    required
                                />
                            </x-form.group>

                            <x-button
                                type="submit"
                                variant="primary"
                                size="md"
                                class="w-full"
                                x-bind:disabled="{{ $canEditStructure ? 'availableCount === 0' : 'true' }}"
                            >
                                <x-icon name="add" class="w-4 h-4" />
                                Crear propuesta de sorteo
                            </x-button>
                        </form>
                    </div>

                    {{-- Pestaña B: Selección Manual (siempre presente en el DOM con x-show) --}}
                    <div x-show="activeTab === 'manual'" class="p-5 space-y-4">
                        <div>
                            <h2 class="text-base font-bold text-acode-primary">Selección manual desde banco</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Marca reactivos específicos disponibles (ya se excluyen los usados en este examen y sus versiones Copy-On-Write).
                            </p>
                        </div>

                        {{-- Filtros GET del banco manual --}}
                        <form method="GET" action="{{ route('exams.assemble', $exam) }}" class="space-y-2.5 rounded-xl border border-slate-200 bg-slate-50/80 p-3">
                            <x-form.input
                                type="text"
                                name="search"
                                size="sm"
                                value="{{ $manualFilters['search'] ?? '' }}"
                                placeholder="Buscar por título o enunciado…"
                            />
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <x-form.select
                                    name="question_type"
                                    size="sm"
                                    aria-label="Filtrar candidatos por tipo"
                                >
                                    <option value="">Todos los tipos</option>
                                    @foreach ($questionTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}" @selected(($manualFilters['question_type'] ?? '') === $typeValue)>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </x-form.select>

                                <x-form.select
                                    name="subject_tag"
                                    size="sm"
                                    aria-label="Filtrar candidatos por materia"
                                >
                                    <option value="">Todas las materias</option>
                                    @foreach ($subjects as $subjectName)
                                        <option value="{{ $subjectName }}" @selected(($manualFilters['subject_tag'] ?? '') === $subjectName)>
                                            {{ $subjectName }}
                                        </option>
                                    @endforeach
                                </x-form.select>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="submit" variant="secondary" size="sm">
                                    <x-icon name="search" class="w-3.5 h-3.5" />
                                    Buscar
                                </x-button>
                                @if ($hasManualFilters)
                                    <a href="{{ route('exams.assemble', $exam) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                                        Limpiar filtros
                                    </a>
                                @endif
                            </div>
                        </form>

                        {{-- Formulario POST para adjuntar reactivos seleccionados --}}
                        <form method="POST" action="{{ route('exams.questions.manual.store', $exam) }}" class="space-y-4">
                            @csrf

                            <div class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Apartado destino</p>

                                <x-toggle-group
                                    model="manualTargetMode"
                                    label="Apartado destino"
                                    :options="$exam->sections->isNotEmpty()
                                        ? ['new' => 'Crear apartado', 'existing' => 'Apartado existente']
                                        : ['new' => 'Crear apartado']"
                                />

                                <div x-show="manualTargetMode === 'new'">
                                    <x-form.input
                                        type="text"
                                        name="new_section_title"
                                        size="sm"
                                        value="{{ old('new_section_title') }}"
                                        maxlength="120"
                                        x-bind:disabled="manualTargetMode !== 'new' || {{ $canEditStructure ? 'false' : 'true' }}"
                                        placeholder="Título del nuevo apartado (ej. Problemas prácticos)"
                                    />
                                </div>

                                @if ($exam->sections->isNotEmpty())
                                    <div x-show="manualTargetMode === 'existing'">
                                        <x-form.select
                                            name="exam_section_id"
                                            size="sm"
                                            x-bind:disabled="manualTargetMode !== 'existing' || {{ $canEditStructure ? 'false' : 'true' }}"
                                        >
                                            <option value="">Selecciona un apartado del mismo tipo…</option>
                                            @foreach ($exam->sections as $secOption)
                                                <option value="{{ $secOption->id }}" @selected((string) old('exam_section_id') === (string) $secOption->id)>
                                                    {{ $secOption->title }} ({{ $questionTypes[$secOption->question_type] ?? $secOption->question_type }})
                                                </option>
                                            @endforeach
                                        </x-form.select>
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-2 max-h-[28rem] overflow-y-auto pr-1">
                                @forelse ($manualCandidates as $candidate)
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition hover:border-acode-tech/60"
                                        :class="selectedManualIds.includes('{{ $candidate->id }}') ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200 bg-white'"
                                    >
                                        <input
                                            type="checkbox"
                                            name="question_ids[]"
                                            value="{{ $candidate->id }}"
                                            x-model="selectedManualIds"
                                            @disabled(! $canEditStructure)
                                            class="mt-1 h-4 w-4 rounded-md border-slate-300 text-acode-tech focus:ring-acode-tech"
                                        >
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <x-badge :variant="$typeVariants[$candidate->question_type] ?? 'neutral'">
                                                    {{ $questionTypes[$candidate->question_type] ?? $candidate->question_type }}
                                                </x-badge>
                                                <x-badge variant="neutral">
                                                    {{ $candidate->subject?->name ?? $candidate->subject_tag ?? 'Sin materia' }}
                                                </x-badge>
                                                <span class="text-xs font-mono text-slate-500 ml-auto">
                                                    {{ (int) ($candidate->paraphrases_count ?? 0) }} paráfrasis
                                                </span>
                                            </div>
                                            <p class="text-sm font-semibold text-acode-primary leading-5">
                                                {{ $candidate->title }}
                                            </p>
                                            <p class="text-xs text-slate-500 line-clamp-2">
                                                {{ \Illuminate\Support\Str::limit($candidate->stem, 115) }}
                                            </p>
                                        </div>
                                    </label>
                                @empty
                                    <x-empty-state
                                        icon="document"
                                        title="Sin reactivos disponibles"
                                        message="No hay candidatos libres en tu banco para los filtros aplicados."
                                    >
                                        <x-button :href="route('questions.create')" variant="primary" size="sm">
                                            <x-icon name="add" class="w-4 h-4" />
                                            Crear reactivo
                                        </x-button>
                                    </x-empty-state>
                                @endforelse
                            </div>

                            @if ($manualCandidates->hasPages())
                                <div class="pt-2 flex justify-end">
                                    {{ $manualCandidates->links() }}
                                </div>
                            @endif

                            <x-button
                                type="submit"
                                variant="primary"
                                size="md"
                                class="w-full"
                                x-bind:disabled="{{ $canEditStructure ? 'selectedManualIds.length === 0' : 'true' }}"
                            >
                                <x-icon name="add" class="w-4 h-4" />
                                <span x-text="selectedManualIds.length > 0 ? `Agregar ${selectedManualIds.length} reactivo(s) al examen` : 'Selecciona reactivos para agregar'"></span>
                            </x-button>
                        </form>
                    </div>
                </x-card>
            </div>

            {{-- Columna Derecha: Estructura del Examen --}}
            <div class="lg:col-span-7 space-y-5">
                {{-- Barra de métricas y avance a Etapa 3 --}}
                <x-card padding="p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
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
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tiempo est.</p>
                                <p class="text-xl font-bold text-acode-tech font-mono tabular-nums">
                                    {{ floor($totalSeconds / 60) }}m {{ $totalSeconds % 60 }}s
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if ($totalQuestions > 0 && ! $hasProposal)
                                <x-button :href="route('exams.configuration', $exam)" variant="primary" size="md">
                                    Continuar a Configuración →
                                </x-button>
                            @else
                                <x-button
                                    type="button"
                                    variant="primary"
                                    size="md"
                                    :disabled="true"
                                    title="{{ $hasProposal ? 'Acepta o cancela la propuesta pendiente antes de continuar.' : 'Agrega al menos 1 reactivo antes de configurar pesos.' }}"
                                >
                                    Continuar a Configuración →
                                </x-button>
                            @endif
                        </div>
                    </div>
                    @if ($totalQuestions === 0)
                        <p class="mt-3 pt-3 border-t border-slate-100 text-xs text-acode-warning-dark font-medium">
                            Agrega al menos 1 reactivo mediante sorteo o selección manual para habilitar la Etapa 3 (Pesos y reglas).
                        </p>
                    @elseif ($hasProposal)
                        <p class="mt-3 pt-3 border-t border-slate-100 text-xs text-acode-tech font-medium">
                            Tienes una propuesta pendiente en el panel izquierdo; acéptala o cancélala antes de continuar.
                        </p>
                    @endif
                </x-card>

                {{-- Tarjetas por Apartado --}}
                <div class="space-y-4">
                    @forelse ($exam->sections as $section)
                        <x-card padding="p-0" class="overflow-hidden">
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
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <x-button
                                                type="button"
                                                variant="neutral"
                                                size="sm"
                                                @click="editingSectionId = (editingSectionId === {{ $section->id }} ? null : {{ $section->id }})"
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
                                                    @click="confirmAction(
                                                        'delete-section-{{ $section->id }}',
                                                        '¿Eliminar apartado?',
                                                        'Se eliminará el apartado ' + {{ \Illuminate\Support\Js::from($section->title) }} + ' junto con sus reactivos asignados en este examen.',
                                                        'Eliminar',
                                                        true
                                                    )"
                                                >
                                                    <x-icon name="delete" class="w-3.5 h-3.5" />
                                                    Eliminar
                                                </x-button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                {{-- Edición inline del apartado --}}
                                @if ($canEditStructure)
                                    <form
                                        x-show="editingSectionId === {{ $section->id }}"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('exams.sections.update', [$exam, $section]) }}"
                                        class="pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end"
                                    >
                                        @csrf
                                        @method('PUT')
                                        <div class="sm:col-span-5">
                                            <label class="block text-xs font-medium text-slate-800 mb-1">Título del apartado</label>
                                            <x-form.input
                                                type="text"
                                                name="title"
                                                size="sm"
                                                value="{{ $section->title }}"
                                                maxlength="150"
                                                required
                                            />
                                        </div>
                                        <div class="sm:col-span-4">
                                            <label class="block text-xs font-medium text-slate-800 mb-1">Materia (opcional)</label>
                                            <x-form.input
                                                type="text"
                                                name="subject_tag"
                                                size="sm"
                                                value="{{ $section->subject?->name ?? $section->subject_tag }}"
                                                maxlength="100"
                                            />
                                        </div>
                                        <div class="sm:col-span-3 flex items-center gap-1.5">
                                            <x-button type="submit" variant="primary" size="sm" class="flex-1">
                                                Guardar
                                            </x-button>
                                            <x-button type="button" variant="neutral" size="sm" @click="editingSectionId = null">
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
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs font-mono text-slate-500">
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
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                {{-- RF-EXA-005: editar solo para este examen --}}
                                                @if ($q && ! $q->trashed())
                                                    <x-button :href="route('exams.questions.edit', [$exam, $examQuestion])" variant="neutral" size="sm" title="Modificar este reactivo solo para este examen">
                                                        Editar
                                                    </x-button>
                                                @endif
                                                <form method="POST" action="{{ route('exams.questions.reshuffle', [$exam, $examQuestion]) }}" class="inline">
                                                    @csrf
                                                    <x-button type="submit" variant="neutral" size="sm" title="Sustituir por otro reactivo conservando peso y tiempo (RW-14)">
                                                        Reintentar
                                                    </x-button>
                                                </form>

                                                <form
                                                    id="remove-eq-{{ $examQuestion->id }}"
                                                    method="POST"
                                                    action="{{ route('exams.questions.destroy', [$exam, $examQuestion]) }}"
                                                    class="inline"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button
                                                        type="button"
                                                        variant="danger"
                                                        size="sm"
                                                        @click="confirmAction(
                                                            'remove-eq-{{ $examQuestion->id }}',
                                                            '¿Eliminar reactivo del apartado?',
                                                            'El reactivo ' + {{ \Illuminate\Support\Js::from($q?->title ?? 'seleccionado') }} + ' se removerá de este apartado (seguirá en tu banco).',
                                                            'Eliminar',
                                                            true
                                                        )"
                                                    >
                                                        Eliminar
                                                    </x-button>
                                                </form>
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
    </div>
@endsection
