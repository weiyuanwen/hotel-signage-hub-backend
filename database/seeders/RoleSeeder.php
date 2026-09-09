<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public const PERMISSIONS = [
        'hotels.create',
        'hotels.view',
        'rooms.view',
        'rooms.manage',
        'stays.manage',
        'devices.pair',
        'devices.manage',
        'staff.view',
        'staff.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super-admin', 'web')->syncPermissions(self::PERMISSIONS);

        Role::findOrCreate('hotel-manager', 'web')->syncPermissions([
            'hotels.view',
            'rooms.view',
            'rooms.manage',
            'stays.manage',
            'devices.pair',
            'devices.manage',
            'staff.view',
            'staff.manage',
        ]);

        Role::findOrCreate('receptionist', 'web')->syncPermissions([
            'hotels.view',
            'rooms.view',
            'stays.manage',
            'devices.pair',
        ]);
    }
}
