<?php

namespace Tests\Feature;

use App\Domains\Device\HeartbeatService;
use App\Domains\Device\PairingCodeGenerator;
use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class PairingAndHeartbeatTest extends TestCase
{
    use CreatesStaff;

    public function test_tv_requests_pin_staff_claims_and_tv_receives_token(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('receptionist', $hotel);

        $request = $this->postJson('/api/device/pairing-codes', ['name' => 'Lobby TV'])
            ->assertCreated()
            ->assertJsonStructure(['code', 'expires_at']);

        $code = $request->json('code');
        $this->assertSame(6, strlen($code));
        $this->assertSame('', preg_replace('/['.PairingCodeGenerator::ALPHABET.']/', '', $code));

        $this->getJson("/api/device/pairing-codes/{$code}")
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        Sanctum::actingAs($staff);
        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
            'code' => $code,
            'room_id' => $room->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paired')
            ->assertJsonPath('data.room_id', $room->id);

        $paired = $this->getJson("/api/device/pairing-codes/{$code}")
            ->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonPath('hotel_id', $hotel->id)
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonStructure(['token', 'device_id', 'hotel_id', 'room_id'])
            ->assertJsonMissing(['device']);

        $device = Device::query()->where('room_id', $room->id)->firstOrFail();
        $this->assertSame($device->id, $paired->json('device_id'));
        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('room.id', $room->id)
            ->assertJsonPath('guest', null);
    }

    public function test_paired_poll_issues_token_once_and_omits_eloquent_device(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('receptionist', $hotel);

        $code = $this->postJson('/api/device/pairing-codes', ['name' => 'Room TV'])->json('code');

        Sanctum::actingAs($staff);
        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
            'code' => $code,
            'room_id' => $room->id,
        ])->assertOk();

        $first = $this->getJson("/api/device/pairing-codes/{$code}")
            ->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonPath('hotel_id', $hotel->id)
            ->assertJsonPath('room_id', $room->id)
            ->json();

        $this->assertNotEmpty($first['token']);
        $this->assertArrayNotHasKey('device', $first);

        $device = Device::query()->findOrFail($first['device_id']);
        $this->assertSame(1, $device->tokens()->count());

        $second = $this->getJson("/api/device/pairing-codes/{$code}")
            ->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonPath('device_id', $first['device_id'])
            ->assertJsonPath('hotel_id', $hotel->id)
            ->assertJsonPath('room_id', $room->id)
            ->json();

        $this->assertArrayNotHasKey('token', $second);
        $this->assertArrayNotHasKey('device', $second);
        $this->assertSame(1, $device->tokens()->count());
    }

    public function test_polling_does_not_block_issuing_a_new_pin(): void
    {
        $code = $this->postJson('/api/device/pairing-codes')->assertCreated()->json('code');

        for ($i = 0; $i < 25; $i++) {
            $this->getJson("/api/device/pairing-codes/{$code}")->assertStatus(202);
        }

        $this->postJson('/api/device/pairing-codes')->assertCreated();
    }

    public function test_expired_code_cannot_be_claimed(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('receptionist', $hotel);

        $this->postJson('/api/device/pairing-codes')->assertCreated();
        $device = Device::query()->latest('id')->firstOrFail();
        $pairing = $device->pairingCodes()->firstOrFail();
        $pairing->forceFill(['expires_at' => now()->subMinute()])->save();

        Sanctum::actingAs($staff);
        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
            'code' => $pairing->code,
            'room_id' => $room->id,
        ])->assertUnprocessable();
    }

    public function test_heartbeat_does_not_write_last_seen_to_mysql(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create(['last_seen_at' => null]);

        Sanctum::actingAs($device);

        $this->postJson('/api/device/heartbeat')
            ->assertOk()
            ->assertJsonPath('online', true);

        $this->assertNull($device->fresh()->last_seen_at);
        $this->assertTrue(app(HeartbeatService::class)->isOnline($device->id));
    }

    public function test_unpair_revokes_token_and_broadcasts_device_command(): void
    {
        Event::fake([DeviceCommandIssued::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        $manager = $this->staff('hotel-manager', $hotel);

        $token = $device->createToken('tv', ['device'])->plainTextToken;

        Sanctum::actingAs($manager);
        $this->postJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}/unpair")
            ->assertOk();

        $this->assertSame('revoked', $device->fresh()->status);
        $this->assertSame(0, $device->tokens()->count());
        Event::assertDispatched(DeviceCommandIssued::class);

        $this->withToken($token)->getJson('/api/device/screen')->assertForbidden();
    }

    public function test_two_devices_can_pair_to_the_same_room(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('receptionist', $hotel);

        foreach (['TV-A', 'TV-B'] as $name) {
            $code = $this->postJson('/api/device/pairing-codes', ['name' => $name])->json('code');
            Sanctum::actingAs($staff);
            $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
                'code' => $code,
                'room_id' => $room->id,
            ])->assertOk();
        }

        $this->assertSame(2, Device::query()->where('room_id', $room->id)->where('status', 'paired')->count());

        Sanctum::actingAs($staff);
        $this->getJson("/api/cms/hotels/{$hotel->id}/rooms")
            ->assertOk()
            ->assertJsonPath('data.0.paired_tv_count', 2);

        $this->getJson("/api/cms/hotels/{$hotel->id}/devices")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.room_code', $room->code)
            ->assertJsonPath('data.0.room_name', $room->name)
            ->assertJsonPath('data.0.room_kind', $room->kind)
            ->assertJsonPath('data.0.screen.room.code', $room->code)
            ->assertJsonPath('data.0.screen.guest', null)
            ->assertJsonPath('data.1.room_code', $room->code);
    }

    public function test_staff_can_rename_and_move_a_paired_tv(): void
    {
        Event::fake([DeviceCommandIssued::class]);

        $hotel = Hotel::factory()->create();
        $from = Room::factory()->create(['hotel_id' => $hotel->id, 'code' => '101']);
        $to = Room::factory()->create(['hotel_id' => $hotel->id, 'code' => '202']);
        $device = Device::factory()->paired($hotel, $from)->create(['name' => 'TV cũ']);
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}", [
            'name' => 'TV sảnh',
            'room_id' => $to->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'TV sảnh')
            ->assertJsonPath('data.room_code', '202')
            ->assertJsonPath('data.screen.room.code', '202');

        $this->assertSame($to->id, $device->fresh()->room_id);
        Event::assertDispatched(DeviceCommandIssued::class, fn (DeviceCommandIssued $event) => $event->command === 'reload');
    }

    public function test_rename_without_moving_does_not_reload_tv(): void
    {
        Event::fake([DeviceCommandIssued::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create(['name' => 'TV']);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}", [
            'name' => 'TV 101',
        ])->assertOk()->assertJsonPath('data.name', 'TV 101');

        Event::assertNotDispatched(DeviceCommandIssued::class);
    }

    public function test_claimed_pin_stops_issuing_token_after_redeem_window(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $staff = $this->staff('receptionist', $hotel);

        $code = $this->postJson('/api/device/pairing-codes', ['name' => 'TV'])->json('code');

        Sanctum::actingAs($staff);
        $this->postJson("/api/cms/hotels/{$hotel->id}/pairing-codes/claim", [
            'code' => $code,
            'room_id' => $room->id,
        ])->assertOk();

        $this->travel(3)->minutes();

        $this->getJson("/api/device/pairing-codes/{$code}")->assertStatus(422);

        $device = Device::query()->where('room_id', $room->id)->firstOrFail();
        $this->assertSame(0, $device->tokens()->count());
    }
}
