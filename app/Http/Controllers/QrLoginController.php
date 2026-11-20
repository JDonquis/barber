<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\QrLoginToken;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\QrLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrLoginController extends Controller
{
    public function consume(Request $request, string $token): RedirectResponse
    {
        $record = QrLoginToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $record || ! $record->isUsable()) {
            return redirect()->route('login')
                ->with('error', 'Este código QR ya venció o fue utilizado. Genera uno nuevo.');
        }

        $user = User::withoutGlobalScope('company')->find($record->user_id);
        $company = $user?->company_id
            ? Company::query()->find($user->company_id)
            : null;

        if (! $user || ! $user->active || ! $company || ! $company->active) {
            return redirect()->route('login')
                ->with('error', 'El usuario o la barbería ya no están activos.');
        }

        $claimed = QrLoginToken::query()
            ->whereKey($record->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update([
                'used_at' => now(),
                'used_ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return redirect()->route('login')
                ->with('error', 'Este código QR ya venció o fue utilizado. Genera uno nuevo.');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to($user->isSuperAdmin()
            ? route('admin.companies.index')
            : RouteServiceProvider::HOME);
    }

    public function regenerate(Request $request, QrLoginService $qrLogin): RedirectResponse
    {
        $qrLogin->issue($request->user());

        return redirect()->route('settings.edit')
            ->with('status', 'Nuevo código QR generado.');
    }
}
