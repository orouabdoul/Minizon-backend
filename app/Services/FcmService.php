<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de notifications push via Firebase Cloud Messaging HTTP v1 (OAuth2).
 *
 * Nécessite un fichier Service Account JSON Firebase dans storage/app/.
 * Configurer FCM_PROJECT_ID et FCM_CREDENTIALS_PATH dans .env.
 *
 * Usage :
 *   app(FcmService::class)->send($token, 'Titre', 'Corps', ['key' => 'value']);
 */
class FcmService
{
    private string $projectId;
    private string $credentialsPath;

    public function __construct()
    {
        $this->projectId       = config('fcm.project_id', '');
        $this->credentialsPath = config('fcm.credentials_path', '');
    }

    /**
     * Envoie une notification push à un seul appareil.
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (empty($this->projectId) || empty($token)) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return false;
        }

        try {
            $response = Http::withToken($accessToken)
                ->post($this->apiUrl(), [
                    'message' => $this->buildMessage($token, $title, $body, $data),
                ]);

            if (! $response->successful()) {
                Log::warning('FCM v1 send failed', [
                    'token'  => substr($token, 0, 20) . '...',
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->successful();

        } catch (\Throwable $e) {
            Log::error('FCM v1 exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Envoie la même notification à plusieurs appareils (un appel par token — FCM v1 n'a plus de multicast).
     */
    public function sendToMultiple(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($this->projectId) || empty($tokens)) {
            return;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return;
        }

        foreach (array_filter(array_unique($tokens)) as $token) {
            try {
                Http::withToken($accessToken)
                    ->post($this->apiUrl(), [
                        'message' => $this->buildMessage($token, $title, $body, $data),
                    ]);
            } catch (\Throwable $e) {
                Log::error('FCM v1 batch exception', ['error' => $e->getMessage()]);
            }
        }
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function apiUrl(): string
    {
        return "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
    }

    private function buildMessage(string $token, string $title, string $body, array $data): array
    {
        // Toutes les valeurs data doivent être des strings pour FCM v1
        $stringData = array_map('strval', $data);

        return [
            'token'        => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'data'    => $stringData,
            'android' => [
                'priority'     => 'high',
                'notification' => [
                    'sound' => 'default',
                    'icon'  => config('fcm.icon'),
                    'color' => config('fcm.color'),
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => ['sound' => 'default'],
                ],
            ],
        ];
    }

    /**
     * Obtient un access token OAuth2 depuis le service account, mis en cache 55 min.
     */
    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_v1_access_token', 3300, function () {
            if (! file_exists($this->credentialsPath)) {
                Log::error('FCM v1 : fichier de credentials introuvable', ['path' => $this->credentialsPath]);
                return null;
            }

            $credentials = json_decode(file_get_contents($this->credentialsPath), true);

            if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
                Log::error('FCM v1 : credentials invalides (client_email ou private_key manquant)');
                return null;
            }

            try {
                $jwt = $this->buildJwt($credentials['client_email'], $credentials['private_key']);

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);

                if (! $response->successful()) {
                    Log::error('FCM v1 : échec OAuth2', ['body' => $response->body()]);
                    return null;
                }

                return $response->json('access_token');

            } catch (\Throwable $e) {
                Log::error('FCM v1 : exception OAuth2', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Construit un JWT signé RS256 pour l'échange OAuth2 service account.
     */
    private function buildJwt(string $clientEmail, string $privateKey): string
    {
        $now = time();

        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signInput = "{$header}.{$payload}";

        openssl_sign($signInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$signInput}." . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
