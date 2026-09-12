<?php
/**
 * api2.php — API Resto Self-Contained
 * Routes: PATH_INFO  → /backend/api2.php/orders
 *         Query param → /backend/api2.php?path=orders
 */

// --- Error handling (JSON) ---
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
});

// --- CORS ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Database ---
$host = '127.0.0.1:3306';
$db   = 'u473966384_menuavepoeo';
$user = 'u473966384_menuavepoeo';
$pass = '1234Opera1234.';
$charset = 'utf8mb4';
$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// --- JWT helpers (HS256 / native PHP, no vendor needed) ---
define('JWT_SECRET', 'your-secret-key-change-this'); // <-- change this!

function jwt_encode(array $payload): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + 3600 * 24 * 7; // 7 days
    $body    = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;
    return $payload;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

// --- Auth middleware ---
function require_auth(): array {
    $headers    = getallheaders() ?: [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $m)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token manquant ou invalide']);
        exit;
    }
    $payload = jwt_decode($m[1]);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token expiré ou invalide']);
        exit;
    }
    return $payload;
}

// --- Route parsing (PATH_INFO first, then ?path=) ---
$path = $_SERVER['PATH_INFO'] ?? '';
if (!$path && !empty($_GET['path'])) {
    $path = '/' . ltrim($_GET['path'], '/');
}
if (!$path) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
}
$base = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH);
$path = str_replace($base, '', $path);
$path = trim($path, '/');

$segments = $path ? explode('/', $path) : [];
$module   = $segments[0] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

// ============================================================
//  ROUTES
// ============================================================

// --- POST /auth/login ---
if ($module === 'auth') {
    if ($method === 'POST' && ($segments[1] ?? '') === 'login') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = trim($body['email']    ?? '');
        $password = trim($body['password'] ?? '');

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Email et mot de passe requis']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Identifiants invalides']);
            exit;
        }

        echo json_encode([
            'token'   => jwt_encode(['user_id' => $user['id'], 'role' => $user['role'] ?? 'RESTAURANT', 'email' => $user['email']]),
            'role'    => $user['role'] ?? 'RESTAURANT',
            'user_id' => $user['id'],
        ]);
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
    exit;
}

// --- ORDERS ---
if ($module === 'orders') {
    require_auth();

    $order_id = is_numeric($segments[1] ?? '') ? (int)$segments[1] : null;
    $action   = $segments[2] ?? '';

    // PATCH /orders/{id}/status
    if ($method === 'PATCH' && $order_id && $action === 'status') {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $status  = $body['status'] ?? '';
        $allowed = ['PREPARING', 'READY_FOR_PICKUP', 'delivered', 'pending'];
        if (!in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Statut invalide', 'allowed' => $allowed]);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'Commande introuvable']); exit; }
        echo json_encode(['success' => true, 'order_id' => $order_id, 'status' => $status]);
        exit;
    }

    // GET /orders/{id}
    if ($method === 'GET' && $order_id) {
        $stmt = $pdo->prepare("SELECT o.*, dp.first_name as driver_first, dp.last_name as driver_last
            FROM orders o LEFT JOIN delivery_persons dp ON o.assigned_driver_id = dp.id WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order) { http_response_code(404); echo json_encode(['error' => 'Commande introuvable']); exit; }
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order['items'] = $stmt->fetchAll();
        echo json_encode($order);
        exit;
    }

    // GET /orders
    if ($method === 'GET') {
        $where = []; $params = [];
        if (!empty($_GET['status'])) { $where[] = 'o.status = ?'; $params[] = $_GET['status']; }
        if (!empty($_GET['date']))   { $where[] = 'DATE(o.created_at) = ?'; $params[] = $_GET['date']; }
        $sql = "SELECT o.*, dp.first_name as driver_first, dp.last_name as driver_last
                FROM orders o LEFT JOIN delivery_persons dp ON o.assigned_driver_id = dp.id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY o.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// --- DELIVERY ---
if ($module === 'delivery') {
    require_auth();
    $sub    = $segments[1] ?? '';
    $action = $segments[2] ?? '';

    if ($method === 'GET' && $sub === 'ready') {
        $stmt = $pdo->query("SELECT o.*, dp.first_name as driver_first, dp.last_name as driver_last
            FROM orders o LEFT JOIN delivery_persons dp ON o.assigned_driver_id = dp.id
            WHERE o.status = 'READY_FOR_PICKUP' ORDER BY o.created_at ASC");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($method === 'POST' && $sub === 'assign') {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $order_id  = $body['order_id']  ?? null;
        $driver_id = $body['driver_id'] ?? null;
        if (!$order_id || !$driver_id) { http_response_code(400); echo json_encode(['error' => 'order_id et driver_id requis']); exit; }
        $stmt = $pdo->prepare("UPDATE orders SET assigned_driver_id = ?, status = 'PREPARING' WHERE id = ?");
        $stmt->execute([$driver_id, $order_id]);
        if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'Commande introuvable']); exit; }
        echo json_encode(['success' => true, 'order_id' => $order_id, 'driver_id' => $driver_id]);
        exit;
    }

    if ($method === 'PATCH' && is_numeric($sub) && $action === 'complete') {
        $oid = (int)$sub;
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
        $stmt->execute([$oid]);
        if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'Commande introuvable']); exit; }
        echo json_encode(['success' => true, 'order_id' => $oid, 'status' => 'delivered']);
        exit;
    }

    http_response_code(404); echo json_encode(['error' => 'Route not found']); exit;
}

