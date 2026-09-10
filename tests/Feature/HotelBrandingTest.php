<?php

namespace Tests\Feature;

use App\Domains\Realtime\Events\RoomContentUpdated;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class HotelBrandingTest extends TestCase
{
    use CreatesStaff;

    public function test_manager_lists_weather_regions(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->getJson('/api/cms/weather-regions')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'ha-noi')
            ->assertJsonFragment(['key' => 'sam-son', 'label' => 'Sầm Sơn']);
    }

    public function test_manager_sets_weather_region_and_screen_exposes_coords(): void
    {
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create(['weather_region' => 'ho-chi-minh']);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}", [
            'weather_region' => 'da-nang',
        ])
            ->assertOk()
            ->assertJsonPath('data.weather_region', 'da-nang')
            ->assertJsonPath('data.weather.label', 'Đà Nẵng');

        $this->assertSame('da-nang', $hotel->fresh()->weather_region);
        Event::assertDispatched(RoomContentUpdated::class);

        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('weather.key', 'da-nang')
            ->assertJsonPath('weather.latitude', 16.0544);
    }

    public function test_receptionist_cannot_patch_branding_or_upload(): void
    {
        Storage::fake('public');
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}", [
            'weather_region' => 'hue',
        ])->assertForbidden();

        $this->post("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 80, 80),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->deleteJson("/api/cms/hotels/{$hotel->id}/media/logo")->assertForbidden();

        $this->postJson("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'url' => 'https://youtu.be/jfKfPfyJRdk',
        ])->assertForbidden();

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/media", [
            'url' => 'https://youtu.be/jfKfPfyJRdk',
        ])->assertForbidden();

        $this->deleteJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/media")->assertForbidden();
    }

    public function test_manager_uploads_logo_and_background(): void
    {
        Storage::fake('public');
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $logo = $this->post("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 120, 40),
        ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertStringContainsString('/storage/hotels/', (string) $logo->json('data.logo_url'));

        $this->post("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'file' => UploadedFile::fake()->create('grounds.mp4', 400, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.background_kind', 'video');

        $show = $this->getJson("/api/cms/hotels/{$hotel->id}")->assertOk();
        $this->assertSame('video', $show->json('data.background_kind'));
        $this->assertNotEmpty($show->json('data.logo_url'));
    }

    public function test_manager_clears_logo_and_background(): void
    {
        Storage::fake('public');
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->post("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 120, 40),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'file' => UploadedFile::fake()->image('lobby.jpg', 800, 450),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->deleteJson("/api/cms/hotels/{$hotel->id}/media/logo")
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        $this->deleteJson("/api/cms/hotels/{$hotel->id}/media/background")
            ->assertOk()
            ->assertJsonPath('data.background_url', null)
            ->assertJsonPath('data.background_kind', null);

        $this->assertDatabaseCount('media_assets', 0);

        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('hotel.logo_url', null)
            ->assertJsonPath('media.background_url', null);
    }

    public function test_manager_sets_background_from_video_url(): void
    {
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'url' => 'https://youtu.be/jfKfPfyJRdk',
        ])
            ->assertCreated()
            ->assertJsonPath('data.background_kind', 'video')
            ->assertJsonPath('data.background_url', 'https://youtu.be/jfKfPfyJRdk');

        $this->postJson("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'url' => 'https://example.com/welcome.mp4',
        ])
            ->assertCreated()
            ->assertJsonPath('data.background_url', 'https://example.com/welcome.mp4');

        $this->assertDatabaseCount('media_assets', 1);

        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.kind', 'video')
            ->assertJsonPath('media.background_url', 'https://example.com/welcome.mp4');
    }

    public function test_invalid_video_url_is_rejected(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'url' => 'https://example.com/photo.jpg',
        ])->assertStatus(422);
    }

    public function test_unknown_weather_region_is_rejected(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}", [
            'weather_region' => 'mars',
        ])->assertStatus(422);
    }

    public function test_manager_sets_wifi_and_screen_exposes_it(): void
    {
        Event::fake([RoomContentUpdated::class]);
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}", [
            'wifi_ssid' => 'Pearl-Guest',
            'wifi_password' => 'staywell',
        ])
            ->assertOk()
            ->assertJsonPath('data.wifi_ssid', 'Pearl-Guest')
            ->assertJsonPath('data.wifi_password', 'staywell');

        Event::assertDispatched(RoomContentUpdated::class);

        $device = Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($device);

        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('hotel.wifi.ssid', 'Pearl-Guest')
            ->assertJsonPath('hotel.wifi.password', 'staywell');
    }

    public function test_manager_sets_room_background_without_changing_hotel_default(): void
    {
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        $suite = Room::factory()->create(['hotel_id' => $hotel->id, 'code' => '801']);
        $standard = Room::factory()->create(['hotel_id' => $hotel->id, 'code' => '101']);
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/media", [
            'purpose' => 'background',
            'url' => 'https://example.com/hotel-default.mp4',
        ])->assertCreated();

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$suite->id}/media", [
            'url' => 'https://youtu.be/jfKfPfyJRdk',
        ])
            ->assertCreated()
            ->assertJsonPath('data.background_kind', 'video')
            ->assertJsonPath('data.background_url', 'https://youtu.be/jfKfPfyJRdk');

        $list = $this->getJson("/api/cms/hotels/{$hotel->id}/rooms")->assertOk();
        $this->assertSame('https://youtu.be/jfKfPfyJRdk', collect($list->json('data'))->firstWhere('id', $suite->id)['background_url']);
        $this->assertNull(collect($list->json('data'))->firstWhere('id', $standard->id)['background_url']);

        $suiteTv = Device::factory()->paired($hotel, $suite)->create();
        $standardTv = Device::factory()->paired($hotel, $standard)->create();

        Sanctum::actingAs($suiteTv);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.background_url', 'https://youtu.be/jfKfPfyJRdk');

        Sanctum::actingAs($standardTv);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.background_url', 'https://example.com/hotel-default.mp4');

        Sanctum::actingAs($this->staff('hotel-manager', $hotel));
        $this->deleteJson("/api/cms/hotels/{$hotel->id}/rooms/{$suite->id}/media")
            ->assertOk()
            ->assertJsonPath('data.background_url', null);

        Sanctum::actingAs($suiteTv);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('media.background_url', 'https://example.com/hotel-default.mp4');
    }
}
