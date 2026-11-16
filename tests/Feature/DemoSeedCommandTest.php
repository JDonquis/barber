<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Closing;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_command_populates_a_barbershop_with_demo_data(): void
    {
        $company = Company::create([
            'name' => 'BarberTest',
            'slug' => 'barbertest',
            'active' => true,
        ]);

        $this->artisan('demo:seed', [
            'company' => 'barbertest',
            '--days' => 5,
        ])->assertSuccessful();

        app(TenantManager::class)->set($company);

        $this->assertSame(4, User::query()->barbers()->count());
        $this->assertTrue(User::query()->where('email', 'admin@barbertest.demo')->exists());
        $this->assertTrue(Service::query()->exists());
        $this->assertTrue(Product::query()->exists());
        $this->assertTrue(Customer::query()->exists());
        $this->assertTrue(Appointment::query()->exists());
        $this->assertTrue(Sale::query()->exists());
        $this->assertTrue(Expense::query()->exists());
        $this->assertTrue(Loan::query()->exists());
        $this->assertTrue(Closing::query()->exists());

        $this->assertTrue(
            Sale::query()->where('type', Sale::TYPE_SERVICE)->exists(),
            'The demo data should contain service sales.'
        );
    }
}
