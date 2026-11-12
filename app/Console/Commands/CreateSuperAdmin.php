<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'saas:super-admin
        {email : Correo del superadministrador}
        {--name= : Nombre a mostrar}
        {--password= : Contraseña (si se omite se genera una temporal)}';

    protected $description = 'Crea o actualiza el superadministrador de la plataforma SaaS';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: Str::password(12, symbols: false));

        $user = User::withoutGlobalScope('company')->updateOrCreate(
            ['email' => $email],
            [
                'company_id' => null,
                'name' => $this->option('name') ?: 'Super Administrador',
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'is_super_admin' => true,
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->info("Superadministrador listo: {$user->email}");

        if (! $this->option('password')) {
            $this->line("Contraseña temporal: {$password}");
        }

        return self::SUCCESS;
    }
}
