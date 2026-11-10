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
    <header class="sticky top-0 z-50 border-b border-white/10 bg-acode-navy shadow-sm">
        <div class="mx-auto flex min-h-20 max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">

            {{-- Identidad institucional --}}
            <a href="{{ route('landing') }}"
               class="flex shrink-0 items-center focus:outline-none"
               aria-label="A-Code Solutions - Inicio">
                <img
                    src="{{ asset('images/branding/logo-fondo-claro.png') }}"
                    alt="A-Code Solutions"
                    class="h-12 w-auto max-w-[240px] object-contain"
                >
            </a>

            {{-- Navegación --}}
            <nav class="order-3 flex w-full flex-wrap items-center justify-center gap-x-6 gap-y-3 border-t border-white/10 pt-3 text-sm font-semibold text-white/90 lg:order-2 lg:w-auto lg:border-0 lg:pt-0"
                 aria-label="Navegación principal">
                <a href="#capacidades" class="whitespace-nowrap transition hover:text-acode-accent">
                    Capacidades
                </a>

                <a href="#como-funciona" class="whitespace-nowrap transition hover:text-acode-accent">
                    ¿Cómo funciona?
                </a>

                <a href="#planes" class="whitespace-nowrap transition hover:text-acode-accent">
                    Planes y Precios
                </a>

                <a href="#movil" class="whitespace-nowrap transition hover:text-acode-accent">
                    App Móvil
                </a>
            </nav>

            {{-- Acceso y registro --}}
            <div class="order-2 flex shrink-0 items-center gap-3 lg:order-3">
                <a href="{{ route('auth.login.show') }}"
                   class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                    Iniciar Sesión
                </a>

                <a href="{{ route('auth.register.teacher.show') }}"
                   class="whitespace-nowrap rounded-xl bg-acode-accent px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-110">
                    Prueba Gratis
                </a>
            </div>

        </div>
    </header>


    {{-- 2. Hero Section --}}
    <section class="relative isolate overflow-hidden border-b border-slate-200 bg-gradient-to-br from-white via-slate-50 to-[#EDF7FA] py-20 lg:py-28">
        {{-- Figuras decorativas institucionales --}}
        <div aria-hidden="true" class="pointer-events-none absolute -left-48 -top-64 h-[380px] w-[380px] rounded-full bg-acode-tech opacity-[0.08]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-48 top-16 h-[520px] w-[520px] rounded-full bg-acode-accent opacity-[0.10]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-72 left-1/3 h-[480px] w-[480px] rounded-full bg-acode-tech opacity-[0.06] blur-3xl"></div>
        <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                {{-- Columna Izquierda: Copywriting y CTA --}}
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
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
                            <span>1 Grupo y 1 Examen Gratis</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>Alumnos No Pagan</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-acode-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>Cancela en Cualquier Momento</span>
                        </div>
                    </div>
                </div>

                {{-- Columna Derecha: Mockup Interactivo del Runner de Examen --}}
                <div class="lg:col-span-5">
                    <div class="relative space-y-5 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl shadow-acode-navy/10 transition-shadow duration-300 hover:shadow-acode-navy/15">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center space-x-2">
                                <span class="w-3 h-3 rounded-full bg-acode-danger inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-acode-warning inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-acode-accent inline-block"></span>
                            </div>>
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
                                Enviar Respuesta
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- 3. Sección de Capacidades del Ecosistema --}}
    <section id="capacidades" class="border-b border-slate-200 bg-gradient-to-b from-white to-slate-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-widest text-acode-tech">Evaluación Inteligente y Confiable</h2>
                <p class="text-3xl sm:text-4xl font-extrabold text-acode-navy">Todo lo que necesitas para evaluar con certeza y tranquilidad.</p>
                <p class="text-slate-600 text-base">Herramientas diseñadas para que docentes y estudiantes vivan una experiencia de evaluación justa, rápida y sin complicaciones.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                
                {{-- Tarjeta 1 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-acode-tech/30 hover:shadow-xl hover:shadow-acode-navy/10">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-acode-navy text-white flex items-center justify-center font-bold shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <h3 class="text-lg font-extrabold leading-snug tracking-tight text-acode-navy">Preguntas Variadas y Flexibles</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Combina opción múltiple, relación de columnas, preguntas abiertas y ejercicios de código. Tu banco personal de reactivos se guarda de forma segura para reutilizarlo en cualquier ciclo escolar.
                        </p>
                    </div>
                </div>

                {{-- Tarjeta 2 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-acode-tech/30 hover:shadow-xl hover:shadow-acode-navy/10">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-acode-tech text-white flex items-center justify-center font-bold shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="text-lg font-extrabold leading-snug tracking-tight text-acode-navy">Cronómetro Justo y Blindado</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            El tiempo de cada examen se calcula de manera centralizada. Ningún alumno puede alterar su reloj local ni pausar el cronómetro, garantizando igualdad de condiciones para todos.
                        </p>
                    </div>
                </div>

                {{-- Tarjeta 3 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-acode-tech/30 hover:shadow-xl hover:shadow-acode-navy/10">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-acode-accent text-white flex items-center justify-center font-bold shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <h3 class="text-lg font-extrabold leading-snug tracking-tight text-acode-navy">Supervisión y Honestidad</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Detección inteligente de cambio de pestaña o ventana, bloqueo de copiar/pegar y verificación visual por cámara para certificar que el examen lo responde quien corresponde.
                        </p>
                    </div>
                </div>

                {{-- Tarjeta 4 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-acode-tech/30 hover:shadow-xl hover:shadow-acode-navy/10">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-12 h-12 shrink-0 rounded-2xl bg-acode-warning text-slate-900 flex items-center justify-center font-bold shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <h3 class="text-lg font-extrabold leading-snug tracking-tight text-acode-navy">Calificaciones al Instante</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Ahorra horas de revisión manual: las preguntas objetivas se califican automáticamente y el sistema genera el control de notas y promedios listos para entregar en tus actas.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- 3.5. Sección ¿Cómo funciona? --}}
    <section id="como-funciona" class="border-b border-slate-200 bg-white py-20 lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-acode-surface text-acode-tech border border-acode-tech/20">
                    Paso a Paso
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-acode-navy tracking-tight">
                    ¿Cómo funciona la plataforma?
                </h2>
                <p class="text-slate-600 text-base leading-relaxed">
                    Evaluar a tus estudiantes y calificar con precisión es más sencillo que nunca. Todo el proceso en 4 sencillos pasos:
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                {{-- Paso 1 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-slate-50/60 p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-acode-tech/30">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-acode-navy text-white text-lg font-extrabold shadow-sm">
                                01
                            </span>
                            <h3 class="text-lg font-bold text-acode-navy leading-snug">Crea tu grupo escolar</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Genera tu aula en segundos. Proyecta el código QR o comparte un código de 8 dígitos para que tus alumnos se inscriban al instante desde su app móvil o web.
                        </p>
                    </div>
                </div>

                {{-- Paso 2 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-slate-50/60 p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-acode-tech/30">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-acode-tech text-white text-lg font-extrabold shadow-sm">
                                02
                            </span>
                            <h3 class="text-lg font-bold text-acode-navy leading-snug">Arma tu evaluación</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Selecciona preguntas de tu banco propio o deja que el asistente sortee reactivos automáticamente. Configura tiempos por pregunta, escala de notas y fecha de aplicación.
                        </p>
                    </div>
                </div>

                {{-- Paso 3 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-slate-50/60 p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-acode-accent/40">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-acode-accent text-white text-lg font-extrabold shadow-sm">
                                03
                            </span>
                            <h3 class="text-lg font-bold text-acode-navy leading-snug">Aplica con tranquilidad</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Tus alumnos responden con cronómetro sincronizado desde el servidor, cámara activa y candados que bloquean trampas e intentos de copiar respuestas.
                        </p>
                    </div>
                </div>

                {{-- Paso 4 --}}
                <div class="group flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-slate-50/60 p-7 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-amber-300">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-acode-warning text-slate-900 text-lg font-extrabold shadow-sm">
                                04
                            </span>
                            <h3 class="text-lg font-bold text-acode-navy leading-snug">Obtén calificaciones</h3>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Calificación automática al momento. Los estudiantes reciben sus notas y retroalimentación en su celular, y tú descargas el control completo de calificaciones.
                        </p>
                    </div>
                </div>
            </div>

            {{-- CTA interno --}}
            <div class="mt-12 text-center">
                <a href="{{ route('auth.register.teacher.show') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-acode-navy px-7 py-3.5 text-sm font-bold text-white shadow-md transition hover:bg-acode-primary-dark hover:shadow-lg">
                    <span>Comenzar a evaluar gratis</span>
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </section>

    {{-- 4. Sección de Planes y Precios SaaS --}}
    <section id="planes" class="relative isolate overflow-hidden border-b border-slate-200 bg-gradient-to-br from-[#F5F9FC] via-white to-[#EAF5FA] py-20">

    {{-- Figuras decorativas institucionales --}}
        <div aria-hidden="true" class="pointer-events-none absolute -left-48 -top-48 h-[420px] w-[420px] rounded-full bg-acode-tech opacity-[0.12]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-48 top-24 h-[480px] w-[480px] rounded-full bg-acode-accent opacity-[0.10]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-64 left-1/3 h-[420px] w-[420px] rounded-full bg-acode-tech opacity-[0.08]"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-acode-navy text-white">
                    Transparencia y Sin Plazos Forzosos
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-acode-navy">Planes diseñados para cada nivel docente</h2>
                <p class="text-slate-600 text-base">El maestro contrata el paquete según sus grupos y alumnos. Ni la escuela ni los alumnos pagan nada.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 items-stretch md:grid-cols-2 xl:grid-cols-3">
                @forelse ($packages as $pkg)
                    @php
                        $isFeatured = $pkg->tier === 'advanced';
                    @endphp
                    <div class="relative flex h-full flex-col justify-between rounded-3xl border bg-white p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl {{ $isFeatured ? 'border-acode-tech shadow-xl ring-2 ring-acode-tech/20' : 'border-slate-200 shadow-lg shadow-acode-navy/5' }}">
                        @if ($isFeatured)
                            <div class="absolute -top-4 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-acode-accent px-5 py-2 text-xs font-extrabold uppercase tracking-wider text-white shadow-sm">
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

                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <span class="whitespace-nowrap text-3xl font-extrabold tracking-tight text-acode-navy sm:text-4xl">
                                    ${{ number_format((float) $pkg->monthly_price, 2) }}
                                </span>
                                <span class="whitespace-nowrap text-sm font-semibold text-slate-500">
                                    MXN / mes
                                </span>
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
                            <a href="{{ route('auth.register.teacher.show') }}"
                            class="inline-flex min-h-12 w-full items-center justify-center rounded-xl px-4 py-3 text-center text-sm font-bold transition-all duration-200 hover:-translate-y-0.5 {{ $isFeatured ? 'bg-acode-navy text-white shadow-md hover:bg-acode-primary-dark' : 'bg-slate-100 text-acode-navy hover:bg-slate-200' }}">
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
                        Prueba Gratuita Freemium 
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
    <section id="movil" class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-acode-navy via-acode-navy to-acode-primary-dark p-8 text-white shadow-2xl shadow-acode-navy/15 lg:p-14">

                {{-- Figuras decorativas institucionales --}}
                <div aria-hidden="true"
                    class="pointer-events-none absolute -right-32 -top-40 h-[420px] w-[420px] rounded-full border-[65px] border-white/5">
                </div>

                <div aria-hidden="true"
                    class="pointer-events-none absolute -bottom-52 -left-32 h-[400px] w-[400px] rounded-full bg-acode-accent/10">
                </div>
                <div class="relative z-10 grid grid-cols-1 items-center gap-10 lg:grid-cols-12">
                    
                    <div class="lg:col-span-7 space-y-6">
                        <h2 class="max-w-2xl text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
                            Tus alumnos presentan exámenes directo desde su celular.
                        </h2>

                        <p class="text-slate-300 text-base leading-relaxed">
                            Una aplicación ágil y cómoda pensada para que tus estudiantes nunca se queden sin presentar una evaluación. Se incorporan a tu clase en segundos escaneando el código QR proyectado en el aula o ingresando un código único.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-slate-200 pt-2">
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-acode-accent shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span><strong>Inscripción en segundos:</strong> Acceso instantáneo con la cámara del celular o mediante folio de 8 caracteres.</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-acode-accent shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span><strong>Exámenes en vivo:</strong> Respuestas fluidas con cronómetro visible y guardado automático.</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-acode-accent shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span><strong>Avisos al instante:</strong> Notificaciones directas en el teléfono cuando hay un examen activo o calificado.</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-acode-accent shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span><strong>100% gratuita para el alumno:</strong> Sin compras ocultas ni suscripciones para los estudiantes.</span>
                            </div>
                        </div>

                        <div class="pt-4 flex flex-wrap items-center gap-4">
                            <a href="{{ route('auth.register.student.show') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-acode-tech px-6 py-3 text-center text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-acode-tech-dark">
                                Registrar Alumno en Web
                            </a>
                        </div>
                    </div>

                    <div class="flex justify-center lg:col-span-5">
                         <div class="relative w-64 space-y-4 rounded-[2.5rem] border-[5px] border-slate-700 bg-slate-900 p-4 text-slate-800 shadow-2xl shadow-black/30 transition-transform duration-300 hover:-translate-y-1">
                            <div class="h-4 w-28 bg-slate-700 rounded-full mx-auto"></div>
                            <div class="bg-white rounded-2xl p-4 space-y-3">
                                <div class="text-xs font-bold text-acode-navy flex justify-between items-center border-b border-slate-100 pb-2">
                                    <span>Mis Grupos</span>
                                    <span class="inline-flex items-center gap-1 text-[10px] text-emerald-600 font-semibold">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Conectado
                                    </span>
                                </div>
                                <div class="p-3 bg-acode-surface rounded-xl border border-slate-200 text-xs space-y-1">
                                    <div class="font-bold text-acode-navy">Auditoría de Sistemas</div>
                                    <div class="text-slate-500">Prof. Mauro Sánchez</div>
                                    <div class="text-emerald-700 font-semibold pt-1">Calificación: 9.5 / 10</div>
                                </div>
                                <div class="p-3 bg-acode-surface rounded-xl border border-slate-200 text-xs space-y-1">
                                    <div class="font-bold text-acode-navy">Programación Web</div>
                                    <div class="text-slate-500">A-Code Solutions</div>
                                    <div class="text-acode-tech font-semibold pt-1 flex items-center justify-between">
                                        <span>Examen activo hoy</span>
                                        <span class="bg-acode-tech text-white text-[10px] px-2 py-0.5 rounded">Presentar →</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    {{-- 6. Footer Normativo A-Code Solutions (MC-V2, LFPDPPP) --}}
    {{-- Pie de página institucional --}}
    <footer class="relative overflow-hidden border-t border-white/10 bg-acode-navy text-white">
        <div aria-hidden="true"
            class="pointer-events-none absolute -bottom-32 -left-32 h-72 w-72 rounded-full bg-acode-tech/10">
        </div>

        <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 gap-10 border-b border-white/10 pb-10 md:grid-cols-3">

                {{-- Identidad del producto --}}
                <div class="space-y-4">
                    <span class="text-xs font-bold uppercase tracking-[0.18em] text-acode-accent">
                        A-Code Solutions
                    </span>

                    <h2 class="text-2xl font-extrabold tracking-tight text-white">
                        Evaluador de Exámenes
                    </h2>

                    <p class="max-w-sm text-sm leading-relaxed text-slate-300">
                        Una plataforma para crear, aplicar y gestionar evaluaciones académicas.
                    </p>
                </div>

                {{-- Navegación --}}
                <div class="space-y-4 md:justify-self-center">
                    <h3 class="text-sm font-bold text-white">Explora</h3>

                    <nav aria-label="Navegación del pie de página"
                        class="flex flex-col items-start gap-3 text-sm text-slate-300">
                        <a href="#capacidades" class="transition hover:text-acode-accent">
                            Capacidades
                        </a>
                        <a href="#planes" class="transition hover:text-acode-accent">
                            Planes y precios
                        </a>
                        <a href="#movil" class="transition hover:text-acode-accent">
                            Aplicación móvil
                        </a>
                    </nav>
                </div>

                {{-- Información y acceso --}}
                <div class="space-y-4 md:justify-self-end">
                    <h3 class="text-sm font-bold text-white">Información</h3>

                    <div class="flex flex-col items-start gap-3 text-sm text-slate-300">
                        <button type="button"
                                @click="showPrivacyModal = true"
                                class="text-left transition hover:text-acode-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-acode-accent">
                            Aviso de Privacidad
                        </button>

                        <a href="{{ route('auth.login.show') }}"
                        class="transition hover:text-acode-accent">
                            Iniciar sesión
                        </a>

                        <a href="{{ route('auth.register.teacher.show') }}"
                        class="transition hover:text-acode-accent">
                            Registro docente
                        </a>
                    </div>
                </div>

            </div>

            {{-- Cierre institucional --}}
            <div class="flex flex-col gap-3 pt-7 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    © 2026 A-Code Solutions. Todos los derechos reservados.
                </p>

                <p class="italic">
                    Analizamos, diseñamos, transformamos.
                </p>
            </div>

        </div>
    </footer>

    {{-- Modal de Aviso de Privacidad Normativo (LFPDPPP) --}}
    <div x-show="showPrivacyModal"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         @keydown.escape.window="showPrivacyModal = false">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6 sm:p-8 space-y-6 shadow-2xl border border-slate-200 transform transition-all duration-200"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
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
