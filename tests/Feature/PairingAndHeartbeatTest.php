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

        $this->getJson("/api/device/pairing-codes/{$code}")
            ->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonStructure(['token']);

        $device = Device::query()->where('room_id', $room->id)->firstOrFail();
        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('room.id', $room->id)
            ->assertJsonPath('guest', null);
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
    }
}
