{{-- ============================================================================
A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
============================================================================
Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
Archivo:        landing.blade.php
Ubicación:      resources/views/landing.blade.php
Requerimiento:  RF-SUS-001 (Consulta de paquetes), RF-SUS-008 (Prueba gratuita), RW-01, MC-V2
Autor:          Equipo 4 (A-Code Solutions)
Versión:        1.0.0
Fecha Creación: 08/11/2026
Descripción:    Landing page pública institucional del SaaS. Presenta el ecosistema,
                capacidades anti-trampa, tabla de precios y descarga de la app móvil.
============================================================================ --}}
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>A-Code Solutions · Evaluador de Exámenes SaaS Blindado</title>

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
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</head>
<body x-data="{ showPrivacyModal: false }" class="bg-slate-50 font-sans text-slate-800 antialiased selection:bg-acode-tech selection:text-white">

    {{-- 1. Barra de Navegación Superior --}}
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="focus:outline-none">
                <x-logo variant="full" />
            </a>

            <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-slate-600">
                <a href="#capacidades" class="hover:text-acode-primary transition">Capacidades</a>
                <a href="#como-funciona" class="hover:text-acode-primary transition">¿Cómo funciona?</a>
                <a href="#planes" class="hover:text-acode-primary transition">Planes y Precios</a>
                <a href="#movil" class="hover:text-acode-primary transition">App Móvil</a>
            </nav>

            <div class="flex items-center space-x-3">
                <a href="{{ route('auth.login.show') }}" class="text-sm font-semibold text-acode-navy hover:text-acode-tech px-4 py-2 rounded-lg transition">
                    Iniciar Sesión
                </a>
                <a href="{{ route('auth.register.teacher.show') }}" class="text-sm font-bold bg-acode-navy hover:bg-acode-primary-dark text-white px-5 py-2.5 rounded-lg shadow-sm hover:shadow transition">
                    Prueba Gratis
                </a>
            </div>
        </div>
    </header>

    {{-- 2. Hero Section --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-slate-100/80 via-white to-slate-50 py-20 lg:py-28 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                {{-- Columna Izquierda: Copywriting y CTA --}}
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-acode-surface border border-acode-tech/30 text-acode-tech text-xs font-bold tracking-wide uppercase">
                        <span class="w-2 h-2 rounded-full bg-acode-accent animate-pulse"></span>
                        SaaS Evaluador con Tolerancia Server-Side (MPW-V2)
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-acode-navy tracking-tight leading-[1.15]">
                        Exámenes Contrarreloj <span class="text-acode-tech">Blindados</span> contra Trampas de IA.
                    </h1>

                    <p class="text-lg text-slate-600 max-w-2xl leading-relaxed">
                        Diseñado para docentes y universidades que necesitan certificar conocimientos reales. Evalúa opción múltiple, columnas, reactivos abiertos y código fuente con cronómetro estricto en el servidor y supervisión por cámara.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start pt-2">
                        <a href="{{ route('auth.register.teacher.show') }}" class="inline-flex items-center justify-center font-bold text-base bg-acode-navy hover:bg-acode-primary-dark text-white px-7 py-3.5 rounded-xl shadow-md hover:shadow-lg transition">
                            <span>Comenzar Prueba Gratuita</span>
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        <a href="#planes" class="inline-flex items-center justify-center font-bold text-base bg-white hover:bg-slate-50 text-acode-navy border border-slate-300 px-6 py-3.5 rounded-xl transition">
                            Ver Planes desde $299 MXN
                        </a>
                    </div>

                    {{-- Garantías del modelo SaaS --}}
                    <div class="pt-4 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs font-semibold text-slate-500">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>1 Grupo y 1 Examen Gratis (RF-SUS-008)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>Alumnos No Pagan (RW-01)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>Cancela en Cualquier Momento</span>
                        </div>
                    </div>
                </div>

                {{-- Columna Derecha: Mockup Interactivo del Runner de Examen --}}
                <div class="lg:col-span-5">
                    <div class="relative bg-white rounded-2xl shadow-xl border border-slate-200 p-6 space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center space-x-2">
                                <span class="w-3 h-3 rounded-full bg-acode-danger inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-acode-warning inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-acode-accent inline-block"></span>
                                <span class="text-xs font-mono font-bold text-slate-400 ml-2">EXAM-RUNNER v1.0</span>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Cámara Activa
                            </span>
                        </div>

                        {{-- Temporizador reactivo --}}
                        <div class="flex items-center justify-between bg-acode-surface px-4 py-3 rounded-xl border border-slate-200">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-acode-primary animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Tiempo de reactivo</span>
                            </div>
                            <span class="font-mono font-extrabold text-lg text-acode-danger">00:28 s</span>
                        </div>

                        {{-- Pregunta simulada de código --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-acode-tech uppercase">Reactivo 3 de 15 · Código PHP</span>
                                <span class="text-xs font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded">10 Puntos</span>
                            </div>
                            <p class="text-sm font-semibold text-slate-800">
                                Encuentra el error de sintaxis en el siguiente bloque e ingresa la corrección:
                            </p>
                            <div class="bg-slate-900 rounded-lg p-3 text-xs font-mono text-emerald-400 overflow-x-auto">
                                <code>function calcularTotal($subtotal, $iva) {<br>&nbsp;&nbsp;return $subtotal * (1 + $iva)<br>}</code>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="button" class="w-full bg-acode-navy hover:bg-acode-primary-dark text-white text-sm font-bold py-2.5 rounded-lg shadow-sm">
                                Enviar Respuesta Contrarreloj
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- 3. Sección de Capacidades del Ecosistema --}}
    <section id="capacidades" class="py-20 bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-widest text-acode-tech">Ingeniería A-Code Solutions</h2>
                <p class="text-3xl sm:text-4xl font-extrabold text-acode-navy">Todo lo que necesitas para evaluar con certeza técnica.</p>
                <p class="text-slate-600 text-base">Desarrollado bajo estándares estrictos de programación limpia, arquitectura desacoplada y seguridad contra fraudes.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                
                {{-- Tarjeta 1 --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-3 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-acode-navy text-white flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-acode-navy">Reactivos Polimórficos</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Crea reactivos de Opción Múltiple, Relación de Columnas, Abiertas y Programación con Copy-On-Write para proteger exámenes históricos.
                    </p>
                </div>

                {{-- Tarjeta 2 --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-3 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-acode-tech text-white flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-acode-navy">Reloj en el Servidor</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        El tiempo nunca corre del lado del cliente. PostgreSQL y el backend validan cada segundo con 3 segundos de tolerancia máxima.
                    </p>
                </div>

                {{-- Tarjeta 3 --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-3 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-acode-accent text-white flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-acode-navy">Supervisión Anti-Trampa</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Supervisión por cámara web, bloqueo de portapapeles y detección de cambio de pestaña para asegurar que el alumno mantenga el foco.
                    </p>
                </div>

                {{-- Tarjeta 4 --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-3 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-acode-warning text-slate-900 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-acode-navy">Analítica con Chart.js</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Métricas de desempeño en tiempo real, tasas de aprobación por grupo y ponderación de calificaciones por periodos de evaluación.
                    </p>
                </div>

            </div>
        </div>
    </section>

    {{-- 4. Sección de Planes y Precios SaaS (RF-SUS-001, MPW-V2 Tabla 15) --}}
    <section id="planes" class="py-20 bg-slate-100 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-acode-navy text-white">
                    Transparencia y Sin Plazos Forzosos
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-acode-navy">Planes diseñados para cada nivel docente</h2>
                <p class="text-slate-600 text-base">El maestro contrata el paquete según sus grupos y alumnos. Ni la escuela ni los alumnos pagan nada (RW-01).</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
                @forelse ($packages as $pkg)
                    @php
                        $isFeatured = $pkg->tier === 'advanced';
                    @endphp
                    <div class="relative bg-white rounded-3xl p-8 flex flex-col justify-between border {{ $isFeatured ? 'border-acode-tech shadow-xl ring-2 ring-acode-tech/20' : 'border-slate-200 shadow-sm' }}">
                        @if ($isFeatured)
                            <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-acode-accent text-white text-xs font-extrabold uppercase tracking-wider py-1 px-4 rounded-full shadow-sm">
                                Más Popular · Colegiado
                            </div>
                        @endif

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-2xl font-bold text-acode-navy">{{ $pkg->name }}</h3>
                                <p class="text-xs text-slate-500 mt-1 uppercase font-semibold tracking-wider">
                                    {{ $pkg->tier === 'basic' ? 'Docente Individual' : ($pkg->tier === 'advanced' ? 'Colegiado Departamental' : 'Institucional / Pro') }}
                                </p>
                            </div>

                            <div class="flex items-baseline space-x-1">
                                <span class="text-4xl font-extrabold text-acode-navy">${{ number_format((float)$pkg->monthly_price, 2) }}</span>
                                <span class="text-slate-500 text-sm font-semibold">MXN / mes</span>
                            </div>

                            <div class="border-t border-slate-100 pt-6 space-y-3.5 text-sm text-slate-700">
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 text-acode-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span><strong>{{ $pkg->max_groups ?? 'Ilimitados' }}</strong> grupos activos</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 text-acode-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span><strong>{{ $pkg->max_questions ? number_format($pkg->max_questions) : 'Ilimitados' }}</strong> reactivos en banco</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 text-acode-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span>Hasta <strong>{{ $pkg->max_concurrent_students }}</strong> alumnos en vivo</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 text-acode-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span><strong>{{ $pkg->max_active_exams ?? 'Ilimitados' }}</strong> exámenes simultáneos</span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <svg class="w-5 h-5 text-acode-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span>Soporte para 4 tipos de reactivos</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-8">
                            <a href="{{ route('auth.register.teacher.show') }}" class="w-full inline-flex justify-center items-center font-bold text-sm py-3 px-4 rounded-xl transition {{ $isFeatured ? 'bg-acode-navy hover:bg-acode-primary-dark text-white shadow-md' : 'bg-slate-100 hover:bg-slate-200 text-acode-navy' }}">
                                Comenzar con {{ $pkg->name }}
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-12 text-slate-500">
                        Los paquetes de suscripción se están sincronizando con el servidor.
                    </div>
                @endforelse
            </div>

            {{-- Banner de Prueba Gratuita Freemium --}}
            <div class="mt-12 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="space-y-1 text-center md:text-left">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                        Prueba Gratuita Freemium (RF-SUS-008)
                    </span>
                    <p class="text-sm font-semibold text-slate-800">
                        ¿Quieres probar antes de contratar? Crea 1 grupo y aplica 1 examen completo sin tarjeta de crédito.
                    </p>
                </div>
                <a href="{{ route('auth.register.teacher.show') }}" class="shrink-0 text-xs font-bold bg-acode-navy hover:bg-acode-primary-dark text-white px-5 py-2.5 rounded-lg transition">
                    Activar Prueba Gratis
                </a>
            </div>
        </div>
    </section>

    {{-- 5. Sección de la App Móvil para Estudiantes --}}
    <section id="movil" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-acode-navy to-acode-primary-dark rounded-3xl p-8 lg:p-14 text-white overflow-hidden shadow-xl">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                    
                    <div class="lg:col-span-7 space-y-6">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/10 text-acode-accent border border-white/10">
                            App Gratuita para Estudiantes (RM-05, RF-AUT-002)
                        </span>

                        <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                            Tus alumnos se incorporan en segundos con Código QR.
                        </h2>

                        <p class="text-slate-300 text-base leading-relaxed">
                            Diseñada en Flutter con arquitectura Clean Architecture. Los estudiantes se unen escaneando el código QR proyectado en el aula o ingresando un folio único de 8 caracteres.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-slate-200 pt-2">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span>Multi-Grupo y Multi-Docente (RM-08)</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span>Consulta de Calificaciones (RM-10)</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span>100% Gratuita para el Alumno</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span>Notificaciones Push FCM (RM-12)</span>
                            </div>
                        </div>

                        <div class="pt-4">
                            <a href="{{ route('auth.register.student.show') }}" class="inline-flex items-center font-bold text-sm bg-acode-tech hover:bg-acode-tech-dark text-white px-6 py-3 rounded-xl transition">
                                Registrar Alumno en Web
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-5 flex justify-center">
                        <div class="w-64 bg-slate-900 border-4 border-slate-700 rounded-[2.5rem] p-4 shadow-2xl text-slate-800 space-y-4">
                            <div class="h-4 w-28 bg-slate-700 rounded-full mx-auto"></div>
                            <div class="bg-white rounded-2xl p-4 space-y-3">
                                <div class="text-xs font-bold text-acode-navy flex justify-between items-center">
                                    <span>Mis Grupos</span>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                </div>
                                <div class="p-3 bg-acode-surface rounded-xl border border-slate-200 text-xs space-y-1">
                                    <div class="font-bold text-acode-navy">Auditoría de Sistemas</div>
                                    <div class="text-slate-500">Prof. Mauro Sánchez</div>
                                    <div class="text-emerald-700 font-semibold pt-1">Calificación: 9.5 / 10</div>
                                </div>
                                <div class="p-3 bg-acode-surface rounded-xl border border-slate-200 text-xs space-y-1">
                                    <div class="font-bold text-acode-navy">Programación Web</div>
                                    <div class="text-slate-500">A-Code Solutions</div>
                                    <div class="text-slate-600 font-semibold pt-1">Examen activo hoy</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    {{-- 6. Footer Normativo A-Code Solutions (MC-V2, LFPDPPP) --}}
    <footer class="bg-acode-navy text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <x-logo variant="negative" />

                <div class="flex flex-wrap items-center justify-center gap-6 text-sm">
                    <a href="#capacidades" class="hover:text-white transition">Capacidades</a>
                    <a href="#planes" class="hover:text-white transition">Precios</a>
                    <a href="#movil" class="hover:text-white transition">Estudiantes</a>
                    <button type="button" @click="showPrivacyModal = true" class="text-acode-accent hover:underline font-semibold focus:outline-none">
                        Aviso de Privacidad (LFPDPPP)
                    </button>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <p>© 2026 A-Code Solutions. Todos los derechos reservados. — <em>Analizamos, diseñamos, transformamos.</em></p>
                <p>Ecosistema Evaluador SaaS v1.0.0 · Homologado con MPW-V2 y MPM-V4</p>
            </div>
        </div>
    </footer>

    {{-- Modal de Aviso de Privacidad Normativo (LFPDPPP) --}}
    <div x-show="showPrivacyModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         @keydown.escape.window="showPrivacyModal = false">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6 sm:p-8 space-y-6 shadow-2xl border border-slate-200"
             @click.away="showPrivacyModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-xl font-bold text-acode-navy">Aviso de Privacidad Integral</h3>
                <button type="button" @click="showPrivacyModal = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="max-h-96 overflow-y-auto text-sm text-slate-600 space-y-4 pr-2">
                @if ($privacyNotice)
                    <p class="font-bold text-acode-primary">Versión Vigente: {{ $privacyNotice->version }} (Aprobado)</p>
                    <p class="whitespace-pre-line leading-relaxed">{{ $privacyNotice->content }}</p>
                @else
                    <p class="text-slate-500 italic">El aviso de privacidad se encuentra en proceso de publicación oficial conforme a LFPDPPP.</p>
                @endif
            </div>
            <div class="pt-2 flex justify-end">
                <button type="button" @click="showPrivacyModal = false" class="px-5 py-2.5 rounded-xl bg-acode-navy text-white text-sm font-bold hover:bg-acode-primary-dark transition">
                    Entendido
                </button>
            </div>
        </div>
    </div>

</body>
</html>
