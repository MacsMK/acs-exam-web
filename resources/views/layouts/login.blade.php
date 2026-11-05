<!DOCTYPE html>
{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     login.blade.php
    Ubicación:   resources/views/layouts/login.blade.php
    Descripción: Layout visual exclusivo del inicio de sesión.
                 Conserva el formulario y el flujo de autenticación.
    ============================================================================
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Iniciar sesión') · Evaluador de Exámenes</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com/3.4.13"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                    colors: {
                        acode: {
                            primary: '#142B4A',
                            tech: '#2563A6',
                            accent: '#16A6A1',
                            warning: '#F4B942',
                            surface: '#E9F0F5',
                            danger: '#DC2626',
                        },
                    },
                },
            },
        };
    </script>
</head>
<body class="min-h-screen bg-acode-surface font-sans text-slate-700">
    <main class="min-h-screen lg:grid lg:grid-cols-2">
 
        {{-- Columna institucional --}}
        <section class="relative isolate flex min-h-[400px] flex-col overflow-hidden bg-acode-primary px-7 py-8 text-white sm:px-12 lg:min-h-screen lg:px-14 lg:py-12">

            {{-- Elementos decorativos --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-48 -left-48 -z-10 h-[450px] w-[450px] rounded-full"
                style="
                    background: linear-gradient(
                        135deg,
                        rgba(55, 125, 200, 0.40) 0%,
                        rgba(37, 99, 166, 0.25) 45%,
                        rgba(20, 43, 74, 0.04) 100%
                    );
                    -webkit-mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 135px),
                        #000 calc(100% - 100px)
                    );
                    mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 135px),
                        #000 calc(100% - 100px)
                    );
                "
            ></div>

            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-44 -right-44 -z-10 h-80 w-80 rounded-full"
                style="
                    background: linear-gradient(
                        145deg,
                        rgba(55, 125, 200, 0.28) 0%,
                        rgba(37, 99, 166, 0.14) 55%,
                        rgba(20, 43, 74, 0.02) 100%
                    );
                    -webkit-mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 45px),
                        #000 calc(100% - 40px)
                    );
                    mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 45px),
                        #000 calc(100% - 40px)
                    );
                "
            ></div>

            <div
                aria-hidden="true"
                class="pointer-events-none absolute bottom-0 left-0 -z-10 h-72 w-96"
                style="
                    background: radial-gradient(
                        ellipse at 35% 80%,
                        rgba(37, 99, 166, 0.18) 0%,
                        rgba(37, 99, 166, 0.07) 45%,
                        transparent 75%
                    );
                "
            ></div>

            <div aria-hidden="true" class="pointer-events-none absolute -right-32 top-1/4 -z-10 h-80 w-80 rounded-full bg-acode-tech/10 blur-3xl"></div>

            {{-- Identidad principal del proyecto --}}
            <div class="relative max-w-xl pt-8 sm:pt-12 lg:pt-16">

                <div class="mb-7 h-1 w-16 rounded-full bg-acode-accent"></div>

                <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl xl:text-6xl">
                    Evaluador de<br class="hidden sm:block">
                    Exámenes
                </h1>

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

        {{-- Columna del formulario --}}
        <section class="flex min-h-[550px] items-center justify-center px-5 py-12 sm:px-10 lg:min-h-screen lg:px-12">
            <div class="w-full max-w-lg">
                <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10 shadow-xl shadow-acode-primary/10 sm:px-10 sm:py-12">

                    @if (session('status'))
                        <div role="status" class="mb-5 rounded-lg border border-acode-accent/30 bg-acode-accent/10 px-4 py-3 text-sm text-acode-primary">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div role="alert" class="mb-5 rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </section>
    </main>
</body>
</html>
