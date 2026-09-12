<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../middleware/auth.php';

function handle_menu(array $segments, string $method, $pdo): void {
    $auth = require_auth();
    $id   = is_numeric($segments[2] ?? '') ? (int)$segments[2] : null; // /menu/items/{id}

    // GET /menu/items
    if ($method === 'GET' && !$id) {
        $where  = [];
        $params = [];

        if (!empty($_GET['type'])) {
            $where[]  = "d.menu_type = ?";
            $params[] = $_GET['type'];
        }
        if (!empty($_GET['category'])) {
            $where[]  = "c.nom = ?";
            $params[] = $_GET['category'];
        }
        if (isset($_GET['active'])) {
            $where[]  = "d.active = ?";
            $params[] = (int)$_GET['active'];
        }

        $sql = "SELECT d.*, c.nom as category FROM dishes d 
                LEFT JOIN categories c ON d.category_id = c.id";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY c.nom, d.nom";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dishes = $stmt->fetchAll();

        // Attach variations
        foreach ($dishes as &$dish) {
            $vs = $pdo->prepare("SELECT id, name, price, group_name, stock_kg FROM dish_variations WHERE dish_id = ?");
            $vs->execute([$dish['id']]);
            $dish['variations'] = $vs->fetchAll();
        }
        echo json_encode($dishes);
        return;
    }

    // POST /menu/items
    if ($method === 'POST' && !$id) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $required = ['nom', 'prix', 'category_id', 'menu_type'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Champ requis: $field"]);
                return;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO dishes (nom, description, prix, category_id, image, menu_type, active)
                               VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $body['nom'],
            $body['description'] ?? '',
            $body['prix'],
            $body['category_id'],
            $body['image'] ?? null,
            $body['menu_type'],
        ]);
        $new_id = $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $new_id]);
        return;
    }

    // PUT /menu/items/{id}
    if ($method === 'PUT' && $id) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [];

        $updatable = ['nom', 'description', 'prix', 'image', 'active', 'menu_type', 'category_id'];
        foreach ($updatable as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = ?";
                $params[] = $body[$f];
            }
        }

        if (!$fields) {
            http_response_code(400);
            echo json_encode(['error' => 'Aucun champ à modifier']);
            return;
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE dishes SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Plat introuvable']);
            return;
        }
        echo json_encode(['success' => true, 'id' => $id]);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
?>
