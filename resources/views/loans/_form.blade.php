@php
    $loan = $loan ?? null;
    $editing = (bool) ($loan && $loan->exists);
    $currentAmount = (float) old('amount_usd', $loan ? (usd_conversion_enabled() ? $loan->amount_usd : $loan->localAmount()) : 0);
    $currentDate = old('loan_date', $loan?->loan_date?->format('Y-m-d') ?? now()->toDateString());
    $currentBarber = old('barber_id', $loan?->barber_id);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Préstamos</h1>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4" x-data="{
        barberId: @js((string) ($currentBarber ?? '')),
        entry: {{ $currentAmount }},
        conversionEnabled: {{ $usdConversionEnabled ? 'true' : 'false' }},
        currency: @js(usd_conversion_enabled() ? 'USD' : local_currency()),
        rate: {{ $usdConversionEnabled ? (float) $currentRate : 1 }},
        localCode: @js(local_currency()),
        localSymbol: @js(local_currency_symbol()),
        selectBarber(id) {
            this.barberId = String(id);
        },
        formatVes(value) {
            return this.localSymbol + ' ' + Number(value || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatUsd(value) {
            return '$' + Number(value || 0).toFixed(2);
        },
        get usdValue() {
            return this.currency === this.localCode && this.rate > 0 ? Number(this.entry || 0) / this.rate : Number(this.entry || 0);
        },
        get equivalent() {
            return this.currency === this.localCode ? this.formatUsd(this.usdValue) : this.formatVes(this.usdValue * this.rate);
        },
        setCurrency(value) {
            if (value === this.currency) return;
            const usd = this.usdValue;
            this.currency = value;
            this.entry = value === this.localCode ? Number((usd * this.rate).toFixed(2)) : Number(usd.toFixed(2));
        }
    }">
        <!-- Subcabecera -->
        <div class="flex items-center justify-between">
            <a href="{{ route('loans.index') }}"
               class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100 active:scale-95" title="Volver a préstamos">
                <x-icon name="arrow-left" class="h-5 w-5" />
            </a>
            @if ($usdConversionEnabled)
                <div class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 shadow-sm ring-1 ring-slate-200">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ local_currency() }}:</span>
                    <span class="text-[11px] font-bold text-emerald-600">{{ ves($currentRate) }}</span>
                </div>
            @endif
        </div>

        <!-- Título -->
        <div>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-brand-600">Préstamos · {{ $editing ? 'Edición' : 'Registro' }}</span>
            <h2 class="text-xl font-extrabold tracking-tight text-slate-800">{{ $editing ? 'Editar préstamo' : 'Nuevo préstamo' }}</h2>
            <p class="mt-0.5 text-xs text-slate-400">Registra el dinero adelantado al barbero para luego controlar sus abonos.</p>
        </div>

        <form method="POST" action="{{ $editing ? route('loans.update', $loan) : route('loans.store') }}" class="space-y-4">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <x-card>
                <div class="space-y-5 p-4 sm:p-5">
                    <!-- Barbero -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Barbero *</span>
                            <span class="text-[11px] text-slate-400">Toca para seleccionar</span>
                        </div>

                        <input type="hidden" name="barber_id" :value="barberId">

                        @if ($barbers->isEmpty())
                            <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-xs text-slate-400">
                                No hay barberos registrados.
                            </p>
                        @else
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                @foreach ($barbers as $barber)
                                    @php
                                        $initials = collect(explode(' ', trim($barber->name)))
                                            ->filter()
                                            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                            ->take(2)
                                            ->implode('');
                                    @endphp
                                    <button type="button" @click="selectBarber({{ $barber->id }})"
                                            :class="barberId === '{{ $barber->id }}' ? 'ring-2 ring-brand-500 bg-brand-50' : 'bg-slate-50 hover:bg-slate-100'"
                                            class="flex flex-col items-center gap-1.5 rounded-xl p-2.5 transition active:scale-95">
                                        <span class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold transition"
                                              :class="barberId === '{{ $barber->id }}' ? 'bg-brand-500 text-white' : 'bg-slate-200 text-slate-600'">
                                            {{ $initials }}
                                        </span>
                                        <span class="w-full truncate text-center text-xs font-medium transition"
                                              :class="barberId === '{{ $barber->id }}' ? 'text-brand-600 font-semibold' : 'text-slate-600'">
                                            {{ $barber->name }}
                                        </span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide"
                                              :class="barberId === '{{ $barber->id }}' ? 'bg-brand-100 text-brand-700' : 'bg-slate-200 text-slate-500'">
                                            {{ $barber->active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <x-input-error :messages="$errors->get('barber_id')" />
                    </div>

                    <!-- Monto -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <x-input-label for="amount" :value="usd_conversion_enabled() ? 'Monto prestado *' : 'Monto prestado en '.local_currency().' *'" />
                            <div class="inline-flex rounded-lg bg-slate-100 p-0.5" x-show="conversionEnabled" x-cloak>
                                <button type="button" @click="setCurrency('USD')"
                                        :class="currency === 'USD' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'"
                                        class="rounded-md px-3 py-1 text-xs font-bold uppercase tracking-wide transition">USD</button>
                                <button type="button" @click="setCurrency(localCode)"
                                        :class="currency === localCode ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'"
                                        class="rounded-md px-3 py-1 text-xs font-bold uppercase tracking-wide transition"><span x-text="localCode"></span></button>
                            </div>
                        </div>

                        <input type="hidden" name="amount_usd" :value="usdValue.toFixed(2)">
                        <input type="hidden" name="exchange_rate" value="{{ (float) $currentRate }}">

                        <div class="relative flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 transition focus-within:border-brand-500 focus-within:bg-white">
                            <span class="select-none pr-2 text-lg font-extrabold text-brand-500" x-text="currency === localCode ? localSymbol : '$'"></span>
                            <input id="amount" type="number" step="0.01" min="0.01" required x-ref="amountInput"
                                   x-model.number="entry"
                                   class="h-14 w-full border-0 bg-transparent p-0 text-xl font-extrabold tracking-tight text-slate-800 focus:ring-0" />
                            <button type="button" @click="entry = 0; $refs.amountInput.focus()"
                                    class="p-2 text-slate-400 transition hover:text-slate-700" title="Limpiar monto">
                                <x-icon name="x" class="h-5 w-5" />
                            </button>
                        </div>

                        <div x-show="conversionEnabled" x-cloak class="mt-1 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-100">
                            <span class="flex items-center gap-1.5 text-xs text-slate-400">
                                <x-icon name="refresh" class="h-4 w-4 text-emerald-500" /> Equivale a:
                            </span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold text-emerald-600" x-text="equivalent"></span>
                                <span class="text-[11px] text-slate-400">(a la tasa actual)</span>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('amount_usd')" />
                    </div>

                    <!-- Fecha -->
                    <div class="space-y-1.5">
                        <x-input-label for="loan_date" value="Fecha del préstamo *" />
                        <input id="loan_date" name="loan_date" type="date" required value="{{ $currentDate }}"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('loan_date')" />
                    </div>

                    <!-- Notas -->
                    <div class="space-y-1.5">
                        <x-input-label for="notes" value="Notas" />
                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                  placeholder="Motivo o detalles del préstamo (opcional)..."
                                  class="block w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">{{ old('notes', $loan?->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" />
                    </div>
                </div>
            </x-card>

            <div class="flex flex-col gap-2 pt-1">
                <button type="submit"
                        class="flex h-[54px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 to-brand-400 text-sm font-extrabold uppercase tracking-wide text-white shadow-lg shadow-brand-500/30 transition-all hover:opacity-95 active:scale-[0.985]">
                    <x-icon name="check" class="h-5 w-5" />
                    {{ $editing ? 'Guardar cambios' : 'Registrar préstamo' }}
                </button>
                <a href="{{ $editing ? route('loans.show', $loan) : route('loans.index') }}"
                   class="flex h-12 w-full items-center justify-center rounded-xl bg-white text-sm font-semibold text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 active:scale-[0.985]">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
