<?php

namespace App\Services;

use App\Models\Retention;
use App\Models\User;
use Carbon\Carbon;

class RetentionService
{
    public function enabled(): bool
    {
        return (string) setting('retentions_enabled', '0') === '1';
    }

    /**
     * Number of calendar days covered by a closing period.
     */
    public function periodDays(Carbon $start, Carbon $end): int
    {
        $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        return max(1, (int) $days);
    }

    /**
     * Retentions applied to a barber for the given period.
     *
     * The amount is prorated by the period length based on the concept's
     * frequency. For example, a weekly retention is divided by 7 days.
     *
     * @return array{total_usd: float, items: array<int, array<string, mixed>>}
     */
    public function forBarber(User $barber, Carbon $start, Carbon $end): array
    {
        if (! $this->enabled()) {
            return ['total_usd' => 0.0, 'items' => []];
        }

        $days = $this->periodDays($start, $end);

        $concepts = Retention::query()
            ->active()
            ->forBarber($barber->id)
            ->orderBy('name')
            ->get();

        $items = [];
        $total = 0.0;
        $localMode = ! usd_conversion_enabled();
        $rate = $localMode ? app(ExchangeRateService::class)->referenceRate() : 1.0;

        foreach ($concepts as $concept) {
            $appliedLocal = round((float) $concept->amount_usd * $days / $concept->frequencyDays(), 2);

            if ($appliedLocal <= 0) {
                continue;
            }

            $amountBase = $localMode && $rate > 0
                ? round((float) $concept->amount_usd / $rate, 2)
                : (float) $concept->amount_usd;
            $appliedBase = $localMode && $rate > 0
                ? round($appliedLocal / $rate, 2)
                : $appliedLocal;

            $total += $appliedBase;

            $items[] = [
                'id' => $concept->id,
                'name' => $concept->name,
                'amount_usd' => $amountBase,
                'amount_local' => $appliedLocal,
                'frequency' => $concept->frequency,
                'frequency_label' => $concept->frequency_label,
                'days' => $days,
                'applied_usd' => $appliedBase,
                'applied_local' => $appliedLocal,
                'barber_id' => $barber->id,
                'barber_name' => $barber->name,
                'scope' => $concept->isGlobal() ? 'todos' : 'barbero',
            ];
        }

        return ['total_usd' => round($total, 2), 'items' => $items];
    }
}
