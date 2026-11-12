<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PushSubscription;
use App\Models\ReminderSubscription;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        $customers = Customer::query()
            ->with(['subscription', 'pushSubscriptions'])
            ->withCount('appointments')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderByDesc('last_visit_at')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Customer::query()->count(),
            'active_reminders' => ReminderSubscription::query()->where('active', true)->count(),
            'push_devices' => PushSubscription::query()->count(),
            'due' => ReminderSubscription::query()
                ->where('active', true)
                ->whereNotNull('next_reminder_at')
                ->where('next_reminder_at', '<=', now())
                ->count(),
        ];

        return view('customers.index', compact('customers', 'stats', 'search'));
    }
}
