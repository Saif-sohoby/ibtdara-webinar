<?php

namespace App\Services;

use App\Models\Participant;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    public function sendTo(Participant $user, string $title, string $body, array $extra = []): array
    {
        // Log::info('extra', $extra);
        // Log::info('Preparing to send FCM notification', ['user_id' => $user->id, 'title' => $title]);
        if (empty($user->fcm_token)) {
            return ['ok' => false, 'error' => 'User has no FCM token'];
        }

        $projectId = config('services.fcm.project_id');
        $credentialsPath = config('services.fcm.credentials');

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();

        $token = $client->getAccessToken();
        $accessToken = $token['access_token'] ?? null;

        if (!$accessToken) {
            return ['ok' => false, 'error' => 'Unable to obtain Google access token'];
        }

        $extraAssoc = [];

        foreach ((array) $extra as $k => $v) {
            // Ensure string key; numeric keys will make JSON array (list), so prefix them
            $key = is_int($k) ? 'k_' . $k : (string) $k;

            // Ensure string value; encode arrays/objects
            if (is_array($v) || is_object($v)) {
                $extraAssoc[$key] = json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($v)) {
                $extraAssoc[$key] = $v ? 'true' : 'false';
            } elseif ($v === null) {
                // Skip nulls (FCM wants strings)
                continue;
            } else {
                $extraAssoc[$key] = (string) $v;
            }
        }

        $payload = [
            'message' => array_filter([
                'token' => $user->fcm_token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                // Only include data if non-empty (avoid sending an empty array)
                'data' => !empty($extraAssoc) ? $extraAssoc : null,
            ], fn($v) => $v !== null),
        ];

        $res = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        // Log::info('FCM send response', ['status' => $res->status(), 'body' => $res->body()]);

        if ($res->successful()) {
            return ['ok' => true, 'response' => $res->json()];
        }

        return ['ok' => false, 'status' => $res->status(), 'error' => $res->json()];
    }
}
