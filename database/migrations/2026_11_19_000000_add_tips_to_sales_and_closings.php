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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('tip_usd', 12, 2)->default(0)->after('barber_commission_usd');
        });

        Schema::table('closings', function (Blueprint $table) {
            $table->decimal('barber_tips_usd', 12, 2)->default(0)->after('barber_commission_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('tip_usd');
        });

        Schema::table('closings', function (Blueprint $table) {
            $table->dropColumn('barber_tips_usd');
        });
    }
};
