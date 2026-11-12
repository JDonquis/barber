<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderSubscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'customer_id',
        'interval_days',
        'active',
        'next_reminder_at',
        'last_reminder_at',
        'preferred_barber_id',
        'preferred_service_id',
    ];

    protected $casts = [
        'interval_days' => 'integer',
        'active' => 'boolean',
        'next_reminder_at' => 'datetime',
        'last_reminder_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function preferredBarber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_barber_id')->withTrashed();
    }

    public function preferredService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'preferred_service_id');
    }
}
