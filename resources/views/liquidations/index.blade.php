<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Liquidaciones de barberos</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto space-y-4" x-data="{
        open: {{ $errors->any() ? 'true' : 'false' }},
        barberId: @js(old('barber_id', '')),
        amount: @js(old('amount', '')),
        balances: @js($barberBalances->mapWithKeys(fn ($barber) => [$barber->id => usd_conversion_enabled() ? round((float) $barber->outstanding, 2) : round((float) $barber->outstanding * usd_conversion_reference_rate(), 2)])),
        selectBarber(id) {
            this.barberId = String(id);
            if (this.balances[id] !== undefined) {
                this.amount = Number(this.balances[id]).toFixed(2);
            }
        }
    }">
        <section class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-emerald-600">
                    <x-icon name="wallet" class="h-4 w-4" /> Pagos al equipo
                </span>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Liquidaciones</h2>
                <p class="mt-0.5 text-xs leading-snug text-slate-400">
                    Registra los pagos que le haces a cada barbero. El saldo pendiente se descuenta automáticamente.
                </p>
            </div>
            <button type="button" @click="open = ! open"
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-md shadow-emerald-500/25 transition hover:bg-emerald-600 active:scale-95"
                    title="Registrar pago">
                <x-icon name="plus" class="h-6 w-6" />
            </button>
        </section>

        @if ($barberBalances->isNotEmpty())
            <section class="space-y-2.5 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                            <x-icon name="users" class="h-4 w-4 text-emerald-600" /> Saldo pendiente por barbero
                        </h3>
                        <p class="text-[11px] text-slate-400">Comisión + propinas pendientes menos pagos registrados.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">Al día de hoy</span>
                </div>

                @php
                    $totalOutstanding = $barberBalances->sum('outstanding');
                @endphp

                @if ($totalOutstanding > 0)
                    <form method="POST" action="{{ route('liquidations.pay-all') }}"
                          onsubmit="return confirm('¿Registrar el pago del saldo total a todos los barberos?');">
                        @csrf
                        <button type="submit"
                                class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-slate-800 text-xs font-bold text-white shadow-sm transition hover:bg-slate-700 active:scale-[0.985]">
                            <x-icon name="wallet" class="h-4 w-4" /> Pagar a todos · {{ money($totalOutstanding) }}
                        </button>
                    </form>
                @endif

                <div class="space-y-2">
                    @foreach ($barberBalances as $barberBalance)
                        @php
                            $words = preg_split('/\s+/', trim($barberBalance->name));
                            $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1));
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">{{ $initials }}</span>
                                <div class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-700">{{ $barberBalance->name }}</span>
                                    <span class="block text-[10px] text-slate-400">
                                        Com. {{ money($barberBalance->outstanding_commission) }} · Prop. {{ money($barberBalance->outstanding_tips) }}
                                        @if ($barberBalance->paid_total > 0)
                                            · Pagado {{ money($barberBalance->paid_total) }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <div class="text-right">
                                    <span class="block text-sm font-extrabold {{ $barberBalance->outstanding > 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ money($barberBalance->outstanding) }}</span>
                                    <span class="text-[10px] uppercase tracking-wide text-slate-400">Por pagar</span>
                                </div>
                                @if ($barberBalance->outstanding > 0)
                                    <button type="button" @click="open = true; selectBarber('{{ $barberBalance->id }}')"
                                            class="flex h-9 items-center justify-center rounded-lg bg-emerald-500 px-3 text-[11px] font-bold text-white shadow-sm transition hover:bg-emerald-600 active:scale-95">
                                        Pagar
                                    </button>
                                @else
                                    <span class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-100">Al día</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section x-cloak x-show="open" x-transition.opacity class="rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-200">
            <form method="POST" action="{{ route('liquidations.store') }}" class="space-y-3">
                @csrf
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <x-icon name="wallet" class="h-4 w-4 text-emerald-500" /> Registrar pago
                    </span>
                    <span class="text-[11px] text-slate-400">Barbero + monto</span>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-500">Barbero *</label>
                        <span class="text-[11px] text-slate-400">Toca para seleccionar</span>
                    </div>

                    @if ($barbers->isEmpty())
                        <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-xs text-slate-400">No hay barberos registrados.</p>
                    @else
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($barbers as $barber)
                                @php
                                    $words = preg_split('/\s+/', trim($barber->name));
                                    $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1));
                                @endphp
                                <button type="button" @click="selectBarber('{{ $barber->id }}')"
                                        :class="barberId === '{{ $barber->id }}' ? 'ring-2 ring-emerald-500 bg-emerald-50' : 'bg-slate-50 hover:bg-slate-100'"
                                        class="flex flex-col items-center gap-1.5 rounded-xl p-2.5 transition active:scale-95">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold transition"
                                          :class="barberId === '{{ $barber->id }}' ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600'">
                                        {{ $initials }}
                                    </span>
                                    <span class="w-full truncate text-center text-xs font-medium transition"
                                          :class="barberId === '{{ $barber->id }}' ? 'text-emerald-700 font-semibold' : 'text-slate-600'">
                                        {{ $barber->name }}
                                    </span>
                                    <span class="rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide"
                                          :class="barberId === '{{ $barber->id }}' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'">
                                        {{ $barber->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <input type="hidden" name="barber_id" :value="barberId">
                    <x-input-error :messages="$errors->get('barber_id')" />
                </div>

                <div class="space-y-1.5">
                    <label for="amount" class="text-xs font-medium text-slate-500">
                        {{ usd_conversion_enabled() ? 'Monto a pagar (USD) *' : 'Monto a pagar en '.local_currency().' *' }}
                    </label>
                    <div class="relative flex items-center">
                        <span class="pointer-events-none absolute left-4 text-sm font-bold text-emerald-600">{{ usd_conversion_enabled() ? '$' : local_currency_symbol() }}</span>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" required x-model="amount"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm font-semibold text-slate-700 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500" />
                    </div>
                    <p class="text-[11px] text-slate-400">Se carga automáticamente el saldo pendiente del barbero seleccionado.</p>
                    <x-input-error :messages="$errors->get('amount')" />
                </div>

                <div class="space-y-1.5">
                    <label for="payment_date" class="text-xs font-medium text-slate-500">Fecha del pago *</label>
                    <input id="payment_date" name="payment_date" type="date" required value="{{ old('payment_date', now()->toDateString()) }}"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500" />
                    <x-input-error :messages="$errors->get('payment_date')" />
                </div>

                <div class="space-y-1.5">
                    <label for="notes" class="text-xs font-medium text-slate-500">Nota</label>
                    <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}" placeholder="Detalle del pago (opcional)"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500" />
                    <x-input-error :messages="$errors->get('notes')" />
                </div>

                <button type="submit"
                        class="flex h-[50px] w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 text-sm font-bold text-white shadow-md shadow-emerald-500/25 transition hover:bg-emerald-600 active:scale-[0.985]">
                    <x-icon name="check" class="h-5 w-5" /> Registrar pago
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
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Pagos registrados</h3>
                <span class="text-[11px] text-slate-400">{{ $payments->count() }} pago(s)</span>
            </div>

            @forelse ($payments as $payment)
                @php
                    $words = preg_split('/\s+/', trim($payment->barber?->name ?? '?'));
                    $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1));
                @endphp
                <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">
                                {{ $initials }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $payment->barber?->name ?? 'Barbero eliminado' }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ $payment->payment_date->format('d/m/Y') }}
                                    @if ($payment->createdBy) · {{ $payment->createdBy->name }} @endif
                                </p>
                                @if ($payment->notes)
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $payment->notes }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <div class="text-right">
                                <span class="block text-sm font-extrabold text-emerald-700">{{ money($payment->amount_usd, $payment->amount_local) }}</span>
                                <span class="text-[10px] uppercase tracking-wide text-slate-400">Pagado</span>
                            </div>
                            <form method="POST" action="{{ route('liquidations.payments.destroy', $payment) }}"
                                  onsubmit="return confirm('¿Eliminar este pago? El saldo se restaurará.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Eliminar pago"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 text-rose-500 transition hover:bg-rose-100 active:scale-95">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-white px-4 py-10 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    No hay pagos registrados todavía.
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
