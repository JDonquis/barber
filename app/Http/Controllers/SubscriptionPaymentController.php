<?php

namespace App\Http\Controllers;

use App\Models\CompanyPayment;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function __construct(protected PlatformSettingService $settings) {}

    public function index()
    {
        $company = current_company();
        $rate = (float) $this->settings->get('saas_paypal_rate', 950);
        $monthlyFee = (float) ($company->monthly_fee_usd ?? $this->settings->get('saas_monthly_fee', 5));
        $amountVes = round($monthlyFee * $rate, 2);

        $payments = CompanyPayment::query()
            ->with('reviewedBy')
            ->latest()
            ->paginate(10);

        $pendingPayment = CompanyPayment::query()->pending()->first();

        $paymentData = [
            'cedula' => $this->settings->get('payment_cedula', '30847627'),
            'bank' => $this->settings->get('payment_bank', '0102 Venezuela'),
            'phone' => $this->settings->get('payment_phone', '04125800610'),
        ];

        $copyText = "Cédula: {$paymentData['cedula']}\nBanco: {$paymentData['bank']}\nTeléfono: {$paymentData['phone']}";

        return view('payments.index', [
            'company' => $company,
            'rate' => $rate,
            'monthlyFee' => $monthlyFee,
            'amountVes' => $amountVes,
            'payments' => $payments,
            'pendingPayment' => $pendingPayment,
            'paymentData' => $paymentData,
            'copyText' => $copyText,
        ]);
    }

    public function store(Request $request)
    {
        $company = current_company();

        if ($company->is_exempt) {
            return back()->with('error', 'Tu barbería está exonerada del pago mensual.');
        }

        if (CompanyPayment::query()->pending()->exists()) {
            return back()->with('error', 'Ya tienes un pago en revisión. Espera la respuesta del administrador.');
        }

        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
            'screenshot' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $rate = (float) $this->settings->get('saas_paypal_rate', 950);
        $amountUsd = (float) ($company->monthly_fee_usd ?? $this->settings->get('saas_monthly_fee', 5));

        $screenshotPath = $request->file('screenshot')->store('payment-proofs', 'public');

        CompanyPayment::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'period' => $request->date('paid_at')->format('Y-m'),
            'amount_usd' => round($amountUsd, 2),
            'exchange_rate' => $rate,
            'amount_ves' => round($amountUsd * $rate, 2),
            'payment_method' => 'pago_movil',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'],
            'screenshot_path' => $screenshotPath,
            'status' => CompanyPayment::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Pago reportado correctamente. Queda pendiente de revisión.');
    }
}
