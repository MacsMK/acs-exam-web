{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: exam-runner.blade.php (MPW-V2 §7.2, §8.10.2) --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Presentando examen') · A-Code Solutions</title>

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
<body class="bg-slate-50 font-sans text-acode-ink min-h-screen select-none">
    <header class="sticky top-0 z-40 h-14 bg-acode-navy text-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-bold text-sm tracking-tight">A-Code Solutions</span>
                <span class="text-xs text-white/70">· Modo evaluación supervisada</span>
            </div>
            @auth
                <span class="text-xs text-white/80">{{ auth()->user()->full_name }}</span>
            @endauth
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
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

    @stack('scripts')
</body>
</html>
