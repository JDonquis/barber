<x-admin-layout title="Barberías">
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-brand-600">
                    <span class="h-2 w-2 rounded-full bg-brand-500"></span> Administración SaaS
                </span>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-800">Barberías</h1>
                <p class="mt-0.5 text-xs text-slate-400">Crea instancias independientes, asigna su identidad y controla el acceso.</p>
            </div>

            <a href="{{ route('admin.companies.create') }}"
               class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 text-sm font-bold text-white shadow-lg shadow-brand-500/25 transition hover:bg-brand-400 active:scale-[0.985]">
                <x-icon name="plus" class="h-5 w-5" /> Nueva barbería
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['label' => 'Instancias', 'value' => $stats['total'], 'icon' => 'home'],
                ['label' => 'Activas', 'value' => $stats['active'], 'icon' => 'check'],
                ['label' => 'Suspendidas', 'value' => $stats['inactive'], 'icon' => 'alert'],
                ['label' => 'Usuarios', 'value' => $stats['users'], 'icon' => 'users'],
            ] as $stat)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                        <x-icon :name="$stat['icon']" class="h-4 w-4" />
                    </span>
                    <p class="mt-3 text-2xl font-extrabold tracking-tight text-slate-800">{{ $stat['value'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.companies.index') }}" class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <x-icon name="search" class="h-5 w-5" />
            </span>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Buscar por nombre, slug o correo..."
                   class="h-12 w-full rounded-xl border border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500" />
        </form>

        <div class="space-y-3">
            @forelse ($companies as $company)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <img src="{{ $company->logo_url }}" alt="{{ $company->name }}"
                                 class="h-14 w-14 shrink-0 rounded-xl border border-slate-200 bg-slate-50 object-contain p-1.5" />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-bold text-slate-800">{{ $company->name }}</h2>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $company->active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $company->active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                        {{ $company->active ? 'Activa' : 'Suspendida' }}
                                    </span>
                                    @if ($company->is_exempt)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">Exonerado</span>
                                    @elseif ($company->pending_payments_count > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">Pago en revisión</span>
                                    @elseif ($company->isOverdue())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700">Pago vencido</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Al día</span>
                                    @endif
                                </div>
                                <p class="truncate text-xs text-slate-400">/{{ $company->slug }}{{ $company->email ? ' · '.$company->email : '' }}</p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                    <span><strong class="text-slate-700">{{ $company->users_count }}</strong> usuarios</span>
                                    <span><strong class="text-slate-700">{{ $company->services_count }}</strong> servicios</span>
                                    <span><strong class="text-slate-700">{{ $company->products_count }}</strong> productos</span>
                                    <span><strong class="text-slate-700">{{ $company->sales_count }}</strong> ventas</span>
                                    <span><strong class="text-slate-700">{{ usd($company->monthly_fee_usd) }}</strong> / mes</span>
                                    @if (! $company->is_exempt && $company->next_payment_due_at)
                                        <span>Vence <strong class="text-slate-700">{{ $company->next_payment_due_at->format('d/m/Y') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <form method="POST" action="{{ route('admin.companies.toggle', $company) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition {{ $company->active ? 'bg-rose-50 text-rose-600 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' }}">
                                    <x-icon :name="$company->active ? 'lock' : 'check'" class="h-4 w-4" />
                                    {{ $company->active ? 'Suspender' : 'Activar' }}
                                </button>
                            </form>
                            <a href="{{ route('admin.companies.edit', $company) }}"
                               class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                                <x-icon name="edit" class="h-4 w-4 text-slate-500" /> Editar
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl bg-white px-6 py-14 text-center shadow-sm ring-1 ring-slate-200">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <x-icon name="home" class="h-8 w-8" />
                    </span>
                    <p class="mt-3 text-base font-bold text-slate-800">Todavía no hay barberías</p>
                    <p class="mt-1 max-w-sm text-xs text-slate-400">Crea la primera instancia con su nombre, logo y usuario administrador.</p>
                    <a href="{{ route('admin.companies.create') }}"
                       class="mt-4 inline-flex h-11 items-center gap-2 rounded-xl bg-brand-500 px-4 text-sm font-bold text-white transition hover:bg-brand-400">
                        <x-icon name="plus" class="h-4 w-4" /> Crear barbería
                    </a>
                </div>
            @endforelse
        </div>

        @if ($companies->hasPages())
            <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
