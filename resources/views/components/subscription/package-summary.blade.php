@props(['package'])

<x-card padding="p-4 sm:p-6">
    <div class="space-y-4">
        <x-section-header title="Resumen del paquete" :institutional="true" />
        <x-summary-list>
            <x-summary-item :label="$package->name" :value="'$' . number_format((float) $package->monthly_price, 2) . ' MXN'" note="Por mes" />
            <x-summary-item label="Vigencia del paquete" value="Un mes desde la activación" compact />
            <x-summary-item label="Activación" value="Al aprobar el pago simulado" compact />
        </x-summary-list>
        <x-subscription.package-capabilities :package="$package" class="border-t border-slate-200 pt-4" />
    </div>
</x-card>
