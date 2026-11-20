<?php

namespace App\Services;

use App\Models\QrLoginToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QrLoginService
{
    public const TTL_MINUTES = 10;

    public const SESSION_KEY = 'qr_login_token';

    /**
     * Return the active QR token for the user, creating a new one when needed.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    public function tokenFor(User $user): array
    {
        $stored = session(self::SESSION_KEY);

        if (is_array($stored) && isset($stored['id'], $stored['token'])) {
            $record = QrLoginToken::query()
                ->whereKey($stored['id'])
                ->where('user_id', $user->id)
                ->first();

            if ($record && $record->isUsable()) {
                return [
                    'token' => $stored['token'],
                    'expires_at' => $record->expires_at,
                ];
            }
        }

        return $this->issue($user);
    }

    /**
     * Invalidate previous QR tokens and create a fresh one.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    public function issue(User $user): array
    {
        QrLoginToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $rawToken = Str::random(64);

        $record = QrLoginToken::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        session([self::SESSION_KEY => [
            'id' => $record->id,
            'token' => $rawToken,
        ]]);

        return [
            'token' => $rawToken,
            'expires_at' => $record->expires_at,
        ];
    }
}
