<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Retenciones</h1>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4">
        <!-- Encabezado -->
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-rose-600">
                    <x-icon name="shield-check" class="h-4 w-4" /> Descuentos al equipo
                </span>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Retenciones</h2>
                <p class="mt-0.5 text-xs leading-snug text-slate-400">Crea conceptos recurrentes que se descuentan de las ganancias de los barberos en sus liquidaciones.</p>
            </div>
            <a href="{{ route('retentions.create') }}"
               class="flex h-12 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-4 text-sm font-bold text-white shadow-md shadow-brand-500/25 transition hover:bg-brand-400 active:scale-95">
                <x-icon name="plus" class="h-4 w-4" /> Nuevo
            </a>
        </div>

        <!-- Resumen -->
        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2 flex items-center justify-between rounded-xl bg-rose-50 p-4 shadow-sm ring-1 ring-rose-200">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-500 text-white">
                        <x-icon name="shield-check" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-rose-700">Conceptos activos</p>
                        <p class="text-[11px] text-rose-700">{{ $stats['active'] }} de {{ $stats['total'] }} concepto(s)</p>
                    </div>
                </div>
                <span class="shrink-0 text-2xl font-extrabold tracking-tight text-rose-700">{{ $stats['active'] }}</span>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <span class="text-xs text-slate-400">Para todos</span>
                <p class="mt-1 text-lg font-extrabold tracking-tight text-slate-800">{{ $stats['global'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <span class="text-xs text-slate-400">Por barbero</span>
                <p class="mt-1 text-lg font-extrabold tracking-tight text-slate-800">{{ $stats['barbers'] }}</p>
            </div>
        </div>

        <p class="rounded-xl bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-500 ring-1 ring-slate-200">
            El monto se prorratea según los días del cierre. Ejemplo: una retención semanal de $7 en una liquidación diaria descuenta $1.
        </p>

        <!-- Listado -->
        <section class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Conceptos registrados</span>
                <span class="text-[11px] font-semibold text-brand-600">Moneda: {{ $usdConversionEnabled ? 'USD ($)' : local_currency() }}</span>
            </div>

            <div class="space-y-2">
                @forelse ($retentions as $retention)
                    @php
                        $owner = $retention->barber_id
                            ? ($retention->barber?->name ?? 'Barbero eliminado')
                            : 'Todos los barberos';
                    @endphp

                    <article class="rounded-xl bg-white p-3.5 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $retention->active ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-400' }}">
                                    <x-icon name="shield-check" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $retention->name }}</p>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $retention->active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $retention->active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400">
                                        <x-icon name="user" class="h-3.5 w-3.5" /> {{ $owner }}
                                    </p>
                                    <p class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400">
                                        <x-icon name="refresh" class="h-3.5 w-3.5" /> {{ $retention->frequency_label }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="text-sm font-extrabold text-rose-600">- {{ price_money($retention->amount_usd) }}</span>
                                <div class="flex items-center gap-1">
                                    <form method="POST" action="{{ route('retentions.toggle', $retention) }}">
                                        @csrf
                                        <button type="submit"
                                                class="flex h-8 w-8 items-center justify-center rounded-lg {{ $retention->active ? 'bg-amber-50 text-amber-600 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' }} transition"
                                                title="{{ $retention->active ? 'Desactivar' : 'Activar' }}">
                                            <x-icon :name="$retention->active ? 'x' : 'check'" class="h-4 w-4" />
                                        </button>
                                    </form>
                                    <a href="{{ route('retentions.edit', $retention) }}"
                                       class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition hover:bg-slate-200" title="Editar">
                                        <x-icon name="edit" class="h-4 w-4" />
                                    </a>
                                    <form method="POST" action="{{ route('retentions.destroy', $retention) }}" onsubmit="return confirm('¿Eliminar este concepto de retención?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-500 transition hover:bg-rose-100" title="Eliminar">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="flex flex-col items-center justify-center rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-slate-200">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-500">
                            <x-icon name="shield-check" class="h-7 w-7" />
                        </span>
                        <p class="mt-3 text-base font-bold text-slate-800">Sin retenciones</p>
                        <p class="mt-1 max-w-xs text-xs text-slate-400">Crea tu primer concepto con el botón Nuevo.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
