@props(['package', 'teacher', 'whatsappNumber'])

@php
    $message = 'Hola A-Code Solutions, solicito apoyo comercial para contratar el paquete ' . $package->name
        . ' ($' . $package->monthly_price . ' MXN/mes).' . PHP_EOL
        . 'Docente: ' . $teacher->full_name . PHP_EOL
        . 'Correo: ' . $teacher->email;
@endphp

<x-card padding="p-4 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl leading-7 font-semibold text-acode-primary">Atención comercial</h2>
            <p class="mt-1 text-sm text-slate-700">Abre WhatsApp en otra pestaña con un mensaje preparado.</p>
        </div>
        <x-button
            :href="'https://wa.me/' . $whatsappNumber . '?text=' . urlencode($message)"
            target="_blank"
            rel="noopener noreferrer"
            variant="secondary"
            class="max-w-full"
        >
            <x-icon name="chat" class="h-4 w-4 shrink-0" />
            Abrir WhatsApp comercial
        </x-button>
    </div>
</x-card>
