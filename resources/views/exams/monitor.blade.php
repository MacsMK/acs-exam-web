{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/monitor.blade.php (MPW-V2 §4.1.2, §7, §8, RW-12, RW-16) --}}
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
            connectionLabel: 'Conectando WebSocket...',
            statusFilter: 'all',
            searchQuery: '',

            init() {
                this.connectWebSocket();
            },

            get filteredStudents() {
                const term = this.searchQuery.trim().toLowerCase();
                return Object.values(this.students).filter((st) => {
                    const matchesSearch = !term
                        || (st.name && st.name.toLowerCase().includes(term))
                        || (st.email && st.email.toLowerCase().includes(term));
                    const matchesStatus = this.statusFilter === 'all' || st.status === this.statusFilter;
                    return matchesSearch && matchesStatus;
                });
            },

            connectWebSocket() {
                try {
                    const useTls = @js((bool) ($reverbConfig['useTls'] ?? false)) || window.location.protocol === 'https:';
                    const wsProtocol = useTls ? 'wss' : 'ws';
                    const wsHost = @js($reverbConfig['host'] ?? '') || window.location.hostname;
                    const wsPort = @js((int) ($reverbConfig['port'] ?? 8080));
                    const wsKey = @js($reverbConfig['key'] ?? '');
                    const wsUrl = `${wsProtocol}://${wsHost}:${wsPort}/app/${wsKey}?protocol=7&client=js&version=8.4.0-reverb`;

                    const ws = new WebSocket(wsUrl);

                    ws.onmessage = (event) => {
                        try {
                            const message = JSON.parse(event.data);

                            if (message.event === 'pusher:connection_established') {
                                const connData = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
                                const socketId = connData.socket_id;
                                this.connectionLabel = 'Autenticando canal privado...';

                                fetch(@js(url('/broadcasting/auth')), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': @js(csrf_token())
                                    },
                                    body: JSON.stringify({
                                        socket_id: socketId,
                                        channel_name: `private-exam.${this.examId}`
                                    })
                                })
                                .then((res) => {
                                    if (!res.ok) throw new Error('HTTP ' + res.status);
                                    return res.json();
                                })
                                .then((authData) => {
                                    ws.send(JSON.stringify({
                                        event: 'pusher:subscribe',
                                        data: {
                                            auth: authData.auth || '',
                                            channel: `private-exam.${this.examId}`
                                        }
                                    }));
                                })
                                .catch(() => {
                                    this.socketConnected = false;
                                    this.connectionLabel = 'Error autenticando canal';
                                });
                                return;
                            }

                            if (message.event === 'pusher:subscription_succeeded') {
                                this.socketConnected = true;
                                this.connectionLabel = 'Reverb Conectado en Vivo';
                                return;
                            }

                            if (message.event === 'pusher:subscription_error' || message.event === 'pusher:error') {
                                this.socketConnected = false;
                                this.connectionLabel = 'Error de suscripción en canal';
                                this.pushAlert('No fue posible suscribirse al canal en vivo de este examen.', 'warning');
                                return;
                            }

                            this.handleLiveEvent(message);
                        } catch (e) {
                            console.error('Error parseando evento Reverb:', e);
                        }
                    };

                    ws.onerror = () => {
                        this.socketConnected = false;
                        this.connectionLabel = 'Sin conexión en tiempo real';
                    };

                    ws.onclose = () => {
                        this.socketConnected = false;
                        this.connectionLabel = 'Reconectando WebSocket...';
                        setTimeout(() => this.connectWebSocket(), 5000);
                    };
                } catch (err) {
                    this.socketConnected = false;
                    this.connectionLabel = 'Modo pasivo (sin WebSocket)';
                }
            },

            handleLiveEvent(message) {
                const ev = message.event;
                const data = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;

                if (ev === 'exam.attempt.started') {
                    const st = this.students[data.student_id];
                    if (st) {
                        if (st.status === 'not_started') {
                            this.inProgress++;
                        }
                        st.status = 'in_progress';
                        st.started_at = data.started_at;
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
                            if (st.status === 'in_progress') {
                                this.inProgress = Math.max(0, this.inProgress - 1);
                            }
                            st.status = 'cancelled_by_incident';
                            this.cancelled++;
                            alertType = 'danger';
                            alertMsg = `¡ALERTA CRÍTICA! Intento cancelado por acumulación de incidentes: ${data.student_name}`;

                            if (typeof AcodeAlert !== 'undefined') {
                                AcodeAlert.warning(
                                    'Intento cancelado por incidencias',
                                    `El intento de ${data.student_name} fue cancelado automáticamente por superar el límite anti-trampa.`
                                );
                            }
                        }

                        this.pushAlert(alertMsg, alertType);
                    }
                } else if (ev === 'exam.attempt.submitted') {
                    const st = this.students[data.student_id];
                    if (st) {
                        if (st.status === 'in_progress') {
                            this.inProgress = Math.max(0, this.inProgress - 1);
                        }
                        st.status = data.status || 'submitted';
                        st.submitted_at = data.submitted_at;
                        if (data.final_score !== null && data.final_score !== undefined) {
                            st.score = parseFloat(data.final_score).toFixed(2);
                        }
                        this.submitted++;
                        this.pushAlert(`El alumno ${data.student_name} ha entregado su examen.`, 'success');
                    }
                }
            },

            pushAlert(text, type = 'info') {
                this.alerts.unshift({ id: Date.now() + Math.random(), text, type });
                if (this.alerts.length > 5) {
                    this.alerts.pop();
                }
            },

            removeAlert(id) {
                this.alerts = this.alerts.filter((a) => a.id !== id);
            }
        }"
        class="space-y-6"
    >
        {{-- Encabezado --}}
        <x-page-header
            :title="'Monitoreo en vivo · ' . $exam->title"
            subtitle="Supervisa en tiempo real la conexión de tus estudiantes, su progreso y posibles incidencias de seguridad."
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => $exam->title, 'href' => route('exams.review', $exam)],
                ['label' => 'Monitoreo'],
            ]"
        >
            <x-slot:badge>
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all"
                    :class="socketConnected ? 'bg-acode-accent/15 text-acode-accent-dark border border-acode-accent/30' : 'bg-acode-warning/15 text-acode-warning-dark border border-acode-warning/40'"
                >
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="socketConnected ? 'bg-acode-accent animate-pulse' : 'bg-acode-warning'"
                    ></span>
                    <span x-text="connectionLabel"></span>
                </span>
            </x-slot:badge>

            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
                <x-button :href="route('exams.assemble', $exam)" variant="secondary" size="md">
                    <x-icon name="document" class="w-4 h-4" />
                    Abrir estructura
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Tarjetas KPI en Tiempo Real --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5" aria-label="Métricas de monitoreo en tiempo real">
            <x-kpi-card
                label="Matriculados"
                :value="$totalEnrolled"
                meta="En grupos asignados"
                icon="userGroup"
                tone="tech"
            />

            <x-kpi-card
                label="En examen"
                meta="Respondiendo en vivo"
                icon="clock"
                tone="accent"
            >
                <x-slot:valueSlot>
                    <span class="inline-flex items-center gap-2 text-acode-accent-dark">
                        <span x-text="inProgress"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-acode-accent animate-ping"></span>
                    </span>
                </x-slot:valueSlot>
            </x-kpi-card>

            <x-kpi-card
                label="Entregados"
                meta="Finalizados"
                icon="success"
                tone="tech"
            >
                <x-slot:valueSlot>
                    <span x-text="submitted"></span>
                </x-slot:valueSlot>
            </x-kpi-card>

            <x-kpi-card
                label="Incidentes anti-fraude"
                meta="Cambios de pestaña / app"
                icon="shield"
                tone="danger"
                x-bind:class="incidents > 0 ? '!bg-acode-danger/10 !border-acode-danger/40 ring-2 ring-acode-danger/20' : ''"
            >
                <x-slot:valueSlot>
                    <span x-bind:class="incidents > 0 ? 'text-acode-danger-dark' : 'text-acode-primary'" x-text="incidents"></span>
                </x-slot:valueSlot>
            </x-kpi-card>
        </section>

        {{-- Alertas en Tiempo Real --}}
        <template x-if="alerts.length > 0">
            <div class="space-y-2">
                <template x-for="alert in alerts" :key="alert.id">
                    <div
                        class="p-3.5 rounded-xl border flex items-center justify-between shadow-sm transition-all"
                        :class="{
                            'bg-acode-danger/10 border-acode-danger/40 text-acode-danger-dark': alert.type === 'danger',
                            'bg-acode-warning/15 border-acode-warning/50 text-acode-warning-dark': alert.type === 'warning',
                            'bg-acode-accent/15 border-acode-accent/40 text-acode-accent-dark': alert.type === 'success',
                            'bg-acode-surface border-acode-tech/30 text-acode-primary': alert.type === 'info'
                        }"
                    >
                        <div class="flex items-center gap-2.5">
                            <x-icon name="warning" class="w-5 h-5 shrink-0" />
                            <span class="text-xs sm:text-sm font-semibold" x-text="alert.text"></span>
                        </div>
                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            :icon-only="true"
                            x-on:click="removeAlert(alert.id)"
                            title="Descartar alerta"
                            aria-label="Descartar alerta"
                        >
                            <x-icon name="xMark" class="w-3.5 h-3.5" />
                        </x-button>
                    </div>
                </template>
            </div>
        </template>

        {{-- Barra de búsqueda/filtro y Tabla de Alumnos en Vivo (sin doble tarjeta) --}}
        <section class="space-y-4" aria-label="Supervisión individual de alumnos">
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <x-section-header
                    title="Supervisión individual de alumnos"
                    subtitle="Actualización reactiva en vivo vía Laravel Reverb"
                />

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <div class="relative w-full sm:w-56">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-icon name="search" class="w-4 h-4" />
                        </div>
                        <x-form.input
                            type="search"
                            size="sm"
                            x-model="searchQuery"
                            placeholder="Buscar estudiante…"
                            class="pl-9"
                        />
                    </div>

                    <div class="w-full sm:w-48">
                        <x-form.select size="sm" x-model="statusFilter" aria-label="Filtrar alumnos por estado">
                            <option value="all">Todos los estados</option>
                            <option value="in_progress">En examen</option>
                            <option value="submitted">Entregado</option>
                            <option value="expired">Expirado</option>
                            <option value="cancelled_by_incident">Cancelado por incidencias</option>
                            <option value="not_started">Sin iniciar</option>
                        </x-form.select>
                    </div>
                </div>
            </div>

            <div x-show="filteredStudents.length === 0" x-cloak>
                <x-empty-state
                    icon="userGroup"
                    title="No hay estudiantes para mostrar"
                    message="No hay alumnos matriculados en los grupos asignados o ninguno coincide con el filtro actual."
                />
            </div>

            <div x-show="filteredStudents.length > 0">
                <x-table>
                    <x-slot:header>
                        <x-table.th>Estudiante</x-table.th>
                        <x-table.th align="center">Estado del Examen</x-table.th>
                        <x-table.th align="center">Incidentes Anti-Fraude</x-table.th>
                        <x-table.th align="center">Hora de Inicio</x-table.th>
                        <x-table.th align="center">Hora de Entrega</x-table.th>
                        <x-table.th align="right">Calificación</x-table.th>
                    </x-slot:header>

                    <template x-for="st in filteredStudents" :key="st.id">
                        <tr>
                            <x-table.td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech shrink-0">
                                        <span x-text="st.name.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <div class="font-bold text-acode-primary text-sm" x-text="st.name"></div>
                                        <div class="text-xs text-slate-500" x-text="st.email"></div>
                                    </div>
                                </div>
                            </x-table.td>

                            <x-table.td align="center">
                                <template x-if="st.status === 'in_progress'">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-acode-accent/15 text-acode-accent-dark border border-acode-accent/30">
                                        <span class="w-2 h-2 rounded-full bg-acode-accent animate-ping"></span>
                                        En Examen
                                    </span>
                                </template>
                                <template x-if="st.status === 'submitted'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-acode-surface text-acode-tech border border-acode-tech/30">
                                        <x-icon name="check" class="w-3.5 h-3.5" />
                                        Entregado
                                    </span>
                                </template>
                                <template x-if="st.status === 'expired'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-acode-warning/15 text-acode-warning-dark border border-acode-warning/40">
                                        <x-icon name="clock" class="w-3.5 h-3.5" />
                                        Expirado
                                    </span>
                                </template>
                                <template x-if="st.status === 'cancelled_by_incident'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-acode-danger/15 text-acode-danger-dark border border-acode-danger/30">
                                        <x-icon name="lock" class="w-3.5 h-3.5" />
                                        Cancelado por Incidencias
                                    </span>
                                </template>
                                <template x-if="st.status === 'not_started'">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                        Sin Iniciar
                                    </span>
                                </template>
                            </x-table.td>

                            <x-table.td align="center">
                                <template x-if="st.incidents > 0">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-mono text-xs font-bold"
                                        :class="st.incidents >= maxIncidents ? 'bg-acode-danger text-white' : 'bg-acode-warning/20 text-acode-warning-dark'"
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
                                <span class="font-mono text-sm font-bold text-acode-primary" x-text="st.score"></span>
                            </x-table.td>
                        </tr>
                    </template>
                </x-table>
            </div>
        </section>
    </div>
@endsection
