<x-admin-layout title="Nueva barbería">
    <div class="mx-auto max-w-2xl space-y-4">
        <div>
            <a href="{{ route('admin.companies.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600">
                <x-icon name="arrow-left" class="h-4 w-4" /> Volver a barberías
            </a>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-800">Nueva barbería</h1>
            <p class="mt-0.5 text-xs text-slate-400">Cada instancia tiene sus propios usuarios, ventas, inventario y configuración.</p>
        </div>

        <form method="POST" action="{{ route('admin.companies.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @include('admin.companies._form', ['company' => $company])

            <section class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <x-icon name="user" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Administrador de la instancia</h2>
                        <p class="text-xs text-slate-400">Con esta cuenta se gestionará la barbería.</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <x-input-label for="admin_name" value="Nombre completo *" />
                    <input id="admin_name" name="admin_name" type="text" required value="{{ old('admin_name') }}"
                           placeholder="Carlos Pérez"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    <x-input-error :messages="$errors->get('admin_name')" />
                </div>

                <div class="space-y-1.5">
                    <x-input-label for="admin_email" value="Correo de acceso *" />
                    <input id="admin_email" name="admin_email" type="email" required value="{{ old('admin_email') }}"
                           placeholder="admin@barberia.com"
                           class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    <x-input-error :messages="$errors->get('admin_email')" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <x-input-label for="admin_password" value="Contraseña *" />
                        <input id="admin_password" name="admin_password" type="password" required autocomplete="new-password"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                        <x-input-error :messages="$errors->get('admin_password')" />
                    </div>
                    <div class="space-y-1.5">
                        <x-input-label for="admin_password_confirmation" value="Confirmar contraseña *" />
                        <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required autocomplete="new-password"
                               class="block h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500" />
                    </div>
                </div>
            </section>

            <button type="submit"
                    class="flex h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 to-brand-400 text-sm font-extrabold uppercase tracking-wider text-white shadow-lg shadow-brand-500/30 transition-all hover:opacity-95 active:scale-[0.985]">
                <x-icon name="check" class="h-5 w-5" /> Crear barbería
            </button>
        </form>
    </div>
</x-admin-layout>
