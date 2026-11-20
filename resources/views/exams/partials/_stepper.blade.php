{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Partial: exams/partials/_stepper.blade.php (MPW-V2 §4.1.2, §7, §8) --}}
@php
    $stepNumber = (int) ($currentStep ?? 1);
    $showTitle = (bool) ($showTitle ?? false);
    $hasExam = isset($exam) && $exam instanceof \App\Models\Exam && $exam->exists;

    $resolvedWeight = isset($totalPoints)
        ? (float) $totalPoints
        : ($hasExam
            ? (float) ($exam->total_points_weight ?? ($exam->relationLoaded('examQuestions') ? $exam->examQuestions->sum('points_weight') : 0))
            : 0.0);

    $opStatus = $hasExam ? $exam->operationalStatus() : 'draft';
    $statusMeta = match ($opStatus) {
        'active' => ['label' => 'Activo', 'variant' => 'accent'],
        'scheduled' => ['label' => 'Programado', 'variant' => 'info'],
        'closed' => ['label' => 'Cerrado', 'variant' => 'neutral'],
        default => ['label' => 'Borrador', 'variant' => 'warning'],
    };

    $weightExact = abs($resolvedWeight - 100.0) <= 0.01;
    $steps = [
        [
            'num' => 1,
            'label' => 'Datos y grupos',
            'url' => $hasExam ? route('exams.edit', $exam) : route('exams.create'),
        ],
        [
            'num' => 2,
            'label' => 'Armar reactivos',
            'url' => $hasExam ? route('exams.assemble', $exam) : null,
        ],
        [
            'num' => 3,
            'label' => 'Pesos y reglas',
            'url' => $hasExam ? route('exams.configuration', $exam) : null,
        ],
        [
            'num' => 4,
            'label' => 'Revisar y publicar',
            'url' => $hasExam ? route('exams.review', $exam) : null,
        ],
    ];
@endphp

<x-card padding="p-4 sm:p-5" class="border-slate-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
        <div class="min-w-0 flex items-center gap-2.5 flex-wrap">
            <span class="text-xs font-semibold text-slate-600">
                Progreso de configuración · Etapa {{ $stepNumber }} de 4
            </span>
            @if ($showTitle)
                <span class="text-slate-300 hidden sm:inline">·</span>
                <p class="text-sm font-bold text-acode-primary truncate">
                    {{ $hasExam ? $exam->title : 'Nuevo examen en preparación' }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <x-badge :variant="$statusMeta['variant']">
                {{ $statusMeta['label'] }}
            </x-badge>

            @if ($hasExam)
                <span
                    id="stepper-points-badge"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-mono font-semibold border {{ $weightExact ? 'bg-acode-accent/15 text-acode-primary border-acode-accent/40' : 'bg-acode-warning/20 text-acode-primary border-acode-warning/50' }}"
                >
                    <x-icon name="{{ $weightExact ? 'check' : 'reportBar' }}" class="w-3.5 h-3.5 {{ $weightExact ? 'text-acode-accent' : 'text-acode-warning-dark' }}" />
                    <span>{{ number_format($resolvedWeight, 2) }} / 100.00 pts</span>
                </span>
            @endif
        </div>
    </div>

    <nav aria-label="Etapas de configuración del examen" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
        @foreach ($steps as $step)
            @php
                $isActive = $step['num'] === $stepNumber;
                $isCompleted = $hasExam && $step['num'] < $stepNumber;
                $isUnlocked = $step['url'] !== null;
            @endphp

            @if ($isUnlocked && ! $isActive)
                <a
                    href="{{ $step['url'] }}"
                    class="group flex items-center gap-3 rounded-xl border px-3.5 py-2.5 transition {{ $isCompleted ? 'border-acode-accent/30 bg-acode-accent/5 hover:bg-acode-accent/10' : 'border-slate-200 bg-slate-50/70 hover:border-acode-tech/50 hover:bg-acode-surface/60' }}"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition {{ $isCompleted ? 'bg-acode-accent text-white' : 'bg-white border border-slate-300 text-acode-primary group-hover:border-acode-tech group-hover:text-acode-tech' }}">
                        @if ($isCompleted)
                            <x-icon name="check" class="w-4 h-4" />
                        @else
                            {{ $step['num'] }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Etapa {{ $step['num'] }}
                        </p>
                        <p class="text-xs sm:text-sm font-semibold text-acode-primary truncate group-hover:text-acode-tech">
                            {{ $step['num'] }}. {{ $step['label'] }}
                        </p>
                    </div>
                </a>
            @else
                <div
                    @if ($isActive) aria-current="step" @endif
                    class="flex items-center gap-3 rounded-xl border px-3.5 py-2.5 {{ $isActive ? 'border-acode-primary bg-acode-primary text-white shadow-sm' : 'border-slate-200 bg-slate-50/60 opacity-60 cursor-not-allowed' }}"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isActive ? 'bg-acode-accent text-white' : 'bg-slate-200 text-slate-500' }}">
                        {{ $step['num'] }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider {{ $isActive ? 'text-acode-surface' : 'text-slate-500' }}">
                            Etapa {{ $step['num'] }}
                        </p>
                        <p class="text-xs sm:text-sm font-semibold truncate {{ $isActive ? 'text-white' : 'text-slate-500' }}">
                            {{ $step['num'] }}. {{ $step['label'] }}
                        </p>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
</x-card>
