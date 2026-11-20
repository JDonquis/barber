<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use App\Services\CustomerReminderService;
use App\Services\SettingService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        protected AppointmentAvailabilityService $availability,
        protected CustomerReminderService $reminders,
    ) {}

    public function show(Company $company)
    {
        $this->bootTenant($company);

        $bookedAppointment = session('booking_success')
            ? Appointment::query()->with(['barber', 'service'])->find(session('booking_success'))
            : null;

        $customer = $this->clientCustomer($company);
        $subscription = $customer
            ? $customer->subscription()->first()
            : null;

        $services = Service::query()->active()->orderBy('name')->get();
        $barbers = User::query()->active()->barbers()->orderBy('name')->get();

        return view('booking.show', [
            'company' => $company,
            'services' => $services,
            'barbers' => $barbers,
            'servicesData' => $services->map(fn (Service $service) => [
                'id' => (string) $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration' => (int) ($service->duration_minutes ?? 30),
                'price' => price_money($service->price),
            ])->values(),
            'barbersData' => $barbers->map(fn (User $barber) => [
                'id' => (string) $barber->id,
                'name' => $barber->name,
                'initials' => $this->initials($barber->name),
            ])->values(),
            'bookedAppointment' => $bookedAppointment,
            'customer' => $customer,
            'subscription' => $subscription,
            'pushSubscribed' => $customer ? $customer->pushSubscriptions()->exists() : false,
            'pushPublicKey' => config('webpush.vapid.public_key'),
            'minDate' => now()->toDateString(),
            'maxDate' => now()->addDays(90)->toDateString(),
        ]);
    }

    public function days(Request $request, Company $company)
    {
        $this->bootTenant($company);

        $data = $request->validate([
            'service_id' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('company_id', $company->id)
                    ->where('active', true),
            ],
            'barber_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', $company->id)
                    ->where('role', User::ROLE_BARBER)
                    ->where('active', true)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $service = Service::query()->active()->findOrFail($data['service_id']);
        $barber = User::query()->active()->barbers()->findOrFail($data['barber_id']);
        $start = now()->startOfDay();
        $days = [];

        for ($offset = 0; $offset < 14; $offset++) {
            $date = $start->copy()->addDays($offset);

            $days[] = [
                'date' => $date->toDateString(),
                'count' => count($this->availability->slotsFor($barber, $service, $date)),
            ];
        }

        return response()->json(['days' => $days]);
    }

    public function slots(Request $request, Company $company)
    {
        $this->bootTenant($company);

        $data = $request->validate($this->rules($company));

        $service = Service::query()->active()->findOrFail($data['service_id']);
        $barber = User::query()->active()->barbers()->findOrFail($data['barber_id']);
        $date = Carbon::parse($data['date']);

        return response()->json([
            'slots' => $this->availability->slotsFor($barber, $service, $date),
            'duration' => (int) ($service->duration_minutes ?? 30),
        ]);
    }

    public function store(Request $request, Company $company)
    {
        $this->bootTenant($company);

        $data = $request->validate($this->rules($company) + [
            'start_time' => ['required', 'date_format:H:i'],
            'client_name' => ['required', 'string', 'max:120'],
            'client_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service = Service::query()->active()->findOrFail($data['service_id']);
        $barber = User::query()->active()->barbers()->findOrFail($data['barber_id']);
        $date = Carbon::parse($data['date']);

        if (! $this->availability->isSlotAvailable($barber, $service, $date, $data['start_time'])) {
            return back()->withInput()->with('error', 'Ese horario ya no está disponible. Elige otro.');
        }

        $duration = max(5, (int) ($service->duration_minutes ?? 30));
        $start = Carbon::parse($data['date'].' '.$data['start_time']);
        $end = $start->copy()->addMinutes($duration);

        $customer = null;

        $appointment = DB::transaction(function () use ($data, $barber, $service, $start, $end, $duration, $company, &$customer) {
            $customer = $this->reminders->findOrCreate($company, $data['client_name'], $data['client_phone']);

            return Appointment::create([
                'customer_id' => $customer->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'duration_minutes' => $duration,
                'client_name' => $data['client_name'],
                'client_phone' => $data['client_phone'],
                'date' => $start->toDateString(),
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'status' => Appointment::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        session([
            'client_customer_id' => $customer->id,
            'client_company_id' => $company->id,
        ]);

        return redirect()->route('booking.show', $company->slug)
            ->with('status', 'Tu cita fue registrada. La barbería confirmará la disponibilidad.')
            ->with('booking_success', $appointment->id);
    }

    protected function rules(Company $company): array
    {
        return [
            'service_id' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('company_id', $company->id)
                    ->where('active', true),
            ],
            'barber_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', $company->id)
                    ->where('role', User::ROLE_BARBER)
                    ->where('active', true)
                    ->whereNull('deleted_at'),
            ],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(90)->toDateString()],
        ];
    }

    protected function bootTenant(Company $company): void
    {
        abort_unless($company->active, 404);

        app(TenantManager::class)->set($company);
        app(SettingService::class)->resetMemory();
        apply_timezone($company);
    }

    protected function clientCustomer(Company $company): ?Customer
    {
        if ((int) session('client_company_id') !== $company->id || ! session('client_customer_id')) {
            return null;
        }

        return Customer::query()->find(session('client_customer_id'));
    }

    protected function initials(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
