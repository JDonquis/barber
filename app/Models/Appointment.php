<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_CONFIRMED = 'confirmada';

    public const STATUS_CANCELLED = 'cancelada';

    public const STATUS_COMPLETED = 'completada';

    protected $fillable = [
        'company_id',
        'customer_id',
        'barber_id',
        'service_id',
        'service_name',
        'duration_minutes',
        'client_name',
        'client_phone',
        'client_email',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'duration_minutes' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barber_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
