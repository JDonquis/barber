<?php

namespace Tests;

use App\Models\Company;
use App\Services\SettingService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Company::class) && Schema::hasTable('companies')) {
            $company = Company::create([
                'name' => 'Barbería de prueba',
                'slug' => 'test-'.Str::lower(Str::random(8)),
                'active' => true,
            ]);

            app(TenantManager::class)->set($company);
            app(SettingService::class)->resetMemory();
        }

        Http::fake([
            've.dolarapi.com/*' => Http::response([
                'moneda' => 'USD',
                'fuente' => 'oficial',
                'promedio' => 30,
            ], 200),
        ]);
    }
}
