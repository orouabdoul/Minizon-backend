<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de notifications push via Firebase Cloud Messaging (Legacy HTTP API).
 *
 * Usage :
 *   app(FcmService::class)->send($token, 'Titre', 'Corps', ['trip_uuid' => $uuid]);
 */
class FcmService
{
    private string $url;
    private string $serverKey;

    public function __construct()
    {
        $this->url       = config('fcm.url');
        $this->serverKey = config('fcm.server_key', '');
    }

    /**
     * Envoie une notification push à un seul appareil.
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (empty($this->serverKey) || empty($token)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type'  => 'application/json',
            ])->post($this->url, [
                'to'           => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                    'icon'  => config('fcm.icon'),
                    'color' => config('fcm.color'),
                ],
                'data'     => $data,
                'priority' => 'high',
            ]);

            if (! $response->successful()) {
                Log::warning('FCM send failed', [
                    'token'  => substr($token, 0, 20) . '...',
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->successful();

        } catch (\Throwable $e) {
            Log::error('FCM exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Envoie la même notification à plusieurs appareils (max 500 par lot).
     */
    public function sendToMultiple(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($this->serverKey) || empty($tokens)) {
            return;
        }

        foreach (array_chunk(array_filter($tokens), 500) as $chunk) {
            try {
                Http::withHeaders([
                    'Authorization' => 'key=' . $this->serverKey,
                    'Content-Type'  => 'application/json',
                ])->post($this->url, [
                    'registration_ids' => array_values($chunk),
                    'notification'     => [
                        'title' => $title,
                        'body'  => $body,
                        'sound' => 'default',
                        'icon'  => config('fcm.icon'),
                        'color' => config('fcm.color'),
                    ],
                    'data'     => $data,
                    'priority' => 'high',
                ]);
            } catch (\Throwable $e) {
                Log::error('FCM batch exception', ['error' => $e->getMessage()]);
            }
        }
    }
}
