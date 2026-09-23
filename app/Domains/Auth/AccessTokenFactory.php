<?php

namespace App\Domains\Auth;

use App\Models\Device;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class AccessTokenFactory
{
    public function cms(User $user): string
    {
        return $user->createToken(
            'cms',
            ['cms'],
            now()->addMinutes((int) config('sanctum.cms_expiration_minutes')),
        )->plainTextToken;
    }

    public function device(Device $device): string
    {
        return $device->createToken(
            'tv',
            ['device'],
            now()->addMinutes((int) config('sanctum.device_expiration_minutes')),
        )->plainTextToken;
    }

    public function renewDeviceIfDue(Device $device): void
    {
        $token = $device->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->expires_at) {
            return;
        }

        if ($token->expires_at->greaterThan(now()->addDays(7))) {
            return;
        }

        $token->forceFill([
            'expires_at' => now()->addMinutes((int) config('sanctum.device_expiration_minutes')),
        ])->save();
    }
}
