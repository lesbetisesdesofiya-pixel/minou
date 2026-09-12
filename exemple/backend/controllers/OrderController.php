<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../middleware/auth.php';

function handle_orders(array $segments, string $method, $pdo): void {
    $auth = require_auth();

    $order_id = is_numeric($segments[1] ?? '') ? (int)$segments[1] : null;
    $action   = $segments[2] ?? null; // e.g. 'status'

    // PATCH /orders/{id}/status
    if ($method === 'PATCH' && $order_id && $action === 'status') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $body['status'] ?? '';

        $allowed = ['PREPARING', 'READY_FOR_PICKUP', 'delivered', 'pending'];
        if (!in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Statut invalide', 'allowed' => $allowed]);
            return;
        }

        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande introuvable']);
            return;
        }
        echo json_encode(['success' => true, 'order_id' => $order_id, 'status' => $status]);
        return;
    }

    // GET /orders/{id}
    if ($method === 'GET' && $order_id) {
        $stmt = $pdo->prepare("
            SELECT o.*,
                   dp.first_name as driver_first, dp.last_name as driver_last
            FROM orders o
            LEFT JOIN delivery_persons dp ON o.assigned_driver_id = dp.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Commande introuvable']);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order['items'] = $stmt->fetchAll();

        echo json_encode($order);
        return;
    }

    // GET /orders
    if ($method === 'GET') {
        $where  = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[]  = "o.status = ?";
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['date'])) {
            $where[]  = "DATE(o.created_at) = ?";
            $params[] = $_GET['date'];
        }

        $sql  = "SELECT o.*, dp.first_name as driver_first, dp.last_name as driver_last
                 FROM orders o
                 LEFT JOIN delivery_persons dp ON o.assigned_driver_id = dp.id";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY o.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
}
?>
