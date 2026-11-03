{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: app.blade.php (MPW-V2 §7.2, §8.1, §8.2, §8.10.1, §8.10.4, §8.10.7) --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'A-Code Solutions') · Plataforma Evaluadora</title>

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/alerts.js') }}"></script>
</head>
<body
    x-data="{
        sidebarOpen: true,
        mobileDrawerOpen: false,
        userDropdownOpen: false,
        confirmLogout() {
            if (typeof AcodeAlert !== 'undefined') {
                AcodeAlert.confirm({
                    title: '¿Cerrar sesión?',
                    text: 'Se dará por finalizada tu sesión de trabajo en el sistema.',
                    confirmButtonText: 'Cerrar sesión',
                    cancelButtonText: 'Cancelar',
                    isDanger: true,
                    onConfirm: () => document.getElementById('logout-form').submit()
                });
            } else {
                if (confirm('¿Cerrar sesión?')) {
                    document.getElementById('logout-form').submit();
                }
            }
        }
    }"
    class="bg-acode-surface font-sans text-acode-ink min-h-screen flex flex-col antialiased"
>
    {{-- Navbar Superior Fijo --}}
    <nav class="sticky top-0 z-40 bg-acode-navy text-white shadow-md border-b border-white/10 select-none">
        <div class="px-4 lg:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                {{-- Botón Hamburger para Sidebar (Móvil y Desktop) --}}
                <button
                    type="button"
                    @click="if (window.innerWidth < 1024) { mobileDrawerOpen = !mobileDrawerOpen } else { sidebarOpen = !sidebarOpen }"
                    class="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition focus:outline-none focus:ring-2 focus:ring-acode-accent"
                    aria-label="Alternar barra lateral"
                >
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <a href="{{ route('dashboard') }}" class="flex items-center">
                    <x-logo variant="negative" />
                </a>
            </div>

            @auth
                <div class="flex items-center gap-4">
                    {{-- Badge de Rol --}}
                    <div class="hidden sm:flex items-center gap-2">
                        @if (auth()->user()->role === 'teacher')
                            <x-badge variant="info">Docente</x-badge>
                        @elseif (auth()->user()->role === 'student')
                            <x-badge variant="accent">Estudiante</x-badge>
                        @endif
                    </div>

                    {{-- Perfil de Usuario y Dropdown Alpine.js --}}
                    <div class="relative" @click.outside="userDropdownOpen = false">
                        <button
                            type="button"
                            @click="userDropdownOpen = !userDropdownOpen"
                            class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-white/10 transition focus:outline-none focus:ring-2 focus:ring-acode-accent"
                            aria-expanded="false"
                            :aria-expanded="userDropdownOpen.toString()"
                        >
                            {{-- Avatar con Iniciales --}}
                            <div class="w-9 h-9 rounded-full bg-acode-tech text-white font-bold flex items-center justify-center text-sm shadow-sm ring-2 ring-white/20">
                                {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->first_name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="hidden md:flex flex-col text-left">
                                <span class="text-xs font-semibold text-white leading-tight">
                                    {{ auth()->user()->full_name }}
                                </span>
                                <span class="text-[11px] text-white/70">
                                    {{ auth()->user()->role === 'teacher' ? 'Profesor titular' : 'Alumno registrado' }}
                                </span>
                            </div>
                            <x-icon name="chevronDown" class="w-4 h-4 text-white/70" />
                        </button>

                        {{-- Menú Desplegable de Perfil --}}
                        <div
                            x-show="userDropdownOpen"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-xl border border-slate-200 py-1.5 text-slate-800 z-50 divide-y divide-slate-100"
                            style="display: none;"
                        >
                            <div class="px-4 py-2.5">
                                <p class="text-xs font-semibold text-slate-800">{{ auth()->user()->full_name }}</p>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ auth()->user()->email }}</p>
                            </div>

                            <div class="py-1">
                                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
                                    <x-icon name="reportBar" class="w-4 h-4 text-acode-tech" />
                                    <span>Panel de control</span>
                                </a>
                            </div>

                            <div class="py-1">
                                <button
                                    type="button"
                                    @click="userDropdownOpen = false; confirmLogout()"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-acode-danger hover:bg-acode-danger/10 transition text-left"
                                >
                                    <x-icon name="lock" class="w-4 h-4 text-acode-danger" />
                                    <span>Cerrar sesión</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Formulario oculto de logout --}}
                    <form id="logout-form" method="POST" action="{{ route('auth.logout') }}" class="hidden">
                        @csrf
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    {{-- Estructura de Pantalla: Sidebar + Contenido Principal --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Barra Lateral de Navegación (Desktop) --}}
        @auth
            <aside
                :class="sidebarOpen ? 'w-64' : 'w-[72px]'"
                class="hidden lg:flex flex-col bg-white border-r border-slate-200 transition-all duration-300 ease-in-out shrink-0 select-none shadow-sm"
            >
                <div class="p-3 border-b border-slate-100 flex items-center" :class="sidebarOpen ? 'justify-between' : 'justify-center'">
                    <span x-show="sidebarOpen" class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-2">
                        Menú de Navegación
                    </span>
                    <button
                        type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-acode-primary hover:bg-slate-100 transition"
                        title="Alternar ancho de barra"
                    >
                        <x-icon name="chevronDown" class="w-4 h-4 transform" ::class="sidebarOpen ? '-rotate-90' : 'rotate-90'" />
                    </button>
                </div>

                {{-- Enlaces de navegación con iconos institucionales --}}
                <nav class="flex-1 px-2.5 py-4 space-y-1.5 overflow-y-auto">
                    <a
                        href="{{ route('dashboard') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-acode-primary text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }}"
                        :title="sidebarOpen ? '' : 'Panel Principal'"
                    >
                        <x-icon name="reportBar" class="w-5 h-5 shrink-0" />
                        <span x-show="sidebarOpen" class="truncate">Inicio / Métricas</span>
                    </a>

                    @if (auth()->user()->role === 'teacher')
                        <a
                            href="{{ route('groups.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('groups.*') ? 'bg-acode-primary text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }}"
                            :title="sidebarOpen ? '' : 'Grupos Escolares'"
                        >
                            <x-icon name="userGroup" class="w-5 h-5 shrink-0" />
                            <span x-show="sidebarOpen" class="truncate">Grupos Escolares</span>
                        </a>

                        <a
                            href="{{ route('questions.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('questions.*') ? 'bg-acode-primary text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }}"
                            :title="sidebarOpen ? '' : 'Banco de Reactivos'"
                        >
                            <x-icon name="edit" class="w-5 h-5 shrink-0" />
                            <span x-show="sidebarOpen" class="truncate">Banco de Reactivos</span>
                        </a>

                        <a
                            href="{{ route('exams.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('exams.*') ? 'bg-acode-primary text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }}"
                            :title="sidebarOpen ? '' : 'Catálogo de Exámenes'"
                        >
                            <x-icon name="clock" class="w-5 h-5 shrink-0" />
                            <span x-show="sidebarOpen" class="truncate">Exámenes</span>
                        </a>
                    @elseif (auth()->user()->role === 'student')
                        <a
                            href="{{ route('student.exams.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('student.exams.*') ? 'bg-acode-primary text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }}"
                            :title="sidebarOpen ? '' : 'Mis Exámenes'"
                        >
                            <x-icon name="clock" class="w-5 h-5 shrink-0" />
                            <span x-show="sidebarOpen" class="truncate">Mis Exámenes</span>
                        </a>

                        <a
                            href="{{ route('student.groups.join.form') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('student.groups.*') ? 'bg-acode-primary text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }}"
                            :title="sidebarOpen ? '' : 'Unirme a un Grupo'"
                        >
                            <x-icon name="add" class="w-5 h-5 shrink-0" />
                            <span x-show="sidebarOpen" class="truncate">Unirme a Grupo</span>
                        </a>
                    @endif
                </nav>

                {{-- Pie de barra lateral --}}
                <div class="p-3 border-t border-slate-100">
                    <button
                        type="button"
                        @click="confirmLogout()"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold text-acode-danger hover:bg-acode-danger/10 transition"
                        :title="sidebarOpen ? '' : 'Cerrar sesión'"
                    >
                        <x-icon name="lock" class="w-5 h-5 shrink-0 text-acode-danger" />
                        <span x-show="sidebarOpen" class="truncate">Cerrar sesión</span>
                    </button>
                </div>
            </aside>

            {{-- Cajón Drawer Responsivo para Móviles (<1024px) --}}
            <div
                x-show="mobileDrawerOpen"
                x-cloak
                class="fixed inset-0 z-50 lg:hidden flex"
                style="display: none;"
            >
                {{-- Backdrop blur --}}
                <div
                    x-show="mobileDrawerOpen"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="mobileDrawerOpen = false"
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
                ></div>

                <div
                    x-show="mobileDrawerOpen"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                    class="relative w-72 max-w-[85vw] bg-white h-full shadow-2xl flex flex-col z-10"
                >
                    <div class="p-4 bg-acode-navy text-white flex items-center justify-between">
                        <x-logo variant="negative" />
                        <button
                            type="button"
                            @click="mobileDrawerOpen = false"
                            class="p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10"
                        >
                            <x-icon name="xMark" class="w-5 h-5" />
                        </button>
                    </div>

                    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
                        <a
                            href="{{ route('dashboard') }}"
                            @click="mobileDrawerOpen = false"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-acode-primary text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                        >
                            <x-icon name="reportBar" class="w-5 h-5" />
                            <span>Inicio / Métricas</span>
                        </a>

                        @if (auth()->user()->role === 'teacher')
                            <a
                                href="{{ route('groups.index') }}"
                                @click="mobileDrawerOpen = false"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('groups.*') ? 'bg-acode-primary text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <x-icon name="userGroup" class="w-5 h-5" />
                                <span>Grupos Escolares</span>
                            </a>

                            <a
                                href="{{ route('questions.index') }}"
                                @click="mobileDrawerOpen = false"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('questions.*') ? 'bg-acode-primary text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <x-icon name="edit" class="w-5 h-5" />
                                <span>Banco de Reactivos</span>
                            </a>

                            <a
                                href="{{ route('exams.index') }}"
                                @click="mobileDrawerOpen = false"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('exams.*') ? 'bg-acode-primary text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <x-icon name="clock" class="w-5 h-5" />
                                <span>Exámenes</span>
                            </a>
                        @elseif (auth()->user()->role === 'student')
                            <a
                                href="{{ route('student.exams.index') }}"
                                @click="mobileDrawerOpen = false"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('student.exams.*') ? 'bg-acode-primary text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <x-icon name="clock" class="w-5 h-5" />
                                <span>Mis Exámenes</span>
                            </a>

                            <a
                                href="{{ route('student.groups.join.form') }}"
                                @click="mobileDrawerOpen = false"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('student.groups.*') ? 'bg-acode-primary text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <x-icon name="add" class="w-5 h-5" />
                                <span>Unirme a Grupo</span>
                            </a>
                        @endif
                    </nav>

                    <div class="p-4 border-t border-slate-100">
                        <button
                            type="button"
                            @click="mobileDrawerOpen = false; confirmLogout()"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold text-acode-danger hover:bg-acode-danger/10 transition"
                        >
                            <x-icon name="lock" class="w-5 h-5 text-acode-danger" />
                            <span>Cerrar sesión</span>
                        </button>
                    </div>
                </div>
            </div>
        @endauth

        {{-- Área de Contenido Principal --}}
        <main class="flex-1 overflow-y-auto px-4 lg:px-8 py-6">
            <div class="max-w-6xl mx-auto">
                {{-- Mensajes Flash de Sesión --}}
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm flex items-center gap-3 shadow-sm">
                        <x-icon name="success" class="w-5 h-5 text-acode-accent shrink-0" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm shadow-sm">
                        <div class="flex items-center gap-2 font-semibold mb-1">
                            <x-icon name="warning" class="w-4 h-4 shrink-0" />
                            <span>Revisa los siguientes campos del formulario:</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 pl-6 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    {{-- Footer Institucional de Dos Secciones (MPW-V2 §8.10.7) --}}
    <footer class="border-t border-slate-200 bg-white mt-auto select-none">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-5 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            {{-- Sección Izquierda: Créditos Institucionales --}}
            <div class="flex items-center gap-2 text-center md:text-left">
                <span class="font-semibold text-acode-primary">© 2026 A-Code Solutions.</span>
                <span>Plataforma Evaluadora de Exámenes. Todos los derechos reservados.</span>
                <x-badge variant="neutral" class="ml-1 text-[10px]">v1.0.0</x-badge>
            </div>

            {{-- Sección Derecha: Enlaces Normativos y Soporte --}}
            <div class="flex flex-wrap items-center justify-center md:justify-end gap-x-5 gap-y-2 text-slate-600">
                <a href="#" class="hover:text-acode-tech transition" onclick="event.preventDefault(); AcodeAlert.toast('Términos del Servicio vigentes conforme a MPW-V2 §8.6.1', 'info');">Términos del Servicio</a>
                <a href="#" class="hover:text-acode-tech transition" onclick="event.preventDefault(); AcodeAlert.toast('Aviso de Privacidad protegido según RF-AUT-003', 'info');">Aviso de Privacidad</a>
                <a href="#" class="hover:text-acode-tech transition" onclick="event.preventDefault(); AcodeAlert.toast('Centro de Ayuda institucional activo', 'info');">Centro de Ayuda</a>
                <a href="#" class="hover:text-acode-tech transition" onclick="event.preventDefault(); AcodeAlert.toast('Soporte Técnico: soporte@acode.solutions', 'info');">Soporte Técnico</a>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
