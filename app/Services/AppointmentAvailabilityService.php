<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentDayOff;
use App\Models\AppointmentSchedule;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentAvailabilityService
{
    /**
     * Available start times for a barber/service on a given date.
     *
     * @return array<int, array{time: string, end: string, label: string}>
     */
    public function slotsFor(User $barber, Service $service, Carbon $date): array
    {
        $date = $date->copy()->startOfDay();

        if ($date->lt(today())) {
            return [];
        }

        if ($this->isDayOff($barber, $date)) {
            return [];
        }

        $duration = max(5, (int) ($service->duration_minutes ?? 30));
        $schedules = $this->schedulesFor($barber, (int) $date->dayOfWeek);

        if ($schedules->isEmpty()) {
            return [];
        }

        $busy = Appointment::query()
            ->where('barber_id', $barber->id)
            ->whereDate('date', $date->toDateString())
            ->notCancelled()
            ->get(['start_time', 'end_time'])
            ->map(fn (Appointment $appointment) => [
                $this->minutes($appointment->start_time),
                $this->minutes($appointment->end_time),
            ])
            ->all();

        $now = now();
        $slots = [];

        foreach ($schedules as $schedule) {
            $windowStart = $this->minutes($schedule->start_time);
            $windowEnd = $this->minutes($schedule->end_time);

            if ($windowEnd <= $windowStart) {
                continue;
            }

            for ($start = $windowStart; $start + $duration <= $windowEnd; $start += 15) {
                $end = $start + $duration;

                if ($this->overlaps($start, $end, $busy)) {
                    continue;
                }

                $slotDate = $date->copy()->setTime(intdiv($start, 60), $start % 60);

                if ($slotDate->lte($now)) {
                    continue;
                }

                $time = $this->formatMinutes($start);

                $slots[$time] = [
                    'time' => $time,
                    'end' => $this->formatMinutes($end),
                    'label' => $time,
                ];
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    public function isSlotAvailable(User $barber, Service $service, Carbon $date, string $startTime): bool
    {
        $startTime = mb_substr($startTime, 0, 5);

        return collect($this->slotsFor($barber, $service, $date))
            ->contains(fn (array $slot) => $slot['time'] === $startTime);
    }

    public function isDayOff(User $barber, Carbon $date): bool
    {
        return AppointmentDayOff::query()
            ->whereDate('date', $date->toDateString())
            ->where(function ($query) use ($barber) {
                $query->whereNull('barber_id')->orWhere('barber_id', $barber->id);
            })
            ->exists();
    }

    /**
     * @return Collection<int, AppointmentSchedule>
     */
    public function schedulesFor(User $barber, int $dayOfWeek): Collection
    {
        $specific = AppointmentSchedule::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('active', true)
            ->where('barber_id', $barber->id)
            ->orderBy('start_time')
            ->get();

        if ($specific->isNotEmpty()) {
            return $specific;
        }

        return AppointmentSchedule::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('active', true)
            ->whereNull('barber_id')
            ->orderBy('start_time')
            ->get();
    }

    protected function minutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }

    protected function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $busy
     */
    protected function overlaps(int $start, int $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }
}
