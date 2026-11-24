(() => {
    const connectionLabels = {
        connecting: 'Conectando…',
        syncing: 'Actualizando datos…',
        live: 'En vivo',
        reconnecting: 'Reconectando…',
        offline: 'Sin conexión en tiempo real',
    };

    const incidentLabels = {
        app_left: 'Salida de la aplicación',
        multi_window: 'Uso de varias ventanas',
        focus_lost: 'Pérdida del foco de la ventana',
        tab_switch: 'Cambio de pestaña',
        app_background: 'Aplicación en segundo plano',
        split_screen: 'Uso de pantalla dividida',
        camera_lost: 'Pérdida de la señal de cámara',
        camera_covered: 'Cámara cubierta',
        disconnect: 'Desconexión',
        screenshot_attempt: 'Intento de captura de pantalla',
    };

    const monitoredEvents = new Set([
        'exam.attempt.started',
        'exam.attempt.submitted',
        'exam.incident.reported',
    ]);
    const snapshotInterval = 15000;

    function readConfiguration() {
        try {
            return JSON.parse(document.getElementById('exam-monitor-data')?.textContent);
        } catch {
            return null;
        }
    }

    function createMonitor() {
        const config = readConfiguration();
        const initial = config?.snapshot;
        const channel = `private-exam.${config?.examId}`;
        const incidentDetails = new Map();
        let socket = null;
        let subscribed = false;
        let destroyed = false;
        let reconnectDelay = 5000;
        let reconnectTimer;
        let handshakeTimer;
        let heartbeatTimer;
        let pongTimer;
        let pollTimer;
        let snapshotTimer;
        let authRequest;
        let snapshotRequest;
        let snapshotQueued = false;
        let handleOnline;
        let handleOffline;
        let handleResume;
        let lastSnapshotAt = Date.now();

        return {
            ready: false,
            counts: initial?.counts ?? {},
            students: initial?.students ?? [],
            maxIncidents: initial?.maxIncidents ?? null,
            observedAt: initial?.observedAt ?? null,
            observedAtLabel: initial?.observedAtLabel ?? '',
            searchQuery: config?.filters?.search ?? '',
            statusFilter: config?.filters?.status ?? 'all',
            connectionState: 'connecting',
            snapshotUnavailable: false,
            alerts: [],
            announcement: '',
            criticalAnnouncement: '',

            get connectionLabel() {
                return connectionLabels[this.connectionState];
            },

            get connectionMessage() {
                if (this.connectionState === 'live' || !this.observedAt) return '';

                const time = this.observedAtLabel || this.observedAt.slice(11, 19);
                return this.snapshotUnavailable
                    ? `Se muestran los datos de las ${time}.`
                    : `Última actualización a las ${time}.`;
            },

            get hasFilters() {
                return this.searchQuery.trim() !== '' || this.statusFilter !== 'all';
            },

            get filteredStudents() {
                const term = this.searchQuery.trim().toLocaleLowerCase('es');
                return this.students.filter((student) => {
                    const matchesStatus = this.statusFilter === 'all' || student.status === this.statusFilter;
                    return matchesStatus && (!term || student.name.toLocaleLowerCase('es').includes(term)
                        || student.email.toLocaleLowerCase('es').includes(term));
                });
            },

            init() {
                if (!initial || !config.snapshotUrl) return;

                this.ready = true;
                this.connect();
                pollTimer = setInterval(() => this.refreshSnapshot(), snapshotInterval);
                handleOnline = () => this.retry();
                handleOffline = () => {
                    if (socket) this.reconnect(socket);
                    this.connectionState = 'offline';
                    this.snapshotUnavailable = true;
                };
                handleResume = () => {
                    if (document.visibilityState !== 'visible' || Date.now() - lastSnapshotAt < snapshotInterval) return;

                    if (subscribed) this.connectionState = 'syncing';
                    this.retry();
                };
                window.addEventListener('online', handleOnline);
                window.addEventListener('offline', handleOffline);
                window.addEventListener('pageshow', handleResume);
                document.addEventListener('visibilitychange', handleResume);
            },

            destroy() {
                destroyed = true;
                clearTimeout(reconnectTimer);
                clearTimeout(snapshotTimer);
                clearInterval(pollTimer);
                if (handleOnline) {
                    window.removeEventListener('online', handleOnline);
                    window.removeEventListener('offline', handleOffline);
                    window.removeEventListener('pageshow', handleResume);
                    document.removeEventListener('visibilitychange', handleResume);
                }
                snapshotRequest?.abort();
                this.releaseSocket();
            },

            clearFilters() {
                this.searchQuery = '';
                this.statusFilter = 'all';
                this.$nextTick(() => this.$refs.monitorSearch?.focus());
            },

            focusResults() {
                this.$nextTick(() => {
                    const target = this.filteredStudents.length ? this.$refs.monitorResults : this.$refs.monitorEmptyResults;
                    target?.focus();
                });
            },

            incidentLimitReached(student) {
                return student.status === 'in_progress' && this.maxIncidents !== null
                    && student.incidents > 0 && student.incidents >= this.maxIncidents;
            },

            incidentCount(student) {
                return this.maxIncidents === null ? String(student.incidents) : `${student.incidents} / ${this.maxIncidents}`;
            },

            removeAlert(id, event) {
                const button = event?.currentTarget;
                const restoreFocus = button && document.activeElement === button;
                const buttons = this.$refs.monitorAlerts?.querySelectorAll('[data-monitor-alert-dismiss]') ?? [];
                const index = Array.from(buttons).indexOf(button);
                const next = buttons[index + 1] ?? buttons[index - 1];
                this.alerts = this.alerts.filter((alert) => alert.id !== id);
                if (restoreFocus) {
                    this.$nextTick(() => {
                        const target = next?.isConnected ? next : this.$refs.monitorSearch;
                        target?.focus();
                    });
                }
            },

            retry() {
                clearTimeout(reconnectTimer);
                if (!subscribed) {
                    this.releaseSocket();
                    this.connect();
                }
                this.refreshSnapshot();
            },

            connect() {
                const realtime = config.realtime;
                if (realtime?.enabled === false || !realtime?.key || typeof WebSocket !== 'function') {
                    this.connectionState = 'offline';
                    return;
                }

                try {
                    const protocol = realtime.useTls || window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                    const endpoint = new URL(`${protocol}//${realtime.host || window.location.hostname}`);
                    endpoint.port = realtime.port;
                    endpoint.pathname = `/app/${encodeURIComponent(realtime.key)}`;
                    endpoint.search = 'protocol=7&client=js&version=8.4.0-reverb';
                    const connection = new WebSocket(endpoint);
                    socket = connection;
                    handshakeTimer = setTimeout(() => this.reconnect(connection), 10000);
                    connection.onmessage = (event) => this.receive(connection, event);
                    connection.onerror = () => this.reconnect(connection);
                    connection.onclose = () => this.reconnect(connection);
                } catch {
                    this.reconnect(null);
                }
            },

            releaseSocket() {
                clearTimeout(handshakeTimer);
                clearTimeout(pongTimer);
                clearInterval(heartbeatTimer);
                authRequest?.abort();
                authRequest = null;
                const connection = socket;
                socket = null;
                subscribed = false;
                connection?.close();
            },

            reconnect(connection) {
                if (socket !== connection || destroyed) return;

                this.releaseSocket();
                this.connectionState = 'reconnecting';
                reconnectTimer = setTimeout(() => this.connect(), reconnectDelay);
                reconnectDelay = Math.min(reconnectDelay * 2, 30000);
                this.queueSnapshot();
            },

            async authorize(connection, socketId) {
                const request = new AbortController();
                authRequest = request;
                try {
                    const response = await fetch(config.authUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({socket_id: socketId, channel_name: channel}),
                        signal: request.signal,
                    });
                    if (!response.ok) throw new Error('Subscription authorization failed');

                    const {auth} = await response.json();
                    if (socket === connection) {
                        connection.send(JSON.stringify({event: 'pusher:subscribe', data: {auth, channel}}));
                    }
                } catch {
                    this.reconnect(connection);
                } finally {
                    if (authRequest === request) authRequest = null;
                }
            },

            receive(connection, event) {
                if (socket !== connection) return;

                clearTimeout(pongTimer);
                let message;
                let data;
                try {
                    message = JSON.parse(event.data);
                    data = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
                } catch {
                    return;
                }

                if (message.event === 'pusher:connection_established' && data?.socket_id && !authRequest && !subscribed) {
                    this.authorize(connection, data.socket_id);
                } else if (message.event === 'pusher_internal:subscription_succeeded' && message.channel === channel) {
                    if (subscribed) return;

                    subscribed = true;
                    reconnectDelay = 5000;
                    clearTimeout(handshakeTimer);
                    this.connectionState = 'syncing';
                    this.queueSnapshot();
                    heartbeatTimer = setInterval(() => {
                        if (socket !== connection || connection.readyState !== WebSocket.OPEN) return;

                        connection.send(JSON.stringify({event: 'pusher:ping', data: {}}));
                        pongTimer = setTimeout(() => this.reconnect(connection), 10000);
                    }, 30000);
                } else if (message.event === 'pusher:ping') {
                    connection.send(JSON.stringify({event: 'pusher:pong', data: {}}));
                } else if (['pusher:error', 'pusher:subscription_error', 'pusher_internal:subscription_error'].includes(message.event)) {
                    this.reconnect(connection);
                } else if (subscribed && message.channel === channel && monitoredEvents.has(message.event)) {
                    if (message.event === 'exam.incident.reported' && data?.attempt_id != null) {
                        incidentDetails.set(`${data.attempt_id}:${data.incident_count}`, incidentLabels[data.incident_type]);
                    }
                    this.queueSnapshot();
                }
            },

            queueSnapshot() {
                if (snapshotTimer != null) return;

                snapshotTimer = setTimeout(() => {
                    snapshotTimer = null;
                    this.refreshSnapshot();
                }, 100);
            },

            announce(region, notices) {
                if (!notices.length) return;

                const text = notices.map((notice) => notice.text).join(' ');
                if (this[region] !== text) {
                    this[region] = text;
                    return;
                }

                // Two attempts can produce the same words. Reset the live region before repeating them.
                this[region] = '';
                this.$nextTick(() => {
                    if (this[region] === '') this[region] = text;
                });
            },

            async refreshSnapshot() {
                if (destroyed) return;
                if (snapshotRequest) {
                    snapshotQueued = true;
                    return;
                }

                const confirmedSocket = subscribed ? socket : null;
                const request = new AbortController();
                snapshotRequest = request;
                const timeout = setTimeout(() => request.abort(), 8000);

                try {
                    const response = await fetch(config.snapshotUrl, {
                        headers: {Accept: 'application/json'},
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: request.signal,
                    });
                    if (!response.ok) throw new Error('Snapshot unavailable');

                    const snapshot = await response.json();
                    if (!Array.isArray(snapshot.students) || !snapshot.counts || !snapshot.observedAt) {
                        throw new Error('Invalid monitor snapshot');
                    }
                    if (destroyed) return;

                    this.applySnapshot(snapshot);
                    lastSnapshotAt = Date.now();
                    this.snapshotUnavailable = false;
                    if (confirmedSocket && socket === confirmedSocket) this.connectionState = 'live';
                } catch {
                    if (destroyed) return;

                    this.snapshotUnavailable = true;
                    if (this.connectionState === 'live' || this.connectionState === 'syncing') {
                        this.connectionState = 'offline';
                    }
                } finally {
                    clearTimeout(timeout);
                    snapshotRequest = null;
                    if (snapshotQueued && !destroyed) {
                        snapshotQueued = false;
                        this.queueSnapshot();
                    }
                }
            },

            applySnapshot(snapshot) {
                const previousStudents = new Map(this.students.map((student) => [student.id, student]));
                const notices = [];
                this.maxIncidents = snapshot.maxIncidents;

                for (const student of snapshot.students) {
                    const previous = previousStudents.get(student.id);
                    const sameAttempt = previous && previous.attempt_id === student.attempt_id;
                    const incidentAmount = `${student.incidents} ${student.incidents === 1 ? 'incidencia' : 'incidencias'}`;
                    const key = `${student.attempt_id}:${student.incidents}`;
                    if (student.status === 'cancelled_by_incident' && (!sameAttempt || previous.status !== student.status)) {
                        const text = `Intento cancelado por incidencias. ${student.name}, ${incidentAmount}.`;
                        notices.push({id: student.attempt_id, type: 'danger', text});
                    } else if (student.incidents > (sameAttempt ? previous.incidents : 0)) {
                        const detail = incidentDetails.get(key) ?? 'Incidente reportado';
                        const warning = this.incidentLimitReached(student) ? ' Límite de incidencias alcanzado.' : '';
                        const text = `${detail}. ${student.name}, ${incidentAmount}.${warning}`;
                        notices.push({id: student.attempt_id, type: 'warning', text});
                    }
                    incidentDetails.delete(key);
                }

                if (notices.length) {
                    const updated = new Set(notices.map((notice) => notice.id));
                    const feed = [...notices, ...this.alerts.filter((alert) => !updated.has(alert.id))];
                    this.alerts = [...feed.filter((alert) => alert.type === 'danger'), ...feed.filter((alert) => alert.type === 'warning').slice(0, 5)];
                }
                const critical = notices.filter((notice) => notice.type === 'danger');
                const routine = notices.filter((notice) => notice.type === 'warning');
                this.announce('criticalAnnouncement', critical);
                this.announce('announcement', routine);

                const active = document.activeElement;
                const hasResults = this.filteredStudents.length > 0;
                const region = hasResults ? this.$refs.monitorResults : this.$refs.monitorEmptyResults;
                this.students = snapshot.students;
                this.counts = snapshot.counts;
                this.observedAt = snapshot.observedAt;
                this.observedAtLabel = snapshot.observedAtLabel;

                if (region?.contains(active) && hasResults !== (this.filteredStudents.length > 0)) this.focusResults();
                if (incidentDetails.size > 100) incidentDetails.clear();
            },
        };
    }

    document.addEventListener('alpine:init', () => Alpine.data('examMonitor', createMonitor));
})();
