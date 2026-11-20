<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'con_saldo');

        if (! in_array($status, ['con_saldo', 'pendiente', 'parcial', 'pagado', 'todos'], true)) {
            $status = 'con_saldo';
        }

        $barberId = $request->input('barber_id');
        $barbers = User::query()->barbers()->orderBy('name')->get();

        $query = Loan::query()
            ->with('barber')
            ->withCount('payments')
            ->when($barberId, fn ($builder) => $builder->where('barber_id', $barberId))
            ->when($status === 'con_saldo', fn ($builder) => $builder->withBalance())
            ->when($status === 'pendiente', fn ($builder) => $builder->pending())
            ->when($status === 'parcial', fn ($builder) => $builder->partial())
            ->when($status === 'pagado', fn ($builder) => $builder->paid());

        $loans = $query
            ->orderBy('loan_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        if (usd_conversion_enabled()) {
            $stats = [
                'lent' => (float) Loan::query()->sum('amount_usd'),
                'paid' => (float) Loan::query()->sum('paid_usd'),
                'outstanding' => (float) Loan::query()->withBalance()->get(['amount_usd', 'paid_usd'])->sum(fn (Loan $loan) => $loan->balance_usd),
                'active' => Loan::query()->withBalance()->count(),
            ];
        } else {
            $allLoans = Loan::query()->with('payments')->get();
            $stats = [
                'lent' => round($allLoans->sum(fn (Loan $loan) => $loan->localAmount()), 2),
                'paid' => round($allLoans->sum(fn (Loan $loan) => $loan->localPaid()), 2),
                'outstanding' => round($allLoans->sum(fn (Loan $loan) => $loan->localBalance()), 2),
                'active' => $allLoans->filter(fn (Loan $loan) => $loan->localBalance() > 0)->count(),
            ];
        }

        return view('loans.index', compact('loans', 'stats', 'status', 'barberId', 'barbers'));
    }

    public function create()
    {
        return view('loans.create', [
            'loan' => new Loan(['loan_date' => now()]),
            'barbers' => $this->barbers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateLoan($request);
        $data = $this->prepareAmounts($data);
        $data['created_by'] = $request->user()->id;

        $loan = Loan::create($data);

        return redirect()->route('loans.show', $loan)->with('status', 'Préstamo registrado.');
    }

    public function show(Loan $loan)
    {
        $loan->load([
            'barber',
            'createdBy',
            'payments' => fn ($query) => $query->with('createdBy')->orderByDesc('payment_date')->orderByDesc('id'),
        ]);

        return view('loans.show', [
            'loan' => $loan,
            'paymentMethods' => payment_methods(),
        ]);
    }

    public function edit(Loan $loan)
    {
        return view('loans.edit', [
            'loan' => $loan,
            'barbers' => $this->barbers(),
        ]);
    }

    public function update(Request $request, Loan $loan)
    {
        $data = $this->validateLoan($request);
        $data = $this->prepareAmounts($data, $loan);

        if ((float) $data['amount_usd'] < (float) $loan->paid_usd) {
            return back()->withInput()->withErrors([
                'amount_usd' => 'El monto del préstamo no puede ser menor a lo que ya se ha pagado ('.money($loan->paid_usd, $loan->localPaid()).').',
            ]);
        }

        $loan->update($data);

        return redirect()->route('loans.show', $loan)->with('status', 'Préstamo actualizado.');
    }

    public function destroy(Loan $loan)
    {
        if ($loan->payments()->exists()) {
            return back()->with('error', 'No puedes eliminar un préstamo con pagos registrados.');
        }

        $loan->delete();

        return redirect()->route('loans.index')->with('status', 'Préstamo eliminado.');
    }

    protected function validateLoan(Request $request): array
    {
        return $request->validate([
            'barber_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', company_id())
                    ->where('role', User::ROLE_BARBER)
                    ->whereNull('deleted_at'),
            ],
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'loan_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function prepareAmounts(array $data, ?Loan $loan = null): array
    {
        if (! usd_conversion_enabled()) {
            $local = round((float) $data['amount_usd'], 2);
            $rate = $loan && (float) $loan->exchange_rate > 0
                ? (float) $loan->exchange_rate
                : app(ExchangeRateService::class)->referenceRate();

            $data['amount_usd'] = $rate > 0 ? round($local / $rate, 2) : $local;
            $data['amount_ves'] = $local;
            $data['exchange_rate'] = $rate;

            return $data;
        }

        $data['amount_ves'] = round((float) $data['amount_usd'] * (float) $data['exchange_rate'], 2);

        return $data;
    }

    protected function barbers()
    {
        return User::query()->barbers()->orderBy('name')->get();
    }
}
