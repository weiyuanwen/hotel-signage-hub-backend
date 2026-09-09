<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use CreatesStaff;

    public function test_receptionist_cannot_list_or_create_staff(): void
    {
        $hotel = Hotel::factory()->create();
        $desk = $this->staff('receptionist', $hotel);

        Sanctum::actingAs($desk);

        $this->getJson("/api/cms/hotels/{$hotel->id}/staff")->assertForbidden();
        $this->postJson("/api/cms/hotels/{$hotel->id}/staff", [
            'name' => 'Lan',
            'email' => 'lan@hotel.test',
            'password' => 'password',
            'role' => 'receptionist',
        ])->assertForbidden();
    }

    public function test_manager_can_create_receptionist_in_own_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = $this->staff('hotel-manager', $hotel);

        Sanctum::actingAs($manager);

        $this->postJson("/api/cms/hotels/{$hotel->id}/staff", [
            'name' => 'Le Tan Mai',
            'email' => 'mai@hotel.test',
            'password' => 'password',
            'role' => 'receptionist',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'mai@hotel.test')
            ->assertJsonPath('data.roles.0', 'receptionist')
            ->assertJsonPath('data.is_active', true);

        $this->getJson("/api/cms/hotels/{$hotel->id}/staff")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_cannot_create_hotel_manager_or_super_admin(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = $this->staff('hotel-manager', $hotel);

        Sanctum::actingAs($manager);

        $this->postJson("/api/cms/hotels/{$hotel->id}/staff", [
            'name' => 'Boss',
            'email' => 'boss@hotel.test',
            'password' => 'password',
            'role' => 'hotel-manager',
        ])->assertForbidden();

        $this->postJson("/api/cms/hotels/{$hotel->id}/staff", [
            'name' => 'Root',
            'email' => 'root@hotel.test',
            'password' => 'password',
            'role' => 'super-admin',
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_hotel_manager(): void
    {
        $hotel = Hotel::factory()->create();
        $admin = $this->staff('super-admin');

        Sanctum::actingAs($admin);

        $this->postJson("/api/cms/hotels/{$hotel->id}/staff", [
            'name' => 'Quan Ly',
            'email' => 'ql@hotel.test',
            'password' => 'password',
            'role' => 'hotel-manager',
        ])
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'hotel-manager');

        $created = User::query()->where('email', 'ql@hotel.test')->first();
        $this->assertTrue($created?->canAccessHotel($hotel->id));
    }

    public function test_staff_list_is_hotel_scoped(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $manager = $this->staff('hotel-manager', $hotelA);
        $this->staff('receptionist', $hotelB);

        Sanctum::actingAs($manager);

        $this->getJson("/api/cms/hotels/{$hotelA->id}/staff")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $manager->id);

        $this->getJson("/api/cms/hotels/{$hotelB->id}/staff")->assertForbidden();
    }

    public function test_manager_cannot_lock_self_or_edit_other_managers(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = $this->staff('hotel-manager', $hotel);
        $peer = $this->staff('hotel-manager', $hotel);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/staff/{$manager->id}", [
            'is_active' => false,
        ])->assertUnprocessable();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/staff/{$peer->id}", [
            'is_active' => false,
        ])->assertForbidden();
    }

    public function test_deactivate_blocks_login_and_revokes_tokens(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = $this->staff('hotel-manager', $hotel);
        $desk = $this->staff('receptionist', $hotel);
        $desk->createToken('cms', ['cms']);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/staff/{$desk->id}", [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $desk->tokens()->count());

        $this->postJson('/api/cms/login', [
            'email' => $desk->email,
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_super_admin_can_demote_manager_to_receptionist_and_keep_current_hotel_only(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $admin = $this->staff('super-admin');
        $manager = $this->staff('hotel-manager', $hotelA);
        $manager->hotels()->attach($hotelB->id, ['is_primary' => false]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/cms/hotels/{$hotelA->id}/staff/{$manager->id}", [
            'role' => 'receptionist',
        ])
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'receptionist');

        $manager->refresh();
        $this->assertTrue($manager->hotels()->where('hotels.id', $hotelA->id)->exists());
        $this->assertFalse($manager->hotels()->where('hotels.id', $hotelB->id)->exists());
    }
}
