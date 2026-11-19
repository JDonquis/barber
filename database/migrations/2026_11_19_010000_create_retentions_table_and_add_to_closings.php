<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('retentions')) {
            Schema::create('retentions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('barber_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 120);
                $table->decimal('amount_usd', 12, 2);
                $table->string('frequency', 20)->default('mensual');
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'barber_id']);
                $table->index(['company_id', 'active']);
            });
        }

        if (! Schema::hasColumn('closings', 'barber_retentions_usd')) {
            Schema::table('closings', function (Blueprint $table) {
                $table->decimal('barber_retentions_usd', 12, 2)->default(0)->after('barber_tips_usd');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('closings', 'barber_retentions_usd')) {
            Schema::table('closings', function (Blueprint $table) {
                $table->dropColumn('barber_retentions_usd');
            });
        }

        Schema::dropIfExists('retentions');
    }
};
