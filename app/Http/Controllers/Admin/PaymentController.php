<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'todos');
        $search = trim((string) $request->input('search'));

        $payments = CompanyPayment::query()
            ->with(['company', 'user', 'reviewedBy'])
            ->when(in_array($status, [
                CompanyPayment::STATUS_PENDING,
                CompanyPayment::STATUS_APPROVED,
                CompanyPayment::STATUS_REJECTED,
            ], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term) {
                    $query->where('reference', 'like', $term)
                        ->orWhere('period', 'like', $term)
                        ->orWhereHas('company', fn ($company) => $company->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'pending' => CompanyPayment::query()->pending()->count(),
            'approved_month' => (float) CompanyPayment::query()
                ->approved()
                ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount_usd'),
            'rejected' => CompanyPayment::query()
                ->where('status', CompanyPayment::STATUS_REJECTED)
                ->count(),
            'overdue_companies' => Company::query()
                ->where('is_exempt', false)
                ->whereNotNull('next_payment_due_at')
                ->whereDate('next_payment_due_at', '<', now()->toDateString())
                ->count(),
        ];

        return view('admin.payments.index', compact('payments', 'stats', 'status', 'search'));
    }

    public function approve(Request $request, CompanyPayment $payment)
    {
        if (! $payment->isPending()) {
            return back()->with('error', 'Este pago ya fue revisado.');
        }

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($payment, $request, $data) {
            $payment->update([
                'status' => CompanyPayment::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            $company = $payment->company;

            if ($company && ! $company->is_exempt) {
                $company->update([
                    'last_payment_at' => now()->toDateString(),
                    'next_payment_due_at' => now()->addMonth()->toDateString(),
                ]);
            }
        });

        return back()->with('status', 'Pago aprobado y suscripción renovada por un mes.');
    }

    public function reject(Request $request, CompanyPayment $payment)
    {
        if (! $payment->isPending()) {
            return back()->with('error', 'Este pago ya fue revisado.');
        }

        $data = $request->validate([
            'review_notes' => ['required', 'string', 'max:500'],
        ]);

        $payment->update([
            'status' => CompanyPayment::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);

        return back()->with('status', 'Pago rechazado. La barbería deberá corregir y enviar uno nuevo.');
    }
}
