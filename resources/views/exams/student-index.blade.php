{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/student-index.blade.php (MPW-V2 §2.4, §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Mis exámenes</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse ($exams as $exam)
            <x-card>
                <h3 class="font-semibold text-slate-800 mb-1">{{ $exam->title }}</h3>
                <p class="text-xs text-slate-500 mb-3">{{ $exam->description }}</p>
                <p class="text-xs text-slate-500 mb-4">
                    {{ $exam->sections->sum(fn ($s) => ($s->exam_questions_count ?? ($s->relationLoaded('examQuestions') ? $s->examQuestions->count() : 0)) ?: $s->requested_quantity) }} reactivos ·
                    Disponible hasta {{ $exam->ends_at?->format('d/m/Y H:i') }}
                </p>
                <form method="POST" action="{{ route('attempts.start', $exam) }}">
                    @csrf
                    <x-button type="submit" variant="primary">
                        Iniciar examen
                    </x-button>
                </form>
            </x-card>
        @empty
            <div class="sm:col-span-2">
                <x-empty-state message="No tienes exámenes publicados todavía.">
                    Si tu docente te dio un folio de grupo,
                    <a href="{{ route('student.groups.join.form') }}" class="text-acode-tech font-medium hover:underline">únete aquí</a>.
                </x-empty-state>
            </div>
        @endforelse
    </div>
@endsection
