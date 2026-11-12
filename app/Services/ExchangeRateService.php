<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    protected const CACHE_KEY = 'exchange_rate.current';

    protected const SOURCES = [
        'oficial' => 'https://ve.dolarapi.com/v1/dolares/oficial',
        'paralelo' => 'https://ve.dolarapi.com/v1/dolares/paralelo',
    ];

    protected const COP_SOURCE = 'https://open.er-api.com/v6/latest/USD';

    public function __construct(protected SettingService $settings) {}

    public function currency(): string
    {
        return local_currency();
    }

    /**
     * Current USD to local currency rate.
     */
    public function current(): float
    {
        $manual = $this->settings->get('exchange_rate_manual');

        if ($manual !== null && (float) $manual > 0) {
            return (float) $manual;
        }

        $rate = Cache::remember($this->cacheKey(), now()->addHour(), function () {
            return $this->fetch();
        });

        return (float) $rate;
    }

    /**
     * Force a fresh value from the API and cache it.
     */
    public function refresh(): ?float
    {
        Cache::forget($this->cacheKey());

        $rate = $this->fetch();

        if ($rate !== null) {
            Cache::put($this->cacheKey(), $rate, now()->addHour());
        }

        return $rate;
    }

    public function usingManual(): bool
    {
        $manual = $this->settings->get('exchange_rate_manual');

        return $manual !== null && (float) $manual > 0;
    }

    /**
     * Last stored rate for the active local currency, used as fallback.
     */
    public function lastKnown(): float
    {
        $sources = $this->currency() === 'COP' ? ['cop'] : ['oficial', 'paralelo'];

        $last = ExchangeRate::query()
            ->whereIn('source', $sources)
            ->latest('fetched_at')
            ->first();

        if ($last) {
            return (float) $last->rate;
        }

        return (float) $this->settings->get('exchange_rate_fallback', 0);
    }

    public function source(): string
    {
        if ($this->currency() === 'COP') {
            return 'cop';
        }

        $source = $this->settings->get('dolar_api_source') ?: config('app.dolar_api_source', 'oficial');

        return array_key_exists($source, self::SOURCES) ? $source : 'oficial';
    }

    public function sourceLabel(): string
    {
        if ($this->currency() === 'COP') {
            return 'ExchangeRate-API · USD/COP';
        }

        return ucfirst($this->source()).' · Banco Central de Venezuela (API)';
    }

    public function forgetAll(): void
    {
        $tenant = app(TenantManager::class)->id() ?? 'central';

        foreach (['VES', 'COP'] as $currency) {
            Cache::forget(self::CACHE_KEY.'.'.$tenant.'.'.$currency);
        }
    }

    protected function cacheKey(): string
    {
        return self::CACHE_KEY.'.'.(app(TenantManager::class)->id() ?? 'central').'.'.$this->currency();
    }

    protected function fetch(): ?float
    {
        $currency = $this->currency();
        $source = $this->source();

        try {
            if ($currency === 'COP') {
                $response = Http::timeout(10)->retry(2, 500)->get(self::COP_SOURCE);

                if (! $response->successful()) {
                    return $this->lastKnown() ?: null;
                }

                $rate = (float) ($response->json('rates.COP') ?? 0);
            } else {
                $url = self::SOURCES[$source] ?? self::SOURCES['oficial'];
                $source = array_key_exists($source, self::SOURCES) ? $source : 'oficial';

                $response = Http::timeout(10)->retry(2, 500)->get($url);

                if (! $response->successful()) {
                    return $this->lastKnown() ?: null;
                }

                $rate = (float) ($response->json('promedio') ?? 0);
            }

            if ($rate <= 0) {
                return $this->lastKnown() ?: null;
            }

            ExchangeRate::create([
                'source' => $source,
                'rate' => $rate,
                'fetched_at' => now(),
            ]);

            return $rate;
        } catch (\Throwable $e) {
            Log::warning('No se pudo obtener la tasa de cambio: '.$e->getMessage());

            return $this->lastKnown() ?: null;
        }
    }
}
