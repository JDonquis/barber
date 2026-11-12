<?php

namespace App\Providers;

use App\Services\ExchangeRateService;
use App\Services\PlatformSettingService;
use App\Services\SettingService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(SettingService::class);
        $this->app->singleton(PlatformSettingService::class);
        $this->app->singleton(ExchangeRateService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $company = app(TenantManager::class)->get();

            $view->with('shopName', $company?->name ?? setting('shop_name', config('app.name')));
            $view->with('companyLogo', $company?->logo_url ?? asset('logo.jpeg'));
            $view->with('currentRate', $company ? exchange_rate() : 0);
        });
    }
}
