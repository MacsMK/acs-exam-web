{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: admin/terms/index.blade.php (MPW-V3 §4.1.2) --}}
@extends('layouts.app')
@section('title', 'Términos y condiciones')
@section('content')
    <x-section-header title="Términos y condiciones" />
    <x-card>
        <form method="post" action="{{ route('admin.terms.store') }}" class="grid gap-3">
            @csrf
            <label>Versión <input name="version" maxlength="60" required value="{{ old('version') }}"></label>
            <label>Entrada en vigor <input type="datetime-local" name="effective_at" required value="{{ old('effective_at') }}"></label>
            <label>Texto de los términos <textarea name="content" class="w-full" rows="8" required>{{ old('content') }}</textarea></label>
            <button type="submit" class="acode-button">Publicar versión</button>
        </form>
    </x-card>
    <details class="my-3"><summary>Textos originales del registro · vigencia anterior sin fecha documentada</summary>
        <h3>Docentes</h3>@include('legal.terms-teacher-original')
        <h3>Estudiantes</h3>@include('legal.terms-student-original')
    </details>
    @foreach ($versions as $version)
        <details class="my-3"><summary>{{ $version->version }} · Vigencia desde {{ $version->effective_at->format('d/m/Y H:i') }}</summary>
            <p class="whitespace-pre-line">{{ $version->content }}</p>
        </details>
    @endforeach
    {{ $versions->links() }}
@endsection
