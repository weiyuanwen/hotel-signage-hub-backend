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
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('super-admin', 'web')->givePermissionTo(self::PERMISSIONS);

        Role::findOrCreate('hotel-manager', 'web')->givePermissionTo([
            'hotels.view',
            'rooms.view',
            'rooms.manage',
            'stays.manage',
            'devices.pair',
            'devices.manage',
        ]);

        Role::findOrCreate('receptionist', 'web')->givePermissionTo([
            'hotels.view',
            'rooms.view',
            'stays.manage',
            'devices.pair',
        ]);
    }
}
