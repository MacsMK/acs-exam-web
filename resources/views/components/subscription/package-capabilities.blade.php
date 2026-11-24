@props(['package'])

<ul {{ $attributes->class(['space-y-2.5 text-sm text-slate-700']) }}>
    <li class="flex items-start gap-2">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-acode-accent-dark" />
        @if ($package->max_groups === null)
            <span><strong>Ilimitados</strong> grupos escolares activos</span>
        @else
            <span>Hasta <strong>{{ number_format((int) $package->max_groups) }}</strong> grupos escolares activos</span>
        @endif
    </li>
    <li class="flex items-start gap-2">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-acode-accent-dark" />
        @if ($package->max_questions === null)
            <span><strong>Ilimitados</strong> reactivos en banco</span>
        @else
            <span>Hasta <strong>{{ number_format((int) $package->max_questions) }}</strong> reactivos en banco</span>
        @endif
    </li>
    <li class="flex items-start gap-2">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-acode-accent-dark" />
        <span>Hasta <strong>{{ number_format((int) $package->max_concurrent_students) }}</strong> alumnos inscritos por grupo</span>
    </li>
    <li class="flex items-start gap-2">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-acode-accent-dark" />
        @if ($package->max_active_exams === null)
            <span><strong>Ilimitados</strong> exámenes activos simultáneos</span>
        @else
            <span>Hasta <strong>{{ number_format((int) $package->max_active_exams) }}</strong> exámenes activos simultáneos</span>
        @endif
    </li>
    <li class="flex items-start gap-2">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-acode-accent-dark" />
        @if ($package->ai_requests_limit === null)
            <span>Créditos de IA <strong>ilimitados</strong></span>
        @else
            <span><strong>{{ number_format((int) $package->ai_requests_limit) }}</strong> créditos de IA por ciclo mensual</span>
        @endif
    </li>
</ul>
