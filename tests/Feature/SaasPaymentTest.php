<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPayment;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaasPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return app(TenantManager::class)->get();
    }

    private function tenantAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
    }

    private function superAdmin(): User
    {
        return User::create([
            'company_id' => null,
            'name' => 'Super Admin',
            'email' => 'super-pagos@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_super_admin' => true,
            'active' => true,
        ]);
    }

    public function test_tenant_can_see_payment_data_and_submit_a_proof(): void
    {
        Storage::fake('public');

        $admin = $this->tenantAdmin();
        $company = $this->company();

        $this->actingAs($admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('30847627')
            ->assertSee('0102 Venezuela')
            ->assertSee('04125800610');

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'paid_at' => now()->toDateString(),
                'reference' => 'REF-123',
                'notes' => 'Pago del mes',
                'screenshot' => UploadedFile::fake()->image('pago.jpg'),
            ])
            ->assertRedirect();

        $payment = CompanyPayment::query()->firstOrFail();

        $this->assertSame($company->id, $payment->company_id);
        $this->assertSame($admin->id, $payment->user_id);
        $this->assertSame(CompanyPayment::STATUS_PENDING, $payment->status);
        $this->assertEquals(5, (float) $payment->amount_usd);
        $this->assertEquals(950, (float) $payment->exchange_rate);
        $this->assertEquals(4750, (float) $payment->amount_ves);
        $this->assertSame('REF-123', $payment->reference);
        Storage::disk('public')->assertExists($payment->screenshot_path);
    }

    public function test_exempt_company_cannot_submit_a_payment(): void
    {
        Storage::fake('public');

        $admin = $this->tenantAdmin();
        $this->company()->update(['is_exempt' => true]);

        $this->actingAs($admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Barbería exonerada');

        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'paid_at' => now()->toDateString(),
                'screenshot' => UploadedFile::fake()->image('pago.jpg'),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('company_payments', 0);
    }

    public function test_admin_payment_pages_render(): void
    {
        $company = $this->company();
        $tenant = $this->tenantAdmin();

        CompanyPayment::create([
            'company_id' => $company->id,
            'user_id' => $tenant->id,
            'period' => now()->format('Y-m'),
            'amount_usd' => 5,
            'exchange_rate' => 950,
            'amount_ves' => 4750,
            'payment_method' => 'pago_movil',
            'reference' => 'REF-RENDER',
            'paid_at' => now()->toDateString(),
            'screenshot_path' => 'payment-proofs/render.jpg',
            'status' => CompanyPayment::STATUS_PENDING,
        ]);

        $super = $this->superAdmin();

        $this->actingAs($super)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee('REF-RENDER');

        $this->actingAs($super)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Tasa PayPal');
    }

    public function test_admin_can_approve_a_payment_and_renew_the_company(): void
    {
        $company = $this->company();
        $tenant = $this->tenantAdmin();

        $payment = CompanyPayment::create([
            'company_id' => $company->id,
            'user_id' => $tenant->id,
            'period' => now()->format('Y-m'),
            'amount_usd' => 5,
            'exchange_rate' => 950,
            'amount_ves' => 4750,
            'payment_method' => 'pago_movil',
            'reference' => 'REF-APPROVE',
            'paid_at' => now()->toDateString(),
            'screenshot_path' => 'payment-proofs/test.jpg',
            'status' => CompanyPayment::STATUS_PENDING,
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect();

        $payment->refresh();
        $company->refresh();

        $this->assertSame(CompanyPayment::STATUS_APPROVED, $payment->status);
        $this->assertNotNull($payment->reviewed_at);
        $this->assertSame(now()->toDateString(), $company->last_payment_at->toDateString());
        $this->assertSame(now()->addMonth()->toDateString(), $company->next_payment_due_at->toDateString());
    }

    public function test_admin_can_update_the_paypal_rate_and_payment_data(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('admin.settings.update'), [
                'saas_paypal_rate' => 1000,
                'saas_monthly_fee' => 6,
                'payment_cedula' => '12345678',
                'payment_bank' => '0105 Mercantil',
                'payment_phone' => '04140000000',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('1000', PlatformSetting::query()->where('key', 'saas_paypal_rate')->value('value'));
        $this->assertSame('6', PlatformSetting::query()->where('key', 'saas_monthly_fee')->value('value'));
        $this->assertSame('12345678', PlatformSetting::query()->where('key', 'payment_cedula')->value('value'));
    }
}
