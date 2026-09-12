<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;

// Web Routes
Route::get('/', [MenuController::class, 'index'])->name('menu');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/orders', [CheckoutController::class, 'store'])->name('orders.store');
Route::post('/orders/{id}/cancel', [CheckoutController::class, 'cancel'])->name('orders.cancel');
Route::post('/orders/{id}/validate-payment', [CheckoutController::class, 'validatePayment'])->name('orders.validate_payment');
Route::get('/track', [TrackController::class, 'show'])->name('track');

// Admin Routes
Route::get('/admin-login', [AdminController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin-login', [AdminController::class, 'login']);
Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

// API Routes (matching api2.php)
Route::prefix('api')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login']);
    Route::get('/orders', [ApiController::class, 'listOrders']);
    Route::get('/orders/{id}', [ApiController::class, 'getOrder']);
    Route::patch('/orders/{id}/status', [ApiController::class, 'updateOrderStatus']);
    Route::get('/delivery/ready', [ApiController::class, 'deliveryReady']);
    Route::post('/delivery/assign', [ApiController::class, 'deliveryAssign']);
    Route::patch('/delivery/{id}/complete', [ApiController::class, 'deliveryComplete']);
    Route::get('/inventory/fish', [ApiController::class, 'inventoryFish']);
    Route::patch('/inventory/fish/{id}', [ApiController::class, 'updateFishStock']);
    Route::get('/analytics/revenue', [ApiController::class, 'analyticsRevenue']);
    Route::get('/analytics/deliveries/metrics', [ApiController::class, 'analyticsDeliveryMetrics']);
});

