<x-admin-layout title="Pagos SaaS">
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-brand-600">
                    <span class="h-2 w-2 rounded-full bg-brand-500"></span> Cobros de la plataforma
                </span>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-800">Pagos de barberías</h1>
                <p class="mt-0.5 text-xs text-slate-400">Revisa los captures y valida la mensualidad de cada instancia.</p>
            </div>

            <a href="{{ route('admin.settings.edit') }}"
               class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 text-xs font-bold text-slate-700 transition hover:bg-slate-200">
                <x-icon name="cog" class="h-4 w-4 text-brand-500" /> Configurar tasa
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['label' => 'Por revisar', 'value' => $stats['pending'], 'icon' => 'clock', 'tone' => 'amber'],
                ['label' => 'Aprobados del mes', 'value' => usd($stats['approved_month']), 'icon' => 'check', 'tone' => 'emerald'],
                ['label' => 'Rechazados', 'value' => $stats['rejected'], 'icon' => 'x', 'tone' => 'rose'],
                ['label' => 'Barberías vencidas', 'value' => $stats['overdue_companies'], 'icon' => 'alert', 'tone' => 'rose'],
            ] as $stat)
                @php
                    $tone = match ($stat['tone']) {
                        'amber' => 'bg-amber-100 text-amber-600',
                        'emerald' => 'bg-emerald-100 text-emerald-600',
                        default => 'bg-rose-100 text-rose-600',
                    };
                @endphp
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $tone }}">
                        <x-icon :name="$stat['icon']" class="h-4 w-4" />
                    </span>
                    <p class="mt-3 text-xl font-extrabold tracking-tight text-slate-800">{{ $stat['value'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar barbería, referencia o período..."
                       class="h-12 w-full rounded-xl border border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500" />
            </div>
            <select name="status"
                    class="h-12 rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="todos" @selected($status === 'todos')>Todos los estados</option>
                <option value="pendiente" @selected($status === 'pendiente')>Por revisar</option>
                <option value="aprobado" @selected($status === 'aprobado')>Aprobados</option>
                <option value="rechazado" @selected($status === 'rechazado')>Rechazados</option>
            </select>
            <button type="submit"
                    class="h-12 rounded-xl bg-brand-500 px-5 text-sm font-bold text-white transition hover:bg-brand-400">
                Filtrar
            </button>
        </form>

        <div class="space-y-3">
            @forelse ($payments as $payment)
                @php
                    $badge = match ($payment->status) {
                        \App\Models\CompanyPayment::STATUS_APPROVED => 'bg-emerald-100 text-emerald-700',
                        \App\Models\CompanyPayment::STATUS_REJECTED => 'bg-rose-100 text-rose-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <img src="{{ $payment->company?->logo_url ?? asset('logo.jpeg') }}" alt="{{ $payment->company?->name }}"
                                 class="h-12 w-12 shrink-0 rounded-xl border border-slate-200 bg-slate-50 object-contain p-1" />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-sm font-bold text-slate-800">{{ $payment->company?->name ?? 'Barbería eliminada' }}</h2>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ ucfirst($payment->status) }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    /{{ $payment->company?->slug }} · Período {{ $payment->period }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                    <span><strong class="text-slate-700">{{ usd($payment->amount_usd) }}</strong></span>
                                    <span><strong class="text-slate-700">{{ format_currency('VES', $payment->amount_ves) }}</strong></span>
                                    <span>Tasa {{ number_format((float) $payment->exchange_rate, 2, ',', '.') }}</span>
                                    <span>Pagado: {{ $payment->paid_at?->format('d/m/Y') ?? '—' }}</span>
                                    @if ($payment->reference)
                                        <span>Ref. {{ $payment->reference }}</span>
                                    @endif
                                    <span>Enviado por {{ $payment->user?->name ?? '—' }}</span>
                                </div>
                                @if ($payment->notes)
                                    <p class="mt-2 text-xs text-slate-400">Nota: {{ $payment->notes }}</p>
                                @endif
                                @if ($payment->review_notes)
                                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                        <span class="font-semibold text-slate-600">Revisión:</span> {{ $payment->review_notes }}
                                        @if ($payment->reviewedBy) · {{ $payment->reviewedBy->name }} @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 lg:items-end">
                            <div class="flex items-center gap-2">
                                @if ($payment->screenshot_url)
                                    <a href="{{ $payment->screenshot_url }}" target="_blank" rel="noopener"
                                       class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                                        <x-icon name="eye" class="h-4 w-4 text-brand-500" /> Ver capture
                                    </a>
                                @endif
                            </div>

                            @if ($payment->isPending())
                                <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                                    <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-500 px-4 text-xs font-bold text-white transition hover:bg-emerald-600">
                                            <x-icon name="check" class="h-4 w-4" /> Aprobar
                                        </button>
                                    </form>

                                    <details class="group">
                                        <summary class="flex h-10 cursor-pointer list-none items-center justify-center gap-1.5 rounded-lg bg-rose-50 px-4 text-xs font-bold text-rose-600 transition hover:bg-rose-100">
                                            <x-icon name="x" class="h-4 w-4" /> Rechazar
                                        </summary>
                                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="mt-2 w-full max-w-xs space-y-2 rounded-xl bg-slate-50 p-3">
                                            @csrf
                                            <textarea name="review_notes" rows="2" required maxlength="500" placeholder="Motivo del rechazo *"
                                                      class="block w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <button type="submit"
                                                    class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-rose-500 text-xs font-bold text-white transition hover:bg-rose-600">
                                                Confirmar rechazo
                                            </button>
                                        </form>
                                    </details>
                                </div>
                            @elseif ($payment->reviewed_at)
                                <span class="text-[11px] text-slate-400">Revisado {{ $payment->reviewed_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl bg-white px-6 py-14 text-center shadow-sm ring-1 ring-slate-200">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <x-icon name="card" class="h-8 w-8" />
                    </span>
                    <p class="mt-3 text-base font-bold text-slate-800">Sin pagos para mostrar</p>
                    <p class="mt-1 max-w-sm text-xs text-slate-400">Cuando las barberías reporten sus pagos aparecerán aquí.</p>
                </div>
            @endforelse
        </div>

        @if ($payments->hasPages())
            <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
