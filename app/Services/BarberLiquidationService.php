<?php

namespace App\Services;

use App\Models\BarberLiquidation;
use App\Models\BarberPayment;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BarberLiquidationService
{
    public function __construct(
        protected ClosingService $closings,
        protected ExchangeRateService $rates,
    ) {}

    /**
     * Outstanding balance for every barber as of today.
     *
     * @return Collection<int, User>
     */
    public function balances(): Collection
    {
        $closedLiquidations = BarberLiquidation::query()
            ->where('status', BarberLiquidation::STATUS_CLOSED)
            ->get(['id', 'barber_id', 'period_start', 'period_end', 'closed_at', 'updated_at']);

        $paidByBarber = BarberPayment::query()
            ->selectRaw('barber_id, COALESCE(SUM(amount_usd), 0) as total')
            ->groupBy('barber_id')
            ->pluck('total', 'barber_id');

        return User::query()
            ->barbers()
            ->orderBy('name')
            ->get()
            ->each(function (User $barber) use ($closedLiquidations, $paidByBarber) {
                $totals = $this->outstandingSalesQuery($barber->id, $closedLiquidations)
                    ->selectRaw('COALESCE(SUM(barber_commission_usd), 0) as commission, COALESCE(SUM(tip_usd), 0) as tips')
                    ->first();

                $barber->outstanding_commission = round((float) ($totals->commission ?? 0), 2);
                $barber->outstanding_tips = round((float) ($totals->tips ?? 0), 2);
                $barber->paid_total = round((float) ($paidByBarber[$barber->id] ?? 0), 2);
                $barber->gross_outstanding = round($barber->outstanding_commission + $barber->outstanding_tips, 2);
                $barber->outstanding = max(0, round($barber->gross_outstanding - $barber->paid_total, 2));
            });
    }

    public function balanceForBarber(User $barber): float
    {
        return (float) ($this->balances()->firstWhere('id', $barber->id)?->outstanding ?? 0);
    }

    protected function outstandingSalesQuery(int $barberId, Collection $closedLiquidations)
    {
        $query = Sale::query()
            ->completed()
            ->where('barber_id', $barberId);

        foreach ($closedLiquidations as $liquidation) {
            if ((int) $liquidation->barber_id !== $barberId) {
                continue;
            }

            $start = $liquidation->period_start->copy()->startOfDay();
            $end = $liquidation->period_end->copy()->endOfDay();
            $cutoff = $liquidation->closed_at ?? $liquidation->updated_at ?? now();

            $query->where(function ($query) use ($start, $end, $cutoff) {
                $query->where('sold_at', '<', $start)
                    ->orWhere('sold_at', '>', $end)
                    ->orWhere('sold_at', '>', $cutoff);
            });
        }

        return $query;
    }

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
