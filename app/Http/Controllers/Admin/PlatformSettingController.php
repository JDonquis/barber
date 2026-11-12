<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    public function __construct(protected PlatformSettingService $settings) {}

    public function edit()
    {
        return view('admin.settings.edit', [
            'values' => $this->settings->all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'saas_paypal_rate' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999'],
            'saas_monthly_fee' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'payment_cedula' => ['required', 'string', 'max:30'],
            'payment_bank' => ['required', 'string', 'max:60'],
            'payment_phone' => ['required', 'string', 'max:30'],
        ]);

        $this->settings->setMany($data);

        return redirect()->route('admin.settings.edit')->with('status', 'Configuración SaaS actualizada.');
    }
}
