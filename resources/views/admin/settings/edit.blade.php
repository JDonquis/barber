<x-admin-layout title="Configuración SaaS">
    <div class="mx-auto max-w-2xl space-y-4">
        <div>
            <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600">
                <x-icon name="arrow-left" class="h-4 w-4" /> Volver a pagos
            </a>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-800">Configuración SaaS</h1>
            <p class="mt-0.5 text-xs text-slate-400">Tasa de cobro y datos de pago móvil mostrados a las instancias.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                        <x-icon name="dollar" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Tasa y mensualidad</h2>
                        <p class="text-xs text-slate-400">La tasa PayPal se usa para calcular el monto en bolívares.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <x-input-label for="saas_paypal_rate" value="Tasa PayPal (Bs/USD) *" />
                        <input id="saas_paypal_rate" name="saas_paypal_rate" type="number" step="0.0001" min="0.0001" required
                               value="{{ old('saas_paypal_rate', $values['saas_paypal_rate'] ?? 950) }}"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('saas_paypal_rate')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="saas_monthly_fee" value="Mensualidad por defecto (USD) *" />
                        <input id="saas_monthly_fee" name="saas_monthly_fee" type="number" step="0.01" min="0" required
                               value="{{ old('saas_monthly_fee', $values['saas_monthly_fee'] ?? 5) }}"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('saas_monthly_fee')" />
                    </div>
                </div>

                <p class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    Ejemplo: una instancia de $5 con tasa {{ number_format((float) old('saas_paypal_rate', $values['saas_paypal_rate'] ?? 950), 2, ',', '.') }}
                    pagaría <strong class="text-slate-700">Bs. {{ number_format(5 * (float) old('saas_paypal_rate', $values['saas_paypal_rate'] ?? 950), 2, ',', '.') }}</strong>.
                </p>
            </section>

            <section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <x-icon name="phone" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Datos de pago móvil</h2>
                        <p class="text-xs text-slate-400">Se muestran en el módulo de pagos de cada barbería.</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <x-input-label for="payment_cedula" value="Cédula *" />
                    <input id="payment_cedula" name="payment_cedula" type="text" required maxlength="30"
                           value="{{ old('payment_cedula', $values['payment_cedula'] ?? '30847627') }}"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    <x-input-error :messages="$errors->get('payment_cedula')" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <x-input-label for="payment_bank" value="Banco *" />
                        <input id="payment_bank" name="payment_bank" type="text" required maxlength="60"
                               value="{{ old('payment_bank', $values['payment_bank'] ?? '0102 Venezuela') }}"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('payment_bank')" />
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="payment_phone" value="Teléfono *" />
                        <input id="payment_phone" name="payment_phone" type="text" required maxlength="30"
                               value="{{ old('payment_phone', $values['payment_phone'] ?? '04125800610') }}"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('payment_phone')" />
                    </div>
                </div>
            </section>

            <button type="submit"
                    class="flex h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 to-brand-400 text-sm font-extrabold uppercase tracking-wider text-white shadow-lg shadow-brand-500/30 transition-all hover:opacity-95 active:scale-[0.985]">
                <x-icon name="check" class="h-5 w-5" /> Guardar configuración
            </button>
        </form>
    </div>
</x-admin-layout>
