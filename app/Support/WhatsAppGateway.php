<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Talks to whatsapp-service/ (the Node program holding the linked-device
 * session). Never throws: a failure comes back as an 'error' string so a
 * dropped connection can't break saving a patient or an invoice.
 */
class WhatsAppGateway
{
    public static function configured(): bool
    {
        return config('app.whatsapp.url') && strlen((string) config('app.whatsapp.token')) >= 32;
    }

    public static function status(): array
    {
        return self::call('get', 'status') + ['state' => 'offline', 'number' => null, 'qr' => null];
    }

    /** @return array ['ok' => true, 'id' => ...] or ['error' => '...'] */
    public static function send(string $number, string $text): array
    {
        return self::call('post', 'send', ['to' => $number, 'text' => $text]);
    }

    public static function logout(): array
    {
        return self::call('post', 'logout');
    }

    private static function call(string $method, string $path, array $data = []): array
    {
        if (!self::configured()) {
            return ['error' => 'WhatsApp is not set up on this site.'];
        }

        try {
            $response = Http::withHeaders(['X-Token' => config('app.whatsapp.token')])
                ->timeout(20)
                ->{$method}(rtrim(config('app.whatsapp.url'), '/') . '/' . $path, $data);

            return $response->json() ?: ['error' => 'The WhatsApp service gave no answer (HTTP ' . $response->status() . ').'];
        } catch (\Throwable $e) {
            return ['error' => 'The WhatsApp service could not be reached.'];
        }
    }
}
