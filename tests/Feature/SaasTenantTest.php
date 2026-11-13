<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasTenantTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'slug' => str()->slug($name),
            'active' => true,
        ]);
    }

    private function user(Company $company, string $role = User::ROLE_ADMIN): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => $company->name.' '.$role,
            'email' => str()->lower(str()->random(12)).'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'active' => true,
        ]);
    }

    public function test_platform_panel_pages_render(): void
    {
        $super = User::create([
            'company_id' => null,
            'name' => 'Super Admin',
            'email' => 'super-panel@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_super_admin' => true,
            'active' => true,
        ]);

        $company = $this->company('Barbería Visible');

        $this->actingAs($super)->get(route('admin.companies.index'))->assertOk()->assertSee('Barbería Visible');
        $this->actingAs($super)->get(route('admin.companies.create'))->assertOk()->assertSee('Nueva barbería');
        $this->actingAs($super)->get(route('admin.companies.edit', $company))->assertOk()->assertSee('Barbería Visible');
    }

    public function test_super_admin_can_create_a_company_with_its_administrator(): void
    {
        $super = User::create([
            'company_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_super_admin' => true,
            'active' => true,
        ]);

        $response = $this->actingAs($super)->post(route('admin.companies.store'), [
            'name' => 'Barbería Nueva',
            'slug' => 'barberia-nueva',
            'email' => 'hola@nueva.com',
            'admin_name' => 'Encargado',
            'admin_email' => 'encargado@nueva.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
            'active' => '1',
        ]);

        $response->assertRedirect(route('admin.companies.index'));

        $company = Company::query()->where('slug', 'barberia-nueva')->firstOrFail();
        $admin = User::withoutGlobalScope('company')->where('email', 'encargado@nueva.com')->firstOrFail();

        $this->assertSame($company->id, $admin->company_id);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertDatabaseHas('settings', [
            'company_id' => $company->id,
            'key' => 'shop_name',
            'value' => 'Barbería Nueva',
        ]);
    }

    public function test_company_data_is_isolated_between_tenants(): void
    {
        $first = $this->company('Barbería Uno');
        $second = $this->company('Barbería Dos');

        app(TenantManager::class)->set($first);
        Service::create(['name' => 'Corte Uno', 'price' => 10, 'active' => true]);

        app(TenantManager::class)->set($second);
        Service::create(['name' => 'Corte Dos', 'price' => 20, 'active' => true]);
        Service::create(['name' => 'Corte Dos Bis', 'price' => 25, 'active' => true]);

        app(TenantManager::class)->set($first);
        $this->assertSame(1, Service::query()->count());
        $this->assertSame('Corte Uno', Service::query()->first()->name);

        app(TenantManager::class)->set($second);
        $this->assertSame(2, Service::query()->count());
        $this->assertFalse(Service::query()->where('name', 'Corte Uno')->exists());
    }

    public function test_tenant_admin_cannot_access_the_platform_panel(): void
    {
        $company = $this->company('Barbería Privada');
        $admin = $this->user($company);

        $this->actingAs($admin)->get(route('admin.companies.index'))->assertForbidden();
    }

    public function test_company_is_required_to_access_tenant_routes(): void
    {
        $orphan = User::create([
            'company_id' => null,
            'name' => 'Sin Barbería',
            'email' => 'orphan@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $this->actingAs($orphan)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_update_their_password_from_their_profile(): void
    {
        $super = User::create([
            'company_id' => null,
            'name' => 'Super Admin',
            'email' => 'super-profile@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_super_admin' => true,
            'active' => true,
        ]);

        $this->actingAs($super)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('Contraseña actual');

        $this->actingAs($super)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.profile.edit'));

        $this->assertTrue(Hash::check('new-password', $super->refresh()->password));
    }
}
