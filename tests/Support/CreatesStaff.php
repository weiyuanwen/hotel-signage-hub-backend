<?php

namespace Tests\Support;

use App\Models\Hotel;
use App\Models\User;

trait CreatesStaff
{
    protected function staff(string $role, ?Hotel $hotel = null, bool $primary = true): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($role !== 'super-admin' && $hotel) {
            $user->hotels()->attach($hotel->id, ['is_primary' => $primary]);
        }

        return $user->fresh();
    }
}
