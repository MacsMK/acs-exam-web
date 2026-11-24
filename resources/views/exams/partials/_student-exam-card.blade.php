@php
    $exam = $row['exam'];
    $showDescription = $showDescription ?? true;
    $showCatalogMetadata = $showCatalogMetadata ?? false;
@endphp

<x-card padding="p-4" class="min-w-0">
    <div class="space-y-3">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <x-badge
                    :variant="$row['statusVariant']"
                    :class="$row['statusVariant'] === 'danger' ? '!text-acode-danger-dark' : ''"
                >{{ $row['statusLabel'] }}</x-badge>
                @if ($row['reapplicationNumber'] !== null)
                    <x-badge variant="info">Reaplicación autorizada</x-badge>
                @endif
            </div>
            <h3 class="break-words text-base font-semibold leading-6 text-acode-primary">{{ $exam->title }}</h3>
            @if ($row['groupNames'] !== '')
                <p class="break-words text-xs text-slate-700">{{ $row['groupNames'] }}</p>
            @endif
            @if ($showDescription && filled($exam->description))
                <p @class(['break-words text-sm text-slate-700', 'line-clamp-3' => $row['action'] === 'start'])>{{ $exam->description }}</p>
            @endif
        </div>

        <dl class="space-y-1.5 border-t border-slate-200 pt-3 text-xs text-slate-700">
            @if ($showCatalogMetadata)
                <div class="flex flex-wrap justify-between gap-x-3">
                    <dt>Profesor</dt>
                    <dd class="font-medium">{{ $exam->teacher?->full_name ?? 'Docente titular' }}</dd>
                </div>
            @endif
            @if ($row['startsAt'] && ($row['state'] === 'scheduled' || $showCatalogMetadata && $row['action'] !== 'resume'))
                <div class="flex flex-wrap justify-between gap-x-3">
                    <dt>Fecha de inicio</dt>
                    <dd class="font-medium"><time datetime="{{ $row['startsAt']->toIso8601String() }}">{{ $row['startsAt']->format('d/m/Y H:i') }}</time></dd>
                </div>
            @endif
            @if ($row['action'] === 'resume')
                @if ($row['attemptEndsAt'])
                    <div class="flex flex-wrap justify-between gap-x-3">
                        <dt>{{ $row['attemptHasGrace'] ? 'Fecha límite de edición' : 'Fecha límite del intento' }}</dt>
                        <dd class="font-medium"><time datetime="{{ $row['attemptEndsAt']->toIso8601String() }}">{{ $row['attemptEndsAt']->format('d/m/Y H:i') }}</time></dd>
                    </div>
                @endif
                @if ($row['attemptHasGrace'])
                    <div class="flex flex-wrap justify-between gap-x-3">
                        <dt>Fecha límite de envío</dt>
                        <dd class="font-medium"><time datetime="{{ $row['attemptDeadline']->toIso8601String() }}">{{ $row['attemptDeadline']->format('d/m/Y H:i') }}</time></dd>
                    </div>
                @endif
            @else
                <div class="flex flex-wrap justify-between gap-x-3">
                    <dt>Fecha límite</dt>
                    <dd class="font-medium">
                        @if ($row['endsAt'])
                            <time datetime="{{ $row['endsAt']->toIso8601String() }}">{{ $row['endsAt']->format('d/m/Y H:i') }}</time>
                        @else
                            Sin vencimiento
                        @endif
                    </dd>
                </div>
            @endif
            <div class="flex flex-wrap justify-between gap-x-3">
                <dt>Tiempo máximo:</dt>
                <dd class="font-medium">{{ $row['durationMinutes'] }} minutos</dd>
            </div>
            <div class="flex flex-wrap justify-between gap-x-3">
                <dt>Reactivos</dt>
                <dd class="font-medium">{{ $exam->exam_questions_count }}</dd>
            </div>
        </dl>

        @if ($row['href'])
            <x-button
                :href="$row['href']"
                :variant="$row['actionVariant']"
                :aria-label="$row['actionLabel'].' · '.$exam->title.($row['groupNames'] ? ', '.$row['groupNames'] : '')"
                class="w-full"
            >{{ $row['actionLabel'] }}</x-button>
        @endif
    </div>
</x-card>
