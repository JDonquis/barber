<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\SettingService;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenant
{
    /**
     * Resolve the barbershop that belongs to the authenticated user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantManager::class);
        $tenant->set(null);

        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            $company = $user->company_id
                ? Company::query()->find($user->company_id)
                : null;

            if (! $company || ! $company->active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Tu barbería no está activa. Contacta al administrador de la plataforma.');
            }

            $tenant->set($company);
        }

        app(SettingService::class)->resetMemory();
        apply_timezone($tenant->get());

        return $next($request);
    }
}
