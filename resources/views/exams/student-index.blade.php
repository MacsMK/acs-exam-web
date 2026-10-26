@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Mis exámenes</h1>

    @if ($exams->isEmpty())
        <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-500">
            No tienes exámenes publicados todavía. Si tu docente te dio un folio de grupo,
            <a href="{{ route('student.groups.join.form') }}" class="text-acode-tech font-medium">únete aquí</a>.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($exams as $exam)
                <div class="bg-white rounded-lg border border-slate-200 p-5">
                    <h3 class="font-semibold text-slate-800 mb-1">{{ $exam->title }}</h3>
                    <p class="text-xs text-slate-500 mb-3">{{ $exam->description }}</p>
                    <p class="text-xs text-slate-500 mb-4">
                        {{ $exam->sections->sum(fn ($s) => $s->examQuestions()->count() ?: $s->requested_quantity) }} reactivos ·
                        Disponible hasta {{ $exam->ends_at?->format('d/m/Y H:i') }}
                    </p>
                    <form method="POST" action="{{ route('attempts.start', $exam) }}">
                        @csrf
                        <button type="submit" class="h-10 px-4 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
                            Presentar examen
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
@endsection
