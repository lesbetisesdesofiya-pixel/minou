<?php

namespace App\Services;

use App\Models\Order;

class FcmService
{
    private string $serviceAccountFile;
    private string $tokenUri = 'https://oauth2.googleapis.com/token';
    private string $fcmUrl = 'https://fcm.googleapis.com/v1/projects/mobile-alert-app-f9a2b7/messages:send';

    public function __construct()
    {
        $this->serviceAccountFile = base_path('serviceAccountKey.json');
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function getAccessToken(): string
    {
        if (!file_exists($this->serviceAccountFile)) {
            throw new \Exception("Service account file not found.");
        }

        $keyData = json_decode(file_get_contents($this->serviceAccountFile), true);
        $clientEmail = $keyData['client_email'];
        $privateKey = $keyData['private_key'];

        $header  = $this->base64urlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now     = time();
        $payload = $this->base64urlEncode(json_encode([
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud'   => $this->tokenUri,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signature = '';
        if (!openssl_sign("{$header}.{$payload}", $signature, $privateKey, 'SHA256')) {
            throw new \Exception("Failed to sign JWT.");
        }
        $jwt = "{$header}.{$payload}." . $this->base64urlEncode($signature);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->tokenUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]));
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['access_token'])) return $data['access_token'];

        throw new \Exception("Failed to get FCM access token: " . ($data['error_description'] ?? 'Unknown'));
    }

    public function sendOrderNotification(int $orderId, array $details): array
    {
        try {
            $accessToken  = $this->getAccessToken();
            $itemsCount   = count($details['items'] ?? []);
            $totalAmount  = $details['total'] ?? 0;
            $clientName   = $details['name'] ?? 'Client';
            $serviceType  = strtoupper($details['serviceType'] ?? 'COMMANDE');

            $message = [
                'message' => [
                    'topic' => 'admin_alerts',
                    'data'  => [
                        'type'          => 'new_order',
                        'title'         => "Nouvelle commande #{$orderId} - {$serviceType}",
                        'body'          => "📦 {$itemsCount} article(s) • {$totalAmount} F\n👤 {$clientName} (" . ($details['phone'] ?? '-') . ")",
                        'order_id'      => (string)$orderId,
                        'service_type'  => (string)($details['serviceType'] ?? ''),
                        'name'          => (string)$clientName,
                        'phone'         => (string)($details['phone'] ?? ''),
                        'neighborhood'  => (string)($details['neighborhood'] ?? ''),
                        'table_number'  => (string)($details['table'] ?? ''),
                        'notes'         => (string)($details['notes'] ?? ''),
                        'total'         => (string)$totalAmount,
                        'items'         => json_encode($details['items'] ?? []),
                        'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            $response = curl_exec($ch);
            curl_close($ch);

            return json_decode($response, true) ?? [];
        } catch (\Exception $e) {
            error_log("FCM Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ALARME paiement confirmé : notification haute priorité pour l'app Android.
     * - data.type = "paid" → l'app déclenche l'alarme sonore même verrouillée
     * - bloc "notification" en secours (affiché par le système si l'app est tuée)
     * - canal "alarm_channel", priorité haute, visible sur écran verrouillé
     */
    public function sendPaymentConfirmedNotification(Order $order): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $orderId     = $order->id;
            $total       = $order->total_amount;
            $client      = $order->client_name ?: 'Client';

            $message = [
                'message' => [
                    'topic' => 'admin_alerts',
                    'notification' => [
                        'title' => "Paiement reçu : commande #{$orderId}",
                        'body'  => "{$total} F — {$client}",
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id'       => 'alarm_channel',
                            'visibility'       => 'public',
                            'default_sound'    => false,
                            'notification_priority' => 'PRIORITY_MAX',
                        ],
                    ],
                    'data' => [
                        'type'         => 'paid',
                        'title'        => "Paiement reçu : commande #{$orderId}",
                        'body'         => "{$total} F — {$client} (" . ($order->client_phone ?: '-') . ')',
                        'order_id'     => (string) $orderId,
                        'total'        => (string) $total,
                        'name'         => (string) $client,
                        'service_type' => (string) ($order->service_type ?? ''),
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            $response = curl_exec($ch);
            curl_close($ch);

            return json_decode($response, true) ?? [];
        } catch (\Exception $e) {
            error_log("FCM Alarm Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
