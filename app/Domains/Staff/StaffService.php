<?php

namespace App\Domains\Staff;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StaffService
{
    /**
     * @return Collection<int, User>
     */
    public function list(Hotel $hotel): Collection
    {
        return $hotel->users()
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(User $actor, Hotel $hotel, array $data): User
    {
        $role = $data['role'];
        $this->assertCanAssign($actor, $role);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $user->assignRole($role);
        $user->hotels()->attach($hotel->id, ['is_primary' => true]);

        return $user->fresh(['roles', 'hotels']);
    }

    /**
     * @param  array{name?: string, password?: string, role?: string, is_active?: bool}  $data
     */
    public function update(User $actor, Hotel $hotel, User $member, array $data): User
    {
        $this->assertMemberOfHotel($hotel, $member);

        if (array_key_exists('is_active', $data) && $data['is_active'] === false && $actor->is($member)) {
            throw ValidationException::withMessages([
                'is_active' => 'Không thể khóa chính mình.',
            ]);
        }

        if (isset($data['role']) && $actor->is($member)) {
            throw ValidationException::withMessages([
                'role' => 'Không thể đổi vai trò của chính mình.',
            ]);
        }

        $this->assertCanManageMember($actor, $member);

        if (isset($data['role'])) {
            $this->assertCanAssign($actor, $data['role']);
            $member->syncRoles([$data['role']]);

            if ($data['role'] === 'receptionist') {
                $member->hotels()->sync([
                    $hotel->id => ['is_primary' => true],
                ]);
            }
        }

        $member->fill(array_filter([
            'name' => $data['name'] ?? null,
            'password' => $data['password'] ?? null,
        ], fn ($value) => $value !== null));

        if (array_key_exists('is_active', $data)) {
            $member->is_active = $data['is_active'];
        }

        $member->save();

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $member->tokens()->delete();
        }

        return $member->fresh(['roles']);
    }

    private function assertMemberOfHotel(Hotel $hotel, User $member): void
    {
        abort_unless($member->hotels()->where('hotels.id', $hotel->id)->exists(), 404);
    }

    private function assertCanManageMember(User $actor, User $member): void
    {
        if ($member->hasRole('super-admin')) {
            abort(403, 'Không thể sửa tài khoản super-admin.');
        }

        if ($actor->hasRole('super-admin')) {
            return;
        }

        abort_unless($member->hasRole('receptionist'), 403, 'Chỉ được quản lý lễ tân.');
    }

    private function assertCanAssign(User $actor, string $role): void
    {
        $allowed = $actor->hasRole('super-admin')
            ? ['hotel-manager', 'receptionist']
            : ['receptionist'];

        abort_unless(in_array($role, $allowed, true), 403, 'Không được gán vai trò này.');
    }
}
