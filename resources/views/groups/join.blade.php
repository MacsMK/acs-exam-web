@extends('layouts.app')

@section('title', 'Unirme a un grupo')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Unirme a un grupo</h1>

    <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-md">
        <form method="POST" action="{{ route('student.groups.join') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Folio de acceso</label>
                <input type="text" name="access_folio" required maxlength="12" autofocus
                       placeholder="Ej. 7F3K9QRT"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm font-mono tracking-widest uppercase focus:outline-none focus:ring-2 focus:ring-acode-tech">
                <p class="text-xs text-slate-500 mt-1">Pídele este código a tu docente.</p>
            </div>

            <button type="submit" class="h-10 px-4 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
                Unirme
            </button>
        </form>
    </div>
@endsection
