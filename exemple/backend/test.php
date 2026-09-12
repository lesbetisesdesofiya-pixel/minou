<?php
/**
 * test_fcm_diag.php
 * Upload and visit this file on Hostinger to diagnose FCM issues.
 * DELETE it after use!
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== FCM Diagnostic ===\n\n";

// 1. OpenSSL
echo "1. OpenSSL: " . (extension_loaded('openssl') ? "✅ OK" : "❌ MISSING") . "\n";

// 2. cURL
echo "2. cURL:    " . (extension_loaded('curl') ? "✅ OK" : "❌ MISSING") . "\n";

// 3. serviceAccountKey.json
$keyPath = __DIR__ . '/../serviceAccountKey.json';
if (!file_exists($keyPath)) {
    echo "3. serviceAccountKey.json: ❌ FILE NOT FOUND at $keyPath\n";
} else {
    $key = json_decode(file_get_contents($keyPath), true);
    if (!$key) {
        echo "3. serviceAccountKey.json: ❌ INVALID JSON\n";
    } else {
        echo "3. serviceAccountKey.json: ✅ FOUND (project: " . ($key['project_id'] ?? '?') . ")\n";
        echo "   client_email: " . ($key['client_email'] ?? '?') . "\n";
        echo "   has private_key: " . (!empty($key['private_key']) ? '✅ Yes' : '❌ No') . "\n";
    }
}

// 4. Outbound HTTPS to Google
if (extension_loaded('curl')) {
    $ch = curl_init('https://www.googleapis.com/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    echo "4. Outbound HTTPS to Google: " . ($code > 0 ? "✅ HTTP $code" : "❌ FAILED ($err)") . "\n";
} else {
    echo "4. Outbound HTTPS: ⬛ cURL not available\n";
}

// 5. Try to get a token (real test)
if (extension_loaded('openssl') && extension_loaded('curl') && file_exists($keyPath)) {
    echo "\n5. Testing OAuth2 token fetch...\n";
    require_once __DIR__ . '/fcm_helper.php';
    // We can trigger the static method just for token fetch via reflection
    try {
        $reflection = new ReflectionMethod('FCMHelper', 'getAccessToken');
        $reflection->setAccessible(true);
        $token = $reflection->invoke(null);
        echo "   ✅ Access token obtained (first 20 chars): " . substr($token, 0, 20) . "...\n";
    } catch (Exception $e) {
        echo "   ❌ Token fetch FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n=== END ===\n";
