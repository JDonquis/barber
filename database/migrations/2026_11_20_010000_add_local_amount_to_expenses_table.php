<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses') || Schema::hasColumn('expenses', 'amount_local')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount_local', 16, 2)->nullable()->after('amount_usd');
        });

        $lastRate = null;

        if (Schema::hasTable('exchange_rates')) {
            $lastRate = DB::table('exchange_rates')->latest('fetched_at')->value('rate');
        }

        $rate = (float) ($lastRate ?: 1);

        DB::table('expenses')->whereNull('amount_local')->update([
            'amount_local' => DB::raw('ROUND(amount_usd * '.$rate.', 2)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'amount_local')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('amount_local');
            });
        }
    }
};
