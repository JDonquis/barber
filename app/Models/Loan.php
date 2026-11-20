<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_PARTIAL = 'parcial';

    public const STATUS_PAID = 'pagado';

    protected $fillable = [
        'company_id',
        'barber_id',
        'created_by',
        'amount_usd',
        'amount_ves',
        'exchange_rate',
        'paid_usd',
        'loan_date',
        'notes',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_ves' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'paid_usd' => 'decimal:2',
        'loan_date' => 'date',
    ];

    public function barber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barber_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function getBalanceUsdAttribute(): float
    {
        return max(0, round((float) $this->amount_usd - (float) $this->paid_usd, 2));
    }

    public function getProgressAttribute(): int
    {
        $amount = (float) $this->amount_usd;

        if ($amount <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(((float) $this->paid_usd / $amount) * 100)));
    }

    public function getStatusAttribute(): string
    {
        $paid = (float) $this->paid_usd;

        if ($paid <= 0) {
            return self::STATUS_PENDING;
        }

        if ($paid >= (float) $this->amount_usd) {
            return self::STATUS_PAID;
        }

        return self::STATUS_PARTIAL;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPartial(): bool
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function scopeWithBalance($query)
    {
        return $query->whereColumn('paid_usd', '<', 'amount_usd');
    }

    public function scopePending($query)
    {
        return $query->where('paid_usd', '<=', 0);
    }

    public function scopePartial($query)
    {
        return $query->where('paid_usd', '>', 0)->whereColumn('paid_usd', '<', 'amount_usd');
    }

    public function scopePaid($query)
    {
        return $query->whereColumn('paid_usd', '>=', 'amount_usd');
    }

    public function localRate(): float
    {
        $rate = (float) $this->exchange_rate;

        return $rate > 0 ? $rate : 1.0;
    }

    public function localAmount(): float
    {
        return (float) $this->amount_ves > 0
            ? (float) $this->amount_ves
            : round((float) $this->amount_usd * $this->localRate(), 2);
    }

    public function localPaid(): float
    {
        $paid = $this->payments->sum(fn (LoanPayment $payment) => $payment->localAmount());

        return round((float) $paid, 2);
    }

    public function localBalance(): float
    {
        return max(0, round($this->localAmount() - $this->localPaid(), 2));
    }

    public function localProgress(): int
    {
        $amount = $this->localAmount();

        if ($amount <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($this->localPaid() / $amount) * 100)));
    }
}
