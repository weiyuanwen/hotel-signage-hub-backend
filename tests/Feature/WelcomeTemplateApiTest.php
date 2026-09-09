<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
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
}
