{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-question.files — imagen y PDF de la pregunta desde el disco privado (RF-BAN-012, RF-BAN-013, ACT-WEB-026) --}}
@props([
    'questionId',
    'hasImage' => false,
    'hasPdf' => false,
])

@if ($hasImage || $hasPdf)
    <div {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @if ($hasImage)
            <img
                src="{{ route('questions.file', ['question' => $questionId, 'kind' => 'imagen']) }}"
                alt="Imagen de la pregunta"
                class="max-h-96 max-w-full rounded-lg border border-slate-200 bg-white object-contain"
                loading="lazy"
            >
        @endif
        @if ($hasPdf)
            <a
                href="{{ route('questions.file', ['question' => $questionId, 'kind' => 'pdf']) }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-acode-tech hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-acode-tech"
            >
                <x-icon name="document" class="h-4 w-4" />
                Ver PDF de apoyo
            </a>
        @endif
    </div>
@endif