// --- MENU ---
if ($module === 'menu') {
    require_auth();
    $id = is_numeric($segments[2] ?? '') ? (int)$segments[2] : null;

    if ($method === 'GET' && !$id) {
        $where = []; $params = [];
        if (!empty($_GET['type']))     { $where[] = 'd.menu_type = ?'; $params[] = $_GET['type']; }
        if (!empty($_GET['category'])) { $where[] = 'c.nom = ?';       $params[] = $_GET['category']; }
        if (isset($_GET['active']))    { $where[] = 'd.active = ?';    $params[] = (int)$_GET['active']; }
        $sql = "SELECT d.*, c.nom as category FROM dishes d LEFT JOIN categories c ON d.category_id = c.id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY c.nom, d.nom';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dishes = $stmt->fetchAll();
        foreach ($dishes as &$dish) {
            $vs = $pdo->prepare("SELECT id, name, price, group_name, stock_kg FROM dish_variations WHERE dish_id = ?");
            $vs->execute([$dish['id']]);
            $dish['variations'] = $vs->fetchAll();
        }
        echo json_encode($dishes);
        exit;
    }

    if ($method === 'POST' && !$id) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['nom', 'prix', 'category_id', 'menu_type'] as $f) {
            if (empty($body[$f])) { http_response_code(400); echo json_encode(['error' => "Champ requis: $f"]); exit; }
        }
        $stmt = $pdo->prepare("INSERT INTO dishes (nom, description, prix, category_id, image, menu_type, active) VALUES (?,?,?,?,?,?,1)");
        $stmt->execute([$body['nom'], $body['description'] ?? '', $body['prix'], $body['category_id'], $body['image'] ?? null, $body['menu_type']]);
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'PUT' && $id) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];
        foreach (['nom','description','prix','image','active','menu_type','category_id'] as $f) {
            if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        }
        if (!$fields) { http_response_code(400); echo json_encode(['error' => 'Aucun champ à modifier']); exit; }
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE dishes SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'Plat introuvable']); exit; }
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    http_response_code(404); echo json_encode(['error' => 'Route not found']); exit;
}

