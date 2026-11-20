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

class BarberClosingTest extends TestCase
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

    private function registerSale(User $admin, Service $service, User $barber): void
    {
        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'USD',
        ])->assertRedirect();
    }

    private function outstandingFor(User $admin, User $barber): float
    {
        $topBarbers = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('topBarbers');

        return (float) ($topBarbers->firstWhere('id', $barber->id)?->outstanding ?? 0);
    }

    public function test_admin_can_generate_a_barber_liquidation_for_a_period(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();
        $other = $this->barber();

        $this->registerSale($admin, $service, $barber);
        $this->registerSale($admin, $service, $other);

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ])->assertRedirect();

        $liquidation = BarberLiquidation::query()->where('barber_id', $barber->id)->firstOrFail();

        $this->assertSame('10.00', $liquidation->total_usd);
        $this->assertSame('4.00', $liquidation->barber_commission_usd);
        $this->assertSame(1, $liquidation->ticket_count);
        $this->assertSame(BarberLiquidation::STATUS_OPEN, $liquidation->status);
    }

    public function test_barber_liquidation_only_includes_that_barbers_sales(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();
        $other = $this->barber();

        $this->registerSale($admin, $service, $barber);

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $other->id,
            'payment_currency' => 'USD',
        ]);

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'period_type' => 'mensual',
            'date' => now()->toDateString(),
            'barber_id' => $other->id,
        ]);

        $liquidation = BarberLiquidation::query()->where('barber_id', $other->id)->firstOrFail();

        $this->assertSame('10.00', $liquidation->total_usd);
        $this->assertSame(1, $liquidation->ticket_count);
    }

    public function test_a_closed_barber_liquidation_does_not_block_new_sales(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $service, $barber);

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ]);

        $liquidation = BarberLiquidation::query()->where('barber_id', $barber->id)->firstOrFail();
        $this->actingAs($admin)->post(route('liquidations.close', $liquidation), [])->assertRedirect();
        $this->assertTrue($liquidation->fresh()->isClosed());

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'USD',
        ])->assertSessionMissing('error');

        $this->assertSame(2, Sale::count());
    }

    public function test_barber_liquidation_pages_render(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $service, $barber);

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ]);

        $liquidation = BarberLiquidation::query()->firstOrFail();

        $this->actingAs($admin)->get(route('liquidations.index'))
            ->assertOk()
            ->assertSee('Liquidaciones');

        $this->actingAs($admin)->get(route('liquidations.show', $liquidation))
            ->assertOk()
            ->assertSee($barber->name)
            ->assertSee('a pagar');

        $this->actingAs($admin)->get(route('liquidations.print', $liquidation))
            ->assertOk()
            ->assertSee($barber->name);
    }

    public function test_dashboard_ranking_outstanding_balance_drops_after_liquidations(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();
        $other = $this->barber();

        $this->registerSale($admin, $service, $barber);
        $this->registerSale($admin, $service, $other);

        $this->assertSame(4.0, $this->outstandingFor($admin, $barber));
        $this->assertSame(4.0, $this->outstandingFor($admin, $other));

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ])->assertRedirect();

        $liquidation = BarberLiquidation::query()->where('barber_id', $barber->id)->firstOrFail();
        $this->actingAs($admin)->post(route('liquidations.close', $liquidation), [])->assertRedirect();

        $this->assertSame(0.0, $this->outstandingFor($admin, $barber));
        $this->assertSame(4.0, $this->outstandingFor($admin, $other));

        $this->travel(1)->minutes();
        $this->registerSale($admin, $service, $barber);

        $this->assertSame(4.0, $this->outstandingFor($admin, $barber));
    }

    public function test_reopening_daily_closing_reopens_monthly_and_allows_sales(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'mensual',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $daily = Closing::query()->where('period_type', 'diario')->firstOrFail();
        $monthly = Closing::query()->where('period_type', 'mensual')->firstOrFail();

        $this->actingAs($admin)->post(route('closings.close', $daily), [])->assertRedirect();

        $this->assertTrue($daily->fresh()->isClosed());
        $this->assertTrue($monthly->fresh()->isClosed());

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'USD',
        ])->assertSessionHas('error');

        $this->actingAs($admin)->post(route('closings.reopen', $daily), [])->assertRedirect();

        $this->assertFalse($daily->fresh()->isClosed());
        $this->assertFalse($monthly->fresh()->isClosed());

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'USD',
        ])->assertSessionMissing('error');

        $this->assertSame(1, Sale::count());
    }

    public function test_closing_parent_closes_open_children_and_reopening_parent_reopens_them(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'mensual',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $daily = Closing::query()->where('period_type', 'diario')->firstOrFail();
        $monthly = Closing::query()->where('period_type', 'mensual')->firstOrFail();

        $this->actingAs($admin)->post(route('closings.close', $monthly), [])->assertRedirect();

        $this->assertTrue($monthly->fresh()->isClosed());
        $this->assertTrue($daily->fresh()->isClosed());

        $this->actingAs($admin)->post(route('closings.reopen', $monthly), [])->assertRedirect();

        $this->assertFalse($monthly->fresh()->isClosed());
        $this->assertFalse($daily->fresh()->isClosed());

        $this->assertTrue($service->exists());
        $this->assertTrue($barber->exists());
    }
}
