{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/monitor.blade.php (RW-12, RW-16, MPW-V2 §7, §8) --}}
@extends('layouts.app')

@section('title', 'Monitoreo en Vivo - ' . $exam->title)

@section('content')
<div
    x-data="{
        examId: {{ $exam->id }},
        inProgress: {{ $inProgressCount }},
        submitted: {{ $submittedCount }},
        cancelled: {{ $cancelledCount }},
        incidents: {{ $totalIncidents }},
        alerts: [],
        students: @js($studentsPayload),
        maxIncidents: {{ $exam->max_allowed_incidents ?? 3 }},
        socketConnected: false,

        init() {
            this.connectWebSocket();
        },

        connectWebSocket() {
            try {
                const wsHost = window.location.hostname;
                const wsPort = {{ $reverbConfig['port'] }};
                const wsKey = '{{ $reverbConfig['key'] }}';
                const wsUrl = `ws://${wsHost}:${wsPort}/app/${wsKey}?protocol=7&client=js&version=8.4.0-reverb`;

                const ws = new WebSocket(wsUrl);

                ws.onmessage = (event) => {
                    try {
                        const message = JSON.parse(event.data);

                        if (message.event === 'pusher:connection_established') {
                            const connData = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
                            const socketId = connData.socket_id;
                            this.socketConnected = true;

                            // Autenticación en canal privado con firma HMAC
                            fetch('{{ url('/broadcasting/auth') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    socket_id: socketId,
                                    channel_name: `private-exam.${this.examId}`
                                })
                            })
                            .then(res => res.json())
                            .then(authData => {
                                ws.send(JSON.stringify({
                                    event: 'pusher:subscribe',
                                    data: {
                                        auth: authData.auth || '',
                                        channel: `private-exam.${this.examId}`
                                    }
                                }));
                            })
                            .catch(err => console.warn('Error autorizando canal Reverb:', err));
                            return;
                        }

                        this.handleLiveEvent(message);
                    } catch (e) {
                        console.error('Error parseando evento Reverb:', e);
                    }
                };

                ws.onclose = () => {
                    this.socketConnected = false;
                    setTimeout(() => this.connectWebSocket(), 5000);
                };
            } catch (err) {
                console.warn('Reverb WebSocket fallback en modo pasivo:', err);
            }
        },

        handleLiveEvent(message) {
            const ev = message.event;
            const data = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;

            if (ev === 'exam.attempt.started') {
                const st = this.students[data.student_id];
                if (st) {
                    st.status = 'in_progress';
                    st.started_at = data.started_at;
                    this.inProgress++;
                    this.pushAlert(`El alumno ${data.student_name} ha ingresado al examen.`, 'info');
                }
            } else if (ev === 'exam.incident.reported') {
                const st = this.students[data.student_id];
                if (st) {
                    st.incidents = data.incident_count;
                    this.incidents++;

                    let alertType = 'warning';
                    let alertMsg = `Sospecha detectada: ${data.student_name} — ${data.incident_type} (${data.incident_count}/${this.maxIncidents})`;

                    if (data.is_cancelled) {
                        st.status = 'cancelled_by_incident';
                        this.cancelled++;
                        this.inProgress = Math.max(0, this.inProgress - 1);
                        alertType = 'danger';
                        alertMsg = `¡ALERTA CRÍTICA! Intento cancelado por acumulación de trampas: ${data.student_name}`;
                    }

                    this.pushAlert(alertMsg, alertType);
                }
            } else if (ev === 'exam.attempt.submitted') {
                const st = this.students[data.student_id];
                if (st) {
                    st.status = data.status || 'submitted';
                    st.submitted_at = data.submitted_at;
                    if (data.final_score !== null) {
                        st.score = parseFloat(data.final_score).toFixed(2);
                    }
                    this.inProgress = Math.max(0, this.inProgress - 1);
                    this.submitted++;
                    this.pushAlert(`El alumno ${data.student_name} ha entregado su examen.`, 'success');
                }
            }
        },

        pushAlert(text, type = 'info') {
            this.alerts.unshift({ id: Date.now(), text, type });
            if (this.alerts.length > 5) {
                this.alerts.pop();
            }
        },

        removeAlert(id) {
            this.alerts = this.alerts.filter(a => a.id !== id);
        }
    }"
    class="space-y-6"
