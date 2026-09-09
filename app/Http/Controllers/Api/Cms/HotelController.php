<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = Hotel::query()->where('is_active', true)->orderBy('name');

        if (! $user->hasRole('super-admin')) {
            $query->whereIn('id', $user->hotels()->pluck('hotels.id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('hotels.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:hotels,slug'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'default_locale' => ['nullable', 'string', 'max:8'],
        ]);

        $hotel = Hotel::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'timezone' => $data['timezone'] ?? 'Asia/Ho_Chi_Minh',
            'default_locale' => $data['default_locale'] ?? 'vi',
            'is_active' => true,
        ]);

        return response()->json(['data' => $hotel], 201);
    }
}
