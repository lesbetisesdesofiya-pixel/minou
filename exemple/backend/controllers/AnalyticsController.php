<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../middleware/auth.php';

function handle_analytics(array $segments, string $method, $pdo): void {
    $auth    = require_auth();
    $sub     = $segments[1] ?? '';  // 'revenue' | 'deliveries'
    $subsub  = $segments[2] ?? '';  // 'metrics'

    // GET /analytics/revenue?period=monthly|weekly|daily
    if ($method === 'GET' && $sub === 'revenue') {
        $period = $_GET['period'] ?? 'monthly';

        switch ($period) {
            case 'daily':
                $group = "DATE(created_at)";
                $label = "date";
                break;
            case 'weekly':
                $group = "YEARWEEK(created_at, 1)";
                $label = "week";
                break;
            default: // monthly
                $group = "DATE_FORMAT(created_at, '%Y-%m')";
                $label = "month";
        }

        $stmt = $pdo->query("
            SELECT 
                $group AS `$label`,
                COUNT(*) AS total_orders,
                SUM(total_amount) AS total_revenue,
                AVG(total_amount) AS avg_order_value
            FROM orders
            WHERE status NOT IN ('cancelled')
            GROUP BY $group
            ORDER BY $group DESC
            LIMIT 12
        ");
        $rows = $stmt->fetchAll();

        // Overall totals
        $totals = $pdo->query("
            SELECT COUNT(*) as total_orders, SUM(total_amount) as total_revenue
            FROM orders WHERE status NOT IN ('cancelled')
        ")->fetch();

        echo json_encode([
            'period'  => $period,
            'data'    => $rows,
            'totals'  => $totals,
        ]);
        return;
    }

    // GET /analytics/deliveries/metrics
    if ($method === 'GET' && $sub === 'deliveries' && $subsub === 'metrics') {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) AS total_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'READY_FOR_PICKUP' THEN 1 ELSE 0 END) AS waiting_pickup,
                SUM(CASE WHEN status = 'PREPARING' THEN 1 ELSE 0 END) AS in_progress
            FROM orders
        ");
        $metrics = $stmt->fetch();

        // Per driver stats
        $stmt = $pdo->query("
            SELECT 
                dp.id,
                CONCAT(dp.first_name, ' ', dp.last_name) AS driver_name,
                COUNT(o.id) AS deliveries,
                SUM(o.total_amount) AS revenue_handled
            FROM delivery_persons dp
            LEFT JOIN orders o ON o.assigned_driver_id = dp.id AND o.status = 'delivered'
            WHERE dp.active = 1
            GROUP BY dp.id
            ORDER BY deliveries DESC
        ");
        $metrics['per_driver'] = $stmt->fetchAll();

        echo json_encode($metrics);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
?>
