@php
    $retention = $retention ?? null;
    $editing = (bool) ($retention && $retention->exists);
    $currentAmount = (float) old('amount_usd', $retention?->amount_usd ?? 0);
    $currentBarber = old('barber_id', $retention?->barber_id);
    $currentFrequency = old('frequency', $retention?->frequency ?? \App\Models\Retention::FREQUENCY_MONTHLY);
    $currentActive = old('active', $retention?->active ?? true);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Retenciones</h1>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4" x-data="{
        entry: {{ $currentAmount }},
        currency: 'USD',
        rate: {{ (float) $currentRate }},
        localCode: @js(local_currency()),
        localSymbol: @js(local_currency_symbol()),
        formatVes(value) {
            return this.localSymbol + ' ' + Number(value || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatUsd(value) {
            return '$' + Number(value || 0).toFixed(2);
        },
        get usdValue() {
            return this.currency === this.localCode ? Number(this.entry || 0) / this.rate : Number(this.entry || 0);
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
            <a href="{{ route('retentions.index') }}"
               class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100 active:scale-95" title="Volver a retenciones">
                <x-icon name="arrow-left" class="h-5 w-5" />
            </a>
            <div class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 shadow-sm ring-1 ring-slate-200">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ local_currency() }}:</span>
                <span class="text-[11px] font-bold text-emerald-600">{{ ves($currentRate) }}</span>
            </div>
        </div>

        <!-- Título -->
        <div>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-rose-600">Retenciones · {{ $editing ? 'Edición' : 'Registro' }}</span>
            <h2 class="text-xl font-extrabold tracking-tight text-slate-800">{{ $editing ? 'Editar concepto' : 'Nuevo concepto' }}</h2>
            <p class="mt-0.5 text-xs text-slate-400">Define el nombre, monto y cada cuánto se descuenta de las ganancias del barbero.</p>
        </div>

        <form method="POST" action="{{ $editing ? route('retentions.update', $retention) : route('retentions.store') }}" class="space-y-4">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <x-card>
                <div class="space-y-5 p-4 sm:p-5">
                    <!-- Nombre -->
                    <div class="space-y-1.5">
                        <x-input-label for="name" value="Nombre del concepto *" />
                        <input id="name" name="name" type="text" required autofocus
                               value="{{ old('name', $retention?->name) }}"
                               placeholder="Ej. Cuota de herramientas, Uniforme, Fondo de ahorro..."
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <!-- Barbero -->
                    <div class="space-y-1.5">
                        <x-input-label for="barber_id" value="Aplicar a" />
                        <select id="barber_id" name="barber_id"
                                class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                            <option value="">Todos los barberos</option>
                            @foreach ($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected((string) $currentBarber === (string) $barber->id)>
                                    {{ $barber->name }}{{ $barber->active ? '' : ' (inactivo)' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400">Si eliges un barbero, solo se descontará de sus liquidaciones. Si dejas “Todos”, aplica a cada barbero con ventas en el período.</p>
                        <x-input-error :messages="$errors->get('barber_id')" />
                    </div>

                    <!-- Monto -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <x-input-label for="amount" value="Monto *" />
                            <div class="inline-flex rounded-lg bg-slate-100 p-0.5">
                                <button type="button" @click="setCurrency('USD')"
                                        :class="currency === 'USD' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'"
                                        class="rounded-md px-3 py-1 text-xs font-bold uppercase tracking-wide transition">USD</button>
                                <button type="button" @click="setCurrency(localCode)"
                                        :class="currency === localCode ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'"
                                        class="rounded-md px-3 py-1 text-xs font-bold uppercase tracking-wide transition"><span x-text="localCode"></span></button>
                            </div>
                        </div>

                        <input type="hidden" name="amount_usd" :value="usdValue.toFixed(2)">

                        <div class="relative flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 transition focus-within:border-brand-500 focus-within:bg-white">
                            <span class="select-none pr-2 text-lg font-extrabold text-rose-500" x-text="currency === localCode ? localSymbol : '$'"></span>
                            <input id="amount" type="number" step="0.01" min="0.01" required x-ref="amountInput"
                                   x-model.number="entry"
                                   class="h-14 w-full border-0 bg-transparent p-0 text-xl font-extrabold tracking-tight text-slate-800 focus:ring-0" />
                            <button type="button" @click="entry = 0; $refs.amountInput.focus()"
                                    class="p-2 text-slate-400 transition hover:text-slate-700" title="Limpiar monto">
                                <x-icon name="x" class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="mt-1 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-slate-100">
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

                    <!-- Frecuencia -->
                    <div class="space-y-1.5">
                        <x-input-label for="frequency" value="¿Cada cuánto se descuenta? *" />
                        <select id="frequency" name="frequency" required
                                class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                            @foreach (\App\Models\Retention::FREQUENCIES as $value => $label)
                                <option value="{{ $value }}" @selected($currentFrequency === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400">El monto se prorratea según los días del cierre. Ejemplo: una retención semanal de $7 en una liquidación diaria descuenta $1.</p>
                        <x-input-error :messages="$errors->get('frequency')" />
                    </div>

                    <!-- Estado -->
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
                        <div class="pr-3">
                            <span class="text-sm font-semibold text-slate-700">Retención activa</span>
                            <span class="block text-xs text-slate-400">Si la desactivas, dejará de aplicarse en los próximos cierres.</span>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" class="peer sr-only" @checked($currentActive)>
                            <span class="relative h-7 w-12 rounded-full bg-slate-200 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-6 after:w-6 after:rounded-full after:bg-white after:shadow after:transition-all after:content-[''] peer-checked:bg-rose-500 peer-checked:after:translate-x-full"></span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('active')" />
                </div>
            </x-card>

            <!-- Acciones -->
            <div class="flex flex-col gap-2 pt-1">
                <button type="submit"
                        class="flex h-[54px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 to-brand-400 text-sm font-extrabold uppercase tracking-wide text-white shadow-lg shadow-brand-500/30 transition-all hover:opacity-95 active:scale-[0.985]">
                    <x-icon name="check" class="h-5 w-5" />
                    {{ $editing ? 'Guardar cambios' : 'Crear concepto' }}
                </button>
                <a href="{{ route('retentions.index') }}"
                   class="flex h-12 w-full items-center justify-center rounded-xl bg-white text-sm font-semibold text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 active:scale-[0.985]">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
