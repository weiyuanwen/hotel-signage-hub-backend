<?php

namespace Tests\Feature;

use App\Domains\Realtime\ChannelAuthorizer;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use CreatesStaff;

    public function test_paired_device_can_join_its_room_and_device_channels(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        $auth = app(ChannelAuthorizer::class);

        $this->assertTrue($auth->canJoinRoom($device, $hotel->id, $room->id));
        $this->assertTrue($auth->canJoinDevice($device, $device->id));
    }

    public function test_device_cannot_join_another_room(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $other = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();

        $this->assertFalse(app(ChannelAuthorizer::class)->canJoinRoom($device, $hotel->id, $other->id));
    }

    public function test_receptionist_cannot_join_foreign_hotel_room(): void
    {
        $hotelA = Hotel::factory()->create();
        $hotelB = Hotel::factory()->create();
        $roomB = Room::factory()->create(['hotel_id' => $hotelB->id]);
        $user = $this->staff('receptionist', $hotelA);

        $this->assertFalse(app(ChannelAuthorizer::class)->canJoinRoom($user, $hotelB->id, $roomB->id));
        $roomA = Room::factory()->create(['hotel_id' => $hotelA->id]);
        $this->assertTrue(app(ChannelAuthorizer::class)->canJoinRoom($user, $hotelA->id, $roomA->id));
    }
}
