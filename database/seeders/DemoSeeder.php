<?php

namespace Database\Seeders;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@hub.test'],
            ['name' => 'Điều hành Hub', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $admin->syncRoles(['super-admin']);

        $hotel = Hotel::query()->updateOrCreate(
            ['slug' => 'saigon-pearl'],
            [
                'name' => 'Saigon Pearl',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'default_locale' => 'vi',
                'weather_region' => 'ho-chi-minh',
                'wifi_ssid' => 'SaigonPearl-Guest',
                'wifi_password' => 'pearl2026',
                'is_active' => true,
                'plan' => 'premium',
                'device_limit' => null,
            ],
        );

        app(WelcomeTemplateCatalog::class)->syncHotel($hotel);

        foreach (['101', '102', '201', 'Lobby'] as $code) {
            Room::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $code],
                [
                    'name' => $code === 'Lobby' ? 'Sảnh' : 'Phòng '.$code,
                    'kind' => $code === 'Lobby' ? 'public' : 'guest',
                    'is_active' => true,
                ],
            );
        }

        $desk = User::query()->updateOrCreate(
            ['email' => 'desk@saigon-pearl.test'],
            ['name' => 'Lễ tân Pearl', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $desk->syncRoles(['receptionist']);
        $desk->hotels()->syncWithoutDetaching([$hotel->id => ['is_primary' => true]]);

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@saigon-pearl.test'],
            ['name' => 'Quản lý Pearl', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $manager->syncRoles(['hotel-manager']);
        $manager->hotels()->syncWithoutDetaching([$hotel->id => ['is_primary' => true]]);

        $tester = User::query()->updateOrCreate(
            ['email' => 'test@saigon-pearl.test'],
            ['name' => 'Tester Pearl', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $tester->syncRoles(['receptionist']);
        $tester->hotels()->syncWithoutDetaching([$hotel->id => ['is_primary' => true]]);
    }
}
