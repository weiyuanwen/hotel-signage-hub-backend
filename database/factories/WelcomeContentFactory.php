<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\WelcomeContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WelcomeContent>
 */
class WelcomeContentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_id' => Room::factory(),
            'guest_display_name' => fake()->name(),
            'message' => 'Welcome',
            'locale' => 'vi',
            'source' => 'manual',
            'is_current' => true,
            'checked_in_at' => now(),
            'template_key' => 'dusk',
        ];
    }
}
