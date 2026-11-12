<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'duration_minutes')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unsignedInteger('duration_minutes')->default(30)->after('price');
            });
        }

        if (! Schema::hasTable('appointment_schedules')) {
            Schema::create('appointment_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('barber_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'day_of_week']);
            });
        }

        if (! Schema::hasTable('appointment_days_off')) {
            Schema::create('appointment_days_off', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('barber_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('date');
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'date']);
            });
        }

        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('barber_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->string('service_name');
                $table->unsignedInteger('duration_minutes')->default(30);
                $table->string('client_name');
                $table->string('client_phone', 30);
                $table->string('client_email')->nullable();
                $table->date('date');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('status', 20)->default('pendiente');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'date']);
                $table->index(['company_id', 'barber_id', 'date']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_days_off');
        Schema::dropIfExists('appointment_schedules');

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'duration_minutes')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('duration_minutes');
            });
        }
    }
};
