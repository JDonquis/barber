@php
    $company = current_company();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Clientes</h1>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <div>
            <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-brand-600">
                <span class="h-2 w-2 rounded-full bg-brand-500"></span> Fidelización
            </span>
            <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Clientes y recordatorios</h2>
            <p class="mt-0.5 text-xs text-slate-400">Consulta quién tiene recordatorios activos y copia su enlace de acceso.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['label' => 'Clientes', 'value' => $stats['total'], 'icon' => 'users'],
                ['label' => 'Recordatorios activos', 'value' => $stats['active_reminders'], 'icon' => 'clock'],
                ['label' => 'Dispositivos push', 'value' => $stats['push_devices'], 'icon' => 'phone'],
                ['label' => 'Por enviar', 'value' => $stats['due'], 'icon' => 'refresh'],
            ] as $stat)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                        <x-icon :name="$stat['icon']" class="h-4 w-4" />
                    </span>
                    <p class="mt-3 text-xl font-extrabold tracking-tight text-slate-800">{{ $stat['value'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('customers.index') }}" class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <x-icon name="search" class="h-5 w-5" />
            </span>
            <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre o teléfono..."
                   class="h-12 w-full rounded-xl border border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500" />
        </form>

        <div class="space-y-3">
            @forelse ($customers as $customer)
                @php
                    $subscription = $customer->subscription;
                    $accessUrl = route('booking.access', ['company' => $company->slug, 'token' => $customer->access_token]);
                    $badge = ! $subscription
                        ? 'bg-slate-100 text-slate-500'
                        : ($subscription->active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500');
                    $label = ! $subscription ? 'Sin recordatorio' : ($subscription->active ? 'Activo' : 'Pausado');
                @endphp
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200" x-data="{
                    copied: false,
                    copyLink() {
                        const done = () => {
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2200);
                        };

                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(@js($accessUrl)).then(done).catch(() => this.fallbackCopy(done));
                        } else {
                            this.fallbackCopy(done);
                        }
                    },
                    fallbackCopy(done) {
                        const field = document.createElement('textarea');
                        field.value = @js($accessUrl);
                        field.style.position = 'fixed';
                        field.style.opacity = '0';
                        document.body.appendChild(field);
                        field.focus();
                        field.select();
                        try { document.execCommand('copy'); done(); } catch (error) {}
                        document.body.removeChild(field);
                    }
                }">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-800">{{ $customer->name }}</span>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ $label }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $customer->phone }}</p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                <span><strong class="text-slate-700">Visitas:</strong> {{ $customer->appointments_count }}</span>
                                <span><strong class="text-slate-700">Última visita:</strong> {{ $customer->last_visit_at?->format('d/m/Y') ?? '—' }}</span>
                                @if ($subscription)
                                    <span><strong class="text-slate-700">Frecuencia:</strong> cada {{ $subscription->interval_days }} días</span>
                                    <span><strong class="text-slate-700">Próximo:</strong> {{ $subscription->next_reminder_at?->format('d/m/Y') ?? 'Al completar una cita' }}</span>
                                @endif
                                <span><strong class="text-slate-700">Dispositivos:</strong> {{ $customer->pushSubscriptions->count() }}</span>
                            </div>
                            @if ($customer->pushSubscriptions->isEmpty())
                                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
                                    Sin notificaciones activas. Invita al cliente a instalar la app y activar push.
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <button type="button" @click="copyLink()"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                                <x-icon name="copy" class="h-3.5 w-3.5 text-brand-500" />
                                <span x-text="copied ? 'Copiado' : 'Copiar acceso'"></span>
                            </button>
                            <a href="{{ $accessUrl }}" target="_blank" rel="noopener"
                               class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-50 px-3 text-xs font-semibold text-brand-600 transition hover:bg-brand-100">
                                <x-icon name="eye" class="h-3.5 w-3.5" /> Abrir
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white px-6 py-12 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    Todavía no hay clientes registrados. Se crearán al reservar una cita.
                </div>
            @endforelse
        </div>

        @if ($customers->hasPages())
            <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
