<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('closings') || ! Schema::hasTable('barber_liquidations')) {
            return;
        }

        $closings = DB::table('closings')
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

                $alreadyImported = DB::table('barber_liquidations')
                    ->where('source_closing_id', $closing->id)
                    ->where('barber_id', $barberId)
                    ->exists();

                if ($alreadyImported) {
                    continue;
                }

                $samePeriodExists = DB::table('barber_liquidations')
                    ->where('company_id', $closing->company_id)
                    ->where('barber_id', $barberId)
                    ->where('period_type', $closing->period_type)
                    ->whereDate('period_start', $closing->period_start)
                    ->whereDate('period_end', $closing->period_end)
                    ->exists();

                if ($samePeriodExists) {
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

    public function down(): void
    {
        // Historical data import is intentionally not reverted.
    }
};
