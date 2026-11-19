<?php

namespace App\Http\Controllers;

use App\Models\Retention;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RetentionController extends Controller
{
    public function index()
    {
        $this->ensureEnabled();

        $retentions = Retention::query()
            ->with('barber')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $retentions->count(),
            'active' => $retentions->where('active', true)->count(),
            'global' => $retentions->whereNull('barber_id')->count(),
            'barbers' => $retentions->whereNotNull('barber_id')->pluck('barber_id')->unique()->count(),
        ];

        return view('retentions.index', compact('retentions', 'stats'));
    }

    public function create()
    {
        $this->ensureEnabled();

        return view('retentions.create', [
            'retention' => new Retention(['active' => true, 'frequency' => Retention::FREQUENCY_MONTHLY]),
            'barbers' => $this->barbers(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureEnabled();

        $data = $this->validateData($request);
        $data['active'] = $request->boolean('active', true);

        Retention::create($data);

        return redirect()->route('retentions.index')->with('status', 'Concepto de retención creado.');
    }

    public function edit(Retention $retention)
    {
        $this->ensureEnabled();

        return view('retentions.edit', [
            'retention' => $retention,
            'barbers' => $this->barbers(),
        ]);
    }

    public function update(Request $request, Retention $retention)
    {
        $this->ensureEnabled();

        $data = $this->validateData($request);
        $data['active'] = $request->boolean('active');

        $retention->update($data);

        return redirect()->route('retentions.index')->with('status', 'Concepto de retención actualizado.');
    }

    public function toggleActive(Request $request, Retention $retention)
    {
        $this->ensureEnabled();

        $retention->update(['active' => ! $retention->active]);

        if ($request->wantsJson()) {
            return response()->json(['active' => $retention->active]);
        }

        return back()->with('status', $retention->active ? 'Retención activada.' : 'Retención desactivada.');
    }

    public function destroy(Retention $retention)
    {
        $this->ensureEnabled();

        $retention->delete();

        return redirect()->route('retentions.index')->with('status', 'Concepto de retención eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'frequency' => ['required', Rule::in(array_keys(Retention::FREQUENCIES))],
            'barber_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('company_id', company_id())
                    ->where('role', User::ROLE_BARBER)
                    ->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    protected function barbers()
    {
        return User::query()->barbers()->orderBy('name')->get();
    }

    protected function ensureEnabled(): void
    {
        abort_unless((string) setting('retentions_enabled', '0') === '1', 404);
    }
}
