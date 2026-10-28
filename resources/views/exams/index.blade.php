{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/index.blade.php (MPW-V2 §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-acode-primary">Mis exámenes</h1>
        <x-button :href="route('exams.create')" variant="primary">
            Crear examen
        </x-button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-acode-primary text-white text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Título</th>
                    <th class="text-left px-4 py-3">Escala</th>
                    <th class="text-left px-4 py-3">Inicia</th>
                    <th class="text-left px-4 py-3">Termina</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($exams as $exam)
                    <tr class="even:bg-acode-surface hover:bg-acode-tech/5">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $exam->title }}</td>
                        <td class="px-4 py-3">{{ $exam->grading_scale }}</td>
                        <td class="px-4 py-3">{{ $exam->starts_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $exam->ends_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if ($exam->is_published)
                                <x-badge variant="accent">Publicado</x-badge>
                            @else
                                <x-badge variant="warning">Borrador</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('exams.assemble', $exam) }}" class="text-acode-tech font-medium hover:underline">Editar examen</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-0">
                            <x-empty-state
                                class="border-0 shadow-none rounded-none"
                                message="Todavía no has creado exámenes."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
