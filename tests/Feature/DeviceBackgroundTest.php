<?php

namespace Tests\Feature;

use App\Domains\Billing\HotelPlan;
use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class DeviceBackgroundTest extends TestCase
{
    use CreatesStaff;

    public function test_two_tvs_in_one_room_can_show_different_backgrounds(): void
    {
        Event::fake([DeviceCommandIssued::class]);

        $hotel = Hotel::factory()->create(['plan' => HotelPlan::PREMIUM]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $roomBg = MediaAsset::factory()->create([
            'hotel_id' => $hotel->id,
            'type' => 'image',
            'disk' => 'external',
            'path' => 'https://example.test/room.jpg',
        ]);
        $room->update(['default_media_id' => $roomBg->id]);

        $left = Device::factory()->paired($hotel, $room)->create(['name' => 'TV trái']);
        $right = Device::factory()->paired($hotel, $room)->create(['name' => 'TV phải']);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/devices/{$left->id}/media", [
            'url' => 'https://youtu.be/jfKfPfyJRdk',
        ])
            ->assertCreated()
            ->assertJsonPath('data.background_url', 'https://youtu.be/jfKfPfyJRdk')
            ->assertJsonPath('data.background_kind', 'video');

        Storage::fake('public');
        $rightUrl = $this->post("/api/cms/hotels/{$hotel->id}/devices/{$right->id}/media", [
            'file' => UploadedFile::fake()->image('right.jpg', 80, 80),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.background_kind', 'image')
            ->json('data.background_url');
        $this->assertNotEmpty($rightUrl);

        Sanctum::actingAs($left);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.background_url', 'https://youtu.be/jfKfPfyJRdk')
            ->assertJsonPath('media.kind', 'video');

        Sanctum::actingAs($right);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.background_url', $rightUrl)
            ->assertJsonPath('media.kind', 'image');

        Event::assertDispatched(DeviceCommandIssued::class, fn (DeviceCommandIssued $event) => $event->device->is($left) && $event->command === 'reload');
        Event::assertDispatched(DeviceCommandIssued::class, fn (DeviceCommandIssued $event) => $event->device->is($right) && $event->command === 'reload');
    }

    public function test_clearing_device_background_falls_back_to_room(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::STANDARD]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $roomBg = MediaAsset::factory()->create([
            'hotel_id' => $hotel->id,
            'type' => 'image',
            'disk' => 'external',
            'path' => 'https://example.test/room.jpg',
        ]);
        $room->update(['default_media_id' => $roomBg->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}/media", [
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertCreated();

        $this->deleteJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}/media")
            ->assertOk()
            ->assertJsonPath('data.background_url', null);

        Sanctum::actingAs($device);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.background_url', 'https://example.test/room.jpg');
    }

    public function test_free_plan_cannot_set_per_tv_background(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::FREE, 'device_limit' => 3]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}/media", [
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertUnprocessable()->assertJsonValidationErrors(['plan']);
    }

    public function test_device_list_exposes_own_background_and_paid_flag(): void
    {
        $hotel = Hotel::factory()->create(['plan' => HotelPlan::PREMIUM]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/devices/{$device->id}/media", [
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertCreated();

        $this->getJson("/api/cms/hotels/{$hotel->id}/devices")
            ->assertOk()
            ->assertJsonPath('data.0.background_url', 'https://youtu.be/dQw4w9WgXcQ')
            ->assertJsonPath('data.0.background_kind', 'video')
            ->assertJsonPath('data.0.screen.media.background_url', 'https://youtu.be/dQw4w9WgXcQ');

        $this->getJson('/api/cms/hotels')
            ->assertOk()
            ->assertJsonPath('data.0.allows_device_backgrounds', true);
    }
}
