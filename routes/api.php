
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Respond 204 to all OPTIONS preflight requests.
// Laravel's HandleCors middleware (global) adds the actual CORS headers.
// This route prevents the router from returning 404 on preflight.
Route::options('{any}', fn () => response('', 204))->where('any', '.*');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Public contact endpoint
use App\Http\Controllers\ContactController;
Route::post('/contact', [ContactController::class, 'store']);

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ServiceController;

// Route publique pour la liste des services
Route::get('/services', [ServiceController::class, 'index']);

// Webhook from FedaPay (public)
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

// Verify by transaction id (polling) - public endpoint
Route::get('/payments/verify/{transaction_id}', [PaymentController::class, 'verifyByTransaction']);

Route::middleware(['auth:api'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::get('/users', [UserController::class, 'index']);
    Route::apiResource('reservations', ReservationController::class);

    // Paiements
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/{id}/verify', [PaymentController::class, 'verify']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
});
