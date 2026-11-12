<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Customer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'access_token',
        'last_visit_at',
        'consent_at',
    ];

    protected $casts = [
        'last_visit_at' => 'date',
        'consent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (! $customer->access_token) {
                $customer->access_token = Str::random(64);
            }
        });
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(ReminderSubscription::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    public function hasPushDevices(): bool
    {
        return $this->pushSubscriptions()->exists();
    }
}
