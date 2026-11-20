<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barber_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('barber_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('amount_local', 16, 2)->nullable();
            $table->date('payment_date');
            $table->string('payment_method', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'barber_id', 'payment_date'], 'barber_payments_barber_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_payments');
    }
};
