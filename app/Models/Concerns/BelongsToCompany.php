<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public function initializeBelongsToCompany(): void
    {
        $this->mergeFillable(['company_id']);
    }

    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = app(TenantManager::class)->id();

            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });

        static::creating(function ($model) {
            $companyId = app(TenantManager::class)->id();

            if ($companyId !== null && ! array_key_exists('company_id', $model->getAttributes())) {
                $model->setAttribute('company_id', $companyId);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
