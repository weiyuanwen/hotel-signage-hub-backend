<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Device || ! $user->isPaired()) {
            abort(403, 'Device token required.');
        }

        $hotel = $user->hotel;
        if ($hotel && ! $hotel->subscriptionActive()) {
            abort(403, 'Subscription expired.');
        }
        if ($hotel && ! $hotel->deviceWithinQuota($user)) {
            abort(403, 'Device limit reached.');
        }

        return $next($request);
    }
}
