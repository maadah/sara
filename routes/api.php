<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\AiChatTestController;
use App\Http\Controllers\Api\ChatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// AI Chat Testing Routes - ONLY available in local/testing environment
if (app()->environment('local', 'testing')) {
    Route::prefix('ai-test')->group(function () {
        Route::post('/chat', [AiChatTestController::class, 'chat']);
        Route::post('/scenario', [AiChatTestController::class, 'runScenario']);
        Route::post('/full-test', [AiChatTestController::class, 'fullTest']);
        Route::get('/scenarios', [AiChatTestController::class, 'listScenarios']);
        Route::post('/reset', [AiChatTestController::class, 'reset']);
    });
}

// Secure AI Chat Testing Routes - Available in ALL environments with secret token
// Token must be sent as X-Test-Token header
Route::prefix('ai-test-secure')->middleware([])->group(function () {
    Route::post('/chat', function (Request $request) {
        $token = $request->header('X-Test-Token');
        if ($token !== 'rehla-test-2026-secure-key') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return app(AiChatTestController::class)->chat($request);
    });
    Route::post('/reset', function (Request $request) {
        $token = $request->header('X-Test-Token');
        if ($token !== 'rehla-test-2026-secure-key') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return app(AiChatTestController::class)->reset($request);
    });
});

// Public Store API routes - Scoped by store (user_id)
// These routes allow external storefronts to fetch store-specific data
Route::prefix('v1/store/{storeId}')->group(function () {
    // Products for specific store
    Route::get('products/{id}', [ProductController::class, 'showForStore']);
    Route::get('products', [ProductController::class, 'indexForStore']);

    // Categories for specific store
    Route::get('categories', [ProductController::class, 'categoriesForStore']);
});

// AI Marketing Chatbot — public endpoints (called by the widget / Meta webhooks)
Route::prefix('v1/chat')->group(function () {
    Route::post('/', [ChatController::class, 'processMessage']);
    Route::get('/session/{id}', [ChatController::class, 'sessionStats']);
});

// Authenticated API routes - Require login
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Store owner's own data
    Route::get('my/products', [ProductController::class, 'myProducts']);
    Route::get('my/products/{id}', [ProductController::class, 'myProduct']);
    Route::get('my/categories', [ProductController::class, 'myCategories']);

    // Stores - Admin only
    Route::get('stores/{id}', [StoreController::class, 'show']);
    Route::get('stores', [StoreController::class, 'index']);

    // Leads - Owner only sees their own
    Route::get('leads/{id}', [LeadController::class, 'show']);
    Route::get('leads', [LeadController::class, 'index']);
});
