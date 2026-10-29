<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time signed hand-off to the sister app (Aesthetics <-> Dental), so a
 * user with access doesn't log in again. The token carries the user's email,
 * an expiry and a random nonce, HMAC-SHA256 signed with SWITCH_APP_SECRET
 * (identical in both apps). The receiving app checks the signature, expiry
 * and that the nonce hasn't been used, then logs in the active account with
 * the same email.
 */
class AppSwitch
{
    public const TTL_SECONDS = 60;

    public static function enabled(): bool
    {
        return (bool) config('app.switch.url') && strlen((string) config('app.switch.secret')) >= 32;
    }

    public static function allowedFor(?User $user): bool
    {
        return self::enabled() && $user && $user->is_active && $user->can_switch_app;
    }

    /** The other app's base URL, accepting a value that still ends in /login. */
    public static function targetBase(): string
    {
        return rtrim(preg_replace('#/login/?$#', '', (string) config('app.switch.url')), '/');
    }

    public static function makeToken(User $user, ?int $now = null): string
    {
        $payload = self::encode(json_encode([
            'email' => strtolower($user->email),
            'exp' => ($now ?? time()) + self::TTL_SECONDS,
            'nonce' => Str::random(32),
        ]));

        return $payload . '.' . self::sign($payload);
    }

    /**
     * @return string|null the email to log in, or null when the token is
     *         forged, malformed, expired or already used
     */
    public static function verify(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !self::enabled() || !hash_equals(self::sign($parts[0]), $parts[1])) {
            return null;
        }

        $data = json_decode(self::decode($parts[0]), true);
        if (!is_array($data) || empty($data['email']) || empty($data['nonce']) || !is_int($data['exp'] ?? null) || $data['exp'] < time()) {
            return null;
        }

        // One use only: the first redemption claims the nonce.
        if (!Cache::add('app-switch-nonce:' . $data['nonce'], true, self::TTL_SECONDS + 60)) {
            return null;
        }

        return $data['email'];
    }

    private static function sign(string $payload): string
    {
        return self::encode(hash_hmac('sha256', $payload, (string) config('app.switch.secret'), true));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
