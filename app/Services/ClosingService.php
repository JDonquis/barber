<?php

namespace App\Services;

use App\Models\Closing;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClosingService
{
    public function __construct(
        protected ExchangeRateService $rates,
        protected RetentionService $retentions,
    ) {}

    /**
     * Resolve the start/end dates for a period type.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodRange(string $type, ?Carbon $date = null): array
    {
        $date = ($date ?? now())->copy();

        return match ($type) {
            Closing::PERIOD_WEEKLY => [
                $date->copy()->startOfWeek()->startOfDay(),
                $date->copy()->endOfWeek()->endOfDay(),
            ],
            Closing::PERIOD_MONTHLY => [
                $date->copy()->startOfMonth()->startOfDay(),
                $date->copy()->endOfMonth()->endOfDay(),
            ],
            default => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
        };
    }

    /**
     * Build the totals and breakdown for a period.
     *
     * When a barber is given, only their sales are considered and no shop
     * expenses are applied (it represents a payout/liquidation for them).
     */
    public function summarize(Carbon $start, Carbon $end, ?User $barber = null): array
    {
        /** @var Collection<int, Sale> $sales */
        $sales = Sale::query()
            ->completed()
            ->between($start, $end)
            ->when($barber, fn ($query) => $query->where('barber_id', $barber->id))
            ->with(['items', 'barber'])
            ->get();

        $serviceTotal = 0.0;
        $productTotal = 0.0;
        $commission = 0.0;
        $tips = 0.0;
        $totalVes = 0.0;
        $services = [];
        $products = [];
        $byBarber = [];
        $byPayment = [];

        foreach ($sales as $sale) {
            $tip = (float) $sale->tip_usd;
            $commission += (float) $sale->barber_commission_usd;
            $tips += $tip;
            $totalVes += (float) $sale->total_ves;

            $payment = $sale->payment_method ?: 'Sin especificar';
            $byPayment[$payment]['name'] = $payment;
            $byPayment[$payment]['count'] = ($byPayment[$payment]['count'] ?? 0) + 1;
            $byPayment[$payment]['total_usd'] = ($byPayment[$payment]['total_usd'] ?? 0) + (float) $sale->total_usd;
            $byPayment[$payment]['total_ves'] = ($byPayment[$payment]['total_ves'] ?? 0) + (float) $sale->total_ves;

            $barberKey = $sale->barber_id ?? 0;
            $byBarber[$barberKey]['barber_id'] = $sale->barber_id;
            $byBarber[$barberKey]['name'] = $sale->barber->name ?? 'Venta tienda';
            $byBarber[$barberKey]['tickets'] = ($byBarber[$barberKey]['tickets'] ?? 0) + 1;
            $byBarber[$barberKey]['total_usd'] = ($byBarber[$barberKey]['total_usd'] ?? 0) + (float) $sale->total_usd - $tip;
            $byBarber[$barberKey]['commission_usd'] = ($byBarber[$barberKey]['commission_usd'] ?? 0) + (float) $sale->barber_commission_usd;
            $byBarber[$barberKey]['tip_usd'] = ($byBarber[$barberKey]['tip_usd'] ?? 0) + $tip;

            foreach ($sale->items as $item) {
                if ($item->item_type === SaleItem::TYPE_SERVICE) {
                    $serviceTotal += (float) $item->line_total_usd;
                    $key = $item->name;
                    $services[$key]['name'] = $item->name;
                    $services[$key]['quantity'] = ($services[$key]['quantity'] ?? 0) + $item->quantity;
                    $services[$key]['total_usd'] = ($services[$key]['total_usd'] ?? 0) + (float) $item->line_total_usd;
                } else {
                    $productTotal += (float) $item->line_total_usd;
                    $key = $item->name;
                    $products[$key]['name'] = $item->name;
                    $products[$key]['quantity'] = ($products[$key]['quantity'] ?? 0) + $item->quantity;
                    $products[$key]['total_usd'] = ($products[$key]['total_usd'] ?? 0) + (float) $item->line_total_usd;
                }
            }
        }

        $total = $serviceTotal + $productTotal + $tips;

        $retentionsTotal = 0.0;
        $retentionsByBarber = [];
        $retentionItems = [];

        $barberModels = $sales
            ->map(fn (Sale $sale) => $sale->barber)
            ->filter()
            ->unique('id');

        foreach ($barberModels as $barberModel) {
            $retentionSummary = $this->retentions->forBarber($barberModel, $start, $end);

            if ($retentionSummary['total_usd'] <= 0) {
                continue;
            }

            $retentionsByBarber[$barberModel->id] = $retentionSummary['total_usd'];
            $retentionsTotal += $retentionSummary['total_usd'];
            $retentionItems = array_merge($retentionItems, $retentionSummary['items']);
        }

        foreach ($byBarber as $key => $row) {
            $retention = $retentionsByBarber[$key] ?? 0.0;
            $byBarber[$key]['retentions_usd'] = round($retention, 2);
            $byBarber[$key]['earnings_usd'] = round(
                (float) ($row['commission_usd'] ?? 0) + (float) ($row['tip_usd'] ?? 0) - $retention,
                2
            );
        }

        $expenses = $barber
            ? collect()
            : Expense::query()
                ->betweenDates($start, $end)
                ->orderBy('expense_date')
                ->orderBy('id')
                ->get();
        $expensesTotal = (float) $expenses->sum('amount_usd');

        $loanStart = $start->toDateString();
        $loanEnd = $end->toDateString();

        $loanQuery = Loan::query()
            ->with([
                'barber',
                'payments' => fn ($query) => $query
                    ->whereDate('payment_date', '>=', $loanStart)
                    ->whereDate('payment_date', '<=', $loanEnd)
                    ->orderBy('payment_date')
                    ->orderBy('id'),
            ])
            ->when($barber, fn ($query) => $query->where('barber_id', $barber->id));

        if (! $barber) {
            $loanQuery->where(function ($query) use ($loanStart, $loanEnd) {
                $query->whereDate('loan_date', '>=', $loanStart)
                    ->whereDate('loan_date', '<=', $loanEnd)
                    ->orWhereHas('payments', fn ($payments) => $payments
                        ->whereDate('payment_date', '>=', $loanStart)
                        ->whereDate('payment_date', '<=', $loanEnd));
            });
        }

        $loans = $loanQuery
            ->orderBy('loan_date')
            ->orderBy('id')
            ->get();

        $loanItems = $loans->map(function (Loan $loan) use ($loanStart, $loanEnd) {
            $loanDate = $loan->loan_date->toDateString();
            $periodPaid = (float) $loan->payments->sum('amount_usd');

            return [
                'id' => $loan->id,
                'barber_name' => $loan->barber?->name ?? 'Barbero eliminado',
                'loan_date' => $loan->loan_date->format('d/m/Y'),
                'loaned_in_period' => $loanDate >= $loanStart && $loanDate <= $loanEnd,
                'amount_usd' => (float) $loan->amount_usd,
                'paid_usd' => (float) $loan->paid_usd,
                'balance_usd' => (float) $loan->balance_usd,
                'progress' => $loan->progress,
                'period_paid_usd' => round($periodPaid, 2),
                'payments' => $loan->payments->map(fn (LoanPayment $payment) => [
                    'date' => $payment->payment_date->format('d/m/Y'),
                    'amount_usd' => (float) $payment->amount_usd,
                    'payment_method' => $payment->payment_method,
                    'notes' => $payment->notes,
                ])->values()->all(),
            ];
        })->values();

        $loanTotals = [
            'issued_usd' => round($loanItems->where('loaned_in_period', true)->sum('amount_usd'), 2),
            'paid_usd' => round($loanItems->sum('period_paid_usd'), 2),
            'outstanding_usd' => round($loanItems->sum('balance_usd'), 2),
            'count' => $loanItems->count(),
        ];

        return [
            'total_services_usd' => round($serviceTotal, 2),
            'total_products_usd' => round($productTotal, 2),
            'total_usd' => round($total, 2),
            'total_ves' => round($totalVes, 2),
            'barber_commission_usd' => round($commission, 2),
            'barber_tips_usd' => round($tips, 2),
            'barber_retentions_usd' => round($retentionsTotal, 2),
            'barber_earnings_usd' => round($commission + $tips - $retentionsTotal, 2),
            'total_expenses_usd' => round($expensesTotal, 2),
            'shop_amount_usd' => round($total - $commission - $tips + $retentionsTotal - $expensesTotal, 2),
            'ticket_count' => $sales->count(),
            'details' => [
                'barberos' => array_values($byBarber),
                'retenciones' => array_values($retentionItems),
                'prestamos' => $loanItems->all(),
                'prestamos_totales' => $loanTotals,
                'metodos_pago' => array_values($byPayment),
                'servicios' => array_values($services),
                'productos' => array_values($products),
                'gastos' => $expenses->map(fn (Expense $expense) => [
                    'id' => $expense->id,
                    'concept' => $expense->concept,
                    'amount_usd' => (float) $expense->amount_usd,
                    'date' => $expense->expense_date->format('d/m/Y'),
                    'notes' => $expense->notes,
                ])->values()->all(),
            ],
        ];
    }

    /**
     * Create (or refresh) an open shop closing for the given period.
     */
    public function generate(string $type, ?Carbon $date = null, ?User $user = null): Closing
    {
        [$start, $end] = $this->periodRange($type, $date);
        $summary = $this->summarize($start, $end);
        $referenceRate = $this->rates->referenceRate();
        $averageRate = $summary['total_usd'] > 0
            ? round($summary['total_ves'] / $summary['total_usd'], 4)
            : 0;

        $closing = $this->findOrNewPeriod($type, $start, $end);

        if ($closing->exists && $closing->isClosed()) {
            return $closing;
        }

        $closing->fill($summary + [
            'exchange_rate' => $referenceRate,
            'average_rate' => $averageRate,
            'total_ves_reference' => round($summary['total_usd'] * $referenceRate, 2),
            'status' => Closing::STATUS_OPEN,
        ]);

        $closing->closed_by = null;
        $closing->closed_at = null;
        $closing->save();

        if ($user) {
            $this->reopenAncestors($closing, $user);
        }

        return $closing->refresh();
    }

    /**
     * Close a shop period, its open descendants and any parent that is now fully closed.
     */
    public function close(Closing $closing, User $user, ?string $notes = null): Closing
    {
        if ($closing->isClosed()) {
            return $closing;
        }

        $descendants = $this->descendants($closing)
            ->sortBy(fn (Closing $item) => $this->level($item->period_type));

        foreach ($descendants as $descendant) {
            if (! $descendant->isClosed()) {
                $this->closeSingle($descendant, $user);
            }
        }

        $this->closeSingle($closing, $user, $notes);
        $this->closeEligibleAncestors($closing, $user);

        return $closing->fresh();
    }

    /**
     * Reopen a closing together with every related ancestor and descendant.
     */
    public function reopen(Closing $closing, User $user): Closing
    {
        $this->reopenSingle($closing, $user);

        foreach ($this->ancestors($closing) as $ancestor) {
            $this->reopenSingle($ancestor, $user);
        }

        foreach ($this->descendants($closing) as $descendant) {
            $this->reopenSingle($descendant, $user);
        }

        return $closing->fresh();
    }

    public function isPeriodClosed(Carbon $moment): bool
    {
        return Closing::query()
            ->where('status', Closing::STATUS_CLOSED)
            ->whereDate('period_start', '<=', $moment)
            ->whereDate('period_end', '>=', $moment)
            ->exists();
    }

    protected function closeSingle(Closing $closing, User $user, ?string $notes = null): Closing
    {
        if ($closing->isClosed()) {
            return $closing;
        }

        [$start, $end] = [$closing->period_start->copy()->startOfDay(), $closing->period_end->copy()->endOfDay()];
        $summary = $this->summarize($start, $end);
        $referenceRate = $this->rates->referenceRate();
        $averageRate = $summary['total_usd'] > 0
            ? round($summary['total_ves'] / $summary['total_usd'], 4)
            : 0;

        $closing->fill($summary + [
            'exchange_rate' => $referenceRate,
            'average_rate' => $averageRate,
            'total_ves_reference' => round($summary['total_usd'] * $referenceRate, 2),
            'status' => Closing::STATUS_CLOSED,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'reopened_by' => null,
            'reopened_at' => null,
            'notes' => $notes ?? $closing->notes,
        ]);

        $closing->save();

        return $closing;
    }

    protected function reopenSingle(Closing $closing, User $user): void
    {
        if (! $closing->isClosed()) {
            return;
        }

        $closing->update([
            'status' => Closing::STATUS_OPEN,
            'reopened_by' => $user->id,
            'reopened_at' => now(),
        ]);
    }

    protected function reopenAncestors(Closing $closing, User $user): void
    {
        foreach ($this->ancestors($closing) as $ancestor) {
            $this->reopenSingle($ancestor, $user);
        }
    }

    protected function closeEligibleAncestors(Closing $closing, User $user): void
    {
        foreach ($this->ancestors($closing) as $ancestor) {
            if ($ancestor->isClosed()) {
                continue;
            }

            $hasOpenDescendant = $this->descendants($ancestor)
                ->contains(fn (Closing $descendant) => ! $descendant->isClosed());

            if ($hasOpenDescendant) {
                continue;
            }

            $this->closeSingle($ancestor, $user);
            $this->closeEligibleAncestors($ancestor, $user);
        }
    }

    protected function findOrNewPeriod(string $type, Carbon $start, Carbon $end): Closing
    {
        return Closing::query()->firstOrNew([
            'period_type' => $type,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ]);
    }

    /**
     * @return Collection<int, Closing>
     */
    protected function ancestors(Closing $closing): Collection
    {
        return Closing::query()
            ->whereIn('period_type', $this->higherLevels($closing->period_type))
            ->whereDate('period_start', '<=', $closing->period_start)
            ->whereDate('period_end', '>=', $closing->period_end)
            ->orderBy('period_start')
            ->get()
            ->sortBy(fn (Closing $item) => $this->level($item->period_type))
            ->values();
    }

    /**
     * @return Collection<int, Closing>
     */
    protected function descendants(Closing $closing): Collection
    {
        return Closing::query()
            ->whereIn('period_type', $this->lowerLevels($closing->period_type))
            ->whereDate('period_start', '>=', $closing->period_start)
            ->whereDate('period_end', '<=', $closing->period_end)
            ->orderBy('period_start')
            ->get()
            ->sortBy(fn (Closing $item) => $this->level($item->period_type))
            ->values();
    }

    protected function level(string $type): int
    {
        return match ($type) {
            Closing::PERIOD_DAILY => 1,
            Closing::PERIOD_WEEKLY => 2,
            Closing::PERIOD_MONTHLY => 3,
            default => 0,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function higherLevels(string $type): array
    {
        return match ($type) {
            Closing::PERIOD_DAILY => [Closing::PERIOD_WEEKLY, Closing::PERIOD_MONTHLY],
            Closing::PERIOD_WEEKLY => [Closing::PERIOD_MONTHLY],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    protected function lowerLevels(string $type): array
    {
        return match ($type) {
            Closing::PERIOD_MONTHLY => [Closing::PERIOD_WEEKLY, Closing::PERIOD_DAILY],
            Closing::PERIOD_WEEKLY => [Closing::PERIOD_DAILY],
            default => [],
        };
    }
}
