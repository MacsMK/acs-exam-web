{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: legal/terms.blade.php (MPW-V3 §4.1.2) --}}
@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('title', 'Términos y condiciones')
@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <h1 class="text-2xl font-bold text-acode-primary">Términos y condiciones</h1>
        <x-card>
            @if ($version)
                <p>Versión {{ $version->version }} · Vigente desde {{ $version->effective_at->format('d/m/Y H:i') }}</p>
                <p class="whitespace-pre-line">{{ $version->content }}</p>
            @else
                <h2>Docentes</h2>@include('legal.terms-teacher-original')
                <h2>Estudiantes</h2>@include('legal.terms-student-original')
            @endif
        </x-card>
    </div>
@endsection
