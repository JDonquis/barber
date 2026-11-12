<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    /**
     * Register a payment for a loan and refresh the paid total.
     */
    public function registerPayment(Loan $loan, array $data, User $user): LoanPayment
    {
        return DB::transaction(function () use ($loan, $data, $user) {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $amount = round((float) $data['amount_usd'], 2);
            $remaining = round((float) $locked->amount_usd - (float) $locked->paid_usd, 2);

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount_usd' => 'El pago no puede superar el saldo pendiente de '.usd($remaining).'.',
                ]);
            }

            $rate = (float) ($data['exchange_rate'] ?? 0);

            $payment = $locked->payments()->create([
                'amount_usd' => $amount,
                'amount_ves' => round($amount * $rate, 2),
                'exchange_rate' => $rate,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->recalculate($locked);

            return $payment;
        });
    }

    public function deletePayment(Loan $loan, LoanPayment $payment): void
    {
        DB::transaction(function () use ($loan, $payment) {
            $payment->delete();
            $this->recalculate($loan->fresh());
        });
    }

    public function recalculate(Loan $loan): void
    {
        $paid = (float) $loan->payments()->sum('amount_usd');

        $loan->update(['paid_usd' => round($paid, 2)]);
    }
}
