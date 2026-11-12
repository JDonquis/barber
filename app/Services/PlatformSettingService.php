<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettingService
{
    protected const CACHE_KEY = 'platform_settings.all';

    protected ?array $values = null;

    public function all(): array
    {
        if ($this->values === null) {
            $this->values = Cache::rememberForever(self::CACHE_KEY, function () {
                return PlatformSetting::query()->pluck('value', 'key')->all();
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
        PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->forget();
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->forget();
    }

    public function forget(): void
    {
        $this->values = null;

        Cache::forget(self::CACHE_KEY);
    }
}
