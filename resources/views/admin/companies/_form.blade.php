@php($editing = $company->exists)

<div class="space-y-4">
    <section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                <x-icon name="home" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Identidad de la barbería</h2>
                <p class="text-xs text-slate-400">Nombre, logo y datos de contacto de la instancia.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <x-input-label for="name" value="Nombre de la barbería *" />
                <input id="name" name="name" type="text" required value="{{ old('name', $company->name) }}"
                       placeholder="Barbería El Rey"
                       class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div class="space-y-1.5">
                <x-input-label for="slug" value="Identificador (slug)" />
                <input id="slug" name="slug" type="text" value="{{ old('slug', $company->slug) }}"
                       placeholder="barberia-el-rey"
                       class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                <p class="text-[11px] text-slate-400">Si lo dejas vacío se genera automáticamente.</p>
                <x-input-error :messages="$errors->get('slug')" />
            </div>
        </div>

        <div class="space-y-1.5">
            <x-input-label for="logo" value="Logo" />
            <div class="flex flex-col gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 sm:flex-row sm:items-center">
                <img src="{{ $company->logo_url }}" alt="{{ $company->name ?? 'Logo' }}"
                     class="h-16 w-16 rounded-xl border border-slate-200 bg-white object-contain p-1.5" />
                <div class="min-w-0 flex-1">
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                           class="block w-full cursor-pointer text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-brand-400" />
                    <p class="mt-1 text-[11px] text-slate-400">PNG, JPG o WEBP · máximo 2 MB.</p>
                    <x-input-error :messages="$errors->get('logo')" />
                </div>
                @if ($editing && $company->logo_path)
                    <label class="flex items-center gap-2 text-xs font-medium text-rose-600">
                        <input type="checkbox" name="remove_logo" value="1"
                               class="rounded border-slate-300 text-rose-500 focus:ring-rose-500" />
                        Quitar logo actual
                    </label>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <x-input-label for="email" value="Correo de contacto" />
                <input id="email" name="email" type="email" value="{{ old('email', $company->email) }}"
                       placeholder="contacto@barberia.com"
                       class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div class="space-y-1.5">
                <x-input-label for="phone" value="Teléfono" />
                <input id="phone" name="phone" type="text" value="{{ old('phone', $company->phone) }}"
                       placeholder="+58 412 000 0000"
                       class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>
        </div>

        <div class="space-y-1.5">
            <x-input-label for="address" value="Dirección" />
            <textarea id="address" name="address" rows="2" placeholder="Av. Principal, local..."
                      class="block w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">{{ old('address', $company->address) }}</textarea>
            <x-input-error :messages="$errors->get('address')" />
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="active" value="1" @checked(old('active', $company->active ?? true))
                   class="rounded border-slate-300 text-brand-500 focus:ring-brand-500" />
            Instancia activa (los usuarios pueden iniciar sesión)
        </label>
    </section>

    <section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                <x-icon name="card" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Suscripción SaaS</h2>
                <p class="text-xs text-slate-400">Mensualidad que se cobrará a esta barbería.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <x-input-label for="monthly_fee_usd" value="Mensualidad (USD) *" />
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">$</span>
                    <input id="monthly_fee_usd" name="monthly_fee_usd" type="number" step="0.01" min="0" max="9999.99" required
                           value="{{ old('monthly_fee_usd', $company->monthly_fee_usd ?? $defaultMonthlyFee ?? 5) }}"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-4 text-sm text-slate-700 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                </div>
                <x-input-error :messages="$errors->get('monthly_fee_usd')" />
            </div>

            <div class="space-y-1.5">
                <x-input-label value="Cobro mensual" />
                <label class="flex h-12 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-600">
                    <input type="checkbox" name="is_exempt" value="1" @checked(old('is_exempt', $company->is_exempt ?? false))
                           class="rounded border-slate-300 text-emerald-500 focus:ring-emerald-500" />
                    Exonerado (no se le cobra)
                </label>
                <x-input-error :messages="$errors->get('is_exempt')" />
            </div>
        </div>

        @if ($editing)
            <div class="grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-4 text-xs text-slate-500 sm:grid-cols-3">
                <div>
                    <span class="block text-[11px] uppercase tracking-wide text-slate-400">Inicio</span>
                    <span class="font-semibold text-slate-700">{{ $company->subscription_started_at?->format('d/m/Y') ?? '—' }}</span>
                </div>
                <div>
                    <span class="block text-[11px] uppercase tracking-wide text-slate-400">Próximo pago</span>
                    <span class="font-semibold text-slate-700">{{ $company->is_exempt ? 'Exonerado' : ($company->next_payment_due_at?->format('d/m/Y') ?? '—') }}</span>
                </div>
                <div>
                    <span class="block text-[11px] uppercase tracking-wide text-slate-400">Último pago</span>
                    <span class="font-semibold text-slate-700">{{ $company->last_payment_at?->format('d/m/Y') ?? '—' }}</span>
                </div>
            </div>
        @endif
    </section>
</div>
