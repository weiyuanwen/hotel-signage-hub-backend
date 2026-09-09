<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class AuthAndTenantIsolationTest extends TestCase
{
    use CreatesStaff;

    public function test_cms_login_returns_token(): void
    {
        $hotel = Hotel::factory()->create();
        $user = $this->staff('receptionist', $hotel);

        $this->postJson('/api/cms/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.roles.0', 'receptionist')
            ->assertJsonStructure(['token']);
    }

    public function test_receptionist_cannot_see_other_hotel(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $user = $this->staff('receptionist', $hotelA);

        Sanctum::actingAs($user);

        $this->getJson('/api/cms/hotels')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hotelA->id);

        $this->getJson("/api/cms/hotels/{$hotelB->id}/rooms")
            ->assertForbidden();
    }

    public function test_manager_can_access_multiple_hotels(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $manager = $this->staff('hotel-manager', $hotelA);
        $manager->hotels()->attach($hotelB->id, ['is_primary' => false]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/cms/hotels')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/cms/hotels/{$hotelB->id}/rooms")->assertOk();
    }

    public function test_super_admin_can_create_hotel_and_is_not_scoped(): void
    {
        $admin = $this->staff('super-admin');
        Hotel::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/cms/hotels', ['name' => 'Saigon Pearl'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Saigon Pearl');

        $this->getJson('/api/cms/hotels')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_device_token_cannot_call_cms_routes(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = \App\Models\Device::factory()->paired($hotel, $room)->create();

        Sanctum::actingAs($device);

        $this->getJson('/api/cms/me')->assertForbidden();
    }

    public function test_cms_token_cannot_call_device_routes(): void
    {
        $hotel = Hotel::factory()->create();
        $user = $this->staff('receptionist', $hotel);

        Sanctum::actingAs($user);

        $this->getJson('/api/device/screen')->assertForbidden();
    }

    public function test_receptionist_cannot_create_rooms(): void
    {
        $hotel = Hotel::factory()->create();
        $user = $this->staff('receptionist', $hotel);

        Sanctum::actingAs($user);

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms", [
            'code' => '101',
            'kind' => 'guest',
        ])->assertForbidden();
    }
}
