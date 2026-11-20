<?php

namespace Tests\Feature;

use App\Models\Closing;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Product;
use App\Models\Retention;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\ClosingService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsdConversionTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return current_company();
    }

    private function user(Company $company, string $role = User::ROLE_ADMIN): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => 'Usuario '.$role,
            'email' => str()->lower(str()->random(12)).'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'active' => true,
        ]);
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'shop_name' => 'Barbería Local',
            'commission_rate' => 40,
            'local_currency' => 'VES',
            'dolar_api_source' => 'oficial',
            'exchange_rate_manual' => 30,
        ], $overrides);
    }

    private function enableLocalMode(float $rate = 30): void
    {
        app(SettingService::class)->setMany([
            'usd_conversion_enabled' => '0',
            'usd_conversion_reference_rate' => (string) $rate,
            'exchange_rate_manual' => (string) $rate,
            'commission_rate' => '40',
            'local_currency' => 'VES',
        ]);
    }

    public function test_settings_page_shows_the_usd_conversion_option(): void
    {
        $admin = $this->user($this->company());

        $this->actingAs($admin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Conversión de dólares')
            ->assertSee('Permitir conversión y cobros en USD');
    }

    public function test_disabling_conversion_converts_catalog_prices_and_retentions(): void
    {
        $admin = $this->user($this->company());

        $service = Service::create(['name' => 'Corte', 'price' => 10, 'active' => true]);
        $product = Product::create(['name' => 'Cera', 'price' => 20, 'cost' => 5, 'stock' => 3, 'active' => true]);
        $retention = Retention::create(['name' => 'Herramientas', 'amount_usd' => 3, 'frequency' => 'mensual', 'active' => true]);

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'usd_conversion_enabled' => '0',
        ]))->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();

        $this->assertFalse(usd_conversion_enabled());
        $this->assertSame('300.00', $service->fresh()->price);
        $this->assertSame('600.00', $product->fresh()->price);
        $this->assertSame('150.00', $product->fresh()->cost);
        $this->assertSame('90.00', $retention->fresh()->amount_usd);
        $this->assertSame('30', setting('usd_conversion_reference_rate'));
    }

    public function test_local_sale_uses_only_local_currency_and_rejects_usd_payment(): void
    {
        $company = $this->company();
        $admin = $this->user($company);
        $barber = $this->user($company, User::ROLE_BARBER);
        $this->enableLocalMode();

        $service = Service::create([
            'name' => 'Corte',
            'price' => 300,
            'duration_minutes' => 30,
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'tip_usd' => 30,
            'payment_currency' => 'VES',
            'payment_method' => 'Efectivo Bs',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame('VES', $sale->payment_currency);
        $this->assertSame('11.00', $sale->total_usd);
        $this->assertSame('330.00', $sale->total_ves);
        $this->assertSame('4.00', $sale->barber_commission_usd);
        $this->assertSame('1.00', $sale->tip_usd);
        $this->assertSame('30.0000', $sale->exchange_rate);
        $this->assertSame(330.0, $sale->localTotal());
        $this->assertSame(120.0, $sale->localCommission());

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'USD',
        ])->assertSessionHasErrors('payment_currency');

        $this->assertSame(1, Sale::query()->count());
    }

    public function test_expenses_and_loans_are_recorded_in_local_currency(): void
    {
        $company = $this->company();
        $admin = $this->user($company);
        $barber = $this->user($company, User::ROLE_BARBER);
        $this->enableLocalMode();

        $this->actingAs($admin)->post(route('expenses.store'), [
            'concept' => 'Insumos',
            'amount_usd' => 60,
            'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame('60.00', $expense->amount_local);
        $this->assertSame('2.00', $expense->amount_usd);

        $this->actingAs($admin)->post(route('loans.store'), [
            'barber_id' => $barber->id,
            'amount_usd' => 300,
            'exchange_rate' => 30,
            'loan_date' => now()->toDateString(),
        ])->assertRedirect();

        $loan = Loan::query()->firstOrFail();
        $this->assertSame('300.00', $loan->amount_ves);
        $this->assertSame('10.00', $loan->amount_usd);
        $this->assertSame('30.0000', $loan->exchange_rate);

        $this->actingAs($admin)->post(route('loans.payments.store', $loan), [
            'amount_usd' => 150,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect();

        $loan->refresh();
        $this->assertSame('5.00', $loan->paid_usd);
        $this->assertSame(150.0, $loan->localPaid());
        $this->assertSame(150.0, $loan->localBalance());
    }

    public function test_dashboard_hides_exchange_rate_when_conversion_is_disabled(): void
    {
        $admin = $this->user($this->company());
        $this->enableLocalMode();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Tasa de cambio del día')
            ->assertSee('Bs.');
    }

    public function test_reactivating_conversion_converts_catalog_back_to_usd(): void
    {
        $admin = $this->user($this->company());
        $service = Service::create(['name' => 'Corte', 'price' => 300, 'active' => true]);
        $this->enableLocalMode();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload([
            'usd_conversion_enabled' => '1',
        ]))->assertRedirect(route('settings.edit'));

        $this->assertTrue(usd_conversion_enabled());
        $this->assertSame('10.00', $service->fresh()->price);
    }

    public function test_local_product_sale_and_retention_closing_stay_in_local_currency(): void
    {
        $company = $this->company();
        $admin = $this->user($company);
        $barber = $this->user($company, User::ROLE_BARBER);
        $this->enableLocalMode();

        app(SettingService::class)->set('retentions_enabled', '1');

        $service = Service::create([
            'name' => 'Corte',
            'price' => 300,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $product = Product::create([
            'name' => 'Cera',
            'price' => 600,
            'cost' => 300,
            'stock' => 5,
            'active' => true,
        ]);
        Retention::create([
            'name' => 'Herramientas',
            'amount_usd' => 90,
            'frequency' => 'mensual',
            'active' => true,
        ]);

        $this->actingAs($admin)->post(route('sales.store-service'), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'payment_currency' => 'VES',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('sales.store-product'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_currency' => 'VES',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $productSale = Sale::query()->where('type', Sale::TYPE_PRODUCT)->firstOrFail();
        $this->assertSame('600.00', $productSale->total_ves);
        $this->assertSame('20.00', $productSale->total_usd);

        $closing = app(ClosingService::class)->generate(Closing::PERIOD_DAILY);

        $this->assertSame('30.0000', $closing->exchange_rate);
        $this->assertSame(900.0, $closing->localTotal());
        $this->assertSame(120.0, $closing->localCommission());
        $this->assertSame(3.0, $closing->localRetentions());
        $this->assertSame(117.0, $closing->localBarberEarnings());
    }

    public function test_local_mode_pages_render_without_usd_elements(): void
    {
        $admin = $this->user($this->company());
        $this->enableLocalMode();
        app(SettingService::class)->set('retentions_enabled', '1');

        foreach ([
            route('dashboard'),
            route('services.index'),
            route('services.create'),
            route('products.index'),
            route('products.create'),
            route('sales.index'),
            route('sales.create-service'),
            route('sales.create-product'),
            route('closings.index'),
            route('liquidations.index'),
            route('settings.edit'),
            route('loans.index'),
            route('loans.create'),
            route('expenses.index'),
            route('expenses.create'),
            route('retentions.index'),
            route('retentions.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
