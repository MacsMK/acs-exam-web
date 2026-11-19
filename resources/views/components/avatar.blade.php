{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-avatar (RF-AUT-008, RF-AUT-009; MPW-V2 §7.3, Tabla 16) --}}
{{-- Muestra la fotografía de perfil si existe; si no, la imagen predeterminada con la inicial del nombre (ADR-049). --}}
@props([
    'user',              // cuenta cuyo avatar se muestra
    'size' => 'md',      // sm (h-8), md (h-9), lg (h-12)
])
@php
    $sizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-12 w-12 text-base',
        default => 'h-9 w-9 text-sm',
    };
    $initial = mb_strtoupper(mb_substr($user->first_name ?: $user->full_name, 0, 1));
@endphp
@if ($user->profile_photo_path)
    <img
        src="{{ route('users.photo', $user) }}"
        alt="Fotografía de {{ $user->full_name }}"
        {{ $attributes->merge(['class' => 'shrink-0 rounded-full object-cover shadow-sm ' . $sizeClass]) }}
    >
@else
    <div
        role="img"
        aria-label="Imagen predeterminada de {{ $user->full_name }}"
        {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center rounded-full bg-acode-primary font-bold text-white shadow-sm ' . $sizeClass]) }}
    >{{ $initial }}</div>
@endif
