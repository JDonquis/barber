<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    /**
     * Only users linked to an active barbershop may access tenant routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TenantManager::class)->check()) {
            abort(403, 'Esta sección requiere una barbería activa.');
        }

        return $next($request);
    }
}
