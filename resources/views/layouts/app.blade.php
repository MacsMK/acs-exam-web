<!DOCTYPE html>
{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     app.blade.php
    Ubicación:   resources/views/layouts/app.blade.php
    Descripción: Layout maestro para docente/admin (navbar institucional +
                 contenido). Tokens de color y tipografía según el Manual
                 de Programación Laravel (MP-V2 §7.4, §8.1, §8.2): paleta
                 acode-* vía configuración runtime de Tailwind CDN (sin
                 build de Vite, para mantener la demo autocontenida) y
                 fuente Inter. Nada de estilos inline (MP-V2 §7.4.2):
                 solo clases utilitarias de los tokens oficiales.
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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</head>
<body class="bg-acode-surface font-sans text-slate-700 min-h-screen">
    <nav class="bg-acode-primary text-white">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-tight">
                A-Code Solutions
                <span class="hidden sm:inline text-xs font-normal text-white/70 align-middle">· Analizamos, diseñamos, transformamos.</span>
            </a>

            @auth
                <div class="flex items-center gap-4 text-sm">
                    @if (auth()->user()->role === 'teacher')
                        <a href="{{ route('groups.index') }}" class="hover:text-acode-warning">Grupos</a>
                        <a href="{{ route('questions.index') }}" class="hover:text-acode-warning">Reactivos</a>
                        <a href="{{ route('exams.index') }}" class="hover:text-acode-warning">Exámenes</a>
                    @elseif (auth()->user()->role === 'student')
                        <a href="{{ route('student.exams.index') }}" class="hover:text-acode-warning">Mis exámenes</a>
                        <a href="{{ route('student.groups.join.form') }}" class="hover:text-acode-warning">Unirme a un grupo</a>
                    @endif
                    <span class="text-white/80">{{ auth()->user()->full_name }}</span>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="h-8 px-3 text-xs rounded-md border border-white/30 hover:bg-white/10">
                            Salir
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm">
                <p class="font-semibold mb-1">Revisa lo siguiente:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-4 py-6 text-xs text-slate-500">
        A-Code Solutions — Plataforma Evaluadora / Aplicadora de Exámenes (demo).
    </footer>
</body>
</html>
