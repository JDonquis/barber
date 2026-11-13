<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Panel de la plataforma' }} · {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <style>[x-cloak] { display: none !important; }</style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.pwa')
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-800" data-ajax-saves>
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ route('admin.companies.index') }}" class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white">
                        <x-icon name="home" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-extrabold tracking-tight text-slate-800">Panel de la plataforma</span>
                        <span class="block truncate text-[11px] text-slate-400">{{ config('app.name') }} · SaaS</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 text-xs font-semibold md:flex">
                    <a href="{{ route('admin.companies.index') }}"
                       class="rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.companies.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        Barberías
                    </a>
                    <a href="{{ route('admin.payments.index') }}"
                       class="rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.payments.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        Pagos
                    </a>
                    <a href="{{ route('admin.settings.edit') }}"
                       class="rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.settings.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        Configuración
                    </a>
                    <a href="{{ route('admin.profile.edit') }}"
                       class="rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.profile.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                        Mi perfil
                    </a>
                </nav>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right leading-tight sm:block">
                        <p class="text-sm font-semibold text-slate-700">{{ auth()->user()->name }}</p>
                        <p class="text-[11px] text-brand-600">Superadministrador</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" data-no-ajax>
                        @csrf
                        <button type="submit"
                                class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                            <x-icon name="power" class="h-4 w-4 text-brand-500" /> Salir
                        </button>
                    </form>
                </div>
            </div>

            <div class="border-t border-slate-100 md:hidden">
                <nav class="mx-auto flex max-w-6xl items-center gap-1 overflow-x-auto px-4 py-2 text-xs font-semibold">
                    <a href="{{ route('admin.companies.index') }}"
                       class="whitespace-nowrap rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.companies.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100' }}">
                        Barberías
                    </a>
                    <a href="{{ route('admin.payments.index') }}"
                       class="whitespace-nowrap rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.payments.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100' }}">
                        Pagos
                    </a>
                    <a href="{{ route('admin.settings.edit') }}"
                       class="whitespace-nowrap rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.settings.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100' }}">
                        Configuración
                    </a>
                    <a href="{{ route('admin.profile.edit') }}"
                       class="whitespace-nowrap rounded-lg px-3 py-2 transition {{ request()->routeIs('admin.profile.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100' }}">
                        Mi perfil
                    </a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8" data-ajax-region>
            @if (session('status'))
                <div class="mb-5 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" data-flash-status>
                    <x-icon name="check" class="h-5 w-5 shrink-0" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-flash-error>
                    <x-icon name="alert" class="h-5 w-5 shrink-0" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-flash-error>
                    <p class="font-semibold">Revisa los siguientes errores:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>

        <div data-ajax-toast class="pointer-events-none fixed right-4 top-4 z-50 hidden max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg"></div>
    </body>
</html>
