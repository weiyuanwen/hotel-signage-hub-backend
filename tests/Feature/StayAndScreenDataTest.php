<?php

namespace Tests\Feature;

use App\Domains\Realtime\Events\RoomContentUpdated;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\Room;
use App\Models\WelcomeContent;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class StayAndScreenDataTest extends TestCase
{
    use CreatesStaff;

    public function test_check_in_creates_stay_bumps_revision_and_broadcasts(): void
    {
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id, 'kind' => 'guest']);
        $user = $this->staff('receptionist', $hotel);

        Sanctum::actingAs($user);

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Nguyen Van A',
            'message' => 'Welcome',
            'locale' => 'vi',
        ])
            ->assertCreated()
            ->assertJsonPath('data.guest_display_name', 'Nguyen Van A')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.is_current', true);

        $room->refresh();
        $this->assertSame(1, $room->content_revision);
        $this->assertNotNull($room->current_welcome_id);
        $this->assertDatabaseCount('welcome_contents', 1);

        Event::assertDispatched(RoomContentUpdated::class);
    }

    public function test_second_check_in_closes_previous_stay(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $user = $this->staff('receptionist', $hotel);
        Sanctum::actingAs($user);

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Guest One',
        ])->assertCreated();

        $firstId = $room->fresh()->current_welcome_id;

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Guest Two',
            'external_ref' => 'pms-99',
            'source' => 'pms',
        ])->assertCreated();

        $this->assertDatabaseHas('welcome_contents', [
            'id' => $firstId,
            'is_current' => null,
        ]);
        $this->assertSame('Guest Two', $room->fresh()->currentWelcome->guest_display_name);
        $this->assertSame(1, WelcomeContent::query()->where('room_id', $room->id)->where('is_current', true)->count());
    }

    public function test_update_guest_name_mutates_current_stay(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $user = $this->staff('receptionist', $hotel);
        Sanctum::actingAs($user);

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Old Name',
        ])->assertCreated();

        $stayId = $room->fresh()->current_welcome_id;

        $this->patchJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/welcome", [
            'guest_display_name' => 'New Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $stayId)
            ->assertJsonPath('data.guest_display_name', 'New Name');

        $this->assertDatabaseCount('welcome_contents', 1);
    }

    public function test_checkout_clears_guest_and_screen_shows_hotel_branding(): void
    {
        $hotel = Hotel::factory()->create();
        $logo = MediaAsset::factory()->create(['hotel_id' => $hotel->id, 'type' => 'logo', 'path' => 'logo.png']);
        $bg = MediaAsset::factory()->create(['hotel_id' => $hotel->id, 'type' => 'image', 'path' => 'bg.jpg']);
        $hotel->update(['logo_media_id' => $logo->id, 'default_media_id' => $bg->id]);

        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = \App\Models\Device::factory()->paired($hotel, $room)->create();
        $user = $this->staff('receptionist', $hotel);

        Sanctum::actingAs($user);
        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Visible Guest',
        ])->assertCreated();

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/checkout")
            ->assertOk();

        $this->assertNull($room->fresh()->current_welcome_id);

        Sanctum::actingAs($device);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('guest', null)
            ->assertJsonPath('hotel.name', $hotel->name)
            ->assertJsonPath('room.kind', 'guest')
            ->assertHeader('ETag');
    }

    public function test_screen_returns_304_when_revision_matches(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id, 'content_revision' => 4]);
        $device = \App\Models\Device::factory()->paired($hotel, $room)->create();

        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen', ['If-None-Match' => '4'])
            ->assertStatus(304);
    }

    public function test_cannot_access_room_from_another_hotel_via_url(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $roomB = Room::factory()->create(['hotel_id' => $hotelB->id]);
        $user = $this->staff('receptionist', $hotelA);

        Sanctum::actingAs($user);

        $this->postJson("/api/cms/hotels/{$hotelA->id}/rooms/{$roomB->id}/check-in", [
            'guest_display_name' => 'Nope',
        ])->assertNotFound();
    }
}
