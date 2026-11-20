<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('barber_liquidations')) {
            Schema::create('barber_liquidations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
                $table->foreignId('barber_id')->constrained('users')->cascadeOnDelete();
                $table->string('origin', 40)->default('manual');
                $table->unsignedBigInteger('source_closing_id')->nullable()->index();
                $table->enum('period_type', ['diario', 'semanal', 'mensual']);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('total_services_usd', 12, 2)->default(0);
                $table->decimal('total_products_usd', 12, 2)->default(0);
                $table->decimal('total_usd', 12, 2)->default(0);
                $table->decimal('total_ves', 16, 2)->default(0);
                $table->decimal('total_ves_reference', 16, 2)->default(0);
                $table->decimal('barber_commission_usd', 12, 2)->default(0);
                $table->decimal('barber_tips_usd', 12, 2)->default(0);
                $table->decimal('barber_retentions_usd', 12, 2)->default(0);
                $table->unsignedInteger('ticket_count')->default(0);
                $table->decimal('exchange_rate', 14, 4)->default(0);
                $table->decimal('average_rate', 14, 4)->default(0);
                $table->json('details')->nullable();
                $table->enum('status', ['abierto', 'cerrado'])->default('abierto');
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reopened_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'barber_id', 'period_type', 'period_start', 'period_end'], 'barber_liquidations_period_index');
                $table->index(['company_id', 'status', 'closed_at'], 'barber_liquidations_status_index');
            });
        }

        $this->migrateBarberClosings();
        $this->importClosedGeneralClosings();
        $this->removeBarberColumnFromClosings();
    }

    public function down(): void
    {
        if (Schema::hasTable('closings') && ! Schema::hasColumn('closings', 'barber_id')) {
            Schema::table('closings', function (Blueprint $table) {
                $table->foreignId('barber_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        Schema::dropIfExists('barber_liquidations');
    }

    protected function migrateBarberClosings(): void
    {
        if (! Schema::hasTable('closings') || ! Schema::hasColumn('closings', 'barber_id')) {
            return;
        }

        $rows = DB::table('closings')
            ->whereNotNull('barber_id')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('barber_liquidations')->insert([
                'company_id' => $row->company_id,
                'barber_id' => $row->barber_id,
                'origin' => 'legacy_barber_closing',
                'source_closing_id' => $row->id,
                'period_type' => $row->period_type,
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
                'total_services_usd' => $row->total_services_usd,
                'total_products_usd' => $row->total_products_usd,
                'total_usd' => $row->total_usd,
                'total_ves' => $row->total_ves,
                'total_ves_reference' => $row->total_ves_reference ?? 0,
                'barber_commission_usd' => $row->barber_commission_usd,
                'barber_tips_usd' => $row->barber_tips_usd ?? 0,
                'barber_retentions_usd' => $row->barber_retentions_usd ?? 0,
                'ticket_count' => $row->ticket_count,
                'exchange_rate' => $row->exchange_rate,
                'average_rate' => $row->average_rate ?? 0,
                'details' => $row->details,
                'status' => $row->status,
                'closed_by' => $row->closed_by,
                'closed_at' => $row->closed_at,
                'reopened_by' => $row->reopened_by,
                'reopened_at' => $row->reopened_at,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        DB::table('closings')->whereNotNull('barber_id')->delete();
    }

    protected function importClosedGeneralClosings(): void
    {
        if (! Schema::hasTable('closings')) {
            return;
        }

        $closings = DB::table('closings')
            ->whereNull('barber_id')
            ->where('status', 'cerrado')
            ->orderBy('id')
            ->get();

        foreach ($closings as $closing) {
            $details = $closing->details;

            if (is_string($details)) {
                $details = json_decode($details, true) ?: [];
            }

            $barberRows = is_array($details) ? ($details['barberos'] ?? []) : [];

            foreach ($barberRows as $barberRow) {
                $name = trim((string) ($barberRow['name'] ?? ''));
                $commission = (float) ($barberRow['commission_usd'] ?? 0);
                $tips = (float) ($barberRow['tip_usd'] ?? 0);
                $retentions = (float) ($barberRow['retentions_usd'] ?? 0);
                $earnings = array_key_exists('earnings_usd', $barberRow)
                    ? (float) $barberRow['earnings_usd']
                    : round($commission + $tips - $retentions, 2);

                if ($name === '' || in_array($name, ['Sin barbero', 'Venta tienda'], true) || $earnings <= 0) {
                    continue;
                }

                $barberId = $barberRow['barber_id'] ?? null;

                if (! $barberId) {
                    $barber = DB::table('users')
                        ->where('company_id', $closing->company_id)
                        ->where('role', 'barbero')
                        ->whereNull('deleted_at')
                        ->where('name', $name)
                        ->orderBy('id')
                        ->first();

                    $barberId = $barber?->id;
                }

                if (! $barberId) {
                    continue;
                }

                $exists = DB::table('barber_liquidations')
                    ->where('company_id', $closing->company_id)
                    ->where('barber_id', $barberId)
                    ->where('period_type', $closing->period_type)
                    ->whereDate('period_start', $closing->period_start)
                    ->whereDate('period_end', $closing->period_end)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $services = (float) ($barberRow['total_usd'] ?? 0);
                $total = round($services + $tips, 2);
                $averageRate = (float) ($closing->average_rate ?? 0);
                $exchangeRate = (float) ($closing->exchange_rate ?? 0);

                DB::table('barber_liquidations')->insert([
                    'company_id' => $closing->company_id,
                    'barber_id' => $barberId,
                    'origin' => 'legacy_general_closing',
                    'source_closing_id' => $closing->id,
                    'period_type' => $closing->period_type,
                    'period_start' => $closing->period_start,
                    'period_end' => $closing->period_end,
                    'total_services_usd' => round($services, 2),
                    'total_products_usd' => 0,
                    'total_usd' => $total,
                    'total_ves' => $averageRate > 0
                        ? round($total * $averageRate, 2)
                        : (float) $closing->total_ves,
                    'total_ves_reference' => $exchangeRate > 0 ? round($total * $exchangeRate, 2) : 0,
                    'barber_commission_usd' => $commission,
                    'barber_tips_usd' => $tips,
                    'barber_retentions_usd' => $retentions,
                    'ticket_count' => (int) ($barberRow['tickets'] ?? 0),
                    'exchange_rate' => $exchangeRate,
                    'average_rate' => $averageRate,
                    'details' => json_encode([
                        'barberos' => [$barberRow],
                        'origen' => 'importado_desde_cierre_general',
                    ]),
                    'status' => 'cerrado',
                    'closed_by' => $closing->closed_by,
                    'closed_at' => $closing->closed_at,
                    'reopened_by' => null,
                    'reopened_at' => null,
                    'notes' => 'Importado desde el cierre general #'.$closing->id,
                    'created_at' => $closing->created_at,
                    'updated_at' => $closing->updated_at,
                ]);
            }
        }
    }

    protected function removeBarberColumnFromClosings(): void
    {
        if (! Schema::hasTable('closings') || ! Schema::hasColumn('closings', 'barber_id')) {
            return;
        }

        try {
            Schema::table('closings', function (Blueprint $table) {
                $table->dropIndex('closings_company_period_index');
            });
        } catch (Throwable $e) {
            // Index may not exist.
        }

        if (DB::getDriverName() !== 'sqlite') {
            try {
                Schema::table('closings', function (Blueprint $table) {
                    $table->dropForeign(['barber_id']);
                });
            } catch (Throwable $e) {
                // Databases with a different constraint name.
            }
        }

        Schema::table('closings', function (Blueprint $table) {
            $table->dropColumn('barber_id');
        });

        try {
            Schema::table('closings', function (Blueprint $table) {
                $table->unique(['company_id', 'period_type', 'period_start', 'period_end'], 'closings_company_period_unique');
            });
        } catch (Throwable $e) {
            // Duplicate historical periods or an existing index.
        }
    }
};
