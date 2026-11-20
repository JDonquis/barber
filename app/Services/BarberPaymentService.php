<?php

namespace App\Services;

use App\Models\BarberPayment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BarberPaymentService
{
    public function __construct(
        protected BarberLiquidationService $liquidations,
        protected ExchangeRateService $rates,
    ) {}

    public function register(User $barber, array $data, User $user): BarberPayment
    {
        $outstanding = $this->liquidations->balanceForBarber($barber);

        if ($outstanding <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Este barbero no tiene saldo pendiente por pagar.',
            ]);
        }

        $rate = usd_conversion_enabled()
            ? $this->rates->current()
            : $this->rates->referenceRate();

        if (usd_conversion_enabled()) {
            $amountUsd = round((float) $data['amount'], 2);
            $amountLocal = round($amountUsd * $rate, 2);
        } else {
            $amountLocal = round((float) $data['amount'], 2);
            $amountUsd = $rate > 0 ? round($amountLocal / $rate, 2) : $amountLocal;
        }

        if ($amountUsd > $outstanding + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'El pago no puede superar el saldo pendiente de '.money($outstanding).'.',
            ]);
        }

        return BarberPayment::query()->create([
            'barber_id' => $barber->id,
            'amount_usd' => $amountUsd,
            'amount_local' => $amountLocal,
            'payment_date' => $data['payment_date'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function payAll(User $user): int
    {
        $count = 0;

        foreach ($this->liquidations->balances() as $barber) {
            if ($barber->outstanding <= 0) {
                continue;
            }

            $amount = usd_conversion_enabled()
                ? round((float) $barber->outstanding, 2)
                : round((float) $barber->outstanding * $this->rates->referenceRate(), 2);

            $this->register($barber, [
                'amount' => $amount,
                'payment_date' => now()->toDateString(),
                'notes' => 'Pago total del saldo pendiente',
            ], $user);

            $count++;
        }

        return $count;
    }

    public function delete(BarberPayment $payment): void
    {
        $payment->delete();
    }
}
