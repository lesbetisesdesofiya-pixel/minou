<?php
/**
 * jwt_helper.php
 * Provides JWT encode/decode using HMAC-SHA256 (no external dependencies).
 */

define('JWT_SECRET', 'operatogo-resto-secret-2024'); // change this to a strong secret

function jwt_encode(array $payload): string {
    $header  = _b64u_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + 3600 * 24 * 7; // 7 days
    $body    = _b64u_encode(json_encode($payload));
    $sig     = _b64u_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = _b64u_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(_b64u_decode($body), true);
    if (!$payload) return null;
    if (isset($payload['exp']) && $payload['exp'] < time()) return null;
    return $payload;
}

function _b64u_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _b64u_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}
