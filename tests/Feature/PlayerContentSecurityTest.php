<?php

namespace Tests\Feature;

use App\Domains\Realtime\ChannelAuthorizer;
use App\Domains\Realtime\Events\RoomContentUpdated;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class PlayerContentSecurityTest extends TestCase
{
    use CreatesStaff;

    public function test_unauthenticated_cannot_read_screen_or_change_templates(): void
    {
        $hotel = Hotel::factory()->create();

        $this->getJson('/api/device/screen')->assertUnauthorized();
        $this->postJson('/api/device/heartbeat')->assertUnauthorized();
        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'layout' => ['slogan' => 'hack'],
        ])->assertUnauthorized();
        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/1/check-in", [
            'guest_display_name' => 'Hack',
        ])->assertUnauthorized();
    }

    public function test_device_token_cannot_mutate_cms_content(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($device, ['device']);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'layout' => ['slogan' => 'từ TV'],
        ])->assertForbidden();

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'TV',
        ])->assertForbidden();
    }

    public function test_manager_cannot_patch_templates_of_another_hotel(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotelA));

        $this->patchJson("/api/cms/hotels/{$hotelB->id}/welcome-templates/linen", [
            'layout' => ['slogan' => 'xuyên KS'],
        ])->assertForbidden();
    }

    public function test_check_in_rejects_oversized_welcome_message(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Lan',
            'message' => str_repeat('x', 2001),
        ])->assertStatus(422);
    }

    public function test_content_broadcast_is_a_wakeup_hint_without_guest_fields(): void
    {
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Mai',
            'template_key' => 'linen',
        ])->assertCreated();

        Event::assertDispatched(RoomContentUpdated::class, function (RoomContentUpdated $event) use ($hotel, $room) {
            $wire = $event->broadcastWith();

            return $wire === [
                'hotel_id' => $hotel->id,
                'room_id' => $room->id,
                'content_revision' => $event->room->content_revision,
            ]
                && ! array_key_exists('guest', $wire)
                && ! array_key_exists('media', $wire)
                && ($event->payload['guest']['display_name'] ?? null) === 'Mai';
        });
    }

    public function test_staff_cannot_subscribe_room_channel_when_room_is_not_in_hotel(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $roomA = Room::factory()->create(['hotel_id' => $hotelA->id]);
        $roomB = Room::factory()->create(['hotel_id' => $hotelB->id]);
        $user = $this->staff('receptionist', $hotelA);
        $auth = app(ChannelAuthorizer::class);

        $this->assertTrue($auth->canJoinRoom($user, $hotelA->id, $roomA->id));
        $this->assertFalse($auth->canJoinRoom($user, $hotelA->id, $roomB->id));
        $this->assertFalse($auth->canJoinRoom($user, $hotelB->id, $roomB->id));
    }

    public function test_cms_login_is_rate_limited(): void
    {
        $hotel = Hotel::factory()->create();
        $user = $this->staff('receptionist', $hotel);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/cms/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/cms/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
