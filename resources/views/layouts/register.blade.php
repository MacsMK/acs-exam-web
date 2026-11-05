<!DOCTYPE html>
{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     register.blade.php
    Ubicación:   resources/views/layouts/register.blade.php
    Descripción: Layout visual compartido para el registro de docentes y alumnos.
                 Conserva el funcionamiento de los formularios.
    ============================================================================
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Registro') · Evaluador de Exámenes</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com/3.4.13"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui']
                    },
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

    @php
        $isStudent = trim($__env->yieldContent('registration_role')) === 'student';
    @endphp

    <main class="min-h-screen lg:grid lg:grid-cols-3">

        {{-- Columna institucional --}}
        <section class="relative isolate flex min-h-[350px] flex-col overflow-hidden bg-acode-primary px-7 py-8 text-white sm:px-10 lg:col-span-1 lg:min-h-screen lg:px-10 lg:py-12">

            {{-- Arco principal: posición según el registro --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-44 -z-10 h-[420px] w-[420px] rounded-full {{ $isStudent ? '-left-44' : '-right-44' }}"
                style="
                    background: linear-gradient(
                        145deg,
                        rgba(55, 125, 200, 0.42) 0%,
                        rgba(37, 99, 166, 0.25) 55%,
                        rgba(20, 43, 74, 0.04) 100%
                    );
                    -webkit-mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 115px),
                        #000 calc(100% - 85px)
                    );
                    mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 115px),
                        #000 calc(100% - 85px)
                    );
                "
            ></div>

            {{-- Arco secundario: diagonal invertida --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-32 -z-10 h-72 w-72 rounded-full {{ $isStudent ? '-right-32' : '-left-32' }}"
                style="
                    background: linear-gradient(
                        135deg,
                        rgba(55, 125, 200, 0.32) 0%,
                        rgba(37, 99, 166, 0.16) 60%,
                        rgba(20, 43, 74, 0.02) 100%
                    );
                    -webkit-mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 55px),
                        #000 calc(100% - 40px)
                    );
                    mask: radial-gradient(
                        farthest-side,
                        transparent calc(100% - 55px),
                        #000 calc(100% - 40px)
                    );
                "
            ></div>

            {{-- Iluminación decorativa --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute bottom-0 -z-10 h-64 w-80 {{ $isStudent ? 'right-0' : 'left-0' }}"
                style="
                    background: radial-gradient(
                        ellipse at 20% 90%,
                        rgba(37, 99, 166, 0.13) 0%,
                        rgba(37, 99, 166, 0.05) 45%,
                        transparent 75%
                    );
                "
            ></div>

            {{-- Identidad principal del proyecto --}}
            <div class="relative max-w-md pt-8 lg:pt-12">

                <div class="mb-7 h-1 w-16 rounded-full bg-acode-accent"></div>

                <h1 class="text-4xl font-extrabold leading-tight tracking-tight lg:text-4xl xl:text-5xl">
                    Evaluador de Exámenes
                </h1>

                <p class="mt-6 text-base leading-relaxed text-slate-200">
                    @yield('registration_description')
                </p>

                <div class="mt-8 flex items-start gap-3 text-sm text-slate-300">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-acode-accent"></span>
                    <span>@yield('registration_tagline')</span>
                </div>
            </div>

            {{-- Identidad institucional --}}
            <footer class="relative mt-auto pt-14">

                <div class="flex items-center gap-3">

                    <img
                        src="{{ asset('images/branding/isotipo.png') }}"
                        alt="Isotipo de A-Code Solutions"
                        class="h-14 w-14 shrink-0 object-contain xl:h-16 xl:w-16"
                    >

                    <div
                        aria-hidden="true"
                        class="h-14 w-px shrink-0 bg-slate-300/60"
                    ></div>

                    <div class="min-w-0">
                        <p class="text-base font-bold tracking-tight text-white xl:text-lg">
                            A-Code Solutions
                        </p>

                        <p class="mt-1 text-xs leading-relaxed text-slate-300">
                            Analizamos, diseñamos, transformamos.
                        </p>
                    </div>
                </div>
            </footer>
        </section>

        {{-- Columna del formulario --}}
        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:col-span-2 lg:px-12">

            <div class="w-full max-w-2xl">

                <div class="rounded-2xl border border-slate-200 bg-white px-6 py-8 shadow-xl shadow-acode-primary/10 sm:px-10 sm:py-10">

                    {{-- Mensajes del sistema --}}
                    @if (session('status'))
                        <div
                            role="status"
                            class="mb-5 rounded-lg border border-acode-accent/30 bg-acode-accent/10 px-4 py-3 text-sm text-acode-primary"
                        >
                            {{ session('status') }}
                        </div>
                    @endif

                    {{-- Errores de validación del servidor --}}
                    @if ($errors->any())
                        <div
                            role="alert"
                            class="mb-5 rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger"
                        >
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Formulario correspondiente --}}
                    @yield('content')

                </div>
            </div>
        </section>
    </main>

    {{-- Scripts propios de cada formulario --}}
    @stack('scripts')

</body>
</html>
