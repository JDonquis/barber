<?php

namespace App\Http\Controllers;

use App\Models\BarberLiquidation;
use App\Models\BarberPayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ExchangeRateService $rates)
    {
        $user = $request->user();
        $isBarber = $user->isBarber();

        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $base = fn () => Sale::query()->completed()->when($isBarber, fn ($q) => $q->where('barber_id', $user->id));

        $todaySales = $base()->between($start, $end);

        $serviceTotal = SaleItem::query()
            ->where('item_type', SaleItem::TYPE_SERVICE)
            ->whereHas('sale', fn ($q) => $q->completed()
                ->when($isBarber, fn ($qq) => $qq->where('barber_id', $user->id))
                ->between($start, $end))
            ->sum('line_total_usd');

        $productTotal = SaleItem::query()
            ->where('item_type', SaleItem::TYPE_PRODUCT)
            ->whereHas('sale', fn ($q) => $q->completed()
                ->when($isBarber, fn ($qq) => $qq->where('barber_id', $user->id))
                ->between($start, $end))
            ->sum('line_total_usd');

        $serviceCount = SaleItem::query()
            ->where('item_type', SaleItem::TYPE_SERVICE)
            ->whereHas('sale', fn ($q) => $q->completed()
                ->when($isBarber, fn ($qq) => $qq->where('barber_id', $user->id))
                ->between($start, $end))
            ->sum('quantity');

        $productCount = SaleItem::query()
            ->where('item_type', SaleItem::TYPE_PRODUCT)
            ->whereHas('sale', fn ($q) => $q->completed()
                ->when($isBarber, fn ($qq) => $qq->where('barber_id', $user->id))
                ->between($start, $end))
            ->sum('quantity');

        $monthQuery = $base()->whereBetween('sold_at', [$monthStart, $monthEnd]);

        $recentSales = $base()->with(['barber', 'items'])
            ->latest('sold_at')
            ->take(8)
            ->get();

        $todayExpenses = (float) Expense::query()->betweenDates($start, $end)->sum('amount_usd');
        $monthExpenses = (float) Expense::query()->betweenDates($monthStart, $monthEnd)->sum('amount_usd');

        $todayShopGross = (float) (clone $todaySales)->sum('shop_amount_usd');
        $monthShopGross = (float) (clone $monthQuery)->sum('shop_amount_usd');
        $todayCommission = (float) (clone $todaySales)->sum('barber_commission_usd');
        $todayTips = (float) (clone $todaySales)->sum('tip_usd');
        $monthCommission = (float) (clone $monthQuery)->sum('barber_commission_usd');
        $monthTips = (float) (clone $monthQuery)->sum('tip_usd');

        return view('dashboard', [
            'isBarber' => $isBarber,
            'todayTotal' => (clone $todaySales)->sum('total_usd'),
            'todayCommission' => $todayCommission,
            'todayTips' => $todayTips,
            'todayEarnings' => round($todayCommission + $todayTips, 2),
            'todayShopAmount' => round($todayShopGross - $todayExpenses, 2),
            'todayExpenses' => $todayExpenses,
            'todayTickets' => (clone $todaySales)->count(),
            'serviceTotal' => $serviceTotal,
            'serviceCount' => (int) $serviceCount,
            'productTotal' => $productTotal,
            'productCount' => (int) $productCount,
            'monthTotal' => (clone $monthQuery)->sum('total_usd'),
            'monthCommission' => $monthCommission,
            'monthTips' => $monthTips,
            'monthEarnings' => round($monthCommission + $monthTips, 2),
            'monthShopAmount' => round($monthShopGross - $monthExpenses, 2),
            'monthExpenses' => $monthExpenses,
            'lowStock' => $isBarber ? collect() : Product::query()->active()->lowStock()->orderBy('name')->get(),
            'topBarbers' => $isBarber ? collect() : $this->topBarbers($monthStart, $monthEnd),
            'recentSales' => $recentSales,
            'currentRate' => $rates->current(),
        ]);
    }

    protected function topBarbers($monthStart, $monthEnd)
    {
        $closedLiquidations = BarberLiquidation::query()
            ->where('status', BarberLiquidation::STATUS_CLOSED)
            ->get(['id', 'barber_id', 'period_start', 'period_end', 'closed_at', 'updated_at']);

        $paidByBarber = BarberPayment::query()
            ->selectRaw('barber_id, COALESCE(SUM(amount_usd), 0) as total')
            ->groupBy('barber_id')
            ->pluck('total', 'barber_id');

        return User::query()->barbers()
            ->withCount(['barberSales as tickets' => fn ($q) => $q->completed()->whereBetween('sold_at', [$monthStart, $monthEnd])])
            ->withSum(['barberSales as commission' => fn ($q) => $q->completed()->whereBetween('sold_at', [$monthStart, $monthEnd])], 'barber_commission_usd')
            ->withSum(['barberSales as tips' => fn ($q) => $q->completed()->whereBetween('sold_at', [$monthStart, $monthEnd])], 'tip_usd')
            ->get()
            ->each(function (User $barber) use ($closedLiquidations, $paidByBarber) {
                $barber->earnings = round((float) $barber->commission + (float) $barber->tips, 2);

                $outstanding = $this->outstandingSalesQuery($barber->id, $closedLiquidations)
                    ->selectRaw('COALESCE(SUM(barber_commission_usd), 0) as commission, COALESCE(SUM(tip_usd), 0) as tips')
                    ->first();

                $barber->outstanding_commission = round((float) ($outstanding->commission ?? 0), 2);
                $barber->outstanding_tips = round((float) ($outstanding->tips ?? 0), 2);
                $gross = round($barber->outstanding_commission + $barber->outstanding_tips, 2);
                $paid = round((float) ($paidByBarber[$barber->id] ?? 0), 2);
                $barber->outstanding = max(0, round($gross - $paid, 2));
            })
            ->sortByDesc('earnings')
            ->values();
    }

    protected function outstandingSalesQuery(int $barberId, $closedLiquidations)
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
}
