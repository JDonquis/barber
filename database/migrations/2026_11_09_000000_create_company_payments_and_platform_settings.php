<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'saas_paypal_rate' => '950',
            'saas_monthly_fee' => '5',
            'payment_cedula' => '30847627',
            'payment_bank' => '0102 Venezuela',
            'payment_phone' => '04125800610',
        ] as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'monthly_fee_usd')) {
                    $table->decimal('monthly_fee_usd', 10, 2)->default(5)->after('active');
                }

                if (! Schema::hasColumn('companies', 'is_exempt')) {
                    $table->boolean('is_exempt')->default(false)->after('monthly_fee_usd');
                }

                if (! Schema::hasColumn('companies', 'subscription_started_at')) {
                    $table->date('subscription_started_at')->nullable()->after('is_exempt');
                }

                if (! Schema::hasColumn('companies', 'next_payment_due_at')) {
                    $table->date('next_payment_due_at')->nullable()->after('subscription_started_at');
                }

                if (! Schema::hasColumn('companies', 'last_payment_at')) {
                    $table->date('last_payment_at')->nullable()->after('next_payment_due_at');
                }
            });

            foreach (DB::table('companies')->get() as $company) {
                $startedAt = $company->subscription_started_at
                    ?: ($company->created_at ? mb_substr((string) $company->created_at, 0, 10) : now()->toDateString());

                DB::table('companies')->where('id', $company->id)->update([
                    'monthly_fee_usd' => $company->monthly_fee_usd ?? 5,
                    'is_exempt' => (bool) ($company->is_exempt ?? false),
                    'subscription_started_at' => $startedAt,
                    'next_payment_due_at' => $company->next_payment_due_at
                        ?: ((bool) ($company->is_exempt ?? false) ? null : now()->addMonth()->toDateString()),
                ]);
            }
        }

        if (! Schema::hasTable('company_payments')) {
            Schema::create('company_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('period', 7);
                $table->decimal('amount_usd', 10, 2)->default(5);
                $table->decimal('exchange_rate', 14, 4)->default(950);
                $table->decimal('amount_ves', 16, 2)->default(0);
                $table->string('payment_method')->default('pago_movil');
                $table->string('reference')->nullable();
                $table->date('paid_at')->nullable();
                $table->string('screenshot_path');
                $table->string('status', 20)->default('pendiente');
                $table->text('notes')->nullable();
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index('period');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payments');

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                foreach (['last_payment_at', 'next_payment_due_at', 'subscription_started_at', 'is_exempt', 'monthly_fee_usd'] as $column) {
                    if (Schema::hasColumn('companies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('platform_settings');
    }
};
