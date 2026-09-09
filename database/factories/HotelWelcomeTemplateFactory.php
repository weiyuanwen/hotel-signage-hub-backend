<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelWelcomeTemplate> */
class HotelWelcomeTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'template_key' => 'dusk',
            'is_enabled' => true,
            'display_name' => null,
            'sort_order' => 1,
        ];
    }
}
