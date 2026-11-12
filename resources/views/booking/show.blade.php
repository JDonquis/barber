@php
    $shopPhone = $company->phone ?: setting('shop_phone');
    $shopAddress = $company->address ?: setting('shop_address');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Reservar cita · {{ $shopName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="manifest" href="{{ route('booking.manifest', $company->slug) }}">
    <meta name="theme-color" content="#c01c15">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $shopName }}">
    <meta name="service-worker" content="{{ asset('reservar/sw.js') }}">
    <meta name="service-worker-scope" content="/reservar/{{ $company->slug }}">
    <link rel="icon" type="image/png" href="{{ route('booking.icon', ['company' => $company->slug, 'size' => 192]) }}">
    <link rel="apple-touch-icon" href="{{ route('booking.icon', ['company' => $company->slug, 'size' => 192]) }}">

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
    </style>

    <script>
        window.clientInstall = function () {
            return {
                deferred: null,
                installed: false,
                help: false,
                init() {
                    this.installed = window.matchMedia('(display-mode: standalone)').matches;

                    window.addEventListener('beforeinstallprompt', (event) => {
                        event.preventDefault();
                        this.deferred = event;
                    });

                    window.addEventListener('appinstalled', () => {
                        this.installed = true;
                        this.deferred = null;
                    });
                },
                async install() {
                    if (this.deferred) {
                        this.deferred.prompt();
                        await this.deferred.userChoice;
                        this.deferred = null;
                        return;
                    }

                    this.help = true;
                },
            };
        };

        window.bookingPage = function (config) {
            const weekdays = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const pad = (value) => String(value).padStart(2, '0');

            return {
                serviceId: config.initialService || '',
                barberId: config.initialBarber || '',
                date: config.initialDate || config.minDate || '',
                clientName: config.initialName || '',
                clientPhone: config.initialPhone || '',
                services: config.services || [],
                barbers: config.barbers || [],
                slots: [],
                selected: null,
                days: [],
                loading: false,
                error: '',
                daysLoading: false,
                showCustomDate: false,
                minDate: config.minDate || '',
                maxDate: config.maxDate || '',
                weekdays,
                months,

                init() {
                    this.days = this.calendar();

                    if (this.serviceId && this.barberId) {
                        this.loadDays();
                        this.loadSlots();
                    }
                },

                get ready() {
                    return !!(this.serviceId && this.barberId);
                },

                get selectedService() {
                    return this.services.find((service) => String(service.id) === String(this.serviceId)) || null;
                },

                get selectedBarber() {
                    return this.barbers.find((barber) => String(barber.id) === String(this.barberId)) || null;
                },

                get canConfirm() {
                    return !!(this.serviceId && this.barberId && this.date && this.selected);
                },

                get progress() {
                    return [
                        !!this.serviceId,
                        !!this.barberId,
                        !!(this.date && this.selected),
                        !!(this.clientName && this.clientPhone),
                    ];
                },

                get monthLabel() {
                    const base = this.date ? this.parseDate(this.date) : (this.days[0] ? this.parseDate(this.days[0].date) : new Date());

                    return this.months[base.getMonth()] + ' ' + base.getFullYear();
                },

                get summaryDate() {
                    if (!this.date) {
                        return 'Elige fecha';
                    }

                    const day = this.parseDate(this.date);

                    return this.weekdays[day.getDay()] + ' ' + day.getDate() + ' ' + this.months[day.getMonth()].slice(0, 3);
                },

                get slotGroups() {
                    const groups = [
                        { label: 'Turno Mañana', slots: [] },
                        { label: 'Turno Tarde', slots: [] },
                        { label: 'Turno Noche', slots: [] },
                    ];

                    this.slots.forEach((slot) => {
                        const minutes = this.toMinutes(slot.time);
                        const index = minutes < 12 * 60 ? 0 : (minutes < 18 * 60 ? 1 : 2);
                        groups[index].slots.push(slot);
                    });

                    return groups.filter((group) => group.slots.length > 0);
                },

                calendar() {
                    const start = this.parseDate(this.minDate || this.date);
                    const days = [];

                    for (let offset = 0; offset < 14; offset += 1) {
                        const day = new Date(start);
                        day.setDate(start.getDate() + offset);
                        days.push(this.makeDay(this.toDateString(day), null));
                    }

                    return days;
                },

                makeDay(dateString, count) {
                    const day = this.parseDate(dateString);

                    return {
                        date: dateString,
                        count,
                        dayShort: this.weekdays[day.getDay()],
                        dayNumber: day.getDate(),
                        isToday: dateString === this.minDate,
                    };
                },

                parseDate(value) {
                    return new Date(value + 'T00:00:00');
                },

                toDateString(value) {
                    return value.getFullYear() + '-' + pad(value.getMonth() + 1) + '-' + pad(value.getDate());
                },

                dayStatus(day) {
                    if (day.count === null) {
                        return day.isToday ? 'Hoy' : '';
                    }

                    if (day.count === 0) {
                        return 'Sin cupos';
                    }

                    if (day.isToday) {
                        return 'Hoy';
                    }

                    return day.count + (day.count === 1 ? ' cupo' : ' cupos');
                },

                dayStatusClass(day) {
                    return day.count === 0 ? 'text-slate-400' : 'text-emerald-600';
                },

                selectService(id) {
                    this.serviceId = String(id);
                    this.selected = null;
                    this.refreshAvailability();
                },

                selectBarber(id) {
                    this.barberId = String(id);
                    this.selected = null;
                    this.refreshAvailability();
                },

                selectDay(day) {
                    if (day.count === 0) {
                        return;
                    }

                    this.date = day.date;
                    this.loadSlots();
                },

                select(slot) {
                    this.selected = slot;
                },

                refreshAvailability() {
                    if (!this.ready) {
                        this.slots = [];
                        this.selected = null;
                        this.error = '';
                        this.days = this.calendar();

                        return;
                    }

                    this.loadDays();
                    this.loadSlots();
                },

                async loadDays() {
                    if (!this.ready) {
                        return;
                    }

                    this.daysLoading = true;

                    try {
                        const params = new URLSearchParams({
                            service_id: this.serviceId,
                            barber_id: this.barberId,
                        });

                        const response = await fetch(config.daysUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });

                        const data = await response.json();

                        if (response.ok && Array.isArray(data.days)) {
                            this.days = data.days.map((day) => this.makeDay(day.date, day.count));
                        }
                    } catch (error) {
                        // El calendario sigue disponible aunque no se puedan cargar los cupos.
                    } finally {
                        this.daysLoading = false;
                    }
                },

                async loadSlots() {
                    this.slots = [];
                    this.selected = null;
                    this.error = '';

                    if (!this.ready || !this.date) {
                        return;
                    }

                    this.loading = true;

                    try {
                        const params = new URLSearchParams({
                            service_id: this.serviceId,
                            barber_id: this.barberId,
                            date: this.date,
                        });

                        const response = await fetch(config.slotsUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.error = 'No se pudieron cargar los horarios. Revisa los datos.';
                            return;
                        }

                        this.slots = data.slots || [];

                        if (config.initialStart) {
                            const match = this.slots.find((slot) => slot.time === config.initialStart);

                            if (match) {
                                this.selected = match;
                            }

                            config.initialStart = '';
                        }

                        if (!this.slots.length) {
                            this.error = 'No hay horarios disponibles para esa fecha. Prueba con otro día.';
                        }
                    } catch (error) {
                        this.error = 'No se pudieron cargar los horarios. Inténtalo de nuevo.';
                    } finally {
                        this.loading = false;
                    }
                },

                toMinutes(time) {
                    const [hours, minutes] = String(time || '0:0').split(':').map(Number);

                    return (hours || 0) * 60 + (minutes || 0);
                },

                formatTime(time) {
                    const [hours, minutes] = String(time || '0:0').split(':').map(Number);
                    const suffix = hours >= 12 ? 'PM' : 'AM';
                    const hour = hours % 12 || 12;

                    return hour + ':' + pad(minutes || 0) + ' ' + suffix;
                },
            };
        };
    </script>
