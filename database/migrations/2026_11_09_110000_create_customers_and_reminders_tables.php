<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('phone', 30);
                $table->string('access_token', 80)->unique();
                $table->date('last_visit_at')->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'phone']);
            });
        }

        if (! Schema::hasTable('reminder_subscriptions')) {
            Schema::create('reminder_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('interval_days')->default(15);
                $table->boolean('active')->default(true);
                $table->timestamp('next_reminder_at')->nullable();
                $table->timestamp('last_reminder_at')->nullable();
                $table->foreignId('preferred_barber_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('preferred_service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'active', 'next_reminder_at'], 'reminders_due_index');
            });
        }

        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('endpoint_hash', 64)->unique();
                $table->text('p256dh');
                $table->text('auth');
                $table->string('content_encoding', 20)->default('aesgcm');
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('reminder_logs')) {
            Schema::create('reminder_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('reminder_subscriptions')->nullOnDelete();
                $table->string('channel', 20)->default('webpush');
                $table->string('status', 20)->default('enviado');
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'sent_at']);
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (! Schema::hasColumn('appointments', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->after('company_id')->constrained('customers')->nullOnDelete();
                }

                if (! Schema::hasColumn('appointments', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (Schema::hasColumn('appointments', 'customer_id')) {
                    $table->dropConstrainedForeignId('customer_id');
                }

                if (Schema::hasColumn('appointments', 'completed_at')) {
                    $table->dropColumn('completed_at');
                }
            });
        }

        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('reminder_subscriptions');
        Schema::dropIfExists('customers');
    }
};
