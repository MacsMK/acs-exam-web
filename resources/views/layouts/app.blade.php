<!DOCTYPE html>
{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     app.blade.php
    Ubicación:   resources/views/layouts/app.blade.php
    Descripción: Layout maestro de la plataforma.
                 Navegación lateral docente y navegación para estudiantes.
    ============================================================================
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'A-Code Solutions') · Plataforma de Exámenes</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="min-h-screen bg-acode-surface font-sans text-slate-700">

@auth
    @if (auth()->user()->role === 'teacher')

        <div x-data="{ sidebarOpen: false, profileOpen: false }"
             class="min-h-screen">

            {{-- Fondo del menú móvil --}}
            <div x-show="sidebarOpen"
                 x-cloak
                 x-transition.opacity
                 @click="sidebarOpen = false"
                 class="fixed inset-0 z-40 bg-black/50 lg:hidden">
            </div>

            {{-- Menú lateral docente --}}
            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-acode-primary text-white transform transition-transform duration-200 lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

                <div class="flex min-h-24 items-center justify-between border-b border-white/10 px-6 py-5">
                    <a href="{{ route('dashboard') }}" class="min-w-0">
                        <p class="text-base font-bold leading-tight text-white">
                            Evaluador de Exámenes
                        </p>
                        <p class="mt-2 text-xs text-white/60">
                            Panel docente
                        </p>
                    </a>

                    <button type="button"
                            @click="sidebarOpen = false"
                            class="rounded-lg p-2 text-white/70 hover:bg-white/10 lg:hidden"
                            aria-label="Cerrar menú">
                        ✕
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6 text-sm">

                    <a href="{{ route('dashboard') }}"
                       class="flex items-center gap-3 rounded-lg px-4 py-3 transition
                       {{ request()->routeIs('dashboard') ? 'bg-acode-tech text-white font-semibold' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                        <x-nav-icon name="home" />
                        Inicio
                    </a>

                    <a href="{{ route('groups.index') }}"
                       class="flex items-center gap-3 rounded-lg px-4 py-3 transition
                       {{ request()->routeIs('groups.*') ? 'bg-acode-tech text-white font-semibold' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                        <x-nav-icon name="groups" />
                        Grupos
                    </a>

                    <a href="{{ route('questions.index') }}"
                       class="flex items-center gap-3 rounded-lg px-4 py-3 transition
                       {{ request()->routeIs('questions.*') ? 'bg-acode-tech text-white font-semibold' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                        <x-nav-icon name="questions" />
                        Banco de preguntas
                    </a>

                    <a href="{{ route('exams.index') }}"
                       class="flex items-center gap-3 rounded-lg px-4 py-3 transition
                       {{ request()->routeIs('exams.*') ? 'bg-acode-tech text-white font-semibold' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                        <x-nav-icon name="exams" />
                        Exámenes
                    </a>

                    {{-- Secciones docentes en desarrollo --}}
                    @foreach ([
                        ['slug' => 'revision', 'label' => 'Revisión', 'icon' => 'review'],
                        ['slug' => 'calificaciones', 'label' => 'Calificaciones', 'icon' => 'grades'],
                        ['slug' => 'suscripcion', 'label' => 'Suscripción', 'icon' => 'subscription'],
                        ['slug' => 'creditos-ia', 'label' => 'Créditos de IA', 'icon' => 'ai-credits'],
                    ] as $item)

                        <a href="{{ route('teacher.coming-soon', ['section' => $item['slug']]) }}"
                        class="flex items-center justify-between gap-2 rounded-lg px-4 py-3 transition
                        {{ request()->routeIs('teacher.coming-soon')
                            && request()->route('section') === $item['slug']
                            ? 'bg-acode-tech font-semibold text-white'
                            : 'text-white/75 hover:bg-white/10 hover:text-white' }}">

                            <span class="flex min-w-0 items-center gap-3">
                                <x-nav-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                            </span>

                            <span class="shrink-0 text-xs opacity-75">
                                Próx.
                            </span>
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-white/10 px-4 py-5">
                    <img src="{{ asset('images/branding/logo-fondo-claro.png') }}"
                        alt="A-Code Solutions"
                        class="mx-auto max-h-16 w-full object-contain">

                    <p class="mt-3 text-center text-xs leading-relaxed text-white/65">
                        Analizamos, diseñamos, transformamos.
                    </p>
                </div>
            </aside>
            {{-- Contenido principal --}}
            <div class="min-h-screen lg:pl-64">

                {{-- Encabezado --}}
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">

                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="sidebarOpen = true"
                                class="rounded-lg p-2 text-acode-primary hover:bg-acode-surface lg:hidden"
                                aria-label="Abrir menú">
                            ☰
                        </button>

                        <span class="text-sm font-semibold text-acode-primary sm:text-base">
                            @yield('title', 'Panel docente')
                        </span>
                    </div>

                    {{-- Perfil --}}
                    <div class="relative" @click.outside="profileOpen = false">

                        <button type="button"
                                @click="profileOpen = !profileOpen"
                                :aria-expanded="profileOpen.toString()"
                                class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-100">

                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-acode-primary text-sm font-bold text-white">
                                {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                            </div>

                            <span class="hidden text-sm font-medium text-acode-primary sm:block">
                                {{ auth()->user()->full_name }}
                            </span>

                            <span class="text-xs text-slate-400">▾</span>
                        </button>

                        <div x-show="profileOpen"
                             x-cloak
                             x-transition
                             class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-2 shadow-lg">

                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="text-sm font-semibold text-acode-primary">
                                    {{ auth()->user()->full_name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Docente
                                </p>
                            </div>

                            {{-- Acceso al perfil docente --}}
                            <a href="{{ route('teacher.coming-soon', ['section' => 'perfil']) }}"
                            class="flex min-h-11 items-center gap-3 px-4 py-3 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">
                                    <circle cx="12" cy="8" r="4"/>
                                    <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                </svg>

                                <span>Mi perfil</span>
                            </a>

                            {{-- Acceso al aviso de privacidad --}}
                            <a href="{{ route('teacher.coming-soon', ['section' => 'aviso-privacidad']) }}"
                            class="flex min-h-11 items-center gap-3 px-4 py-3 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                                    <path d="M9 12l2 2 4-4"/>
                                </svg>
                                <span>Aviso de privacidad</span>
                            </a>

                            <div class="my-1 border-t border-slate-100"></div>
                            <form method="POST" action="{{ route('auth.logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full px-4 py-2 text-left text-sm text-acode-danger hover:bg-red-50">
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                {{-- Contenido --}}
                <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

                    @if (session('status'))
                        <div class="mb-6 rounded-lg border border-acode-accent/30 bg-acode-accent/10 px-4 py-3 text-sm text-acode-primary">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any() && !request()->routeIs('exams.create'))
                        <div class="mb-6 rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger">
                            <p class="mb-1 font-semibold">Revisa lo siguiente:</p>
                            <ul class="list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

    @else

        {{-- Navegación existente para estudiantes y otros roles --}}
        <nav class="bg-acode-primary text-white">
            <div class="mx-auto flex min-h-16 max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('dashboard') }}" class="text-lg font-bold">
                    A-Code Solutions
                </a>

                <div class="flex flex-wrap items-center gap-4 text-sm">
                    @if (auth()->user()->role === 'student')
                        <a href="{{ route('student.exams.index') }}" class="hover:text-acode-warning">
                            Mis exámenes
                        </a>
                        <a href="{{ route('student.groups.join.form') }}" class="hover:text-acode-warning">
                            Unirme a un grupo
                        </a>
                    @endif

                    <span class="text-white/80">
                        {{ auth()->user()->full_name }}
                    </span>

                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-white/30 px-3 py-2 text-xs hover:bg-white/10">
                            Salir
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">

            @if (session('status'))
                <div class="mb-6 rounded-lg border border-acode-accent/30 bg-acode-accent/10 px-4 py-3 text-sm text-acode-primary">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any() && !request()->routeIs('exams.create'))
                <div class="mb-6 rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger">
                    <p class="mb-1 font-semibold">Revisa lo siguiente:</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

    @endif
@else
    <main class="min-h-screen">
        @yield('content')
    </main>
@endauth

@stack('scripts')
</body>
</html>
