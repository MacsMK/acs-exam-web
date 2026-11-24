<section class="space-y-3">
    <x-section-header
        title="Calificaciones de exámenes"
        subtitle="Entregas vigentes con calificación publicada o en revisión."
        :institutional="true"
    />

    @if ($attempts->isEmpty())
        <x-empty-state
            icon="reportBar"
            title="No tienes calificaciones registradas"
            message="Tus calificaciones publicadas y las evaluaciones en revisión aparecerán aquí."
        />
    @else
        <div class="space-y-3 md:hidden">
            @foreach ($attempts as $attempt)
                <x-card padding="p-4">
                    <h3 class="break-words text-base font-semibold leading-6 text-acode-primary">{{ $attempt->exam->title }}</h3>
                    @if ($showGroup)
                        <p class="mt-1 break-words text-sm text-slate-700">{{ $attempt->group->name }}</p>
                    @endif
                    @if ($attempt->submitted_at)
                        <p class="mt-1 text-xs text-slate-600">Fecha de entrega: {{ $attempt->submitted_at->format('d/m/Y H:i') }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-3">
                        <x-student.attempt-grade :grade="$attemptGrades[$attempt->id]" />
                        <x-button :href="route('attempts.result', $attempt)" variant="secondary" size="sm"
                            :aria-label="'Ver detalles de ' . $attempt->exam->title . ', grupo ' . $attempt->group->name">
                            Ver detalles
                        </x-button>
                    </div>
                </x-card>
            @endforeach
        </div>

        <x-table zebra="slate" scroll-label="Calificaciones de exámenes" class="hidden md:block">
            <x-slot:header>
                <x-table.th align="center">Examen</x-table.th>
                @if ($showGroup)
                    <x-table.th align="center">Grupo</x-table.th>
                @endif
                <x-table.th align="center">Fecha de entrega</x-table.th>
                <x-table.th align="center">Calificación</x-table.th>
                <x-table.th align="center">Detalles</x-table.th>
            </x-slot:header>

            @foreach ($attempts as $attempt)
                <tr>
                    <th scope="row" class="min-w-36 px-4 py-3 text-left font-semibold text-acode-primary">
                        {{ $attempt->exam->title }}
                    </th>
                    @if ($showGroup)
                        <x-table.td>
                            <p class="text-sm text-slate-700">{{ $attempt->group->name }}</p>
                            @if ($attempt->group->subject)
                                <p class="text-xs text-slate-600">{{ $attempt->group->subject }}</p>
                            @endif
                        </x-table.td>
                    @endif
                    <x-table.td align="center">
                        <span class="whitespace-nowrap text-xs tabular-nums text-slate-700">
                            {{ $attempt->submitted_at?->format('d/m/Y H:i') ?? '—' }}
                        </span>
                    </x-table.td>
                    <x-table.td align="center">
                        <x-student.attempt-grade :grade="$attemptGrades[$attempt->id]" />
                    </x-table.td>
                    <x-table.td align="right">
                        <x-button :href="route('attempts.result', $attempt)" variant="secondary" size="sm"
                            class="whitespace-nowrap"
                            :aria-label="'Ver detalles de ' . $attempt->exam->title . ', grupo ' . $attempt->group->name">
                            Ver detalles
                        </x-button>
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    @endif
</section>
