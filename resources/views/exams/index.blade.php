@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-acode-primary">Mis exámenes</h1>
        <a href="{{ route('exams.create') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            + Nuevo examen
        </a>
    </div>

    @if ($exams->isEmpty())
        <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-500">
            Todavía no has creado exámenes.
        </div>
    @else
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
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
                    @foreach ($exams as $exam)
                        <tr class="even:bg-acode-surface hover:bg-blue-50/70">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $exam->title }}</td>
                            <td class="px-4 py-3">{{ $exam->grading_scale }}</td>
                            <td class="px-4 py-3">{{ $exam->starts_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $exam->ends_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">
                                @if ($exam->is_published)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-acode-accent/15 text-acode-accent">Publicado</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-acode-warning/20 text-acode-primary">Borrador</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('exams.assemble', $exam) }}" class="text-acode-tech font-medium">Armar →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
