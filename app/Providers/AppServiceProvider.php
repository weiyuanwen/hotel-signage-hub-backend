<?php

namespace App\Providers;

use App\Models\Hotel;
use App\Models\User;
use App\Observers\HotelObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Hotel::observe(HotelObserver::class);

        Gate::before(function ($user, string $ability) {
            return $user instanceof User && $user->hasRole('super-admin') ? true : null;
        });

        RateLimiter::for('pairing', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
