<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\ExchangeRateService;
use App\Services\LoanService;
use Illuminate\Http\Request;

class LoanPaymentController extends Controller
{
    public function __construct(protected LoanService $loans) {}

    public function store(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! usd_conversion_enabled()) {
            $local = round((float) $data['amount_usd'], 2);
            $rate = (float) $loan->exchange_rate > 0
                ? (float) $loan->exchange_rate
                : app(ExchangeRateService::class)->referenceRate();

            $data['amount_usd'] = $rate > 0 ? round($local / $rate, 2) : $local;
            $data['amount_ves'] = $local;
            $data['exchange_rate'] = $rate;
        }

        $this->loans->registerPayment($loan, $data, $request->user());

        return redirect()->route('loans.index')->with('status', 'Pago registrado.');
    }

    public function destroy(Loan $loan, LoanPayment $payment)
    {
        abort_unless((int) $payment->loan_id === (int) $loan->id, 404);

        $this->loans->deletePayment($loan, $payment);

        return redirect()->route('loans.show', $loan)->with('status', 'Pago eliminado.');
    }
}
