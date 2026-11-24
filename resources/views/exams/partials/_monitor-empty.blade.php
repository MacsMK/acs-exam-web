@if ($withoutStudents)
    <x-empty-state icon="userGroup" title="Sin estudiantes registrados"
                   message="Aún no hay estudiantes matriculados en los grupos asignados al examen." />
@else
    <x-empty-state icon="search" title="No se encontraron estudiantes"
                   message="Ningún estudiante coincide con la búsqueda y el estado seleccionados." />
@endif
