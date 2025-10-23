<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ParticipantApiController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\WebinarController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/participant', [ParticipantApiController::class, 'registerOrUpdate']);

Route::prefix('auth/login')->group(function () {
    Route::post('/request-otp', [AuthController::class, 'requestOtp']);
    Route::post('/verify-otp',  [AuthController::class, 'verifyOtp']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/participant/profile/update', [ParticipantController::class, 'updateProfile']);
    Route::delete('participant/account/delete', [ParticipantController::class, 'deleteAccount']);

    Route::get('/webinars', [WebinarController::class, 'index']);
    Route::get('/webinars/{id}', [WebinarController::class, 'show']);
    Route::post('webinars/join/{id}', [WebinarController::class, 'joinWebinar']);

    Route::get('/notifications', [UserNotificationController::class, 'index']);
    Route::put('/notifications/{id}/read/status/update', [UserNotificationController::class, 'readSatusUpdate']);
    Route::get('notifications/{id}', [UserNotificationController::class, 'show']);
});
