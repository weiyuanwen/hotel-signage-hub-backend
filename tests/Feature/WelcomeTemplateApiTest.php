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
}
