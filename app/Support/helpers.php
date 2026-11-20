<?php

use App\Models\Company;
use App\Services\ExchangeRateService;
use App\Services\PlatformSettingService;
use App\Services\SettingService;
use App\Services\TenantManager;

if (! function_exists('current_company')) {
    function current_company(): ?Company
    {
        return app(TenantManager::class)->get();
    }
}

if (! function_exists('company_id')) {
    function company_id(): ?int
    {
        return app(TenantManager::class)->id();
    }
}

if (! function_exists('company_logo_url')) {
    function company_logo_url(): string
    {
        return current_company()?->logo_url ?? asset('logo.jpeg');
    }
}

if (! function_exists('platform_setting')) {
    function platform_setting(string $key, mixed $default = null): mixed
    {
        return app(PlatformSettingService::class)->get($key, $default);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}

if (! function_exists('single_barber_mode')) {
    /**
     * Whether the shop is operated by a single person (the administrator acts as the barber).
     */
    function single_barber_mode(): bool
    {
        return (string) setting('single_barber_mode', '0') === '1';
    }
}

if (! function_exists('exchange_rate')) {
    function exchange_rate(): float
    {
        return app(ExchangeRateService::class)->current();
    }
}

if (! function_exists('usd')) {
    /**
     * Format an amount in US dollars. Example: $ 1.234,56
     */
    function usd(float|int|string|null $amount, bool $symbol = true): string
    {
        $formatted = number_format((float) $amount, 2, ',', '.');

        return $symbol ? '$ '.$formatted : $formatted;
    }
}

if (! function_exists('usd_conversion_enabled')) {
    function usd_conversion_enabled(): bool
    {
        return (string) setting('usd_conversion_enabled', '1') === '1';
    }
}

if (! function_exists('usd_conversion_reference_rate')) {
    function usd_conversion_reference_rate(): float
    {
        return app(ExchangeRateService::class)->referenceRate();
    }
}

if (! function_exists('money')) {
    /**
     * Format a base amount in the shop's active display currency.
     */
    function money(float|int|string|null $amountUsd, float|int|string|null $amountLocal = null, bool $symbol = true): string
    {
        if (usd_conversion_enabled()) {
            return usd($amountUsd, $symbol);
        }

        $local = $amountLocal ?? ((float) $amountUsd * usd_conversion_reference_rate());

        return local_money($local, $symbol);
    }
}

if (! function_exists('price_money')) {
    /**
     * Format a catalog price, which is stored in the active currency.
     */
    function price_money(float|int|string|null $amount, bool $symbol = true): string
    {
        return usd_conversion_enabled()
            ? usd($amount, $symbol)
            : local_money($amount, $symbol);
    }
}

if (! function_exists('company_timezone')) {
    function company_timezone(?Company $company = null): string
    {
        $company ??= current_company();
        $timezone = $company?->timezone ?: config('app.timezone', 'America/Caracas');

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone', 'America/Caracas');
    }
}

if (! function_exists('apply_timezone')) {
    function apply_timezone(?Company $company = null): string
    {
        $timezone = company_timezone($company);

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return $timezone;
    }
}

if (! function_exists('local_currency')) {
    /**
     * Local currency configured by the current barbershop: VES or COP.
     */
    function local_currency(): string
    {
        return strtoupper((string) setting('local_currency', 'VES')) === 'COP' ? 'COP' : 'VES';
    }
}

if (! function_exists('local_currency_symbol')) {
    function local_currency_symbol(): string
    {
        return local_currency() === 'COP' ? 'COP $' : 'Bs.';
    }
}

if (! function_exists('local_currency_name')) {
    function local_currency_name(): string
    {
        return local_currency() === 'COP' ? 'Pesos colombianos' : 'Bolívares';
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format an amount in an explicit currency. Example: Bs. 1.234,56
     */
    function format_currency(string $currency, float|int|string|null $amount, bool $symbol = true): string
    {
        $prefix = match (strtoupper($currency)) {
            'USD' => '$',
            'VES' => 'Bs.',
            'COP' => 'COP $',
            default => strtoupper($currency),
        };

        $formatted = number_format((float) $amount, 2, ',', '.');

        return $symbol ? $prefix.' '.$formatted : $formatted;
    }
}

if (! function_exists('local_money')) {
    /**
     * Format an amount in the barbershop's local currency.
     */
    function local_money(float|int|string|null $amount, bool $symbol = true): string
    {
        return format_currency(local_currency(), $amount, $symbol);
    }
}

if (! function_exists('ves')) {
    /**
     * Format an amount in the barbershop's local currency.
     */
    function ves(float|int|string|null $amount, bool $symbol = true): string
    {
        return local_money($amount, $symbol);
    }
}

if (! function_exists('to_local')) {
    /**
     * Convert a base (USD) amount to the barbershop's local currency.
     */
    function to_local(float|int|string|null $amountUsd, ?float $rate = null): float
    {
        $rate ??= exchange_rate();

        return round((float) $amountUsd * $rate, 2);
    }
}

if (! function_exists('to_ves')) {
    /**
     * Convert a base (USD) amount to the barbershop's local currency.
     */
    function to_ves(float|int|string|null $amountUsd, ?float $rate = null): float
    {
        return to_local($amountUsd, $rate);
    }
}

if (! function_exists('payment_methods')) {
    /**
     * Available payment methods configured in settings.
     *
     * @return array<int, string>
     */
    function payment_methods(): array
    {
        $default = local_currency() === 'COP'
            ? 'Efectivo COP, Efectivo USD, Nequi, Daviplata, Transferencia, PSE, Tarjeta'
            : 'Efectivo Bs, Efectivo USD, Pago móvil, Punto de venta, Transferencia, Zelle';

        $raw = setting('payment_methods', $default);
        $methods = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));

        if (! usd_conversion_enabled()) {
            $methods = array_values(array_filter($methods, function (string $method) {
                $value = mb_strtolower($method);

                return ! str_contains($value, 'usd')
                    && ! str_contains($value, 'dólar')
                    && ! str_contains($value, 'dolar')
                    && ! str_contains($value, 'zelle');
            }));
        }

        return $methods;
    }
}
