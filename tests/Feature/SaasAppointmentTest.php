<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentDayOff;
use App\Models\AppointmentSchedule;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasAppointmentTest extends TestCase
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

    private function service(int $duration = 30): Service
    {
        return Service::create([
            'name' => 'Corte de prueba',
            'price' => 10,
            'duration_minutes' => $duration,
            'active' => true,
        ]);
    }

    public function test_appointment_admin_pages_render(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('appointments.index'))->assertOk()->assertSee('Enlace público');
        $this->actingAs($admin)->get(route('appointments.availability'))->assertOk()->assertSee('Horario semanal');
    }

    public function test_appointment_list_links_to_whatsapp_with_a_default_message(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $service = $this->service(30);
        $date = now()->toDateString();

        Appointment::create([
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => 30,
            'client_name' => 'Ana Pérez',
            'client_phone' => '04121234567',
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'status' => Appointment::STATUS_PENDING,
        ]);

        $this->actingAs($admin)->get(route('appointments.index', ['date' => $date]))
            ->assertOk()
            ->assertSee('wa.me/584121234567', false)
            ->assertSee('Hola+Ana+P%C3%A9rez', false)
            ->assertSee('m%C3%A9todo+de+pago', false);
    }

    public function test_appointments_index_defaults_to_pending_oldest_first_without_date_filter(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $service = $this->service(30);

        $make = function (array $attributes) use ($barber, $service) {
            return Appointment::create($attributes + [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'duration_minutes' => 30,
                'client_phone' => '04120000000',
                'start_time' => '09:00',
                'end_time' => '09:30',
            ]);
        };

        $make([
            'client_name' => 'Cliente Antiguo',
            'date' => now()->subDays(3)->toDateString(),
            'status' => Appointment::STATUS_PENDING,
        ]);

        $make([
            'client_name' => 'Cliente Nuevo',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => Appointment::STATUS_PENDING,
        ]);

        $make([
            'client_name' => 'Cliente Confirmado',
            'date' => now()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($admin)->get(route('appointments.index'))->assertOk();

        $response->assertSeeInOrder(['Cliente Antiguo', 'Cliente Nuevo']);
        $response->assertDontSee('Cliente Confirmado');
    }

    public function test_admin_can_create_schedules_days_off_and_change_service_duration(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $company = $this->company();

        $this->actingAs($admin)->post(route('appointments.schedules.store'), [
            'day_of_week' => 1,
            'barber_id' => $barber->id,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])->assertRedirect();

        $schedule = AppointmentSchedule::query()->firstOrFail();

        $this->assertSame($company->id, $schedule->company_id);
        $this->assertSame($barber->id, $schedule->barber_id);

        $this->actingAs($admin)->post(route('appointments.days-off.store'), [
            'date' => now()->addDays(2)->toDateString(),
            'barber_id' => $barber->id,
            'reason' => 'Descanso',
        ])->assertRedirect();

        $this->assertSame(1, AppointmentDayOff::query()->count());

        $service = $this->service(30);

        $this->actingAs($admin)->put(route('services.update', $service), [
            'name' => $service->name,
            'price' => 12,
            'duration_minutes' => 45,
            'active' => '1',
        ])->assertRedirect(route('services.index'));

        $this->assertSame(45, $service->fresh()->duration_minutes);
    }

    public function test_admin_can_sync_weekly_schedules_per_scope(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();

        AppointmentSchedule::create([
            'day_of_week' => 3,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'active' => true,
        ]);

        $days = array_fill_keys(range(0, 6), ['active' => false, 'blocks' => []]);
        $days[1] = [
            'active' => true,
            'blocks' => [
                ['start' => '09:00', 'end' => '13:30'],
                ['start' => '14:30', 'end' => '19:00'],
            ],
        ];

        $this->actingAs($admin)->post(route('appointments.schedules.sync'), [
            'barber_id' => '',
            'days' => json_encode($days),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, AppointmentSchedule::query()->whereNull('barber_id')->count());
        $this->assertSame(0, AppointmentSchedule::query()->where('day_of_week', 3)->count());

        $this->actingAs($admin)->post(route('appointments.schedules.sync'), [
            'barber_id' => $barber->id,
            'days' => json_encode($days),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, AppointmentSchedule::query()->where('barber_id', $barber->id)->count());
        $this->assertSame(2, AppointmentSchedule::query()->whereNull('barber_id')->count());
    }

    public function test_schedule_sync_returns_json_for_autosave(): void
    {
        $admin = $this->admin();

        $days = array_fill_keys(range(0, 6), ['active' => false, 'blocks' => []]);

        $this->actingAs($admin)->postJson(route('appointments.schedules.sync'), [
            'barber_id' => null,
            'days' => json_encode($days),
        ])->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_schedule_sync_rejects_overlapping_blocks(): void
    {
        $admin = $this->admin();

        $days = array_fill_keys(range(0, 6), ['active' => false, 'blocks' => []]);
        $days[1] = [
            'active' => true,
            'blocks' => [
                ['start' => '09:00', 'end' => '13:00'],
                ['start' => '12:00', 'end' => '18:00'],
            ],
        ];

        $this->actingAs($admin)->post(route('appointments.schedules.sync'), [
            'barber_id' => '',
            'days' => json_encode($days),
        ])->assertSessionHasErrors('days.1.blocks.1.start');

        $this->assertSame(0, AppointmentSchedule::query()->count());
    }

    public function test_admin_can_update_service_durations_from_availability(): void
    {
        $admin = $this->admin();
        $service = $this->service(30);

        $this->actingAs($admin)->post(route('appointments.services.durations.update'), [
            'durations' => [$service->id => 45],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(45, $service->fresh()->duration_minutes);

        $this->actingAs($admin)->postJson(route('appointments.services.durations.update'), [
            'durations' => [$service->id => 50],
        ])->assertOk()->assertJsonPath('status', 'ok');

        $this->assertSame(50, $service->fresh()->duration_minutes);

        $this->actingAs($admin)->post(route('appointments.services.durations.update'), [
            'durations' => [$service->id => 2],
        ])->assertSessionHasErrors('durations.'.$service->id);

        $this->assertSame(50, $service->fresh()->duration_minutes);
    }

    public function test_public_page_can_list_available_days(): void
    {
        $company = $this->company();
        $barber = $this->barber();
        $service = $this->service(30);
        $date = now()->addDay()->startOfDay();

        AppointmentSchedule::create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'active' => true,
        ]);

        $response = $this->getJson(route('booking.days', [
            'company' => $company->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
        ]))->assertOk()->assertJsonStructure(['days' => [['date', 'count']]]);

        $day = collect($response->json('days'))->firstWhere('date', $date->toDateString());

        $this->assertNotNull($day);
        $this->assertGreaterThan(0, $day['count']);
    }

    public function test_public_page_can_list_slots_and_book_an_appointment(): void
    {
        $company = $this->company();
        $barber = $this->barber();
        $service = $this->service(30);
        $date = now()->addDay()->startOfDay();

        AppointmentSchedule::create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'active' => true,
        ]);

        $this->get(route('booking.show', $company->slug))
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee($service->name);

        $slots = $this->getJson(route('booking.slots', [
            'company' => $company->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
        ]))->assertOk()->json('slots');

        $this->assertNotEmpty($slots);
        $this->assertSame('09:00', $slots[0]['time']);

        $this->post(route('booking.store', $company->slug), [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
            'start_time' => '09:00',
            'client_name' => 'Cliente Prueba',
            'client_phone' => '04120000000',
            'client_email' => 'cliente@example.com',
        ])->assertRedirect(route('booking.show', $company->slug));

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($company->id, $appointment->company_id);
        $this->assertSame($barber->id, $appointment->barber_id);
        $this->assertSame($service->name, $appointment->service_name);
        $this->assertSame(Appointment::STATUS_PENDING, $appointment->status);
        $this->assertSame('09:00', mb_substr($appointment->start_time, 0, 5));

        $remaining = $this->getJson(route('booking.slots', [
            'company' => $company->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
        ]))->assertOk()->json('slots');

        $this->assertNotContains('09:00', array_column($remaining, 'time'));
    }

    public function test_day_off_blocks_the_public_slots(): void
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

        AppointmentDayOff::create([
            'date' => $date->toDateString(),
            'reason' => 'Feriado',
        ]);

        $this->getJson(route('booking.slots', [
            'company' => $company->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
        ]))->assertOk()->assertJsonCount(0, 'slots');
    }

    public function test_appointments_are_isolated_between_companies(): void
    {
        $first = $this->company();
        $barber = $this->barber();
        $service = $this->service();

        Appointment::create([
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => 30,
            'client_name' => 'Cliente A',
            'client_phone' => '04120000000',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'status' => Appointment::STATUS_PENDING,
        ]);

        $this->assertSame(1, Appointment::query()->count());

        $second = Company::create([
            'name' => 'Otra Barbería',
            'slug' => 'otra-barberia',
            'active' => true,
        ]);

        app(TenantManager::class)->set($second);

        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(0, AppointmentSchedule::query()->count());
        $this->assertNotSame($first->id, $second->id);
    }
}
