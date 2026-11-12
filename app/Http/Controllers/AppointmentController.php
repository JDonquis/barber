<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\CustomerReminderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(protected CustomerReminderService $reminders) {}

    public function index(Request $request)
    {
        $status = $request->input('status', Appointment::STATUS_PENDING);
        $date = $request->boolean('all') ? null : $request->input('date');

        $appointments = Appointment::query()
            ->with(['barber', 'service'])
            ->when($date, fn ($query) => $query->whereDate('date', $date))
            ->when($status !== 'todos', fn ($query) => $query->where('status', $status))
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'today' => Appointment::query()
                ->whereDate('date', now()->toDateString())
                ->where('status', '!=', Appointment::STATUS_CANCELLED)
                ->count(),
            'pending' => Appointment::query()->where('status', Appointment::STATUS_PENDING)->count(),
            'confirmed' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->count(),
        ];

        return view('appointments.index', compact('appointments', 'stats', 'status', 'date'));
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_COMPLETED,
            ])],
        ]);

        $wasCompleted = $appointment->isCompleted();

        $appointment->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === Appointment::STATUS_COMPLETED
                ? ($appointment->completed_at ?? now())
                : $appointment->completed_at,
        ]);

        if ($data['status'] === Appointment::STATUS_COMPLETED && ! $wasCompleted) {
            $this->reminders->handleCompleted($appointment->fresh());
        }

        return back()->with('status', 'Cita actualizada correctamente.');
    }
}
