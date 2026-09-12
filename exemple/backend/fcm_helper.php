<?php
/**
 * fcm_helper.php
 * Handles Firebase Cloud Messaging (FCM) v1 API notification sending.
 * Requires OpenSSL extension for RS256 signing.
 */

class FCMHelper {
    private static $serviceAccountFile = __DIR__ . '/../serviceAccountKey.json';
    private static $tokenUri = 'https://oauth2.googleapis.com/token';
    private static $fcmUrl = 'https://fcm.googleapis.com/v1/projects/mobile-alert-app-f9a2b7/messages:send';

    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function getAccessToken() {
        if (!file_exists(self::$serviceAccountFile)) {
            throw new Exception("Service account file not found.");
        }

        $keyData = json_decode(file_get_contents(self::$serviceAccountFile), true);
        $clientEmail = $keyData['client_email'];
        $privateKey = $keyData['private_key'];

        $header = self::base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $payload = self::base64url_encode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => self::$tokenUri,
            'iat' => $now,
            'exp' => $now + 3600
        ]));

        $signature = '';
        if (!openssl_sign("$header.$payload", $signature, $privateKey, 'SHA256')) {
            throw new Exception("Failed to sign JWT.");
        }
        $signature = self::base64url_encode($signature);
        $jwt = "$header.$payload.$signature";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$tokenUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            return $data['access_token'];
        }

        throw new Exception("Failed to get access token: " . ($data['error_description'] ?? 'Unknown error'));
    }

    public static function sendOrderNotification($orderId, $details) {
        try {
            $accessToken = self::getAccessToken();

            $itemsCount = count($details['items'] ?? []);
            $totalAmount = $details['total'] ?? 0;
            $clientName = $details['name'] ?? 'Client';
            $serviceType = strtoupper($details['serviceType'] ?? 'COMMANDE');

            $message = [
                'message' => [
                    'topic' => 'admin_alerts',
                    'data' => [
                        'title' => "Nouvelle commande #$orderId - $serviceType",
                        'body' => "📦 $itemsCount article(s) • $totalAmount F\n👤 $clientName (" . ($details['phone'] ?? '-') . ")",
                        'order_id' => (string)$orderId,
                        'service_type' => (string)($details['serviceType'] ?? ''),
                        'name' => (string)$clientName,
                        'phone' => (string)($details['phone'] ?? ''),
                        'neighborhood' => (string)($details['neighborhood'] ?? ''),
                        'table_number' => (string)($details['table'] ?? ''),
                        'notes' => (string)($details['notes'] ?? ''),
                        'total' => (string)$totalAmount,
                        'items' => json_encode($details['items'] ?? []),
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::$fcmUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

            $response = curl_exec($ch);
            curl_close($ch);

            return json_decode($response, true);
        } catch (Exception $e) {
            error_log("FCM Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
