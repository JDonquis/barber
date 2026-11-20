<?php

namespace App\Services;

use App\Models\BarberLiquidation;
use App\Models\User;
use Carbon\Carbon;

class BarberLiquidationService
{
    public function __construct(
        protected ClosingService $closings,
        protected ExchangeRateService $rates,
    ) {}

    public function generate(string $type, Carbon $date, User $barber, ?User $user = null): BarberLiquidation
    {
        [$start, $end] = $this->closings->periodRange($type, $date);
        $summary = $this->closings->summarize($start, $end, $barber);

        $liquidation = BarberLiquidation::query()->firstOrNew([
            'barber_id' => $barber->id,
            'period_type' => $type,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ]);

        if ($liquidation->exists && $liquidation->isClosed()) {
            return $liquidation;
        }

        $referenceRate = $this->rates->referenceRate();
        $averageRate = $summary['total_usd'] > 0
            ? round($summary['total_ves'] / $summary['total_usd'], 4)
            : 0;

        $liquidation->fill($summary + [
            'origin' => 'manual',
            'exchange_rate' => $referenceRate,
            'average_rate' => $averageRate,
            'total_ves_reference' => round($summary['total_usd'] * $referenceRate, 2),
            'status' => BarberLiquidation::STATUS_OPEN,
        ]);

        $liquidation->closed_by = null;
        $liquidation->closed_at = null;
        $liquidation->save();

        return $liquidation->refresh();
    }

    public function close(BarberLiquidation $liquidation, User $user, ?string $notes = null): BarberLiquidation
    {
        if ($liquidation->isClosed()) {
            return $liquidation;
        }

        [$start, $end] = [
            $liquidation->period_start->copy()->startOfDay(),
            $liquidation->period_end->copy()->endOfDay(),
        ];
        $summary = $this->closings->summarize($start, $end, $liquidation->barber);
        $referenceRate = $this->rates->referenceRate();
        $averageRate = $summary['total_usd'] > 0
            ? round($summary['total_ves'] / $summary['total_usd'], 4)
            : 0;

        $liquidation->fill($summary + [
            'exchange_rate' => $referenceRate,
            'average_rate' => $averageRate,
            'total_ves_reference' => round($summary['total_usd'] * $referenceRate, 2),
            'status' => BarberLiquidation::STATUS_CLOSED,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'reopened_by' => null,
            'reopened_at' => null,
            'notes' => $notes ?? $liquidation->notes,
        ]);

        $liquidation->save();

        return $liquidation->refresh();
    }

    public function reopen(BarberLiquidation $liquidation, User $user): BarberLiquidation
    {
        if (! $liquidation->isClosed()) {
            return $liquidation;
        }

        $liquidation->update([
            'status' => BarberLiquidation::STATUS_OPEN,
            'reopened_by' => $user->id,
            'reopened_at' => now(),
        ]);

        return $liquidation->refresh();
    }
}
