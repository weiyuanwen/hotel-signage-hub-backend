<?php

use App\Http\Controllers\Api\Cms\AuthController;
use App\Http\Controllers\Api\Cms\DeviceAdminController;
use App\Http\Controllers\Api\Cms\DeviceMediaController;
use App\Http\Controllers\Api\Cms\HotelController;
use App\Http\Controllers\Api\Cms\HotelMediaController;
use App\Http\Controllers\Api\Cms\PairingClaimController;
use App\Http\Controllers\Api\Cms\PairingLinkController;
use App\Http\Controllers\Api\Cms\RoomController;
use App\Http\Controllers\Api\Cms\RoomMediaController;
use App\Http\Controllers\Api\Cms\StaffController;
use App\Http\Controllers\Api\Cms\StayController;
use App\Http\Controllers\Api\Cms\WaitlistController;
use App\Http\Controllers\Api\Cms\WeatherRegionController;
use App\Http\Controllers\Api\Cms\WelcomeTemplateController;
use App\Http\Controllers\Api\Device\HeartbeatController;
use App\Http\Controllers\Api\Device\PairingController;
use App\Http\Controllers\Api\Device\ScreenController;
use Illuminate\Support\Facades\Route;

Route::prefix('cms')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:cms-login');
    Route::post('waitlist', [WaitlistController::class, 'store'])->middleware('throttle:waitlist');

    Route::middleware(['auth:sanctum', 'auth.cms'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('weather-regions', [WeatherRegionController::class, 'index']);
        Route::get('hotels', [HotelController::class, 'index']);
        Route::post('hotels', [HotelController::class, 'store']);

        Route::prefix('hotels/{hotel}')->middleware('hotel.scope')->group(function () {
            Route::get('/', [HotelController::class, 'show']);
            Route::patch('/', [HotelController::class, 'update']);
            Route::post('media', [HotelMediaController::class, 'store']);
            Route::delete('media/{purpose}', [HotelMediaController::class, 'destroy'])->whereIn('purpose', ['logo', 'background']);
            Route::get('rooms', [RoomController::class, 'index']);
            Route::post('rooms', [RoomController::class, 'store']);
            Route::post('rooms/{room}/media', [RoomMediaController::class, 'store']);
            Route::delete('rooms/{room}/media', [RoomMediaController::class, 'destroy']);
            Route::get('welcome-templates', [WelcomeTemplateController::class, 'index']);
            Route::patch('welcome-templates/default', [WelcomeTemplateController::class, 'updateDefault']);
            Route::post('welcome-templates/{template}/media', [WelcomeTemplateController::class, 'storeBackground']);
            Route::delete('welcome-templates/{template}/media', [WelcomeTemplateController::class, 'destroyBackground']);
            Route::patch('welcome-templates/{template}', [WelcomeTemplateController::class, 'update']);
            Route::post('rooms/{room}/check-in', [StayController::class, 'checkIn']);
            Route::post('rooms/{room}/checkout', [StayController::class, 'checkout']);
            Route::patch('rooms/{room}/welcome', [StayController::class, 'update']);
            Route::post('pairing-codes/claim', [PairingClaimController::class, 'store']);
            Route::post('pairing-links', [PairingLinkController::class, 'store']);
            Route::get('devices', [DeviceAdminController::class, 'index']);
            Route::patch('devices/{device}', [DeviceAdminController::class, 'update']);
            Route::post('devices/{device}/unpair', [DeviceAdminController::class, 'unpair']);
            Route::post('devices/{device}/media', [DeviceMediaController::class, 'store']);
            Route::delete('devices/{device}/media', [DeviceMediaController::class, 'destroy']);
            Route::get('staff', [StaffController::class, 'index']);
            Route::post('staff', [StaffController::class, 'store']);
            Route::patch('staff/{user}', [StaffController::class, 'update']);
        });
    });
});

Route::prefix('device')->group(function () {
    Route::post('pairing-codes', [PairingController::class, 'store'])->middleware('throttle:pairing');
    Route::get('pairing-codes/{code}', [PairingController::class, 'show'])->middleware('throttle:pairing-poll');
    Route::post('pairing-links/{token}', [PairingController::class, 'consumeLink'])->middleware('throttle:pairing');

    Route::middleware(['auth:sanctum', 'auth.device'])->group(function () {
        Route::get('screen', [ScreenController::class, 'show']);
        Route::post('heartbeat', [HeartbeatController::class, 'store']);
    });
});
