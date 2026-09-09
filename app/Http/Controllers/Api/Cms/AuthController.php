<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        $token = $user->createToken('cms', ['cms'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->profile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->profile($user)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $user->load('hotels:id,name,slug', 'roles:name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
            'hotels' => $user->hasRole('super-admin')
                ? []
                : $user->hotels->map(fn ($hotel) => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'slug' => $hotel->slug,
                ])->values(),
        ];
    }
}
