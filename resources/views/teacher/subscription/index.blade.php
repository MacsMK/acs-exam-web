@extends('layouts.app')

@section('title', 'Suscripción SaaS y Cuotas')

@section('content')
<div class="space-y-8 max-w-6xl mx-auto">
    {{-- Encabezado --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">Suscripción SaaS y Límites de Uso</h1>
            <p class="text-sm text-slate-500 mt-1">
                Consulte sus cuotas de servicio, vigencia de plan y solicite ampliaciones comerciales de su paquete docente.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @php
                $waMessage = urlencode("Hola A-Code Solutions, deseo solicitar/renovar un paquete docente para mi cuenta:\nDocente: {$teacher->full_name}\nCorreo: {$teacher->email}\nID: {$teacher->id}");
            @endphp
            <a
                href="https://wa.me/{{ $whatsappNumber }}?text={{ $waMessage }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 bg-[#25D366] hover:bg-[#1EBE5D] text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-sm transition-all duration-200 hover:shadow active:scale-[0.98]"
            >
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                </svg>
                Atención Comercial por WhatsApp
            </a>
        </div>
    </div>

    {{-- Estado actual del docente --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card class="lg:col-span-1 border-acode-tech/30 bg-gradient-to-br from-white to-acode-surface/30">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-acode-tech mb-1">Plan Actual</h3>
            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-2xl font-black text-acode-primary">
                    {{ $activePackage ? $activePackage->name : 'Prueba Freemium' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mb-4">
                {{ $activePackage ? 'Paquete activo con cuota mensual asignada.' : 'Beneficio gratuito inicial (RF-SUS-010: límite histórico acumulado).' }}
            </p>

            <div class="pt-3 border-t border-slate-200/60 text-xs text-slate-600 space-y-2">
                <div class="flex justify-between">
                    <span>Estado:</span>
                    <span class="font-semibold text-emerald-700">● Activo</span>
                </div>
                <div class="flex justify-between">
                    <span>Modalidad de pago:</span>
                    <span class="font-medium text-slate-800">Transferencia / WhatsApp</span>
                </div>
            </div>
        </x-card>

        {{-- Barras de cuotas y consumo --}}
        <x-card class="lg:col-span-2 space-y-4">
            <h3 class="text-base font-bold text-acode-primary mb-3">Consumo de Cuotas del Paquete</h3>

            {{-- Grupos escolares --}}
            @php
                $maxGroups = $activePackage?->max_groups ?? 1;
                $usedGroups = $activePackage ? $activeGroupsCount : $cumulativeGroupsCount;
                $pctGroups = min(100, (int) round(($usedGroups / max(1, $maxGroups)) * 100));
            @endphp
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">Grupos Escolares Creados</span>
                    <span class="text-acode-primary">{{ $usedGroups }} / {{ $maxGroups }}</span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $pctGroups >= 100 ? 'bg-acode-danger' : 'bg-acode-tech' }} rounded-full transition-all duration-500" style="width: {{ $pctGroups }}%"></div>
                </div>
                @if (!$activePackage && $usedGroups >= 1)
                    <p class="text-[11px] text-acode-danger mt-1">Cota máxima de prueba gratuita alcanzada. Contrata un plan para agregar más grupos.</p>
                @endif
            </div>

            {{-- Banco de preguntas --}}
            @php
                $maxQuestions = $activePackage?->max_questions ?? 50;
                $pctQuestions = min(100, (int) round(($questionsCount / max(1, $maxQuestions)) * 100));
            @endphp
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">Reactivos en Banco de Preguntas</span>
                    <span class="text-acode-primary">{{ $questionsCount }} / {{ number_format($maxQuestions) }}</span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $pctQuestions >= 100 ? 'bg-acode-danger' : 'bg-acode-accent' }} rounded-full transition-all duration-500" style="width: {{ $pctQuestions }}%"></div>
                </div>
            </div>

            {{-- Exámenes activos --}}
            @php
                $maxExams = $activePackage?->max_active_exams ?? 1;
                $usedExams = $activePackage ? $examsCount : $cumulativeExamsCount;
                $pctExams = min(100, (int) round(($usedExams / max(1, $maxExams)) * 100));
            @endphp
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">Exámenes Guardados / Activos</span>
                    <span class="text-acode-primary">{{ $usedExams }} / {{ $maxExams }}</span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $pctExams >= 100 ? 'bg-acode-danger' : 'bg-acode-primary' }} rounded-full transition-all duration-500" style="width: {{ $pctExams }}%"></div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Catálogo de Paquetes Oficiales (MPW-V2 Tabla 15) --}}
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-acode-primary">Catálogo de Paquetes Disponibles</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ($packages as $pkg)
                @php
                    $isCurrent = $activePackage && $activePackage->id === $pkg->id;
                    $pkgWaMessage = urlencode("Hola A-Code Solutions, me interesa contratar/renovar el paquete {$pkg->name} (\${$pkg->monthly_price} MXN/mes) para mi cuenta docente:\nNombre: {$teacher->full_name}\nCorreo: {$teacher->email}");
                @endphp
                <x-card class="flex flex-col justify-between relative {{ $isCurrent ? 'ring-2 ring-acode-tech shadow-md' : 'border-slate-200' }}">
                    @if ($isCurrent)
                        <span class="absolute -top-3 right-4 bg-acode-tech text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full shadow-sm">
                            Tu Plan Actual
                        </span>
                    @endif

                    <div>
                        <h3 class="text-lg font-bold text-acode-primary mb-1">{{ $pkg->name }}</h3>
                        <div class="flex items-baseline gap-1 my-3">
                            <span class="text-3xl font-extrabold text-acode-primary">${{ number_format((float) $pkg->monthly_price, 0) }}</span>
                            <span class="text-xs text-slate-500">MXN / mes</span>
                        </div>

                        <ul class="space-y-2.5 text-xs text-slate-600 my-5">
                            <li class="flex items-center gap-2">
                                <span class="text-acode-accent font-bold">✓</span>
                                <span>Hasta <strong>{{ $pkg->max_groups ?? 'Ilimitados' }}</strong> grupos escolares</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-acode-accent font-bold">✓</span>
                                <span>Hasta <strong>{{ number_format((int) $pkg->max_questions) }}</strong> reactivos en banco</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-acode-accent font-bold">✓</span>
                                <span>Hasta <strong>{{ $pkg->max_concurrent_students }}</strong> alumnos en vivo</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-acode-accent font-bold">✓</span>
                                <span>Hasta <strong>{{ $pkg->max_active_exams }}</strong> exámenes guardados</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-acode-accent font-bold">✓</span>
                                <span>Evaluación con IA Gemini incluida</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        @if ($isCurrent)
                            <x-button variant="neutral" class="w-full" disabled>
                                Plan Vigente
                            </x-button>
                        @else
                            <a
                                href="https://wa.me/{{ $whatsappNumber }}?text={{ $pkgWaMessage }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center gap-2 bg-[#25D366] hover:bg-[#1EBE5D] text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-sm transition-all duration-200 active:scale-[0.98]"
                            >
                                Contratar vía WhatsApp
                            </a>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    </div>
</div>
@endsection
