<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class WelcomeTemplateApiTest extends TestCase
{
    use CreatesStaff;

    public function test_receptionist_lists_only_enabled_templates(): void
    {
        $hotel = Hotel::factory()->create();
        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'stone')
            ->update(['is_enabled' => false]);

        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $keys = $this->getJson("/api/cms/hotels/{$hotel->id}/welcome-templates")
            ->assertOk()
            ->assertJsonPath('data.default_key', 'dusk')
            ->assertJsonCount(4, 'data.templates')
            ->json('data.templates');

        $this->assertSame(['dusk', 'linen', 'harbor', 'garden'], array_column($keys, 'key'));
    }

    public function test_manager_lists_disabled_templates_too(): void
    {
        $hotel = Hotel::factory()->create();
        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'stone')
            ->update(['is_enabled' => false, 'display_name' => 'Tối đá']);

        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->getJson("/api/cms/hotels/{$hotel->id}/welcome-templates")
            ->assertOk()
            ->assertJsonCount(5, 'data.templates')
            ->assertJsonPath('data.templates.4.key', 'stone')
            ->assertJsonPath('data.templates.4.is_enabled', false)
            ->assertJsonPath('data.templates.4.label', 'Tối đá')
            ->assertJsonPath('data.templates.4.built_in_name', 'Đá ấm');
    }

    public function test_receptionist_cannot_patch_templates(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'is_enabled' => false,
        ])->assertForbidden();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'linen',
        ])->assertForbidden();
    }

    public function test_manager_renames_and_disables_non_default_template(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/garden", [
            'display_name' => 'Trà sen',
            'is_enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.templates.3.label', 'Trà sen')
            ->assertJsonPath('data.templates.3.is_enabled', false);
    }

    public function test_cannot_disable_default_or_last_enabled_template(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/dusk", [
            'is_enabled' => false,
        ])->assertStatus(422);

        foreach (['linen', 'harbor', 'garden', 'stone'] as $key) {
            $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/{$key}", [
                'is_enabled' => false,
            ])->assertOk();
        }

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/dusk", [
            'is_enabled' => false,
        ])->assertStatus(422);
    }

    public function test_manager_sets_default_to_enabled_key_only(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'harbor',
        ])
            ->assertOk()
            ->assertJsonPath('data.default_key', 'harbor');

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/stone", [
            'is_enabled' => false,
        ])->assertOk();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'stone',
        ])->assertStatus(422);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'not-a-template',
        ])->assertStatus(422);
    }

    public function test_empty_display_name_clears_to_built_in_label(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'display_name' => 'Sáng',
        ])->assertOk();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'display_name' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.templates.1.display_name', null)
            ->assertJsonPath('data.templates.1.label', 'Sáng nhẹ');
    }

    public function test_manager_saves_layout_and_list_returns_normalized_look(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->getJson("/api/cms/hotels/{$hotel->id}/welcome-templates")
            ->assertOk()
            ->assertJsonPath('data.templates.1.layout.background.source', 'gallery')
            ->assertJsonPath('data.templates.1.layout.font', 'be-vietnam');

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'layout' => [
                'background' => ['source' => 'gallery', 'gallery_id' => 'sunlit'],
                'tone' => 'warm',
                'font' => 'outfit',
                'slogan' => 'Kỳ nghỉ bắt đầu từ đây',
                'colors' => [
                    'name' => '#fff6ea',
                    'slogan' => '#f3e6d4',
                    'muted' => '#d9cbb8',
                ],
                'sizes' => [
                    'name' => 6.2,
                    'slogan' => 2.8,
                    'message' => 2.1,
                    'room' => 1.8,
                ],
                'slots' => [
                    'logo' => ['x' => 10, 'y' => 20, 'visible' => true],
                    'name' => ['x' => 12, 'y' => 44],
                    'slogan' => ['x' => 12, 'y' => 58, 'visible' => true],
                    'message' => ['x' => 12, 'y' => 68, 'visible' => true],
                    'room' => ['x' => 86, 'y' => 88],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.templates.1.layout.tone', 'warm')
            ->assertJsonPath('data.templates.1.layout.font', 'outfit')
            ->assertJsonPath('data.templates.1.layout.slogan', 'Kỳ nghỉ bắt đầu từ đây')
            ->assertJsonPath('data.templates.1.layout.background.gallery_id', 'sunlit')
            ->assertJsonPath('data.templates.1.layout.colors.name', '#fff6ea')
            ->assertJsonPath('data.templates.1.layout.colors.slogan', '#f3e6d4')
            ->assertJsonPath('data.templates.1.layout.colors.muted', '#d9cbb8')
            ->assertJsonPath('data.templates.1.layout.sizes.name', 6.2)
            ->assertJsonPath('data.templates.1.layout.sizes.slogan', 2.8)
            ->assertJsonPath('data.templates.1.layout.sizes.message', 2.1)
            ->assertJsonPath('data.templates.1.layout.sizes.room', 1.8)
            ->assertJsonPath('data.templates.1.layout.slots.name.x', 12);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'layout' => [
                'tone' => 'neon',
                'font' => 'comic',
                'background' => ['source' => 'gallery', 'gallery_id' => 'not-a-photo'],
            ],
        ])->assertStatus(422);
    }

    public function test_manager_uploads_and_clears_template_background(): void
    {
        Storage::fake('public');
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->post("/api/cms/hotels/{$hotel->id}/welcome-templates/linen/media", [
            'file' => UploadedFile::fake()->image('pool.jpg', 800, 450),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.templates.1.layout.background.source', 'upload');

        $url = $this->getJson("/api/cms/hotels/{$hotel->id}/welcome-templates")
            ->assertOk()
            ->json('data.templates.1.background_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('/storage/hotels/', $url);

        $this->deleteJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen/media")
            ->assertOk()
            ->assertJsonPath('data.templates.1.layout.background.source', 'gallery');
    }

    public function test_receptionist_cannot_upload_template_background(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->post("/api/cms/hotels/{$hotel->id}/welcome-templates/linen/media", [
            'file' => UploadedFile::fake()->image('pool.jpg', 800, 450),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }
}
