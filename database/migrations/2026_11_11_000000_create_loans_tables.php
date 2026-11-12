<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loans')) {
            Schema::create('loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('barber_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount_usd', 12, 2);
                $table->decimal('amount_ves', 16, 2)->default(0);
                $table->decimal('exchange_rate', 14, 4)->default(0);
                $table->decimal('paid_usd', 12, 2)->default(0);
                $table->date('loan_date');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'barber_id']);
                $table->index(['company_id', 'loan_date']);
            });
        }

        if (! Schema::hasTable('loan_payments')) {
            Schema::create('loan_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount_usd', 12, 2);
                $table->decimal('amount_ves', 16, 2)->default(0);
                $table->decimal('exchange_rate', 14, 4)->default(0);
                $table->date('payment_date');
                $table->string('payment_method', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'loan_id']);
                $table->index(['company_id', 'payment_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loans');
    }
};
