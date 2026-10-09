<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\StaffController;

// Web Routes
Route::get('/', [MenuController::class, 'index'])->name('menu');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/orders', [CheckoutController::class, 'store'])->name('orders.store');
Route::post('/orders/{id}/cancel', [CheckoutController::class, 'cancel'])->name('orders.cancel');
Route::post('/orders/{id}/moneyfusion/initiate', [CheckoutController::class, 'initiateMoneyFusion'])->name('orders.moneyfusion.initiate');
Route::post('/orders/{id}/moneyfusion/simulate', [CheckoutController::class, 'simulatePayment'])->name('orders.moneyfusion.simulate');
Route::get('/orders/{id}/moneyfusion/status', [CheckoutController::class, 'moneyFusionStatus'])->name('orders.moneyfusion.status');
Route::get('/payment/callback', [CheckoutController::class, 'paymentCallback'])->name('payment.callback');
Route::post('/payment/webhook', [CheckoutController::class, 'paymentWebhook'])->name('payment.webhook');
Route::get('/track', [TrackController::class, 'show'])->name('track');

// Session WebView app mobile : token API -> session web Laravel (throttle anti-brute-force)
Route::post('/mobile/webview-session', [ApiController::class, 'webviewSession'])
    ->middleware('throttle:10,1')
    ->name('mobile.webview_session');

// Note : l'administration web (ex-SPA React /app + Blade admin/staff) est
// supprimée — l'admin vit dans l'app Expo "Opéra Admin" (dossier myopera/).
// Les anciennes URLs /admin-login, /admin/dashboard, /staff* répondent 404.

