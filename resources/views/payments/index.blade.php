@php
    $statusLabel = $company->is_exempt
        ? 'Exonerado'
        : ($pendingPayment
            ? 'Pago en revisión'
            : ($company->isOverdue() ? 'Pago vencido' : 'Al día'));

    $statusClass = $company->is_exempt
        ? 'bg-slate-100 text-slate-500'
        : ($pendingPayment
            ? 'bg-amber-100 text-amber-700'
            : ($company->isOverdue() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'));
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Pagos y suscripción</h1>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4"
         x-data="{
            copyText: @js($copyText),
            copied: false,
            copy() {
                const done = () => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2200);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(this.copyText).then(done).catch(() => this.fallbackCopy(done));
                } else {
                    this.fallbackCopy(done);
                }
            },
            fallbackCopy(done) {
                const field = document.createElement('textarea');
                field.value = this.copyText;
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.focus();
                field.select();
                try { document.execCommand('copy'); done(); } catch (error) {}
                document.body.removeChild(field);
            }
         }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-brand-600">
                    <span class="h-2 w-2 rounded-full bg-brand-500"></span> Suscripción mensual
                </span>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Pago de tu barbería</h2>
                <p class="mt-0.5 text-xs text-slate-400">Reporta tu pago móvil y sube el capture para que sea validado.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide {{ $statusClass }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $company->is_exempt ? 'bg-slate-400' : ($pendingPayment ? 'bg-amber-500' : ($company->isOverdue() ? 'bg-rose-500' : 'bg-emerald-500')) }}"></span>
                {{ $statusLabel }}
            </span>
        </div>

        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                        <x-icon name="card" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mensualidad SaaS</p>
                        <p class="text-2xl font-extrabold tracking-tight text-slate-800">{{ usd($monthlyFee) }}</p>
                        <p class="text-xs text-slate-400">≈ {{ format_currency('VES', $amountVes) }} a tasa {{ number_format($rate, 2, ',', '.') }} Bs/USD</p>
                    </div>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    <p><span class="font-semibold text-slate-700">Próximo vencimiento:</span>
                        {{ $company->is_exempt ? 'Exonerado' : ($company->next_payment_due_at?->format('d/m/Y') ?? 'Sin fecha') }}
                    </p>
                    <p class="mt-1"><span class="font-semibold text-slate-700">Último pago:</span> {{ $company->last_payment_at?->format('d/m/Y') ?? '—' }}</p>
                </div>
            </div>
        </section>

        @if ($company->is_exempt)
            <section class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <x-icon name="check" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-sm font-bold text-emerald-800">Barbería exonerada</h3>
                    <p class="mt-1 text-xs text-emerald-700">Esta instancia no tiene cobro mensual. No necesitas reportar pagos.</p>
                </div>
            </section>
        @else
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <x-icon name="phone" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Datos para pago móvil</h3>
                        <p class="text-xs text-slate-400">Realiza el pago y luego sube el capture.</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Cédula</span>
                        <span class="text-sm font-bold text-slate-800">{{ $paymentData['cedula'] }}</span>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Banco</span>
                        <span class="text-sm font-bold text-slate-800">{{ $paymentData['bank'] }}</span>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Teléfono</span>
                        <span class="text-sm font-bold text-slate-800">{{ $paymentData['phone'] }}</span>
                    </div>
                </div>

                <button type="button" @click="copy()"
                        class="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-slate-100 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 active:scale-[0.985]">
                    <x-icon name="copy" class="h-4 w-4 text-brand-500" />
                    <span x-text="copied ? 'Datos copiados' : 'Copiar todos los datos'"></span>
                </button>
            </section>

            @if ($pendingPayment)
                <section class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <x-icon name="clock" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <h3 class="text-sm font-bold text-amber-800">Pago en revisión</h3>
                        <p class="mt-1 text-xs text-amber-700">
                            Enviaste un pago el {{ $pendingPayment->paid_at?->format('d/m/Y') ?? $pendingPayment->created_at?->format('d/m/Y') }}.
                            Espera a que el administrador lo valide para poder enviar otro.
                        </p>
                    </div>
                </section>
            @else
                <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                            <x-icon name="plus" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Reportar pago</h3>
                            <p class="text-xs text-slate-400">Monto a pagar: {{ usd($monthlyFee) }} · {{ format_currency('VES', $amountVes) }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <x-input-label for="paid_at" value="Fecha del pago *" />
                                <input id="paid_at" name="paid_at" type="date" required
                                       value="{{ old('paid_at', now()->toDateString()) }}"
                                       class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                                <x-input-error :messages="$errors->get('paid_at')" />
                            </div>

                            <div class="space-y-1.5">
                                <x-input-label for="reference" value="Referencia" />
                                <input id="reference" name="reference" type="text" maxlength="60"
                                       value="{{ old('reference') }}" placeholder="Ej: 123456789"
                                       class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                                <x-input-error :messages="$errors->get('reference')" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <x-input-label for="screenshot" value="Capture del pago *" />
                            <input id="screenshot" name="screenshot" type="file" required
                                   accept="image/png,image/jpeg,image/webp"
                                   class="block w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-4 file:py-3 file:text-xs file:font-bold file:text-white hover:file:bg-brand-400" />
                            <p class="text-[11px] text-slate-400">PNG, JPG o WEBP · máximo 5 MB.</p>
                            <x-input-error :messages="$errors->get('screenshot')" />
                        </div>

                        <div class="space-y-1.5">
                            <x-input-label for="notes" value="Nota (opcional)" />
                            <textarea id="notes" name="notes" rows="2" maxlength="500"
                                      placeholder="Datos adicionales del pago..."
                                      class="block w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" />
                        </div>

                        <button type="submit"
                                class="flex h-[50px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 to-brand-400 text-sm font-bold text-white shadow-lg shadow-brand-500/25 transition hover:opacity-95 active:scale-[0.985]">
                            <x-icon name="check" class="h-5 w-5" /> Enviar pago a revisión
                        </button>
                    </form>
                </section>
            @endif
        @endif

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800">Historial de pagos</h3>
                <span class="text-[11px] text-slate-400">{{ $payments->total() }} registro(s)</span>
            </div>

            @forelse ($payments as $payment)
                @php
                    $badge = match ($payment->status) {
                        \App\Models\CompanyPayment::STATUS_APPROVED => 'bg-emerald-100 text-emerald-700',
                        \App\Models\CompanyPayment::STATUS_REJECTED => 'bg-rose-100 text-rose-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-800">{{ usd($payment->amount_usd) }}</span>
                                <span class="text-xs text-slate-400">{{ format_currency('VES', $payment->amount_ves) }}</span>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ ucfirst($payment->status) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                Período {{ $payment->period }} · Pagado el {{ $payment->paid_at?->format('d/m/Y') ?? '—' }}
                                @if ($payment->reference) · Ref. {{ $payment->reference }} @endif
                            </p>
                            @if ($payment->notes)
                                <p class="mt-1 text-xs text-slate-400">Nota: {{ $payment->notes }}</p>
                            @endif
                            @if ($payment->review_notes)
                                <p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    <span class="font-semibold text-slate-600">Respuesta del administrador:</span> {{ $payment->review_notes }}
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($payment->screenshot_url)
                                <a href="{{ $payment->screenshot_url }}" target="_blank" rel="noopener"
                                   class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                                    <x-icon name="eye" class="h-4 w-4 text-brand-500" /> Capture
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white px-6 py-12 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    Todavía no has reportado ningún pago.
                </div>
            @endforelse

            @if ($payments->hasPages())
                <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
                    {{ $payments->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
