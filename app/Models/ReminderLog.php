<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use BelongsToCompany;

    public const STATUS_SENT = 'enviado';

    public const STATUS_SKIPPED = 'omitido';

    public const STATUS_FAILED = 'fallido';

    protected $fillable = [
        'company_id',
        'customer_id',
        'subscription_id',
        'channel',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ReminderSubscription::class, 'subscription_id');
    }
}
