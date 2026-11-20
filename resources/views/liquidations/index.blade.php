<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Liquidaciones de barberos</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto space-y-4" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
        <section class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-emerald-600">
                    <x-icon name="wallet" class="h-4 w-4" /> Pagos al equipo
                </span>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Liquidaciones</h2>
                <p class="mt-0.5 text-xs leading-snug text-slate-400">
                    Genera y cierra liquidaciones por barbero. Solo una liquidación cerrada descuenta el saldo pendiente del ranking.
                </p>
            </div>
            <button type="button" @click="open = ! open"
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-md shadow-emerald-500/25 transition hover:bg-emerald-600 active:scale-95"
                    title="Nueva liquidación">
                <x-icon name="plus" class="h-6 w-6" />
            </button>
        </section>

        <section x-cloak x-show="open" x-transition.opacity class="rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-200">
            <form method="POST" action="{{ route('liquidations.store') }}" class="space-y-3">
                @csrf
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <x-icon name="wallet" class="h-4 w-4 text-emerald-500" /> Nueva liquidación
                    </span>
                    <span class="text-[11px] text-slate-400">Barbero + período</span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="barber_id" class="text-xs font-medium text-slate-500">Barbero *</label>
                        <select id="barber_id" name="barber_id" required
                                class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500">
                            <option value="">Selecciona un barbero</option>
                            @foreach ($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(old('barber_id') == $barber->id)>
                                    {{ $barber->name }}{{ $barber->active ? '' : ' (inactivo)' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('barber_id')" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="period_type" class="text-xs font-medium text-slate-500">Período *</label>
                        <select id="period_type" name="period_type"
                                class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500">
                            <option value="diario" @selected(old('period_type') === 'diario')>Diario</option>
                            <option value="semanal" @selected(old('period_type') === 'semanal')>Semanal</option>
                            <option value="mensual" @selected(old('period_type') === 'mensual')>Mensual</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="date" class="text-xs font-medium text-slate-500">Fecha de referencia *</label>
                    <input id="date" name="date" type="date" required value="{{ old('date', now()->toDateString()) }}"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500" />
                    <x-input-error :messages="$errors->get('date')" />
                </div>

                <button type="submit"
                        class="flex h-[50px] w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 text-sm font-bold text-white shadow-md shadow-emerald-500/25 transition hover:bg-emerald-600 active:scale-[0.985]">
                    <x-icon name="check" class="h-5 w-5" /> Calcular liquidación
                </button>
            </form>
        </section>

        <section class="space-y-2">
            <form method="GET" action="{{ route('liquidations.index') }}" class="flex items-center gap-2">
                <select name="barber_id" onchange="this.form.submit()"
                        class="h-11 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Todos los barberos</option>
                    @foreach ($barbers as $barber)
                        <option value="{{ $barber->id }}" @selected((string) $barberId === (string) $barber->id)>
                            {{ $barber->name }}
                        </option>
                    @endforeach
                </select>
                @if ($barberId)
                    <a href="{{ route('liquidations.index') }}"
                       class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200" title="Quitar filtro">
                        <x-icon name="x" class="h-4 w-4" />
                    </a>
                @endif
            </form>
        </section>

        <section class="space-y-2.5">
            @forelse ($liquidations as $liquidation)
                @php
                    $words = preg_split('/\s+/', trim($liquidation->barber?->name ?? '?'));
                    $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1));
                @endphp
                <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">
                                {{ $initials }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="truncate text-sm font-bold text-slate-800">{{ $liquidation->barber?->name ?? 'Barbero eliminado' }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $liquidation->isClosed() ? 'bg-emerald-100 text-emerald-700' : 'bg-brand-100 text-brand-700' }}">
                                        {{ $liquidation->isClosed() ? 'Cerrada' : 'Abierta' }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                                        {{ ucfirst($liquidation->period_type) }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ $liquidation->period_start->format('d/m/Y') }} — {{ $liquidation->period_end->format('d/m/Y') }}
                                    · {{ $liquidation->ticket_count }} ticket(s)
                                </p>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="block text-sm font-extrabold text-emerald-700">{{ money($liquidation->barberEarningsUsd(), $liquidation->localBarberEarnings()) }}</span>
                            <span class="text-[10px] uppercase tracking-wide text-slate-400">A pagar</span>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 text-center">
                        <div>
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Comisión</span>
                            <span class="block text-xs font-bold text-slate-700">{{ money($liquidation->barber_commission_usd) }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Propinas</span>
                            <span class="block text-xs font-bold text-slate-700">{{ money($liquidation->barber_tips_usd) }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Retenciones</span>
                            <span class="block text-xs font-bold text-rose-600">- {{ money($liquidation->barber_retentions_usd) }}</span>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('liquidations.show', $liquidation) }}"
                           class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-100 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                            <x-icon name="search" class="h-4 w-4 text-emerald-500" /> Ver detalle
                        </a>
                        <a href="{{ route('liquidations.print', $liquidation) }}" target="_blank"
                           class="flex h-10 items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-4 text-xs font-semibold text-slate-500 transition hover:bg-slate-200">
                            <x-icon name="printer" class="h-4 w-4" /> PDF
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-white px-4 py-10 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    No hay liquidaciones registradas.
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
