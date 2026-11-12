<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Horarios y disponibilidad</h1>
    </x-slot>

    @php
        $hasDayOffError = $errors->has('date') || $errors->has('reason');
        $initialTab = $hasDayOffError ? 'exceptions' : 'weekly';
        $initialScope = ! $hasDayOffError && old('barber_id') ? (string) old('barber_id') : 'general';
    @endphp

    <script>
        window.availabilityEditor = function (config) {
            const emptyWeek = () => {
                const week = {};

                config.dayOrder.forEach((day) => {
                    week[day] = { active: false, blocks: [] };
                });

                return week;
            };

            return {
                scope: config.initialScope || 'general',
                tab: config.initialTab || 'weekly',
                barbers: config.barbers || [],
                dayOrder: config.dayOrder || [1, 2, 3, 4, 5, 6, 0],
                dayNames: config.dayNames || {},
                dayShort: config.dayShort || {},
                schedules: config.schedules || {},
                drafts: {},
                week: emptyWeek(),
                inherited: false,
                duplicatingDay: null,
                copiedAll: false,
                saveState: 'idle',
                saveError: '',
                saveTimer: null,
                saveInFlight: false,
                pendingPayload: null,
                baseline: '',
                durations: config.durations || {},
                durationState: 'idle',
                durationError: '',
                durationTimer: null,
                durationInFlight: false,
                durationPending: null,
                durationBaseline: '',

                init() {
                    this.durationBaseline = JSON.stringify(this.durations);
                    this.loadScope();
                    this.$watch('scope', () => this.loadScope());
                    this.$watch('week', () => this.onWeekChanged());
                    this.$watch('durations', () => this.onDurationsChanged());
                },

                get currentKey() {
                    return this.scope === 'general' ? 'general' : String(this.scope);
                },

                get isGeneral() {
                    return this.scope === 'general';
                },

                get currentBarber() {
                    return this.barbers.find((barber) => String(barber.id) === String(this.scope)) || null;
                },

                get saveStatusText() {
                    if (this.saveState === 'saving') {
                        return 'Guardando...';
                    }

                    if (this.saveState === 'error') {
                        return this.saveError || 'No se pudo guardar';
                    }

                    if (this.saveState === 'saved') {
                        return 'Guardado';
                    }

                    return 'Guardado automático';
                },

                get saveStatusClass() {
                    if (this.saveState === 'saving') {
                        return 'bg-amber-50 text-amber-700';
                    }

                    if (this.saveState === 'error') {
                        return 'bg-rose-50 text-rose-600';
                    }

                    if (this.saveState === 'saved') {
                        return 'bg-emerald-50 text-emerald-700';
                    }

                    return 'bg-slate-50 text-slate-500';
                },

                get durationStatusText() {
                    if (this.durationState === 'saving') {
                        return 'Guardando...';
                    }

                    if (this.durationState === 'error') {
                        return this.durationError || 'No se pudo guardar';
                    }

                    if (this.durationState === 'saved') {
                        return 'Guardado';
                    }

                    return 'Guardado automático';
                },

                get durationStatusClass() {
                    if (this.durationState === 'saving') {
                        return 'bg-amber-50 text-amber-700';
                    }

                    if (this.durationState === 'error') {
                        return 'bg-rose-50 text-rose-600';
                    }

                    if (this.durationState === 'saved') {
                        return 'bg-emerald-50 text-emerald-700';
                    }

                    return 'bg-slate-50 text-slate-500';
                },

                loadScope() {
                    this.flushPendingSave();

                    const key = this.currentKey;

                    if (! this.drafts[key]) {
                        this.drafts[key] = this.buildWeek(key);
                    }

                    const draft = this.drafts[key];

                    this.baseline = JSON.stringify(draft.week);
                    this.week = draft.week;
                    this.inherited = draft.inherited;
                    this.duplicatingDay = null;
                },

                onWeekChanged() {
                    const serialized = JSON.stringify(this.week);

                    if (serialized === this.baseline) {
                        if (this.pendingPayload && this.pendingPayload.key === this.currentKey) {
                            this.pendingPayload = null;
                            clearTimeout(this.saveTimer);

                            if (! this.saveInFlight) {
                                this.saveState = 'idle';
                            }
                        }

                        return;
                    }

                    if (this.weekHasErrors()) {
                        this.pendingPayload = null;
                        clearTimeout(this.saveTimer);
                        this.saveState = 'error';
                        this.saveError = 'Revisa las horas de los turnos.';

                        return;
                    }

                    this.pendingPayload = {
                        key: this.currentKey,
                        barberId: this.isGeneral ? null : this.scope,
                        days: serialized,
                    };

                    this.saveState = 'saving';
                    this.saveError = '';
                    clearTimeout(this.saveTimer);
                    this.saveTimer = setTimeout(() => this.drainSaves(), 700);
                },

                weekHasErrors() {
                    return this.dayOrder.some((day) => {
                        const state = this.week[day];

                        if (! state.active) {
                            return false;
                        }

                        const ranges = [];

                        for (const block of state.blocks) {
                            if (! block.start || ! block.end) {
                                return true;
                            }

                            const start = this.toMinutes(block.start);
                            const end = this.toMinutes(block.end);

                            if (end <= start) {
                                return true;
                            }

                            ranges.push([start, end]);
                        }

                        ranges.sort((left, right) => left[0] - right[0]);

                        for (let index = 1; index < ranges.length; index += 1) {
                            if (ranges[index][0] < ranges[index - 1][1]) {
                                return true;
                            }
                        }

                        return false;
                    });
                },

                flushPendingSave() {
                    clearTimeout(this.saveTimer);
                    this.saveTimer = null;

                    if (this.pendingPayload) {
                        this.drainSaves();
                    }
                },

                async drainSaves() {
                    if (this.saveInFlight) {
                        return;
                    }

                    this.saveInFlight = true;

                    while (this.pendingPayload) {
                        const payload = this.pendingPayload;
                        this.pendingPayload = null;

                        await this.sendSave(payload);
                    }

                    this.saveInFlight = false;
                },

                async sendSave(payload) {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    this.saveState = 'saving';
                    this.saveError = '';

                    try {
                        const response = await fetch(config.syncUrl, {
                            method: 'POST',
                            keepalive: true,
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                barber_id: payload.barberId,
                                days: payload.days,
                            }),
                        });

                        if (! response.ok) {
                            let message = 'No se pudieron guardar los cambios.';

                            if (response.status === 422) {
                                const data = await response.json().catch(() => ({}));
                                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                                message = firstError || data.message || 'Revisa las horas de los turnos.';
                            } else if (response.status === 419) {
                                message = 'La sesión expiró. Recarga la página.';
                            }

                            this.saveState = 'error';
                            this.saveError = message;

                            return;
                        }

                        const draft = this.drafts[payload.key];

                        if (draft) {
                            draft.inherited = false;
                        }

                        this.schedules[payload.key] = JSON.parse(payload.days);

                        if (payload.key === 'general') {
                            Object.keys(this.drafts).forEach((key) => {
                                if (key !== 'general' && this.drafts[key].inherited) {
                                    delete this.drafts[key];
                                }
                            });
                        }

                        if (payload.key === this.currentKey) {
                            this.inherited = false;

                            if (! this.pendingPayload) {
                                this.baseline = payload.days;
                            }
                        }

                        this.saveError = '';

                        if (this.pendingPayload) {
                            this.saveState = 'saving';
                        } else {
                            this.saveState = 'saved';

                            setTimeout(() => {
                                if (this.saveState === 'saved') {
                                    this.saveState = 'idle';
                                }
                            }, 2200);
                        }
                    } catch (error) {
                        this.saveState = 'error';
                        this.saveError = 'No se pudo guardar. Revisa tu conexión.';
                    }
                },

                onDurationsChanged() {
                    const serialized = JSON.stringify(this.durations);

                    if (serialized === this.durationBaseline) {
                        if (this.durationPending) {
                            this.durationPending = null;
                            clearTimeout(this.durationTimer);

                            if (! this.durationInFlight) {
                                this.durationState = 'idle';
                            }
                        }

                        return;
                    }

                    if (this.durationsHaveErrors()) {
                        this.durationPending = null;
                        clearTimeout(this.durationTimer);
                        this.durationState = 'error';
                        this.durationError = 'La duración debe estar entre 5 y 480 minutos.';

                        return;
                    }

                    this.durationPending = serialized;
                    this.durationState = 'saving';
                    this.durationError = '';
                    clearTimeout(this.durationTimer);
                    this.durationTimer = setTimeout(() => this.drainDurationSaves(), 700);
                },

                durationsHaveErrors() {
                    return Object.values(this.durations).some((value) => {
                        const minutes = Number(value);

                        return ! Number.isFinite(minutes) || minutes < 5 || minutes > 480;
                    });
                },

                async drainDurationSaves() {
                    if (this.durationInFlight) {
                        return;
                    }

                    this.durationInFlight = true;

                    while (this.durationPending) {
                        const payload = this.durationPending;
                        this.durationPending = null;

                        await this.sendDurationSave(payload);
                    }

                    this.durationInFlight = false;
                },

                async sendDurationSave(payload) {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    this.durationState = 'saving';
                    this.durationError = '';

                    try {
                        const response = await fetch(config.durationsUrl, {
                            method: 'POST',
                            keepalive: true,
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                durations: JSON.parse(payload),
                            }),
                        });

                        if (! response.ok) {
                            let message = 'No se pudieron guardar las duraciones.';

                            if (response.status === 422) {
                                const data = await response.json().catch(() => ({}));
                                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                                message = firstError || data.message || 'Revisa las duraciones.';
                            } else if (response.status === 419) {
                                message = 'La sesión expiró. Recarga la página.';
                            }

                            this.durationState = 'error';
                            this.durationError = message;

                            return;
                        }

                        if (! this.durationPending) {
                            this.durationBaseline = payload;
                        }

                        this.durationError = '';

                        if (this.durationPending) {
                            this.durationState = 'saving';
                        } else {
                            this.durationState = 'saved';

                            setTimeout(() => {
                                if (this.durationState === 'saved') {
                                    this.durationState = 'idle';
                                }
                            }, 2200);
                        }
                    } catch (error) {
                        this.durationState = 'error';
                        this.durationError = 'No se pudo guardar. Revisa tu conexión.';
                    }
                },

                buildWeek(key) {
                    let source = this.schedules[key];
                    let inherited = false;

                    if (! source) {
                        source = this.schedules.general || {};
                        inherited = key !== 'general';
                    }

                    const week = emptyWeek();

                    this.dayOrder.forEach((day) => {
                        const blocks = (source[day] || []).map((block) => ({
                            start: this.normalizeTime(block.start),
                            end: this.normalizeTime(block.end),
                        }));

                        week[day] = {
                            active: blocks.length > 0,
                            blocks,
                        };
                    });

                    return { week, inherited };
                },

                normalizeTime(value) {
                    return String(value || '').slice(0, 5);
                },

                toMinutes(value) {
                    const [hours, minutes] = String(value || '0:0').split(':').map(Number);

                    return (hours || 0) * 60 + (minutes || 0);
                },

                formatMinutes(total) {
                    const safe = Math.max(0, Math.min(total, 23 * 60 + 59));

                    return String(Math.floor(safe / 60)).padStart(2, '0') + ':' + String(safe % 60).padStart(2, '0');
                },

                blockMinutes(block) {
                    return Math.max(0, this.toMinutes(block.end) - this.toMinutes(block.start));
                },

                humanDuration(minutes) {
                    const safe = Math.max(0, Number(minutes) || 0);
                    const hours = Math.floor(safe / 60);
                    const rest = safe % 60;

                    if (! hours) {
                        return rest + ' min';
                    }

                    return rest ? hours + ' h ' + rest + ' min' : hours + ' h';
                },

                dayMinutes(day) {
                    return this.week[day].blocks.reduce((total, block) => total + this.blockMinutes(block), 0);
                },

                daySummary(day) {
                    const count = this.week[day].blocks.length;
                    const label = count === 1 ? '1 turno' : count + ' turnos';

                    return label + ' • ' + this.humanDuration(this.dayMinutes(day)) + ' de atención';
                },

                blockLabel(block) {
                    const start = this.toMinutes(block.start);

                    if (start < 12 * 60) {
                        return 'Mañana';
                    }

                    if (start < 18 * 60) {
                        return 'Tarde';
                    }

                    return 'Noche';
                },

                pauses(day) {
                    const blocks = this.week[day].blocks
                        .filter((block) => this.toMinutes(block.end) > this.toMinutes(block.start))
                        .slice()
                        .sort((left, right) => this.toMinutes(left.start) - this.toMinutes(right.start));

                    const result = [];

                    for (let index = 1; index < blocks.length; index += 1) {
                        const previousEnd = this.toMinutes(blocks[index - 1].end);
                        const currentStart = this.toMinutes(blocks[index].start);

                        if (currentStart > previousEnd) {
                            result.push({
                                start: this.formatMinutes(previousEnd),
                                end: this.formatMinutes(currentStart),
                                minutes: currentStart - previousEnd,
                            });
                        }
                    }

                    return result;
                },

                toggleDay(day) {
                    this.week[day].active = ! this.week[day].active;

                    if (this.week[day].active && ! this.week[day].blocks.length) {
                        this.week[day].blocks.push({ start: '09:00', end: '13:00' });
                    }

                    if (! this.week[day].active) {
                        this.duplicatingDay = null;
                    }
                },

                addBlock(day) {
                    const blocks = this.week[day].blocks;
                    let start = '15:00';
                    let end = '19:00';

                    if (blocks.length) {
                        const latestEnd = blocks.reduce((max, block) => Math.max(max, this.toMinutes(block.end)), 0);
                        start = this.formatMinutes(latestEnd);
                        end = this.formatMinutes(Math.min(latestEnd + 240, 23 * 60 + 59));
                    }

                    blocks.push({ start, end });
                    this.week[day].active = true;
                },

                removeBlock(day, index) {
                    this.week[day].blocks.splice(index, 1);

                    if (! this.week[day].blocks.length) {
                        this.week[day].active = false;
                    }
                },

                copyDayTo(source, targets) {
                    const origin = this.week[source];

                    if (! origin) {
                        return;
                    }

                    targets.forEach((target) => {
                        this.week[target] = {
                            active: origin.active,
                            blocks: origin.blocks.map((block) => ({ start: block.start, end: block.end })),
                        };
                    });
                },

                copyFirstToAll() {
                    const source = this.dayOrder.find((day) => this.week[day].active && this.week[day].blocks.length);

                    if (! source) {
                        return;
                    }

                    this.copyDayTo(source, this.dayOrder.filter((day) => day !== source));
                    this.copiedAll = true;
                    setTimeout(() => { this.copiedAll = false; }, 1800);
                },

                avatarTone(index) {
                    const tones = [
                        'bg-brand-100 text-brand-700',
                        'bg-slate-200 text-slate-700',
                        'bg-amber-100 text-amber-700',
                        'bg-emerald-100 text-emerald-700',
                        'bg-sky-100 text-sky-700',
                        'bg-violet-100 text-violet-700',
                    ];

                    return tones[index % tones.length];
                },
            };
        };
    </script>

    <div class="mx-auto max-w-3xl space-y-4 pb-4"
         x-data="availabilityEditor({
            initialScope: @js($initialScope),
            initialTab: @js($initialTab),
            barbers: @js($barbersData),
            schedules: @js($schedulesByScope),
            durations: @js($serviceDurations),
            dayOrder: @js($dayOrder),
            dayNames: @js($days),
            dayShort: @js($dayShort),
            syncUrl: @js(route('appointments.schedules.sync')),
            durationsUrl: @js(route('appointments.services.durations.update'))
         })">
        <!-- Encabezado y selector de alcance -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-2">
                    <a href="{{ route('appointments.index') }}" title="Volver a citas"
                       class="-ml-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                        <x-icon name="arrow-left" class="h-5 w-5" />
                    </a>
                    <div class="min-w-0">
                        <h2 class="text-lg font-extrabold leading-tight tracking-tight text-slate-800">Disponibilidad</h2>
                        <p class="truncate text-xs text-slate-400">{{ $shopName }} &amp; Barberos</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" title="Historial de cambios"
                            class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <x-icon name="history" class="h-4 w-4" />
                    </button>
                    <button type="button" title="Ayuda"
                            class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <x-icon name="help" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="mt-4 border-t border-slate-100 pt-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Configurar para</span>
                    <span class="inline-flex min-w-0 items-center gap-1 text-xs font-medium text-brand-600">
                        <x-icon name="check-circle" class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate" x-text="isGeneral ? 'Horario del local' : (currentBarber ? currentBarber.name : 'Barbero')"></span>
                    </span>
                </div>

                <div class="flex gap-2 overflow-x-auto no-scrollbar py-0.5">
                    <button type="button" @click="scope = 'general'"
                            :class="isGeneral ? 'bg-slate-800 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="flex flex-shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs transition-all">
                        <x-icon name="store" class="h-3.5 w-3.5" />
                        <span>Todo el local</span>
                        <span x-show="isGeneral" class="h-1.5 w-1.5 rounded-full bg-brand-400"></span>
                    </button>

                    <template x-for="(barber, index) in barbers" :key="barber.id">
                        <button type="button" @click="scope = String(barber.id)"
                                :class="scope === String(barber.id) ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                class="flex flex-shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs transition-all">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                                  :class="avatarTone(index)" x-text="barber.initials"></span>
                            <span x-text="barber.name"></span>
                        </button>
                    </template>

                    @if ($barbers->isEmpty())
                        <span class="flex-shrink-0 rounded-full border border-dashed border-slate-200 px-3 py-1.5 text-xs text-slate-400">
                            Sin barberos registrados
                        </span>
                    @endif
                </div>
            </div>
        </section>

        <!-- Notificación contextual -->
        <div class="flex items-start gap-2.5 rounded-xl border border-amber-200/70 bg-amber-50/80 p-3">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
            <p class="text-xs leading-snug text-slate-700">
                El horario general define la apertura del local. Si un barbero necesita un turno especial o un día libre personal, selecciónalo en la barra superior.
            </p>
        </div>

        <!-- Pestañas -->
        <div class="flex border-b border-slate-200">
            <button type="button" @click="tab = 'weekly'"
                    :class="tab === 'weekly' ? 'border-brand-500 font-semibold text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="flex flex-1 items-center justify-center gap-1.5 border-b-2 pb-2.5 text-sm transition-colors">
                <x-icon name="calendar" class="h-4 w-4" />
                <span>Semanal (7 días)</span>
            </button>
            <button type="button" @click="tab = 'exceptions'"
                    :class="tab === 'exceptions' ? 'border-brand-500 font-semibold text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="flex flex-1 items-center justify-center gap-1.5 border-b-2 pb-2.5 text-sm transition-colors">
                <x-icon name="calendar-off" class="h-4 w-4" />
                <span>Días libres y feriados</span>
                @if ($daysOff->count())
                    <span class="rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $daysOff->count() }}</span>
                @endif
            </button>
        </div>

        <!-- SECCIÓN 1: HORARIOS SEMANALES -->
        <div x-show="tab === 'weekly'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <div>
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Horario semanal</span>
                    <span class="text-xs text-slate-400">Jornadas regulares</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5 rounded-full px-2 py-1 text-[11px] font-semibold transition-colors"
                          :class="saveStatusClass">
                        <x-icon name="refresh" class="h-3 w-3 animate-spin" x-show="saveState === 'saving'" x-cloak />
                        <x-icon name="check-circle" class="h-3 w-3" x-show="saveState === 'saved'" x-cloak />
                        <x-icon name="alert" class="h-3 w-3" x-show="saveState === 'error'" x-cloak />
                        <x-icon name="save" class="h-3 w-3" x-show="saveState === 'idle'" x-cloak />
                        <span x-text="saveStatusText"></span>
                    </span>
                    <button type="button" @click="copyFirstToAll()"
                            class="flex items-center gap-1 text-xs font-semibold text-brand-600 transition hover:text-brand-700">
                        <x-icon name="copy" class="h-3.5 w-3.5" />
                        <span x-text="copiedAll ? 'Copiado' : 'Copiar a todos'"></span>
                    </button>
                </div>
            </div>

            <div x-show="inherited && ! isGeneral" x-cloak
                 class="flex items-start gap-2 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2.5 text-[11px] leading-snug text-brand-700">
                <x-icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                <span>Este barbero todavía usa el horario general. Se muestra como plantilla; al guardar se creará su horario propio.</span>
            </div>

            <template x-for="day in dayOrder" :key="day">
                <article class="rounded-xl border p-3.5 transition-all"
                         :class="week[day].active ? 'border-slate-200 bg-white shadow-sm' : 'border-dashed border-slate-200 bg-slate-50/60 opacity-80'">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                 :class="week[day].active ? 'bg-brand-100 text-brand-700' : 'bg-slate-200 text-slate-500'"
                                 x-text="dayShort[day]"></div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold" :class="week[day].active ? 'text-slate-800' : 'text-slate-500'" x-text="dayNames[day]"></h3>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                          :class="week[day].active ? 'border-emerald-200/60 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'"
                                          x-text="week[day].active ? 'Abierto' : 'Cerrado'"></span>
                                </div>
                                <p class="text-xs text-slate-500"
                                   x-text="week[day].active ? daySummary(day) : 'Sin citas disponibles'"></p>
                            </div>
                        </div>

                        <label class="relative inline-flex flex-shrink-0 cursor-pointer items-center">
                            <input type="checkbox" class="peer sr-only" :checked="week[day].active" @change="toggleDay(day)" />
                            <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-brand-500 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none"></div>
                        </label>
                    </div>

                    <div x-show="week[day].active" x-cloak class="mt-3.5 space-y-2 border-t border-slate-100 pt-3">
                        <template x-for="(block, index) in week[day].blocks" :key="day + '-' + index">
                            <div class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5">
                                <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-500" />
                                <input type="time" x-model="block.start"
                                       class="h-9 w-[5.75rem] rounded-lg border-slate-200 bg-white px-2 text-sm font-medium text-slate-800 focus:border-brand-500 focus:ring-brand-500" />
                                <span class="text-xs text-slate-400">a</span>
                                <input type="time" x-model="block.end"
                                       class="h-9 w-[5.75rem] rounded-lg border-slate-200 bg-white px-2 text-sm font-medium text-slate-800 focus:border-brand-500 focus:ring-brand-500" />
                                <span class="ml-1 hidden text-[11px] text-slate-400 sm:inline" x-text="blockLabel(block)"></span>
                                <button type="button" @click="removeBlock(day, index)" title="Eliminar turno"
                                        class="ml-auto rounded p-1 text-slate-400 transition hover:text-rose-500">
                                    <x-icon name="trash" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </template>

                        <template x-for="pause in pauses(day)" :key="day + '-pause-' + pause.start">
                            <div class="flex items-center gap-2 px-1 text-[11px] text-slate-500">
                                <x-icon name="coffee" class="h-3.5 w-3.5 text-amber-600" />
                                <span x-text="'Pausa: ' + pause.start + ' - ' + pause.end + ' (' + humanDuration(pause.minutes) + ')'"></span>
                            </div>
                        </template>

                        <div class="flex items-center justify-between gap-2 pt-1">
                            <button type="button" @click="addBlock(day)"
                                    class="flex items-center gap-1 text-xs font-semibold text-brand-600 transition hover:text-brand-700">
                                <x-icon name="plus" class="h-3.5 w-3.5" />
                                Añadir bloque
                            </button>
                            <button type="button" @click="duplicatingDay = duplicatingDay === day ? null : day"
                                    class="flex items-center gap-1 text-xs text-slate-500 transition hover:text-slate-700">
                                <x-icon name="copy" class="h-3 w-3" />
                                Duplicar a...
                            </button>
                        </div>

                        <div x-show="duplicatingDay === day" x-cloak class="flex flex-wrap gap-1.5 rounded-lg bg-slate-50 p-2">
                            <template x-for="target in dayOrder.filter((targetDay) => targetDay !== day)" :key="day + '-to-' + target">
                                <button type="button" @click="copyDayTo(day, [target]); duplicatingDay = null"
                                        class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-brand-300 hover:text-brand-700"
                                        x-text="dayNames[target]"></button>
                            </template>
                        </div>
                    </div>
                </article>
            </template>
        </div>

        <!-- SECCIÓN 2: DÍAS LIBRES Y EXCEPCIONES -->
        <section x-show="tab === 'exceptions'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between gap-3 pt-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Fechas especiales</span>
                <span class="text-xs text-slate-400">{{ $daysOff->count() }} registradas</span>
            </div>

            <form method="POST" action="{{ route('appointments.days-off.store') }}"
                  class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                @csrf
                <div class="flex items-center gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                        <x-icon name="calendar-x" class="h-3.5 w-3.5" />
                    </div>
                    <h4 class="text-sm font-bold text-slate-800">Bloquear día completo</h4>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-input-label for="date" value="Fecha" class="!text-[11px] !font-semibold !text-slate-400" />
                        <input id="date" name="date" type="date" required min="{{ now()->toDateString() }}"
                               value="{{ old('date', now()->toDateString()) }}"
                               class="mt-1 block h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('date')" />
                    </div>
                    <div>
                        <x-input-label for="day_off_barber_id" value="Aplica a" class="!text-[11px] !font-semibold !text-slate-400" />
                        <select id="day_off_barber_id" name="barber_id"
                                class="mt-1 block h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                            <option value="">Todo el local</option>
                            @foreach ($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(old('barber_id') == $barber->id)>{{ $barber->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('barber_id')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="reason" value="Motivo (opcional)" class="!text-[11px] !font-semibold !text-slate-400" />
                    <input id="reason" name="reason" type="text" maxlength="120" value="{{ old('reason') }}"
                           placeholder="Ej: Feriado nacional, mantenimiento, capacitación..."
                           class="mt-1 block h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    <x-input-error :messages="$errors->get('reason')" />
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-800 py-2 text-xs font-bold text-white transition hover:bg-slate-700">
                    <x-icon name="check" class="h-4 w-4" />
                    Guardar excepción
                </button>
            </form>

            <div class="space-y-2 pt-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Próximos días no laborables</span>

                @forelse ($daysOff as $dayOff)
                    @php
                        $months = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-600">
                                <span class="text-[9px] font-bold uppercase leading-none">{{ $months[(int) $dayOff->date->format('n')] }}</span>
                                <span class="text-sm font-bold leading-tight">{{ $dayOff->date->format('d') }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ $dayOff->reason ?: 'Día no laborable' }}</p>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                        {{ $dayOff->barber?->name ?? 'Todo el local' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">{{ ucfirst($dayOff->date->translatedFormat('l, j \d\e F Y')) }}</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('appointments.days-off.destroy', $dayOff) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="Eliminar excepción"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-500">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-400">
                        No hay días libres registrados.
                    </p>
                @endforelse
            </div>
        </section>

        <!-- Reglas de reserva -->
        <section class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <x-icon name="scissors" class="h-3.5 w-3.5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Reglas de reserva</h4>
                        <p class="text-xs text-slate-400">Ajusta la duración de cada servicio</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1.5 rounded-full px-2 py-1 text-[11px] font-semibold transition-colors"
                          :class="durationStatusClass">
                        <x-icon name="refresh" class="h-3 w-3 animate-spin" x-show="durationState === 'saving'" x-cloak />
                        <x-icon name="check-circle" class="h-3 w-3" x-show="durationState === 'saved'" x-cloak />
                        <x-icon name="alert" class="h-3 w-3" x-show="durationState === 'error'" x-cloak />
                        <x-icon name="save" class="h-3 w-3" x-show="durationState === 'idle'" x-cloak />
                        <span x-text="durationStatusText"></span>
                    </span>
                    <a href="{{ route('services.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">Catálogo</a>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2 rounded-lg border border-slate-200/80 bg-slate-50 px-2.5 py-2 text-[11px] text-slate-500">
                <x-icon name="clock" class="h-3.5 w-3.5 shrink-0 text-emerald-600" />
                <span>Las citas se generan cada 15 minutos según la duración de cada servicio.</span>
            </div>

            @if ($services->isEmpty())
                <p class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-xs text-slate-400">
                    No hay servicios registrados.
                </p>
            @else
                <div class="mt-3 space-y-2">
                    @foreach ($services as $service)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/80 bg-slate-50 px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-700">{{ $service->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $service->active ? 'Disponible' : 'Pausado' }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <input type="number" x-model.number="durations[{{ $service->id }}]"
                                       min="5" max="480" step="5" inputmode="numeric"
                                       class="h-9 w-20 rounded-lg border border-slate-200 bg-white px-2 text-right text-sm font-semibold text-slate-700 focus:border-brand-500 focus:ring-brand-500" />
                                <span class="text-[11px] font-medium text-slate-400">min</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

    </div>
</x-app-layout>
