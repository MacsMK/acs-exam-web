<p class="mb-2 text-xs text-slate-700 xl:hidden">Desplaza la tabla para ver todas las columnas.</p>
<x-table zebra="slate" scroll-label="Tabla de estudiantes" table-class="min-w-[56rem] table-fixed sm:table-auto">
    <x-slot:columns>
        <colgroup>
            <col class="w-32 sm:w-auto">
            <col class="w-32 sm:w-auto">
            <col span="4">
        </colgroup>
    </x-slot:columns>
    <x-slot:header>
        <x-table.th align="center">Estudiante</x-table.th>
        <x-table.th align="center">Estado</x-table.th>
        <x-table.th align="center">Incidentes reportados</x-table.th>
        <x-table.th align="center">Hora de inicio</x-table.th>
        <x-table.th align="center">Hora de entrega</x-table.th>
        <x-table.th align="center">Calificación final ({{ $gradingScale }})</x-table.th>
    </x-slot:header>

    @if ($reactive)
        <template x-for="st in (ready ? filteredStudents : [])" :key="st.id">
            @include('exams.partials._monitor-row', ['student' => []])
        </template>
    @else
        @foreach ($visibleStudents as $student)
            @include('exams.partials._monitor-row')
        @endforeach
    @endif
</x-table>
