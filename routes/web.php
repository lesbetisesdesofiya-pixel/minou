<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\AdminController;

// Web Routes
Route::get('/', [MenuController::class, 'index'])->name('menu');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/orders', [CheckoutController::class, 'store'])->name('orders.store');
Route::post('/orders/{id}/cancel', [CheckoutController::class, 'cancel'])->name('orders.cancel');
Route::post('/orders/{id}/moneyfusion/initiate', [CheckoutController::class, 'initiateMoneyFusion'])->name('orders.moneyfusion.initiate');
Route::get('/orders/{id}/moneyfusion/status', [CheckoutController::class, 'moneyFusionStatus'])->name('orders.moneyfusion.status');
Route::get('/payment/callback', [CheckoutController::class, 'paymentCallback'])->name('payment.callback');
Route::post('/payment/webhook', [CheckoutController::class, 'paymentWebhook'])->name('payment.webhook');
Route::get('/track', [TrackController::class, 'show'])->name('track');

// Admin Routes
Route::get('/admin-login', [AdminController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin-login', [AdminController::class, 'login']);
Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');
Route::post('/admin/delivery-zones', [AdminController::class, 'storeZone'])->name('admin.zones.store');
Route::delete('/admin/delivery-zones/{id}', [AdminController::class, 'destroyZone'])->name('admin.zones.destroy');

