{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     app.blade.php
    Ubicación:   resources/views/layouts/app.blade.php
    Descripción: Layout maestro de la plataforma.
                 Navegación lateral retráctil (docente, administración y
                 estudiantes), topbar institucional y footer de dos secciones
                 (MPW-V2 §7.3, §7.4.2, §7.8.2, §8.10; MC-V2 §2.4).
    ============================================================================
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'A-Code Solutions') · Plataforma de Exámenes</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com/3.4.13"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['Fira Code', 'ui-monospace', 'SFMono-Regular', 'monospace'],
                    },
                    colors: {
                        acode: {
                            primary: '#142B4A',
                            'primary-dark': '#0E1F36',
                            navy: '#142B4A',
                            tech: '#2563A6',
                            'tech-dark': '#1D4F85',
                            accent: '#16A6A1',
                            'accent-dark': '#11807C',
                            warning: '#F4B942',
                            'warning-dark': '#D97706',
                            surface: '#E9F0F5',
                            danger: '#DC2626',
                            'danger-dark': '#B91C1C',
                            ink: '#0F172A',
                        },
                    },
                },
            },
        };
    </script>

    <script>
        if (localStorage.getItem('acode_sidebar_collapsed') === 'true') {
            document.documentElement.classList.add('acode-sidebar-collapsed');
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .acode-sidebar-divider,
        .acode-brand-collapsed,
        .acode-icon-expand {
            display: none;
        }

        @media (max-width: 1023px) {
            #acode-sidebar {
                transition: transform 200ms ease;
            }
        }

        @media (min-width: 1024px) {
            #acode-sidebar {
                width: 16rem;
                transform: translateX(0) !important;
            }
            #acode-main-shell {
                padding-left: 16rem;
            }
            html.acode-sidebar-collapsed #acode-sidebar {
                width: 5rem;
            }
            html.acode-sidebar-collapsed #acode-main-shell {
                padding-left: 5rem;
            }
            html.acode-sidebar-collapsed .acode-sidebar-label,
            html.acode-sidebar-collapsed .acode-sidebar-category,
            html.acode-sidebar-collapsed .acode-brand-expanded,
            html.acode-sidebar-collapsed .acode-icon-collapse {
                display: none !important;
            }
            html.acode-sidebar-collapsed .acode-sidebar-divider,
            html.acode-sidebar-collapsed .acode-brand-collapsed {
                display: block;
            }
            html.acode-sidebar-collapsed .acode-icon-expand {
                display: inline-flex;
            }
            html.acode-sidebar-collapsed .acode-sidebar-header {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            html.acode-sidebar-collapsed .acode-sidebar-link {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
            html.acode-sidebar-collapsed .acode-sidebar-footer {
                padding: 1rem 0.5rem;
            }
            html.acode-sidebar-animating #acode-sidebar {
                transition: width 200ms ease;
            }
            html.acode-sidebar-animating #acode-main-shell {
                transition: padding-left 200ms ease;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-acode-surface font-sans text-sm text-slate-700 antialiased {{ ! empty($showPlanOnboardingOverlay) ? 'overflow-hidden' : '' }}">

@auth
    <div x-data="{
            sidebarOpen: false,
            sidebarCollapsed: document.documentElement.classList.contains('acode-sidebar-collapsed'),
            profileOpen: false,
            toggleCollapse() {
                const root = document.documentElement;
                root.classList.add('acode-sidebar-animating');
                this.sidebarCollapsed = !this.sidebarCollapsed;
                root.classList.toggle('acode-sidebar-collapsed', this.sidebarCollapsed);
                localStorage.setItem('acode_sidebar_collapsed', String(this.sidebarCollapsed));
                setTimeout(() => root.classList.remove('acode-sidebar-animating'), 240);
            }
         }"
         class="min-h-screen">

            {{-- Fondo del menú móvil --}}
            <div x-show="sidebarOpen"
                 x-cloak
                 x-transition.opacity
                 @click="sidebarOpen = false"
                 class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden">
            </div>

            {{-- Menú lateral institucional retráctil (zero-flicker) --}}
            <aside id="acode-sidebar"
                   class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-acode-primary text-white -translate-x-full lg:translate-x-0"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

                {{-- Cabecera del menú lateral --}}
                <div class="acode-sidebar-header flex min-h-20 items-center justify-between gap-2 border-b border-white/10 px-4 py-4">
                    <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.teachers.index') : (auth()->user()->role === 'teacher' && ! auth()->user()->isVerifiedTeacher() ? route('teacher.verification.status') : route('dashboard')) }}"
                       class="acode-brand-expanded min-w-0">
                        <p class="text-sm font-bold leading-snug text-white">
                            Evaluador de Exámenes
                        </p>
                        <p class="mt-1 text-xs text-white/60">
                            @if (auth()->user()->role === 'super_admin')
                                Panel de Administración
                            @elseif (auth()->user()->role === 'student')
                                Panel del Estudiante
                            @else
                                Panel Docente
                            @endif
                        </p>
                    </a>

                    <button type="button"
                            @click="toggleCollapse()"
                            class="hidden lg:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/75 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-acode-accent"
                            :aria-label="sidebarCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'"
                            :title="sidebarCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'">
                        <span class="acode-icon-collapse inline-flex">
                            <x-icon name="sidebarCollapse" class="h-5 w-5" />
                        </span>
                        <span class="acode-icon-expand items-center justify-center">
                            <x-icon name="sidebarExpand" class="h-5 w-5" />
                        </span>
                    </button>

                    <button type="button"
                            @click="sidebarOpen = false"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white/75 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
                            aria-label="Cerrar menú">
                        <x-icon name="xMark" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Navegación principal --}}
                <nav aria-label="Menú principal" class="flex-1 space-y-1 overflow-y-auto px-3 py-5 text-sm">
                    @if (auth()->user()->role === 'super_admin')
                        <div class="acode-sidebar-category px-3 pt-1 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                            Administración
                        </div>

                        <a href="{{ route('admin.teachers.index') }}"
                           title="Gestión de Docentes"
                           @if (request()->routeIs('admin.teachers.*')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('admin.teachers.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-nav-icon name="groups" />
                            <span class="acode-sidebar-label truncate">Gestión de Docentes</span>
                        </a>

                        <a href="{{ route('admin.invitations.index') }}"
                           title="Códigos de Invitación"
                           @if (request()->routeIs('admin.invitations.*')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('admin.invitations.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-icon name="key" class="h-5 w-5" />
                            <span class="acode-sidebar-label truncate">Códigos de Invitación</span>
                        </a>
                    @elseif (auth()->user()->role === 'student')
                        {{-- Categoría: Principal --}}
                        <div class="acode-sidebar-category px-3 pt-1 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                            Principal
                        </div>

                        <a href="{{ route('dashboard') }}"
                           title="Inicio"
                           @if (request()->routeIs('dashboard')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('dashboard') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-nav-icon name="home" />
                            <span class="acode-sidebar-label truncate">Inicio</span>
                        </a>

                        {{-- Categoría: Mi Aprendizaje --}}
                        <div class="acode-sidebar-category px-3 pt-4 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                            Mi Aprendizaje
                        </div>
                        <div class="acode-sidebar-divider my-2 mx-2 border-t border-white/10" aria-hidden="true"></div>

                        <a href="{{ route('student.groups.index') }}"
                           title="Mis Grupos"
                           @if (request()->routeIs('student.groups.index', 'student.groups.leave')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('student.groups.index', 'student.groups.leave') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-nav-icon name="groups" />
                            <span class="acode-sidebar-label truncate">Mis Grupos</span>
                        </a>

                        <a href="{{ route('student.exams.index') }}"
                           title="Mis Exámenes"
                           @if (request()->routeIs('student.exams.*')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('student.exams.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-nav-icon name="exams" />
                            <span class="acode-sidebar-label truncate">Mis Exámenes</span>
                        </a>

                        <a href="{{ route('student.grades.index') }}"
                           title="Mis Calificaciones"
                           @if (request()->routeIs('student.grades.*', 'attempts.result', 'attempts.review')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('student.grades.*', 'attempts.result', 'attempts.review') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-nav-icon name="grades" />
                            <span class="acode-sidebar-label truncate">Mis Calificaciones</span>
                        </a>

                        {{-- Categoría: Inscripción --}}
                        <div class="acode-sidebar-category px-3 pt-4 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                            Inscripción
                        </div>
                        <div class="acode-sidebar-divider my-2 mx-2 border-t border-white/10" aria-hidden="true"></div>

                        <a href="{{ route('student.groups.join.form') }}"
                           title="Unirme a un grupo"
                           @if (request()->routeIs('student.groups.join.*')) aria-current="page" @endif
                           class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('student.groups.join.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                            <x-nav-icon name="add" />
                            <span class="acode-sidebar-label truncate">Unirme a un grupo</span>
                        </a>
                    @else
                        @if (auth()->user()->isVerifiedTeacher() && auth()->user()->needsPlanOnboarding())
                            <a href="{{ route('dashboard') }}"
                               title="Selección de plan"
                               @if (request()->routeIs('dashboard')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center justify-between gap-2 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('dashboard') ? 'bg-acode-tech font-semibold text-white shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex min-w-0 items-center gap-3">
                                    <x-nav-icon name="home" />
                                    <span class="acode-sidebar-label truncate">Selección de plan</span>
                                </span>
                                <span class="acode-sidebar-label shrink-0 rounded-full bg-acode-warning px-2 py-0.5 text-xs font-bold text-acode-primary">
                                    Pendiente
                                </span>
                            </a>

                            <a href="{{ route('teacher.subscription.index') }}"
                               title="Suscripción"
                               @if (request()->routeIs('teacher.subscription.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.subscription.*') ? 'bg-acode-tech font-semibold text-white shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="subscription" />
                                <span class="acode-sidebar-label truncate">Suscripción</span>
                            </a>
                        @elseif (auth()->user()->isVerifiedTeacher())
                            {{-- Categoría: Principal --}}
                            <div class="acode-sidebar-category px-3 pt-1 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                                Principal
                            </div>

                            <a href="{{ route('dashboard') }}"
                               title="Inicio"
                               @if (request()->routeIs('dashboard')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('dashboard') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="home" />
                                <span class="acode-sidebar-label truncate">Inicio</span>
                            </a>

                            {{-- Categoría: Gestión Académica --}}
                            <div class="acode-sidebar-category px-3 pt-4 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                                Gestión Académica
                            </div>
                            <div class="acode-sidebar-divider my-2 mx-2 border-t border-white/10" aria-hidden="true"></div>

                            <a href="{{ route('groups.index') }}"
                               title="Grupos"
                               @if (request()->routeIs('groups.index', 'groups.create', 'groups.show', 'groups.periods.index')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('groups.index', 'groups.create', 'groups.show', 'groups.periods.index') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="groups" />
                                <span class="acode-sidebar-label truncate">Grupos</span>
                            </a>

                            <a href="{{ route('questions.index') }}"
                               title="Banco de preguntas"
                               @if (request()->routeIs('questions.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('questions.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="questions" />
                                <span class="acode-sidebar-label truncate">Banco de preguntas</span>
                            </a>

                            <a href="{{ route('exams.index') }}"
                               title="Exámenes"
                               @if (request()->routeIs('exams.*') && ! request()->routeIs('exams.revision.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('exams.*') && ! request()->routeIs('exams.revision.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="exams" />
                                <span class="acode-sidebar-label truncate">Exámenes</span>
                            </a>

                            {{-- Categoría: Evaluación y Resultados --}}
                            <div class="acode-sidebar-category px-3 pt-4 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                                Evaluación y Resultados
                            </div>
                            <div class="acode-sidebar-divider my-2 mx-2 border-t border-white/10" aria-hidden="true"></div>

                            <a href="{{ route('teacher.reviews.index') }}"
                               title="Revisión"
                               @if (request()->routeIs('teacher.reviews.*', 'exams.revision.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.reviews.*', 'exams.revision.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="review" />
                                <span class="acode-sidebar-label truncate">Revisión</span>
                            </a>

                            <a href="{{ route('teacher.gradebook.hub') }}"
                               title="Calificaciones"
                               @if (request()->routeIs('teacher.gradebook.*', 'groups.periods.gradebook*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.gradebook.*', 'groups.periods.gradebook*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="grades" />
                                <span class="acode-sidebar-label truncate">Calificaciones</span>
                            </a>

                            {{-- Categoría: Cuenta y Plan --}}
                            <div class="acode-sidebar-category px-3 pt-4 pb-1.5 text-xs font-semibold uppercase tracking-wider text-white/45">
                                Cuenta y Plan
                            </div>
                            <div class="acode-sidebar-divider my-2 mx-2 border-t border-white/10" aria-hidden="true"></div>

                            <a href="{{ route('teacher.subscription.index') }}"
                               title="Suscripción"
                               @if (request()->routeIs('teacher.subscription.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.subscription.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="subscription" />
                                <span class="acode-sidebar-label truncate">Suscripción</span>
                            </a>

                            <a href="{{ route('teacher.ai-credits.index') }}"
                               title="Créditos de IA"
                               @if (request()->routeIs('teacher.ai-credits.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.ai-credits.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="ai-credits" />
                                <span class="acode-sidebar-label truncate">Créditos de IA</span>
                            </a>

                            <a href="{{ route('teacher.profile.index') }}"
                               title="Mi perfil"
                               @if (request()->routeIs('teacher.profile.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center gap-3 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.profile.*') ? 'bg-acode-tech text-white font-semibold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <x-nav-icon name="profile" />
                                <span class="acode-sidebar-label truncate">Mi perfil</span>
                            </a>
                        @else
                            <a href="{{ route('teacher.verification.status') }}"
                               title="Verificación de cuenta"
                               @if (request()->routeIs('teacher.verification.*')) aria-current="page" @endif
                               class="acode-sidebar-link flex items-center justify-between gap-2 rounded-lg px-3.5 py-2.5 transition-colors {{ request()->routeIs('teacher.verification.*') ? 'bg-acode-tech font-semibold text-white shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex min-w-0 items-center gap-3">
                                    <x-icon name="shield" class="h-5 w-5" />
                                    <span class="acode-sidebar-label truncate">Verificación de cuenta</span>
                                </span>
                                @if (auth()->user()->isRejectedTeacherVerification())
                                    <span class="acode-sidebar-label shrink-0 rounded-full bg-acode-danger px-2 py-0.5 text-xs font-bold text-white">
                                        Acción
                                    </span>
                                @else
                                    <span class="acode-sidebar-label shrink-0 rounded-full bg-acode-warning px-2 py-0.5 text-xs font-bold text-acode-primary">
                                        Pendiente
                                    </span>
                                @endif
                            </a>
                        @endif
                    @endif
                </nav>

                {{-- Footer de marca institucional --}}
                <div class="acode-sidebar-footer border-t border-white/10 px-4 py-5">
                    <div class="acode-brand-expanded">
                        <img src="{{ asset('images/branding/logo-fondo-claro.png') }}"
                             alt="A-Code Solutions"
                             class="mx-auto max-h-14 w-full object-contain">

                        <p class="mt-3 text-center text-xs leading-relaxed text-white/65">
                            Analizamos, diseñamos, transformamos.
                        </p>
                    </div>

                    <div class="acode-brand-collapsed">
                        <img src="{{ asset('images/branding/isotipo.png') }}"
                             alt="A-Code Solutions"
                             class="mx-auto h-10 w-10 object-contain">
                    </div>
                </div>
            </aside>

            {{-- Contenido principal --}}
            <div id="acode-main-shell" class="flex min-h-screen flex-col">

                {{-- Encabezado superior --}}
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">

                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button"
                                @click="sidebarOpen = true"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-acode-primary transition hover:bg-acode-surface lg:hidden"
                                aria-label="Abrir menú">
                            <x-icon name="bars3" class="h-5 w-5" />
                        </button>

                        <span class="truncate text-sm font-semibold text-acode-primary sm:text-base">
                            @yield('title', auth()->user()->role === 'student' ? 'Panel del Estudiante' : (auth()->user()->role === 'super_admin' ? 'Panel de Administración' : 'Panel Docente'))
                        </span>
                    </div>

                    {{-- Menú de perfil --}}
                    <div class="relative" @click.outside="profileOpen = false">

                        <button type="button"
                                @click="profileOpen = !profileOpen"
                                :aria-expanded="profileOpen.toString()"
                                class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-acode-tech">

                            <x-avatar :user="auth()->user()" />

                            <span class="hidden text-sm font-medium text-acode-primary sm:block">
                                {{ auth()->user()->full_name }}
                            </span>

                            <span class="inline-flex text-slate-400 transition-transform duration-200"
                                  :class="profileOpen ? 'rotate-180' : ''">
                                <x-icon name="chevronDown" class="h-4 w-4" />
                            </span>
                        </button>

                        <div x-show="profileOpen"
                             x-cloak
                             x-transition
                             class="absolute right-0 mt-2 w-60 rounded-xl border border-slate-200 bg-white py-2 shadow-lg">

                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-acode-primary">
                                    {{ auth()->user()->full_name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    @if (auth()->user()->role === 'student')
                                        Estudiante
                                    @elseif (auth()->user()->role === 'super_admin')
                                        Administrador
                                    @else
                                        Docente
                                    @endif
                                </p>
                            </div>

                            @if (auth()->user()->role === 'teacher' && auth()->user()->isVerifiedTeacher())
                                @if (! auth()->user()->needsPlanOnboarding())
                                    {{-- Acceso al perfil docente --}}
                                    <a href="{{ route('teacher.profile.index') }}"
                                       class="flex min-h-11 items-center gap-3 px-4 py-2.5 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                        <x-icon name="userCircle" class="h-5 w-5 text-acode-tech" />
                                        <span>Mi perfil</span>
                                    </a>
                                @endif
                            @elseif (auth()->user()->role === 'teacher')
                                <a href="{{ route('teacher.verification.status') }}"
                                   class="flex min-h-11 items-center gap-3 px-4 py-2.5 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    <x-icon name="shield" class="h-5 w-5 text-acode-tech" />
                                    <span>Verificación de cuenta</span>
                                </a>
                            @elseif (in_array(auth()->user()->role, ['student', 'super_admin'], true))
                                {{-- Perfil del alumno y del superadministrador (RF-AUT-013) --}}
                                <a href="{{ route('account.profile') }}"
                                   class="flex min-h-11 items-center gap-3 px-4 py-3 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    <x-icon name="user" class="h-5 w-5" />
                                    <span>Mi perfil</span>
                                </a>
                            @endif

                            @if (auth()->user()->role === 'student')
                                <a href="{{ route('student.groups.index') }}"
                                   class="flex min-h-11 items-center gap-3 px-4 py-2.5 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    <x-nav-icon name="groups" class="h-5 w-5 text-acode-tech" />
                                    <span>Mis grupos</span>
                                </a>
                                <a href="{{ route('student.exams.index') }}"
                                   class="flex min-h-11 items-center gap-3 px-4 py-2.5 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    <x-nav-icon name="exams" class="h-5 w-5 text-acode-tech" />
                                    <span>Mis exámenes</span>
                                </a>
                                <a href="{{ route('student.grades.index') }}"
                                   class="flex min-h-11 items-center gap-3 px-4 py-2.5 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                    <x-nav-icon name="grades" class="h-5 w-5 text-acode-tech" />
                                    <span>Mis calificaciones</span>
                                </a>
                            @endif

                            {{-- Acceso al aviso de privacidad --}}
                            <a href="{{ route('privacy.show.public') }}"
                               class="flex min-h-11 items-center gap-3 px-4 py-2.5 text-sm text-acode-primary transition hover:bg-acode-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
                                <x-icon name="shield" class="h-5 w-5 text-slate-500" />
                                <span>Aviso de privacidad</span>
                            </a>

                            <div class="my-1 border-t border-slate-100"></div>
                            {{-- Cierre de sesión con confirmación previa; si se cancela, la sesión continúa (RF-AUT-012) --}}
                            <form
                                method="POST"
                                action="{{ route('auth.logout') }}"
                                data-logout-form
                                x-on:submit.prevent="window.AcodeAlert.confirm({
                                    title: '¿Cerrar sesión?',
                                    text: 'Tendrás que volver a ingresar tu correo, contraseña y código de verificación.',
                                    confirmButtonText: 'Cerrar sesión',
                                    cancelButtonText: 'Cancelar',
                                    isDanger: false,
                                    onConfirm: () => $el.submit(),
                                })"
                            >
                                @csrf
                                <button type="submit"
                                        class="flex min-h-11 w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-medium text-acode-danger transition hover:bg-acode-danger/10">
                                    <x-icon name="logout" class="h-5 w-5" />
                                    <span>Cerrar sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                {{-- Contenido --}}
                <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">

                    @if (session('status') || session('success'))
                        <div x-data="{ show: true }"
                             x-show="show"
                             x-transition.opacity.duration.200ms
                             role="status"
                             class="mb-6 flex items-center justify-between rounded-xl border border-acode-accent/40 bg-acode-accent/10 px-4 py-3.5 text-sm text-acode-primary shadow-sm">
                            <div class="flex items-center gap-2.5">
                                <x-icon name="success" class="h-5 w-5 text-acode-accent" />
                                <span class="font-medium">{{ session('status') ?? session('success') }}</span>
                            </div>
                            <button type="button"
                                    @click="show = false"
                                    class="rounded-lg p-1 text-acode-primary/70 transition hover:bg-acode-accent/20 hover:text-acode-primary focus:outline-none"
                                    aria-label="Cerrar notificación">
                                <x-icon name="xMark" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif

                    @if (session('warning'))
                        <div x-data="{ show: true }"
                             x-show="show"
                             x-transition.opacity.duration.200ms
                             role="alert"
                             class="mb-6 flex items-center justify-between rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-4 py-3.5 text-sm text-acode-primary shadow-sm">
                            <div class="flex items-center gap-2.5">
                                <x-icon name="warning" class="h-5 w-5 text-acode-warning-dark" />
                                <span class="font-medium">{{ session('warning') }}</span>
                            </div>
                            <button type="button"
                                    @click="show = false"
                                    class="rounded-lg p-1 text-acode-primary/70 transition hover:bg-acode-warning/25 hover:text-acode-primary focus:outline-none"
                                    aria-label="Cerrar advertencia">
                                <x-icon name="xMark" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div x-data="{ show: true }"
                             x-show="show"
                             x-transition.opacity.duration.200ms
                             role="alert"
                             class="mb-6 flex items-center justify-between rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3.5 text-sm text-acode-danger shadow-sm">
                            <div class="flex items-center gap-2.5">
                                <x-icon name="error" class="h-5 w-5 text-acode-danger" />
                                <span class="font-medium">{{ session('error') }}</span>
                            </div>
                            <button type="button"
                                    @click="show = false"
                                    class="rounded-lg p-1 text-acode-danger/70 transition hover:bg-acode-danger/15 hover:text-acode-danger focus:outline-none"
                                    aria-label="Cerrar alerta">
                                <x-icon name="xMark" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif

                    @if ($errors->any() && empty($showPlanOnboardingOverlay) && !request()->routeIs('exams.*', 'questions.create', 'questions.edit', 'questions.batch.create'))
                        <div class="mb-6 rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3.5 text-sm text-acode-danger">
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

                {{-- Footer Institucional de Dos Secciones (MPW-V2 §8.10.7) --}}
                <footer class="mt-auto border-t border-slate-200 bg-white py-6">
                    <div class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:px-6 lg:flex-row lg:px-8 text-xs text-slate-500">
                        <div class="flex flex-col sm:flex-row items-center gap-1 sm:gap-2 text-center sm:text-left">
                            <span class="font-semibold text-acode-primary">© 2026 A-Code Solutions.</span>
                            <span>Plataforma Evaluadora de Exámenes. Todos los derechos reservados.</span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">v1.0.0</span>
                        </div>
                        <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-slate-500">
                            <a href="#" class="transition hover:text-acode-tech">Términos del Servicio</a>
                            <a href="{{ route('privacy.show.public') }}" class="transition hover:text-acode-tech">Aviso de Privacidad</a>
                            <a href="#" class="transition hover:text-acode-tech">Centro de Ayuda</a>
                            <a href="#" class="transition hover:text-acode-tech">Soporte Técnico</a>
                        </nav>
                    </div>
                </footer>
            </div>
        </div>
@else
    <main class="min-h-screen">
        @yield('content')
    </main>
@endauth

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/alerts.js') }}"></script>
@stack('scripts')
</body>
</html>
