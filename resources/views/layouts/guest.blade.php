{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: guest.blade.php (MPW-V2 §7.2, §8.1, §8.2, §8.10.3) --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'A-Code Solutions') · Evaluador de Exámenes</title>

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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/alerts.js') }}"></script>
</head>
<body class="min-h-screen bg-acode-surface font-sans text-slate-700 antialiased">
    <main class="min-h-screen lg:grid lg:grid-cols-2">

        {{-- Columna institucional (Izquierda) --}}
        <section class="relative isolate flex min-h-[400px] flex-col overflow-hidden bg-acode-primary px-7 py-8 text-white sm:px-12 lg:min-h-screen lg:px-14 lg:py-12">

            {{-- Elementos decorativos (Fondos circulares degradados con tokens A-Code) --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-48 -left-48 -z-10 h-[450px] w-[450px] rounded-full border border-acode-tech/20 bg-acode-tech/15 blur-2xl"
            ></div>

            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-44 -right-44 -z-10 h-80 w-80 rounded-full border border-acode-accent/20 bg-acode-accent/10 blur-xl"
            ></div>

            <div
                aria-hidden="true"
                class="pointer-events-none absolute bottom-0 left-0 -z-10 h-72 w-96 acode-auth-radial-bg"
            ></div>

            <div aria-hidden="true" class="pointer-events-none absolute -right-32 top-1/4 -z-10 h-80 w-80 rounded-full bg-acode-tech/10 blur-3xl"></div>

            {{-- Identidad principal del proyecto --}}
            <div class="relative max-w-xl pt-8 sm:pt-12 lg:pt-16">
                <a href="{{ route('landing') }}" class="group inline-flex items-center gap-2 text-xs font-semibold text-slate-300/80 hover:text-white transition mb-6">
                    <span aria-hidden="true">←</span> Volver a la página principal
                </a>

                <div class="mb-7 h-1 w-16 rounded-full bg-acode-accent"></div>

                <a href="{{ route('landing') }}" class="block">
                    <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl xl:text-6xl text-white">
                        Evaluador de<br class="hidden sm:block">
                        Exámenes
                    </h1>
                </a>

                <p class="mt-6 max-w-md text-base leading-relaxed text-slate-200 sm:text-lg">
                    Crea, aplica y evalúa exámenes de forma sencilla, segura y eficiente.
                </p>

                <div class="mt-9 flex items-center gap-3 text-sm text-slate-300">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-acode-accent"></span>
                    <span>Una plataforma para docentes y alumnos</span>
                </div>
            </div>

            {{-- Identidad institucional de A-Code Solutions --}}
            <footer class="relative mt-auto pl-8 pt-14 sm:pl-10">
                <div class="flex items-center gap-4 sm:gap-5">
                    {{-- Isotipo oficial --}}
                    <img
                        src="{{ asset('images/branding/isotipo.png') }}"
                        alt="Isotipo de A-Code Solutions"
                        class="h-14 w-14 shrink-0 object-contain sm:h-20 sm:w-20"
                    >

                    {{-- Separador vertical --}}
                    <div aria-hidden="true" class="h-14 w-px shrink-0 bg-slate-300/60 sm:h-16"></div>

                    {{-- Nombre y eslogan de la empresa --}}
                    <div class="min-w-0">
                        <p class="text-lg font-bold tracking-tight text-white sm:text-xl">
                            A-Code Solutions
                        </p>

                        <p class="mt-1 text-xs leading-relaxed text-slate-300 sm:text-sm">
                            Analizamos, diseñamos, transformamos.
                        </p>
                    </div>
                </div>
            </footer>
        </section>

        {{-- Columna del formulario (Derecha) --}}
        <section class="flex min-h-[550px] items-center justify-center px-5 py-12 sm:px-10 lg:min-h-screen lg:px-12 bg-acode-surface">
            <div class="w-full max-w-lg">
                <x-card class="shadow-xl border-slate-200 bg-white p-6 sm:p-10 rounded-2xl">
                    @if (session('status'))
                        <div class="mb-4 rounded-xl border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm flex items-center gap-2">
                            <x-icon name="success" class="w-4 h-4 text-acode-accent shrink-0" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-xl border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm">
                            <div class="flex items-center gap-1.5 font-semibold mb-1">
                                <x-icon name="warning" class="w-4 h-4 shrink-0" />
                                <span>Revisa los siguientes campos:</span>
                            </div>
                            <ul class="list-disc list-inside space-y-0.5 text-xs pl-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </x-card>

                <div class="mt-6 text-center text-xs text-slate-400 select-none">
                    <p>&copy; 2026 A-Code Solutions · Cumplimiento Normativo MPW-V2</p>
                </div>
            </div>
        </section>
    </main>

    @stack('scripts')
</body>
</html>
