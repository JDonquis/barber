@php
    $statusOptions = [
        'con_saldo' => 'Con saldo',
        'pendiente' => 'Pendientes',
        'parcial' => 'Parciales',
        'pagado' => 'Pagados',
        'todos' => 'Todos',
    ];

    $preserve = array_filter([
        'barber_id' => $barberId,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Préstamos</h1>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4">
        <!-- Encabezado -->
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-brand-600">
                    <x-icon name="wallet" class="h-4 w-4" /> Adelantos al equipo
                </span>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Préstamos</h2>
                <p class="mt-0.5 text-xs leading-snug text-slate-400">Registra el dinero adelantado a tus barberos y controla sus pagos.</p>
            </div>
            <a href="{{ route('loans.create') }}"
               class="flex h-12 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-4 text-sm font-bold text-white shadow-md shadow-brand-500/25 transition hover:bg-brand-400 active:scale-95">
                <x-icon name="plus" class="h-4 w-4" /> Nuevo
            </a>
        </div>

        <!-- Resumen -->
        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2 flex items-center justify-between rounded-xl bg-slate-800 p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white">
                        <x-icon name="wallet" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-300">Saldo por cobrar</p>
                        <p class="text-[11px] text-slate-400">{{ $stats['active'] }} préstamo(s) con saldo</p>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <span class="block text-2xl font-extrabold tracking-tight text-white">{{ price_money($stats['outstanding']) }}</span>
                    @if ($usdConversionEnabled)
                        <span class="text-[11px] font-medium text-slate-400">{{ ves(to_ves($stats['outstanding'], $currentRate)) }} aprox.</span>
                    @endif
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <span class="text-xs text-slate-400">Total prestado</span>
                <p class="mt-1 text-lg font-extrabold tracking-tight text-slate-800">{{ price_money($stats['lent']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <span class="text-xs text-slate-400">Total recuperado</span>
                <p class="mt-1 text-lg font-extrabold tracking-tight text-emerald-600">{{ price_money($stats['paid']) }}</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="space-y-2">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                @foreach ($statusOptions as $key => $label)
                    <a href="{{ route('loans.index', array_merge($preserve, ['status' => $key])) }}"
                       class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-full px-3.5 text-xs font-semibold transition active:scale-95
                              {{ $status === $key ? 'bg-brand-500 text-white shadow-sm' : 'bg-white text-slate-500 ring-1 ring-slate-200 hover:text-slate-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('loans.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="status" value="{{ $status }}" />
                <select name="barber_id" onchange="this.form.submit()"
                        class="h-11 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos los barberos</option>
                    @foreach ($barbers as $barber)
                        <option value="{{ $barber->id }}" @selected((string) $barberId === (string) $barber->id)>
                            {{ $barber->name }}{{ $barber->active ? '' : ' (inactivo)' }}
                        </option>
                    @endforeach
                </select>
                @if ($barberId)
                    <a href="{{ route('loans.index', ['status' => $status]) }}"
                       class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200" title="Quitar filtro">
                        <x-icon name="x" class="h-4 w-4" />
                    </a>
                @endif
            </form>
        </div>

        <!-- Listado -->
        <section class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Préstamos registrados</span>
                <span class="text-[11px] font-semibold text-brand-600">Moneda: {{ $usdConversionEnabled ? 'USD ($)' : local_currency() }}</span>
            </div>

            <div class="space-y-2.5">
                @forelse ($loans as $loan)
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
                    @endphp

                    <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">
                                    {{ $initials }}
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $loan->barber?->name ?? 'Barbero eliminado' }}</p>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-slate-400">Prestado el {{ $loan->loan_date->format('d/m/Y') }}</p>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-sm font-extrabold text-slate-800">{{ money($loan->amount_usd, $loan->localAmount()) }}</p>
                                @if ($usdConversionEnabled)
                                    <p class="text-[10px] text-slate-400">{{ ves($loan->amount_ves) }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Pagado: <strong class="text-slate-600">{{ money($loan->paid_usd, $loan->localPaid()) }}</strong></span>
                                <span class="text-slate-400">Saldo: <strong class="{{ ($usdConversionEnabled ? $loan->balance_usd : $loan->localBalance()) > 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ money($loan->balance_usd, $loan->localBalance()) }}</strong></span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-brand-500 transition-all" style="width: {{ $usdConversionEnabled ? $loan->progress : $loan->localProgress() }}%"></div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                            <span class="text-[11px] text-slate-400">{{ $loan->payments_count }} pago(s)</span>

                            <div class="flex items-center gap-1.5">
                                @if (($usdConversionEnabled ? $loan->balance_usd : $loan->localBalance()) > 0)
                                    <a href="{{ route('loans.show', $loan) }}#registrar-pago"
                                       class="inline-flex h-9 items-center gap-1 rounded-lg bg-brand-500 px-3 text-xs font-bold text-white transition hover:bg-brand-400">
                                        <x-icon name="plus" class="h-3.5 w-3.5" /> Registrar pago
                                    </a>
                                @endif
                                <a href="{{ route('loans.show', $loan) }}"
                                   class="inline-flex h-9 items-center gap-1 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                                    Ver detalle
                                </a>
                                <a href="{{ route('loans.edit', $loan) }}" title="Editar préstamo"
                                   class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition hover:bg-slate-200">
                                    <x-icon name="edit" class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="flex flex-col items-center justify-center rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-slate-200">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-500">
                            <x-icon name="wallet" class="h-7 w-7" />
                        </span>
                        <p class="mt-3 text-base font-bold text-slate-800">Sin préstamos</p>
                        <p class="mt-1 max-w-xs text-xs text-slate-400">No hay préstamos para este filtro. Registra uno con el botón Nuevo.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($loans->hasPages())
            <div class="flex flex-col items-center gap-2 pb-2">
                <span class="text-xs text-slate-400">
                    Mostrando {{ $loans->firstItem() }}-{{ $loans->lastItem() }} de {{ $loans->total() }} préstamos
                </span>
                @if ($loans->hasMorePages())
                    <a href="{{ $loans->nextPageUrl() }}"
                       class="flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50">
                        <x-icon name="chevron-down" class="h-4 w-4 text-brand-500" />
                        Cargar préstamos anteriores
                    </a>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
