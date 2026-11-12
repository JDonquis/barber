<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
    }

    private function barber(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_BARBER,
            'active' => true,
        ]);
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'shop_name' => 'Barbería Colombia',
            'commission_rate' => 40,
            'local_currency' => 'COP',
            'dolar_api_source' => 'oficial',
        ], $overrides);
    }

    public function test_settings_page_shows_local_currency_selector(): void
    {
        $this->actingAs($this->admin())
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Moneda local')
            ->assertSee('Pesos colombianos');
    }

    public function test_settings_can_update_company_timezone(): void
    {
        $admin = $this->admin();
        $company = current_company();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'local_currency' => 'VES',
            'timezone' => 'America/Bogota',
        ]))->assertRedirect(route('settings.edit'));

        $this->assertSame('America/Bogota', $company->fresh()->timezone);
    }

    public function test_company_timezone_is_applied_to_tenant_requests(): void
    {
        $admin = $this->admin();
        current_company()->update(['timezone' => 'America/Bogota']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $this->assertSame('America/Bogota', config('app.timezone'));
    }

    public function test_public_booking_applies_the_company_timezone(): void
    {
        $company = current_company();
        $company->update(['timezone' => 'America/Bogota']);

        $this->get(route('booking.show', $company->slug))->assertOk();

        $this->assertSame('America/Bogota', config('app.timezone'));
    }

    public function test_shop_can_select_colombian_pesos_and_uses_the_cop_api(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['COP' => 4000],
            ], 200),
            've.dolarapi.com/*' => Http::response(['promedio' => 30], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload())
            ->assertRedirect(route('settings.edit'));

        $this->assertSame('COP', setting('local_currency'));
        $this->assertSame('COP', local_currency());
        $this->assertSame('COP $', local_currency_symbol());
        $this->assertSame('COP $ 4.000,00', ves(4000));

        $rate = app(ExchangeRateService::class)->current();

        $this->assertSame(4000.0, $rate);
    }

    public function test_colombian_pesos_can_be_used_as_sale_payment_currency(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response(['rates' => ['COP' => 4000]], 200),
            've.dolarapi.com/*' => Http::response(['promedio' => 30], 200),
        ]);

        $admin = $this->admin();
        $barber = $this->barber();
        app(SettingService::class)->set('local_currency', 'COP');

        $service = Service::create([
            'name' => 'Corte',
            'price' => 10,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'COP',
            'payment_method' => 'Efectivo COP',
        ])->assertRedirect()->assertSessionHasNoErrors();


        $sale = \App\Models\Sale::query()->firstOrFail();

        $this->assertSame('COP', $sale->payment_currency);
        $this->assertSame(40000.0, (float) $sale->total_ves);
    }

    public function test_local_currency_cannot_change_after_financial_records_exist(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();

        $service = Service::create([
            'name' => 'Corte',
            'price' => 10,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'VES',
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'local_currency' => 'COP',
        ]))->assertSessionHasErrors('local_currency');

        $this->assertSame('VES', local_currency());
    }

    public function test_switching_local_currency_clears_cached_rates(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response(['rates' => ['COP' => 4000]], 200),
            've.dolarapi.com/*' => Http::response(['promedio' => 30], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'local_currency' => 'VES',
        ]))->assertRedirect(route('settings.edit'));

        $this->assertSame(30.0, app(ExchangeRateService::class)->current());

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'local_currency' => 'COP',
        ]))->assertRedirect(route('settings.edit'));

        $this->assertSame(4000.0, app(ExchangeRateService::class)->current());
    }
}
