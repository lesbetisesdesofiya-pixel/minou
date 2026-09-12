<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\DishVariation;
use App\Models\DeliveryPerson;
use App\Services\MenuService;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApiController extends Controller
{
    // ─── JWT Helpers ────────────────────────────────────────────────────────────
    // Secret chargé paresseusement : les endpoints publics marchent sans JWT configuré,
    // toute opération de signature/vérification exige JWT_SECRET défini dans .env.
    private function jwtSecret(): string
    {
        $secret = (string) config('services.jwt.secret', '');
        if ($secret === '' || $secret === 'change-me-in-production') {
            throw new \RuntimeException('JWT_SECRET manquant : définissez une valeur forte dans .env');
        }
        return $secret;
    }

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
        $sig     = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$body}", $this->jwtSecret(), true));
        return "{$header}.{$body}.{$sig}";
    }

    private function jwtDecode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $body, $sig] = $parts;
        $expected = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$body}", $this->jwtSecret(), true));
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
        if ($request->limit)  $query->limit((int) $request->limit);

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

        $driver = DeliveryPerson::find($driverId);
        if (!$driver || !$driver->active || $driver->suspended) {
            return response()->json(['error' => 'Livreur indisponible'], 422);
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

    // ═══════════════════════════════════════════════════════════════════
    //  CATALOGUE & COMMANDE CLIENT (publics — même comportement que le web)
    // ═══════════════════════════════════════════════════════════════════

    // ─── GET /api/menu ───────────────────────────────────────────────────────
    // Catalogue complet + barème des frais (pour app mobile / frontend externe)
    public function menu(Request $request)
    {
        $catalog = (new MenuService())->catalog();
        $pricing = new PricingService();

        return response()->json([
            'products'   => $catalog['productsData'],
            'garnitures' => $catalog['garnituresGlobal'],
            'fees'       => [
                'service_rate'      => $pricing->feeRate(),
                'service_label'     => 'Frais de service (' . rtrim(rtrim(number_format($pricing->feeRate() * 100, 2, ',', ''), '0'), ',') . '%)',
                'delivery_default'  => $pricing->defaultDeliveryFee(),
            ],
            'delivery_zones' => $pricing->zonesMap(),
        ]);
    }

    // ─── POST /api/quote ─────────────────────────────────────────────────────
    // Devis sans créer de commande. Body JSON :
    // { items: [{itemPrice, quantity}], service_type: "livraison|emporter", neighborhood }
    public function quote(Request $request)
    {
        $pricing = new PricingService();
        $quote   = $pricing->quote(
            $request->input('items', []),
            $request->input('service_type', 'emporter'),
            $request->input('neighborhood')
        );

        return response()->json(array_merge($quote, [
            'fee_rate'     => $pricing->feeRate(),
            'currency'     => 'F',
            'service_type' => $request->input('service_type', 'emporter'),
        ]));
    }

    // ─── POST /api/orders ────────────────────────────────────────────────────
    // Créer une commande (statut "En attente de paiement"). Body JSON :
    // { serviceType, name, phone, neighborhood?, table?, notes?, paymentMethod?, items: [{id?, name, quantity, itemPrice, selectedOptions?}] }
    public function storeOrder(Request $request)
    {
        $data = $request->json()->all() ?: $request->all();

        try {
            $result = (new OrderService())->create($data);
            $order  = $result['order'];

            return response()->json([
                'success'      => true,
                'order_id'     => $order->id,
                'subtotal'     => $result['subtotal'],
                'service_fee'  => $result['service_fee'],
                'delivery_fee' => $result['delivery_fee'],
                'total'        => $result['total'],
                'status'       => $order->status,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    // ─── POST /api/orders/{id}/cancel ────────────────────────────────────────
    public function cancelOrder(Request $request, int $id)
    {
        return app(CheckoutController::class)->cancel($request, $id);
    }

    // ─── POST /api/orders/{id}/moneyfusion/initiate ──────────────────────────
    // Body JSON : { numeroSend, nomclient } → { payment_url, token }
    public function initiatePayment(Request $request, int $id)
    {
        return app(CheckoutController::class)->initiateMoneyFusion($request, $id);
    }

    // ─── GET /api/orders/{id}/moneyfusion/status ─────────────────────────────
    public function paymentStatus(Request $request, int $id)
    {
        return app(CheckoutController::class)->moneyFusionStatus($request, $id);
    }

    // ─── GET /api/orders/{id}/tracking ───────────────────────────────────────
    // Suivi public d'une commande (même données que la page /track)
    public function tracking(Request $request, int $id)
    {
        $order = Order::with(['items', 'driver'])->find($id);
        if (!$order) return response()->json(['error' => 'Commande introuvable'], 404);

        return response()->json([
            'id'             => $order->id,
            'status'         => $order->status,
            'service_type'   => $order->service_type,
            'client_name'    => $order->client_name,
            'client_phone'   => $order->client_phone,
            'neighborhood'   => $order->neighborhood,
            'table_number'   => $order->table_number,
            'subtotal'       => $order->subtotal_amount,
            'service_fee'    => $order->service_fee,
            'delivery_fee'   => $order->delivery_fee,
            'total'          => $order->total_amount,
            'driver_first'   => $order->driver?->first_name,
            'driver_last'    => $order->driver?->last_name,
            'created_at'     => $order->created_at,
            'items'          => $order->items->map(fn($i) => [
                'product_name' => $i->product_name,
                'quantity'     => $i->quantity,
                'price'        => $i->price,
                'options'      => $i->options_text,
            ]),
        ]);
    }

    // ─── GET /api/delivery/zones (public) ────────────────────────────────────
    public function deliveryZones(Request $request)
    {
        $pricing = new PricingService();

        return response()->json([
            'default_fee' => $pricing->defaultDeliveryFee(),
            'zones'       => DeliveryZone::where('active', true)->orderBy('quartier')->get(['id', 'quartier', 'fee']),
        ]);
    }

    // ─── POST /api/delivery/zones (admin JWT) ────────────────────────────────
    public function storeZone(Request $request)
    {
        $this->requireAuth($request);

        $validated = $request->validate([
            'quartier' => 'required|string|max:100|unique:delivery_zones,quartier',
            'fee'      => 'required|integer|min:0|max:100000',
        ]);

        $zone = DeliveryZone::create([
            'quartier' => trim($validated['quartier']),
            'fee'      => (int) $validated['fee'],
            'active'   => true,
        ]);

        return response()->json(['success' => true, 'zone' => $zone], 201);
    }

    // ─── DELETE /api/delivery/zones/{id} (admin JWT) ─────────────────────────
    public function destroyZone(Request $request, int $id)
    {
        $this->requireAuth($request);

        $deleted = DeliveryZone::where('id', $id)->delete();
        if (!$deleted) return response()->json(['error' => 'Zone introuvable'], 404);

        return response()->json(['success' => true]);
    }

    // ─── POST /api/payment/webhook ( MoneyFusion → nous, sans auth) ──────────
    public function paymentWebhook(Request $request)
    {
        return app(CheckoutController::class)->paymentWebhook($request);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ADMINISTRATION (JWT admin requis)
    // ═══════════════════════════════════════════════════════════════════

    // ─── GET /api/admin/stats ────────────────────────────────────────────────
    // Chiffres du dashboard : commandes, CA, frais, top récent
    public function adminStats(Request $request)
    {
        $this->requireAuth($request);

        return response()->json([
            'total_orders'     => Order::count(),
            'total_revenue'    => Order::where('status', '!=', 'cancelled')->sum('total_amount'),
            'service_fees'     => Order::where('status', '!=', 'cancelled')->sum('service_fee'),
            'delivery_fees'    => Order::where('status', '!=', 'cancelled')->sum('delivery_fee'),
            'pending_orders'   => Order::where('status', 'pending')->count(),
            'awaiting_payment' => Order::where('status', 'En attente de paiement')->count(),
            'paid_orders'      => Order::where('status', 'Payée')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'recent_orders'    => Order::orderByDesc('created_at')->limit(10)->get(),
        ]);
    }

    // ─── GET /api/delivery/persons ───────────────────────────────────────────
    public function deliveryPersons(Request $request)
    {
        $this->requireAuth($request);

        return response()->json(
            DeliveryPerson::orderBy('first_name')->orderBy('last_name')->get()
        );
    }

    // ─── POST /api/delivery/persons ──────────────────────────────────────────
    public function storePerson(Request $request)
    {
        $this->requireAuth($request);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:150|unique:delivery_persons,email',
            'phone'      => 'nullable|string|max:20',
        ]);

        $person = DeliveryPerson::create(array_merge($validated, ['active' => true]));

        return response()->json(['success' => true, 'driver' => $person], 201);
    }

    // ─── PATCH /api/delivery/persons/{id} ────────────────────────────────────
    // Body : { first_name?, last_name?, phone?, active?, suspended? }
    public function updatePerson(Request $request, int $id)
    {
        $this->requireAuth($request);

        $person = DeliveryPerson::find($id);
        if (!$person) return response()->json(['error' => 'Livreur introuvable'], 404);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'phone'      => 'nullable|string|max:20',
            'active'     => 'sometimes|boolean',
            'suspended'  => 'sometimes|boolean',
        ]);

        $person->update($validated);

        return response()->json(['success' => true, 'driver' => $person->fresh()]);
    }

    // ─── GET /api/admin/menu ─────────────────────────────────────────────────
    // Tous les plats (actifs + inactifs) pour gestion
    public function adminMenu(Request $request)
    {
        $this->requireAuth($request);

        $dishes = Dish::with('category')->orderBy('nom')->get()->map(fn($d) => [
            'id'           => (int) $d->id,
            'nom'          => $d->nom,
            'prix'         => (float) $d->prix,
            'category'     => $d->category?->nom ?? '',
            'menu_type'    => $d->menu_type,
            'active'       => (bool) $d->active,
            'orders_count' => (int) $d->orders_count,
        ]);

        return response()->json($dishes);
    }

    // ─── PATCH /api/dishes/{id} ──────────────────────────────────────────────
    // Body : { active } — activer/désactiver un plat au menu
    public function updateDish(Request $request, int $id)
    {
        $this->requireAuth($request);

        $dish = Dish::find($id);
        if (!$dish) return response()->json(['error' => 'Plat introuvable'], 404);

        $validated = $request->validate(['active' => 'required|boolean']);
        $dish->update(['active' => $validated['active']]);

        return response()->json(['success' => true, 'dish_id' => $dish->id, 'active' => (bool) $dish->fresh()->active]);
    }
}
