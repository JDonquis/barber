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

        foreach ($concepts as $concept) {
            $applied = round((float) $concept->amount_usd * $days / $concept->frequencyDays(), 2);

            if ($applied <= 0) {
                continue;
            }

            $total += $applied;

            $items[] = [
                'id' => $concept->id,
                'name' => $concept->name,
                'amount_usd' => (float) $concept->amount_usd,
                'frequency' => $concept->frequency,
                'frequency_label' => $concept->frequency_label,
                'days' => $days,
                'applied_usd' => $applied,
                'barber_id' => $barber->id,
                'barber_name' => $barber->name,
                'scope' => $concept->isGlobal() ? 'todos' : 'barbero',
            ];
        }

        return ['total_usd' => round($total, 2), 'items' => $items];
    }
}
