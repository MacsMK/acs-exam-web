<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="Crea exámenes, supervisa a tu grupo en vivo y revisa sus resultados con A-Code Solutions. Conoce los planes docentes y la prueba gratuita.">
    <title>A-Code Solutions · Plataforma de evaluación docente</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/isotipo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing-experience.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing-controls.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing-stack.css') }}">
    <script src="{{ asset('js/landing.js') }}" defer></script>
    <script src="{{ asset('js/landing-demo.js') }}" defer></script>
</head>
<body id="inicio">
    @php
        $sections = [
            'capacidades' => 'Capacidades',
            'como-funciona' => 'Cómo funciona',
            'planes' => 'Planes',
            'movil' => 'Estudiantes',
        ];
    @endphp
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header class="masthead">
        <div class="wrap masthead-inner">
            <a class="brand" href="{{ route('landing') }}" aria-label="A-Code Solutions · Inicio">
                <x-logo :show-tagline="false" />
            </a>
            <nav class="desktop-nav" aria-label="Navegación principal">
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}"><span class="nav-roll"><span>{{ $label }}</span></span></a>
                @endforeach
            </nav>
            <div class="header-actions">
                <a class="login-link" href="{{ route('auth.login.show') }}">
                    <x-icon name="userCircle" /> Iniciar sesión
                </a>
                <details class="mobile-menu">
                    <summary aria-label="Menú de navegación">
                        <x-icon name="bars3" class="menu-open-icon" />
                        <x-icon name="xMark" class="menu-close-icon" />
                    </summary>
                    <nav aria-label="Navegación móvil">
                        @foreach ($sections as $id => $label)
                            <a href="#{{ $id }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="contenido" tabindex="-1">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-copy">
                <div class="hero-copy-inner">
                    <h1 id="hero-title">
                        <span class="headline-line"><span>Tus exámenes,</span></span>
                        <span class="headline-line"><em>bajo control.</em></span>
                    </h1>
                    <p class="hero-description">Crea evaluaciones con tu banco de reactivos, supervisa a tu grupo en vivo y revisa los resultados desde un solo lugar.</p>
                    <div class="hero-actions">
                        <a class="button button--primary" href="{{ route('auth.register.teacher.show') }}">Probar gratis</a>
                        <a class="button button--outline" href="#planes">Ver planes</a>
                    </div>
                </div>
            </div>

            <figure class="hero-figure">
                <div class="figure-stage">
                    @include('landing.stack')
                </div>
            </figure>
        </section>

        @include('landing.capabilities')
        @include('landing.workflow')
        @include('landing.plans')
        @include('landing.students')
    </main>

    <footer class="footer">
        <div class="wrap">
            <div class="footer-main">
                <div>
                    <a class="brand" href="#inicio" aria-label="A-Code Solutions · Volver al inicio"><x-logo :show-tagline="false" /></a>
                    <p>Analizamos, diseñamos, transformamos.</p>
                </div>
                <nav aria-label="Plataforma">
                    <h2>Plataforma</h2>
                    <a href="#capacidades">Capacidades</a>
                    <a href="#planes">Planes y precios</a>
                    <a href="#movil">Para estudiantes</a>
                </nav>
                <nav aria-label="Acceso">
                    <h2>Acceso</h2>
                    <a href="{{ route('auth.login.show') }}">Iniciar sesión</a>
                    <a href="{{ route('auth.register.teacher.show') }}">Registro docente</a>
                    <a href="{{ route('auth.register.student.show') }}">Registro estudiantil</a>
                </nav>
            </div>
            <div class="footer-legal">
                <span>© {{ now()->year }} A-Code Solutions</span>
                <a href="{{ route('privacy.show.public') }}" data-privacy-link>Aviso de privacidad</a>
                <a href="{{ route('terms.public') }}">Términos y condiciones</a>
            </div>
        </div>
    </footer>

    <dialog class="privacy-dialog" id="aviso" aria-labelledby="aviso-titulo">
        <div class="privacy-inner">
            <header>
                <h2 id="aviso-titulo">Aviso de privacidad integral</h2>
                <form method="dialog"><button class="icon-button" aria-label="Cerrar aviso de privacidad"><x-icon name="xMark" /></button></form>
            </header>
            <div class="privacy-content" tabindex="0">
                @if ($privacyNotice)
                    <p><b>Versión {{ $privacyNotice->version }}</b></p>
                    <p class="notice-text">{{ $privacyNotice->content }}</p>
                @else
                    <p>El aviso de privacidad se encuentra en proceso de publicación.</p>
                @endif
            </div>
            <a href="{{ route('privacy.show.public') }}">Abrir el aviso en su página</a>
        </div>
    </dialog>
</body>
</html>
