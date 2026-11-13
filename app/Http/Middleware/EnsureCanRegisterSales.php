<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanRegisterSales
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canRegisterSales()) {
            abort(403, 'No tienes permiso para registrar cortes.');
        }

        return $next($request);
    }
}
