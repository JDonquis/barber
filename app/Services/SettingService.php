<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'settings.all';

    protected ?array $values = null;

    /**
     * Return every setting as an associative array.
     */
    public function all(): array
    {
        if (app(TenantManager::class)->id() === null) {
            return [];
        }

        if ($this->values === null) {
            $this->values = Cache::rememberForever($this->cacheKey(), function () {
                return Setting::query()->pluck('value', 'key')->all();
            });
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return $values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['company_id' => app(TenantManager::class)->id(), 'value' => $value],
        );

        $this->forget();
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['company_id' => app(TenantManager::class)->id(), 'value' => $value],
            );
        }

        $this->forget();
    }

    public function forget(): void
    {
        $this->resetMemory();

        Cache::forget($this->cacheKey());
    }

    /**
     * Drop the in-memory values only, keeping the persistent cache intact.
     */
    public function resetMemory(): void
    {
        $this->values = null;
    }

    protected function cacheKey(): string
    {
        return self::CACHE_KEY.'.'.(app(TenantManager::class)->id() ?? 'central');
    }
}
