<?php

namespace App\Http\Controllers;

use App\Models\Closing;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\User;
use App\Services\ClosingService;
use App\Services\RetentionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ClosingController extends Controller
{
    public function __construct(protected ClosingService $closings) {}

    public function index()
    {
        $closings = Closing::query()->with('closedBy')->latest('period_start')->get();

        $monthStart = now()->startOfMonth();
        $monthRevenue = (float) Sale::query()->completed()->where('sold_at', '>=', $monthStart)->sum('total_usd');
        $monthCommission = (float) Sale::query()->completed()->where('sold_at', '>=', $monthStart)->sum('barber_commission_usd');
        $monthTips = (float) Sale::query()->completed()->where('sold_at', '>=', $monthStart)->sum('tip_usd');
        $monthExpenses = (float) Expense::query()->betweenDates($monthStart, now()->endOfMonth())->sum('amount_usd');

        $retentionService = app(RetentionService::class);
        $monthRetentions = 0.0;
        $monthBarberIds = Sale::query()
            ->completed()
            ->where('sold_at', '>=', $monthStart)
            ->whereNotNull('barber_id')
            ->distinct()
            ->pluck('barber_id');

        foreach (User::query()->whereIn('id', $monthBarberIds)->get() as $monthBarber) {
            $monthRetentions += $retentionService->forBarber($monthBarber, $monthStart, now()->endOfDay())['total_usd'];
        }

        $monthRetentions = round($monthRetentions, 2);
        $monthShopAmount = round($monthRevenue - $monthCommission - $monthTips + $monthRetentions - $monthExpenses, 2);

        return view('closings.index', compact('closings', 'monthRevenue', 'monthCommission', 'monthTips', 'monthRetentions', 'monthExpenses', 'monthShopAmount'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'period_type' => ['required', 'in:diario,semanal,mensual'],
            'date' => ['required', 'date'],
        ]);

        $closing = $this->closings->generate(
            $data['period_type'],
            Carbon::parse($data['date']),
            $request->user(),
        );

        return redirect()->route('closings.show', $closing)->with('status', 'Cierre de tienda generado.');
    }

    public function show(Closing $closing)
    {
        return view('closings.show', compact('closing'));
    }

    public function close(Request $request, Closing $closing)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->closings->close($closing, $request->user(), $data['notes'] ?? null);

        return redirect()->route('closings.show', $closing)->with('status', 'Cierre bloqueado correctamente.');
    }

    public function reopen(Request $request, Closing $closing)
    {
        $this->closings->reopen($closing, $request->user());

        return redirect()->route('closings.show', $closing)->with('status', 'Período reabierto. Ya puedes registrar o corregir ventas.');
    }

    public function print(Closing $closing)
    {
        return view('closings.print', compact('closing'));
    }
}
