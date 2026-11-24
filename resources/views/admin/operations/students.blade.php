{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: admin/operations/students.blade.php (MPW-V3 §4.1.2) --}}
@extends('layouts.app')
@section('title', 'Cuentas de estudiantes')
@section('content')
    <x-section-header title="Cuentas de estudiantes" />
    <x-table>
        <x-slot:header>
            <x-table.th>Cuenta</x-table.th><x-table.th>Nombre</x-table.th>
            <x-table.th>Correo</x-table.th><x-table.th>Estado</x-table.th>
        </x-slot:header>
        @forelse ($students as $student)
            <tr><x-table.td>{{ $student->id }}</x-table.td><x-table.td>{{ $student->full_name }}</x-table.td>
                <x-table.td>{{ $student->email }}</x-table.td><x-table.td>{{ $student->is_active ? 'Activa' : 'Inactiva' }}</x-table.td></tr>
        @empty
            <tr><td colspan="4">Sin cuentas de estudiantes</td></tr>
        @endforelse
    </x-table>
    {{ $students->links() }}
@endsection
