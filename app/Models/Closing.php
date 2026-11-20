<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Closing extends Model
{
    use BelongsToCompany, HasFactory;

    public const PERIOD_DAILY = 'diario';

    public const PERIOD_WEEKLY = 'semanal';

    public const PERIOD_MONTHLY = 'mensual';

    public const STATUS_OPEN = 'abierto';

    public const STATUS_CLOSED = 'cerrado';

    protected $fillable = [
        'period_type',
        'period_start',
        'period_end',
        'total_services_usd',
        'total_products_usd',
        'total_usd',
        'total_ves',
        'total_ves_reference',
        'barber_commission_usd',
        'barber_tips_usd',
        'barber_retentions_usd',
        'total_expenses_usd',
        'shop_amount_usd',
        'ticket_count',
        'exchange_rate',
        'average_rate',
        'details',
        'status',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_services_usd' => 'decimal:2',
        'total_products_usd' => 'decimal:2',
        'total_usd' => 'decimal:2',
        'total_ves' => 'decimal:2',
        'total_ves_reference' => 'decimal:2',
        'barber_commission_usd' => 'decimal:2',
        'barber_tips_usd' => 'decimal:2',
        'barber_retentions_usd' => 'decimal:2',
        'total_expenses_usd' => 'decimal:2',
        'shop_amount_usd' => 'decimal:2',
        'ticket_count' => 'integer',
        'exchange_rate' => 'decimal:4',
        'average_rate' => 'decimal:4',
        'details' => 'array',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by')->withTrashed();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Gross earnings for the barber before retentions.
     */
    public function barberGrossEarningsUsd(): float
    {
        return round((float) $this->barber_commission_usd + (float) $this->barber_tips_usd, 2);
    }

    /**
     * Total amount payable to the barber: commission plus tips minus retentions.
     */
    public function barberEarningsUsd(): float
    {
        return round($this->barberGrossEarningsUsd() - (float) $this->barber_retentions_usd, 2);
    }

    public function localRate(): float
    {
        $rate = (float) $this->average_rate;

        if ($rate <= 0) {
            $rate = (float) $this->exchange_rate;
        }

        return $rate > 0 ? $rate : 1.0;
    }

    public function localTotal(): float
    {
        return (float) $this->total_ves > 0
            ? (float) $this->total_ves
            : round((float) $this->total_usd * $this->localRate(), 2);
    }

    public function localServices(): float
    {
        return round((float) $this->total_services_usd * $this->localRate(), 2);
    }

    public function localProducts(): float
    {
        return round((float) $this->total_products_usd * $this->localRate(), 2);
    }

    public function localCommission(): float
    {
        return round((float) $this->barber_commission_usd * $this->localRate(), 2);
    }

    public function localTips(): float
    {
        return round((float) $this->barber_tips_usd * $this->localRate(), 2);
    }

    public function localRetentions(): float
    {
        return round((float) $this->barber_retentions_usd * $this->localRate(), 2);
    }

    public function localExpenses(): float
    {
        return round((float) $this->total_expenses_usd * $this->localRate(), 2);
    }

    public function localShopAmount(): float
    {
        return round((float) $this->shop_amount_usd * $this->localRate(), 2);
    }

    public function localBarberGrossEarnings(): float
    {
        return round($this->localCommission() + $this->localTips(), 2);
    }

    public function localBarberEarnings(): float
    {
        return round($this->localBarberGrossEarnings() - $this->localRetentions(), 2);
    }
}
