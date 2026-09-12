<?php
/**
 * Auth Middleware - validates Bearer JWT token
 * Returns the decoded payload or sends 401 and exits
 */
require_once __DIR__ . '/../jwt_helper.php';

function require_auth(): array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token manquant ou invalide']);
        exit;
    }

    $payload = jwt_decode($matches[1]);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token expiré ou invalide']);
        exit;
    }

    return $payload;
}

function require_role(string $role, array $payload): void {
    if (($payload['role'] ?? '') !== $role) {
        http_response_code(403);
        echo json_encode(['error' => "Accès réservé au rôle $role"]);
        exit;
    }
}
?>
