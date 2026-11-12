<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSchedule;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PushSubscription;
use App\Models\ReminderLog;
use App\Models\ReminderSubscription;
use App\Models\Service;
use App\Models\User;
use App\Services\CustomerReminderService;
use App\Services\TenantManager;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasCustomerReminderTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return app(TenantManager::class)->get();
    }

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

    private function service(): Service
    {
        return Service::create([
            'name' => 'Corte',
            'price' => 10,
            'duration_minutes' => 30,
            'active' => true,
        ]);
    }

    private function customer(Company $company, string $phone = '04120000000'): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'name' => 'Cliente Frecuente',
            'phone' => $phone,
        ]);
    }

    public function test_booking_creates_a_customer_without_email_and_links_the_appointment(): void
    {
        $company = $this->company();
        $barber = $this->barber();
        $service = $this->service();
        $date = now()->addDay()->startOfDay();

        AppointmentSchedule::create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'active' => true,
        ]);

        $this->post(route('booking.store', $company->slug), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
            'start_time' => '09:00',
            'client_name' => 'Juan Cliente',
            'client_phone' => '0412-555-0000',
        ])->assertRedirect(route('booking.show', $company->slug));

        $customer = Customer::query()->firstOrFail();
        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($company->id, $customer->company_id);
        $this->assertSame('04125550000', $customer->phone);
        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertArrayNotHasKey('email', $customer->getAttributes());
        $this->assertSame($customer->id, session('client_customer_id'));
    }

    public function test_magic_link_and_push_subscription_work_for_a_customer(): void
    {
        $company = $this->company();
        $customer = $this->customer($company);

        $this->get(route('booking.access', [
            'company' => $company->slug,
            'token' => $customer->access_token,
        ]))->assertRedirect(route('booking.account', $company->slug));

        $this->withSession([
            'client_customer_id' => $customer->id,
            'client_company_id' => $company->id,
        ])->postJson(route('booking.push.subscribe', $company->slug), [
            'endpoint' => 'https://push.example.com/endpoint/123',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
            'content_encoding' => 'aesgcm',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame($customer->id, PushSubscription::query()->first()->customer_id);
    }

    public function test_client_can_activate_reminders_and_completion_schedules_the_next_one(): void
    {
        $company = $this->company();
        $admin = $this->admin();
        $barber = $this->barber();
        $service = $this->service();
        $customer = $this->customer($company);

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => 30,
            'client_name' => $customer->name,
            'client_phone' => $customer->phone,
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->withSession([
            'client_customer_id' => $customer->id,
            'client_company_id' => $company->id,
        ])->post(route('booking.reminders.update', $company->slug), [
            'interval_days' => 7,
        ])->assertRedirect();

        $subscription = ReminderSubscription::query()->firstOrFail();

        $this->assertSame(7, $subscription->interval_days);
        $this->assertNull($subscription->next_reminder_at);

        $this->actingAs($admin)
            ->post(route('appointments.status', $appointment), ['status' => Appointment::STATUS_COMPLETED])
            ->assertRedirect();

        $subscription->refresh();

        $this->assertNotNull($subscription->next_reminder_at);
        $this->assertSame(now()->addDays(7)->toDateString(), $subscription->next_reminder_at->toDateString());
        $this->assertSame(now()->toDateString(), $customer->fresh()->last_visit_at->toDateString());
    }

    public function test_due_reminders_are_sent_and_logged(): void
    {
        $company = $this->company();
        $customer = $this->customer($company);

        $subscription = ReminderSubscription::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'interval_days' => 15,
            'active' => true,
            'next_reminder_at' => now()->subHour(),
        ]);

        $this->mock(WebPushService::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'sent' => 1,
                'failed' => 0,
                'removed' => 0,
                'errors' => [],
            ]);
        });

        $stats = app(CustomerReminderService::class)->sendDueReminders();

        $subscription->refresh();

        $this->assertSame(1, $stats['sent']);
        $this->assertNotNull($subscription->last_reminder_at);
        $this->assertTrue($subscription->next_reminder_at->isFuture());
        $this->assertDatabaseHas('reminder_logs', [
            'customer_id' => $customer->id,
            'status' => ReminderLog::STATUS_SENT,
        ]);
    }

    public function test_per_shop_pwa_manifest_and_icon_are_available(): void
    {
        $company = $this->company();

        $this->get(route('booking.manifest', $company->slug))
            ->assertOk()
            ->assertJsonPath('name', $company->name)
            ->assertJsonPath('scope', '/reservar/'.$company->slug)
            ->assertJsonPath('start_url', '/reservar/'.$company->slug);

        $this->get(route('booking.icon', ['company' => $company->slug, 'size' => 192]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_tenant_admin_can_see_customers_with_reminder_status(): void
    {
        $company = $this->company();
        $admin = $this->admin();
        $customer = $this->customer($company);

        ReminderSubscription::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'interval_days' => 15,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('15');
    }
}
