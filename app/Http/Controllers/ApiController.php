<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\DishVariation;
use App\Models\DeliveryPerson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApiController extends Controller
{
    // ─── JWT Helpers ────────────────────────────────────────────────────────────
    private string $jwtSecret = 'your-secret-key-change-this';

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function jwtEncode(array $payload): string
    {
        $header  = $this->base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + 3600 * 24 * 7;
        $body    = $this->base64urlEncode(json_encode($payload));
        $sig     = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$body}", $this->jwtSecret, true));
        return "{$header}.{$body}.{$sig}";
    }

    private function jwtDecode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $body, $sig] = $parts;
        $expected = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$body}", $this->jwtSecret, true));
        if (!hash_equals($expected, $sig)) return null;
        $payload = json_decode($this->base64urlDecode($body), true);
        if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;
        return $payload;
    }

    private function requireAuth(Request $request): array
    {
        $authHeader = $request->header('Authorization', '');
        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $m)) {
            abort(response()->json(['error' => 'Token manquant ou invalide'], 401));
        }
        $payload = $this->jwtDecode($m[1]);
        if (!$payload) {
            abort(response()->json(['error' => 'Token expiré ou invalide'], 401));
        }
        return $payload;
    }

    // ─── POST /api/auth/login ────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $email    = trim($request->input('email', ''));
        $password = trim($request->input('password', ''));

        if (!$email || !$password) {
            return response()->json(['error' => 'Email et mot de passe requis'], 400);
        }

        $admin = Admin::where('email', $email)->first();
        if (!$admin || !Hash::check($password, $admin->password)) {
            return response()->json(['error' => 'Identifiants invalides'], 401);
        }

        return response()->json([
            'token'   => $this->jwtEncode(['user_id' => $admin->id, 'role' => $admin->role, 'email' => $admin->email]),
            'role'    => $admin->role,
            'user_id' => $admin->id,
        ]);
    }

    // ─── GET /api/orders ────────────────────────────────────────────────────────
    public function listOrders(Request $request)
    {
        $this->requireAuth($request);

        $query = Order::with('driver')->orderByDesc('created_at');
        if ($request->status) $query->where('status', $request->status);
        if ($request->date)   $query->whereDate('created_at', $request->date);

        $orders = $query->get()->map(fn($o) => array_merge($o->toArray(), [
            'driver_first' => $o->driver?->first_name,
            'driver_last'  => $o->driver?->last_name,
        ]));

        return response()->json($orders);
    }

    // ─── GET /api/orders/{id} ───────────────────────────────────────────────────
    public function getOrder(Request $request, int $id)
    {
        $this->requireAuth($request);

        $order = Order::with(['items', 'driver'])->find($id);
        if (!$order) return response()->json(['error' => 'Commande introuvable'], 404);

        $data = $order->toArray();
        $data['driver_first'] = $order->driver?->first_name;
        $data['driver_last']  = $order->driver?->last_name;
        $data['items']        = $order->items->toArray();

        return response()->json($data);
    }

    // ─── PATCH /api/orders/{id}/status ─────────────────────────────────────────
    public function updateOrderStatus(Request $request, int $id)
    {
        $this->requireAuth($request);

        $allowed = ['PREPARING', 'READY_FOR_PICKUP', 'delivered', 'pending'];
        $status  = $request->input('status', '');

        if (!in_array($status, $allowed)) {
            return response()->json(['error' => 'Statut invalide', 'allowed' => $allowed], 400);
        }

        $rows = Order::where('id', $id)->update(['status' => $status]);
        if (!$rows) return response()->json(['error' => 'Commande introuvable'], 404);

        return response()->json(['success' => true, 'order_id' => $id, 'status' => $status]);
    }

    // ─── GET /api/delivery/ready ────────────────────────────────────────────────
    public function deliveryReady(Request $request)
    {
        $this->requireAuth($request);

        $orders = Order::with('driver')
            ->where('status', 'READY_FOR_PICKUP')
            ->orderBy('created_at')
            ->get()
            ->map(fn($o) => array_merge($o->toArray(), [
                'driver_first' => $o->driver?->first_name,
                'driver_last'  => $o->driver?->last_name,
            ]));

        return response()->json($orders);
    }

    // ─── POST /api/delivery/assign ──────────────────────────────────────────────
    public function deliveryAssign(Request $request)
    {
        $this->requireAuth($request);

        $orderId  = $request->input('order_id');
        $driverId = $request->input('driver_id');

        if (!$orderId || !$driverId) {
            return response()->json(['error' => 'order_id et driver_id requis'], 400);
        }

        $rows = Order::where('id', $orderId)->update(['assigned_driver_id' => $driverId, 'status' => 'PREPARING']);
        if (!$rows) return response()->json(['error' => 'Commande introuvable'], 404);

        return response()->json(['success' => true, 'order_id' => $orderId, 'driver_id' => $driverId]);
    }

    // ─── PATCH /api/delivery/{id}/complete ──────────────────────────────────────
    public function deliveryComplete(Request $request, int $id)
    {
        $this->requireAuth($request);

        $rows = Order::where('id', $id)->update(['status' => 'delivered']);
        if (!$rows) return response()->json(['error' => 'Commande introuvable'], 404);

        return response()->json(['success' => true, 'order_id' => $id, 'status' => 'delivered']);
    }

    // ─── GET /api/inventory/fish ─────────────────────────────────────────────────
    public function inventoryFish(Request $request)
    {
        $this->requireAuth($request);

        $items = DishVariation::join('dishes', 'dish_variations.dish_id', '=', 'dishes.id')
            ->join('categories', 'dishes.category_id', '=', 'categories.id')
            ->where('categories.nom', 'Poissons')
            ->select('dish_variations.id', 'dish_variations.dish_id', 'dish_variations.name as type_poisson',
                     'dish_variations.price', 'dish_variations.group_name', 'dish_variations.stock_kg',
                     'dishes.nom as plat_nom', 'dishes.active')
            ->orderBy('dishes.nom')
            ->orderBy('dish_variations.name')
            ->get();

        return response()->json($items);
    }

    // ─── PATCH /api/inventory/fish/{id} ──────────────────────────────────────────
    public function updateFishStock(Request $request, int $id)
    {
        $this->requireAuth($request);

        $stockKg = $request->input('stock_kg');
        if ($stockKg === null || !is_numeric($stockKg) || $stockKg < 0) {
            return response()->json(['error' => 'stock_kg requis (>= 0)'], 400);
        }

        $variation = DishVariation::join('dishes', 'dish_variations.dish_id', '=', 'dishes.id')
            ->join('categories', 'dishes.category_id', '=', 'categories.id')
            ->where('categories.nom', 'Poissons')
            ->where('dish_variations.id', $id)
            ->select('dish_variations.*')
            ->first();

        if (!$variation) return response()->json(['error' => 'Variation introuvable'], 404);

        $variation->update(['stock_kg' => (float)$stockKg]);

        return response()->json(['success' => true, 'variation' => $variation->fresh()]);
    }

    // ─── GET /api/analytics/revenue ──────────────────────────────────────────────
    public function analyticsRevenue(Request $request)
    {
        $this->requireAuth($request);

        $period = $request->input('period', 'monthly');

        switch ($period) {
            case 'daily':  $group = 'DATE(created_at)';                $label = 'date';  break;
            case 'weekly': $group = 'YEARWEEK(created_at, 1)';         $label = 'week';  break;
            default:       $group = "DATE_FORMAT(created_at, '%Y-%m')"; $label = 'month';
        }

        $data = DB::select("SELECT {$group} AS `{$label}`, COUNT(*) AS total_orders, SUM(total_amount) AS total_revenue, AVG(total_amount) AS avg_order_value FROM orders WHERE status NOT IN ('cancelled') GROUP BY {$group} ORDER BY {$group} DESC LIMIT 12");

        $totals = DB::selectOne("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_revenue FROM orders WHERE status NOT IN ('cancelled')");

        return response()->json(['period' => $period, 'data' => $data, 'totals' => $totals]);
    }

    // ─── GET /api/analytics/deliveries/metrics ──────────────────────────────────
    public function analyticsDeliveryMetrics(Request $request)
    {
        $this->requireAuth($request);

        $metrics = DB::selectOne("SELECT COUNT(*) AS total_deliveries,
            SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status='READY_FOR_PICKUP' THEN 1 ELSE 0 END) AS waiting_pickup,
            SUM(CASE WHEN status='PREPARING' THEN 1 ELSE 0 END) AS in_progress FROM orders");

        $perDriver = DeliveryPerson::select(
                'delivery_persons.id',
                DB::raw("CONCAT(first_name,' ',last_name) AS driver_name"),
                DB::raw('COUNT(orders.id) AS deliveries'),
                DB::raw('SUM(orders.total_amount) AS revenue_handled')
            )
            ->leftJoin('orders', function($j) {
                $j->on('orders.assigned_driver_id', '=', 'delivery_persons.id')
                  ->where('orders.status', 'delivered');
            })
            ->where('delivery_persons.active', 1)
            ->groupBy('delivery_persons.id', 'first_name', 'last_name')
            ->orderByDesc('deliveries')
            ->get();

        return response()->json(array_merge((array)$metrics, ['per_driver' => $perDriver]));
    }
}
