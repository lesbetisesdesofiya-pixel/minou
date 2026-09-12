<?php
/**
 * save_order.php
 * Saves an order from checkout.php and decrements fish variation stock if applicable.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fcm_helper.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insert the order
    $stmt = $pdo->prepare("
        INSERT INTO orders (service_type, client_name, client_phone, neighborhood, table_number, notes, total_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $data['serviceType']   ?? 'emporter',
        $data['name']          ?? null,
        $data['phone']         ?? null,
        $data['neighborhood']  ?? null,
        $data['table']         ?? null,
        $data['notes']         ?? null,
        $data['total']         ?? 0,
    ]);
    $order_id = $pdo->lastInsertId();

    // 2. Insert order items + decrement fish stock
    $insert_item = $pdo->prepare("
        INSERT INTO order_items (order_id, product_name, quantity, price, options_text)
        VALUES (?, ?, ?, ?, ?)
    ");

    // Prepare stock query: find dish_variation by name for Poissons dishes
    $stock_check = $pdo->prepare("
        SELECT dv.id, dv.stock_kg
        FROM dish_variations dv
        JOIN dishes d ON dv.dish_id = d.id
        JOIN categories c ON d.category_id = c.id
        WHERE c.nom = 'Poissons' AND dv.dish_id = ? AND dv.name = ?
        LIMIT 1
    ");

    $stock_decrement = $pdo->prepare("
        UPDATE dish_variations SET stock_kg = GREATEST(0, stock_kg - ?) WHERE id = ?
    ");

    foreach ($data['items'] ?? [] as $item) {
        $options = $item['selectedOptions'] ?? [];
        $options_text = implode(', ', array_map(fn($o) => is_array($o) ? $o['name'] : $o, $options));

        $insert_item->execute([
            $order_id,
            $item['name']      ?? 'Article',
            $item['quantity']  ?? 1,
            $item['itemPrice'] ?? 0,
            $options_text,
        ]);

        // --- Decrement fish stock & increment orders count ---
        $dish_id = $item['id'] ?? null;
        if ($dish_id) {
            // Increment overall dish popularity
            $stmt_pop = $pdo->prepare("UPDATE dishes SET orders_count = orders_count + ? WHERE id = ?");
            $qty = $item['quantity'] ?? 1;
            $stmt_pop->execute([$qty, $dish_id]);

            // Fish stock check
            foreach ($options as $opt) {
                $opt_name = is_array($opt) ? ($opt['name'] ?? '') : $opt;
                if (!$opt_name) continue;

                $stock_check->execute([$dish_id, $opt_name]);
                $variation = $stock_check->fetch();

                if ($variation && $variation['stock_kg'] > 0) {
                     $stock_decrement->execute([$qty, $variation['id']]);
                }
            }
        }
    }

    $pdo->commit();

    // 3. Send FCM Notification
    $fcm_result = FCMHelper::sendOrderNotification($order_id, $data);

    // Log result for debugging
    if (isset($fcm_result['name'])) {
        error_log("[FCM] ✅ Notification envoyée pour commande #$order_id : " . $fcm_result['name']);
    } else {
        error_log("[FCM] ❌ Échec notification pour commande #$order_id : " . json_encode($fcm_result));
    }

    // TEMP DEBUG: include FCM result in response — remove once confirmed working
    echo json_encode([
        'success'    => true,
        'order_id'   => $order_id,
        '_fcm_debug' => $fcm_result,
    ]);

} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

?>
