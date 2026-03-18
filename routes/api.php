<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // ===== Public Routes =====
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // ===== Protected Routes (Authentication Required) =====
    Route::middleware('auth:sanctum')->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // ===== Event Routes =====
      
             Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{id}', [EventController::class, 'show']);
         Route::get('/bookings', [BookingController::class, 'index']);

        Route::middleware(['role:organizer', 'throttle:60,1'])->group(function () {

            Route::post('/events', [EventController::class, 'store']);
            Route::put('/events/{id}', [EventController::class, 'update']);
            Route::delete('/events/{id}', [EventController::class, 'destroy']);

          
        });

        // ===== Ticket Routes(Organizer) =====
        Route::middleware(['role:organizer', 'throttle:60,1'])->group(function () {
              Route::post('/events/{event_id}/tickets', [TicketController::class, 'store']);
            Route::put('/tickets/{id}', [TicketController::class, 'update']);
            Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
        });

        // ===== Booking Routes (Customer & Admin) =====
        Route::middleware(['role:customer,admin', 'throttle:60,1'])->group(function () {
            Route::post('/tickets/{ticketId}/bookings', [BookingController::class, 'store'])
                ->middleware('check.ticket.availability');
            Route::put('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
               Route::post('/bookings/{id}/payment', [PaymentController::class, 'store']);
             Route::get('/payments/{id}', [PaymentController::class, 'show']);
        });

       

      

       
    });
});
