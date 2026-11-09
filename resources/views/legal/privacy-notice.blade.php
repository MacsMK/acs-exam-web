@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Aviso de Privacidad')

@section('content')
<div class="max-w-4xl mx-auto space-y-6 py-4">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold text-acode-primary">Aviso de Privacidad Integral</h1>
        <p class="text-sm text-slate-500 mt-1">
            Cumplimiento normativo conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP).
        </p>
    </div>

    <x-card class="prose max-w-none text-slate-700 leading-relaxed text-sm">
        @if ($notice)
            <div class="flex items-center justify-between text-xs text-slate-400 border-b border-slate-100 pb-3 mb-4">
                <span>Versión del documento: <strong>{{ $notice->version }}</strong></span>
                <span>Publicación oficial: {{ $notice->published_at ? $notice->published_at->format('d/m/Y') : 'Vigente' }}</span>
            </div>

            <div class="whitespace-pre-line font-sans">
                {{ $notice->content }}
            </div>
        @else
            <p class="text-slate-500 italic">El aviso de privacidad se encuentra en proceso de actualización formal.</p>
        @endif
    </x-card>

    <div class="flex justify-end">
        <x-button variant="neutral" href="javascript:history.back()">
            Volver
        </x-button>
    </div>
</div>
@endsection
