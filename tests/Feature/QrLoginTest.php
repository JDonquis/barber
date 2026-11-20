<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\QrLoginToken;
use App\Models\User;
use App\Services\QrLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QrLoginTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::create([
            'name' => 'Barbería QR',
            'slug' => 'barberia-qr',
            'active' => true,
        ]);
    }

    private function user(Company $company, string $role = User::ROLE_ADMIN): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => 'Usuario QR',
            'email' => str()->lower(str()->random(12)).'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'active' => true,
        ]);
    }

    public function test_settings_page_shows_a_qr_login_token(): void
    {
        $company = $this->company();
        $admin = $this->user($company);

        $this->actingAs($admin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Compartir acceso por QR');

        $token = session(QrLoginService::SESSION_KEY.'.token');

        $this->assertNotNull($token);
        $this->assertDatabaseHas('qr_login_tokens', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_qr_token_logs_in_the_owner_on_another_device(): void
    {
        $company = $this->company();
        $admin = $this->user($company);

        $this->actingAs($admin)->get(route('settings.edit'));
        $token = session(QrLoginService::SESSION_KEY.'.token');

        Auth::guard('web')->logout();

        $this->get(route('qr-login.consume', $token))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(
            QrLoginToken::query()->where('user_id', $admin->id)->first()->used_at
        );
    }

    public function test_qr_token_can_only_be_used_once(): void
    {
        $company = $this->company();
        $admin = $this->user($company);

        $this->actingAs($admin)->get(route('settings.edit'));
        $token = session(QrLoginService::SESSION_KEY.'.token');

        $this->get(route('qr-login.consume', $token))
            ->assertRedirect(route('dashboard'));

        Auth::guard('web')->logout();

        $this->get(route('qr-login.consume', $token))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_expired_qr_token_is_rejected(): void
    {
        $company = $this->company();
        $admin = $this->user($company);

        $token = app(QrLoginService::class)->issue($admin)['token'];

        QrLoginToken::query()
            ->where('user_id', $admin->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->get(route('qr-login.consume', $token))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_use_a_qr_token(): void
    {
        $company = $this->company();
        $admin = $this->user($company);

        $token = app(QrLoginService::class)->issue($admin)['token'];

        $admin->update(['active' => false]);

        $this->get(route('qr-login.consume', $token))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_regenerating_invalidates_the_previous_token(): void
    {
        $company = $this->company();
        $admin = $this->user($company);

        $this->actingAs($admin)->get(route('settings.edit'));
        $oldToken = session(QrLoginService::SESSION_KEY.'.token');

        $this->post(route('settings.qr-login.regenerate'))
            ->assertRedirect(route('settings.edit'));

        $newToken = session(QrLoginService::SESSION_KEY.'.token');

        $this->assertNotSame($oldToken, $newToken);
        $this->assertDatabaseMissing('qr_login_tokens', [
            'token_hash' => hash('sha256', $oldToken),
        ]);
    }
}