// --- WEEKLY MENU ---
if ($module === 'weekly-menu') {
    require_auth();
    $action = $segments[1] ?? '';

    if ($method === 'GET' && $action === '') {
        $week = $_GET['week'] ?? date('W');
        $year = $_GET['year'] ?? date('Y');
        $stmt = $pdo->prepare("SELECT wm.*, d.nom, d.description, d.prix, d.image, c.nom as category
            FROM weekly_menu wm JOIN dishes d ON wm.dish_id = d.id LEFT JOIN categories c ON d.category_id = c.id
            WHERE wm.week_number = ? AND wm.year = ? ORDER BY wm.day_of_week");
        $stmt->execute([$week, $year]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($method === 'POST' && $action === 'update') {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $items = $body['items'] ?? [];
        if (empty($items) || !is_array($items)) { http_response_code(400); echo json_encode(['error' => 'items[] requis']); exit; }
        $week = $body['week'] ?? date('W');
        $year = $body['year'] ?? date('Y');
        $pdo->prepare("DELETE FROM weekly_menu WHERE week_number = ? AND year = ?")->execute([$week, $year]);
        $ins = $pdo->prepare("INSERT INTO weekly_menu (dish_id, day_of_week, week_number, year) VALUES (?,?,?,?)");
        foreach ($items as $i) { if (!empty($i['dish_id']) && !empty($i['day_of_week'])) $ins->execute([$i['dish_id'], $i['day_of_week'], $week, $year]); }
        echo json_encode(['success' => true, 'week' => $week, 'year' => $year, 'count' => count($items)]);
        exit;
    }

    http_response_code(404); echo json_encode(['error' => 'Route not found']); exit;
}

// --- INVENTORY ---
if ($module === 'inventory') {
    require_auth();
    $sub = $segments[1] ?? '';
    $id  = is_numeric($segments[2] ?? '') ? (int)$segments[2] : null;

    if ($sub !== 'fish') { http_response_code(404); echo json_encode(['error' => 'Route not found']); exit; }

    if ($method === 'GET' && !$id) {
        $stmt = $pdo->query("SELECT dv.id, dv.dish_id, dv.name AS type_poisson, dv.price, dv.group_name, dv.stock_kg, d.nom AS plat_nom, d.active
            FROM dish_variations dv JOIN dishes d ON dv.dish_id = d.id JOIN categories c ON d.category_id = c.id
            WHERE c.nom = 'Poissons' ORDER BY d.nom, dv.name");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($method === 'PATCH' && $id) {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $stock_kg = $body['stock_kg'] ?? null;
        if ($stock_kg === null || !is_numeric($stock_kg) || $stock_kg < 0) { http_response_code(400); echo json_encode(['error' => 'stock_kg requis (>= 0)']); exit; }
        $check = $pdo->prepare("SELECT dv.id FROM dish_variations dv JOIN dishes d ON dv.dish_id = d.id JOIN categories c ON d.category_id = c.id WHERE dv.id = ? AND c.nom = 'Poissons'");
        $check->execute([$id]);
        if (!$check->fetch()) { http_response_code(404); echo json_encode(['error' => 'Variation introuvable']); exit; }
        $pdo->prepare("UPDATE dish_variations SET stock_kg = ? WHERE id = ?")->execute([(float)$stock_kg, $id]);
        $stmt = $pdo->prepare("SELECT dv.id, dv.name AS type_poisson, dv.price, dv.stock_kg, d.nom AS plat_nom FROM dish_variations dv JOIN dishes d ON dv.dish_id = d.id WHERE dv.id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'variation' => $stmt->fetch()]);
        exit;
    }

    http_response_code(405); echo json_encode(['error' => 'Méthode non autorisée']); exit;
}

// --- ANALYTICS ---
if ($module === 'analytics') {
    require_auth();
    $sub    = $segments[1] ?? '';
    $subsub = $segments[2] ?? '';

    if ($method === 'GET' && $sub === 'revenue') {
        $period = $_GET['period'] ?? 'monthly';
        switch ($period) {
            case 'daily':   $group = 'DATE(created_at)';               $label = 'date';  break;
            case 'weekly':  $group = 'YEARWEEK(created_at, 1)';        $label = 'week';  break;
            default:        $group = "DATE_FORMAT(created_at, '%Y-%m')"; $label = 'month';
        }
        $stmt = $pdo->query("SELECT $group AS `$label`, COUNT(*) AS total_orders, SUM(total_amount) AS total_revenue, AVG(total_amount) AS avg_order_value
            FROM orders WHERE status NOT IN ('cancelled') GROUP BY $group ORDER BY $group DESC LIMIT 12");
        $totals = $pdo->query("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_revenue FROM orders WHERE status NOT IN ('cancelled')")->fetch();
        echo json_encode(['period' => $period, 'data' => $stmt->fetchAll(), 'totals' => $totals]);
        exit;
    }

    if ($method === 'GET' && $sub === 'deliveries' && $subsub === 'metrics') {
        $metrics = $pdo->query("SELECT COUNT(*) AS total_deliveries,
            SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status='READY_FOR_PICKUP' THEN 1 ELSE 0 END) AS waiting_pickup,
            SUM(CASE WHEN status='PREPARING' THEN 1 ELSE 0 END) AS in_progress FROM orders")->fetch();
        $metrics['per_driver'] = $pdo->query("SELECT dp.id, CONCAT(dp.first_name,' ',dp.last_name) AS driver_name, COUNT(o.id) AS deliveries, SUM(o.total_amount) AS revenue_handled
            FROM delivery_persons dp LEFT JOIN orders o ON o.assigned_driver_id = dp.id AND o.status='delivered' WHERE dp.active=1 GROUP BY dp.id ORDER BY deliveries DESC")->fetchAll();
        echo json_encode($metrics);
        exit;
    }

    http_response_code(404); echo json_encode(['error' => 'Route not found']); exit;
}

// --- DEFAULT: no route matched ---
http_response_code(404);
echo json_encode([
    'error'      => 'Route not found',
    'base_url'   => 'https://avepozocommande.operatogo.net/backend/api2.php',
    'routes'     => [
        'POST   /auth/login',
        'GET    /orders',
        'GET    /orders/{id}',
        'PATCH  /orders/{id}/status',
        'GET    /delivery/ready',
        'POST   /delivery/assign',
        'PATCH  /delivery/{order_id}/complete',
        'GET    /menu/items',
        'POST   /menu/items',
        'PUT    /menu/items/{id}',
        'GET    /weekly-menu?week={w}&year={y}',
        'POST   /weekly-menu/update',
        'GET    /inventory/fish',
        'PATCH  /inventory/fish/{variation_id}',
        'GET    /analytics/revenue?period=monthly|weekly|daily',
        'GET    /analytics/deliveries/metrics',
    ],
]);
