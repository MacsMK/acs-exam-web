{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/show.blade.php (MPW-V2 §5.2.2, §7.3, §7.8.2, §8.4, §8.6, §8.7, §8.9.2, Tabla 20) --}}
@extends('layouts.app')

@section('title', $group->name)

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">{{ $group->name }}</h1>
            @if ($group->is_closed)
                <x-badge variant="neutral">Cerrado</x-badge>
            @else
                <x-badge variant="accent">Activo</x-badge>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <x-button :href="route('groups.index')" variant="neutral" size="sm">
                <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90 mr-1" />
                Volver
            </x-button>
        </div>
    </div>
    <p class="text-sm text-slate-500 mb-6">{{ $group->subject ?? 'Sin materia asignada' }}</p>

    {{-- Tarjeta de Folio y Código QR --}}
    <x-card class="mb-8 max-w-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Folio Oficial de Acceso</p>
                <p class="font-mono text-3xl font-bold text-acode-primary tracking-widest select-all">{{ $group->access_folio }}</p>
                <p class="text-xs text-slate-500 mt-2">Tus alumnos utilizan este folio o escanean el QR para matricularse.</p>
            </div>

            <div class="flex flex-col gap-2 shrink-0">
                <x-button
                    type="button"
                    variant="secondary"
                    size="md"
                    @click="$dispatch('open-modal', 'group-qr-modal')"
                >
                    <x-icon name="qrCode" class="w-4 h-4 mr-1.5" />
                    Ver código QR
                </x-button>
            </div>
        </div>
    </x-card>

    {{-- Modal Institucional de Código QR y Folio (MPW-V2 §8.9.2) --}}
    <x-group.qr-modal
        name="group-qr-modal"
        :groupName="$group->name"
        :folio="$group->access_folio"
        :joinUrl="route('student.groups.join.form', ['folio' => $group->access_folio])"
    />

    {{-- Lista de Alumnos Matriculados --}}
    <div class="space-y-3 mb-8">
        <h2 class="text-lg font-bold text-acode-primary tracking-tight">
            Alumnos Inscritos ({{ $group->students_count ?? $group->students->count() }})
        </h2>

        <x-table>
            <x-slot:header>
                <x-table.th>Nombre del Alumno</x-table.th>
                <x-table.th>Correo Institucional</x-table.th>
                <x-table.th align="right">Fecha de Matriculación</x-table.th>
            </x-slot:header>

            @forelse ($group->students as $student)
                <tr>
                    <x-table.td>
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech">
                                {{ strtoupper(substr($student->first_name ?? $student->full_name, 0, 1)) }}
                            </div>
                            <span class="font-semibold text-slate-900">{{ $student->full_name }}</span>
                        </div>
                    </x-table.td>
                    <x-table.td>
                        <span class="text-slate-600">{{ $student->email }}</span>
                    </x-table.td>
                    <x-table.td align="right">
                        <span class="text-xs text-slate-500">{{ $student->pivot->joined_at ?? $student->created_at->format('d/m/Y') }}</span>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-8"
                            icon="userGroup"
                            title="Aún no se ha unido ningún alumno"
                            message="Comparte el folio o código QR con tu clase para comenzar."
                        >
                            <x-button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="$dispatch('open-modal', 'group-qr-modal')"
                            >
                                <x-icon name="qrCode" class="w-4 h-4 mr-1.5" />
                                Compartir folio y QR
                            </x-button>
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>

    {{-- Cierre de grupo escolar con confirmación SweetAlert2 (MPW-V2 §8.7, Tabla 20) --}}
    @unless ($group->is_closed)
        <div class="pt-4 border-t border-slate-200">
            <form
                id="close-group-form"
                method="POST"
                action="{{ route('groups.close', $group) }}"
                x-data="{
                    confirmCloseGroup() {
                        if (typeof AcodeAlert !== 'undefined') {
                            AcodeAlert.confirm({
                                title: '¿Cerrar este grupo escolar?',
                                text: 'Esta acción corresponde al cierre de ciclo. Se impedirá la asignación de nuevos exámenes pero se conservará íntegro el historial de calificaciones.',
                                confirmButtonText: 'Cerrar grupo',
                                cancelButtonText: 'Cancelar',
                                isDanger: true,
                                onConfirm: () => document.getElementById('close-group-form').submit()
                            });
                        } else {
                            if (confirm('¿Cerrar este grupo?')) {
                                document.getElementById('close-group-form').submit();
                            }
                        }
                    }
                }"
            >
                @csrf
                <x-button
                    type="button"
                    variant="danger"
                    size="md"
                    @click="confirmCloseGroup()"
                >
                    <x-icon name="lock" class="w-4 h-4 mr-1.5" />
                    Cerrar grupo
                </x-button>
            </form>
        </div>
    @endunless
@endsection
