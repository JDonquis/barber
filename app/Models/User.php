<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToCompany, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_BARBER = 'barbero';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
        'commission_rate',
        'active',
        'can_register_sales',
        'phone',
        'google_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'commission_rate' => 'decimal:2',
        'active' => 'boolean',
        'can_register_sales' => 'boolean',
        'is_super_admin' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isForceDeleting() || $user->trashed()) {
                return;
            }

            if ($user->email !== null && ! str_starts_with($user->email, 'deleted+')) {
                $user->deleted_email = $user->email;
                $user->email = 'deleted+'.$user->getKey().'+'.mb_substr($user->email, 0, 200);
                $user->saveQuietly();
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isBarber(): bool
    {
        return $this->role === self::ROLE_BARBER;
    }

    /**
     * Whether this user is allowed to register service sales.
     */
    public function canRegisterSales(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->can_register_sales !== false;
    }

    /**
     * Commission rate for this user: the personal override when set,
     * otherwise the given base rate (typically the global setting).
     */
    public function effectiveCommissionRate(float $base = 0): float
    {
        return $this->commission_rate !== null ? (float) $this->commission_rate : $base;
    }

    /**
     * Sales registered by this user.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'user_id');
    }

    /**
     * Sales where this user acted as the barber.
     */
    public function barberSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'barber_id');
    }

    /**
     * Loans advanced to this barber.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'barber_id');
    }

    /**
     * Loans registered by this user.
     */
    public function createdLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'created_by');
    }

    public function passwordResetRequests(): HasMany
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeBarbers($query)
    {
        return $query->where('role', self::ROLE_BARBER);
    }
}
