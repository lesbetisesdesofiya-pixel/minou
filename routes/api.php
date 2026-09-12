<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

/*
|--------------------------------------------------------------------------
| API Opera Resto — l'app fonctionne intégralement via ces endpoints
| Base URL locale : http://localhost/opera/public/api
|--------------------------------------------------------------------------
| Publics (client / app mobile) : menu, devis, commande, paiement, suivi.
| Protégés (JWT admin, header Authorization: Bearer <token>) : gestion.
| Webhook MoneyFusion : public, appelé par MoneyFusion (POST).
|--------------------------------------------------------------------------
*/

// ─── Index de l'API (découverte) ────────────────────────────────────────────
Route::get('/', function () {
    return response()->json([
        'app'     => 'Opera Resto API',
        'version' => '1.0',
        'base'    => url('/api'),
        'public'  => [
            'GET  /api/menu',
            'GET  /api/delivery/zones',
            'POST /api/quote',
            'POST /api/orders',
            'POST /api/orders/{id}/cancel',
            'POST /api/orders/{id}/moneyfusion/initiate',
            'GET  /api/orders/{id}/moneyfusion/status',
            'GET  /api/orders/{id}/tracking',
            'POST /api/payment/webhook',
            'POST /api/auth/login',
        ],
        'auth_jwt' => [
            'GET    /api/orders',
            'GET    /api/orders/{id}',
            'PATCH  /api/orders/{id}/status',
            'GET    /api/delivery/ready',
            'POST   /api/delivery/assign',
            'PATCH  /api/delivery/{id}/complete',
            'POST   /api/delivery/zones',
            'DELETE /api/delivery/zones/{id}',
            'GET    /api/inventory/fish',
            'PATCH  /api/inventory/fish/{id}',
            'GET    /api/analytics/revenue',
            'GET    /api/analytics/deliveries/metrics',
        ],
    ]);
});
Route::get('/docs', function () {
    return response()->view('api_docs');
});
Route::get('/menu', [ApiController::class, 'menu']);
Route::get('/delivery/zones', [ApiController::class, 'deliveryZones']);

// ─── Devis (public) ──────────────────────────────────────────────────────────
Route::post('/quote', [ApiController::class, 'quote']);

// ─── Commandes client (public, throttlées anti-spam) ───────────────────────────
Route::post('/orders', [ApiController::class, 'storeOrder'])->middleware('throttle:20,1');
Route::post('/orders/{id}/cancel', [ApiController::class, 'cancelOrder']);
Route::post('/orders/{id}/moneyfusion/initiate', [ApiController::class, 'initiatePayment'])->middleware('throttle:20,1');
Route::get('/orders/{id}/moneyfusion/status', [ApiController::class, 'paymentStatus']);
Route::get('/orders/{id}/tracking', [ApiController::class, 'tracking']);

// ─── Webhook MoneyFusion (public, serveur-à-serveur) ─────────────────────────
Route::post('/payment/webhook', [ApiController::class, 'paymentWebhook']);

// ─── Auth admin (public, délivre le JWT — anti-brute-force) ───────────────────
Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:10,1');

// ─── Gestion (JWT admin requis) ──────────────────────────────────────────────
Route::get('/admin/stats', [ApiController::class, 'adminStats']);
Route::get('/admin/menu', [ApiController::class, 'adminMenu']);
Route::patch('/dishes/{id}', [ApiController::class, 'updateDish']);
Route::get('/orders', [ApiController::class, 'listOrders']);
Route::get('/orders/{id}', [ApiController::class, 'getOrder']);
Route::patch('/orders/{id}/status', [ApiController::class, 'updateOrderStatus']);
Route::get('/delivery/ready', [ApiController::class, 'deliveryReady']);
Route::post('/delivery/assign', [ApiController::class, 'deliveryAssign']);
Route::patch('/delivery/{id}/complete', [ApiController::class, 'deliveryComplete']);
Route::get('/delivery/persons', [ApiController::class, 'deliveryPersons']);
Route::post('/delivery/persons', [ApiController::class, 'storePerson']);
Route::patch('/delivery/persons/{id}', [ApiController::class, 'updatePerson']);
Route::post('/delivery/zones', [ApiController::class, 'storeZone']);
Route::delete('/delivery/zones/{id}', [ApiController::class, 'destroyZone']);
Route::get('/inventory/fish', [ApiController::class, 'inventoryFish']);
Route::patch('/inventory/fish/{id}', [ApiController::class, 'updateFishStock']);
Route::get('/analytics/revenue', [ApiController::class, 'analyticsRevenue']);
Route::get('/analytics/deliveries/metrics', [ApiController::class, 'analyticsDeliveryMetrics']);
