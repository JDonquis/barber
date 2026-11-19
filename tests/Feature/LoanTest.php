<?php

namespace Tests\Feature;

use App\Models\Closing;
use App\Models\Company;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return app(TenantManager::class)->get();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
    }

    private function barber(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_BARBER,
            'active' => true,
        ]);
    }

    private function loan(User $barber, array $overrides = []): Loan
    {
        return Loan::create($overrides + [
            'barber_id' => $barber->id,
            'amount_usd' => 100,
            'amount_ves' => 3000,
            'exchange_rate' => 30,
            'paid_usd' => 0,
            'loan_date' => now()->toDateString(),
        ]);
    }

    public function test_admin_can_register_a_loan(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $company = $this->company();

        $this->actingAs($admin)->post(route('loans.store'), [
            'barber_id' => $barber->id,
            'amount_usd' => 150,
            'exchange_rate' => 30,
            'loan_date' => now()->toDateString(),
            'notes' => 'Adelanto de quincena',
        ])->assertRedirect();

        $loan = Loan::query()->firstOrFail();

        $this->assertSame($company->id, $loan->company_id);
        $this->assertSame($barber->id, $loan->barber_id);
        $this->assertSame($admin->id, $loan->created_by);
        $this->assertSame(150.0, (float) $loan->amount_usd);
        $this->assertSame(4500.0, (float) $loan->amount_ves);
        $this->assertSame(Loan::STATUS_PENDING, $loan->status);
    }

    public function test_closing_report_includes_loans_and_payment_progress(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $loan = $this->loan($barber, ['amount_usd' => 100, 'paid_usd' => 40]);
        $oldLoan = $this->loan($barber, [
            'amount_usd' => 50,
            'paid_usd' => 10,
            'loan_date' => now()->subDays(10)->toDateString(),
        ]);

        LoanPayment::create([
            'loan_id' => $loan->id,
            'created_by' => $admin->id,
            'amount_usd' => 40,
            'amount_ves' => 1200,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Efectivo USD',
        ]);

        LoanPayment::create([
            'loan_id' => $oldLoan->id,
            'created_by' => $admin->id,
            'amount_usd' => 10,
            'amount_ves' => 300,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Pago móvil',
        ]);

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $closing = Closing::query()->whereNull('barber_id')->latest('id')->firstOrFail();
        $loans = collect($closing->details['prestamos'] ?? []);
        $totals = $closing->details['prestamos_totales'];

        $this->assertCount(2, $loans);

        $current = $loans->firstWhere('id', $loan->id);
        $previous = $loans->firstWhere('id', $oldLoan->id);

        $this->assertEquals(100.0, $current['amount_usd']);
        $this->assertEquals(40.0, $current['paid_usd']);
        $this->assertEquals(60.0, $current['balance_usd']);
        $this->assertEquals(40.0, $current['period_paid_usd']);
        $this->assertSame(40, $current['progress']);
        $this->assertTrue($current['loaned_in_period']);
        $this->assertFalse($previous['loaned_in_period']);

        $this->assertEquals(100.0, $totals['issued_usd']);
        $this->assertEquals(50.0, $totals['paid_usd']);
        $this->assertEquals(100.0, $totals['outstanding_usd']);

        $this->actingAs($admin)
            ->get(route('closings.show', $closing))
            ->assertOk()
            ->assertSee('Préstamos del período')
            ->assertSee($barber->name)
            ->assertSee('Abonos en el período');

        $this->actingAs($admin)
            ->get(route('closings.print', $closing))
            ->assertOk()
            ->assertSee('Préstamos del período')
            ->assertSee('Abonos del período');
    }

    public function test_barber_closing_includes_all_their_loans_even_without_period_activity(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $loan = $this->loan($barber, [
            'amount_usd' => 20,
            'paid_usd' => 19,
            'loan_date' => now()->subDays(8)->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('closings.generate'), [
            'period_type' => 'diario',
            'date' => now()->toDateString(),
            'barber_id' => $barber->id,
        ])->assertRedirect();

        $closing = Closing::query()->where('barber_id', $barber->id)->latest('id')->firstOrFail();
        $loans = collect($closing->details['prestamos'] ?? []);
        $row = $loans->firstWhere('id', $loan->id);

        $this->assertCount(1, $loans);
        $this->assertEquals(20.0, $row['amount_usd']);
        $this->assertEquals(19.0, $row['paid_usd']);
        $this->assertEquals(1.0, $row['balance_usd']);
        $this->assertEquals(0.0, $row['period_paid_usd']);
        $this->assertFalse($row['loaned_in_period']);

        $this->actingAs($admin)
            ->get(route('closings.print', $closing))
            ->assertOk()
            ->assertSee('Préstamos del período')
            ->assertSee($barber->name);
    }

    public function test_loan_pages_render(): void
    {
        $admin = $this->admin();
        $loan = $this->loan($this->barber());

        $this->actingAs($admin)->get(route('loans.index'))->assertOk()->assertSee('Préstamos');
        $this->actingAs($admin)->get(route('loans.create'))->assertOk()->assertSee('Nuevo préstamo');
        $this->actingAs($admin)->get(route('loans.show', $loan))->assertOk()->assertSee('Historial de pagos');
        $this->actingAs($admin)->get(route('loans.edit', $loan))->assertOk()->assertSee('Editar préstamo');
    }

    public function test_admin_can_register_partial_and_full_payments(): void
    {
        $admin = $this->admin();
        $barber = $this->barber();
        $loan = $this->loan($barber);

        $this->actingAs($admin)->post(route('loans.payments.store', $loan), [
            'amount_usd' => 40,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Efectivo USD',
            'notes' => 'Primer abono',
        ])->assertRedirect(route('loans.index'))->assertSessionHas('status');

        $loan->refresh();

        $this->assertSame(40.0, (float) $loan->paid_usd);
        $this->assertSame(60.0, $loan->balance_usd);
        $this->assertSame(Loan::STATUS_PARTIAL, $loan->status);

        $payment = LoanPayment::query()->firstOrFail();

        $this->assertSame($this->company()->id, $payment->company_id);
        $this->assertSame(1200.0, (float) $payment->amount_ves);
        $this->assertSame($admin->id, $payment->created_by);

        $this->actingAs($admin)->post(route('loans.payments.store', $loan), [
            'amount_usd' => 60,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect(route('loans.index'));

        $loan->refresh();

        $this->assertSame(100.0, (float) $loan->paid_usd);
        $this->assertSame(0.0, $loan->balance_usd);
        $this->assertSame(Loan::STATUS_PAID, $loan->status);
    }

    public function test_payment_cannot_exceed_the_pending_balance(): void
    {
        $admin = $this->admin();
        $loan = $this->loan($this->barber());

        $this->actingAs($admin)->post(route('loans.payments.store', $loan), [
            'amount_usd' => 120,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount_usd');

        $this->assertSame(0.0, (float) $loan->fresh()->paid_usd);
        $this->assertSame(0, LoanPayment::query()->count());
    }

    public function test_barber_cannot_access_the_loans_module(): void
    {
        $barber = $this->barber();

        $this->actingAs($barber)->get(route('loans.index'))->assertForbidden();
    }

    public function test_loans_are_isolated_between_companies(): void
    {
        $barber = $this->barber();
        $loan = $this->loan($barber);

        $this->actingAs($this->admin())->post(route('loans.payments.store', $loan), [
            'amount_usd' => 25,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertSame(1, Loan::query()->count());
        $this->assertSame(1, LoanPayment::query()->count());

        $second = Company::create([
            'name' => 'Otra Barbería',
            'slug' => 'otra-barberia-prestamos',
            'active' => true,
        ]);

        app(TenantManager::class)->set($second);

        $this->assertSame(0, Loan::query()->count());
        $this->assertSame(0, LoanPayment::query()->count());
    }

    public function test_loan_with_payments_cannot_be_deleted_and_removing_a_payment_recalculates_the_balance(): void
    {
        $admin = $this->admin();
        $loan = $this->loan($this->barber());

        $this->actingAs($admin)->post(route('loans.payments.store', $loan), [
            'amount_usd' => 35,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
        ]);

        $payment = LoanPayment::query()->firstOrFail();

        $this->actingAs($admin)->delete(route('loans.destroy', $loan))->assertSessionHas('error');
        $this->assertDatabaseHas('loans', ['id' => $loan->id]);

        $this->actingAs($admin)->delete(route('loans.payments.destroy', [$loan, $payment]))
            ->assertRedirect(route('loans.show', $loan))
            ->assertSessionHas('status');

        $loan->refresh();

        $this->assertSame(0.0, (float) $loan->paid_usd);
        $this->assertSame(Loan::STATUS_PENDING, $loan->status);
        $this->assertDatabaseMissing('loan_payments', ['id' => $payment->id]);
    }

    public function test_loan_amount_cannot_be_updated_below_the_paid_total(): void
    {
        $admin = $this->admin();
        $loan = $this->loan($this->barber());

        $this->actingAs($admin)->post(route('loans.payments.store', $loan), [
            'amount_usd' => 80,
            'exchange_rate' => 30,
            'payment_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->put(route('loans.update', $loan), [
            'barber_id' => $loan->barber_id,
            'amount_usd' => 50,
            'exchange_rate' => 30,
            'loan_date' => $loan->loan_date->toDateString(),
        ])->assertSessionHasErrors('amount_usd');

        $this->assertSame(100.0, (float) $loan->fresh()->amount_usd);
    }
}
