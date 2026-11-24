{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-analytics.group-chart (MPW-V3 §7.3, §7.9) --}}
@props([
    'passedCount' => 0,
    'failedCount' => 0,
    'averages' => [],
    'title' => 'Distribución de Aprobados y Calificaciones',
    'datasetLabel' => 'Alumnos',
    'emptyTitle' => 'Aún no hay entregas finalizadas para graficar',
    'emptyMessage' => 'La distribución de aprobados y reprobados se mostrará cuando los alumnos concluyan sus exámenes.',
])

<div
    x-data="groupScoreChart({
        passed: {{ (int) $passedCount }},
        failed: {{ (int) $failedCount }},
        datasetLabel: @js($datasetLabel)
    })"
    x-init="renderChart()"
    {{ $attributes->merge(['class' => 'bg-white p-6 rounded-xl border border-slate-200 shadow-sm']) }}
>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-2 border-b border-slate-100">
        <h3 class="text-base font-semibold text-acode-primary flex items-center gap-2">
            <x-icon name="reportBar" class="w-5 h-5 text-acode-tech" />
            <span>{{ $title }}</span>
        </h3>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
            <span class="inline-flex items-center gap-1 font-medium text-acode-accent-dark">
                <span class="w-2.5 h-2.5 rounded-full bg-acode-accent"></span> Aprobados ({{ (int) $passedCount }})
            </span>
            <span class="inline-flex items-center gap-1 font-medium text-acode-danger">
                <span class="w-2.5 h-2.5 rounded-full bg-acode-danger"></span> Reprobados ({{ (int) $failedCount }})
            </span>
        </div>
    </div>

    <div class="relative h-64 w-full overflow-hidden">
        <canvas x-ref="canvas" aria-hidden="true"></canvas>

        @if ((int) $passedCount + (int) $failedCount === 0)
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center rounded-lg bg-slate-50 px-4 text-center">
                <x-icon name="reportBar" class="w-8 h-8 text-slate-300 mb-2" />
                <p class="text-xs font-semibold text-slate-600">
                    {{ $emptyTitle }}
                </p>
                <p class="text-xs text-slate-600 mt-0.5 max-w-xs">
                    {{ $emptyMessage }}
                </p>
            </div>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            function groupScoreChart({ passed, failed, datasetLabel }) {
                return {
                    chartInstance: null,
                    renderChart() {
                        if (passed + failed === 0) {
                            return;
                        }

                        if (typeof Chart === 'undefined') {
                            console.warn('A-Code Analytics: Chart.js no se encuentra disponible en la vista.');
                            return;
                        }

                        if (this.chartInstance) {
                            this.chartInstance.destroy();
                        }

                        this.chartInstance = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: {
                                labels: ['Aprobados', 'Reprobados'],
                                datasets: [{
                                    label: datasetLabel,
                                    data: [passed, failed],
                                    backgroundColor: ['#16A6A1', '#DC2626'],
                                    hoverBackgroundColor: ['#11807C', '#B91C1C'],
                                    borderRadius: 8,
                                    borderSkipped: false,
                                    barPercentage: 0.5,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        backgroundColor: '#142B4A',
                                        padding: 10,
                                        cornerRadius: 8,
                                        titleFont: { family: 'Inter, sans-serif', size: 13, weight: '600' },
                                        bodyFont: { family: 'Inter, sans-serif', size: 12 }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0,
                                            font: { family: 'Inter, sans-serif', size: 11 },
                                            color: '#64748B'
                                        },
                                        grid: {
                                            color: '#E2E8F0',
                                            drawBorder: false
                                        }
                                    },
                                    x: {
                                        ticks: {
                                            font: { family: 'Inter, sans-serif', size: 12, weight: '500' },
                                            color: '#142B4A'
                                        },
                                        grid: {
                                            display: false
                                        }
                                    }
                                }
                            }
                        });
                    }
                };
            }
        </script>
    @endpush
@endonce
