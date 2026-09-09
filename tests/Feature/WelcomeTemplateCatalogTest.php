<?php

namespace Tests\Feature;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Domains\Content\WelcomeTemplateKey;
use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Tests\TestCase;

class WelcomeTemplateCatalogTest extends TestCase
{
    public function test_creating_a_hotel_syncs_five_enabled_templates_default_dusk(): void
    {
        $hotel = Hotel::factory()->create();

        $rows = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(5, $rows);
        $this->assertSame(
            ['dusk', 'linen', 'harbor', 'garden', 'stone'],
            $rows->pluck('template_key')->all(),
        );
        $this->assertTrue($rows->every(fn (HotelWelcomeTemplate $row) => $row->is_enabled));
        $this->assertTrue($rows->every(fn (HotelWelcomeTemplate $row) => $row->display_name === null));
        $this->assertSame('dusk', $hotel->fresh()->default_welcome_template_key);
        $this->assertSame(1, $rows[0]->sort_order);
        $this->assertSame(5, $rows[4]->sort_order);
    }

    public function test_sync_hotel_is_idempotent_and_does_not_reenable(): void
    {
        $hotel = Hotel::factory()->create();

        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'garden')
            ->update(['is_enabled' => false, 'display_name' => 'Vườn']);

        app(WelcomeTemplateCatalog::class)->syncHotel($hotel);

        $this->assertSame(5, HotelWelcomeTemplate::query()->where('hotel_id', $hotel->id)->count());
        $garden = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'garden')
            ->firstOrFail();
        $this->assertFalse($garden->is_enabled);
        $this->assertSame('Vườn', $garden->display_name);
        $this->assertFalse(app(WelcomeTemplateCatalog::class)->isEnabled($hotel, 'garden'));
        $this->assertTrue(app(WelcomeTemplateCatalog::class)->isEnabled($hotel, WelcomeTemplateKey::Dusk->value));
    }
}
