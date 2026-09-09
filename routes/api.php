<?php

use App\Http\Controllers\Api\Cms\AuthController;
use App\Http\Controllers\Api\Cms\DeviceAdminController;
use App\Http\Controllers\Api\Cms\HotelController;
use App\Http\Controllers\Api\Cms\PairingClaimController;
use App\Http\Controllers\Api\Cms\RoomController;
use App\Http\Controllers\Api\Cms\StaffController;
use App\Http\Controllers\Api\Cms\StayController;
use App\Http\Controllers\Api\Cms\WelcomeTemplateController;
use App\Http\Controllers\Api\Device\HeartbeatController;
use App\Http\Controllers\Api\Device\PairingController;
use App\Http\Controllers\Api\Device\ScreenController;
use Illuminate\Support\Facades\Route;

Route::prefix('cms')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'auth.cms'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('hotels', [HotelController::class, 'index']);
        Route::post('hotels', [HotelController::class, 'store']);

        Route::prefix('hotels/{hotel}')->middleware('hotel.scope')->group(function () {
            Route::get('rooms', [RoomController::class, 'index']);
            Route::post('rooms', [RoomController::class, 'store']);
            Route::get('welcome-templates', [WelcomeTemplateController::class, 'index']);
            Route::patch('welcome-templates/default', [WelcomeTemplateController::class, 'updateDefault']);
            Route::patch('welcome-templates/{template}', [WelcomeTemplateController::class, 'update']);
            Route::post('rooms/{room}/check-in', [StayController::class, 'checkIn']);
            Route::post('rooms/{room}/checkout', [StayController::class, 'checkout']);
            Route::patch('rooms/{room}/welcome', [StayController::class, 'update']);
            Route::post('pairing-codes/claim', [PairingClaimController::class, 'store']);
            Route::get('devices', [DeviceAdminController::class, 'index']);
            Route::post('devices/{device}/unpair', [DeviceAdminController::class, 'unpair']);
            Route::get('staff', [StaffController::class, 'index']);
            Route::post('staff', [StaffController::class, 'store']);
            Route::patch('staff/{user}', [StaffController::class, 'update']);
        });
    });
});

Route::prefix('device')->group(function () {
    Route::post('pairing-codes', [PairingController::class, 'store'])->middleware('throttle:pairing');
    Route::get('pairing-codes/{code}', [PairingController::class, 'show'])->middleware('throttle:pairing');

    Route::middleware(['auth:sanctum', 'auth.device'])->group(function () {
        Route::get('screen', [ScreenController::class, 'show']);
        Route::post('heartbeat', [HeartbeatController::class, 'store']);
    });
});
