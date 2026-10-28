{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: app.blade.php (MPW-V2 §7.2, §8.1, §8.2, §8.10.7) --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
</head>
<body class="bg-acode-surface font-sans text-acode-ink min-h-screen flex flex-col">
    <nav class="sticky top-0 z-40 bg-acode-navy text-white shadow-sm">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-tight">
                A-Code Solutions
                <span class="hidden sm:inline text-xs font-normal text-white/70 align-middle">· Analizamos, diseñamos, transformamos.</span>
            </a>

            @auth
                <div class="flex items-center gap-4 text-sm">
                    @if (auth()->user()->role === 'teacher')
                        <a href="{{ route('groups.index') }}" class="hover:text-acode-warning transition">Grupos</a>
                        <a href="{{ route('questions.index') }}" class="hover:text-acode-warning transition">Reactivos</a>
                        <a href="{{ route('exams.index') }}" class="hover:text-acode-warning transition">Exámenes</a>
                    @elseif (auth()->user()->role === 'student')
                        <a href="{{ route('student.exams.index') }}" class="hover:text-acode-warning transition">Mis exámenes</a>
                        <a href="{{ route('student.groups.join.form') }}" class="hover:text-acode-warning transition">Unirme a un grupo</a>
                    @endif
                    <span class="text-white/80">{{ auth()->user()->full_name }}</span>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="h-8 px-3 text-xs rounded-lg border border-white/30 hover:bg-white/10 transition">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 py-8">
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

    <footer class="border-t border-slate-200 bg-white mt-auto">
        <div class="max-w-6xl mx-auto px-4 py-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-xs text-slate-600">
            <div>
                <p class="font-semibold text-acode-navy">A-Code Solutions</p>
                <p class="text-slate-500">"Analizamos, diseñamos, transformamos." · Plataforma Evaluadora / Aplicadora de Exámenes</p>
            </div>
            <div class="text-slate-500 sm:text-right">
                <p>Ecosistema Evaluador de Exámenes SaaS · Estándar Normativo MPW-V2</p>
                <p>&copy; {{ date('Y') }} A-Code Solutions (Equipo 4). Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
