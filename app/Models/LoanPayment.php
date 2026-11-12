<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'loan_id',
        'created_by',
        'amount_usd',
        'amount_ves',
        'exchange_rate',
        'payment_date',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_ves' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'payment_date' => 'date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
