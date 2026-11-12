<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ReminderLog;
use App\Models\ReminderSubscription;
use Carbon\Carbon;

class CustomerReminderService
{
    public function __construct(protected WebPushService $push) {}

    public function findOrCreate(Company $company, string $name, string $phone): Customer
    {
        $phone = $this->normalizePhone($phone);

        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('phone', $phone)
            ->first();

        if ($customer) {
            if ($customer->name !== $name) {
                $customer->update(['name' => $name]);
            }

            return $customer;
        }

        return Customer::withoutGlobalScope('company')->create([
            'company_id' => $company->id,
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    /**
     * Called when the barbershop marks an appointment as completed.
     */
    public function handleCompleted(Appointment $appointment): void
    {
        $company = Company::query()->find($appointment->company_id);

        if (! $company) {
            return;
        }

        $customer = $appointment->customer_id
            ? Customer::withoutGlobalScope('company')->find($appointment->customer_id)
            : null;

        if (! $customer) {
            $customer = $this->findOrCreate($company, $appointment->client_name, $appointment->client_phone);
            $appointment->update(['customer_id' => $customer->id]);
        }

        $customer->update([
            'last_visit_at' => $appointment->date?->toDateString() ?? now()->toDateString(),
            'consent_at' => $customer->consent_at ?? now(),
        ]);

        $subscription = ReminderSubscription::withoutGlobalScope('company')
            ->where('customer_id', $customer->id)
            ->first();

        if ($subscription) {
            if ($subscription->active) {
                $subscription->update([
                    'next_reminder_at' => $this->nextFrom($appointment->completed_at ?? now(), $subscription->interval_days),
                ]);
            }

            return;
        }

        if ($customer->pushSubscriptions()->exists()) {
            $this->push->send($customer, [
                'title' => 'Activa tus recordatorios',
                'body' => "¿Quieres que te recordemos tu próximo corte en {$company->name}?",
                'icon' => route('booking.icon', ['company' => $company->slug, 'size' => 192]),
                'url' => route('booking.access', ['company' => $company->slug, 'token' => $customer->access_token]),
            ]);
        }
    }

    public function updateSubscription(Customer $customer, int $intervalDays): ReminderSubscription
    {
        $lastAppointment = Appointment::withoutGlobalScope('company')
            ->where('customer_id', $customer->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        $subscription = ReminderSubscription::withoutGlobalScope('company')
            ->firstOrNew(['customer_id' => $customer->id]);

        $subscription->company_id = $customer->company_id;
        $subscription->interval_days = $intervalDays;
        $subscription->active = true;
        $subscription->preferred_barber_id = $lastAppointment?->barber_id;
        $subscription->preferred_service_id = $lastAppointment?->service_id;

        if ($customer->last_visit_at) {
            $next = $this->nextFrom(Carbon::parse($customer->last_visit_at), $intervalDays);

            if ($next->isPast()) {
                $next = now();
            }

            $subscription->next_reminder_at = $next;
        } else {
            $subscription->next_reminder_at = null;
        }

        $customer->update(['consent_at' => $customer->consent_at ?? now()]);

        $subscription->save();

        return $subscription;
    }

    public function disableSubscription(Customer $customer): void
    {
        ReminderSubscription::withoutGlobalScope('company')
            ->where('customer_id', $customer->id)
            ->update(['active' => false, 'next_reminder_at' => null]);
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendDueReminders(int $limit = 200): array
    {
        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        $remaining = max(0, $limit);
        $originalTimezone = config('app.timezone', 'America/Caracas');

        try {
            $companies = Company::query()->orderBy('id')->get();

            foreach ($companies as $company) {
                if ($remaining <= 0) {
                    break;
                }

                apply_timezone($company);

                $subscriptions = ReminderSubscription::withoutGlobalScope('company')
                    ->with('customer')
                    ->where('company_id', $company->id)
                    ->where('active', true)
                    ->whereNotNull('next_reminder_at')
                    ->where('next_reminder_at', '<=', now())
                    ->limit($remaining)
                    ->get();

                foreach ($subscriptions as $subscription) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $customer = $subscription->customer;

                    if (! $customer) {
                        $subscription->update(['active' => false, 'next_reminder_at' => null]);
                        $stats['skipped']++;
                        $remaining--;

                        continue;
                    }

                    $upcoming = Appointment::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where('customer_id', $customer->id)
                        ->whereDate('date', '>=', now()->toDateString())
                        ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                        ->orderBy('date')
                        ->orderBy('start_time')
                        ->first();

                    if ($upcoming) {
                        $subscription->update([
                            'next_reminder_at' => $this->nextFrom(Carbon::parse($upcoming->date), $subscription->interval_days),
                        ]);

                        $this->log($subscription, $customer, ReminderLog::STATUS_SKIPPED, 'El cliente tiene una cita futura.');
                        $stats['skipped']++;
                        $remaining--;

                        continue;
                    }

                    $result = $this->push->send($customer, [
                        'title' => 'Hora de tu corte',
                        'body' => "Han pasado {$subscription->interval_days} días desde tu última visita a {$company->name}. Reserva tu próximo corte.",
                        'icon' => route('booking.icon', ['company' => $company->slug, 'size' => 192]),
                        'url' => $this->bookingUrl($company, $subscription),
                    ]);

                    if ($result['sent'] > 0) {
                        $subscription->update([
                            'last_reminder_at' => now(),
                            'next_reminder_at' => $this->nextFrom(now(), $subscription->interval_days),
                        ]);

                        $this->log($subscription, $customer, ReminderLog::STATUS_SENT);
                        $stats['sent']++;
                        $remaining--;

                        continue;
                    }

                    $subscription->update([
                        'next_reminder_at' => now()->addDay(),
                    ]);

                    $this->log(
                        $subscription,
                        $customer,
                        $result['errors'] ? ReminderLog::STATUS_FAILED : ReminderLog::STATUS_SKIPPED,
                        $result['errors'] ? implode(' | ', $result['errors']) : 'El cliente no tiene dispositivos con notificaciones activas.',
                    );

                    $result['errors'] ? $stats['failed']++ : $stats['skipped']++;
                    $remaining--;
                }
            }
        } finally {
            config(['app.timezone' => $originalTimezone]);
            date_default_timezone_set($originalTimezone);
        }

        return $stats;
    }

    protected function nextFrom(Carbon $base, int $intervalDays): Carbon
    {
        return $base->copy()->addDays($intervalDays)->setTime(10, 0);
    }

    protected function bookingUrl(Company $company, ReminderSubscription $subscription): string
    {
        $params = ['company' => $company->slug];

        if ($subscription->preferred_barber_id) {
            $params['barber_id'] = $subscription->preferred_barber_id;
        }

        if ($subscription->preferred_service_id) {
            $params['service_id'] = $subscription->preferred_service_id;
        }

        return route('booking.show', $params);
    }

    protected function log(ReminderSubscription $subscription, Customer $customer, string $status, ?string $error = null): void
    {
        ReminderLog::withoutGlobalScope('company')->create([
            'company_id' => $subscription->company_id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'channel' => 'webpush',
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }
}
