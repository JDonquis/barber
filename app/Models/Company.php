<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'email',
        'phone',
        'address',
        'active',
        'timezone',
        'monthly_fee_usd',
        'is_exempt',
        'subscription_started_at',
        'next_payment_due_at',
        'last_payment_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'monthly_fee_usd' => 'decimal:2',
        'is_exempt' => 'boolean',
        'subscription_started_at' => 'date',
        'next_payment_due_at' => 'date',
        'last_payment_at' => 'date',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function closings(): HasMany
    {
        return $this->hasMany(Closing::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CompanyPayment::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->is_exempt
            && $this->next_payment_due_at !== null
            && $this->next_payment_due_at->isPast();
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logo_path) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset('logo.jpeg');
    }
}
