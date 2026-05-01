<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/workers-presence', [DashboardController::class, 'workersPresence'])->name('dashboard.workers_presence');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
    Route::get('/products', [DashboardController::class, 'products'])->name('products');
    Route::get('/take-order', [DashboardController::class, 'takeOrder'])->name('take_order');
    Route::get('/recipes', [DashboardController::class, 'recipes'])->name('recipes');
    Route::get('/invoice-import', [DashboardController::class, 'invoiceImport'])->name('invoice_import');
    Route::post('/dashboard/brands', [DashboardController::class, 'addBrand'])->name('dashboard.brands.store');
    Route::post('/dashboard/products', [DashboardController::class, 'addProduct'])->name('dashboard.products.store');
    Route::get('/dashboard/products/image-suggestions', [DashboardController::class, 'imageSuggestions'])->name('dashboard.products.image_suggestions');
    Route::put('/dashboard/products/{productId}', [DashboardController::class, 'updateProduct'])->name('dashboard.products.update');
    Route::delete('/dashboard/products/{productId}', [DashboardController::class, 'deleteProduct'])->name('dashboard.products.delete');
    Route::post('/dashboard/products/{productId}/tobacco-pack-purchases', [DashboardController::class, 'storeTobaccoPackPurchase'])->name('dashboard.products.tobacco_pack_purchases.store');
    Route::put('/dashboard/products/{productId}/tobacco-pack-purchases/{purchaseId}', [DashboardController::class, 'updateTobaccoPackPurchase'])->name('dashboard.products.tobacco_pack_purchases.update');
    Route::delete('/dashboard/products/{productId}/tobacco-pack-purchases/{purchaseId}', [DashboardController::class, 'deleteTobaccoPackPurchase'])->name('dashboard.products.tobacco_pack_purchases.destroy');
    Route::put('/dashboard/products/{productId}/tobacco-pack-inventory/{inventoryId}', [DashboardController::class, 'updateTobaccoPackInventory'])->name('dashboard.products.tobacco_pack_inventory.update');
    Route::post('/dashboard/hookah-recipes', [DashboardController::class, 'addHookahRecipe'])->name('dashboard.hookah_recipes.store');
    Route::post('/dashboard/tables/layout', [DashboardController::class, 'setTableCount'])->name('dashboard.tables.layout');
    Route::post('/dashboard/tables/{tableId}/toggle', [DashboardController::class, 'toggleTable'])->name('dashboard.tables.toggle');
    Route::post('/dashboard/tables/{tableId}/status', [DashboardController::class, 'setTableStatus'])->name('dashboard.tables.status');
    Route::post('/dashboard/tables/{tableId}/reservations', [DashboardController::class, 'createReservation'])->name('dashboard.tables.reservations.store');
    Route::post('/dashboard/orders/items', [DashboardController::class, 'addOrderItem'])->name('dashboard.orders.items.store');
    Route::post('/dashboard/orders/items/{itemId}/quantity', [DashboardController::class, 'updateOrderItemQuantity'])->name('dashboard.orders.items.quantity');
    Route::post('/dashboard/orders/items/{itemId}/status', [DashboardController::class, 'updateOrderItemStatus'])->name('dashboard.orders.items.status');
    Route::post('/dashboard/orders/items/{itemId}/delete', [DashboardController::class, 'deleteOrderItem'])->name('dashboard.orders.items.delete');
    Route::post('/dashboard/orders/ai', [DashboardController::class, 'aiAddOrder'])->name('dashboard.orders.ai');
    Route::post('/dashboard/orders/close', [DashboardController::class, 'closeOrder'])->name('dashboard.orders.close');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
