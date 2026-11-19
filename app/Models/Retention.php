<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retention extends Model
{
    use BelongsToCompany, HasFactory;

    public const FREQUENCY_DAILY = 'diario';

    public const FREQUENCY_WEEKLY = 'semanal';

    public const FREQUENCY_BIWEEKLY = 'quincenal';

    public const FREQUENCY_MONTHLY = 'mensual';

    /**
     * @var array<string, string>
     */
    public const FREQUENCIES = [
        self::FREQUENCY_DAILY => 'Diario',
        self::FREQUENCY_WEEKLY => 'Semanal',
        self::FREQUENCY_BIWEEKLY => 'Quincenal',
        self::FREQUENCY_MONTHLY => 'Mensual',
    ];

    protected $fillable = [
        'company_id',
        'barber_id',
        'name',
        'amount_usd',
        'frequency',
        'active',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function barber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barber_id')->withTrashed();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForBarber($query, int $barberId)
    {
        return $query->where(function ($query) use ($barberId) {
            $query->whereNull('barber_id')->orWhere('barber_id', $barberId);
        });
    }

    public function getFrequencyLabelAttribute(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? ucfirst($this->frequency);
    }

    public function frequencyDays(): int
    {
        return match ($this->frequency) {
            self::FREQUENCY_DAILY => 1,
            self::FREQUENCY_WEEKLY => 7,
            self::FREQUENCY_BIWEEKLY => 15,
            default => 30,
        };
    }

    public function isGlobal(): bool
    {
        return $this->barber_id === null;
    }
}
