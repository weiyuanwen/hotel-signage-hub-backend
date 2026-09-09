<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'code' => (string) fake()->unique()->numberBetween(100, 9999),
            'name' => null,
            'kind' => 'guest',
            'content_revision' => 0,
            'is_active' => true,
        ];
    }

    public function publicArea(): static
    {
        return $this->state(fn () => ['kind' => 'public']);
    }
}
