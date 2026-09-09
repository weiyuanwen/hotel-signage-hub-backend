<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHotelScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'CMS token required.');
        }

        $hotel = $request->route('hotel');
        $hotelId = $hotel instanceof Hotel ? (int) $hotel->id : (int) $hotel;

        if ($hotelId < 1 || ! $user->canAccessHotel($hotelId)) {
            abort(403, 'Hotel out of scope.');
        }

        $request->attributes->set('hotel_id', $hotelId);

        return $next($request);
    }
}
