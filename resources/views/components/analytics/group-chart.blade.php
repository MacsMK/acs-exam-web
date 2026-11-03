{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-analytics.group-chart (MPW-V2 §7.3, §7.9, Tabla 23) --}}
@props([
    'passedCount' => 0,
    'failedCount' => 0,
    'averages' => [],
    'title' => 'Distribución de Aprobados y Calificaciones',
])

<div
    x-data="groupScoreChart({
        passed: {{ (int) $passedCount }},
        failed: {{ (int) $failedCount }},
        averages: {{ json_encode($averages) }}
    })"
    x-init="renderChart()"
    {{ $attributes->merge(['class' => 'bg-white p-6 rounded-xl border border-slate-200 shadow-sm']) }}
>
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
        <h3 class="text-base font-bold text-acode-primary flex items-center gap-2">
            <x-icon name="reportBar" class="w-5 h-5 text-acode-tech" />
            <span>{{ $title }}</span>
        </h3>
        <div class="flex items-center gap-3 text-xs">
            <span class="inline-flex items-center gap-1 font-medium text-acode-accent">
                <span class="w-2.5 h-2.5 rounded-full bg-acode-accent"></span> Aprobados ({{ (int) $passedCount }})
            </span>
            <span class="inline-flex items-center gap-1 font-medium text-acode-danger">
                <span class="w-2.5 h-2.5 rounded-full bg-acode-danger"></span> Reprobados ({{ (int) $failedCount }})
            </span>
        </div>
    </div>

    <div class="relative h-64 w-full">
        <canvas x-ref="canvas"></canvas>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function groupScoreChart({ passed, failed, averages }) {
                return {
                    chartInstance: null,
                    renderChart() {
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
                                    label: 'Alumnos',
                                    data: [passed, failed],
                                    backgroundColor: ['#16A6A1', '#DC2626'],
                                    hoverBackgroundColor: ['#138985', '#B91C1C'],
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
                                        titleFont: { family: 'Inter', size: 13, weight: '600' },
                                        bodyFont: { family: 'Inter', size: 12 }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0,
                                            font: { family: 'Inter', size: 11 },
                                            color: '#64748B'
                                        },
                                        grid: {
                                            color: '#E2E8F0',
                                            drawBorder: false
                                        }
                                    },
                                    x: {
                                        ticks: {
                                            font: { family: 'Inter', size: 12, weight: '500' },
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
