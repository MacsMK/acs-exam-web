(function () {
    function addMinutes(value, minutes) {
        const date = new Date(value + 'Z');
        if (Number.isNaN(date.getTime())) return '';

        date.setUTCMinutes(date.getUTCMinutes() + minutes);
        return date.toISOString().slice(0, 16);
    }

    function findWindow(calendars, earliestStart) {
        const starts = new Set(calendars.flatMap(periods => periods.map(period =>
            period.startsAt > earliestStart ? period.startsAt : earliestStart
        )));

        for (const startsAt of [...starts].sort()) {
            const matches = calendars.map(periods => periods.reduce((best, period) => {
                if (period.startsAt <= startsAt && period.endsAt > startsAt && (!best || period.endsAt > best.endsAt)) {
                    return period;
                }
                return best;
            }, null));

            if (matches.some(period => period === null)) continue;

            return {
                startsAt,
                endsAt: matches.reduce(
                    (end, period) => period.endsAt < end ? period.endsAt : end,
                    addMinutes(startsAt, 120)
                ),
                periodNames: [...new Set(matches.map(period => period.name))].join(', '),
            };
        }

        return null;
    }

    window.examCreateForm = function (initial) {
        const clock = new Intl.DateTimeFormat('sv-SE', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: initial.timezone,
        });
        const displayDate = new Intl.DateTimeFormat('es-MX', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'UTC',
        });

        return {
            ...initial,
            saving: false,
            windowAnnouncement: '',

            init() {
                this.refreshMinimumStart();
                this.applyGroupScale();
                this.$watch('selectedGroups', () => {
                    this.refreshMinimumStart();
                    this.applyGroupScale();
                    this.windowAnnouncement = this.periodAdvice;
                });
            },

            get selectedSchedules() {
                return this.selectedGroups.map(id => this.groupScheduling[id]);
            },

            get calendars() {
                return this.selectedSchedules.map(schedule =>
                    schedule.periods.filter(period => period.endsAt > this.minimumStart)
                ).filter(periods => periods.length > 0);
            },

            get suggestedWindow() {
                const calendars = this.calendars;
                if (!calendars.length) return null;

                const earliest = findWindow(calendars, this.minimumStart);
                const preferredStart = addMinutes(this.minimumStart, 15);
                if (!earliest || earliest.startsAt >= preferredStart || earliest.endsAt <= preferredStart) return earliest;

                return findWindow(calendars, preferredStart) ?? earliest;
            },

            get periodAdvice() {
                if (!this.selectedGroups.length) return '';
                if (this.suggestedWindow) return 'Hay una ventana sugerida en la sección Ventana de aplicación.';
                if (this.calendars.length) return 'Los periodos de los grupos seleccionados no tienen fechas en común. Elige la ventana manualmente.';
                return 'Los grupos seleccionados no tienen periodos vigentes o futuros. Elige la ventana manualmente.';
            },

            get suggestedScale() {
                const groups = this.selectedSchedules;
                const scale = groups[0]?.scale;
                return scale && groups.every(group => group.scale === scale) ? scale : null;
            },

            get scaleAdvice() {
                const scale = this.suggestedScale;
                if (scale) {
                    const label = scale === '0-100' ? '0 a 100' : '0 a 10';
                    return this.gradingScale === scale
                        ? 'Coincide con la escala de los grupos seleccionados.'
                        : 'Los grupos seleccionados usan la escala de ' + label + '.';
                }

                const scales = this.selectedSchedules.map(schedule => schedule.scale);
                if (new Set(scales.filter(Boolean)).size > 1) {
                    return 'Los grupos seleccionados usan escalas distintas. Elige la escala del examen.';
                }
                return scales.some(Boolean) ? 'Hay grupos sin escala configurada. Elige la escala del examen.' : '';
            },

            applyGroupScale() {
                if (!this.scaleEdited) this.gradingScale = this.suggestedScale ?? this.initialScale;
            },

            refreshMinimumStart() {
                const now = clock.format(new Date()).replace(' ', 'T');
                if (now > this.minimumStart) this.minimumStart = now;
            },

            get minimumEnd() {
                return addMinutes(this.startsAt >= this.minimumStart ? this.startsAt : this.minimumStart, 1);
            },

            get startsInPast() {
                return Boolean(this.startsAt && this.startsAt < this.minimumStart);
            },

            get endsTooEarly() {
                return Boolean(this.endsAt && this.endsAt < this.minimumEnd);
            },

            applyPeriodWindow() {
                this.refreshMinimumStart();
                const suggestion = this.suggestedWindow;
                if (!suggestion) return;

                this.startsAt = suggestion.startsAt;
                this.endsAt = suggestion.endsAt;
                this.windowAnnouncement = 'Ventana actualizada. Inicio: ' + this.formatWindowDate(this.startsAt)
                    + '. Cierre: ' + this.formatWindowDate(this.endsAt);
            },

            applyDurationPreset(hours) {
                this.refreshMinimumStart();
                if (!this.startsAt || this.startsInPast) return;

                this.endsAt = addMinutes(this.startsAt, hours * 60);
                this.announceWindow('Cierre', this.endsAt);
            },

            formatWindowDate(value) {
                const date = new Date(value + 'Z');
                return Number.isNaN(date.getTime()) ? 'Sin definir' : displayDate.format(date);
            },

            announceWindow(label, value) {
                this.refreshMinimumStart();
                this.windowAnnouncement = label + ': ' + this.formatWindowDate(value);
            },

            submit(event) {
                if (this.saving) {
                    event.preventDefault();
                    return;
                }
                this.refreshMinimumStart();
                if (this.startsInPast || this.endsTooEarly) {
                    event.preventDefault();
                    this.$nextTick(() => event.target.reportValidity());
                    return;
                }
                this.saving = true;
            },

            get scaleLabel() {
                return this.gradingScale === '0-100' ? '0 a 100 puntos' : '0 a 10 puntos';
            },

            get groupCountLabel() {
                const count = this.selectedGroups.length;
                return count + (count === 1 ? ' grupo' : ' grupos');
            },

            get incidentCountLabel() {
                if (this.maxIncidents == null || this.maxIncidents === '') return 'Sin definir';
                return this.maxIncidents + (Number(this.maxIncidents) === 1 ? ' incidencia' : ' incidencias');
            },
        };
    };
})();
