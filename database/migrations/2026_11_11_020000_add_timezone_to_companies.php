<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'timezone')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('timezone', 64)->default('America/Caracas')->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'timezone')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};
