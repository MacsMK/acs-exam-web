@php
    $maxIncidents = $monitorSnapshot['maxIncidents'];
    $incidents = $student['incidents'] ?? 0;
    $limitReached = ! $reactive && $student['status'] === 'in_progress'
        && $maxIncidents !== null && $incidents > 0 && $incidents >= $maxIncidents;
@endphp
<tr>
    <x-table.td>
        <p class="break-words font-semibold text-acode-primary" @if ($reactive) x-text="st.name" @endif>{{ $student['name'] ?? '' }}</p>
        <p class="break-words text-xs text-slate-600" @if ($reactive) x-text="st.email" @endif>{{ $student['email'] ?? '' }}</p>
    </x-table.td>

    <x-table.td align="center">
        @foreach ($statusBadges as $status => $badge)
            @if ($reactive || $student['status'] === $status)
                @php($statusVisibility = $reactive ? "st.status === '{$status}'" : null)
                <x-badge :variant="$badge['variant']"
                         :class="$badge['variant'] === 'danger' ? '!text-acode-danger-dark' : ''"
                         :x-show="$statusVisibility">{{ $badge['label'] }}</x-badge>
            @endif
        @endforeach
    </x-table.td>

    <x-table.td align="center">
        <span class="whitespace-nowrap tabular-nums font-semibold text-slate-700" @if ($reactive) x-text="incidentCount(st)" @endif>
            @unless ($reactive)
                {{ $incidents }}@if ($maxIncidents !== null) / {{ $maxIncidents }}@endif
            @endunless
        </span>
        @if ($reactive || $limitReached)
            <div class="mt-1" @if ($reactive) x-show="incidentLimitReached(st)" @endif>
                <x-badge variant="warning">Límite alcanzado</x-badge>
            </div>
        @endif
    </x-table.td>

    <x-table.td align="center">
        <span class="whitespace-nowrap tabular-nums" @if ($reactive) x-text="st.started_at" @endif>{{ $student['started_at'] ?? '' }}</span>
    </x-table.td>
    <x-table.td align="center">
        <span class="whitespace-nowrap tabular-nums" @if ($reactive) x-text="st.submitted_at" @endif>{{ $student['submitted_at'] ?? '' }}</span>
    </x-table.td>
    <x-table.td align="right">
        <span class="whitespace-nowrap font-semibold tabular-nums text-acode-primary"
              @if ($reactive) x-text="st.score" @endif>{{ $student['score'] ?? '' }}</span>
    </x-table.td>
</tr>
