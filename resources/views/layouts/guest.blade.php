{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Layout: guest.blade.php (MPW-V2 §7.2, §8.1, §8.2, §8.10.3) --}}
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
    <script src="{{ asset('js/alerts.js') }}"></script>
</head>
<body class="bg-acode-surface font-sans text-acode-ink min-h-screen flex flex-col justify-between p-4 sm:p-6 antialiased">
    <div></div>

    <div class="w-full max-w-md mx-auto my-auto">
        {{-- Logotipo Institucional con área de protección perimetral obligatoria de 16px (MPW-V2 §8.1.2) --}}
        <div class="flex justify-center p-4 mb-4">
            <x-logo variant="full" />
        </div>

        <x-card class="shadow-lg border-slate-200">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm flex items-center gap-2">
                    <x-icon name="success" class="w-4 h-4 text-acode-accent shrink-0" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm">
                    <div class="flex items-center gap-1.5 font-semibold mb-1">
                        <x-icon name="warning" class="w-4 h-4 shrink-0" />
                        <span>Revisa los siguientes campos:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-xs pl-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </x-card>
    </div>

    <div class="text-center text-xs text-slate-400 py-4 select-none">
        <p>&copy; 2026 A-Code Solutions · Cumplimiento Normativo MPW-V2</p>
    </div>

    @stack('scripts')
</body>
</html>