>
    {{-- Encabezado del Monitoreo --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                <a href="{{ route('exams.index') }}" class="hover:text-acode-tech">Exámenes</a>
                <span>/</span>
                <span class="text-acode-primary">Supervisión en Tiempo Real</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">{{ $exam->title }}</h1>
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all"
                    :class="socketConnected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="socketConnected ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"
                    ></span>
                    <span x-text="socketConnected ? 'Reverb Conectado en Vivo' : 'Conectando WebSocket...'"></span>
                </span>
            </div>
            <p class="text-sm text-slate-600 mt-1">
                Monitoreo activo de alumnos, entrega de reactivos y detección inmediata de sospechas anti-trampa.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-button :href="route('exams.index')" variant="neutral" size="sm">
                <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90 mr-1" />
                Volver a Exámenes
            </x-button>
            <x-button :href="route('exams.assemble', $exam)" variant="secondary" size="sm">
                <x-icon name="documentText" class="w-4 h-4 mr-1.5" />
                Ver Estructura
            </x-button>
        </div>
    </div>

    {{-- Tarjetas de Métricas en Tiempo Real (RW-12, RW-16) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Matriculados --}}
        <x-card class="bg-gradient-to-br from-slate-50 to-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Matriculados</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ $totalEnrolled }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Alumnos en grupos asignados</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
                    <x-icon name="userGroup" class="w-5 h-5" />
                </div>
            </div>
        </x-card>

        {{-- En Curso (Activos en Vivo) --}}
        <x-card class="bg-gradient-to-br from-emerald-50/60 to-white border border-emerald-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">En Examen (En Vivo)</p>
                    <div class="flex items-center gap-2 mt-1">
                        <p class="text-2xl font-bold text-emerald-800" x-text="inProgress"></p>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                    </div>
                    <p class="text-[11px] text-emerald-600 mt-0.5">Respondiendo actualmente</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700">
                    <x-icon name="clock" class="w-5 h-5" />
                </div>
            </div>
        </x-card>

        {{-- Entregados / Calificados --}}
        <x-card class="bg-gradient-to-br from-blue-50/60 to-white border border-blue-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Entregados</p>
                    <p class="text-2xl font-bold text-blue-900 mt-1" x-text="submitted"></p>
                    <p class="text-[11px] text-blue-600 mt-0.5">Finalizados o expirados</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700">
                    <x-icon name="checkCircle" class="w-5 h-5" />
                </div>
            </div>
        </x-card>

        {{-- Incidentes Anti-Trampa Detectados --}}
        <div
            class="rounded-2xl p-5 border shadow-sm transition-all"
            :class="incidents > 0 ? 'bg-rose-50/80 border-rose-300 ring-2 ring-rose-200' : 'bg-white border-slate-200'"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider" :class="incidents > 0 ? 'text-rose-700' : 'text-slate-500'">
                        Incidentes Anti-Fraude
                    </p>
                    <p class="text-2xl font-bold mt-1" :class="incidents > 0 ? 'text-rose-900' : 'text-slate-900'" x-text="incidents"></p>
                    <p class="text-[11px] mt-0.5" :class="incidents > 0 ? 'text-rose-600' : 'text-slate-400'">
                        Cambios de pestaña / salida app
                    </p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="incidents > 0 ? 'bg-rose-200 text-rose-800' : 'bg-slate-200 text-slate-500'">
                    <x-icon name="shield" class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Banner de Alertas Dinámicas en Tiempo Real --}}
    <template x-if="alerts.length > 0">
        <div class="space-y-2">
            <template x-for="alert in alerts" :key="alert.id">
                <div
                    class="p-3.5 rounded-lg border flex items-center justify-between shadow-sm transition-all"
                    :class="{
                        'bg-rose-50 border-rose-300 text-rose-900': alert.type === 'danger',
                        'bg-amber-50 border-amber-300 text-amber-900': alert.type === 'warning',
                        'bg-emerald-50 border-emerald-300 text-emerald-900': alert.type === 'success',
                        'bg-blue-50 border-blue-300 text-blue-900': alert.type === 'info'
                    }"
                >
                    <div class="flex items-center gap-2.5">
                        <x-icon name="exclamationCircle" class="w-5 h-5 shrink-0" />
                        <span class="text-xs sm:text-sm font-semibold" x-text="alert.text"></span>
                    </div>
                    <button
                        type="button"
                        @click="removeAlert(alert.id)"
                        class="text-xs font-bold opacity-60 hover:opacity-100 px-2 py-0.5"
                    >
                        &times;
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- Tabla de Alumnos en Vivo --}}
    <x-card>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-acode-primary">Supervisión Individual de Alumnos</h2>
            <span class="text-xs text-slate-500">Actualización en vivo sin recargar &bull; Reverb WS</span>
        </div>

        <x-table>
            <x-slot:header>
                <x-table.th>Estudiante</x-table.th>
                <x-table.th align="center">Estado del Examen</x-table.th>
                <x-table.th align="center">Incidentes Anti-Fraude</x-table.th>
                <x-table.th align="center">Hora de Inicio</x-table.th>
                <x-table.th align="center">Hora de Entrega</x-table.th>
                <x-table.th align="right">Calificación</x-table.th>
            </x-slot:header>

            <template x-for="st in Object.values(students)" :key="st.id">
                <tr>
                    <x-table.td>
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech shrink-0">
                                <span x-text="st.name.charAt(0).toUpperCase()"></span>
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 text-sm" x-text="st.name"></div>
                                <div class="text-xs text-slate-500" x-text="st.email"></div>
                            </div>
                        </div>
                    </x-table.td>

                    {{-- Estado del Examen --}}
                    <x-table.td align="center">
                        <template x-if="st.status === 'in_progress'">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                En Examen
                            </span>
                        </template>
                        <template x-if="st.status === 'submitted'">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                <x-icon name="check" class="w-3.5 h-3.5" />
                                Entregado
                            </span>
                        </template>
                        <template x-if="st.status === 'cancelled_by_incident'">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                <x-icon name="lock" class="w-3.5 h-3.5" />
                                Cancelado por Trampa
                            </span>
                        </template>
                        <template x-if="st.status === 'not_started'">
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                                Sin Iniciar
                            </span>
                        </template>
                    </x-table.td>

                    {{-- Contador de Incidentes --}}
                    <x-table.td align="center">
                        <template x-if="st.incidents > 0">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-mono text-xs font-bold"
                                :class="st.incidents >= maxIncidents ? 'bg-rose-600 text-white' : 'bg-amber-100 text-amber-900'"
                            >
                                <x-icon name="shield" class="w-3.5 h-3.5" />
                                <span x-text="`${st.incidents} / ${maxIncidents}`"></span>
                            </span>
                        </template>
                        <template x-if="st.incidents === 0">
                            <span class="text-xs text-slate-400 font-mono">0</span>
                        </template>
                    </x-table.td>

                    <x-table.td align="center">
                        <span class="font-mono text-xs text-slate-600" x-text="st.started_at"></span>
                    </x-table.td>

                    <x-table.td align="center">
                        <span class="font-mono text-xs text-slate-600" x-text="st.submitted_at"></span>
                    </x-table.td>

                    <x-table.td align="right">
                        <span class="font-mono text-sm font-bold text-slate-900" x-text="st.score"></span>
                    </x-table.td>
                </tr>
            </template>
        </x-table>
    </x-card>
</div>
@endsection
