<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CompanyPayment extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_APPROVED = 'aprobado';

    public const STATUS_REJECTED = 'rechazado';

    protected $fillable = [
        'company_id',
        'user_id',
        'period',
        'amount_usd',
        'exchange_rate',
        'amount_ves',
        'payment_method',
        'reference',
        'paid_at',
        'screenshot_path',
        'status',
        'notes',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'amount_ves' => 'decimal:2',
        'paid_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function getScreenshotUrlAttribute(): ?string
    {
        return $this->screenshot_path
            ? Storage::disk('public')->url($this->screenshot_path)
            : null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
