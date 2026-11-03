{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: exam-runner.blade.php (MPW-V2 §7.2, §7.7, §8.10.2, ADR-020) --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Presentando examen') · A-Code Solutions</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com/3.4.13"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
                        mono: ['Fira Code', 'ui-monospace', 'SFMono-Regular'],
                    },
                    colors: {
                        'acode-navy': '#142B4A',
                        'acode-primary': '#142B4A',
                        'acode-primary-dark': '#0E1F36',
                        'acode-blue': '#2563A6',
                        'acode-tech': '#2563A6',
                        'acode-tech-dark': '#1D4F85',
                        'acode-cyan': '#16A6A1',
                        'acode-accent': '#16A6A1',
                        'acode-warning': '#F4B942',
                        'acode-danger': '#DC2626',
                        'acode-surface': '#E9F0F5',
                        'acode-ink': '#0F172A',
                        acode: {
                            navy: '#142B4A',
                            primary: '#142B4A',
                            'primary-dark': '#0E1F36',
                            blue: '#2563A6',
                            tech: '#2563A6',
                            'tech-dark': '#1D4F85',
                            cyan: '#16A6A1',
                            accent: '#16A6A1',
                            warning: '#F4B942',
                            danger: '#DC2626',
                            surface: '#E9F0F5',
                            ink: '#0F172A',
                        },
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/alerts.js') }}"></script>
</head>
<body class="bg-slate-50 font-sans text-acode-ink min-h-screen flex flex-col select-none antialiased">
    {{-- Encabezado libre de distracciones (Distraction-Free) --}}
    <header class="sticky top-0 z-40 h-14 bg-acode-navy text-white shadow-md border-b border-white/10 select-none">
        <div class="max-w-4xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-logo variant="compact" class="w-7 h-7 text-white" />
                <div class="flex flex-col">
                    <span class="font-bold text-sm tracking-tight text-white leading-tight">A-Code Solutions</span>
                    <span class="text-[10px] text-white/70">Modo de evaluación supervisada</span>
                </div>
            </div>

            @auth
                <div class="flex items-center gap-2">
                    <span class="text-xs text-white/80 font-medium">{{ auth()->user()->full_name }}</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" title="Sesión activa y monitoreada"></span>
                </div>
            @endauth
        </div>
    </header>

    <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm flex items-center gap-2">
                <x-icon name="success" class="w-5 h-5 text-acode-accent shrink-0" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm">
                <div class="flex items-center gap-1.5 font-semibold mb-1">
                    <x-icon name="warning" class="w-4 h-4 shrink-0" />
                    <span>Revisa lo siguiente:</span>
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Protección anti-trampa y sensores de ventana --}}
    <script>
        // Bloqueo de historial y navegación popstate (ADR-020)
        history.pushState(null, '', location.href);
        window.addEventListener('popstate', function () {
            history.pushState(null, '', location.href);
            if (typeof AcodeAlert !== 'undefined') {
                AcodeAlert.warning('Navegación restringida', 'No está permitido regresar a reactivos anteriores o salir del flujo con el botón Atrás del navegador.');
            }
        });

        // Sensor anti-fraude de pérdida de foco o cambio de pestaña (MPW-V2 §7.7, RNF-SEG-001)
        let focusLossWarnings = 0;
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                focusLossWarnings++;
                console.warn(`A-Code Anti-Cheat: Pérdida de foco detectada (${focusLossWarnings}).`);
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.warning(
                        'Alerta de Supervisión Anti-Trampa',
                        `Se ha detectado un cambio de pestaña o ventana (${focusLossWarnings}). Esta incidencia ha sido registrada en tu intento de examen.`
                    );
                }
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
