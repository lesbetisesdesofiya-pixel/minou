<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../jwt_helper.php';

function handle_auth(array $segments, string $method, $pdo): void {
    if ($method === 'POST' && ($segments[1] ?? '') === 'login') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = $body['email']    ?? '';
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Email et mot de passe requis']);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Identifiants invalides']);
            return;
        }

        $token = jwt_encode([
            'user_id' => $user['id'],
            'role'    => $user['role'] ?? 'RESTAURANT',
            'email'   => $user['email'],
        ]);

        echo json_encode([
            'token'   => $token,
            'role'    => $user['role'] ?? 'RESTAURANT',
            'user_id' => $user['id'],
        ]);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
?>
