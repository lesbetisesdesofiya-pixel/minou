<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../middleware/auth.php';

function handle_weekly_menu(array $segments, string $method, $pdo): void {
    $auth   = require_auth();
    $action = $segments[1] ?? ''; // '' or 'update'

    // GET /weekly-menu
    if ($method === 'GET' && $action === '') {
        $week = $_GET['week'] ?? date('W');
        $year = $_GET['year'] ?? date('Y');

        $stmt = $pdo->prepare("
            SELECT wm.*, d.nom, d.description, d.prix, d.image, c.nom as category
            FROM weekly_menu wm
            JOIN dishes d ON wm.dish_id = d.id
            LEFT JOIN categories c ON d.category_id = c.id
            WHERE wm.week_number = ? AND wm.year = ?
            ORDER BY wm.day_of_week
        ");
        $stmt->execute([$week, $year]);
        echo json_encode($stmt->fetchAll());
        return;
    }

    // POST /weekly-menu/update
    if ($method === 'POST' && $action === 'update') {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $items = $body['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            http_response_code(400);
            echo json_encode(['error' => 'items[] requis']);
            return;
        }

        $week = $body['week'] ?? date('W');
        $year = $body['year'] ?? date('Y');

        // Clear existing entries for this week
        $stmt = $pdo->prepare("DELETE FROM weekly_menu WHERE week_number = ? AND year = ?");
        $stmt->execute([$week, $year]);

        // Insert new ones
        $insert = $pdo->prepare("INSERT INTO weekly_menu (dish_id, day_of_week, week_number, year) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            if (empty($item['dish_id']) || empty($item['day_of_week'])) continue;
            $insert->execute([$item['dish_id'], $item['day_of_week'], $week, $year]);
        }

        echo json_encode(['success' => true, 'week' => $week, 'year' => $year, 'count' => count($items)]);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
?>
