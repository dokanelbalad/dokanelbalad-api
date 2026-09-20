<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\VendorController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\PaymentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes (no login required)
Route::prefix('v1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::post('/otp/send', [OtpController::class, 'send']);
    Route::post('/otp/verify', [OtpController::class, 'verify']);

    // Paymob calls this directly - must stay public, protected by HMAC verification instead
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
});

// Protected routes (must be logged in)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/vendor/register', [VendorController::class, 'register']);
    Route::get('/vendor/dashboard', [VendorController::class, 'dashboard']);
    Route::get('/vendor/products', [VendorController::class, 'products']);
    Route::get('/vendor/orders', [VendorController::class, 'orders']);
    Route::post('/vendor/products', [VendorController::class, 'storeProduct']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/confirm-delivery', [OrderController::class, 'confirmDelivery']);

    Route::post('/orders/{id}/pay', [PaymentController::class, 'startPayment']);
    Route::get('/orders/{id}/payment-status', [PaymentController::class, 'paymentStatus']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/overview', [AdminController::class, 'overview']);

        Route::get('/vendors', [AdminController::class, 'vendors']);
        Route::post('/vendors/{id}/approve', [AdminController::class, 'approveVendor']);
        Route::post('/vendors/{id}/reject', [AdminController::class, 'rejectVendor']);

        Route::get('/categories', [AdminController::class, 'categories']);
        Route::post('/categories', [AdminController::class, 'storeCategory']);
        Route::put('/categories/{id}', [AdminController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [AdminController::class, 'destroyCategory']);

        Route::get('/commissions/pending', [AdminController::class, 'pendingCommissions']);
        Route::post('/commissions/{vendorId}/collect', [AdminController::class, 'collectCommission']);

        Route::get('/products', [AdminController::class, 'products']);
        Route::put('/products/{id}/category', [AdminController::class, 'updateProductCategory']);
        Route::put('/products/{id}/discount', [AdminController::class, 'updateProductDiscount']);
    });
});