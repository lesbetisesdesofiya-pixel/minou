<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../middleware/auth.php';

function handle_delivery(array $segments, string $method, $pdo): void {
    $auth = require_auth();

    $sub      = $segments[1] ?? ''; // 'ready' | 'assign' | '{order_id}'
    $action   = $segments[2] ?? ''; // 'complete'

    // GET /delivery/ready
    if ($method === 'GET' && $sub === 'ready') {
        $stmt = $pdo->query("
            SELECT o.*, dp.first_name as driver_first, dp.last_name as driver_last
            FROM orders o
            LEFT JOIN delivery_persons dp ON o.assigned_driver_id = dp.id
            WHERE o.status = 'READY_FOR_PICKUP'
            ORDER BY o.created_at ASC
        ");
        echo json_encode($stmt->fetchAll());
        return;
    }

    // POST /delivery/assign
    if ($method === 'POST' && $sub === 'assign') {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $order_id  = $body['order_id']  ?? null;
        $driver_id = $body['driver_id'] ?? null;

        if (!$order_id || !$driver_id) {
            http_response_code(400);
            echo json_encode(['error' => 'order_id et driver_id requis']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE orders SET assigned_driver_id = ?, status = 'PREPARING' WHERE id = ?");
        $stmt->execute([$driver_id, $order_id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande introuvable']);
            return;
        }
        echo json_encode(['success' => true, 'order_id' => $order_id, 'driver_id' => $driver_id]);
        return;
    }

    // PATCH /delivery/{order_id}/complete
    if ($method === 'PATCH' && is_numeric($sub) && $action === 'complete') {
        $order_id = (int)$sub;
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
        $stmt->execute([$order_id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande introuvable']);
            return;
        }
        echo json_encode(['success' => true, 'order_id' => $order_id, 'status' => 'delivered']);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
?>
