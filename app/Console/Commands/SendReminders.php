<?php

namespace App\Console\Commands;

use App\Services\CustomerReminderService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Envía los recordatorios de corte pendientes por Web Push';

    public function handle(CustomerReminderService $reminders): int
    {
        $stats = $reminders->sendDueReminders();

        $this->info("Enviados: {$stats['sent']} · Omitidos: {$stats['skipped']} · Fallidos: {$stats['failed']}");

        return self::SUCCESS;
    }
}
