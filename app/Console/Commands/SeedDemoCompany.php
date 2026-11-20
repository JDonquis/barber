<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\BarberLiquidation;
use App\Models\Closing;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ClosingService;
use App\Services\SettingService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDemoCompany extends Command
{
    protected $signature = 'demo:seed
        {company=barbertest : Nombre o slug de la barbería}
        {--days=45 : Días de historial de ventas a generar}
        {--reset : Elimina ventas, cierres, gastos, préstamos, citas y clientes antes de sembrar}';

    protected $description = 'Genera datos de demostración (barberos, cortes, productos, préstamos, gastos y citas) para una barbería';

    public function handle(): int
    {
        $company = $this->findCompany((string) $this->argument('company'));

        if (! $company) {
            $this->error('No se encontró la barbería indicada.');
            $this->line('Barberías disponibles:');
            Company::query()->orderBy('name')->get(['name', 'slug'])->each(
                fn (Company $item) => $this->line(" - {$item->name} ({$item->slug})")
            );

            return self::FAILURE;
        }

        $tenant = app(TenantManager::class);
        $tenant->set($company);
        apply_timezone($company);
        app(SettingService::class)->forget();

        $this->info("Sembrando datos de demostración para: {$company->name} ({$company->slug})");

        if ($this->option('reset')) {
            $this->resetData();
            $this->line('Datos transaccionales anteriores eliminados.');
        }

        $days = max(1, (int) $this->option('days'));
        $rate = $this->resolveExchangeRate();

        $result = DB::transaction(function () use ($company, $days, $rate) {
            [$admin, $barbers] = $this->seedTeam($company);
            $services = $this->seedServices();
            $products = $this->seedProducts($admin);
            $this->seedSettings();

            $customers = $this->seedCustomers();
            $this->seedAppointments($customers, $barbers, $services);

            if (! Sale::query()->exists() || $this->option('reset')) {
                $this->seedSales($admin, $barbers, $services, $products, $rate, $days);
            } else {
                $this->warn('Ya existen ventas para esta barbería; se omite el historial. Usa --reset para regenerarlo.');
            }

            $this->seedLoans($admin, $barbers, $rate);
            $this->seedExpenses($admin);
            $this->seedClosings($admin);

            return compact('admin', 'barbers');
        });

        app(SettingService::class)->forget();

        $this->newLine();
        $this->info('Datos de demostración generados correctamente.');
        $this->table(['Acceso demo', 'Valor'], [
            ['Administrador', $result['admin']->email],
            ['Contraseña admin', 'demo1234'],
            ['Barbero', $result['barbers']->first()->email],
            ['Contraseña barbero', 'barbero123'],
            ['Ventas', Sale::query()->count()],
            ['Citas', Appointment::query()->count()],
            ['Préstamos', Loan::query()->count()],
            ['Gastos', Expense::query()->count()],
        ]);

        return self::SUCCESS;
    }

    protected function findCompany(string $needle): ?Company
    {
        $needle = mb_strtolower(trim($needle));

        if ($needle === '') {
            return null;
        }

        $companies = Company::query()->get();

        return $companies->first(fn (Company $company) => mb_strtolower((string) $company->slug) === $needle)
            ?? $companies->first(fn (Company $company) => mb_strtolower((string) $company->name) === $needle)
            ?? $companies->first(fn (Company $company) => str_contains(mb_strtolower((string) $company->name), $needle)
                || str_contains(mb_strtolower((string) $company->slug), $needle));
    }

    protected function resetData(): void
    {
        LoanPayment::query()->delete();
        Loan::query()->delete();
        SaleItem::query()->delete();
        StockMovement::query()->delete();
        Sale::query()->delete();
        BarberLiquidation::query()->delete();
        Closing::query()->delete();
        Expense::query()->delete();
        Appointment::query()->delete();
        Customer::query()->delete();
    }

    /**
     * @return array{0: User, 1: Collection<int, User>}
     */
    protected function seedTeam(Company $company): array
    {
        $domain = mb_strtolower((string) $company->slug ?: $company->id);

        $admin = User::query()->updateOrCreate(
            ['email' => "admin@{$domain}.demo"],
            [
                'company_id' => $company->id,
                'name' => 'Dueño de '.$company->name,
                'phone' => '0414-0000001',
                'password' => 'demo1234',
                'role' => User::ROLE_ADMIN,
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        $barberData = [
            ['name' => 'Carlos Méndez', 'email' => "carlos@{$domain}.demo", 'phone' => '0414-1112233', 'rate' => 40],
            ['name' => 'Luis Rodríguez', 'email' => "luis@{$domain}.demo", 'phone' => '0414-2223344', 'rate' => 35],
            ['name' => 'Andrés Pérez', 'email' => "andres@{$domain}.demo", 'phone' => '0414-3334455', 'rate' => 45],
            ['name' => 'Miguel Torres', 'email' => "miguel@{$domain}.demo", 'phone' => '0414-4445566', 'rate' => null],
        ];

        $barbers = collect();

        foreach ($barberData as $data) {
            $barbers->push(User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'company_id' => $company->id,
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => 'barbero123',
                    'role' => User::ROLE_BARBER,
                    'commission_rate' => $data['rate'],
                    'can_register_sales' => true,
                    'active' => true,
                    'email_verified_at' => now(),
                ],
            ));
        }

        return [$admin, $barbers];
    }

    /**
     * @return Collection<int, Service>
     */
    protected function seedServices(): Collection
    {
        $services = [
            ['name' => 'Corte clásico', 'description' => 'Corte de cabello tradicional', 'price' => 8.00, 'duration_minutes' => 30],
            ['name' => 'Degradado / Fade', 'description' => 'Corte degradado a máquina', 'price' => 10.00, 'duration_minutes' => 40],
            ['name' => 'Corte + barba', 'description' => 'Corte de cabello más arreglo de barba', 'price' => 14.00, 'duration_minutes' => 50],
            ['name' => 'Arreglo de barba', 'description' => 'Perfilado y arreglo de barba', 'price' => 6.00, 'duration_minutes' => 20],
            ['name' => 'Corte para niño', 'description' => 'Corte de cabello infantil', 'price' => 7.00, 'duration_minutes' => 25],
            ['name' => 'Diseño de cejas', 'description' => 'Perfilado de cejas con navaja', 'price' => 5.00, 'duration_minutes' => 15],
        ];

        $result = collect();

        foreach ($services as $service) {
            $result->push(Service::query()->updateOrCreate(
                ['name' => $service['name']],
                $service + ['active' => true],
            ));
        }

        return $result;
    }

    /**
     * @return Collection<int, Product>
     */
    protected function seedProducts(User $admin): Collection
    {
        $products = [
            ['name' => 'Cera para cabello', 'sku' => 'DEMO-CER-001', 'price' => 9.00, 'cost' => 5.00, 'stock' => 24, 'min_stock' => 6],
            ['name' => 'Shampoo anticaída', 'sku' => 'DEMO-SHA-001', 'price' => 12.00, 'cost' => 7.00, 'stock' => 18, 'min_stock' => 5],
            ['name' => 'Aceite para barba', 'sku' => 'DEMO-ACE-001', 'price' => 11.00, 'cost' => 6.50, 'stock' => 16, 'min_stock' => 5],
            ['name' => 'Gel fijador', 'sku' => 'DEMO-GEL-001', 'price' => 8.00, 'cost' => 4.50, 'stock' => 20, 'min_stock' => 6],
            ['name' => 'Talco perfumado', 'sku' => 'DEMO-TAL-001', 'price' => 6.00, 'cost' => 3.00, 'stock' => 10, 'min_stock' => 4],
            ['name' => 'Peine profesional', 'sku' => 'DEMO-PEI-001', 'price' => 7.00, 'cost' => 3.50, 'stock' => 14, 'min_stock' => 4],
        ];

        $result = collect();

        foreach ($products as $product) {
            $item = Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                $product + ['active' => true],
            );

            if (! $item->movements()->exists()) {
                StockMovement::query()->create([
                    'product_id' => $item->id,
                    'user_id' => $admin->id,
                    'type' => StockMovement::TYPE_IN,
                    'quantity' => $item->stock,
                    'stock_after' => $item->stock,
                    'note' => 'Inventario inicial de demostración',
                ]);
            }

            $result->push($item);
        }

        return $result;
    }

    protected function seedSettings(): void
    {
        foreach ([
            'commission_rate' => '40',
            'payment_methods' => 'Efectivo Bs, Efectivo USD, Pago móvil, Punto de venta, Transferencia, Zelle',
            'usd_conversion_enabled' => '1',
            'single_barber_mode' => '0',
        ] as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        app(SettingService::class)->forget();
    }

    /**
     * @return Collection<int, Customer>
     */
    protected function seedCustomers(): Collection
    {
        $customers = [
            'José Ramírez', 'María González', 'Pedro Salazar', 'Daniela Rojas',
            'Alejandro Díaz', 'Valentina López', 'Gabriel Herrera', 'Camila Moreno',
            'Ricardo Blanco', 'Sofía Castro', 'Fernando Rivas', 'Lucía Mendoza',
        ];

        $result = collect();

        foreach ($customers as $index => $name) {
            $result->push(Customer::query()->updateOrCreate(
                ['phone' => '0414-900'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'last_visit_at' => now()->subDays(random_int(1, 35))->toDateString(),
                    'consent_at' => now()->subDays(random_int(40, 120)),
                ],
            ));
        }

        return $result;
    }

    protected function seedAppointments(Collection $customers, Collection $barbers, Collection $services): void
    {
        $slots = ['09:00', '10:30', '12:00', '14:30', '16:00', '17:30'];

        for ($day = 0; $day < 7; $day++) {
            $date = now()->startOfDay()->addDays($day);

            foreach ($slots as $slotIndex => $slot) {
                if (random_int(1, 100) > 55) {
                    continue;
                }

                $barber = $barbers->random();
                $service = $services->random();
                $customer = $customers->random();

                $start = Carbon::createFromFormat('H:i', $slot);
                $end = $start->copy()->addMinutes((int) $service->duration_minutes);

                Appointment::query()->updateOrCreate(
                    [
                        'barber_id' => $barber->id,
                        'date' => $date->toDateString(),
                        'start_time' => $slot,
                    ],
                    [
                        'customer_id' => $customer->id,
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                        'duration_minutes' => $service->duration_minutes,
                        'client_name' => $customer->name,
                        'client_phone' => $customer->phone,
                        'end_time' => $end->format('H:i'),
                        'status' => $day === 0
                            ? Appointment::STATUS_CONFIRMED
                            : (random_int(1, 100) <= 65 ? Appointment::STATUS_PENDING : Appointment::STATUS_CONFIRMED),
                        'notes' => $slotIndex % 3 === 0 ? 'Cita de demostración' : null,
                    ],
                );
            }
        }
    }

    protected function seedSales(
        User $admin,
        Collection $barbers,
        Collection $services,
        Collection $products,
        float $rate,
        int $days,
    ): void {
        $baseRate = (float) setting('commission_rate', 40);
        $methods = payment_methods();
        $stock = $products->mapWithKeys(fn (Product $product) => [$product->id => (int) $product->stock])->all();
        $notes = ['Cliente frecuente', 'Pasó sin cita', 'Cliente nuevo', 'Pidió degradado alto', 'Cliente del barrio'];

        $start = now()->startOfDay()->subDays($days - 1);
        $end = now()->startOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $isSunday = $date->isSunday();
            $isWeekend = $date->isFriday() || $date->isSaturday();
            $serviceCount = $isSunday ? random_int(0, 3) : ($isWeekend ? random_int(8, 16) : random_int(4, 10));
            $productCount = $isSunday ? random_int(0, 1) : random_int(1, 3);

            if ($date->isToday() && now()->hour < 9) {
                $serviceCount = 0;
                $productCount = 0;
            }

            $dayRate = $rate * (1 + random_int(-15, 15) / 1000);

            for ($i = 0; $i < $serviceCount; $i++) {
                $barber = $barbers->random();
                $service = $services->random();
                $registeredBy = random_int(1, 100) <= 50 ? $admin : $barber;
                $total = (float) $service->price;
                $commissionRate = $barber->effectiveCommissionRate($baseRate);
                $commission = round($total * $commissionRate / 100, 2);
                $at = $this->randomTime($date);

                $sale = new Sale([
                    'user_id' => $registeredBy->id,
                    'barber_id' => $barber->id,
                    'type' => Sale::TYPE_SERVICE,
                    'exchange_rate' => round($dayRate, 4),
                    'commission_rate' => $commissionRate,
                    'total_usd' => $total,
                    'total_ves' => round($total * $dayRate, 2),
                    'barber_commission_usd' => $commission,
                    'shop_amount_usd' => round($total - $commission, 2),
                    'payment_method' => $methods ? $methods[array_rand($methods)] : null,
                    'payment_currency' => random_int(1, 100) <= 70 ? 'USD' : local_currency(),
                    'status' => Sale::STATUS_COMPLETED,
                    'notes' => random_int(1, 100) <= 35 ? $notes[array_rand($notes)] : null,
                    'sold_at' => $at,
                ]);
                $this->saveSale($sale, $at);

                $sale->items()->create([
                    'item_type' => SaleItem::TYPE_SERVICE,
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'quantity' => 1,
                    'unit_price_usd' => $total,
                    'line_total_usd' => $total,
                ]);
            }

            for ($i = 0; $i < $productCount; $i++) {
                $product = $products->random();
                $available = $stock[$product->id] ?? 0;

                if ($available < 1) {
                    continue;
                }

                $quantity = min($available, random_int(1, 2));
                $total = round((float) $product->price * $quantity, 2);
                $at = $this->randomTime($date);
                $registeredBy = random_int(1, 100) <= 60 ? $admin : $barbers->random();

                $sale = new Sale([
                    'user_id' => $registeredBy->id,
                    'barber_id' => null,
                    'type' => Sale::TYPE_PRODUCT,
                    'exchange_rate' => round($dayRate, 4),
                    'commission_rate' => 0,
                    'total_usd' => $total,
                    'total_ves' => round($total * $dayRate, 2),
                    'barber_commission_usd' => 0,
                    'shop_amount_usd' => $total,
                    'payment_method' => $methods ? $methods[array_rand($methods)] : null,
                    'payment_currency' => random_int(1, 100) <= 70 ? 'USD' : local_currency(),
                    'status' => Sale::STATUS_COMPLETED,
                    'notes' => random_int(1, 100) <= 30 ? $notes[array_rand($notes)] : null,
                    'sold_at' => $at,
                ]);
                $this->saveSale($sale, $at);

                $sale->items()->create([
                    'item_type' => SaleItem::TYPE_PRODUCT,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price_usd' => $product->price,
                    'line_total_usd' => $total,
                ]);

                $stock[$product->id] -= $quantity;
                $this->recordMovement($product, StockMovement::TYPE_OUT, -$quantity, 'Venta '.$sale->code, $at, $registeredBy->id, $stock[$product->id]);

                if ($stock[$product->id] <= (int) $product->min_stock) {
                    $restock = max(20, (int) $product->min_stock * 5);
                    $stock[$product->id] += $restock;
                    $this->recordMovement($product, StockMovement::TYPE_IN, $restock, 'Reposición de inventario', $at, $admin->id, $stock[$product->id]);
                }
            }
        }

        foreach ($products as $product) {
            $product->update(['stock' => $stock[$product->id] ?? $product->stock]);
        }
    }

    protected function saveSale(Sale $sale, Carbon $at): void
    {
        $sale->code = 'TMP-'.Str::uuid();
        $sale->created_at = $at;
        $sale->updated_at = $at;
        $sale->save();

        $sale->update(['code' => 'V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);
    }

    protected function recordMovement(Product $product, string $type, int $quantity, string $note, Carbon $at, int $userId, int $stockAfter): void
    {
        $movement = new StockMovement([
            'product_id' => $product->id,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $stockAfter,
            'note' => $note,
        ]);
        $movement->created_at = $at;
        $movement->updated_at = $at;
        $movement->save();
    }

    protected function seedLoans(User $admin, Collection $barbers, float $rate): void
    {
        if (Loan::query()->exists()) {
            return;
        }

        $plans = [
            ['barber' => 0, 'amount' => 20, 'paid' => 0, 'days' => 12],
            ['barber' => 1, 'amount' => 40, 'paid' => 15, 'days' => 20],
            ['barber' => 2, 'amount' => 30, 'paid' => 30, 'days' => 28],
            ['barber' => 3, 'amount' => 10, 'paid' => 5, 'days' => 8],
        ];

        foreach ($plans as $plan) {
            $barber = $barbers[$plan['barber']] ?? $barbers->first();

            if (! $barber) {
                continue;
            }

            $date = now()->subDays($plan['days']);

            $loan = Loan::query()->create([
                'barber_id' => $barber->id,
                'created_by' => $admin->id,
                'amount_usd' => $plan['amount'],
                'amount_ves' => round($plan['amount'] * $rate, 2),
                'exchange_rate' => round($rate, 4),
                'paid_usd' => 0,
                'loan_date' => $date->toDateString(),
                'notes' => 'Préstamo de demostración',
            ]);

            if ($plan['paid'] > 0) {
                LoanPayment::query()->create([
                    'loan_id' => $loan->id,
                    'created_by' => $admin->id,
                    'amount_usd' => $plan['paid'],
                    'amount_ves' => round($plan['paid'] * $rate, 2),
                    'exchange_rate' => round($rate, 4),
                    'payment_date' => $date->copy()->addDays(3)->toDateString(),
                    'payment_method' => 'Efectivo USD',
                    'notes' => 'Abono de demostración',
                ]);

                $loan->update(['paid_usd' => $plan['paid']]);
            }
        }
    }

    protected function seedExpenses(User $admin): void
    {
        $expenses = [
            ['Alquiler del local', 120.00, 25],
            ['Servicio de luz', 18.50, 20],
            ['Servicio de agua', 6.00, 18],
            ['Internet', 15.00, 15],
            ['Compra de insumos', 35.00, 10],
            ['Publicidad en redes', 20.00, 7],
            ['Productos de limpieza', 12.00, 4],
            ['Mantenimiento de equipos', 25.00, 2],
        ];

        foreach ($expenses as [$concept, $amount, $daysAgo]) {
            Expense::query()->updateOrCreate(
                [
                    'concept' => $concept,
                    'expense_date' => now()->subDays($daysAgo)->toDateString(),
                ],
                [
                    'amount_usd' => $amount,
                    'user_id' => $admin->id,
                    'notes' => 'Gasto de demostración',
                ],
            );
        }
    }

    protected function seedClosings(User $admin): void
    {
        $closings = app(ClosingService::class);

        $daily = $closings->generate(Closing::PERIOD_DAILY, now()->subDay(), $admin);
        if (! $daily->isClosed()) {
            $closings->close($daily, $admin, 'Cierre demo diario');
        }

        $weekly = $closings->generate(Closing::PERIOD_WEEKLY, now()->subWeek(), $admin);
        if (! $weekly->isClosed()) {
            $closings->close($weekly, $admin, 'Cierre demo semanal');
        }

        $monthly = $closings->generate(Closing::PERIOD_MONTHLY, now()->subMonthNoOverflow(), $admin);
        if (! $monthly->isClosed()) {
            $closings->close($monthly, $admin, 'Cierre demo mensual');
        }

        $closings->generate(Closing::PERIOD_DAILY, now(), $admin);
    }

    protected function randomTime(Carbon $date): Carbon
    {
        $maxHour = $date->isToday() ? min(19, max(9, now()->hour)) : 19;

        return $date->copy()->setTime(random_int(9, $maxHour), random_int(0, 59), random_int(0, 59));
    }

    protected function resolveExchangeRate(): float
    {
        $rate = (float) (setting('exchange_rate_manual') ?: setting('exchange_rate_fallback') ?: 0);

        if ($rate <= 0) {
            $rate = (float) DB::table('exchange_rates')->latest('fetched_at')->value('rate');
        }

        return $rate > 0 ? $rate : 40.0;
    }
}
