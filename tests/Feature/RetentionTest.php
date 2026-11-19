<?php

namespace Tests\Feature;

use App\Models\Closing;
use App\Models\Retention;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionTest extends TestCase
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

    private function enableRetentions(): void
    {
        app(SettingService::class)->set('retentions_enabled', '1');
    }

    private function service(float $price = 10): Service
    {
        return Service::create(['name' => 'Corte clásico', 'price' => $price, 'active' => true]);
    }

    private function registerSale(User $admin, Service $service, User $barber): Sale
    {
        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'USD',
        ])->assertRedirect();

        return Sale::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'shop_name' => 'Barbería de prueba',
            'commission_rate' => 40,
            'dolar_api_source' => 'oficial',
        ], $overrides);
    }

    public function test_retentions_module_is_disabled_by_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('retentions.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('Retenciones');
    }

    public function test_admin_can_enable_retentions_from_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'retentions_enabled' => 1,
        ]))->assertRedirect(route('settings.edit'));

        $this->assertSame('1', app(SettingService::class)->get('retentions_enabled'));

        $this->actingAs($admin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Módulos adicionales');

        $this->actingAs($admin)
            ->get(route('retentions.index'))
            ->assertOk()
            ->assertSee('Retenciones');

        $this->actingAs($admin)->get(route('retentions.create'))->assertOk();
    }

    public function test_admin_can_create_specific_and_global_retentions(): void
    {
        $this->enableRetentions();
        $admin = $this->admin();
        $barber = $this->barber();

        $this->actingAs($admin)->post(route('retentions.store'), [
            'name' => 'Cuota de herramientas',
            'amount_usd' => 2,
            'frequency' => Retention::FREQUENCY_DAILY,
            'barber_id' => $barber->id,
            'active' => 1,
        ])->assertRedirect(route('retentions.index'));

        $this->actingAs($admin)->post(route('retentions.store'), [
            'name' => 'Fondo de ahorro',
            'amount_usd' => 7,
            'frequency' => Retention::FREQUENCY_WEEKLY,
            'barber_id' => null,
            'active' => 1,
        ])->assertRedirect(route('retentions.index'));

        $this->assertDatabaseHas('retentions', [
            'name' => 'Cuota de herramientas',
            'barber_id' => $barber->id,
            'amount_usd' => 2,
        ]);

        $this->assertDatabaseHas('retentions', [
            'name' => 'Fondo de ahorro',
            'barber_id' => null,
            'amount_usd' => 7,
        ]);

        $this->actingAs($admin)
            ->get(route('retentions.index'))
            ->assertOk()
            ->assertSee('Cuota de herramientas')
            ->assertSee('Fondo de ahorro')
            ->assertSee('Todos los barberos');

        $retention = Retention::query()->where('name', 'Cuota de herramientas')->firstOrFail();
        $this->actingAs($admin)->get(route('retentions.edit', $retention))->assertOk();
    }

    public function test_barber_closing_deducts_only_applicable_retentions(): void
    {
        $this->enableRetentions();
        $this->setting('commission_rate', 40);

        $admin = $this->admin();
        $barber = $this->barber();
        $other = $this->barber();
        $service = $this->service(10);

        Retention::create([
            'barber_id' => $barber->id,
            'name' => 'Cuota de herramientas',
            'amount_usd' => 2,
            'frequency' => Retention::FREQUENCY_DAILY,
            'active' => true,
        ]);

        Retention::create([
            'barber_id' => null,
            'name' => 'Fondo de ahorro',
            'amount_usd' => 7,
            'frequency' => Retention::FREQUENCY_WEEKLY,
            'active' => true,
        ]);

        $this->registerSale($admin, $service, $barber);
        $this->registerSale($admin, $service, $other);

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ])->assertRedirect();

        $closing = Closing::query()->where('barber_id', $barber->id)->firstOrFail();

        $this->assertSame('4.00', $closing->barber_commission_usd);
        $this->assertSame('3.00', $closing->barber_retentions_usd);
        $this->assertSame(1.0, $closing->barberEarningsUsd());
        $this->assertSame('9.00', $closing->shop_amount_usd);
        $this->assertCount(2, $closing->details['retenciones']);

        $this->actingAs($admin)
            ->get(route('closings.show', $closing))
            ->assertOk()
            ->assertSee('Retenciones aplicadas')
            ->assertSee('Cuota de herramientas');

        $this->actingAs($admin)
            ->get(route('closings.print', $closing))
            ->assertOk()
            ->assertSee('Retenciones aplicadas');

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $other->id,
        ])->assertRedirect();

        $otherClosing = Closing::query()->where('barber_id', $other->id)->firstOrFail();

        $this->assertSame('1.00', $otherClosing->barber_retentions_usd);
        $this->assertSame(3.0, $otherClosing->barberEarningsUsd());
        $this->assertCount(1, $otherClosing->details['retenciones']);
    }

    public function test_disabled_module_does_not_deduct_retentions(): void
    {
        $this->setting('commission_rate', 40);
        $admin = $this->admin();
        $barber = $this->barber();
        $service = $this->service(10);

        Retention::create([
            'barber_id' => $barber->id,
            'name' => 'Cuota de herramientas',
            'amount_usd' => 2,
            'frequency' => Retention::FREQUENCY_DAILY,
            'active' => true,
        ]);

        $this->registerSale($admin, $service, $barber);

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ])->assertRedirect();

        $closing = Closing::query()->where('barber_id', $barber->id)->firstOrFail();

        $this->assertSame('0.00', $closing->barber_retentions_usd);
        $this->assertSame(4.0, $closing->barberEarningsUsd());
    }

    public function test_barber_cannot_access_the_retentions_module(): void
    {
        $this->enableRetentions();
        $barber = $this->barber();

        $this->actingAs($barber)->get(route('retentions.index'))->assertForbidden();
    }
}
