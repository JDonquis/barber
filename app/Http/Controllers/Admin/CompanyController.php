<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyPayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\PlatformSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompanyController extends Controller
{
    public function __construct(protected PlatformSettingService $platformSettings) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        $companies = Company::query()
            ->withCount(['users', 'sales', 'services', 'products'])
            ->withCount(['payments as pending_payments_count' => fn ($query) => $query->where('status', CompanyPayment::STATUS_PENDING)])
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Company::query()->count(),
            'active' => Company::query()->where('active', true)->count(),
            'inactive' => Company::query()->where('active', false)->count(),
            'users' => User::withoutGlobalScope('company')->count(),
        ];

        return view('admin.companies.index', compact('companies', 'stats', 'search'));
    }

    public function create()
    {
        $defaultMonthlyFee = (float) $this->platformSettings->get('saas_monthly_fee', 5);

        return view('admin.companies.create', [
            'company' => new Company(['active' => true]),
            'defaultMonthlyFee' => $defaultMonthlyFee,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCompany($request);

        $company = DB::transaction(function () use ($request, $data) {
            $isExempt = $request->boolean('is_exempt');

            $company = Company::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'active' => $request->boolean('active', true),
                'monthly_fee_usd' => round((float) ($data['monthly_fee_usd'] ?? $this->platformSettings->get('saas_monthly_fee', 5)), 2),
                'is_exempt' => $isExempt,
                'subscription_started_at' => now()->toDateString(),
                'next_payment_due_at' => $isExempt ? null : now()->addMonth()->toDateString(),
            ]);

            $this->saveLogo($request, $company);

            User::create([
                'company_id' => $company->id,
                'is_super_admin' => false,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'role' => User::ROLE_ADMIN,
                'active' => true,
                'email_verified_at' => now(),
            ]);

            $this->createDefaultSettings($company, $data);

            return $company;
        });

        return redirect()->route('admin.companies.index')
            ->with('status', "Barbería {$company->name} creada con su administrador.");
    }

    public function edit(Company $company)
    {
        $defaultMonthlyFee = (float) $this->platformSettings->get('saas_monthly_fee', 5);

        return view('admin.companies.edit', compact('company', 'defaultMonthlyFee'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $this->validateCompany($request, $company);

        DB::transaction(function () use ($request, $data, $company) {
            $isExempt = $request->boolean('is_exempt');

            $company->fill([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name'], $company),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'active' => $request->boolean('active'),
                'monthly_fee_usd' => round((float) ($data['monthly_fee_usd'] ?? $company->monthly_fee_usd), 2),
                'is_exempt' => $isExempt,
            ]);

            if (! $company->subscription_started_at) {
                $company->subscription_started_at = now()->toDateString();
            }

            if ($isExempt) {
                $company->next_payment_due_at = null;
            } elseif (! $company->next_payment_due_at) {
                $company->next_payment_due_at = now()->addMonth()->toDateString();
            }

            $company->save();

            if ($company->wasChanged('name')) {
                Setting::query()
                    ->where('company_id', $company->id)
                    ->where('key', 'shop_name')
                    ->update(['value' => $company->name]);
            }

            if ($request->boolean('remove_logo') && $company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
                $company->update(['logo_path' => null]);
            }

            $this->saveLogo($request, $company);
        });

        return redirect()->route('admin.companies.index')
            ->with('status', 'Barbería actualizada.');
    }

    public function toggle(Request $request, Company $company)
    {
        $company->update(['active' => ! $company->active]);

        if ($request->wantsJson()) {
            return response()->json(['active' => $company->active]);
        }

        return back()->with('status', $company->active ? 'Barbería activada.' : 'Barbería suspendida.');
    }

    protected function validateCompany(Request $request, ?Company $company = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('companies', 'slug')->ignore($company?->id)],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'monthly_fee_usd' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'is_exempt' => ['nullable', 'boolean'],
        ];

        if ($company === null) {
            $rules = array_merge($rules, [
                'admin_name' => ['required', 'string', 'max:120'],
                'admin_email' => ['required', 'email', 'max:120', 'unique:users,email'],
                'admin_password' => ['required', 'confirmed', Password::min(8)],
            ]);
        }

        return $request->validate($rules);
    }

    protected function uniqueSlug(string $value, ?Company $ignore = null): string
    {
        $slug = Str::slug($value) ?: 'barberia';
        $base = $slug;
        $suffix = 2;

        while (Company::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function saveLogo(Request $request, Company $company): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->update([
            'logo_path' => $request->file('logo')->store('company-logos', 'public'),
        ]);
    }

    protected function createDefaultSettings(Company $company, array $data): void
    {
        $defaults = [
            'shop_name' => $company->name,
            'shop_rif' => '',
            'shop_phone' => $data['phone'] ?? '',
            'shop_address' => $data['address'] ?? '',
            'commission_rate' => '40',
            'local_currency' => 'VES',
            'payment_methods' => 'Efectivo Bs, Efectivo USD, Pago móvil, Punto de venta, Transferencia, Zelle',
            'dolar_api_source' => 'oficial',
            'usd_conversion_enabled' => '1',
            'exchange_rate_manual' => '',
            'exchange_rate_fallback' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::create([
                'company_id' => $company->id,
                'key' => $key,
                'value' => $value,
            ]);
        }
    }
}
