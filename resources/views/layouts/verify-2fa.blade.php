<!DOCTYPE html>
{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     verify-2fa.blade.php
    Ubicación:   resources/views/layouts/verify-2fa.blade.php
    Descripción: Layout exclusivo de verificación en dos pasos.
    ============================================================================
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Verificación en dos pasos') · A-Code Solutions</title>

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
                            danger: '#DC2626'
                        }
                    }
                }
            }
        };
    </script>
</head>

<body class="min-h-screen bg-acode-surface font-sans text-slate-700 flex flex-col">

    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-[460px]">

            <div class="bg-white rounded-2xl shadow-lg shadow-acode-primary/5 border border-slate-200/80 px-6 py-8 sm:px-10 sm:py-10">

                @if (session('status'))
                    <div role="status"
                         class="mb-6 rounded-lg border border-acode-accent/30 bg-acode-accent/10 text-acode-primary px-4 py-3 text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert"
                         class="mb-6 rounded-lg border border-acode-danger/30 bg-acode-danger/10 text-acode-danger px-4 py-3 text-sm">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')

            </div>
        </div>
    </main>

    <footer class="text-center px-4 pb-6 text-xs text-slate-500">
        A-Code Solutions · Verificación en dos pasos
    </footer>

    @stack('scripts')
</body>
</html>
