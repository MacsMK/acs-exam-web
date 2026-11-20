{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: errors/403.blade.php (MPW-V2 §6.6.2, §7.2, §8.1, §8.2) --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Acceso restringido · A-Code Solutions</title>

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
</head>
<body class="min-h-screen bg-acode-surface font-sans text-acode-ink antialiased flex flex-col">
    <header class="bg-acode-primary text-white border-b border-white/10 shadow-sm">
        <div class="max-w-5xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3 select-none">
                <svg class="w-8 h-8 shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M20 4L4 32H13.5L20 19.5L26.5 32H36L20 4Z" fill="#FFFFFF" />
                    <path d="M20 19.5L13.5 32H26.5L20 19.5Z" fill="#FFFFFF" fill-opacity="0.75" />
                    <path d="M20 25L16.8 32H23.2L20 25Z" fill="#FFFFFF" fill-opacity="0.45" />
                </svg>
                <div class="flex flex-col">
                    <span class="font-bold text-base tracking-tight text-white leading-tight">A-Code Solutions</span>
                    <span class="text-[10px] text-white/70 font-normal tracking-wide">Analizamos, diseñamos, transformamos</span>
                </div>
            </a>

            <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-full bg-white/10 text-white/90">
                HTTP 403
            </span>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-5 py-12 sm:px-8">
        <section class="w-full max-w-xl bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="h-1.5 bg-acode-warning w-full"></div>

            <div class="p-8 sm:p-10 text-center">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-acode-warning/15 border border-acode-warning/40 text-acode-primary">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                </div>

                <p class="font-mono text-5xl sm:text-6xl font-extrabold tracking-tight text-acode-primary leading-none">
                    403
                </p>

                <h1 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight text-acode-ink">
                    Acceso restringido
                </h1>

                <p class="mt-3 text-sm sm:text-base leading-relaxed text-slate-600 max-w-md mx-auto">
                    No cuentas con los permisos necesarios para consultar esta sección o realizar esta acción. Si consideras que se trata de un error, comparte el folio de incidente con tu coordinador o con el equipo de soporte.
                </p>

                <div class="mt-6 inline-flex flex-col sm:flex-row items-center gap-1.5 sm:gap-3 rounded-xl bg-acode-surface border border-slate-200 px-4 py-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Folio de incidente
                    </span>
                    <span class="font-mono text-sm font-bold tracking-wider text-acode-primary select-all">
                        ACS-{{ strtoupper(substr(md5((string) microtime(true)), 0, 8)) }}
                    </span>
                </div>

                <div class="mt-8 flex flex-col-reverse sm:flex-row items-center justify-center gap-3">
                    <button
                        type="button"
                        onclick="window.history.back()"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-acode-ink hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-acode-tech/40 transition"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M19 12H5" />
                            <path d="M12 19l-7-7 7-7" />
                        </svg>
                        Volver
                    </button>

                    <a
                        href="{{ url('/') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-acode-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-acode-primary-dark focus:outline-none focus:ring-2 focus:ring-acode-tech/40 transition"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 10.5L12 3l9 7.5" />
                            <path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5" />
                        </svg>
                        Volver al inicio
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="py-6 text-center text-xs text-slate-500 select-none">
        <p>&copy; 2026 A-Code Solutions · Evaluador de Exámenes · Cumplimiento Normativo MPW-V2</p>
    </footer>
</body>
</html>
