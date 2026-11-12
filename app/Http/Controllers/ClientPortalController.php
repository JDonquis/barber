<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PushSubscription;
use App\Services\CustomerReminderService;
use App\Services\SettingService;
use App\Services\TenantManager;
use Illuminate\Http\Request;

class ClientPortalController extends Controller
{
    public function __construct(protected CustomerReminderService $reminders) {}

    public function access(Request $request, Company $company, string $token)
    {
        $this->bootTenant($company);

        $customer = Customer::query()->where('access_token', $token)->firstOrFail();

        $request->session()->regenerate();
        $request->session()->put([
            'client_customer_id' => $customer->id,
            'client_company_id' => $company->id,
        ]);

        return redirect()->route('booking.account', $company->slug);
    }

    public function account(Company $company)
    {
        $this->bootTenant($company);

        $customer = $this->customer($company);

        if (! $customer) {
            return redirect()->route('booking.show', $company->slug)
                ->with('error', 'Necesitas abrir el enlace de acceso que te dio la barbería.');
        }

        $customer->load(['subscription', 'pushSubscriptions']);

        $nextAppointment = $customer->appointments()
            ->with('barber')
            ->whereDate('date', '>=', now()->toDateString())
            ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        return view('booking.account', [
            'company' => $company,
            'customer' => $customer,
            'subscription' => $customer->subscription,
            'pushSubscribed' => $customer->pushSubscriptions->isNotEmpty(),
            'pushPublicKey' => config('webpush.vapid.public_key'),
            'nextAppointment' => $nextAppointment,
        ]);
    }

    public function updateReminders(Request $request, Company $company)
    {
        $this->bootTenant($company);

        $customer = $this->customerOrFail($company);

        $data = $request->validate([
            'interval_days' => ['required', 'integer', 'between:1,365'],
        ]);

        $this->reminders->updateSubscription($customer, (int) $data['interval_days']);

        return back()->with('status', 'Recordatorios activados correctamente.');
    }

    public function disableReminders(Company $company)
    {
        $this->bootTenant($company);

        $customer = $this->customerOrFail($company);

        $this->reminders->disableSubscription($customer);

        return back()->with('status', 'Recordatorios desactivados.');
    }

    public function subscribePush(Request $request, Company $company)
    {
        $this->bootTenant($company);

        $customer = $this->customerOrFail($company);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        PushSubscription::withoutGlobalScope('company')->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
        );

        if (! $customer->consent_at) {
            $customer->update(['consent_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function unsubscribePush(Request $request, Company $company)
    {
        $this->bootTenant($company);

        $customer = $this->customerOrFail($company);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::withoutGlobalScope('company')
            ->where('customer_id', $customer->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    protected function customer(Company $company): ?Customer
    {
        if ((int) session('client_company_id') !== $company->id || ! session('client_customer_id')) {
            return null;
        }

        return Customer::query()->find(session('client_customer_id'));
    }

    protected function customerOrFail(Company $company): Customer
    {
        $customer = $this->customer($company);

        abort_unless($customer, 403, 'Acceso de cliente no válido.');

        return $customer;
    }

    protected function bootTenant(Company $company): void
    {
        abort_unless($company->active, 404);

        app(TenantManager::class)->set($company);
        app(SettingService::class)->resetMemory();
        apply_timezone($company);
    }
}
