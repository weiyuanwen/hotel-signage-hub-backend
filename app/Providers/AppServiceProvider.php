<?php

namespace App\Providers;

use App\Models\Hotel;
use App\Models\User;
use App\Observers\HotelObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::before(function ($user, string $ability) {
            return $user instanceof User && $user->hasRole('super-admin') ? true : null;
        });

        RateLimiter::for('pairing', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('pairing-poll', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('cms-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('waitlist', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip());
        });
    }
}
