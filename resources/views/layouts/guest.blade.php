{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: guest.blade.php (MPW-V2 §7.2, §8.1, §8.2) --}}
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
<body class="bg-acode-surface font-sans text-acode-ink min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <p class="font-bold text-2xl text-acode-primary tracking-tight">A-Code Solutions</p>
            <p class="text-xs text-slate-500">"Analizamos, diseñamos, transformamos."</p>
        </div>

        <x-card>
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </x-card>
    </div>

    @stack('scripts')
</body>
</html>