</head>
<body class="min-h-screen bg-slate-50 pb-28 text-slate-900 antialiased" data-ajax-saves data-ajax-region>
    <div x-data="bookingPage({
        services: @js($servicesData),
        barbers: @js($barbersData),
        slotsUrl: @js(route('booking.slots', $company->slug)),
        daysUrl: @js(route('booking.days', $company->slug)),
        storeUrl: @js(route('booking.store', $company->slug)),
        initialService: @js((string) old('service_id', request('service_id', ''))),
        initialBarber: @js((string) old('barber_id', request('barber_id', ''))),
        initialDate: @js((string) old('date', request('date', ''))),
        initialStart: @js((string) old('start_time', '')),
        initialName: @js(old('client_name', $customer?->name ?? '')),
        initialPhone: @js(old('client_phone', $customer?->phone ?? '')),
        minDate: @js($minDate),
        maxDate: @js($maxDate)
    })">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-md">
            <div class="mx-auto max-w-md px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-900">
                            <img src="{{ $companyLogo }}" alt="{{ $shopName }}" class="h-10 w-10 object-contain p-1" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h1 class="truncate text-base font-extrabold leading-tight text-slate-900">{{ $shopName }}</h1>
                                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                            </div>
                            <p class="truncate text-[11px] text-slate-500">{{ $shopAddress ?: 'Reserva tu cita en línea' }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        @if ($customer)
                            <a href="{{ route('booking.account', $company->slug) }}" title="Mi cuenta y recordatorios"
                               class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                                <x-icon name="user" class="h-4 w-4" />
                            </a>
                        @endif

                        @if ($shopPhone)
                            <a href="tel:{{ $shopPhone }}" title="Llamar a {{ $shopName }}"
                               class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                                <x-icon name="phone" class="h-4 w-4" />
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Progreso -->
                <div class="mt-3 grid grid-cols-4 gap-1.5">
                    <template x-for="(done, index) in progress" :key="index">
                        <div class="h-1 rounded-full transition-colors" :class="done ? 'bg-brand-500' : 'bg-slate-200'"></div>
                    </template>
                </div>
            </div>
        </header>

        <!-- Banner de recordatorios / PWA -->
        <div class="bg-slate-800 text-white" x-data="clientInstall()" x-init="init()">
            <div class="mx-auto flex max-w-md items-center justify-between gap-3 px-4 py-2">
                <button type="button" @click="install()" class="flex min-w-0 items-center gap-2 text-left">
                    <x-icon name="smartphone" class="h-3.5 w-3.5 shrink-0 text-brand-400" />
                    <span class="truncate text-[12px]" x-show="! installed">Recibe recordatorios automáticos</span>
                    <span class="truncate text-[12px]" x-show="installed" x-cloak>App instalada</span>
                </button>
                <button type="button" @click="install()" x-show="! installed" x-cloak
                        class="shrink-0 text-[12px] font-semibold text-brand-400">Gratis</button>
                <span x-show="installed" x-cloak class="shrink-0 text-[12px] font-semibold text-emerald-300">Listo</span>
            </div>
            <p x-show="help" x-cloak class="mx-auto max-w-md px-4 pb-2 text-[11px] leading-relaxed text-slate-300">
                En iPhone: toca <strong>Compartir</strong> y luego <strong>Añadir a pantalla de inicio</strong>.
                En Android: abre el menú del navegador y elige <strong>Instalar aplicación</strong>.
            </p>
        </div>

        <main class="mx-auto max-w-md space-y-5 px-4 py-4">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Revisa los datos:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($bookedAppointment)
                <section class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm" data-flash-status>
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                            <x-icon name="check" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-slate-800">¡Cita registrada!</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $bookedAppointment->service_name }} con {{ $bookedAppointment->barber?->name ?? 'el barbero' }}
                                el {{ $bookedAppointment->date->format('d/m/Y') }}
                                a las {{ mb_substr($bookedAppointment->start_time, 0, 5) }}.
                            </p>
                            <p class="mt-1 text-[11px] text-slate-400">Queda pendiente de confirmación por la barbería.</p>

                            <a href="{{ route('booking.account', $company->slug) }}"
                               class="mt-3 inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-500 px-3 text-xs font-bold text-white transition hover:bg-brand-400">
                                <x-icon name="clock" class="h-4 w-4" /> Activar recordatorios
                            </a>
                        </div>
                    </div>
                </section>
            @endif

            <!-- PASO 1: SERVICIO -->
            <section class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-[11px] font-bold text-white">1</span>
                        <h2 class="text-sm font-bold text-slate-800">Elige el servicio</h2>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400" x-text="selectedService ? '1 seleccionado' : 'Elige uno'"></span>
                </div>

                <div class="space-y-2">
                    @forelse ($services as $service)
                        <label class="relative flex cursor-pointer items-center justify-between rounded-xl border-2 p-3.5 transition-all"
                               :class="serviceId == '{{ $service->id }}' ? 'border-brand-500 bg-brand-50/60 shadow-sm' : 'border-slate-200 bg-white hover:border-brand-300'">
                            <input type="radio" name="service_card" value="{{ $service->id }}" x-model="serviceId"
                                   @change="selectService({{ $service->id }})" class="sr-only" />
                            <div class="min-w-0 pr-2">
                                <div class="flex items-center gap-1.5">
                                    <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-brand-600"
                                            x-show="serviceId == '{{ $service->id }}'" x-cloak />
                                    <span class="text-sm font-bold leading-snug"
                                          :class="serviceId == '{{ $service->id }}' ? 'text-brand-700' : 'text-slate-800'">{{ $service->name }}</span>
                                </div>
                                @if ($service->description)
                                    <p class="mt-0.5 text-xs leading-snug text-slate-500">{{ $service->description }}</p>
                                @endif
                                <div class="mt-1.5 flex items-center gap-3 text-[11px] font-medium text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <x-icon name="clock" class="h-3 w-3" /> {{ $service->duration_minutes }} min
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="block text-sm font-bold text-slate-800">{{ usd($service->price) }}</span>
                            </div>
                        </label>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-400">
                            Esta barbería todavía no tiene servicios disponibles.
                        </p>
                    @endforelse
                </div>
            </section>

            <!-- PASO 2: BARBERO -->
            <section class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-[11px] font-bold text-white">2</span>
                        <h2 class="text-sm font-bold text-slate-800">¿Quién te atiende?</h2>
                    </div>
                    <span class="max-w-[130px] truncate text-[11px] font-medium text-brand-600"
                          x-text="selectedBarber ? selectedBarber.name + ' seleccionado' : 'Elige uno'"></span>
                </div>

                <div class="-mx-4 flex gap-2.5 overflow-x-auto px-4 py-1 no-scrollbar">
                    @forelse ($barbersData as $barber)
                        <label class="relative flex w-28 shrink-0 cursor-pointer flex-col items-center rounded-xl border p-3 text-center transition-all"
                               :class="barberId == '{{ $barber['id'] }}' ? 'border-2 border-brand-500 bg-brand-50' : 'border-slate-200 bg-white hover:border-brand-300'">
                            <input type="radio" name="barber_card" value="{{ $barber['id'] }}" x-model="barberId"
                                   @change="selectBarber({{ $barber['id'] }})" class="sr-only" />
                            <div class="relative mb-1.5">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full text-xs font-bold transition-colors"
                                     :class="barberId == '{{ $barber['id'] }}' ? 'bg-slate-800 text-white' : 'bg-brand-100 text-brand-700'">{{ $barber['initials'] }}</div>
                                <span x-show="barberId == '{{ $barber['id'] }}'" x-cloak
                                      class="absolute -bottom-1 -right-1 rounded-full bg-brand-500 p-0.5 text-white">
                                    <x-icon name="check" class="h-2.5 w-2.5" />
                                </span>
                            </div>
                            <span class="text-xs font-semibold leading-tight"
                                  :class="barberId == '{{ $barber['id'] }}' ? 'text-brand-700' : 'text-slate-700'">{{ $barber['name'] }}</span>
                            <span class="mt-0.5 text-[10px] text-slate-400">Barbero</span>
                        </label>
                    @empty
                        <p class="w-full rounded-xl border border-dashed border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-400">
                            Esta barbería todavía no tiene barberos disponibles.
                        </p>
                    @endforelse
                </div>
            </section>

            <!-- PASO 3: FECHA Y HORA -->
            <section class="space-y-3.5 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-[11px] font-bold text-white">3</span>
                        <h2 class="text-sm font-bold text-slate-800">Fecha y hora</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700" x-text="monthLabel"></span>
                        <button type="button" @click="showCustomDate = ! showCustomDate"
                                class="text-[11px] font-semibold text-brand-600">Otra fecha</button>
                    </div>
                </div>

                <div x-show="showCustomDate" x-cloak>
                    <input type="date" x-model="date" :min="minDate" :max="maxDate" @change="loadSlots()"
                           class="block h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                </div>

                <!-- Carrusel de días -->
                <div class="-mx-3.5 flex gap-2 overflow-x-auto px-3.5 py-0.5 no-scrollbar">
                    <template x-for="day in days" :key="day.date">
                        <button type="button" @click="selectDay(day)" :disabled="day.count === 0"
                                :class="date === day.date
                                    ? 'border-2 border-brand-500 bg-brand-500 text-white shadow-sm'
                                    : (day.count === 0
                                        ? 'cursor-not-allowed border border-dashed border-slate-200 bg-slate-50/50 text-slate-400 opacity-50'
                                        : 'border border-slate-200 bg-slate-50 text-slate-700 hover:border-brand-300')"
                                class="flex w-14 shrink-0 flex-col items-center rounded-xl py-2 transition-all active:scale-95">
                            <span class="text-[10px] font-semibold uppercase"
                                  :class="date === day.date ? 'text-white/80' : 'text-slate-400'" x-text="day.dayShort"></span>
                            <span class="text-base font-bold" x-text="day.dayNumber"></span>
                            <span class="mt-0.5 rounded px-1 text-[9px] font-medium"
                                  x-show="dayStatus(day)"
                                  :class="date === day.date ? 'bg-white/20 text-white/90' : dayStatusClass(day)"
                                  x-text="dayStatus(day)"></span>
                        </button>
                    </template>
                </div>

                <!-- Horarios -->
                <div class="space-y-3 border-t border-slate-100 pt-3">
                    <div x-show="loading" x-cloak class="rounded-lg bg-slate-50 px-4 py-4 text-center text-xs text-slate-400">
                        Cargando horarios disponibles...
                    </div>

                    <div x-show="! loading && error" x-cloak class="rounded-lg bg-amber-50 px-4 py-3 text-xs text-amber-700" x-text="error"></div>

                    <div x-show="! loading && ! error && ! ready" x-cloak class="rounded-lg bg-slate-50 px-4 py-4 text-center text-xs text-slate-400">
                        Selecciona un servicio y un barbero para ver las horas disponibles.
                    </div>

                    <template x-for="group in slotGroups" :key="group.label">
                        <div>
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-400" x-text="group.label"></span>
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="slot in group.slots" :key="slot.time">
                                    <button type="button" @click="select(slot)"
                                            :class="selected && selected.time === slot.time
                                                ? 'border-2 border-brand-500 bg-brand-500 text-white font-bold shadow-sm'
                                                : 'border border-slate-200 bg-slate-50 text-slate-700 hover:border-brand-300'"
                                            class="flex items-center justify-center gap-1 rounded-lg py-2.5 text-xs transition-all active:scale-95">
                                        <span x-text="formatTime(slot.time)"></span>
                                        <x-icon name="check" class="h-3.5 w-3.5" x-show="selected && selected.time === slot.time" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- PASO 4: DATOS -->
            <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-[11px] font-bold text-white">4</span>
                    <h2 class="text-sm font-bold text-slate-800">Tus datos</h2>
                </div>

                <form id="booking-form" method="POST" :action="storeUrl" class="space-y-2.5" data-ajax-scroll-top>
                    @csrf
                    <input type="hidden" name="service_id" :value="serviceId" />
                    <input type="hidden" name="barber_id" :value="barberId" />
                    <input type="hidden" name="date" :value="date" />
                    <input type="hidden" name="start_time" :value="selected ? selected.time : ''" />

                    <div>
                        <label for="client_name" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nombre completo *</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-icon name="user" class="h-4 w-4" />
                            </span>
                            <input id="client_name" name="client_name" type="text" required autocomplete="name"
                                   x-model="clientName" placeholder="Tu nombre"
                                   class="block w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        </div>
                    </div>

                    <div>
                        <label for="client_phone" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">WhatsApp para confirmación *</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-icon name="phone" class="h-4 w-4" />
                            </span>
                            <input id="client_phone" name="client_phone" type="tel" required autocomplete="tel"
                                   x-model="clientPhone" placeholder="0412 000 0000"
                                   class="block w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nota para el barbero (opcional)</label>
                        <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}"
                               placeholder="Ej: Degradado bajo, barba cuadrada..."
                               class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    </div>
                </form>
            </section>

            <!-- Garantía -->
            <div class="flex items-center gap-2 rounded-xl border border-emerald-200/60 bg-emerald-50/70 p-3 text-[12px] text-slate-700">
                <x-icon name="shield-check" class="h-4 w-4 shrink-0 text-emerald-600" />
                <span>Pagas directamente en el local al terminar el corte. Cancelación sin costo.</span>
            </div>

            @if ($services->isEmpty() || $barbers->isEmpty())
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-700">
                    Esta barbería todavía no tiene servicios o barberos disponibles para reservar.
                </p>
            @endif
        </main>

        <!-- Barra fija inferior -->
        <footer class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur-md">
            <div class="mx-auto flex max-w-md items-center justify-between gap-3">
                <div class="min-w-0">
                    <span class="block text-[11px] leading-none text-slate-500"
                          x-text="summaryDate + (selected ? ' • ' + formatTime(selected.time) : ' • Elige hora')"></span>
                    <div class="mt-1 flex items-baseline gap-1">
                        <span class="text-lg font-extrabold leading-none text-slate-800"
                              x-text="selectedService ? selectedService.price : '$ 0,00'"></span>
                        <span class="max-w-[120px] truncate text-[11px] text-slate-400"
                              x-text="selectedService ? selectedService.name : 'Selecciona un servicio'"></span>
                    </div>
                </div>

                <button type="submit" form="booking-form" :disabled="! canConfirm"
                        :class="canConfirm
                            ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/20 hover:bg-brand-400'
                            : 'cursor-not-allowed bg-slate-200 text-slate-400'"
                        class="flex min-w-[165px] items-center justify-center gap-1.5 rounded-xl px-4 py-3 text-sm font-bold transition-all active:scale-95">
                    <x-icon name="check" class="h-4 w-4" />
                    Confirmar cita
                </button>
            </div>
        </footer>

        <div data-ajax-toast class="pointer-events-none fixed right-4 top-4 z-50 hidden max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg"></div>
    </div>
</body>
</html>
