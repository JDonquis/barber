<?php

namespace Tests\Feature;

use App\Models\BarberLiquidation;
use App\Models\Closing;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function barber(): User
    {
        return User::factory()->create(['role' => User::ROLE_BARBER]);
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function service(float $price = 10): Service
    {
        return Service::create(['name' => 'Corte clásico', 'price' => $price, 'active' => true]);
    }

    private function registerSale(User $admin, Service $service, User $barber, float $tip = 0): Sale
    {
        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'tip_usd' => $tip,
            'payment_currency' => 'USD',
        ])->assertRedirect();

        return Sale::query()->latest('id')->firstOrFail();
    }

    public function test_service_sale_with_tip_adds_it_to_the_total_and_the_barber_earnings(): void
    {
        $this->setting('commission_rate', 40);
        $sale = $this->registerSale($this->admin(), $this->service(10), $this->barber(), 3.5);

        $this->assertSame('13.50', $sale->total_usd);
        $this->assertSame('3.50', $sale->tip_usd);
        $this->assertSame('4.00', $sale->barber_commission_usd);
        $this->assertSame('6.00', $sale->shop_amount_usd);
        $this->assertSame('405.00', $sale->total_ves);
        $this->assertSame(7.5, $sale->barberEarningsUsd());
    }

    public function test_tip_does_not_generate_commission_for_the_shop(): void
    {
        $this->setting('commission_rate', 40);
        $sale = $this->registerSale($this->admin(), $this->service(20), $this->barber(), 5);

        $this->assertSame('8.00', $sale->barber_commission_usd);
        $this->assertSame('5.00', $sale->tip_usd);
        $this->assertSame('12.00', $sale->shop_amount_usd);
        $this->assertSame(13.0, $sale->barberEarningsUsd());
    }

    public function test_barber_closing_includes_tips_in_the_payout(): void
    {
        $this->setting('commission_rate', 40);
        $admin = $this->admin();
        $barber = $this->barber();
        $service = $this->service(10);

        $this->registerSale($admin, $service, $barber, 4);

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ])->assertRedirect();

        $closing = BarberLiquidation::query()->where('barber_id', $barber->id)->firstOrFail();

        $this->assertSame('14.00', $closing->total_usd);
        $this->assertSame('4.00', $closing->barber_commission_usd);
        $this->assertSame('4.00', $closing->barber_tips_usd);
        $this->assertSame(8.0, $closing->barberEarningsUsd());
        $this->assertSame(4.0, (float) $closing->details['barberos'][0]['tip_usd']);
        $this->assertSame(8.0, (float) $closing->details['barberos'][0]['earnings_usd']);
    }

    public function test_general_closing_excludes_tips_from_the_shop_amount(): void
    {
        $this->setting('commission_rate', 40);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $this->service(10), $barber, 2);

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $closing = Closing::query()->firstOrFail();

        $this->assertSame('12.00', $closing->total_usd);
        $this->assertSame('4.00', $closing->barber_commission_usd);
        $this->assertSame('2.00', $closing->barber_tips_usd);
        $this->assertSame('6.00', $closing->shop_amount_usd);
    }

    public function test_sale_detail_shows_the_tip_and_total_earnings(): void
    {
        $this->setting('commission_rate', 40);
        $admin = $this->admin();
        $sale = $this->registerSale($admin, $this->service(10), $this->barber(), 3);

        $this->actingAs($admin)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Propina')
            ->assertSee('Ganancia barbero')
            ->assertSee(usd($sale->barberEarningsUsd()));
    }

    public function test_sale_without_tip_keeps_the_previous_behavior(): void
    {
        $this->setting('commission_rate', 40);
        $sale = $this->registerSale($this->admin(), $this->service(10), $this->barber());

        $this->assertSame('10.00', $sale->total_usd);
        $this->assertSame('0.00', $sale->tip_usd);
        $this->assertSame('4.00', $sale->barber_commission_usd);
        $this->assertSame('6.00', $sale->shop_amount_usd);
    }
}
