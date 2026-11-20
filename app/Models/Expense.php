<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'concept',
        'amount_usd',
        'amount_local',
        'expense_date',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_local' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereDate('expense_date', '>=', $start)
            ->whereDate('expense_date', '<=', $end);
    }

    public function localAmount(): float
    {
        if ($this->amount_local !== null) {
            return (float) $this->amount_local;
        }

        return round((float) $this->amount_usd * app(ExchangeRateService::class)->referenceRate(), 2);
    }
}
