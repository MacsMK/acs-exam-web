@extends('layouts.app')

@section('title', 'Administración de Docentes y Paquetes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">Superadministración de Cuentas Docentes</h1>
            <p class="text-sm text-slate-500 mt-1">
                Panel central para verificar estado de pagos, asignar paquetes SaaS y suspender o reactivar cuentas de docentes.
            </p>
        </div>
    </div>

    {{-- Filtros de búsqueda --}}
    <x-card class="p-4">
        <form method="GET" action="{{ route('admin.teachers.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-6">
                <x-form.input name="search" :value="$filters['search'] ?? ''" placeholder="Buscar por nombre o correo de docente..." />
            </div>
            <div class="sm:col-span-3">
                <x-form.select name="status">
                    <option value="">Todos los estados</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Solo Activos</option>
                    <option value="suspended" {{ ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' }}>Solo Suspendidos</option>
                </x-form.select>
            </div>
            <div class="sm:col-span-3 flex items-center gap-2">
                <x-button variant="secondary" size="md" type="submit" class="w-full">
                    Buscar
                </x-button>
                @if (!empty($filters['search']) || !empty($filters['status']))
                    <x-button variant="neutral" size="md" href="{{ route('admin.teachers.index') }}">
                        Limpiar
                    </x-button>
                @endif
            </div>
        </form>
    </x-card>

    {{-- Tabla de Docentes --}}
    <x-card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-acode-primary text-white text-xs font-semibold uppercase">
                    <tr>
                        <th class="px-6 py-4">Docente</th>
                        <th class="px-6 py-4">Contacto</th>
                        <th class="px-6 py-4">Estado Cuenta</th>
                        <th class="px-6 py-4">Suscripción Actual</th>
                        <th class="px-6 py-4 text-right">Acciones de Gestión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($teachers as $teacher)
                        <tr class="hover:bg-blue-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-acode-primary">{{ $teacher->full_name }}</div>
                                <div class="text-xs text-slate-400">ID: {{ $teacher->id }} · Exámenes: {{ $teacher->exams_created_count }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs text-slate-700 font-medium">{{ $teacher->email }}</div>
                                <div class="text-xs text-slate-500">{{ $teacher->phone ?? 'Sin teléfono' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($teacher->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                        Suspendido
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $currentSub = $teacher->teacherSubscriptions->first();
                                    $statusLabels = [
                                        'paid' => 'Pagado',
                                        'pending' => 'Pendiente',
                                        'overdue' => 'Vencido',
                                        'suspended' => 'Suspendido',
                                    ];
                                    $paymentStatus = $currentSub ? ($statusLabels[$currentSub->payment_status] ?? ucfirst($currentSub->payment_status)) : null;
                                    $paymentColor = match ($currentSub?->payment_status) {
                                        'paid' => 'text-emerald-700 font-semibold',
                                        'pending' => 'text-amber-700 font-semibold',
                                        'overdue', 'suspended' => 'text-red-700 font-semibold',
                                        default => 'text-slate-600 font-semibold',
                                    };
                                @endphp
                                @if ($currentSub && $currentSub->package)
                                    <div class="text-xs font-bold text-acode-primary">{{ $currentSub->package->name }}</div>
                                    <div class="text-[11px] text-slate-500">Pago: <span class="{{ $paymentColor }}">{{ $paymentStatus }}</span></div>
                                @else
                                    <span class="text-xs text-slate-400 italic">Prueba Gratuita</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2" x-data="{ openAssign: false }">
                                    {{-- Modal inline / desplegable para asignar paquete --}}
                                    <x-button variant="secondary" size="sm" type="button" @click="openAssign = !openAssign">
                                        Asignar plan
                                    </x-button>

                                    {{-- Botón de suspender o reactivar cuenta --}}
                                    <form method="POST" action="{{ route('admin.teachers.toggle', $teacher) }}" onsubmit="return confirm('¿Está seguro de cambiar el estado de la cuenta?')">
                                        @csrf
                                        <x-button :variant="$teacher->is_active ? 'danger' : 'accent'" size="sm" type="submit">
                                            {{ $teacher->is_active ? 'Suspender' : 'Reactivar' }}
                                        </x-button>
                                    </form>

                                    {{-- Mini formulario de asignación de plan --}}
                                    <div x-show="openAssign" x-cloak @click.outside="openAssign = false" class="absolute z-20 mt-12 right-12 w-80 bg-white border border-slate-200 rounded-xl shadow-xl p-4 text-left">
                                        <h4 class="text-xs font-bold text-acode-primary mb-2">Asignar Paquete a {{ $teacher->first_name }}</h4>
                                        <form method="POST" action="{{ route('admin.teachers.assign', $teacher) }}" class="space-y-3">
                                            @csrf
                                            <x-form.select name="subscription_package_id">
                                                @foreach ($packages as $pkg)
                                                    <option value="{{ $pkg->id }}">{{ $pkg->name }} (${{ $pkg->monthly_price }} MXN)</option>
                                                @endforeach
                                            </x-form.select>

                                            <x-form.select name="payment_status">
                                                <option value="paid">Pagado (Acreditado / Vigente)</option>
                                                <option value="pending">Pendiente de Acreditación</option>
                                                <option value="overdue">Vencido</option>
                                                <option value="suspended">Suspendido por Falta de Pago</option>
                                            </x-form.select>

                                            <div class="flex justify-end gap-2 pt-2">
                                                <x-button variant="neutral" size="sm" type="button" @click="openAssign = false">Cancelar</x-button>
                                                <x-button variant="primary" size="sm" type="submit">Guardar</x-button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                                No se encontraron cuentas docentes con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($teachers->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $teachers->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection
