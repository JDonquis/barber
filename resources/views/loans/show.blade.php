@php
    $initials = collect(explode(' ', trim($loan->barber?->name ?? '?')))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');

    [$statusLabel, $statusClass] = match ($loan->status) {
        \App\Models\Loan::STATUS_PAID => ['Pagado', 'bg-emerald-100 text-emerald-700'],
        \App\Models\Loan::STATUS_PARTIAL => ['Parcial', 'bg-sky-100 text-sky-700'],
        default => ['Pendiente', 'bg-amber-100 text-amber-700'],
    };

    $paymentAmount = (float) old('amount_usd', 0);
    $paymentDate = old('payment_date', now()->toDateString());
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Préstamos</h1>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4">
        <!-- Subcabecera -->
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('loans.index') }}"
               class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100 active:scale-95" title="Volver a préstamos">
                <x-icon name="arrow-left" class="h-5 w-5" />
            </a>

            <div class="flex items-center gap-1.5">
                <a href="{{ route('loans.edit', $loan) }}"
                   class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-slate-100 px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-200">
                    <x-icon name="edit" class="h-4 w-4" /> Editar
                </a>

                @if ($loan->payments->isEmpty())
                    <form method="POST" action="{{ route('loans.destroy', $loan) }}" onsubmit="return confirm('¿Eliminar este préstamo?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-500 transition hover:bg-rose-100" title="Eliminar préstamo">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Resumen del préstamo -->
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                        {{ $initials }}
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-lg font-extrabold tracking-tight text-slate-800">{{ $loan->barber?->name ?? 'Barbero eliminado' }}</h2>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-400">Préstamo del {{ $loan->loan_date->format('d/m/Y') }}</p>
                        @if ($loan->createdBy)
                            <p class="text-[11px] text-slate-400">Registrado por {{ $loan->createdBy->name }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-slate-50 p-2.5 text-center ring-1 ring-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Prestado</span>
                    <span class="mt-0.5 block text-sm font-extrabold text-slate-800">{{ money($loan->amount_usd, $loan->localAmount()) }}</span>
                    @if ($usdConversionEnabled)
                        <span class="block text-[10px] text-slate-400">{{ ves($loan->amount_ves) }}</span>
                    @endif
                </div>
                <div class="rounded-xl bg-emerald-50 p-2.5 text-center ring-1 ring-emerald-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wide text-emerald-700">Pagado</span>
                    <span class="mt-0.5 block text-sm font-extrabold text-emerald-700">{{ money($loan->paid_usd, $loan->localPaid()) }}</span>
                </div>
                <div class="rounded-xl bg-brand-50 p-2.5 text-center ring-1 ring-brand-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wide text-brand-700">Saldo</span>
                    <span class="mt-0.5 block text-sm font-extrabold text-brand-700">{{ money($loan->balance_usd, $loan->localBalance()) }}</span>
                </div>
            </div>

            <div class="mt-3">
                <div class="flex items-center justify-between text-[11px] text-slate-400">
                    <span>Progreso de pago</span>
                    <span>{{ $usdConversionEnabled ? $loan->progress : $loan->localProgress() }}%</span>
                </div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-brand-500 transition-all" style="width: {{ $usdConversionEnabled ? $loan->progress : $loan->localProgress() }}%"></div>
                </div>
            </div>

            @if ($loan->notes)
                <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2.5 text-xs text-slate-500">{{ $loan->notes }}</p>
            @endif
        </section>

        <!-- Registrar pago -->
        @if (($usdConversionEnabled ? $loan->balance_usd : $loan->localBalance()) > 0)
            <section id="registrar-pago" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"
                     x-data="{
                        entry: {{ $paymentAmount }},
                        conversionEnabled: {{ $usdConversionEnabled ? 'true' : 'false' }},
                        currency: @js(usd_conversion_enabled() ? 'USD' : local_currency()),
                        rate: {{ $usdConversionEnabled ? (float) $currentRate : 1 }},
                        localCode: @js(local_currency()),
                        localSymbol: @js(local_currency_symbol()),
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
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <x-icon name="plus" class="h-4 w-4" />
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Registrar pago</h3>
                        <p class="text-xs text-slate-400">Saldo pendiente: {{ money($loan->balance_usd, $loan->localBalance()) }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('loans.payments.store', $loan) }}" class="mt-3 space-y-3">
                    @csrf

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <x-input-label for="payment_amount" :value="usd_conversion_enabled() ? 'Monto del pago *' : 'Monto del pago en '.local_currency().' *'" />
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
                        <input type="hidden" name="exchange_rate" value="{{ $usdConversionEnabled ? (float) $currentRate : (float) $loan->exchange_rate }}">

                        <div class="relative flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 transition focus-within:border-brand-500 focus-within:bg-white">
                            <span class="select-none pr-2 text-base font-extrabold text-emerald-600" x-text="currency === localCode ? localSymbol : '$'"></span>
                            <input id="payment_amount" type="number" step="0.01" min="0.01" required
                                   x-model.number="entry"
                                   class="h-12 w-full border-0 bg-transparent p-0 text-lg font-extrabold tracking-tight text-slate-800 focus:ring-0" />
                        </div>

                        <div x-show="conversionEnabled" x-cloak class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 ring-1 ring-slate-100">
                            <span class="text-[11px] text-slate-400">Equivale a</span>
                            <span class="text-xs font-bold text-emerald-600" x-text="equivalent"></span>
                        </div>
                        <x-input-error :messages="$errors->get('amount_usd')" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1.5">
                            <x-input-label for="payment_date" value="Fecha *" />
                            <input id="payment_date" name="payment_date" type="date" required value="{{ $paymentDate }}"
                                   class="block h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        </div>
                        <div class="space-y-1.5">
                            <x-input-label for="payment_method" value="Método" />
                            <select id="payment_method" name="payment_method"
                                    class="block h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                                <option value="">Sin especificar</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="payment_notes" value="Nota (opcional)" />
                        <input id="payment_notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}"
                               placeholder="Detalle del abono..."
                               class="block h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    </div>

                    <button type="submit"
                            class="flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-500 text-sm font-bold text-white transition hover:bg-emerald-600 active:scale-[0.985]">
                        <x-icon name="check" class="h-4 w-4" /> Registrar pago
                    </button>
                </form>
            </section>
        @else
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <x-icon name="check-circle" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-bold text-emerald-800">Préstamo pagado</p>
                    <p class="text-xs text-emerald-700">Este préstamo ya no tiene saldo pendiente.</p>
                </div>
            </div>
        @endif

        <!-- Historial de pagos -->
        <section class="space-y-2">
            <div class="flex items-center justify-between px-1">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Historial de pagos</span>
                <span class="text-[11px] text-slate-400">{{ $loan->payments->count() }} pago(s)</span>
            </div>

            @forelse ($loan->payments as $payment)
                <div class="flex items-start justify-between gap-3 rounded-xl bg-white p-3.5 shadow-sm ring-1 ring-slate-200">
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-800">
                            {{ money($payment->amount_usd, $payment->localAmount()) }}
                            @if ($usdConversionEnabled)
                                <span class="ml-1 text-[11px] font-normal text-slate-400">{{ ves($payment->amount_ves) }}</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-400">
                            {{ $payment->payment_date->format('d/m/Y') }}
                            @if ($payment->payment_method) · {{ $payment->payment_method }} @endif
                            @if ($payment->createdBy) · {{ $payment->createdBy->name }} @endif
                        </p>
                        @if ($payment->notes)
                            <p class="mt-1 rounded-lg bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-500">{{ $payment->notes }}</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('loans.payments.destroy', [$loan, $payment]) }}"
                          onsubmit="return confirm('¿Eliminar este pago?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="Eliminar pago"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-500 transition hover:bg-rose-100">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            @empty
                <div class="rounded-xl bg-white px-4 py-8 text-center text-xs text-slate-400 shadow-sm ring-1 ring-slate-200">
                    Todavía no hay pagos registrados para este préstamo.
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
