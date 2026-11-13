@php
    $user = $user ?? auth()->user();
    $words = preg_split('/\s+/', trim($user->name));
    $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1));
@endphp

<x-admin-layout title="Mi perfil">
    <div class="mx-auto max-w-2xl space-y-4">
        <div>
            <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-brand-600">
                <x-icon name="user" class="h-4 w-4" /> Configuración de usuario
            </span>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-800">Mi perfil</h1>
            <p class="mt-0.5 text-xs text-slate-400">Administra tu información y las credenciales de acceso al panel de la plataforma.</p>
        </div>

        <!-- Datos de la cuenta -->
        <section class="relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="pointer-events-none absolute -top-12 -right-12 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl"></div>
            <div class="relative z-10 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-brand-100 text-3xl font-extrabold text-brand-600 shadow-[0_0_24px_rgba(245,158,11,0.25)]">
                    {{ $initials }}
                </div>

                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                        <h2 class="truncate text-lg font-bold text-slate-800">{{ $user->name }}</h2>
                        <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-700">
                            Superadministrador
                        </span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center justify-center gap-1.5 text-xs text-slate-400 sm:justify-start">
                        <x-icon name="check" class="h-4 w-4 text-emerald-500" />
                        <span class="font-medium text-emerald-600">Administrador de la plataforma</span>
                        <span>•</span>
                        <span>ID #{{ $user->id }}</span>
                    </div>
                    <div class="mt-3 space-y-1.5 rounded-lg bg-slate-50 p-2.5">
                        <div class="flex items-center gap-2 text-xs">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 text-brand-500" />
                            <span class="truncate text-slate-700">{{ $user->email }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seguridad -->
        <x-card title="Seguridad" description="Cambia la contraseña de acceso a tu cuenta">
            <div class="p-5">
                @include('profile.partials.update-password-form', ['action' => route('admin.profile.password.update')])
            </div>
        </x-card>
    </div>
</x-admin-layout>
