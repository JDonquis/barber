<?php

namespace App\Services;

use App\Models\Closing;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SaleService
{
    public function __construct(
        protected ExchangeRateService $rates,
        protected SettingService $settings,
    ) {}

    /**
     * Register the sale of a single haircut/service.
     */
    public function registerServiceSale(array $data, User $registeredBy): Sale
    {
        $service = Service::query()->findOrFail($data['service_id']);
        $barberId = $data['barber_id'] ?? null;
        $amount = array_key_exists('price', $data) && $data['price'] !== null
            ? (float) $data['price']
            : (float) $service->price;
        $baseRate = (float) $this->settings->get('commission_rate', 0);
        $barber = $barberId ? User::query()->find($barberId) : null;
        $commissionRate = $barber ? $barber->effectiveCommissionRate($baseRate) : $baseRate;
        $tip = round(max(0, (float) ($data['tip_usd'] ?? 0)), 2);
        $localMode = ! $this->rates->enabled();
        $rate = $localMode ? $this->rates->referenceRate() : $this->rates->current();

        if ($localMode) {
            $totalLocal = round($amount, 2);
            $tipLocal = $tip;
            $commissionLocal = round($totalLocal * $commissionRate / 100, 2);
            $totalUsd = $this->localToUsd($totalLocal, $rate);
            $tipUsd = $this->localToUsd($tipLocal, $rate);
            $commissionUsd = $this->localToUsd($commissionLocal, $rate);
            $totalVes = round($totalLocal + $tipLocal, 2);
        } else {
            $totalUsd = round($amount, 2);
            $tipUsd = $tip;
            $commissionUsd = round($totalUsd * $commissionRate / 100, 2);
            $totalVes = round(($totalUsd + $tipUsd) * $rate, 2);
            $totalLocal = round($totalUsd, 2);
        }

        $saleTotalUsd = round($totalUsd + $tipUsd, 2);

        return DB::transaction(function () use ($service, $saleTotalUsd, $totalUsd, $totalVes, $tipUsd, $commissionUsd, $commissionRate, $rate, $data, $registeredBy, $barberId, $localMode, $totalLocal) {
            $sale = $this->createSale([
                'user_id' => $registeredBy->id,
                'barber_id' => $barberId,
                'type' => Sale::TYPE_SERVICE,
                'commission_rate' => $commissionRate,
                'total_usd' => $saleTotalUsd,
                'total_ves' => $totalVes,
                'exchange_rate' => $rate,
                'barber_commission_usd' => $commissionUsd,
                'tip_usd' => $tipUsd,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_currency' => $data['payment_currency'] ?? ($localMode ? local_currency() : 'USD'),
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->items()->create([
                'item_type' => SaleItem::TYPE_SERVICE,
                'service_id' => $service->id,
                'name' => $service->name,
                'quantity' => 1,
                'unit_price_usd' => $totalUsd,
                'line_total_usd' => $totalUsd,
            ]);

            if (! empty($data['update_service_price']) && (float) $service->price !== ($localMode ? $totalLocal : $totalUsd)) {
                $service->update(['price' => $localMode ? $totalLocal : $totalUsd]);
            }

            return $sale;
        });
    }

    /**
     * Register the sale of one or more products, decreasing stock.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function registerProductSale(array $items, array $data, User $registeredBy): Sale
    {
        $commissionRate = (float) $this->settings->get('commission_rate', 0);
        $barberId = $data['barber_id'] ?? null;
        $localMode = ! $this->rates->enabled();
        $rate = $localMode ? $this->rates->referenceRate() : $this->rates->current();

        return DB::transaction(function () use ($items, $data, $registeredBy, $barberId, $commissionRate, $localMode, $rate) {
            $totalLocal = 0;
            $lineItems = [];

            foreach ($items as $row) {
                $product = Product::query()->lockForUpdate()->findOrFail($row['product_id']);
                $quantity = (int) $row['quantity'];

                if ($quantity < 1) {
                    continue;
                }

                if ($product->stock < $quantity) {
                    throw new RuntimeException("Stock insuficiente para {$product->name}. Disponible: {$product->stock}.");
                }

                $lineTotalLocal = round((float) $product->price * $quantity, 2);
                $totalLocal += $lineTotalLocal;

                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total_local' => $lineTotalLocal,
                ];
            }

            if (empty($lineItems)) {
                throw new RuntimeException('Debes agregar al menos un producto a la venta.');
            }

            $totalLocal = round($totalLocal, 2);
            $totalUsd = $localMode ? $this->localToUsd($totalLocal, $rate) : $totalLocal;
            $totalVes = $localMode ? $totalLocal : round($totalUsd * $rate, 2);

            $sale = $this->createSale([
                'user_id' => $registeredBy->id,
                'barber_id' => $barberId,
                'type' => Sale::TYPE_PRODUCT,
                'commission_rate' => $commissionRate,
                'total_usd' => $totalUsd,
                'total_ves' => $totalVes,
                'exchange_rate' => $rate,
                'barber_commission_usd' => 0,
                'tip_usd' => 0,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_currency' => $data['payment_currency'] ?? ($localMode ? local_currency() : 'USD'),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lineItems as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $lineTotalUsd = $localMode
                    ? $this->localToUsd((float) $line['line_total_local'], $rate)
                    : (float) $line['line_total_local'];
                $unitPriceUsd = $localMode
                    ? $this->localToUsd((float) $product->price, $rate)
                    : (float) $product->price;

                $sale->items()->create([
                    'item_type' => SaleItem::TYPE_PRODUCT,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit_price_usd' => $unitPriceUsd,
                    'line_total_usd' => $lineTotalUsd,
                ]);

                $newStock = $product->stock - $line['quantity'];
                $product->update(['stock' => $newStock]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $registeredBy->id,
                    'type' => StockMovement::TYPE_OUT,
                    'quantity' => -$line['quantity'],
                    'stock_after' => $newStock,
                    'note' => 'Venta '.$sale->code,
                ]);
            }

            return $sale;
        });
    }

    /**
     * Cancel a sale and restore product stock when needed.
     */
    public function cancel(Sale $sale, User $user): void
    {
        if ($sale->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($sale, $user) {
            foreach ($sale->items as $item) {
                if ($item->item_type !== SaleItem::TYPE_PRODUCT || ! $item->product) {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find($item->product_id);
                $newStock = $product->stock + $item->quantity;
                $product->update(['stock' => $newStock]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => StockMovement::TYPE_IN,
                    'quantity' => $item->quantity,
                    'stock_after' => $newStock,
                    'note' => 'Anulación venta '.$sale->code,
                ]);
            }

            $sale->update(['status' => Sale::STATUS_CANCELLED]);
        });
    }

    protected function createSale(array $attributes): Sale
    {
        $rate = (float) ($attributes['exchange_rate'] ?? $this->rates->current());
        $total = (float) $attributes['total_usd'];
        $commission = (float) ($attributes['barber_commission_usd'] ?? 0);
        $tip = (float) ($attributes['tip_usd'] ?? 0);

        $this->assertPeriodOpen(now());

        $sale = Sale::create($attributes + [
            'code' => 'TMP-'.Str::uuid(),
            'exchange_rate' => $rate,
            'total_ves' => round($total * $rate, 2),
            'shop_amount_usd' => round($total - $commission - $tip, 2),
            'status' => Sale::STATUS_COMPLETED,
            'sold_at' => now(),
        ]);

        $sale->update(['code' => 'V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

        return $sale;
    }

    protected function localToUsd(float $amount, float $rate): float
    {
        return $rate > 0 ? round($amount / $rate, 2) : round($amount, 2);
    }

    protected function assertPeriodOpen(\DateTimeInterface $moment): void
    {
        $closed = Closing::query()
            ->where('status', Closing::STATUS_CLOSED)
            ->whereDate('period_start', '<=', $moment)
            ->whereDate('period_end', '>=', $moment)
            ->exists();

        if ($closed) {
            throw new RuntimeException('El período de esta venta ya fue cerrado. No se pueden registrar más movimientos.');
        }
    }
}
