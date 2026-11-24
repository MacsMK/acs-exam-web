{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Partial: exams/partials/_stepper.blade.php (MPW-V3 §4.1.2, §7, §8) --}}
@php
    $stepNumber = (int) ($currentStep ?? 1);
    $showTitle = (bool) ($showTitle ?? false);
    $showPoints = (bool) ($showPoints ?? true);
    $hasExam = isset($exam) && $exam instanceof \App\Models\Exam && $exam->exists;
    $assemblyBlocked = filled($assemblyBlockReason ?? null);

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

    $weightValid = abs((int) round($resolvedWeight * 100) - 10000) <= 1;
    $steps = [
        [
            'num' => 1,
            'label' => 'Datos y grupos',
            'url' => $hasExam ? route('exams.edit', $exam) : null,
        ],
        [
            'num' => 2,
            'label' => 'Armar reactivos',
            'url' => $hasExam ? route('exams.assemble', $exam) : null,
        ],
        [
            'num' => 3,
            'label' => 'Pesos y reglas',
            'url' => $hasExam && ! $assemblyBlocked ? route('exams.configuration', $exam) : null,
        ],
        [
            'num' => 4,
            'label' => $opStatus === 'draft' ? 'Revisar y publicar' : 'Resumen del examen',
            'url' => $hasExam && ! $assemblyBlocked ? route('exams.review', $exam) : null,
        ],
    ];
@endphp

<x-card padding="p-3 sm:p-4" class="border-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-slate-100">
        <div class="min-w-0 flex items-center gap-2.5 flex-wrap">
            <span class="text-xs font-semibold text-slate-600">
                Progreso de configuración · Etapa {{ $stepNumber }} de 4
            </span>
            @if ($showTitle)
                <span class="text-slate-300 hidden sm:inline">·</span>
                <p class="min-w-0 break-words text-sm font-bold text-acode-primary">
                    {{ $hasExam ? $exam->title : 'Nuevo examen en preparación' }}
                </p>
            @endif
        </div>

        @if ($hasExam)
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-badge :variant="$statusMeta['variant']">
                    {{ $statusMeta['label'] }}
                </x-badge>

                @if ($showPoints)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-mono font-semibold border text-acode-primary {{ $weightValid ? 'bg-acode-accent/15 border-acode-accent/40' : 'bg-acode-warning/20 border-acode-warning/50' }}">
                        <x-icon name="{{ $weightValid ? 'check' : 'reportBar' }}" class="w-3.5 h-3.5" />
                        <span>{{ number_format($resolvedWeight, 2, '.', '') }} / 100.00 pts</span>
                    </span>
                @endif
            </div>
        @endif
    </div>

    <nav aria-label="Etapas de configuración del examen">
        <ol class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4" role="list">
            @foreach ($steps as $step)
                @php
                    $isActive = $step['num'] === $stepNumber;
                    $isCompleted = $hasExam && $step['num'] < $stepNumber;
                    $isBlocked = $hasExam && $assemblyBlocked && $step['num'] >= 3;
                @endphp

                <li class="min-w-0" @if ($isActive) aria-current="step" @endif>
                    @if ($step['url'] !== null && ! $isActive)
                        <a
                            href="{{ $step['url'] }}"
                            @if (filled($cancelForm ?? null)) data-cancel-form="{{ $cancelForm }}" @endif
                            class="flex h-full min-h-11 items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold text-acode-primary transition hover:border-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech {{ $isCompleted ? 'border-acode-accent/30 bg-acode-accent/5 hover:bg-acode-accent/10' : 'border-slate-200 bg-slate-50 hover:bg-acode-surface' }}"
                        >
                            <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isCompleted ? 'bg-acode-accent-dark text-white' : 'bg-white border border-slate-300 text-acode-primary' }}">
                                @if ($isCompleted)
                                    <x-icon name="check" class="w-4 h-4" />
                                @else
                                    {{ $step['num'] }}
                                @endif
                            </span>
                            <span class="sr-only">Etapa {{ $step['num'] }}{{ $isCompleted ? ' completada' : '' }},</span>
                            <span>{{ $step['label'] }}</span>
                        </a>
                    @else
                        <div class="flex h-full min-h-11 items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold {{ $isActive ? 'border-acode-primary bg-acode-primary text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-600' }}">
                            <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isActive ? 'bg-acode-accent-dark text-white' : 'bg-slate-200 text-slate-700' }}">
                                {{ $step['num'] }}
                            </span>
                            <span class="sr-only">Etapa {{ $step['num'] }}{{ $isBlocked ? ' bloqueada' : '' }},</span>
                            <span>{{ $step['label'] }}</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
    @if ($assemblyBlocked && $stepNumber === 1)
        <p class="mt-2 text-xs text-slate-600">{{ $assemblyBlockReason }}</p>
    @endif
</x-card>
