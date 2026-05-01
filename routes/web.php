<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Catalog\BrandsController as CatalogBrandsController;
use App\Http\Controllers\Catalog\CategoriesController as CatalogCategoriesController;
use App\Http\Controllers\Catalog\ProductsController as CatalogProductsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Orders\AiOrderController;
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
    Route::get('/products', [CatalogProductsController::class, 'index'])->name('products');
    Route::get('/inventory', [CatalogProductsController::class, 'index'])->name('inventory');
    Route::get('/deliveries', [CatalogProductsController::class, 'index'])->name('deliveries');
    Route::get('/take-order', [DashboardController::class, 'takeOrder'])->name('take_order');
    Route::get('/recipes', [DashboardController::class, 'recipes'])->name('recipes');
    Route::get('/invoice-import', [DashboardController::class, 'invoiceImport'])->name('invoice_import');

    // Catalog: categories
    Route::post('/dashboard/categories', [CatalogCategoriesController::class, 'store'])->name('dashboard.categories.store');
    Route::put('/dashboard/categories/{categoryId}', [CatalogCategoriesController::class, 'update'])->name('dashboard.categories.update');
    Route::delete('/dashboard/categories/{categoryId}', [CatalogCategoriesController::class, 'destroy'])->name('dashboard.categories.delete');

    // Catalog: brands (existing name preserved)
    Route::post('/dashboard/brands', [CatalogBrandsController::class, 'store'])->name('dashboard.brands.store');
    Route::put('/dashboard/brands/{brandId}', [CatalogBrandsController::class, 'update'])->name('dashboard.brands.update');
    Route::delete('/dashboard/brands/{brandId}', [CatalogBrandsController::class, 'destroy'])->name('dashboard.brands.delete');

    // Catalog: products (existing names preserved)
    Route::post('/dashboard/products', [CatalogProductsController::class, 'store'])->name('dashboard.products.store');
    Route::get('/dashboard/products/image-suggestions', [CatalogProductsController::class, 'imageSuggestions'])->name('dashboard.products.image_suggestions');
    Route::put('/dashboard/products/{productId}', [CatalogProductsController::class, 'update'])->name('dashboard.products.update');
    Route::delete('/dashboard/products/{productId}', [CatalogProductsController::class, 'destroy'])->name('dashboard.products.delete');
    Route::post('/dashboard/products/{productId}/tobacco-pack-purchases', [CatalogProductsController::class, 'storeTobaccoPackPurchase'])->name('dashboard.products.tobacco_pack_purchases.store');
    Route::put('/dashboard/products/{productId}/tobacco-pack-purchases/{purchaseId}', [CatalogProductsController::class, 'updateTobaccoPackPurchase'])->name('dashboard.products.tobacco_pack_purchases.update');
    Route::delete('/dashboard/products/{productId}/tobacco-pack-purchases/{purchaseId}', [CatalogProductsController::class, 'deleteTobaccoPackPurchase'])->name('dashboard.products.tobacco_pack_purchases.destroy');
    Route::put('/dashboard/products/{productId}/tobacco-pack-inventory/{inventoryId}', [CatalogProductsController::class, 'updateTobaccoPackInventory'])->name('dashboard.products.tobacco_pack_inventory.update');

    // Generic deliveries (non-tobacco)
    Route::post('/dashboard/products/{productId}/deliveries', [CatalogProductsController::class, 'storeGenericDelivery'])->name('dashboard.products.deliveries.store');
    Route::put('/dashboard/products/{productId}/deliveries/{deliveryId}', [CatalogProductsController::class, 'updateGenericDelivery'])->name('dashboard.products.deliveries.update');
    Route::delete('/dashboard/products/{productId}/deliveries/{deliveryId}', [CatalogProductsController::class, 'deleteGenericDelivery'])->name('dashboard.products.deliveries.destroy');

    Route::post('/dashboard/hookah-recipes', [DashboardController::class, 'addHookahRecipe'])->name('dashboard.hookah_recipes.store');
    Route::post('/dashboard/tables/layout', [DashboardController::class, 'setTableCount'])->name('dashboard.tables.layout');
    Route::post('/dashboard/tables/{tableId}/toggle', [DashboardController::class, 'toggleTable'])->name('dashboard.tables.toggle');
    Route::post('/dashboard/tables/{tableId}/status', [DashboardController::class, 'setTableStatus'])->name('dashboard.tables.status');
    Route::post('/dashboard/tables/{tableId}/reservations', [DashboardController::class, 'createReservation'])->name('dashboard.tables.reservations.store');
    Route::post('/dashboard/orders/items', [DashboardController::class, 'addOrderItem'])->name('dashboard.orders.items.store');
    Route::post('/dashboard/orders/items/{itemId}/quantity', [DashboardController::class, 'updateOrderItemQuantity'])->name('dashboard.orders.items.quantity');
    Route::post('/dashboard/orders/items/{itemId}/status', [DashboardController::class, 'updateOrderItemStatus'])->name('dashboard.orders.items.status');
    Route::post('/dashboard/orders/items/{itemId}/delete', [DashboardController::class, 'deleteOrderItem'])->name('dashboard.orders.items.delete');
    Route::post('/dashboard/orders/ai', AiOrderController::class)->name('dashboard.orders.ai');
    Route::post('/dashboard/orders/close', [DashboardController::class, 'closeOrder'])->name('dashboard.orders.close');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
