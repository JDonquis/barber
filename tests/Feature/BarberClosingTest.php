<?php

namespace Tests\Feature;

use App\Models\BarberPayment;
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

    private function registerPayment(User $admin, User $barber, float $amount): BarberPayment
    {
        $this->actingAs($admin)->post(route('liquidations.store'), [
            'barber_id' => $barber->id,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return BarberPayment::query()->latest('id')->firstOrFail();
    }

    public function test_admin_can_register_a_payment_and_the_balance_drops(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $service, $barber);
        $this->assertSame(4.0, $this->outstandingFor($admin, $barber));

        $payment = $this->registerPayment($admin, $barber, 2);

        $this->assertSame('2.00', $payment->amount_usd);
        $this->assertSame(2.0, $this->outstandingFor($admin, $barber));
    }

    public function test_payment_cannot_exceed_the_pending_balance(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $service, $barber);

        $this->actingAs($admin)->post(route('liquidations.store'), [
            'barber_id' => $barber->id,
            'amount' => 5,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, BarberPayment::query()->count());
        $this->assertSame(4.0, $this->outstandingFor($admin, $barber));
    }

    public function test_deleting_a_payment_restores_the_balance(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $service, $barber);
        $payment = $this->registerPayment($admin, $barber, 4);

        $this->assertSame(0.0, $this->outstandingFor($admin, $barber));

        $this->actingAs($admin)->delete(route('liquidations.payments.destroy', $payment))
            ->assertRedirect(route('liquidations.index'));

        $this->assertSame(0, BarberPayment::query()->count());
        $this->assertSame(4.0, $this->outstandingFor($admin, $barber));
    }

    public function test_liquidations_index_shows_balances_and_payment_history(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();

        $this->registerSale($admin, $service, $barber);

        $this->actingAs($admin)
            ->get(route('liquidations.index'))
            ->assertOk()
            ->assertSee('Saldo pendiente por barbero')
            ->assertSee($barber->name)
            ->assertSee('Toca para seleccionar')
            ->assertSee('Pagar')
            ->assertSee(money(4))
            ->assertSee('No hay pagos registrados todavía');

        $this->registerPayment($admin, $barber, 4);

        $this->actingAs($admin)
            ->get(route('liquidations.index'))
            ->assertOk()
            ->assertSee('Al día')
            ->assertSee('Pagos registrados');
    }

    public function test_dashboard_ranking_outstanding_balance_drops_after_payments(): void
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

        $this->registerPayment($admin, $barber, 4);

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

    public function test_pay_all_registers_the_full_balance_for_every_barber(): void
    {
        $this->setting('commission_rate', 40);
        $service = $this->service(10);
        $admin = $this->admin();
        $barber = $this->barber();
        $other = $this->barber();

        $this->registerSale($admin, $service, $barber);
        $this->registerSale($admin, $service, $other);

        $this->actingAs($admin)->post(route('liquidations.pay-all'))
            ->assertRedirect(route('liquidations.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, BarberPayment::query()->count());
        $this->assertSame(0.0, $this->outstandingFor($admin, $barber));
        $this->assertSame(0.0, $this->outstandingFor($admin, $other));
        $this->assertSame(8.0, (float) BarberPayment::query()->sum('amount_usd'));
    }
}
