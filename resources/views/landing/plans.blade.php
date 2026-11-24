<section class="plans section-space" id="planes" aria-labelledby="planes-titulo">
    <div class="wrap">
        <div class="plans-heading">
            <h2 class="section-title" id="planes-titulo">Planes para docentes.</h2>
            <p class="intro">Elige según el tamaño de tus grupos. La suscripción es mensual y el acceso para tus estudiantes es gratuito.</p>
        </div>
        <div class="plan-grid">
            @forelse ($packages as $package)
                <article @class(['plan', 'plan--accent' => $package->tier === 'advanced'])>
                    <header class="plan-head">
                        <h3>{{ $package->name }}</h3>
                        <x-icon name="userTie" />
                    </header>
                    <p class="plan-price"><strong>${{ number_format((float) $package->monthly_price, 2) }}</strong> <span>MXN / mes</span></p>
                    <x-subscription.package-capabilities :package="$package" class="plan-features" />
                </article>
            @empty
                <p class="plan-empty">Por el momento no hay planes de pago disponibles.</p>
            @endforelse
        </div>
        @if ($packages->isNotEmpty())
            <div class="plan-access">
                <p>Elige tu plan al verificar tu cuenta. Puedes cancelar la renovación desde el panel docente.</p>
                <a href="{{ route('auth.register.teacher.show') }}">Registrarme para elegir un plan</a>
            </div>
        @endif
        <div class="trial">
            <div>
                <h3>Prueba gratuita sin tarjeta</h3>
                <p>Incluye {{ $freemiumLimits['max_groups'] }} grupo y {{ $freemiumLimits['max_exams'] }} examen en total, hasta {{ $freemiumLimits['max_students'] }} alumnos por grupo, {{ $freemiumLimits['max_questions'] }} reactivos y {{ $freemiumLimits['max_ai_evaluations'] }} créditos de IA.</p>
            </div>
            <a class="button button--primary" href="{{ route('auth.register.teacher.show') }}">Probar gratis</a>
        </div>
    </div>
</section>
