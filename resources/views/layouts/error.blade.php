{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Plantilla: layouts/error.blade.php (MPW-V2 §6.6.2, §7.3, §8.1, §8.2, §8.10.7) --}}
{{-- Plantilla común de las páginas institucionales de error (403, 404, 500): un solo encabezado, tarjeta y pie (ACT-WEB-055). --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title') · A-Code Solutions</title>

    <link rel="icon" type="image/png" href="{{ asset('images/branding/isotipo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
</head>
<body class="min-h-screen bg-acode-surface font-sans text-acode-ink antialiased flex flex-col">
<body class="min-h-screen bg-acode-surface font-sans text-acode-ink antialiased flex flex-col">
    <header class="bg-acode-primary text-white border-b border-white/10 shadow-sm">
        <div class="max-w-5xl mx-auto px-6 h-16 flex items-center justify-between">
            {{-- Logotipo oficial para fondo oscuro, el mismo del menú lateral (MPW-V2 §8.1) --}}
            <a href="{{ url('/') }}" aria-label="Ir al inicio de A-Code Solutions">
                <img src="{{ asset('images/branding/logo-fondo-claro.png') }}" alt="A-Code Solutions" class="h-10 w-auto object-contain">
            </a>

            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/10 text-white/90">
                HTTP @yield('code')
            </span>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-5 py-12 sm:px-8">
        <section class="w-full max-w-xl bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="h-1.5 w-full @yield('accentBar')"></div>

            <div class="p-8 sm:p-10 text-center">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl @yield('iconBox')">
                    <x-icon :name="trim($__env->yieldContent('icon'))" class="h-8 w-8" />
                </div>

                <p class="text-5xl sm:text-6xl font-extrabold tracking-tight text-acode-primary leading-none">
                    @yield('code')
                </p>

                <h1 class="mt-4 text-2xl font-bold tracking-tight text-acode-primary">
                    @yield('title')
                </h1>

                <p class="mt-3 text-sm leading-relaxed text-slate-700 max-w-md mx-auto">
                    @yield('message')
                </p>

                {{-- Folio de incidente: código del sistema, por eso en Fira Code (MPW-V2 Tabla 13) --}}
                <div class="mt-6 inline-flex flex-col sm:flex-row items-center gap-1.5 sm:gap-3 rounded-xl bg-acode-surface border border-slate-200 px-4 py-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Folio de incidente
                    </span>
                    <span class="font-mono text-sm font-bold tracking-wider text-acode-primary select-all">
                        ACS-{{ strtoupper(substr(md5((string) microtime(true)), 0, 8)) }}
                    </span>
                </div>

                <div class="mt-8 flex flex-col-reverse sm:flex-row items-center justify-center gap-3">
                    <x-button type="button" variant="neutral" size="md" class="w-full sm:w-auto" onclick="window.history.back()">
                        <x-icon name="arrowLeft" class="h-4 w-4" />
                        Volver
                    </x-button>
                    <x-button :href="url('/')" variant="primary" size="md" class="w-full sm:w-auto">
                        Volver al inicio
                    </x-button>
                </div>
            </div>
        </section>
    </main>

    {{-- Pie institucional (MPW-V2 §8.10.7) --}}
    <footer class="py-6 text-center text-xs text-slate-500 select-none">
        <p>&copy; 2026 A-Code Solutions. Plataforma Evaluadora de Exámenes. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
