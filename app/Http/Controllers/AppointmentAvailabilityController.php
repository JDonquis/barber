<?php

namespace App\Http\Controllers;

use App\Models\AppointmentDayOff;
use App\Models\AppointmentSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentAvailabilityController extends Controller
{
    public function index()
    {
        $schedules = AppointmentSchedule::query()
            ->with('barber')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $barbers = User::query()->barbers()->orderBy('name')->get();
        $services = Service::query()->orderBy('name')->get();

        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            0 => 'Domingo',
        ];

        $schedulesByScope = $schedules
            ->groupBy(fn (AppointmentSchedule $schedule) => $schedule->barber_id === null ? 'general' : (string) $schedule->barber_id)
            ->map(fn (Collection $group) => $this->weekFromSchedules($group))
            ->all();

        $schedulesByScope['general'] ??= $this->emptyWeek();

        return view('appointments.availability', [
            'schedulesByScope' => $schedulesByScope,
            'daysOff' => AppointmentDayOff::query()
                ->with('barber')
                ->whereDate('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->get(),
            'barbers' => $barbers,
            'barbersData' => $barbers->map(fn (User $barber) => [
                'id' => $barber->id,
                'name' => $barber->name,
                'initials' => $this->initials($barber->name),
            ])->values(),
            'services' => $services,
            'serviceDurations' => $services
                ->mapWithKeys(fn (Service $service) => [$service->id => (int) $service->duration_minutes])
                ->all(),
            'days' => $days,
            'dayOrder' => [1, 2, 3, 4, 5, 6, 0],
            'dayShort' => [
                1 => 'LU',
                2 => 'MA',
                3 => 'MI',
                4 => 'JU',
                5 => 'VI',
                6 => 'SA',
                0 => 'DO',
            ],
        ]);
    }

    public function syncSchedules(Request $request)
    {
        $data = $request->validate([
            'barber_id' => ['nullable', $this->barberRule()],
            'days' => ['required', 'json'],
        ]);

        $days = json_decode($data['days'], true);

        $validator = Validator::make(['days' => $days], [
            'days' => ['required', 'array'],
            'days.*' => ['array'],
            'days.*.active' => ['required', 'boolean'],
            'days.*.blocks' => ['array'],
            'days.*.blocks.*.start' => ['required', 'date_format:H:i'],
            'days.*.blocks.*.end' => ['required', 'date_format:H:i'],
        ]);

        $validator->after(function ($validator) use ($days) {
            if (! is_array($days)) {
                return;
            }

            $invalidDays = array_diff(array_map('intval', array_keys($days)), range(0, 6));

            if ($invalidDays !== []) {
                $validator->errors()->add('days', 'El día seleccionado no es válido.');
            }

            foreach ($days as $day => $state) {
                if (! is_array($state)) {
                    continue;
                }

                $blocks = $state['blocks'] ?? [];

                if (! is_array($blocks)) {
                    continue;
                }

                $ranges = [];

                foreach ($blocks as $index => $block) {
                    if (! is_array($block)) {
                        continue;
                    }

                    $start = $this->minutes($block['start'] ?? '');
                    $end = $this->minutes($block['end'] ?? '');

                    if ($end <= $start) {
                        $validator->errors()->add("days.$day.blocks.$index.end", 'La hora de fin debe ser mayor que la hora de inicio.');
                        continue;
                    }

                    $ranges[] = [$start, $end, $index];
                }

                usort($ranges, fn (array $left, array $right) => $left[0] <=> $right[0]);

                for ($index = 1; $index < count($ranges); $index++) {
                    if ($ranges[$index][0] < $ranges[$index - 1][1]) {
                        $validator->errors()->add("days.$day.blocks.{$ranges[$index][2]}.start", 'Los bloques de un mismo día no pueden solaparse.');
                    }
                }
            }
        });

        $validator->validate();

        $barberId = $data['barber_id'] ?? null;

        DB::transaction(function () use ($days, $barberId) {
            AppointmentSchedule::query()
                ->when(
                    $barberId,
                    fn ($query) => $query->where('barber_id', $barberId),
                    fn ($query) => $query->whereNull('barber_id'),
                )
                ->delete();

            foreach ($days as $day => $state) {
                if (! ($state['active'] ?? false)) {
                    continue;
                }

                foreach (($state['blocks'] ?? []) as $block) {
                    AppointmentSchedule::create([
                        'barber_id' => $barberId ?: null,
                        'day_of_week' => (int) $day,
                        'start_time' => $block['start'],
                        'end_time' => $block['end'],
                        'active' => true,
                    ]);
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return back()->with('status', 'Disponibilidad actualizada correctamente.');
    }

    public function updateServiceDurations(Request $request)
    {
        $data = $request->validate([
            'durations' => ['required', 'array'],
            'durations.*' => ['required', 'integer', 'min:5', 'max:480'],
        ]);

        $ids = array_map('intval', array_keys($data['durations']));
        $services = Service::query()->whereIn('id', $ids)->get()->keyBy('id');

        if ($services->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'durations' => 'Alguno de los servicios seleccionados no pertenece a esta barbería.',
            ]);
        }

        foreach ($services as $id => $service) {
            $service->update(['duration_minutes' => (int) $data['durations'][$id]]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'updated_at' => now()->toIso8601String(),
            ]);
        }

        return back()->with('status', 'Duración de los servicios actualizada.');
    }

    public function storeSchedule(Request $request)
    {
        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'barber_id' => ['nullable', $this->barberRule()],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        AppointmentSchedule::create($data + ['active' => true]);

        return back()->with('status', 'Horario agregado correctamente.');
    }

    public function destroySchedule(AppointmentSchedule $schedule)
    {
        $schedule->delete();

        return back()->with('status', 'Horario eliminado.');
    }

    public function storeDayOff(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'barber_id' => ['nullable', $this->barberRule()],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        AppointmentDayOff::create($data);

        return back()->with('status', 'Día libre registrado.');
    }

    public function destroyDayOff(AppointmentDayOff $dayOff)
    {
        $dayOff->delete();

        return back()->with('status', 'Día libre eliminado.');
    }

    protected function barberRule()
    {
        return Rule::exists('users', 'id')
            ->where('company_id', company_id())
            ->where('role', User::ROLE_BARBER)
            ->whereNull('deleted_at');
    }

    /**
     * @return array<int, array<int, array{start: string, end: string}>>
     */
    protected function emptyWeek(): array
    {
        return array_fill_keys(range(0, 6), []);
    }

    /**
     * @return array<int, array<int, array{start: string, end: string}>>
     */
    protected function weekFromSchedules(Collection $schedules): array
    {
        $week = $this->emptyWeek();

        foreach ($schedules as $schedule) {
            $week[$schedule->day_of_week][] = [
                'start' => mb_substr($schedule->start_time, 0, 5),
                'end' => mb_substr($schedule->end_time, 0, 5),
            ];
        }

        return $week;
    }

    protected function minutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }

    protected function initials(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
