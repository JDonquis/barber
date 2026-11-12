<x-admin-layout title="Editar barbería">
    <div class="mx-auto max-w-2xl space-y-4">
        <div>
            <a href="{{ route('admin.companies.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600">
                <x-icon name="arrow-left" class="h-4 w-4" /> Volver a barberías
            </a>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-800">{{ $company->name }}</h1>
            <p class="mt-0.5 text-xs text-slate-400">Actualiza la identidad y el estado de la instancia.</p>
        </div>

        <form method="POST" action="{{ route('admin.companies.update', $company) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.companies._form', ['company' => $company])

            <button type="submit"
                    class="flex h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 to-brand-400 text-sm font-extrabold uppercase tracking-wider text-white shadow-lg shadow-brand-500/30 transition-all hover:opacity-95 active:scale-[0.985]">
                <x-icon name="check" class="h-5 w-5" /> Guardar cambios
            </button>
        </form>
    </div>
</x-admin-layout>
