<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\DamagedProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\NotificationController;


//Dashboard
Route::get('/dashboard', [DashboardController::class, 'index']);


//Login
Route::post('/login', [AuthController::class, 'login']);

//Inventory
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::put('/products/{id}/receive', [ProductController::class, 'receive']);
Route::put('products/{productName}/deduct', [ProductController::class, 'deduct']);
Route::put('/products/{id}/deducted', [ProductController::class, 'deducted']);
Route::post('/products/{id}/hide', [ProductController::class, 'hideProduct']);
Route::post('/products/{id}/unhide', [ProductController::class, 'unhideProduct']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::apiResource('products', ProductController::class);


//Damaged Products
Route::get('/damaged-products', [DamagedProductController::class, 'index']);
Route::post('/damaged-products', [DamagedProductController::class, 'store']);
Route::get('/damaged-products/stats', [DamagedProductController::class, 'stats']);
Route::post('/damaged-products/{id}/refund', [DamagedProductController::class, 'refund']);
Route::post('/inventory/deduct-from-damage', [DamagedProductController::class, 'deductFromInventory']);

//Customers
Route::prefix('customers')->group(function () {
Route::get('/', [CustomerController::class, 'index']);
Route::post('/', [CustomerController::class, 'store']);
Route::put('/{id}', [CustomerController::class, 'update']);
Route::get('/{id}', [CustomerController::class, 'show']);
});

Route::prefix('notifications')->group(function () {
Route::get('/', [NotificationController::class, 'index']);
Route::post('/', [NotificationController::class, 'store']);
Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']); 
});

//Test
Route::get('/test-cors', function () {
    return response()->json(['message' => 'CORS is working!']);
});
