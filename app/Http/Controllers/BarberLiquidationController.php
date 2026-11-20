<?php

namespace App\Http\Controllers;

use App\Models\BarberLiquidation;
use App\Models\BarberPayment;
use App\Models\User;
use App\Services\BarberLiquidationService;
use App\Services\BarberPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarberLiquidationController extends Controller
{
    public function __construct(
        protected BarberLiquidationService $liquidations,
        protected BarberPaymentService $payments,
    ) {}

    public function index(Request $request)
    {
        $barbers = User::query()->barbers()->orderBy('name')->get();
        $barberBalances = $this->liquidations->balances()->sortByDesc('outstanding')->values();
        $barberId = $request->input('barber_id');

        $payments = BarberPayment::query()
            ->with(['barber', 'createdBy'])
            ->when($barberId, fn ($query) => $query->where('barber_id', $barberId))
            ->latest('payment_date')
            ->latest('id')
            ->get();

        return view('liquidations.index', compact('payments', 'barbers', 'barberBalances', 'barberId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'barber_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('company_id', company_id())
                    ->where('role', User::ROLE_BARBER)
                    ->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $barber = User::query()->barbers()->findOrFail($data['barber_id']);

        $this->payments->register($barber, $data, $request->user());

        return redirect()->route('liquidations.index')
            ->with('status', 'Pago registrado. El saldo pendiente fue actualizado.');
    }

    public function payAll(Request $request)
    {
        $count = $this->payments->payAll($request->user());

        return redirect()->route('liquidations.index')
            ->with('status', $count > 0
                ? "Se registraron {$count} pagos por el saldo total pendiente."
                : 'No hay saldos pendientes para pagar.');
    }

    public function destroyPayment(BarberPayment $payment)
    {
        $this->payments->delete($payment);

        return redirect()->route('liquidations.index')
            ->with('status', 'Pago eliminado. El saldo pendiente fue restaurado.');
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
