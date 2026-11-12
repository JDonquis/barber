<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sales MODIFY payment_currency ENUM('USD', 'VES', 'COP') NOT NULL DEFAULT 'USD'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sales MODIFY payment_currency ENUM('USD', 'VES') NOT NULL DEFAULT 'USD'");
    }
};
