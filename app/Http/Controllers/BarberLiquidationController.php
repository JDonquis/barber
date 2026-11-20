<?php

namespace App\Http\Controllers;

use App\Models\BarberLiquidation;
use App\Models\User;
use App\Services\BarberLiquidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarberLiquidationController extends Controller
{
    public function __construct(protected BarberLiquidationService $liquidations) {}

    public function index(Request $request)
    {
        $barbers = User::query()->barbers()->orderBy('name')->get();
        $barberBalances = $this->liquidations->balances()->sortByDesc('outstanding')->values();
        $barberId = $request->input('barber_id');

        $liquidations = BarberLiquidation::query()
            ->with(['barber', 'closedBy'])
            ->when($barberId, fn ($query) => $query->where('barber_id', $barberId))
            ->latest('period_start')
            ->latest('id')
            ->get();

        return view('liquidations.index', compact('liquidations', 'barbers', 'barberBalances', 'barberId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_type' => ['required', 'in:diario,semanal,mensual'],
            'date' => ['required', 'date'],
            'barber_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('company_id', company_id())
                    ->where('role', User::ROLE_BARBER)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $barber = User::query()->barbers()->findOrFail($data['barber_id']);

        $liquidation = $this->liquidations->generate(
            $data['period_type'],
            Carbon::parse($data['date']),
            $barber,
            $request->user(),
        );

        return redirect()->route('liquidations.show', $liquidation)
            ->with('status', 'Liquidación generada.');
    }

    public function show(BarberLiquidation $liquidation)
    {
        $liquidation->load(['barber', 'closedBy', 'reopenedBy']);

        return view('liquidations.show', compact('liquidation'));
    }

    public function close(Request $request, BarberLiquidation $liquidation)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->liquidations->close($liquidation, $request->user(), $data['notes'] ?? null);

        return redirect()->route('liquidations.show', $liquidation)
            ->with('status', 'Liquidación cerrada correctamente.');
    }

    public function reopen(Request $request, BarberLiquidation $liquidation)
    {
        $this->liquidations->reopen($liquidation, $request->user());

        return redirect()->route('liquidations.show', $liquidation)
            ->with('status', 'Liquidación reabierta. El saldo vuelve a quedar pendiente.');
    }

    public function print(BarberLiquidation $liquidation)
    {
        $liquidation->load(['barber', 'closedBy', 'reopenedBy']);

        return view('liquidations.print', compact('liquidation'));
    }
}
