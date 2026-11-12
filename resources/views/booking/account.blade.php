<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Mi cuenta · {{ $shopName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

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
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    </style>

    <script>
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = window.atob(base64);
            const output = new Uint8Array(raw.length);

            for (let i = 0; i < raw.length; ++i) {
                output[i] = raw.charCodeAt(i);
            }

            return output;
        }

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

        window.clientPush = function (config) {
            return {
                subscribed: config.subscribed,
                busy: false,
                message: '',
                async enable() {
                    if (! ('serviceWorker' in navigator) || ! ('PushManager' in window)) {
                        this.message = 'Este navegador no soporta notificaciones push.';
                        return;
                    }

                    if (! config.publicKey) {
                        this.message = 'Las notificaciones no están configuradas todavía.';
                        return;
                    }

                    this.busy = true;
                    this.message = '';

                    try {
                        const permission = await Notification.requestPermission();

                        if (permission !== 'granted') {
                            this.message = 'Debes permitir las notificaciones para recibir recordatorios.';
                            return;
                        }

                        const registration = await navigator.serviceWorker.register(config.swUrl, { scope: config.scope });
                        let subscription = await registration.pushManager.getSubscription();

                        if (! subscription) {
                            subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(config.publicKey),
                            });
                        }

                        const supported = window.PushManager && window.PushManager.supportedContentEncodings
                            ? window.PushManager.supportedContentEncodings[0]
                            : 'aesgcm';

                        const response = await fetch(config.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                            },
                            body: JSON.stringify({
                                ...subscription.toJSON(),
                                content_encoding: supported || 'aesgcm',
                            }),
                        });

                        if (! response.ok) {
                            throw new Error('No se pudo registrar el dispositivo.');
                        }

                        this.subscribed = true;
                        this.message = 'Notificaciones activadas en este dispositivo.';
                    } catch (error) {
                        this.message = 'No se pudieron activar las notificaciones. Inténtalo de nuevo.';
                    } finally {
                        this.busy = false;
                    }
                },
                async disable() {
                    this.busy = true;
                    this.message = '';

                    try {
                        const registration = await navigator.serviceWorker.register(config.swUrl, { scope: config.scope });
                        const subscription = await registration.pushManager.getSubscription();

                        if (subscription) {
                            await fetch(config.deleteUrl, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                },
                                body: JSON.stringify({ endpoint: subscription.endpoint }),
                            });

                            await subscription.unsubscribe();
                        }

                        this.subscribed = false;
                        this.message = 'Notificaciones desactivadas en este dispositivo.';
                    } catch (error) {
                        this.message = 'No se pudieron desactivar las notificaciones.';
                    } finally {
                        this.busy = false;
                    }
                },
            };
        };
    </script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased" data-ajax-saves data-ajax-region>
    <main class="mx-auto max-w-2xl px-4 py-6 sm:py-10">
        <header class="flex flex-col items-center text-center">
            <img src="{{ $companyLogo }}" alt="{{ $shopName }}"
                 class="h-20 w-20 rounded-2xl border border-slate-200 bg-white object-contain p-2 shadow-sm" />
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">{{ $shopName }}</h1>
            <p class="mt-1 text-xs font-medium uppercase tracking-widest text-brand-600">Mi cuenta</p>
        </header>

        @if (session('status'))
            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" data-flash-status>
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-flash-error>
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-flash-error>
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                    <x-icon name="user" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Hola, {{ $customer->name }}</h2>
                    <p class="text-xs text-slate-400">{{ $customer->phone }}</p>
                </div>
            </div>
        </section>

        @if ($nextAppointment)
            <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Tu próxima cita</p>
                <div class="mt-2 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">{{ $nextAppointment->service_name }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $nextAppointment->date->format('d/m/Y') }} a las {{ mb_substr($nextAppointment->start_time, 0, 5) }}
                        </p>
                        <p class="text-xs text-slate-400">Con {{ $nextAppointment->barber?->name ?? 'tu barbero' }}</p>
                    </div>
                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                        {{ ucfirst($nextAppointment->status) }}
                    </span>
                </div>
            </section>
        @endif

        <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"
                 x-data="clientPush({
                    url: @js(route('booking.push.subscribe', $company->slug)),
                    deleteUrl: @js(route('booking.push.destroy', $company->slug)),
                    csrf: @js(csrf_token()),
                    publicKey: @js($pushPublicKey),
                    subscribed: @js($pushSubscribed),
                    swUrl: @js(asset('reservar/sw.js')),
                    scope: @js('/reservar/'.$company->slug)
                 })">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <x-icon name="phone" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-bold text-slate-800">Notificaciones en este dispositivo</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Actívalas para recibir el recordatorio de tu próximo corte.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" x-show="! subscribed" @click="enable()" :disabled="busy"
                                class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-brand-500 px-4 text-xs font-bold text-white transition hover:bg-brand-400 disabled:opacity-60">
                            <x-icon name="check" class="h-4 w-4" /> Activar notificaciones
                        </button>
                        <button type="button" x-show="subscribed" x-cloak @click="disable()" :disabled="busy"
                                class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-slate-100 px-4 text-xs font-bold text-slate-600 transition hover:bg-slate-200 disabled:opacity-60">
                            <x-icon name="x" class="h-4 w-4" /> Desactivar notificaciones
                        </button>
                        <span x-show="subscribed" x-cloak class="inline-flex h-10 items-center rounded-lg bg-emerald-50 px-3 text-xs font-semibold text-emerald-700">
                            Activadas
                        </span>
                    </div>

                    <p x-show="message" x-cloak class="mt-2 text-xs text-slate-500" x-text="message"></p>
                </div>
            </div>
        </section>

        <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                    <x-icon name="clock" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-bold text-slate-800">Recordatorio de corte</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Elige cada cuánto quieres que te avisemos después de cada cita.
                    </p>

                    @php
                        $currentInterval = (int) old('interval_days', $subscription?->interval_days ?? 15);
                        $isPreset = in_array($currentInterval, [7, 15, 30], true);
                    @endphp

                    <div class="mt-3" x-data="{
                        choice: @js($isPreset ? (string) $currentInterval : 'custom'),
                        custom: @js($isPreset ? '' : (string) $currentInterval)
                    }">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ([7 => '7 días', 15 => '15 días', 30 => '30 días'] as $days => $label)
                                <button type="button" @click="choice = '{{ $days }}'"
                                        :class="choice === '{{ $days }}' ? 'bg-brand-500 text-white shadow-sm' : 'bg-slate-50 text-slate-600 ring-1 ring-slate-200'"
                                        class="h-10 rounded-lg text-xs font-bold transition">
                                    {{ $label }}
                                </button>
                            @endforeach
                            <button type="button" @click="choice = 'custom'"
                                    :class="choice === 'custom' ? 'bg-brand-500 text-white shadow-sm' : 'bg-slate-50 text-slate-600 ring-1 ring-slate-200'"
                                    class="h-10 rounded-lg text-xs font-bold transition">
                                Personalizado
                            </button>
                        </div>

                        <div x-show="choice === 'custom'" x-cloak class="mt-3">
                            <label for="custom_interval" class="text-xs font-semibold text-slate-500">Días</label>
                            <input id="custom_interval" type="number" min="1" max="365" x-model="custom"
                                   class="mt-1 block h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        </div>

                        <form method="POST" action="{{ route('booking.reminders.update', $company->slug) }}" class="mt-3" data-ajax-scroll-top>
                            @csrf
                            <input type="hidden" name="interval_days" :value="choice === 'custom' ? custom : choice" />
                            <button type="submit"
                                    class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-500 text-sm font-bold text-white transition hover:bg-brand-400 active:scale-[0.985]">
                                <x-icon name="check" class="h-4 w-4" />
                                {{ $subscription?->active ? 'Guardar frecuencia' : 'Activar recordatorios' }}
                            </button>
                        </form>

                        @if ($subscription?->active)
                            <form method="POST" action="{{ route('booking.reminders.destroy', $company->slug) }}" class="mt-2" data-ajax-scroll-top>
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="flex h-10 w-full items-center justify-center rounded-xl bg-slate-100 text-xs font-semibold text-slate-600 transition hover:bg-slate-200">
                                    Desactivar recordatorios
                                </button>
                            </form>

                            @if ($subscription->next_reminder_at)
                                <p class="mt-2 text-center text-[11px] text-slate-400">
                                    Próximo recordatorio: {{ $subscription->next_reminder_at->format('d/m/Y') }}
                                </p>
                            @endif
                        @else
                            <p class="mt-2 text-center text-[11px] text-slate-400">
                                El conteo comienza cuando la barbería marca tu cita como completada.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200" x-data="clientInstall()" x-init="init()">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Instala la app</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Reserva y revisa tus recordatorios más rápido.</p>
                </div>
                <button type="button" @click="install()" x-show="! installed"
                        class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white transition hover:bg-slate-700">
                    <x-icon name="download" class="h-4 w-4" /> Instalar
                </button>
                <span x-show="installed" x-cloak class="inline-flex h-10 items-center rounded-lg bg-emerald-100 px-3 text-xs font-bold text-emerald-700">Instalada</span>
            </div>
            <p x-show="help" x-cloak class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500">
                En iPhone: toca <strong>Compartir</strong> y luego <strong>Añadir a pantalla de inicio</strong>.
                En Android: abre el menú del navegador y elige <strong>Instalar aplicación</strong>.
            </p>
        </section>

        <div class="mt-4">
            <a href="{{ route('booking.show', $company->slug) }}"
               class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-slate-900 text-sm font-bold text-white transition hover:bg-slate-700">
                <x-icon name="scissors" class="h-4 w-4" /> Reservar otra cita
            </a>
        </div>

        <footer class="mt-6 text-center text-[11px] text-slate-400">
            {{ $shopName }} · Citas y recordatorios
        </footer>
    </main>

    <div data-ajax-toast class="pointer-events-none fixed right-4 top-4 z-50 hidden max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg"></div>
</body>
</html>
