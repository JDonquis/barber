@php
    $publicUrl = route('booking.show', current_company()->slug);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-slate-800">Citas</h1>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4"
         x-data="{
            copied: false,
            copyLink() {
                const done = () => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2200);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(@js($publicUrl)).then(done).catch(() => this.fallbackCopy(done));
                } else {
                    this.fallbackCopy(done);
                }
            },
            fallbackCopy(done) {
                const field = document.createElement('textarea');
                field.value = @js($publicUrl);
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
                    <span class="h-2 w-2 rounded-full bg-brand-500"></span> Agenda de la barbería
                </span>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-800">Citas de tus clientes</h2>
                <p class="mt-0.5 text-xs text-slate-400">Comparte el enlace para que reserven por su cuenta.</p>
            </div>
            <a href="{{ route('appointments.availability') }}"
               class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 text-xs font-bold text-slate-700 transition hover:bg-slate-200">
                <x-icon name="clock" class="h-4 w-4 text-brand-500" /> Horarios y disponibilidad
            </a>
        </div>

        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Enlace público para clientes</p>
                    <p class="truncate text-sm font-semibold text-brand-600">{{ $publicUrl }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" @click="copyLink()"
                            class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                        <x-icon name="copy" class="h-4 w-4 text-brand-500" />
                        <span x-text="copied ? 'Copiado' : 'Copiar enlace'"></span>
                    </button>
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
                       class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-brand-500 px-3 text-xs font-bold text-white transition hover:bg-brand-400">
                        <x-icon name="eye" class="h-4 w-4" /> Abrir
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-3 gap-3">
            @foreach ([
                ['label' => 'Hoy', 'value' => $stats['today'], 'icon' => 'receipt'],
                ['label' => 'Pendientes', 'value' => $stats['pending'], 'icon' => 'clock'],
                ['label' => 'Confirmadas', 'value' => $stats['confirmed'], 'icon' => 'check'],
            ] as $stat)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                        <x-icon :name="$stat['icon']" class="h-4 w-4" />
                    </span>
                    <p class="mt-3 text-xl font-extrabold tracking-tight text-slate-800">{{ $stat['value'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('appointments.index') }}" class="flex flex-col gap-2 sm:flex-row">
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                   class="h-12 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            <select name="status" onchange="this.form.submit()"
                    class="h-12 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="todos" @selected($status === 'todos')>Todos los estados</option>
                <option value="pendiente" @selected($status === 'pendiente')>Pendientes</option>
                <option value="confirmada" @selected($status === 'confirmada')>Confirmadas</option>
                <option value="completada" @selected($status === 'completada')>Completadas</option>
                <option value="cancelada" @selected($status === 'cancelada')>Canceladas</option>
            </select>
            <a href="{{ route('appointments.index', ['all' => 1, 'status' => $status]) }}"
               class="flex h-12 items-center justify-center rounded-xl bg-slate-100 px-4 text-xs font-semibold text-slate-600 transition hover:bg-slate-200">
                Ver todas
            </a>
        </form>

        <div class="space-y-3">
            @forelse ($appointments as $appointment)
                @php
                    $badge = match ($appointment->status) {
                        \App\Models\Appointment::STATUS_CONFIRMED => 'bg-emerald-100 text-emerald-700',
                        \App\Models\Appointment::STATUS_COMPLETED => 'bg-sky-100 text-sky-700',
                        \App\Models\Appointment::STATUS_CANCELLED => 'bg-rose-100 text-rose-700',
                        default => 'bg-amber-100 text-amber-700',
                    };

                    $waDigits = preg_replace('/\D+/', '', (string) $appointment->client_phone);

                    if (str_starts_with($waDigits, '0')) {
                        $waDigits = '58'.mb_substr($waDigits, 1);
                    } elseif (mb_strlen($waDigits) === 10 && str_starts_with($waDigits, '4')) {
                        $waDigits = '58'.$waDigits;
                    }

                    $price = $appointment->service?->price;
                    $priceAmount = $price !== null ? price_money($price) : null;

                    $amountLine = $priceAmount
                        ? 'El monto a pagar es de '.$priceAmount.'. '
                        : '';

                    $waMessage = 'Hola '.$appointment->client_name.', le saluda '.$shopName.
                        '. Su cita de '.$appointment->service_name.
                        ' está pautada para el '.$appointment->date->format('d/m/Y').
                        ' a las '.mb_substr($appointment->start_time, 0, 5).'. '.
                        $amountLine.
                        'En breve le haré llegar el método de pago.';
                @endphp
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-800">{{ $appointment->date->format('d/m/Y') }}</span>
                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">
                                    {{ mb_substr($appointment->start_time, 0, 5) }} - {{ mb_substr($appointment->end_time, 0, 5) }}
                                </span>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ ucfirst($appointment->status) }}</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ $appointment->client_name }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span>{{ $appointment->client_phone }}</span>

                                @if ($waDigits)
                                    <a href="https://wa.me/{{ $waDigits }}?text={{ urlencode($waMessage) }}"
                                       target="_blank" rel="noopener noreferrer" title="Abrir chat de WhatsApp"
                                       class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200 transition hover:bg-emerald-100">
                                        <x-icon name="chat" class="h-3.5 w-3.5" /> WhatsApp
                                    </a>
                                @endif

                                @if ($appointment->client_email)
                                    <span>· {{ $appointment->client_email }}</span>
                                @endif
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                <span><strong class="text-slate-700">Servicio:</strong> {{ $appointment->service_name }}</span>
                                <span><strong class="text-slate-700">Barbero:</strong> {{ $appointment->barber?->name ?? '—' }}</span>
                                <span><strong class="text-slate-700">Duración:</strong> {{ $appointment->duration_minutes }} min</span>
                            </div>
                            @if ($appointment->notes)
                                <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">{{ $appointment->notes }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($appointment->isPending())
                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="confirmada" />
                                    <button type="submit"
                                            class="inline-flex h-9 items-center gap-1 rounded-lg bg-emerald-500 px-3 text-xs font-bold text-white transition hover:bg-emerald-600">
                                        <x-icon name="check" class="h-3.5 w-3.5" /> Confirmar
                                    </button>
                                </form>
                            @endif

                            @if ($appointment->isConfirmed())
                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="completada" />
                                    <button type="submit"
                                            class="inline-flex h-9 items-center gap-1 rounded-lg bg-sky-500 px-3 text-xs font-bold text-white transition hover:bg-sky-600">
                                        Completar
                                    </button>
                                </form>
                            @endif

                            @unless ($appointment->isCancelled() || $appointment->isCompleted())
                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelada" />
                                    <button type="submit"
                                            class="inline-flex h-9 items-center gap-1 rounded-lg bg-rose-50 px-3 text-xs font-bold text-rose-600 transition hover:bg-rose-100">
                                        Cancelar
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white px-6 py-12 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    No hay citas para los filtros seleccionados.
                </div>
            @endforelse
        </div>

        @if ($appointments->hasPages())
            <div class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
                {{ $appointments->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
