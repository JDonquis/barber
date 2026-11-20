<?php

namespace App\Http\Controllers;

use App\Models\Closing;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Product;
use App\Models\Retention;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\QrLoginService;
use App\Services\SettingService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settings, protected QrLoginService $qrLogin) {}

    public function edit(Request $request)
    {
        $baseCommission = (float) $this->settings->get('commission_rate', 0);
        $barbers = User::query()->barbers()->orderBy('name')->get();
        $qrLogin = $this->qrLogin->tokenFor($request->user());

        return view('settings.edit', [
            'values' => $this->settings->all(),
            'baseCommission' => $baseCommission,
            'qrLoginUrl' => route('qr-login.consume', $qrLogin['token']),
            'qrLoginExpiresAt' => $qrLogin['expires_at']->getTimestampMs(),
            'barbersData' => $barbers->map(fn (User $barber) => [
                'id' => $barber->id,
                'name' => $barber->name,
                'active' => (bool) $barber->active,
                'rate' => $barber->effectiveCommissionRate($baseCommission),
                'custom' => $barber->commission_rate !== null && (float) $barber->commission_rate !== $baseCommission,
            ])->values(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:120'],
            'shop_rif' => ['nullable', 'string', 'max:30'],
            'shop_phone' => ['nullable', 'string', 'max:30'],
            'shop_address' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_methods' => ['nullable', 'string', 'max:255'],
            'local_currency' => ['nullable', 'in:VES,COP'],
            'timezone' => ['nullable', 'in:America/Caracas,America/Bogota'],
            'dolar_api_source' => ['nullable', 'in:oficial,paralelo'],
            'usd_conversion_enabled' => ['nullable', 'boolean'],
            'exchange_rate_manual' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate_fallback' => ['nullable', 'numeric', 'min:0'],
            'barber_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', company_id())],
            'barber_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'barber_use_base' => ['nullable', 'boolean'],
            'single_barber_mode' => ['nullable', 'boolean'],
            'retentions_enabled' => ['nullable', 'boolean'],
        ]);

        $previousCurrency = $this->settings->get('local_currency', 'VES');
        $localCurrency = $data['local_currency'] ?? $previousCurrency;
        $company = app(TenantManager::class)->get();
        $timezone = $data['timezone'] ?? ($company?->timezone ?: ($localCurrency === 'COP' ? 'America/Bogota' : 'America/Caracas'));
        $conversionWasEnabled = (string) $this->settings->get('usd_conversion_enabled', '1') === '1';
        $conversionEnabled = $request->has('usd_conversion_enabled')
            ? $request->boolean('usd_conversion_enabled')
            : $conversionWasEnabled;
        $conversionChanged = $conversionWasEnabled !== $conversionEnabled;
        $conversionRate = 0.0;

        if ($conversionChanged) {
            $submittedManual = (float) ($data['exchange_rate_manual'] ?? 0);

            if ($submittedManual > 0) {
                $conversionRate = $submittedManual;
            } else {
                $conversionRate = $conversionEnabled
                    ? (float) app(ExchangeRateService::class)->liveRate()
                    : (float) app(ExchangeRateService::class)->current();
            }

            if ($conversionRate <= 0) {
                throw ValidationException::withMessages([
                    'usd_conversion_enabled' => 'No se pudo obtener una tasa de cambio válida para convertir precios y productos.',
                ]);
            }
        }

        if ($previousCurrency !== $localCurrency) {
            if (Sale::query()->exists() || Closing::query()->exists() || Loan::query()->exists() || LoanPayment::query()->exists()) {
                throw ValidationException::withMessages([
                    'local_currency' => 'No puedes cambiar la moneda local porque ya existen ventas, cierres o préstamos registrados.',
                ]);
            }

            app(ExchangeRateService::class)->forgetAll();
        }

        DB::transaction(function () use ($data, $localCurrency, $request, $conversionEnabled, $conversionChanged, $conversionRate) {
            $this->settings->setMany([
                'shop_name' => $data['shop_name'],
                'shop_rif' => $data['shop_rif'] ?? '',
                'shop_phone' => $data['shop_phone'] ?? '',
                'shop_address' => $data['shop_address'] ?? '',
                'commission_rate' => $data['commission_rate'],
                'payment_methods' => $data['payment_methods'] ?? '',
                'local_currency' => $localCurrency,
                'dolar_api_source' => $data['dolar_api_source'] ?? $this->settings->get('dolar_api_source', 'oficial'),
                'exchange_rate_manual' => $data['exchange_rate_manual'] ?? $this->settings->get('exchange_rate_manual', ''),
                'exchange_rate_fallback' => $data['exchange_rate_fallback'] ?? $this->settings->get('exchange_rate_fallback', ''),
                'usd_conversion_enabled' => $conversionEnabled ? '1' : '0',
                'single_barber_mode' => $request->boolean('single_barber_mode') ? '1' : '0',
                'retentions_enabled' => $request->boolean('retentions_enabled') ? '1' : '0',
            ]);

            if ($conversionChanged) {
                $factor = $conversionEnabled ? 1 / $conversionRate : $conversionRate;

                Service::query()->each(function (Service $service) use ($factor) {
                    $service->update(['price' => round((float) $service->price * $factor, 2)]);
                });

                Product::query()->each(function (Product $product) use ($factor) {
                    $product->update([
                        'price' => round((float) $product->price * $factor, 2),
                        'cost' => round((float) $product->cost * $factor, 2),
                    ]);
                });

                Retention::query()->each(function (Retention $retention) use ($factor) {
                    $retention->update(['amount_usd' => round((float) $retention->amount_usd * $factor, 2)]);
                });

                if (! $conversionEnabled) {
                    $this->settings->set('usd_conversion_reference_rate', (string) $conversionRate);
                }
            }
        });

        if ($company && $company->name !== $data['shop_name']) {
            $company->update(['name' => $data['shop_name']]);
        }

        if ($company && $company->timezone !== $timezone) {
            $company->update(['timezone' => $timezone]);
        }

        if (! empty($data['barber_id'])) {
            $barber = User::query()->barbers()->find($data['barber_id']);

            if ($barber) {
                $rate = $request->boolean('barber_use_base') || ($data['barber_commission_rate'] ?? null) === null
                    ? null
                    : $data['barber_commission_rate'];

                $barber->update(['commission_rate' => $rate]);
            }
        }

        return redirect()->route('settings.edit')->with('status', 'Configuración guardada.');
    }
}
