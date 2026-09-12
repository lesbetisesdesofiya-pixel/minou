<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../middleware/auth.php';

/**
 * Fish Inventory Controller
 * "Fish inventory" = dish_variations for dishes in the 'Poissons' category.
 * These are the selectable fish types (Bar, Dorade, Tilapia...) with individual stock levels.
 */
function handle_inventory(array $segments, string $method, $pdo): void {
    $auth = require_auth();

    $sub = $segments[1] ?? '';  // 'fish'
    $id  = is_numeric($segments[2] ?? '') ? (int)$segments[2] : null; // variation id

    if ($sub !== 'fish') {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        return;
    }

    // GET /inventory/fish
    // Returns all dish_variations that belong to dishes in the 'Poissons' category
    if ($method === 'GET' && !$id) {
        $stmt = $pdo->query("
            SELECT 
                dv.id,
                dv.dish_id,
                dv.name AS type_poisson,
                dv.price,
                dv.group_name,
                dv.stock_kg,
                d.nom AS plat_nom,
                d.active
            FROM dish_variations dv
            JOIN dishes d ON dv.dish_id = d.id
            JOIN categories c ON d.category_id = c.id
            WHERE c.nom = 'Poissons'
            ORDER BY d.nom, dv.name
        ");
        echo json_encode($stmt->fetchAll());
        return;
    }

    // PATCH /inventory/fish/{variation_id}
    // Updates the stock_kg of a specific fish variation
    if ($method === 'PATCH' && $id) {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $stock_kg = $body['stock_kg'] ?? null;

        if ($stock_kg === null || !is_numeric($stock_kg) || $stock_kg < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'stock_kg doit être un nombre >= 0']);
            return;
        }

        // Verify that this variation belongs to a Poissons dish
        $check = $pdo->prepare("
            SELECT dv.id FROM dish_variations dv
            JOIN dishes d ON dv.dish_id = d.id
            JOIN categories c ON d.category_id = c.id
            WHERE dv.id = ? AND c.nom = 'Poissons'
        ");
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Variation de poisson introuvable']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE dish_variations SET stock_kg = ? WHERE id = ?");
        $stmt->execute([(float)$stock_kg, $id]);

        // Return updated variation
        $stmt = $pdo->prepare("
            SELECT dv.id, dv.name AS type_poisson, dv.price, dv.stock_kg, d.nom AS plat_nom
            FROM dish_variations dv
            JOIN dishes d ON dv.dish_id = d.id
            WHERE dv.id = ?
        ");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'variation' => $stmt->fetch()]);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
}
?>
